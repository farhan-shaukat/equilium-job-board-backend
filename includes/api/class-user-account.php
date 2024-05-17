<?php


namespace THJB\Api;

use WP_REST_Request;
use WP_Error;

class UserAccount
{
    private $api_base;

    public function __construct($base)
    {
        $this->api_base = $base;
    }

    public function register_routes()
    {
        register_rest_route($this->api_base, 'user', [
                'methods'             => 'GET',
                'callback'            => [$this, 'get'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => [
                    'add_consents' => [
                        'required'          => false,
                        'type'              => 'input',
                    ],
                ]
            ]
        );

        register_rest_route($this->api_base, 'user', [
                'methods'             => 'POST',
                'callback'            => [$this, 'update'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
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
                ]
            ]
        );

        register_rest_route($this->api_base, 'user/suggested-jobs', [
                'methods'             => 'GET',
                'callback'            => [$this, 'suggested'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => [
                    'per_page' => [
                        'description'         => 'Posts count to show',
                        'type'                => 'integer',
                        'default'             => 20
                    ]
                ]
            ]
        );

        register_rest_route($this->api_base, 'user/applied-jobs', [
                'methods'             => 'GET',
                'callback'            => [$this, 'applied_jobs'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => [
                    'per_page' => [
                        'description'         => 'Posts count to show',
                        'type'                => 'integer',
                        'default'             => 5
                    ]
                ]
            ]
        );

        register_rest_route($this->api_base, 'user/rtbf', [
                'methods'             => 'POST',
                'callback'            => [$this, 'rtbf_request'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => []
            ]
        );

    }

    public function get(WP_REST_Request $request)
    {
        $user = wp_get_current_user();
        $params = $request->get_params();

        $result = $this->prepare_item($user);

        $response =  ['data' => $result];

        if ( ! empty($params['add_consents'])
            && function_exists('get_field') ) {
            $response['consents'] = get_field('my_consents', 'options');
        }

        return $response;
    }

    public function update(WP_REST_Request $request)
    {
        $params = $request->get_params();

        $user = wp_get_current_user();

        if ( $user->user_email !== $params['email'] ) {
            $exists = email_exists($params['email']);
            if ( $exists ) {
                return new WP_Error('change_email_error', 'You cant use this email', ['status' => 409]);
            }
        }

        $updated = wp_update_user([
            'ID'          => $user->ID,
            'first_name'  => $params['first_name'],
            'last_name'   => $params['last_name'],
            'user_email'  => $params['email'],
        ]);

        if ( ! is_wp_error( $updated ) ) {

            $user = get_user_by('id', $updated);

            $result = $this->prepare_item($user);

            return ['data' => $result];

        }
        return new WP_Error('update_user_error', 'Something went wrong', ['status' => 400]);
    }

    public function suggested(WP_REST_Request $request)
    {
        $response_data = [];

        $posts_count = (int) $request['per_page'];

        $result = thjb_get_user_suggested_jobs($posts_count);

        if ( empty($result) || $posts_count > count($result) ) {

            $saved_jobs = thjb_get_user_saved_jobs();

            $similar = thjb_get_similar_jobs($saved_jobs, $posts_count );
            $result = array_merge($result, $similar);
            $result = array_unique($result);
            $result = array_slice($result, 0, $posts_count);
        }

        if ( ! empty($result) ) {
            $response_data = $this->prepare_user_jobs_list($result, $posts_count);
        }

        return ['data' => $response_data];
    }

    public function applied_jobs(WP_REST_Request $request)
    {
        $response_data = [];

        $posts_count = (int) $request['per_page'];

        $result = thjb_get_user_applied_jobs();

        if ( ! empty($result) ) {
            $response_data = $this->prepare_user_jobs_list($result, $posts_count);
        }

        return ['data' => $response_data];
    }

    public function rtbf_request(WP_REST_Request $request)
    {
        $user = wp_get_current_user();

        if ( $user ) {

            require_once THJB_PLUGIN_INC_PATH . 'api/class-api-user-model.php';

            $api_user = new ApiUserModel($user->ID);

            $api_user->send_rtbf_request();

            return [
                'success' => true
            ];
        }

        return [
            'success' => false
        ];
    }

    private function prepare_user_jobs_list($posts_in, $posts_count)
    {
        $response_data = [];

        $args = [
            'post_type'         => 'jobs',
            'post_status'       => 'publish',
            'posts_per_page'    => $posts_count,
            'post__in'          => $posts_in
        ];

        $jobs_query = new \WP_Query($args);

        if ( $jobs_query->have_posts() ) {
            foreach ( $jobs_query->posts as $job ) {

                $job_salary = get_post_meta($job->ID, 'job_salary', 1);

                $state = '';
                $city = '';

                $locations = wp_get_post_terms($job->ID, 'states');

                if ( ! empty($locations) ) {
                    foreach ($locations as $location) {
                        if ( 0 == $location->parent ) {
                            $state = $location->name;
                        } else {
                            $city = $location->name;
                        }
                    }
                }

                $posted_time = get_post_time('U', false, $job);
                $posted_string = thjb_get_timeago_string($posted_time);

                $excerpt =  wp_trim_words( html_entity_decode($job->post_content), 15, '...');

                $response_data[] = [
                    'id'              => $job->ID,
                    'title'           => html_entity_decode($job->post_title),
                    'excerpt'         => $excerpt,
                    'permalink'       => get_permalink($job->ID),
                    'date'            => $job->post_date,
                    'date_str'        => $posted_string,
                    'city'            => $city,
                    'state'           => $state,
                    'salary'          => $job_salary
                ];

            }
        }

        return $response_data;
    }

    private function prepare_item($user)
    {
        $result = [
            'firstName'    => $user->user_firstname,
            'lastName'     => $user->user_lastname,
            'email'         => $user->user_email
        ];

        $result = apply_filters('thjb_api_model_user', $result, $user);

        return $result;
    }

}