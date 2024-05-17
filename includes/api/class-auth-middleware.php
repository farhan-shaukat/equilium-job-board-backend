<?php

namespace THJB\Api;

use Exception;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use Firebase\JWT\JWT;

/**
 * The public-facing functionality of the plugin.
 *
 * https://github.com/usefulteam/jwt-auth/blob/master/class-auth.php
 */

class AuthMiddleware
{

    /**
     * Store errors to display if the JWT is wrong
     *
     * @var WP_REST_Response
     */
    private $jwt_error = null;

    /**
     * Collection of translate-able messages.
     *
     * @var array
     */
    private $messages = array();

    /**
     * The REST API slug.
     *
     * @var string
     */
    private $rest_api_slug = 'wp-json';

    /**
     * Setup action & filter hooks.
     */
    public function __construct() {
        $this->messages = array(
            'jwt_auth_no_auth_header'  => __( 'Authorization header not found.', 'jwt-auth' ),
            'jwt_auth_bad_auth_header' => __( 'Authorization header malformed.', 'jwt-auth' ),
        );
    }

    /**
     * Add CORs suppot to the request.
     */
    public function disable_cors_support()
    {
        $enable_cors = true;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $enable_cors = false;
        }

        $enable_cors = defined( 'JWT_AUTH_CORS_ENABLE' ) ? JWT_AUTH_CORS_ENABLE : $enable_cors;

