<?php
add_action('wp_enqueue_scripts', 'th_enqueue_scripts');
add_filter('template_include', 'th_dynamic_page_template', 99);
add_action('init', 'th_fake_page_rewrite');

add_filter('wpseo_title', 'th_fake_page_titles', 10, 2);

function th_fake_page_rewrite() {
  global $wp_rewrite;

  //set up our query variable %test% which equates to index.php?test=
  add_rewrite_tag('%thjb%', '([^&]+)');

  add_rewrite_rule('^register/?', 'index.php?thjb=register', 'top');
  add_rewrite_rule('^sign-in/?', 'index.php?thjb=sign-in', 'top');
  add_rewrite_rule('^reset-password/?', 'index.php?thjb=reset-password', 'top');

  add_rewrite_rule('^alert/([^/]*)/?', 'index.php?thjb=$matches[1]', 'top');
  add_rewrite_rule('^update-alert/([^/]*)/?', 'index.php?thjb=$matches[1]', 'top');
  add_rewrite_rule('^create-alert/?', 'index.php?thjb=create-alert', 'top');

  add_rewrite_rule('^register-apply/([^/]*)/?', 'index.php?thjb=$matches[1]', 'top');
  add_rewrite_rule('^sign-in-apply/([^/]*)/?', 'index.php?thjb=$matches[1]', 'top');
  add_rewrite_rule('^apply-for-job/([^/]*)/?', 'index.php?thjb=$matches[1]', 'top');

  add_rewrite_rule('^dashboard/?', 'index.php?thjb=dashboard', 'top');
  add_rewrite_rule('^dashboard/([^/]*)/?', 'index.php?thjb=$matches[1]', 'top');

  //add endpoint, in this case 'test' to satisfy our rewrite rule /test
  add_rewrite_endpoint('thjb', EP_PERMALINK | EP_PAGES);

  //flush rules to get this to work properly (do this once, then comment out)
  $wp_rewrite->flush_rules();
}

function th_dynamic_page_template($template) {
  global $wp;

  $vars = $wp->query_vars;

  if (array_key_exists('thjb', $vars) && $vars['thjb']) {
    return THJB_PLUGIN_DIR_PATH . 'templates/vue-app.php';
  }

  return $template;
}

function th_fake_page_titles($title, $presentation) {
  global $wp;
  $vars = $wp->query_vars;

  if (array_key_exists('thjb', $vars) && $vars['thjb']) {
    if ('dashboard' == $vars['thjb']) {
      $parts = explode('/', $wp->request);
      if (empty($parts[1])) {
        $title = str_replace('Blog', 'Dashboard', $title);
      } else {
        switch ($parts[1]) {
          case 'alerts':
            $title = str_replace('Blog', 'Job alerts', $title);
            break;
          case 'user':
            $title = str_replace('Blog', 'User profile', $title);
            break;
        }
      }
    }
  }

  return $title;
}


function th_enqueue_scripts() {
  wp_enqueue_style('thjb-styles', THJB_PLUGIN_DIR_URL . 'assets/css/app.css', [], time());

  wp_enqueue_script('thjb-scripts-vendor', THJB_PLUGIN_DIR_URL . 'assets/js/chunk-vendors.js', [], null, true);
  wp_register_script('thjb-scripts', THJB_PLUGIN_DIR_URL . 'assets/js/app.js', [], time(), true);
  wp_localize_script('thjb-scripts', 'th_vars', [
    'api_base' => get_rest_url(null, 'thjb/v1'),
  ]);
  wp_enqueue_script('thjb-scripts');
}

function thjb_insert_state_tag($args, $parent = 0) {
  $taxonomy = 'states';

  if (empty($args['name'])) {
    return 0;
  }

  $term_args = [
    'parent' => $parent
  ];

  if (!empty($args['slug'])) {
    $term_args['slug'] = $args['slug'];
  }

  $new_term = wp_insert_term($args['name'], $taxonomy, $term_args);

  if (is_wp_error($new_term)) {
    return 0;
  }

  if (!empty($args['zip'])) {
    add_term_meta($new_term['term_id'], "zip_code", $args['zip']);
  }

  return $new_term['term_id'];
}

function thjb_get_terms_for_filter($taxonomy) {
  $data = [];

  $terms = get_terms([
    'taxonomy' => $taxonomy,
    'hide_empty' => true,
  ]);

  if (!empty($terms)) {

    foreach ($terms as $term) {

      $data[] = [
        'name'  => $term->name,
        'value' => $term->slug,
        'count' => $term->count,
      ];
    }
  }

  return $data;
}

