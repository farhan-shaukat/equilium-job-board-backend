<?php
/*
 * Functions for filter Jobs list
 */

function thjb_get_jobs_filtered_data(WP_REST_Request $request)
{
    $response = [
        'meta' => [
            'found_posts'    => 0,
            'page'           => (int) $request['page'],
            'max_page'       => 1,
            'per_page'       => (int) $request['per_page'],
        ],
        'data' => []
    ];

    $args = [
        'post_type'         => 'jobs',
        'post_status'       => 'publish',
        'posts_per_page'    => (int) $request['per_page'],
        'paged'             => (int) $request['page'],
        'tax_query'         => [
            'relation' => 'AND'
        ]
    ];

    if ( ! empty($request['industries']) ) {
        $args['tax_query'][] = [
            'taxonomy' => 'industry',
            'field'    => 'slug',
            'terms'    => $request['industries']
        ];
    }

    if ( ! empty($request['job-types']) ) {
        $args['tax_query'][] = [
            'taxonomy' => 'job-type',
            'field'    => 'slug',
            'terms'    => $request['job-types']
        ];
    }

    if ( ! empty($request['states']) ) {
        $args['tax_query'][] = [
            'taxonomy' => 'states',
            'field'    => 'slug',
            'terms'    => $request['states']
        ];
    }

    if ( ! empty($request['search']) ) {
        $args['s'] = $request['search'];
    }

    $jobs_q = new WP_Query($args);

    if ( $jobs_q->have_posts() ) {
        foreach ($jobs_q->posts as $job) {
            $response['data'][] = thjb_prepare_job($job);
        }
    }

    $response['meta']['found_posts'] = $jobs_q->found_posts;
    $response['meta']['max_page'] = $jobs_q->max_num_pages;

    return $response;
}

function thjb_prepare_job($job)
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
    
    $job_location= get_post_meta($job->ID, 'job_location', 1);

    $posted_time = get_post_time('U', false, $job);
    $posted_string = thjb_get_timeago_string($posted_time);

    $result = [
        'id'              => $job->ID,
        'title'           => $job->post_title,
        'excerpt'         => get_the_excerpt($job),
        'permalink'       => get_permalink($job->ID),
        'reference'       => $reference_number,
        'date'            => $job->post_date,
        'date_str'        => $posted_string,
        'city'            => $city,
        'state'           => $state,
        'location'        => $job_location,
        'salary'          => $job_salary
    ];

    $result = apply_filters('thjb_api_model_job', $result, $job->ID);

    return $result;
}
