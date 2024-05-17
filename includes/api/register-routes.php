<?php

add_action( 'rest_api_init', 'thjb_register_api_routes_function' );

function thjb_register_api_routes_function()
{
    $namespace = 'thjb/v1';

    $api_auth = new THJB\Api\Auth();
    $jobs_api_class = new THJB\Api\RestApiJobs();
    $job_alerts_api_class = new THJB\Api\RestApiJobAlerts();

    register_rest_route( $namespace, 'jobs', [
        'methods'  => 'GET',
        'callback' => [$jobs_api_class, 'index'],
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
                'default'             => 10
            ]
        ],
    ] );

    register_rest_route( $namespace . '/jobs', 'filter', [
        'methods'  => 'GET',
        'callback' => 'thjb_get_jobs_list_filter_data',
        'permission_callback' => '__return_true',
        'args' => [],
    ] );

    register_rest_route($namespace, 'token', [
            'methods'             => 'POST',
            'callback'            => [$api_auth, 'get_token'],
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route( $namespace, 'token/validate', [
            'methods'             => 'POST',
            'callback'            => [$api_auth, 'validate_token'],
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route( 'thjb/v1', 'job-alerts', [
        'methods'  => 'POST',
        'callback' => [$job_alerts_api_class, 'create'],
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

    register_rest_route( 'thjb/v1', 'job-alerts', [
        'methods'  => 'GET',
        'callback' => [$job_alerts_api_class, 'index'],
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

    register_rest_route( 'thjb/v1', 'job-alerts/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => [$job_alerts_api_class, 'read'],
        'permission_callback' => function ($param) {
            return thjb_api_post_permission_callback('job-alerts', $param['id']);
        },
        'args' => []
    ] );

    register_rest_route( 'thjb/v1', 'job-alerts/(?P<id>\d+)', [
        'methods'  => 'POST',
        'callback' => [$job_alerts_api_class, 'update'],
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

    register_rest_route( 'thjb/v1', 'job-alerts/(?P<id>\d+)', [
        'methods'  => 'DELETE',
        'callback' => [$job_alerts_api_class, 'delete'],
        'permission_callback' => function ($param) {
            return current_user_can( 'delete_job_alert', $param['id'] );
        },
        'args' => []
    ] );

}

function thjb_api_post_permission_callback($post_type, $post_id)
{
    $current_user = get_current_user_id();

    $post = get_post($post_id);
    if ( ! empty($post)
        && $post_type == $post->post_type
        && $current_user == $post->post_author
        && 'publish' == $post->post_status
    ) {
        return true;
    }

    return false;
}
