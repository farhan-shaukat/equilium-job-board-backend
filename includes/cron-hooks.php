<?php

add_action( 'wp', 'thjb_add_cron_events' );
add_action( 'thjb_import_jobs_data_1', 'thjb_import_jobs_jobdiva_api' );

add_action( 'thjb_check_expired_jobs', 'thjb_shedule_saved_jobs_cleaning_proces' );
add_action( 'thjb_delete_job_from_saved_lists', 'thjb_delete_job_from_saved_lists', 10, 1 );

add_action( 'thjb_send_new_job_alert_email', 'thjb_send_job_alerts_emails_function', 10, 1 );
add_action( 'thjb_hide_closed_jobs', 'thjb_hide_closed_jobs_function', 10, 1 );

function thjb_add_cron_events()
{
    $hours = date('G');

    if ( ! wp_next_scheduled( 'thjb_import_jobs_data_1' ) ) {
        wp_schedule_event( time(), 'hourly', 'thjb_import_jobs_data_1');
    }
/*
    if ( ! wp_next_scheduled( 'thjb_check_expired_jobs' ) ) {
        wp_schedule_event( time() + (30 * MINUTE_IN_SECONDS), 'hourly', 'thjb_check_expired_jobs');
    }
*/
}
