<?php

namespace THJB\Api;

use WP_REST_Request;

class RestApiJobAlerts extends RestApiBase
{
    private $filter_meta;

    public function __construct()
    {
        parent::__construct();

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

        $post_id = wp_insert_post( wp_slash($post_data) );

        if ( $post_id ) {

            $this->update_post_meta($post_id, $params, $current_user);

            return new \WP_REST_Response([
                'data' => [
                    'id' => $post_id,
                ]
            ], 201);
        }

        return new \WP_Error('thjb_rest_api_add_alert_error', 'Sorry, something went wrong', 400);
    }

    public function read(WP_REST_Request $request)
    {
        $post = get_post($request['id']);

        $data = $this->prepare_item($post);

        return ['data' => $data];
    }

    public function delete(WP_REST_Request $request)
    {
        $deleted_post = wp_delete_post($request['id']);

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

            return new \WP_REST_Response([
                'data' => [
                    'id' => $post_id,
                ]
            ], 201);
        }

        return new \WP_Error('thjb_rest_api_update_alert_error', 'Sorry, something went wrong', 400);
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

        if ( ! empty($params['states']) ) {
            foreach ($params['states'] as $state) {
                $state_term = get_term_by('slug', $state, 'states');
                if ( ! empty($state_term) ) {
                    $post_title .= 'in ' . $state_term->name;
                    break;
                }
            }
        }

        if ( empty($post_title) ) {
            $post_title = 'Alert '.date('Y-m-d');
        }

        return $post_title;
    }

}