function thjb_find_or_add_simple_tag($name, $taxonomy, $parent_name = '') {
  $result = [];

  $term_args = [
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
    'parent'     => 0,
    'name'       => $name
  ];

  if (!empty($parent_name)) {

    $parent_tag = get_term_by('name', $parent_name, $taxonomy);
    if (empty($parent_tag)) {
      $new_parent_term = wp_insert_term($parent_name, $taxonomy);
      if (!empty($new_parent_term)) {
        $term_args['parent'] = $new_parent_term['term_id'];
      }
    } else {
      $term_args['parent'] = $parent_tag->term_id;
    }
  }

  $terms = get_terms($term_args);

  if (empty($terms)) {

    $new_term = wp_insert_term($name, $taxonomy, $term_args);
    if (is_wp_error($new_term)) {
      return [];
    }

    $term = get_term($new_term['term_id']);

    $result = [
      'term_id' => $term->term_id,
      'parent' => $term->parent,
    ];
  } else {
    $result = [
      'term_id' => $terms[0]->term_id,
      'parent' => $terms[0]->parent,
    ];
  }

  return $result;
}

function thjb_get_timeago_string($time) {
  $time = time() - $time; // to get the time since that moment
  $time = ($time < 1) ? 1 : $time;
  $tokens = array(
    31536000 => 'year',
    2592000 => 'month',
    604800 => 'week',
    86400 => 'day',
    3600 => 'hour',
    60 => 'minute',
    1 => 'second'
  );

  foreach ($tokens as $unit => $text) {
    if ($time < $unit) continue;
    $numberOfUnits = floor($time / $unit);
    return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '') . ' ago';
  }

  return '';
}

function thjb_get_jobs_query_args($params) {
  $args = [
    'post_type'         => 'jobs',
    'post_status'       => 'publish',
    'tax_query'         => [
      'relation' => 'AND'
    ]
  ];

  if (!empty($params['per_page'])) {
    $args['posts_per_page'] = (int) $params['per_page'];
  }

  if (!empty($params['page'])) {
    $args['paged'] = (int) $params['page'];
  }

  if (!empty($params['industries'])) {
    $args['tax_query'][] = [
      'taxonomy' => 'industry',
      'field'    => 'slug',
      'terms'    => $params['industries']
    ];
  }

  if (!empty($params['job-types'])) {
    $args['tax_query'][] = [
      'taxonomy' => 'job-type',
      'field'    => 'slug',
      'terms'    => $params['job-types']
    ];
  }

  if (!empty($params['states'])) {
    $args['tax_query'][] = [
      'taxonomy' => 'states',
      'field'    => 'slug',
      'terms'    => $params['states']
    ];
  }

  if (!empty($params['search'])) {
    $args['s'] = $params['search'];
  }

  return $args;
}


function thjb_api_post_permission_callback($post_type, $post_id) {
  $current_user = get_current_user_id();

  $post = get_post($post_id);
  if (
    !empty($post)
    && $post_type == $post->post_type
    && $current_user == $post->post_author
    && 'publish' == $post->post_status
  ) {
    return true;
  }

  return false;
}

function thjb_validate_non_empty_password($param) {
  $p = trim($param);

  if (6 < strlen($p)) {
    return true;
  }

  return false;
}

function thjb_get_pass_field_params($required = true) {
  return [
    'required'     => $required,
    'type'         => 'string',
    'minLength'    => 6,
    'sanitize_callback' => function ($param) {
      return trim($param);
    },
    'validate_callback' => 'thjb_validate_non_empty_password'
  ];
}

function thjb_check_uploaded_by_api_file($files, $field_name) {
  if (empty($files[$field_name])) {
    return new WP_Error('rest_missing_callback_param', 'CV File is required', [
      'status' => 400,
      'params' => [
        $field_name
      ]
    ], 400);
  }

  if (
    !is_uploaded_file($files[$field_name]['tmp_name'])
    || !$files[$field_name]['error'] === UPLOAD_ERR_OK
  ) {
    return new WP_Error('rest_file_upload_error', 'Error in upload CV File', [
      'status' => 400,
    ], 400);
  }

  return true;
}

