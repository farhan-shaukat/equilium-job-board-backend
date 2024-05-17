<?php
/*
 * Functions for Jobs Filter
 */

function thjb_get_jobs_list_filter_data(WP_REST_Request $request)
{
    $response = [];

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
        'child_of' => 0
    ]);

    $states_filter_values = [];

    if ( ! empty($states) ) {

        foreach ($states as $state) {

            $child_states = get_terms([
                'taxonomy' => 'states',
                'child_of' => $state->term_id
            ]);

            $childrens = [];

            if ( !empty($child_states) ) {

                foreach ( $child_states as $child_state ) {

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

function thjb_get_terms_for_filter($taxonomy)
{
    $data = [];

    $terms = get_terms([
        'taxonomy' => $taxonomy,
    ]);

    if ( ! empty($terms) ) {

        foreach ($terms as $term) {

            $data[] = [
                'name'  => $term->name,
                'value' => $term->slug,
                'count' => $term->count,
            ];

        }
    }

    return $data;
}
