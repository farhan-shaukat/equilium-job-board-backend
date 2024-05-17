<?php

namespace THJB\Api;

use WP_REST_Request;

class JobAlerts extends RestApiBase
{
    private $filter_meta;

    public function __construct($api_base)
    {
        parent::__construct($api_base);

        $this->post_type = 'job-alerts';

        // array to specify meta field key and api parameter
        // although used to check if one or more filter params filled
        $this->filter_meta =  [
            'search_term'   => 'search',
            'industries'    => 'industries',
            'job_types'     => 'job-types',
            'locations'     => 'states',
        ];

    }

    public function register_routes()
    {
        register_rest_route( $this->api_base, 'job-alerts', [
            'methods'  => 'POST',
            'callback' => [$this, 'create'],
            'permission_callback' => '__return_true',
            'args' => [
                'search' => [
                    'description'         => 'Search string',
                    'type'                => 'string',
                ],
                'industries' => [
                    'description'         => 'Industries filter. Array of taxonomy slugs',
                    'type'                => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ],
                'job-types' => [
                    'description'         => 'Job Type filter. Array of taxonomy slugs',
                    'type'                => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ],
                'states' => [
                    'description'         => 'States filter. Array of taxonomy slugs',
                    'type'                => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ],
                'email' => [
                    'description'       => 'User email if he has no account',
                    'type'              => 'string',
                    'format'            => 'email',
                    'maxLength'         => 300,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email'
                ],
            ],
        ] );

        register_rest_route($this->api_base, 'job-alerts', [
            'methods'  => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => function(){
                return is_user_logged_in();
            },
            'args' => [
                'page' => [
                    'description'         => 'Current page number',
                    'type'                => 'integer',
                    'default'             => 1
                ],
                'per_page' => [
                    'description'         => 'Posts count to show',
                    'type'                => 'integer',
                    'default'             => 5
                ]
            ]
        ] );

        register_rest_route( $this->api_base, 'job-alerts/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'read'],
            'permission_callback' => function ($param) {
                return thjb_api_post_permission_callback('job-alerts', $param['id']);
            },
            'args' => []
        ] );

        register_rest_route($this->api_base, 'job-alerts/(?P<id>\d+)', [
            'methods'  => 'POST',
            'callback' => [$this, 'update'],
            'permission_callback' => function ($param) {
                return current_user_can( 'edit_job_alert', $param['id'] );
            },
            'args' => [
                'search' => [
                    'description'         => 'Search string',
                    'type'                => 'string',
                ],
                'industries' => [
                    'description'         => 'Industries filter. Array of taxonomy slugs',
                    'type'                => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ],
                'job-types' => [
                    'description'         => 'Job Type filter. Array of taxonomy slugs',
                    'type'                => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ],
                'states' => [
                    'description'         => 'States filter. Array of taxonomy slugs',
                    'type'                => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ]
            ]
        ] );

