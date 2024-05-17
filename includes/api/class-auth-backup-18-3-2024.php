<?php
namespace THJB\Api;

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
class Auth {

    private $api_base;

    public function __construct($base)
    {
        $this->api_base = $base;
    }

    public function register_routes()
    {
        register_rest_route($this->api_base, 'token', [
                'methods'             => 'POST',
                'callback'            => [$this, 'get_token'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route($this->api_base, 'logout', [
                'methods'             => 'POST',
                'callback'            => [$this, 'logout'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => []
            ]
        );

        register_rest_route($this->api_base, 'register', [
                'methods'             => 'POST',
                'callback'            => [$this, 'register'],
                'permission_callback' => '__return_true',
                'args' => [
                    'first_name' => [
                        'required'          => true,
                        'type'              => 'string',
                        'minLength'         => 2,
                        'maxLength'         => 100,
                    ],
                    'last_name' => [
                        'required'          => true,
                        'type'              => 'string',
                        'minLength'         => 2,
                        'maxLength'         => 100,
                    ],
                    'email' => [
                        'required'          => true,
                        'type'              => 'string',
                        'format'            => 'email',
                        'maxLength'         => 100,
                        'sanitize_callback' => 'sanitize_email',
                        'validate_callback' => 'is_email'
                    ],
                    'pass' => thjb_get_pass_field_params(),
                    'pass_repeat' => thjb_get_pass_field_params(),
                ]
            ]
        );

        register_rest_route($this->api_base, 'apply-job/register', [
                'methods'             => 'POST',
                'callback'            => [$this, 'register_and_apply'],
                'permission_callback' => '__return_true',
                'args' => [
                    'job_id' => [
                        'description'         => 'Applied job ID',
                        'type'                => 'integer',
                        'required'            => true,
                        'validate_callback'   => function($param) {
                            $post = get_post((int)$param);
                            if ( ! empty($post)
                                && 'jobs' == $post->post_type
                                && 'publish' == $post->post_status ) {
                                return true;
                            }

                            return false;
                        }
                    ],
                    'first_name' => [
                        'required'          => true,
                        'type'              => 'string',
                        'minLength'         => 2,
                        'maxLength'         => 100,
                    ],
                    'last_name' => [
                        'required'          => true,
                        'type'              => 'string',
                        'minLength'         => 2,
                        'maxLength'         => 100,
                    ],
                    'email' => [
                        'required'          => true,
                        'type'              => 'string',
                        'format'            => 'email',
                        'maxLength'         => 100,
                        'sanitize_callback' => 'sanitize_email',
                        'validate_callback' => 'is_email'
                    ],
                    'pass' => thjb_get_pass_field_params(),
                    'pass_repeat' => thjb_get_pass_field_params(),
                ]
            ]
        );

        register_rest_route($this->api_base, 'apply-job/login', [
                'methods'             => 'POST',
                'callback'            => [$this, 'login_and_apply'],
                'permission_callback' => '__return_true',
                'args' => [
                    'job_id' => [
                        'description'         => 'Applied job ID',
                        'type'                => 'integer',
                        'required'            => true,
                        'validate_callback'   => function($param) {
                            $post = get_post((int)$param);
                            if ( ! empty($post)
                                && 'jobs' == $post->post_type
                                && 'publish' == $post->post_status ) {
                                return true;
                            }

                            return false;
                        }
                    ],
                    'username' => [
                        'required'          => true,
                        'type'              => 'string',
                    ],
                    'password' => thjb_get_pass_field_params(),
                ]
            ]
        );

        register_rest_route( $this->api_base, 'reset-password', [
            'methods'  => 'POST',
            'callback'            => [$this, 'send_reset_password_code'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'format'            => 'email',
                    'maxLength'         => 100,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email'
                ]
            ],
        ] );

        register_rest_route($this->api_base, 'set-password', [
            'methods'  => 'POST',
            'callback' => [$this, 'set_password'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'format'            => 'email',
                    'maxLength'         => 100,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email'
                ],
                'code' => [
                    'required'     => true,
                    'type'         => 'string',
                    'sanitize_callback' => function($param){
                        return trim($param);
                    },
                    'validate_callback' => function($param) {
                        $p = trim($param);

                        if ( 0 < strlen($p) ) {
                            return true;
                        }

                        return false;
                    }
                ],
                'pass' => thjb_get_pass_field_params(),
                'pass_repeat' => thjb_get_pass_field_params(),
            ],
        ] );
    }

    /**
     * Get token by sending POST request
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response The response.
     */
    public function get_token( WP_REST_Request $request ) {
        $secret_key = defined( 'THJB_JWT_AUTH_SECRET_KEY' ) ? THJB_JWT_AUTH_SECRET_KEY : false;

        // First thing, check the secret key if not exist return a error.
        if ( ! $secret_key ) {
            return $this->get_auth_config_error();
        }

        $login_error = new WP_REST_Response(
            array(
                'success'    => false,
                'statusCode' => 403,
                'code'       => 'invalid_creds',
                'message'    => "Please, check login and password",
                'data'       => array(),
            ),
            403
        );

        $username    = $request->get_param( 'username' );
        $password    = $request->get_param( 'password' );

        $exists = email_exists( $username );
        if ( ! $exists ) {
            $exists = username_exists( $username );
            if ( ! $exists ) {
                return $login_error;
            }
        }

        $user = wp_authenticate( $username, $password );

        // If the authentication is failed return error response.
        if ( is_wp_error( $user ) ) {
            return $login_error;
        }

        // Valid credentials, the user exists, let's generate the token.
        return $this->generate_token( $user, false );
    }

    public function logout()
    {
        wp_logout();

        return [
            'success'    => true,
        ];
    }

    public function register(WP_REST_Request $request)
    {
        $secret_key = defined( 'THJB_JWT_AUTH_SECRET_KEY' ) ? THJB_JWT_AUTH_SECRET_KEY : false;

        // First thing, check the secret key if not exist return a error.
        if ( ! $secret_key ) {
            return $this->get_auth_config_error();
        }

        $params = $request->get_params();

        // check uploaded file
        $files = $request->get_file_params();
        if ( ! empty($files['cv_file']) ) {
            $result = thjb_check_uploaded_by_api_file($files, 'cv_file');
            if ( is_wp_error($result) ) {
                return $result;
            }
        }

        $user = $this->process_user_register($params);

        if ( ! $user || is_wp_error( $user ) ) {
            return $user;
        }

        $user = wp_authenticate( $params['email'], $params['pass'] );

        if ( is_wp_error( $user ) ) {
            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 400,
                    'code'       => 'login_error',
                    'message'    => "Please, try login manually in login page",
                    'data'       => [],
                ),
                400
            );
        }

        if ( ! empty($files['cv_file']) ) {
            $saved_resume = thjs_add_user_resume_file($user->ID);
            if ( is_wp_error( $saved_resume ) ) {
                return $saved_resume;
            }
        }

        // Valid credentials, the user exists, let's generate the token.
        return $this->generate_token( $user, false );
    }

