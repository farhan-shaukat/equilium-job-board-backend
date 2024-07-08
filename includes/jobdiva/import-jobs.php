<?php

function thjb_import_jobs_jobdiva_api()
{
    $import_enabled = get_option('thjb_jobdiva_api_import_enabled');

    if ($import_enabled) {

        $jobs = thjb_jobdiva_get_posted_jobs();

        if ( ! empty($jobs) ) {

            $import_date = date('YmdHi');

            $jobs_count = count($jobs);
            $jobs_last_i = $jobs_count-1;

            $jobs_chunk = [];
            $chunk_size = 12;

            foreach ($jobs as $i => $job) {

                $post_id = thjb_search_post_id_by_external_id($job->JOBID, ['publish', 'draft', 'expired', 'trash']);

                if ( ! empty($post_id) ) {
                    $date_updated = get_post_meta($post_id, 'job_updated_date', 1);
                    $date_fields_updated = get_post_meta($post_id, 'job_fields_updated_date', 1);
					
// 					if (is_user_logged_in()){
// 						if ($job->JOBID == "20486012"){
// 							echo "<pre>";
// 							print_r($job);
// 							echo $date_updated . " - " . $job->DATEUPDATED . " - " . $date_fields_updated . " - " . $job->DATEUSERFIELDUPDATED;
// 							echo "</pre>";							
// 						}
// 					}
					
                    // if post has not been updated since the last import
                    if ($date_updated == $job->DATEUPDATED
                        && $date_fields_updated == $job->DATEUSERFIELDUPDATED) {

                        update_post_meta($post_id, '_job_local_update', $import_date);

						continue;
                    }
                }

                $jobs_chunk[$job->JOBID] = [
                    'post_id'   => $post_id
                ];
// 				if (is_user_logged_in()){
// 					if ($job->JOBID == "20486012"){
// 						echo "<pre>";
// 						print_r("chunk - " . $jobs_chunk);
// 	 					//echo $date_updated . " - " . $job->DATEUPDATED . " - " . $date_fields_updated . " - " . $job->DATEUSERFIELDUPDATED;
// 						echo "</pre>";							
// 					}
// 				}

                if ( count($jobs_chunk) == $chunk_size
                    || $jobs_last_i == $i ) {

                    // process import chunk data
                    $chunk_ids = array_keys($jobs_chunk);

                    $details_data = thjb_jobdiva_get_jobs_data($chunk_ids);

                    if ( ! empty($details_data) ) {
                        foreach ($details_data as $job_data) {
                            $data = thjb_prepare_jobdiva_data_for_import( (array)$job_data );
                            thjb_process_jobdiva_job_import($data, $jobs_chunk, $import_date);
                        }
                    }

                    // clear chunk
                    $jobs_chunk = [];
                }

            }
			
			if ( count($jobs_chunk) < $chunk_size ) {

				// process import chunk data
				$chunk_ids = array_keys($jobs_chunk);

				$details_data = thjb_jobdiva_get_jobs_data($chunk_ids);

				if ( ! empty($details_data) ) {
					foreach ($details_data as $job_data) {
						$data = thjb_prepare_jobdiva_data_for_import( (array)$job_data );
						thjb_process_jobdiva_job_import($data, $jobs_chunk, $import_date);
					}
				}

				// clear chunk
				$jobs_chunk = [];
			}

            wp_schedule_single_event( time() + (10 * MINUTE_IN_SECONDS), 'thjb_hide_closed_jobs', [$import_date]);

        } else {
            error_log('empty jobs');
        }

    }
}

function thjb_search_post_id_by_external_id($external_id, $post_status = 'any')
{
    $posts_query = new WP_Query([
        'post_type'     => 'jobs',
        'post_status'   => $post_status,
        'fields'        => 'ids',
        'meta_key'      => 'job_external_id',
        'meta_value'    => $external_id,
    ]);

    if ( $posts_query->have_posts() ) {
        return $posts_query->posts[0];
    }

    return 0;
}