        register_rest_route($this->api_base, 'job-alerts/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [$this, 'delete'],
            'permission_callback' => function ($param) {
                return current_user_can( 'delete_job_alert', $param['id'] );
            },
            'args' => []
        ] );
    }

    public function create(WP_REST_Request $request)
    {
        $params = $request->get_params();

        $current_user = wp_get_current_user();

        if ( 0 == $current_user->ID && empty($params['email']) ) {
            return new \WP_Error('rest_missing_callback_param', 'Parameter email is required for non logged users', [
                'status' => 400,
                'params' => [
                    'email'
                ]
            ]);
        }

        $is_all_filters_empty = true;
        foreach ($this->filter_meta as $key) {
            if ( ! empty($params[$key]) ) {
                $is_all_filters_empty = false;
                break;
            }
        }

        if ($is_all_filters_empty) {
            return new \WP_Error('rest_missing_callback_param', 'Please, specify one or more filter value', [
                'status' => 400,
                'params' => []
            ]);
        }

        $post_data = [
            'post_type'     => 'job-alerts',
            'post_title'    => $this->get_alert_title($params),
            'post_status'   => 'publish',
        ];

        if ( 0 < $current_user->ID ) {
            $post_data['post_author'] = $current_user->ID;
        } else {
            $post_data['post_author'] = 1;
        }

        $post_id = wp_insert_post( wp_slash($post_data) );

        if ( $post_id ) {

            $this->update_post_meta($post_id, $params, $current_user);

            $post = get_post($post_id);
            $data = $this->prepare_item($post);

            return new \WP_REST_Response([
                'data' => $data
            ], 201);
        }

        return new \WP_Error('thjb_rest_api_add_alert_error', 'Sorry, something went wrong', ['status' => 400]);
    }

    public function read(WP_REST_Request $request)
    {
        $post = get_post($request['id']);

        $data = $this->prepare_item($post);

        return ['data' => $data];
    }

    public function delete(WP_REST_Request $request)
    {
        $deleted_post = wp_delete_post($request['id'], true);

        if ( ! empty($deleted_post->ID) ) {
            return [
                'data' => [
                    'id' => $deleted_post->ID
                ]
            ];
        }

        return new \WP_Error('rest_delete_post_error', 'Something wend wrong', [
            'status' => 400,
            'params' => []
        ]);
    }

    public function update(WP_REST_Request $request)
    {
        $params = $request->get_params();

        $current_user = wp_get_current_user();

        $is_all_filters_empty = true;
        foreach ($this->filter_meta as $key) {
            if ( ! empty($params[$key]) ) {
                $is_all_filters_empty = false;
                break;
            }
        }

        if ($is_all_filters_empty) {
            return new \WP_Error('rest_missing_callback_param', 'Please, specify one or more filter value', [
                'status' => 400,
                'params' => []
            ]);
        }

        $post_data = [
            'ID'            => $params['id'],
            'post_type'     => 'job-alerts',
            'post_title'    => $this->get_alert_title($params)
        ];

        $post_id = wp_update_post( wp_slash($post_data) );

        if ( $post_id ) {

            $this->update_post_meta($post_id, $params, $current_user);

            $post = get_post($post_id);
            $data = $this->prepare_item($post);

            return new \WP_REST_Response([
                'data' => $data
            ], 201);
        }

        return new \WP_Error('thjb_rest_api_update_alert_error', 'Sorry, something went wrong', ['status' => 400]);
    }

    private function update_post_meta($post_id, $params, $current_user)
    {
        $args = thjb_get_jobs_query_args($params);
        update_post_meta($post_id, '_job_alert_query', $args);

        foreach ($this->filter_meta as $meta_key => $args_key) {
            $meta_value = '';
            if ( ! empty($params[$args_key]) ) {
                if ( is_array($params[$args_key]) ) {
                    $meta_value = implode(',', $params[$args_key]);
                } else {
                    $meta_value = $params[$args_key];
                }
            }
            update_post_meta($post_id, $meta_key, $meta_value);
        }

        if ( 0 === $current_user->ID ) {
            $send_to_email = $params['email'];
        } else{
            $send_to_email = $current_user->user_email;
        }

        update_post_meta($post_id, 'notify_user_email', $send_to_email);
    }

    protected function get_query_args($params)
    {
        $args = parent::get_query_args($params);

        $args['author'] = get_current_user_id();

        return $args;
    }

    protected function prepare_item($post)
    {
        $search_term = get_post_meta($post->ID, 'search_term', 1);

        $industries = get_post_meta($post->ID, 'industries', 1);
        if ( empty($industries) ) {
            $industries = [];
        } else {
            $industries = explode(',', $industries);
        }

        $job_types = get_post_meta($post->ID, 'job_types', 1);
        if ( empty($job_types) ) {
            $job_types = [];
        } else {
            $job_types = explode(',', $job_types);
        }

        $locations = get_post_meta($post->ID, 'locations', 1);
        if ( empty($locations) ) {
            $locations = [];
        } else {
            $locations = explode(',', $locations);
        }

        $result = [
            'id'              => $post->ID,
            'title'           => $post->post_title,
            'search'          => $search_term,
            'industries'      => $industries,
            'job-types'       => $job_types,
            'states'          => $locations,
        ];

        $result = apply_filters('thjb_api_model_job_alert', $result, $post->ID);

        return $result;
    }

    private function get_alert_title($params)
    {
        $post_title = '';

        if ( ! empty($params['search']) ) {
            $post_title = $params['search'] . ' ';
        }

        // JOBTITLE in CITY1, CITY2, CITY3, STATE1, STATE2
        if ( ! empty($params['states']) ) {
            $state_string = '';
            $states = [];
            $cities = [];
            $state_params = $params['states'];
            foreach ($state_params as $state) {

                $state_term = get_term_by('slug', $state, 'states');
                if ( ! empty($state_term) ) {

                    if ( 0 == $state_term->parent ) {

                        $states[] = $state_term->name;

                    } else {

                        $siblings = get_terms( [
                            'taxonomy'     => 'states',
                            'hide_empty'   => true,
                            'fields'       => 'slugs',
                            'parent'       => $state_term->parent,
                            'cache_domain' => 'thjb_filter'
                        ] );

                        // check if there are all cities from state in params
                        $cities_intersect = array_intersect($siblings, $state_params);

                        if ( count($cities_intersect) == count($siblings) ) {
                            // if all add only state name
                            $parent_term = get_term_by('id', $state_term->parent, 'states');
                            $states[] = $parent_term->name;
                        } else {
                            // if not all add names of all cities
                            $states_part = $this->get_terms_name_array($cities_intersect, 'states');
                            $cities = array_merge($cities, $states_part);
                        }

                        // delete added cities from array
                        $state_params = array_diff($state_params, $cities_intersect);
                    }

                }
            }

            if ( ! empty($cities) ) {

                if ( empty($states) ) {
                    $state_string .= implode(', ', $cities);
                } else {
                    $state_string .= implode(', ', $cities);
                    $state_string .= ', ' . implode(', ', $states);
                }

            } elseif ( ! empty($states) ) {
                $state_string .= implode(', ', $states);
            }

            if ( ! empty($state_string) ) {
                if ( ! empty($post_title) ) {
                    $post_title .= 'in ';
                }
                $post_title .= $state_string;
            }

        }

        if ( ! empty($params['industries']) ) {
            $industries = $this->get_terms_name_array($params['industries'], 'industry');

            if ( ! empty($post_title) ) {
                $post_title .= ' - ';
            }

            $post_title .= implode(', ', $industries);
        }

        if ( ! empty($params['job-types']) ) {
            $job_types = $this->get_terms_name_array($params['job-types'], 'job-type');

            if ( ! empty($post_title) ) {
                $post_title .= ' - ';
            }

            $post_title .= implode(', ', $job_types);
        }

        if ( empty($post_title) ) {
            $post_title = 'Alert '.date('Y-m-d');
        }

        return $post_title;
    }

    private function get_terms_name_array($terms, $taxonomy)
    {
        $result = [];

        foreach ($terms as $slug ) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if ( ! empty($term) ) {
                $result[] = $term->name;
            }
        }

        return $result;
    }

}