    public function register_and_apply(WP_REST_Request $request)
    {
        global $thjb_is_send_candidate_success;

        $secret_key = defined( 'THJB_JWT_AUTH_SECRET_KEY' ) ? THJB_JWT_AUTH_SECRET_KEY : false;

        // First thing, check the secret key if not exist return a error.
        if ( ! $secret_key ) {
            return $this->get_auth_config_error();
        }

        // check uploaded file
        $files = $request->get_file_params();

        $result = thjb_check_uploaded_by_api_file($files, 'cv_file');

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $params = $request->get_params();

        // register user
        $user = $this->process_user_register($params);

        if ( ! $user || is_wp_error( $user ) ) {
            return $user;
        }

        $user = wp_authenticate( $params['email'], $params['pass'] );

        if ( is_wp_error( $user ) ) {
            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 400,
                    'code'       => 'login_error',
                    'message'    => "Please, try login manually in login page",
                    'data'       => [],
                ),
                400
            );
        }

        // save user resume file
        $saved_resume = thjs_add_user_resume_file($user->ID);
        if ( is_wp_error( $saved_resume ) ) {
            return $saved_resume;
        }

        // Valid credentials, the user exists, let's generate the token.
        $token_result = $this->generate_token( $user, false );

        $candidate_data = [
            'job_id'       => $request->get_param( 'job_id' ),
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->user_email,
        ];

        // send data to jobdiva
        do_action('thjb_send_candidate_to_external_api', $candidate_data, $saved_resume);

        // save job to applied jobs list
        update_user_meta($user->ID, 'applied_jobs_list', [ $params['job_id'] ]);

        $token_result['success'] = $thjb_is_send_candidate_success;
        $token_result['meta']['applied_id'] = $params['job_id'];

        return $token_result;
    }

    private function process_user_register($params)
    {
        $exists = email_exists( $params['email'] );
        if ($exists) {
            return new WP_Error('email_conflict_error', 'Maybe you already have an account', ['status' => 409]);
        }

        $password = $params['pass'];
        $password_repeat = $params['pass_repeat'];

        if ( strcmp($password, $password_repeat) !== 0 ) {
            return new WP_Error('pass_repeat_error', 'Passwords are not equal', ['status' => 400]);
        }

        $strong_password = preg_match('/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9]).{6,}$/', $password);
        if ( ! $strong_password ) {
            return new WP_Error('password_weak_error', 'Weak Password', ['status' => 400]);
        }

        $sanitized_user_login = sanitize_user( $params['email'] );
        $user_role = apply_filters('thjb_registered_user_role', 'subscriber');

        $user_id = wp_insert_user( [
            'user_login'    => $sanitized_user_login,
            'user_pass'     => $password,
            'user_email'    => $params['email'],
            'first_name'    => $params['first_name'],
            'last_name'     => $params['last_name'],
            'role'          => $user_role
        ]);

        return $user_id;
    }

    public function login_and_apply( WP_REST_Request $request )
    {
        global $thjb_is_send_candidate_success;

        $secret_key = defined( 'THJB_JWT_AUTH_SECRET_KEY' ) ? THJB_JWT_AUTH_SECRET_KEY : false;

        // First thing, check the secret key if not exist return a error.
        if ( ! $secret_key ) {
            return $this->get_auth_config_error();
        }

        $login_error = new WP_REST_Response(
            array(
                'success'    => false,
                'statusCode' => 403,
                'code'       => 'invalid_creds',
                'message'    => "Please, check login and password",
                'data'       => array(),
            ),
            403
        );

        $username    = $request->get_param( 'username' );
        $password    = $request->get_param( 'password' );

        $exists = email_exists( $username );
        if ( ! $exists ) {
            $exists = username_exists( $username );
            if ( ! $exists ) {
                return $login_error;
            }
        }

        $files = $request->get_file_params();
        $result = thjb_check_uploaded_by_api_file($files, 'cv_file');

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $user = wp_authenticate( $username, $password );

        // If the authentication is failed return error response.
        if ( is_wp_error( $user ) ) {
            return $login_error;
        }

        // generate token and save result
        $response = $this->generate_token( $user, false );

        $applied_job_id = $request->get_param( 'job_id' );

        $applied_jobs = get_user_meta($user->ID, 'applied_jobs_list', 1);
        if ( empty($applied_jobs) ) {
            $applied_jobs = [];
        }

        $response['meta']['applied_id'] = $applied_job_id;

        if ( in_array($applied_job_id, $applied_jobs) ) {

            $response['success'] = false;
            $response['code'] = 'thjb_applied_jobs_conflict';
            $response['message'] = __('You have already applied for this job');

            new WP_REST_Response(
                $response,
                400
            );
        }

        // send user data to Jobdiva
        $candidate_data = [
            'job_id'       => $applied_job_id,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->user_email,
        ];

        do_action('thjb_send_candidate_to_external_api', $candidate_data, $files['cv_file']);

        // save job to applied jobs list
        $applied_jobs[] = $applied_job_id;

        $applied_jobs = array_unique($applied_jobs);

        update_user_meta($user->ID, 'applied_jobs_list', $applied_jobs);

        // change status to send data result
        $response['success'] = $thjb_is_send_candidate_success;

        return $response;
    }

    public function send_reset_password_code( WP_REST_Request $request )
    {
        $user = get_user_by( 'email', $request['email'] );
        if ( $user ) {

            require_once THJB_PLUGIN_INC_PATH . 'api/class-api-user-model.php';

            $api_user = new ApiUserModel($user->ID);

            $api_user->send_reset_code();

            return [
                'success' => true
            ];
        }

        return new WP_Error('email_not_found', 'There are no account for this email', ['status' => 400]);
    }

    public function set_password( WP_REST_Request $request )
    {
        $user = get_user_by( 'email', $request['email'] );

        if ( $user ) {

            require_once THJB_PLUGIN_INC_PATH . 'api/class-api-user-model.php';

            $api_user = new ApiUserModel($user->ID);

            $validation = $api_user->validate_code($request['code']);

            if ( is_wp_error($validation) ) {
                return $validation;
            }

            $password = $request['pass'];
            $password_repeat = $request['pass_repeat'];

            if ( strcmp($password, $password_repeat) !== 0 ) {
                return new WP_Error('pass_repeat_error', 'Passwords are not equal',  ['status' => 400]);
            }

            wp_set_password( $password, $api_user->ID );
            $api_user->clear_restore_password_code();

            return [
                'success' => true
            ];

        }

        return new WP_Error('email_not_found', 'There are no account for this email', ['status' => 400]);
    }

    /**
     * Generate token
     *
     * @param WP_User $user The WP_User object.
     * @param bool    $return_raw Whether or not to return as raw token string.
     *
     * @return WP_REST_Response|string Return as raw token string or as a formatted WP_REST_Response.
     */
    public function generate_token( $user, $return_raw = true ) {
        $secret_key = defined( 'THJB_JWT_AUTH_SECRET_KEY' ) ? THJB_JWT_AUTH_SECRET_KEY : false;
        $issued_at  = time();
        $not_before = $issued_at;
        $not_before = apply_filters( 'jwt_auth_not_before', $not_before, $issued_at );
        $expire     = $issued_at + ( DAY_IN_SECONDS * 7 );
        $expire     = apply_filters( 'jwt_auth_expire', $expire, $issued_at );

        $payload = array(
            'iss'  => $this->get_iss(),
            'iat'  => $issued_at,
            'nbf'  => $not_before,
            'exp'  => $expire,
            'data' => array(
                'user' => array(
                    'id' => $user->ID,
                ),
            ),
        );

        $alg = $this->get_alg();

        // Let the user modify the token data before the sign.
        $token = JWT::encode( apply_filters( 'jwt_auth_payload', $payload, $user ), $secret_key, $alg );

        // If return as raw token string.
        if ( $return_raw ) {
            return $token;
        }

        // The token is signed, now create object with basic info of the user.
        $response = array(
            'success'    => true,
            'code'       => 'jwt_auth_valid_credential',
            'message'    => __( 'Credential is valid', 'jwt-auth' ),
            'data'       => array(
                'token'       => $token,
                'id'          => $user->ID,
                'email'       => $user->user_email,
                'firstName'   => $user->first_name,
                'lastName'    => $user->last_name,
            ),
        );
        wp_set_auth_cookie($user->ID, true);
        // Let the user modify the data before send it back.
        return apply_filters( 'jwt_auth_valid_credential_response', $response, $user );
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

    public function get_auth_config_error()
    {
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

}
