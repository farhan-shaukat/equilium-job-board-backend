<?php

namespace THJB\Api;

use WP_REST_Request;
use WP_Error;

class SavedJobs extends RestApiBase
{
    public function __construct($api_base)
    {
        parent::__construct($api_base);

        $this->post_type = 'jobs';
    }

    public function register_routes()
    {
        register_rest_route( $this->api_base, 'saved-jobs', [
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
                    'default'             => 10
                ]
            ],
        ] );

        register_rest_route( $this->api_base, 'saved-jobs', [
            'methods'  => 'POST',
            'callback' => [$this, 'save'],
            'permission_callback' => function(){
                return is_user_logged_in();
            },
            'args' => [
                'id' => [
                    'description'       => 'Job ID to save',
                    'type'              => 'integer',
                    'required'          => true,
                    'validate_callback' => function($param) {
                        $post = get_post((int)$param);
                        if ( ! empty($post)
                            && 'jobs' == $post->post_type
                            && 'publish' == $post->post_status ) {
                            return true;
                        }

                        return false;
                    }
                ],
            ],
        ] );

        register_rest_route( $this->api_base, 'saved-jobs/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [$this, 'delete'],
            'permission_callback' => function(){
                return is_user_logged_in();
            },
            'args' => [],
        ] );

    }

    private function get_user_saved_jobs()
    {
        return thjb_get_user_saved_jobs();
    }

    private function set_user_saved_jobs($value)
    {
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'saved_jobs_list', $value);
    }

    public function index(WP_REST_Request $request)
    {
        $posts_list = $this->get_user_saved_jobs();

        if ( empty($posts_list) ) {

            $this->meta_arr = [
                'found_posts' => 0,
                'page' => (int)$request['page'],
                'max_page' => 0,
                'per_page' => (int)$request['per_page'],
            ];

        } else {

            $params = array_merge($request->get_params(), ['saved_posts_list' => $posts_list]);

            $args = $this->get_query_args($params);

            $posts_query = new \WP_Query($args);

            if ( $posts_query->have_posts() ) {
                foreach ( $posts_query->posts as $post ) {
                    $this->response_data[] = $this->prepare_item($post);
                }
            }

            $this->meta_arr = [
                'found_posts' => $posts_query->found_posts,
                'page' => (int)$params['page'],
                'max_page' => $posts_query->max_num_pages,
                'per_page' => (int)$params['per_page'],
            ];
        }

        return $this->get_data();
    }

    public function save(WP_REST_Request $request)
    {
        $params = $request->get_params();

        $post = get_post((int)$params['id']);

        if ( ! empty($post)
            && 'jobs' == $post->post_type
            && 'publish' == $post->post_status ) {

                $posts_list = $this->get_user_saved_jobs();

                if ( empty($posts_list) ) {
                    $posts_list = [];
                }

                $posts_list[] = $params['id'];

                $posts_list = array_unique($posts_list);

                $this->set_user_saved_jobs($posts_list);

                return [
                    'data' => [
                        'id' => $params['id']
                    ]
                ];

        }

        return new \WP_REST_Response(
            array(
                'statusCode' => 400,
                'code'       => 'rest_api_post_not_found',
                'message'    => __('You cant save this job'),
                'data'       => array(),
            ),
            400
        );

    }

    public function delete(WP_REST_Request $request)
    {
        $params = $request->get_params();

        $posts_list = $this->get_user_saved_jobs();

        if ( empty($posts_list) ) {
            return new WP_Error('rest_saved_jobs_not_found', 'You have no saved jobs', [
                'status' => 400
            ], 400);
        }

        $deleted = false;

        foreach ($posts_list as $i => $item) {
            if ( $params['id'] == $item ) {
                unset($posts_list[$i]);
                $deleted = true;
                break;
            }
        }

        if ( $deleted ) {

            $this->set_user_saved_jobs($posts_list);

            return [
                'data' => [
                    'id' => $params['id']
                ]
            ];
        }

        return new WP_Error('rest_saved_job_not_found', 'maybe this job was deleted previously', [
            'status' => 400,
            'params' => [
                'id'
            ]
        ], 400);
    }

    protected function get_query_args($params)
    {
        $args = [
            'post_type'         =>  $this->post_type,
            'post_status'       => 'publish',
            'posts_per_page'    => (int) $params['per_page'],
            'paged'             => (int) $params['page'],
            'post__in'          => $params['saved_posts_list']
        ];

        return $args;
    }

    protected function prepare_item($job)
    {
        $reference_number = get_post_meta($job->ID, 'reference_number', 1);
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

        $result = [
            'id'              => $job->ID,
            'title'           => html_entity_decode($job->post_title),
            'excerpt'         => get_the_excerpt($job),
            'permalink'       => get_permalink($job->ID),
            'reference'       => $reference_number,
            'date'            => $job->post_date,
            'date_str'        => $posted_string,
            'city'            => $city,
            'state'           => $state,
            'salary'          => $job_salary
        ];

        $result = apply_filters('thjb_api_model_saved_job', $result, $job->ID);

        return $result;
    }

}