function thjb_get_user_suggested_jobs($count = 10) {
  $user_id = get_current_user_id();

  if (0 === $user_id) {
    return [];
  }

  $jobs_list = [];

  $args = [
    'post_type'     => 'job-alerts',
    'post_status'   => 'publish',
    'author__in'    => [$user_id],
    'fields'        => 'ids',
    'orderby'       => 'rand'
  ];

  $alerts_query = new WP_Query($args);

  if ($alerts_query->have_posts()) {
    foreach ($alerts_query->posts as $alert_id) {

      $jobs_args = get_post_meta($alert_id, '_job_alert_query', 1);
      if (empty($jobs_args)) {
        continue;
      }
      $jobs_args['fields'] = 'ids';

      $jobs_query = new WP_Query($jobs_args);
      if ($jobs_query->have_posts()) {
        $jobs_list = array_merge($jobs_list, $jobs_query->posts);
        $jobs_list = array_unique($jobs_list);
        if (count($jobs_list) >= $count) {
          $jobs_list = array_slice($jobs_list, 0, $count);
          break;
        }
      }
    }

    if (!empty($jobs_list)) {
      return $jobs_list;
    }
  }

  return [];
}

function thjb_get_user_saved_jobs($user_id = -1) {
  if (0 > $user_id) {
    $user_id = get_current_user_id();
  }
  $posts_list = get_user_meta($user_id, 'saved_jobs_list', 1);

  if (empty($posts_list)) {
    $posts_list = [];
  }

  $posts_list = apply_filters('thjb_user_saved_jobs_list', $posts_list, $user_id);

  return $posts_list;
}

function thjb_get_user_applied_jobs() {
  $user_id = get_current_user_id();
  $posts_list = get_user_meta($user_id, 'applied_jobs_list', 1);

  if (empty($posts_list)) {
    $posts_list = [];
  }

  $posts_list = apply_filters('thjb_current_user_applied_jobs_list', $posts_list);

  return $posts_list;
}

function thjb_make_job_expired($post_id, $time_shift = 0) {
  $post_data = [
    'ID'            => $post_id,
    'post_status'   => 'expired',
  ];

  wp_update_post($post_data);

  update_post_meta($post_id, '_prepared_to_delete', 1);

  // add cron event to 02:00
  $tomorrow = strtotime('tomorrow 02:00') + ($time_shift * 50);
  wp_schedule_single_event($tomorrow, 'thjb_delete_job_from_saved_lists', [$post_id]);
}

function thjb_is_job_saved($job_id, $saved_list = []) {
  if (empty($saved_list)) {
    $saved_list = thjb_get_user_saved_jobs();
  }

  return in_array($job_id, $saved_list);
}

function thjb_is_job_applied($job_id, $applied_list = []) {
  if (empty($applied_list)) {
    $applied_list = thjb_get_user_applied_jobs();
  }

  return in_array($job_id, $applied_list);
}

function is_job_expired($timestamp, $api_status = 'open') {
  if ('expired' == strtolower($api_status)) {
    return true;
  }

  $current_time = date('U');

  if ($timestamp > $current_time) {
    return false;
  }

  return true;
}

function thjb_shedule_saved_jobs_cleaning_proces() {
  $args = [
    'post_type'     => 'jobs',
    'post_status'   => 'publish',
    'fields'        => 'ids',
    'meta_query'    => [
      'relation'  => 'AND',
      [
        'key'       => 'job_expiry_date',
        'value'     => date('Ymd'),
        'compare'   => '<=',
        'type'      => 'DATE'
      ],
      [
        'key'       => '_prepared_to_delete',
        'compare'   => 'NOT EXISTS',
      ]
    ]
  ];

  $expired_query = new WP_Query($args);

  if ($expired_query->have_posts()) {
    foreach ($expired_query->posts as $i => $job_id) {
      thjb_make_job_expired($job_id, $i);
    }
  }
}

function thjb_delete_job_from_saved_lists($post_id) {
  if (!empty($post_id)) {

    $users = get_users([
      'meta_key'  => 'saved_jobs_list',
      'role'      => 'subscriber',
      'fields'    => 'ids'
    ]);

    if (!empty($users)) {
      foreach ($users as $user_id) {
        $saved_jobs = thjb_get_user_saved_jobs($user_id);
        if (!empty($saved_jobs)) {
          foreach ($saved_jobs as $i => $item) {
            if ($post_id === $item) {
              unset($saved_jobs[$i]);
              break;
            }
          }
          update_user_meta($user_id, 'saved_jobs_list', $saved_jobs);
        }
      }
    }
  }
}

