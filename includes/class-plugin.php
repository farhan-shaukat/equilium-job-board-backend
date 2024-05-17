<?php

namespace THJB;

class Plugin
{
    public function __construct()
    {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies()
    {
        require_once THJB_PLUGIN_INC_PATH . 'api/class-board-api.php';
        require_once THJB_PLUGIN_INC_PATH . 'api/class-auth-middleware.php';
    }

    private function register_hooks()
    {
        $api = new Api\BoardApi();
        $api_middleware = new Api\AuthMiddleware();

        add_filter( 'rest_pre_dispatch', [ $api_middleware, 'rest_pre_dispatch' ], 10, 3 );
        add_filter( 'rest_api_init', [ $api_middleware, 'disable_cors_support' ]);
        add_filter( 'determine_current_user', [ $api_middleware, 'determine_current_user' ] );

        add_filter('rest_allowed_cors_headers', function ($headers){
            $headers[] = 'Board-Auth';
            return $headers;
        });

        add_action( 'rest_api_init', [$api, 'register_routes'] );
        // routes with required user login
        add_filter( 'thjb_closed_by_token_api_urls', [$api, 'protected_endpoints']);
        // login user in this routes but it is not required
        add_filter( 'thjb_auth_by_token_api_urls', [$api, 'login_if_you_can']);

        //add_action( 'pre_get_posts', [$this, 'pre_get_jobs_posts'] );
    }

    public function pre_get_jobs_posts($query)
    {
        if ( ! is_admin() && ! $query->is_main_query() )
        {
            if ( 'jobs' == $query->get('post_type')
                && 'any' !== $query->get('post_status')) {

                $meta = $query->get('meta_query');
                if ( empty($meta) ) {
                    $meta = [];
                }

                $meta[] = [
                    'relation' => 'OR',
                    [
                        'key' => 'job_expiry_date',
                        'value' => date('Ymd'),
                        'compare' => '>',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'job_expiry_date',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => 'job_expiry_date',
                        'value' => '',
                    ]
                ];

                $query->set('meta_query', $meta);
            }
        }

    }

}
