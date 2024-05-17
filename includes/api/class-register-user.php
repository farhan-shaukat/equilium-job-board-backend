<?php


namespace THJB\Api;

use WP_REST_Request;
use WP_Error;

class RegisterUser
{
    private $api_base;

    public function __construct($base)
    {
        $this->api_base = $base;
    }

    public function register_routes()
    {
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
    }

    public function register(WP_REST_Request $request)
    {
        $params = $request->get_params();

        $user = $this->process_user_register($params);

        if ( ! $user || is_wp_error( $user ) ) {
            return $user;
        }

        return [
            'success' => true
        ];
    }

    public function register_and_apply(WP_REST_Request $request)
    {
        global $thjb_is_send_candidate_success;

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

        // send data to jobdiva
        do_action('thjb_send_candidate_to_external_api', $params, $files['cv_file']);

        // save job to applied jobs list
        update_user_meta($user, 'applied_jobs_list', [ $params['job_id'] ]);

        return [
            'success' => $thjb_is_send_candidate_success
        ];
    }

    private function process_user_register($params)
    {
        $exists = email_exists( $params['email'] );
        if ($exists) {
            return new WP_Error('register_email_error', 'Maybe you already have an account', ['status' => 409]);
        }

        $password = $params['pass'];
        $password_repeat = $params['pass_repeat'];

        if ( strcmp($password, $password_repeat) !== 0 ) {
            return new WP_Error('register_error', 'Passwords are not equal', ['status' => 400]);
        }

        $strong_password = preg_match('/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9]).{6,}$/', $password);
        if ( ! $strong_password ) {
            return new WP_Error('register_error', 'Weak Password', ['status' => 400]);
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

}