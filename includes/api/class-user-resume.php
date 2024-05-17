<?php

namespace THJB\Api;

use WP_REST_Request;
use WP_Error;

class UserResume
{
    public function __construct($api_base)
    {
        $this->api_base = $api_base;
    }

    public function register_routes()
    {
        register_rest_route($this->api_base, 'user/resume', [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => []
            ]
        );

        register_rest_route($this->api_base, 'user/resume', [
                'methods'             => 'POST',
                'callback'            => [$this, 'add'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => []
            ]
        );

        register_rest_route($this->api_base, 'user/resume', [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete'],
                'permission_callback' => function(){
                    return is_user_logged_in();
                },
                'args' => [
                    'filename' => [
                        'required'          => true,
                        'type'              => 'string',
                        'minLength'         => 2,
                        'maxLength'         => 300,
                    ],
                ]
            ]
        );

    }

    public function index()
    {
        $resume_list = get_user_meta(get_current_user_id(), '_user_resume_filename_list', 1);

        if ( empty($resume_list) ) {
            $resume_list = [];
        }

        return $resume_list;
    }

    public function add(WP_REST_Request $request)
    {
        $files = $request->get_file_params();
        $result = thjb_check_uploaded_by_api_file($files, 'cv_file');

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $result = thjs_add_user_resume_file( get_current_user_id() );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'success' => true
        ];
    }

    public function delete(WP_REST_Request $request)
    {
        $resume_list = get_user_meta(get_current_user_id(), '_user_resume_filename_list', 1);

        if ( empty($resume_list) ) {
            return new WP_Error('rest_resume_list_empty', 'You have no resume', [
                'status' => 400,
            ], 400);
        }

        $file_to_delete = sanitize_file_name($request->get_param( 'filename' ));

        $user_dir_path = thjb_get_user_resume_dir_path( get_current_user_id() );

        foreach ($resume_list as $i => $resume_name) {
            if ( $resume_name == $file_to_delete ) {
                unlink($user_dir_path . '/' . $file_to_delete);
                unset($resume_list[$i]);

                $resume_list = array_values($resume_list);

                update_user_meta(get_current_user_id(), '_user_resume_filename_list', $resume_list);

                break;
            }
        }

        return $resume_list;
    }

}