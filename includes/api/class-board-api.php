<?php

namespace THJB\Api;

class BoardApi
{
    private $api_base;

    private $controllers;

    public function __construct()
    {
        $this->api_base = 'thjb/v1';
        $this->controllers = [];
    }

    public function register_routes()
    {
        $this->load_dependencies();

        if ( !empty($this->controllers) ) {
            foreach ( $this->controllers as $controller ) {
                $controller->register_routes();
            }
        }
    }

    private function load_dependencies()
    {

        $api_folder_path = THJB_PLUGIN_INC_PATH . 'api/';

        require_once $api_folder_path . 'class-rest-api-base.php';

        require_once $api_folder_path . 'class-auth.php';
        require_once $api_folder_path . 'class-user-account.php';
        require_once $api_folder_path . 'class-job-alerts.php';
        require_once $api_folder_path . 'class-jobs.php';
        require_once $api_folder_path . 'class-saved-jobs.php';
        require_once $api_folder_path . 'class-apply-job.php';
        require_once $api_folder_path . 'class-user-resume.php';

        // login and register user
        $this->controllers[] = new Auth($this->api_base);
        // get and update current user data
        $this->controllers[] = new UserAccount($this->api_base);
        // saved job alerts
        $this->controllers[] = new JobAlerts($this->api_base);
        // jobs listing
        $this->controllers[] = new Jobs($this->api_base);
        // saved jobs by user
        $this->controllers[] = new SavedJobs($this->api_base);

        $this->controllers[] = new ApplyJob($this->api_base);

        $this->controllers[] = new UserResume($this->api_base);

    }

    public function protected_endpoints($defaults)
    {
        $full_base = '/wp-json/' . $this->api_base;
        $defaults = [
            [
                'path' => $full_base . '/job-alerts*',
                'method' => 'GET'
            ],
            [
                'path' => $full_base . '/job-alerts*',
                'method' => 'DELETE'
            ],
            [
                'path' => $full_base . '/job-alerts*',
                'method' => 'POST',
                'check_if_empty' => 'email' // check token if field is not set
            ],
            $full_base . '/saved-jobs*',
            $full_base . '/user*',
            [
                'path' => $full_base . '/apply-job',
                'method' => 'POST',
            ],
        ];

        return $defaults;
    }

    public function login_if_you_can($defaults)
    {
        $full_base = '/wp-json/' . $this->api_base;
        $defaults = [
            $full_base . '/jobs',
        ];

        return $defaults;
    }

}
