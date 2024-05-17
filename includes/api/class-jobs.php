<?php

namespace THJB\Api;

class Jobs extends RestApiBase
{
    private $user_applied;
    private $user_saved;

    public function __construct($api_base)
    {
        parent::__construct($api_base);

        $this->post_type = 'jobs';
        $this->user_applied = [];
        $this->user_saved = [];
    }

    public function register_routes()
    {
        register_rest_route( $this->api_base, 'jobs', [
            'methods'  => 'GET',
            'callback' => [$this, 'index'],
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
                'page' => [
                    'description'         => 'Current page number',
                    'type'                => 'integer',
                    'default'             => 1
                ],
                'per_page' => [
                    'description'         => 'Posts count to show',
                    'type'                => 'integer',
                    'default'             => 50
                ]
            ],
        ] );

        register_rest_route( $this->api_base, 'jobs/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'read'],
            'permission_callback' => '__return_true',
            'args' => []
        ] );

        register_rest_route( $this->api_base . '/jobs', 'filter', [
            'methods'  => 'GET',
            'callback' => [$this, 'filter'],
            'permission_callback' => '__return_true',
            'args' => [],
        ] );
    }

    public function filter(\WP_REST_Request $request)
    {
        $response = [];
        nocache_headers();

        // get industries filter values
        $filter_values = thjb_get_terms_for_filter('industry');
        $filter_values = apply_filters('thjb_filter_industries_filter_values', $filter_values);

        $response['data']['filter']['industries'] = [
            'name' => apply_filters('thjb_filter_groups_title', 'Industries', 'industries'),
            'values' => $filter_values
        ];

        // get jobs type
        $filter_values = thjb_get_terms_for_filter('job-type');
        $filter_values = apply_filters('thjb_filter_jobtypes_filter_values', $filter_values);

        $response['data']['filter']['job-types'] = [
            'name' => apply_filters('thjb_filter_groups_title', 'Job types', 'job-type'),
            'values' => $filter_values
        ];

        // get locations
        $states = get_terms([
            'taxonomy' => 'states',
            'parent' => 0,
            'hide_empty' => 1,
            'cache_domain' => 'thjb_filter'
        ]);

        $states_filter_values = [];

        if ( ! empty($states) ) {

            foreach ($states as $state) {

                $child_states = get_terms([
                    'taxonomy' => 'states',
                    'parent' => $state->term_id,
                    'hide_empty' => 1,
                    'cache_domain' => 'thjb_filter'
                ]);

                $childrens = [];

                if ( ! empty($child_states) ) {

                    foreach ( $child_states as $child_state ) {

                        if ( 0 == $child_state->count ) {
                            continue;
                        }

                        $childrens[] = [
                            'name' => $child_state->name,
                            'value' => $child_state->slug,
                            'count' => $child_state->count,
                        ];
                    }
                }

                $states_filter_values[] = [
                    'name' => $state->name,
                    'value' => $state->slug,
                    'count' => $state->count,
                    'cities' => $childrens
                ];

            }
        }

        $states_filter_values = apply_filters('thjb_filter_states_filter_values', $states_filter_values);

        $response['data']['filter']['states'] = [
            'name' => apply_filters('thjb_filter_groups_title', 'States', 'states'),
            'values' => $states_filter_values
        ];
		
        return $response;
    }

    public function index(\WP_REST_Request $request)
    {
        nocache_headers();
        $user_id = get_current_user_id();
        if ( 0 !== $user_id ) {
            $this->user_saved = thjb_get_user_saved_jobs();
            $this->user_applied = thjb_get_user_applied_jobs();
        }

        return parent::index($request);
    }

    public function read(\WP_REST_Request $request)
    {
        $post = get_post($request['id']);

        if ( ! empty($post)
            && 'jobs' == $post->post_type
            && 'publish' == $post->post_status ) {

                $data = $this->prepare_item($post);

                return ['data' => $data];

        }

        return new \WP_REST_Response(
            array(
                'statusCode' => 400,
                'code'       => 'rest_api_post_not_found',
                'message'    => __('You cant read this job data'),
                'data'       => array(),
            ),
            400
        );
    }

    protected function get_query_args($params)
    {
        return thjb_get_jobs_query_args($params);
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
            'title'           => html_entity_decode($job->post_title,  ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401),
            'excerpt'         => html_entity_decode(get_the_excerpt($job),  ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401),
            'permalink'       => get_permalink($job->ID),
            'reference'       => $reference_number,
            'date'            => $job->post_date,
            'date_str'        => $posted_string,
            'city'            => $city,
            'state'           => $state,
            'salary'          => $job_salary,
            'is_saved'        => thjb_is_job_saved($job->ID, $this->user_saved),
            'is_applied'      => thjb_is_job_applied($job->ID, $this->user_applied),
        ];

        $result = apply_filters('thjb_api_model_job', $result, $job->ID);

        return $result;
    }

}