function thjb_process_jobdiva_job_import($data, $chunk_data, $updated_date)
{
    $is_expired = false;
    $post_id = 0;

    if ( ! empty($data["end_date"]) ) {
        $d = new DateTime($data["end_date"]);
        $is_expired = is_job_expired($d->format('U'), $data["status"]);
    }

    if ( isset($chunk_data[ $data["id"] ]['post_id']) ) {
        $post_id = $chunk_data[ $data["id"] ]['post_id'];
    }

    if ( ! empty($post_id) ) {

        if ( $is_expired ) {

            thjb_make_job_expired($post_id);

        } else {

            $content =  apply_filters('thjb_jobdiva_import_filter_job_content', $data["description"]);

            $post_data = [
                'ID'            => $post_id,
                'post_type'     => 'jobs',
                'post_title'    => $data["title"],
                'post_content'  => $content,
                'post_status'   => thjb_get_jobdiva_post_status($data["status"]),
            ];
			
// 			print_r($post_data);

            $post_id = wp_update_post(wp_slash($post_data));
        }
        
    } elseif ( 'open' == $data["status"] ) {

        $content =  apply_filters('thjb_jobdiva_import_filter_job_content', $data["description"]);

        $post_data = [
            'post_type'     => 'jobs',
            'post_title'    => $data["title"],
            'post_content'  => $content,
            'post_status'   => thjb_get_jobdiva_post_status($data["status"]),
        ];

        $post_id = wp_insert_post(wp_slash($post_data));

        update_post_meta($post_id, 'job_external_id', $data["id"]);
    }

    if ( $post_id ) {

        update_post_meta($post_id, '_job_local_update', $updated_date);

        update_post_meta($post_id, 'job_start_date', $data['start_date']);
        update_post_meta($post_id, 'job_expiry_date', $data['end_date']);

        update_post_meta($post_id, 'job_updated_date', $data['updated_at']);
        update_post_meta($post_id, 'job_fields_updated_date', $data['fields_updated_at']);

        update_post_meta($post_id, 'reference_number', $data['ref_num']);
        
        $jobLocation = $data['city'];
        if ($data['state']) {
            $jobLocation .= ', ' . $data['state'];
        }
        if ($data['zipcode']) {
            $jobLocation .= ', ' . $data['zipcode'];
        }
        if ($data['country']) {
            $jobLocation .= ', ' . $data['country'];
        }

        update_post_meta($post_id, 'job_location', $jobLocation);

        $salary_string = thjb_generate_job_salary_string($data);
        update_post_meta($post_id, 'job_salary', $salary_string);

        if ( ! empty($data["industry"]) ) {
            wp_set_post_terms($post_id, $data["industry"], 'industry');
        }

        if ( ! empty($data["job_type"]) ) {
            wp_set_post_terms($post_id, $data["job_type"], 'job-type');
        }

        if ( ! empty($data["city"]) ) {

            $state = $data["state"];

            if ( false === stripos( addslashes($data["city"]), 'remote' ) ) {
                $city = $data["city"];
            } else {
                $city = 'Remote';
                $state = '';
            }

            $tag = thjb_find_or_add_simple_tag($city, 'states', $state);
            wp_set_post_terms($post_id, $tag, 'states');

        }

        return $post_id;
    }

    return 0;
}

function thjb_hide_closed_jobs_function($import_date)
{
    $posts_query = new WP_Query([
        'post_type'     => 'jobs',
        'post_status'   => 'publish',
        'fields'        => 'ids',
        'posts_per_page' => -1,
        'meta_query'    => [
            'relation'  => 'OR',
            [
                'key'   => '_job_local_update',
                'compare' => 'NOT EXISTS'
            ],
            [
                'key'   => '_job_local_update',
                'value' => $import_date,
                'compare' => '<',
                'type'  => 'NUMERIC',
            ],
        ]
    ]);

    if ( $posts_query->have_posts() ) {
        foreach ($posts_query->posts as $pid) {
            wp_trash_post($pid, 0);
        }
    }
}