        if ( ! $enable_cors ) {
            header( sprintf( 'Access-Control-Allow-Origin: %s', '*' ) );
        }
    }

    /**
     * Get the token issuer.
     *
     * @return string The token issuer (iss).
     */
    public function get_iss() {
        return apply_filters( 'jwt_auth_iss', get_bloginfo( 'url' ) );
    }

    /**
     * Get the supported jwt auth signing algorithm.
     *
     * @see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
     *
     * @return string $alg
     */
    public function get_alg() {
        return apply_filters( 'jwt_auth_alg', 'HS256' );
    }

    /**
     * Determine if given response is an error response.
     *
     * @param mixed $response The response.
     * @return boolean
     */
    public function is_error_response( $response ) {

        if ( ! empty( $response ) && property_exists( $response, 'data' ) && is_array( $response->data ) ) {
            if ( isset( $response->data['success'] ) && ! $response->data['success'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Main validation function, this function try to get the Autentication
     * headers and decoded.
     *
     * @param bool $return_response Either to return full WP_REST_Response or to return the payload only.
     *
     * @return WP_REST_Response | Array Returns WP_REST_Response or token's $payload.
     */
    public function validate_token( $return_response = true ) {
        /**
         * Looking for the HTTP_AUTHORIZATION header, if not present just
         * return the user.
         */
        $headerkey = apply_filters( 'th_jwt_auth_authorization_header', 'HTTP_BOARD_AUTH' );
        $auth      = isset( $_SERVER[ $headerkey ] ) ? $_SERVER[ $headerkey ] : false;

        // Double check for different auth header string (server dependent).
        if ( ! $auth ) {
            $auth = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;
        }

        if ( ! $auth ) {
            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 403,
                    'code'       => 'jwt_auth_no_auth_header',
                    'message'    => $this->messages['jwt_auth_no_auth_header'],
                    'data'       => array(),
                ), 403
            );
        }

        /**
         * The HTTP_AUTHORIZATION is present, verify the format.
         * If the format is wrong return the user.
         */
        list($token) = sscanf( $auth, 'Bearer %s' );

        if ( ! $token ) {
            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 403,
                    'code'       => 'jwt_auth_bad_auth_header',
                    'message'    => $this->messages['jwt_auth_bad_auth_header'],
                    'data'       => array(),
                ), 403
            );
        }

        // Get the Secret Key.
        $secret_key = defined( 'THJB_JWT_AUTH_SECRET_KEY' ) ? THJB_JWT_AUTH_SECRET_KEY : false;

        if ( ! $secret_key ) {
            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 403,
                    'code'       => 'jwt_auth_bad_config',
                    'message'    => __( 'JWT is not configured properly.', 'jwt-auth' ),
                    'data'       => array(),
                ),
                403
            );
        }

        // Try to decode the token.
        try {
            $alg     = $this->get_alg();
            $payload = JWT::decode( $token, $secret_key, array( $alg ) );

            // The Token is decoded now validate the iss.
            if ( $payload->iss !== $this->get_iss() ) {
                // The iss do not match, return error.
                return new WP_REST_Response(
                    array(
                        'success'    => false,
                        'statusCode' => 403,
                        'code'       => 'jwt_auth_bad_iss',
                        'message'    => __( 'The iss do not match with this server.', 'jwt-auth' ),
                        'data'       => array(),
                    ),
                    403
                );
            }

            // Check the user id existence in the token.
            if ( ! isset( $payload->data->user->id ) ) {
                // No user id in the token, abort!!
                return new WP_REST_Response(
                    array(
                        'success'    => false,
                        'statusCode' => 403,
                        'code'       => 'jwt_auth_bad_request',
                        'message'    => __( 'User ID not found in the token.', 'jwt-auth' ),
                        'data'       => array(),
                    ),
                    403
                );
            }

            // So far so good, check if the given user id exists in db.
            $user = get_user_by( 'id', $payload->data->user->id );

            if ( ! $user ) {
                // No user id in the token, abort!!
                return new WP_REST_Response(
                    array(
                        'success'    => false,
                        'statusCode' => 403,
                        'code'       => 'jwt_auth_user_not_found',
                        'message'    => __( "User doesn't exist", 'jwt-auth' ),
                        'data'       => array(),
                    ),
                    403
                );
            }

            // Check extra condition if exists.
            $failed_msg = apply_filters( 'jwt_auth_extra_token_check', '', $user, $token, $payload );

            if ( ! empty( $failed_msg ) ) {
                // No user id in the token, abort!!
                return new WP_REST_Response(
                    array(
                        'success'    => false,
                        'statusCode' => 403,
                        'code'       => 'jwt_auth_obsolete_token',
                        'message'    => __( 'Token is obsolete', 'jwt-auth' ),
                        'data'       => array(),
                    ),
                    403
                );
            }

            // Everything looks good, return the payload if $return_response is set to false.
            if ( ! $return_response ) {
                return $payload;
            }

            if ( ! in_array( 'employer' , (array) $user->roles )) {
                return new WP_REST_Response(
                    array(
                        'success'    => false,
                        'statusCode' => 403,
                        'code'       => 'jwt_auth_error',
                        'message'    => 'Sorry, You have no permissions',
                        'data'       => array(),
                    ),
                    403
                );
            }

            $response = array(
                'success'    => true,
                'statusCode' => 200,
                'code'       => 'jwt_auth_valid_token',
                'message'    => __( 'Token is valid', 'jwt-auth' ),
                'data'       => array(),
            );

            $response = apply_filters( 'jwt_auth_valid_token_response', $response, $user, $token, $payload );

            // Otherwise, return success response.
            return new WP_REST_Response( $response );
        } catch ( Exception $e ) {
            // Something is wrong when trying to decode the token, return error response.
            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 403,
                    'code'       => 'jwt_auth_invalid_token',
                    'message'    => $e->getMessage(),
                    'data'       => array(),
                ),
                403
            );
        }
    }

    /**
     * This is our Middleware to try to authenticate the user according to the token sent.
     *
     * @param int|bool $user_id User ID if one has been determined, false otherwise.
     * @return int|bool User ID if one has been determined, false otherwise.
     */
    public function determine_current_user( $user_id ) {
        /**
         * This hook only should run on the REST API requests to determine
         * if the user in the Token (if any) is valid, for any other
         * normal call ex. wp-admin/.* return the user.
         */
        $this->rest_api_slug = get_option( 'permalink_structure' ) ? rest_get_url_prefix() : '?rest_route=/';

        $valid_api_uri = strpos( $_SERVER['REQUEST_URI'], $this->rest_api_slug );

        if ( ! $valid_api_uri ) {
            return $user_id;
        }

        $auth_required_routes = apply_filters( 'thjb_closed_by_token_api_urls', array() );

        if ( $this->is_closed($auth_required_routes) ) {
            $payload = $this->validate_token( false );

            // If $payload is an error response, then return the default $user_id.
            if ( $this->is_error_response( $payload ) ) {
                $this->jwt_error = $payload;

                return $user_id;
            }
            return $payload->data->user->id;

        } else {
            // login user if we can
            $no_required_auth = apply_filters( 'thjb_auth_by_token_api_urls', array() );

            $payload = $this->validate_token( false );
            if ( $this->is_error_response( $payload ) ) {
                return $user_id;
            }
            return $payload->data->user->id;

        }

        return $user_id;
    }

    /**
     * Check whether or not current endpoint is whitelisted.
     *
     * @return bool
     */
    public function is_closed($closed_routes) {

        if ( empty( $closed_routes ) || ! is_array( $closed_routes ) ) {
            return false;
        }

        $request_uri    = $_SERVER['REQUEST_URI'];
        $request_method = $_SERVER['REQUEST_METHOD'];

        if ( 'OPTIONS' === $request_method ) {
            return false;
        }

        $prefix      = get_option( 'permalink_structure' ) ? rest_get_url_prefix() : '?rest_route=/';
        $split       = explode( $prefix, $request_uri );
        $request_uri = '/' . $prefix . ( ( count( $split ) > 1 ) ? $split[1] : $split[0] );

        // Only use string before "?" sign if permalink is enabled.
        if ( get_option( 'permalink_structure' ) && false !== stripos( $request_uri, '?' ) ) {
            $split       = explode( '?', $request_uri );
            $request_uri = $split[0];
        }

        // Let's remove trailingslash for easier checking.
        $request_uri = untrailingslashit( $request_uri );

        foreach ( $closed_routes as $endpoint ) {

            $method = '';
            $check_params = false;

            if ( is_array( $endpoint ) ) {
                $method = $endpoint['method'];
                $path   = $endpoint['path'];

                if ( isset($endpoint['check_if_empty']) ) {
                    $check_params = true;
                }

            } else {
                $method = 'all';
                $path   = $endpoint;
            }

            // If the endpoint doesn't contain * sign.
            if ( false === stripos( $path, '*' ) ) {
                $path = untrailingslashit( $path );

                if ( $path === $request_uri && ( 'all' === $method || $method === $request_method ) ) {

                    if ( $check_params ) {
                        if( ! empty( $_REQUEST[ $endpoint['check_if_empty'] ]) ) {
                            return false;
                        }
                    }

                    return true;
                }
            } else {

                $regex = '/' . str_replace( '/', '\/', $path ) . '/';

                if ( preg_match( $regex, $request_uri ) && ( 'all' === $method || $method === $request_method ) ) {

                    if ( $check_params ) {
                        if( ! empty( $_REQUEST[ $endpoint['check_if_empty'] ]) ) {
                            return false;
                        }
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Filter to hook the rest_pre_dispatch, if there is an error in the request
     * send it, if there is no error just continue with the current request.
     *
     * @param mixed           $result Can be anything a normal endpoint can return, or null to not hijack the request.
     * @param WP_REST_Server  $server Server instance.
     * @param WP_REST_Request $request The request.
     *
     * @return mixed $result
     */
    public function rest_pre_dispatch( $result, WP_REST_Server $server, WP_REST_Request $request ) {
        if ( $this->is_error_response( $this->jwt_error ) ) {
            return $this->jwt_error;
        }

        return $result;
    }

}
