<?php

namespace THJB\Api;

class RestApiJobs extends RestApiBase
{
    public function __construct()
    {
        parent::__construct();

        $this->post_type = 'jobs';
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
            'title'           => $job->post_title,
            'excerpt'         => get_the_excerpt($job),
            'permalink'       => get_permalink($job->ID),
            'reference'       => $reference_number,
            'date'            => $job->post_date,
            'date_str'        => $posted_string,
            'city'            => $city,
            'state'           => $state,
            'salary'          => $job_salary
        ];

        $result = apply_filters('thjb_api_model_job', $result, $job->ID);

        return $result;
    }

}