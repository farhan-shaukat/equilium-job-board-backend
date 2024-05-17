# Talent Hero Job Board Plugin

## Actions

**thjb_general_section_register_fields** - fires to add settings to plugin page

Example

add_action('thjb_general_section_register_fields', 'thjb_general_section_jobdiva_fields_function', 10, 2);

**thjb_import_jobs_data** - import jobs data cron event

**thjb_delete_job_from_saved_lists** - Fired to run deleting the Job from saved jobs lists

do_action('thjb_delete_job_from_saved_lists', $post_id);

**thjb_send_candidate_to_external_api** - Send data from apply forms to external API

**thjb_notify_user_about_new_published_job** - Job alert email sent

**thjb_unsubscribe_job_alerts** - Unsubscribe user action

## Filters

**thjb_filter_groups_title** - Change filter titles

add_filter('thjb_filter_groups_title', function($old_title, $filter_type){}),

$filter_type - value from array ['industries', 'job-type', 'states']

**thjb_filter_industries_filter_values** - change the Industries filter values

**thjb_filter_jobtypes_filter_values** - change the Job types filter values

**thjb_filter_states_filter_values** - change the States filter values

**thjb_jobdiva_import_filter_job_content** - Filter Job post content before save it from API

**thjb_api_model_job** - Filter Job model return by plugin

## Shortcodes

**thjb_unsubscribe_page** - Shortcode for unsubscribe page. Contains thjb_unsubscribe_job_alerts action.
