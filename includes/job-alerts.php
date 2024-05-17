<?php
add_action( 'init', 'thjb_maybe_add_alert_actions' );

add_action( 'thjb_notify_user_about_new_published_job', 'thjb_send_job_alert_email_function', 10, 2 );
add_action( 'thjb_unsubscribe_job_alerts', 'thjb_unsubscribe_job_alerts_function', 10, 1 );

function thjb_maybe_add_alert_actions()
{
    $send_email_enabled = get_option('thjb_send_alerts');

    if ( $send_email_enabled ) {
        add_action( 'wp_insert_post', 'thjb_run_emails_sent_for_new_job', 10, 3 );
    }

}

function thjb_run_emails_sent_for_new_job($post_id, $post, $update )
{
    if ( wp_is_post_revision( $post_id ) )
        return;

    if ( 'jobs' !== $post->post_type || $update ) {
        return;
    }

    // add cron event to check and send available for post alerts
    wp_schedule_single_event( time() + (60*3), 'thjb_send_new_job_alert_email', [$post_id] );

}


function thjb_send_job_alerts_emails_function($post_id)
{
    $args = [
        'post_type'         => 'job-alerts',
        'post_status'       => 'publish',
        'fields'            => 'ids',
        'posts_per_page'    => -1,
    ];

    $alerts_query = new WP_Query($args);

    if ( $alerts_query->have_posts() ) {

        foreach($alerts_query->posts as $alert_id) {
            $jobs_args = get_post_meta($alert_id, '_job_alert_query', 1);

            if ( ! empty($jobs_args) ) {
                $jobs_args['post__in'] = [$post_id];
                $jobs_args['fields'] = 'ids';

                $jobs_query = new WP_Query($jobs_args);
                if ( $jobs_query->have_posts() ) {
                    do_action('thjb_notify_user_about_new_published_job', $post_id, $alert_id);
                }
            }
        }
    }

}

function thjb_send_job_alert_email_function($job_id, $alert_id)
{
    // get or create user token
    // get unsubscribe page and add query token parameter
    $email_to = get_post_meta($alert_id, 'notify_user_email', 1);

    if ( empty($email_to) ) {
        return false;
    }

    $unsubscribe_link = get_option('thjb_job_alerts_unsubscribe_url');

    if ( ! empty($unsubscribe_link) ) {

        $query_params = [];

        $token = get_post_meta($alert_id, '_unsubscribe_token', 1);
        if ( empty($token) ) {
            $token = thjb_get_token();
            update_post_meta($alert_id, '_unsubscribe_token', $token);
        }
        $query_params['al'] = $token;

        $unsubscribe_link = add_query_arg($query_params, $unsubscribe_link);
    }

    $from = get_option('thjb_job_alerts_email_from');
    $subject = get_option('thjb_job_alerts_email_subject');

    $email_template_path = apply_filters('thjb_job_alert_email_path', THJB_PLUGIN_DIR_PATH . 'partials/emails/job-alert.php');

    ob_start();
    include $email_template_path;
    $message = ob_get_contents();

    ob_end_clean();

    $headers = [
        'Content-Type: text/html; charset=UTF-8'
    ];
    if ( ! empty($from) ) {
        $headers[] = 'From: ' . get_bloginfo('name') . ' <' . $from . '>';
    }

    return wp_mail( $email_to, $subject, $message, $headers );
}

function thjb_unsubscribe_job_alerts_function()
{
    if ( ! empty($_GET['al']) ) {

        $args = [
            'post_type'         => 'job-alerts',
            'post_status'       => 'publish',
            'fields'            => 'ids',
            'posts_per_page'    => 1,
            'meta_key'          => '_unsubscribe_token',
            'meta_value'        => $_GET['al']
        ];

        $alerts_query = new WP_Query($args);

        if ( $alerts_query->have_posts() ) {

            foreach ( $alerts_query->posts as $alert_id ) {
                $deleted_post = wp_delete_post($alert_id, true);
                if ( ! empty($deleted_post->ID) ) {
                    echo '<h2>You have successfully unsubscribed</h2>';
                    return;
                }
            }

        } else {
            echo '<h2>It looks like you are already unsubscribed</h2>';
        }
    }
}