function thjb_get_similar_jobs($jobs, $posts_count = 3, $relation = 'AND') {
  $job_terms = [];

  foreach ($jobs as $job_id) {

    $terms = wp_get_post_terms($job_id, 'industry', ["fields" => "slugs"]);
    if (!empty($terms)) {
      if (empty($job_terms['industry'])) {
        $job_terms['industry'] = $terms;
      } else {
        $job_terms['industry'] = array_unique(array_merge($job_terms['industry'], $terms));
      }
    }

    $terms = wp_get_post_terms($job_id, 'states');
    if (!empty($terms)) {
      foreach ($terms as $term) {
        if (0 != $term->parent) {
          $job_terms['states'][] = $term->slug;
        }
      }
    }
  }

  if (!empty($job_terms['states'])) {
    $job_terms['states'] = array_unique($job_terms['states']);
  }

  if (!empty($job_terms)) {
    $args = [
      'post_type'      => 'jobs',
      'post_status'    => 'publish',
      'posts_per_page' => $posts_count,
      'orderby'        => 'rand',
      'fields'         => 'ids',
      'post__not_in'   => $jobs,
      'tax_query'      => [
        'relation' => $relation
      ]
    ];

    foreach ($job_terms as $tax => $terms) {
      $args['tax_query'][] = [
        'taxonomy' => $tax,
        'field'    => 'slug',
        'terms'    => $terms
      ];
    }

    $jobs_query = new WP_Query($args);

    if ($jobs_query->have_posts()) {
      return $jobs_query->posts;
    }
  }

  return [];
}

function thjb_get_token($size = 10) {
  return substr(md5(rand()), 0, $size);
}

function thjb_is_post_expired($post_id) {
  $expired_date = get_post_meta($post_id, 'job_expiry_date', 1);

  if (empty($expired_date) || ($expired_date > date('Ymd'))) {
    return false;
  }

  return true;
}

function thjb_get_data_dir_path() {
  $upload_dir   = wp_upload_dir();

  if (!empty($upload_dir['basedir'])) {
    $data_dirname = $upload_dir['basedir'] . '/thjb_data';
    if (!file_exists($data_dirname)) {
      wp_mkdir_p($data_dirname);
    }

    return $data_dirname;
  }

  return '';
}

function thjb_get_user_resume_dir_path($user_id) {
  $upload_dir   = thjb_get_data_dir_path();

  if (!empty($upload_dir)) {
    $data_dirname = $upload_dir . '/resume/' . $user_id;
    if (!file_exists($data_dirname)) {
      wp_mkdir_p($data_dirname);
    }

    return $data_dirname;
  }

  return '';
}

function thjb_get_remote_data($base_link, $params = [], $headers = []) {
  if (empty($params)) {
    $api_link = $base_link;
  } else {
    $api_link = add_query_arg($params, $base_link);
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $api_link);
  //curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPod; CPU iPod OS 7_4_0; like Mac OS X) AppleWebKit/602.2 (KHTML, like Gecko) Chrome/50.0.1489.274 Mobile Safari/603.3');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  //curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_NOBODY, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, 0);

  $headers[] = 'Content-Type: application/json';

  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }

  $response = curl_exec($ch);

  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  $body = $response;

  curl_close($ch);

  if (200 == $httpcode && !empty($body)) {
    return $body;
  }

  return '';
}

function thjb_post_remote_data($post_link, $params = [], $headers = [], $query_data = 1) {
  $curl_options = [
    CURLOPT_URL             => $post_link,
    CURLOPT_RETURNTRANSFER  => 1,
    CURLOPT_FOLLOWLOCATION  => 1,
    CURLOPT_NOBODY          => 0,
    CURLOPT_TIMEOUT         => 0,
    CURLOPT_CUSTOMREQUEST   => "POST"
  ];

  $ch = curl_init();
  curl_setopt_array($ch, $curl_options);

  if (!empty($params)) {
    if ($query_data) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }
  }

  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }

  $response = curl_exec($ch);

  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  if (200 == $httpcode && !empty($response)) {
    return $response;
  }

  return '';
}

function thjs_add_user_resume_file($user_id) {
  $source      = $_FILES['cv_file']['tmp_name'];
  $new_filename = sanitize_file_name($_FILES['cv_file']['name']);

  $destination = trailingslashit(thjb_get_user_resume_dir_path($user_id)) . $new_filename;

  if (file_exists($destination)) {
    return new WP_Error('rest_file_upload_conflict', 'Filename exists', [
      'status' => 400,
    ], 400);
  }

  $moved = move_uploaded_file($source, $destination);

  if ($moved) {

    $resume_list = get_user_meta($user_id, '_user_resume_filename_list', 1);

    if (empty($resume_list)) {
      $resume_list = [];
    }
    $resume_list[] = $new_filename;

    update_user_meta($user_id, '_user_resume_filename_list', $resume_list);

    return [
      'path'  => $destination,
      'name'  => $new_filename,
    ];
  }

  return new WP_Error('rest_file_upload_error', 'Error in uploading file', [
    'status' => 400,
  ], 400);
}