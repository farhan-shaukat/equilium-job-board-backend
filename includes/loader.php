<?php

// add custom post types and taxonomies
require THJB_PLUGIN_INC_PATH . 'core/register-cpt.php';
// add custom fields
require THJB_PLUGIN_INC_PATH . 'core/register-cpt-fields.php';

require THJB_PLUGIN_INC_PATH . 'core/user-caps.php';

if ( is_admin() ) {
    require THJB_PLUGIN_INC_PATH . 'core/admin-hooks.php';
}
// add plugin settings
require THJB_PLUGIN_INC_PATH . 'settings.php';
require THJB_PLUGIN_INC_PATH . 'functions.php';
require THJB_PLUGIN_INC_PATH . 'shortcodes/functions.php';

// register cron actions
require THJB_PLUGIN_INC_PATH . 'cron-hooks.php';

require THJB_PLUGIN_INC_PATH . 'job-alerts.php';

/**
 * Custom frontend API
 */
require THJB_PLUGIN_INC_PATH . 'class-plugin.php';

// JobDiva
require THJB_PLUGIN_INC_PATH . 'jobdiva/functions.php';
require THJB_PLUGIN_INC_PATH . 'jobdiva/import-jobs.php';
