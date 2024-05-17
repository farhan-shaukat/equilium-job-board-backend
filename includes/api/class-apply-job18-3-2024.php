<?php

namespace THJB\Api;

use WP_REST_Request;

class ApplyJob
{
    public function __construct($api_base)
    {
        $this->api_base = $api_base;
    }

    public function register_routes()
    {
        register_rest_route($this->api_base, 'apply-job', [
                'methods'             => 'POST',
                'callback'            => [$this, 'apply'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
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
                    ]
                ]
            ]
        );
        register_rest_route($this->api_base, 'apply-job/guest', [
                'methods'             => 'POST',
                'callback'            => [$this, 'apply_by_guest'],
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
                    ]
                ]
            ]
        );

        /**
         * Api endpoints to login or register before apply job:
         *
         * apply-job/login - Auth->login_and_apply()
         * apply-job/register - Auth->register_and_apply()
         *
         * get user applied jobs list
         * user/applied-jobs - UserAccount->applied_jobs()
         *
         */

    }

    public function apply( WP_REST_Request $request )
    {
        global $thjb_is_send_candidate_success;

        $filename = $request->get_param( 'filename' );
        $cv_file = [];

        if ( ! empty($filename) ) {

            $resume_list = get_user_meta(get_current_user_id(), '_user_resume_filename_list', 1);
            if ( empty($resume_list) ) {
                return new WP_Error('rest_resume_list_empty', 'You have no resume', [
                    'status' => 400,
                ], 400);
            }

            $filename = sanitize_file_name($filename);
            foreach ($resume_list as $i => $resume_name) {
                if ( $resume_name == $filename ) {

                    $cv_file = [
                        'path'  => trailingslashit( thjb_get_user_resume_dir_path(get_current_user_id()) ) . $resume_name,
                        'name'  => $filename,
                    ];

                    break;
                }
            }

        } else {

            $files = $request->get_file_params();

            if ( empty($files['cv_file']) ) {

                return new \WP_Error('thjb_applied_jobs_cv_empty', 'You must specify cv filename or upload new file', [
                    'status' => 400
                ]);
            }

            $result = thjb_check_uploaded_by_api_file($files, 'cv_file');

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            // save user resume file
            $cv_file = thjs_add_user_resume_file(get_current_user_id());
            if ( is_wp_error( $cv_file ) ) {
                return $cv_file;
            }

        }

        if ( empty($cv_file) ) {

            return new \WP_Error('thjb_applied_jobs_cv_empty', 'You must specify cv filename or upload new file', [
                'status' => 400
            ]);
        }

        $user = wp_get_current_user();

        $applied_job_id = $request->get_param( 'job_id' );

        $applied_jobs = get_user_meta($user->ID, 'applied_jobs_list', 1);

        if ( empty($applied_jobs) ) {
            $applied_jobs = [];
        }

        if ( in_array($applied_job_id, $applied_jobs) ) {
            return new \WP_Error('thjb_applied_jobs_conflict', 'You have already applied for this job', [
                'status' => 400,
                'params' => [
                    'job_id'
                ]
            ]);
        }

        // send user data to Jobdiva
        $candidate_data = [
            'job_id'       => $applied_job_id,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->user_email,
        ];

        do_action('thjb_send_candidate_to_external_api', $candidate_data, $cv_file);

        // save job to applied jobs list
        $applied_jobs[] = $applied_job_id;

        $applied_jobs = array_unique($applied_jobs);

        update_user_meta($user->ID, 'applied_jobs_list', $applied_jobs);

        // change status to send data result
        return [
            'success' => $thjb_is_send_candidate_success
        ];
    }

    public function apply_by_guest( WP_REST_Request $request )
    {
        global $thjb_is_send_candidate_success;

        // check uploaded file
        $files = $request->get_file_params();

        $result = thjb_check_uploaded_by_api_file($files, 'cv_file');

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $applied_job_id = $request->get_param( 'job_id' );

        $cv_file = [
            'name' => $files['cv_file']['name'],
            'path' => $files['cv_file']['tmp_name'],
        ];

        // send user data to Jobdiva
        $candidate_data = [
            'job_id'       => $applied_job_id,
            'first_name'   => $request->get_param( 'first_name' ),
            'last_name'    => $request->get_param( 'last_name' ),
            'email'        => $request->get_param( 'email' ),
        ];

        do_action('thjb_send_candidate_to_external_api', $candidate_data, $cv_file);

        // change status to send data result
        return [
            'success' => $thjb_is_send_candidate_success
        ];
    }

}