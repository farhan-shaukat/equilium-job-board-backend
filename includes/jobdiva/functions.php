<?php

add_filter( 'thjb_jobdiva_api_base_url', 'thjb_jobdiva_api_get_base_url' );
add_filter( 'thjb_jobdiva_import_filter_job_content', 'thjb_jobdiva_job_content_clear_tags' );

add_action( 'thjb_send_candidate_to_external_api', 'thjb_jobdiva_send_candidate', 10, 2 );

function thjb_jobdiva_api_get_base_url($base)
{
    return 'https://api.jobdiva.com/';
}

function thjb_jobdiva_job_content_clear_tags($content)
{
    $content = str_replace('&nbsp;', ' ', $content);
    $content = html_entity_decode($content,  ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);

    return $content;
}

function thjb_jobdiva_auth()
{
    $token = get_transient('thjb_jobdiva_api_token');

    if ( empty($token) ) {
        $api_base = apply_filters('thjb_jobdiva_api_base_url', '');

        $auth_link = $api_base . 'api/authenticate';

        $responce = thjb_get_remote_data($auth_link, [
            'clientid' => get_option('thjb_jobdiva_clientid'),
            'username' => urlencode(get_option('thjb_jobdiva_username')),
            'password' => urlencode(get_option('thjb_jobdiva_password')),
        ]);

        if ( !empty($responce) ) {
            set_transient( 'thjb_jobdiva_api_token', $responce, HOUR_IN_SECONDS * 3 );
            return $responce;
        }

        return '';
    }

    return $token;
}

function thjb_jobdiva_get_posted_jobs()
{
    $api_base = apply_filters('thjb_jobdiva_api_base_url', '');

    $jobs_link = $api_base . 'api/bi/PortalJobsList';

    $auth_token = thjb_jobdiva_auth();

    if ( ! empty($auth_token) ) {

        $resp = thjb_get_remote_data($jobs_link, [
            'portalID' => -1,
            'alternateFormat' => true
        ], ['Authorization: ' . $auth_token] );

        $data = (array) json_decode($resp);

        // if (is_user_logged_in()){
        //     echo "<pre>";
        //     print_r($data);
        //     echo "</pre>";
        // }

        // die();

        if ( ! empty($data['data']) ) {
            return $data['data'];
        }

    } else {
        error_log('empty token');
    }

    return [];
}


function thjb_jobdiva_get_new_updated_jobs()
{
    $api_base = apply_filters('thjb_jobdiva_api_base_url', '');

    $jobs_link = $api_base . 'api/bi/NewUpdatedJobRecords';

    $auth_token = thjb_jobdiva_auth();

    if ( ! empty($auth_token) ) {

        $resp = thjb_get_remote_data($jobs_link, [
            'fromDate'        => urlencode( date('m/d/Y H:i:s', strtotime('-1 day')) ),
            'toDate'          => urlencode( date('m/d/Y H:i:s' ) ),
            'userFieldsName'  => 'Industry',
            'alternateFormat' => true
        ], ['Authorization: ' . $auth_token] );

        return $resp;
    } else {
        error_log('empty token');
    }

    return [];
}

function thjb_jobdiva_get_job_data($job_id)
{
    $api_base = apply_filters('thjb_jobdiva_api_base_url', '');

    $jobs_link = $api_base . 'api/bi/JobDetail';

    $auth_token = thjb_jobdiva_auth();

    if ( ! empty($auth_token) ) {

        $resp = thjb_get_remote_data($jobs_link, [
            'jobId'           => $job_id,
            'alternateFormat' => true,
            'userFieldsName'  => 'Industry'
        ], ['Authorization: ' . $auth_token] );

        $result = (array)$resp;
        if ( ! empty($result[0]) ) {
            $result = (array) json_decode($result[0]);
            if ( ! empty($result['data'][0]) ) {
                return (array)$result['data'][0];
            }
        }

    } else {
        error_log('empty token');
    }

    return [];
}


function thjb_jobdiva_get_jobs_data($jobs = [])
{
    if ( empty($jobs) ) {
        return [];
    }

    $api_base = apply_filters('thjb_jobdiva_api_base_url', '');

    $jobs_link = $api_base . 'apiv2/bi/JobsDetail';

    $auth_token = thjb_jobdiva_auth();

    if ( ! empty($auth_token) ) {

        $jobs_link = add_query_arg([
            'alternateFormat' => true,
            'userFieldsName'  => 'Industry'
        ], $jobs_link);

        foreach ($jobs as $job_id) {
            $jobs_link .= '&jobIds=' . $job_id;
        }

        $resp = thjb_get_remote_data($jobs_link, [], ['Authorization: ' . $auth_token] );

        $result = (array)$resp;

        if ( ! empty($result[0]) ) {
            $result = (array) json_decode($result[0]);
            // if (is_user_logged_in()){
            //     echo "<pre>";
            //     print_r($result);
            //     echo "</pre>";
            // }
    
            if ( ! empty($result['data']) ) {
                return (array)$result['data'];
            }
        }

        // die();

    } else {
        error_log('empty token');
    }

    return [];
}

/*
 * Send apply forms data to Jobdiva endpoints
 *
 *  /api/jobdiva/createCandidate
 * firstName
 * lastName
 * email
 *
 * /api/jobdiva/createSubmittal
 * jobid
 * candidateid
 * submittaldate  [MM/dd/yyyy HH:mm:ss]
 * filename
 * filecontent (string byte)
 *
 */
function thjb_jobdiva_send_candidate($data, $cv_file)
{
    global $thjb_is_send_candidate_success;

    $thjb_is_send_candidate_success = false;

    $job_external_id = get_post_meta($data['job_id'], 'job_external_id', 1);
    if ( empty($job_external_id) ) {
        return;
    }

    // login in jobdiva or get saved token
    $auth_token = thjb_jobdiva_auth();

    if ( empty($auth_token) ) {
        return;
    }

    $api_base = apply_filters('thjb_jobdiva_api_base_url', '');

    // create candidate row
    // $add_candidate_url = $api_base . 'api/jobdiva/createCandidate';

    	// curl_setopt_array($curl, array(
	// 	CURLOPT_URL => $add_candidate_url . '?firstName=' . $data['first_name'] . '&lastName=' . $data['last_name'] . '&email=' . $data['email'],
	// 	// CURLOPT_URL => $update_candidate_url . '?candidateid=' . $candidate_id . '&firstName=' . $data['first_name'] . '&lastName=' . $data['last_name'],
	// 	CURLOPT_RETURNTRANSFER => true,
	// 	CURLOPT_ENCODING => '',
	// 	CURLOPT_MAXREDIRS => 10,
	// 	CURLOPT_TIMEOUT => 0,
	// 	CURLOPT_FOLLOWLOCATION => true,
	// 	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	// 	CURLOPT_CUSTOMREQUEST => 'POST',
	// 	CURLOPT_HTTPHEADER => array(
	// 		'Authorization: ' . $auth_token
	// 	),
	// ));

	// $r = curl_exec($curl);

    $checkAvailableCandidate = $api_base . 'apiv2/jobdiva/searchCandidateProfile';
    $add_resume_url = $api_base . 'api/jobdiva/uploadResume';
    $update_candidate_url = $api_base . 'api/jobdiva/updateCandidateProfile';
    $application_url = $api_base . 'api/jobdiva/createJobApplication';
	
	// $curl = curl_init();

	$data['first_name'] = urlencode($data['first_name']);
	$data['last_name'] = urlencode($data['last_name']);

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $checkAvailableCandidate,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "email": "' . $data['email'] . '"
        }',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: ' . $auth_token
        ),
    ));

    $response = curl_exec($curl);
    $candidateData = json_decode($response);

    curl_close($curl);

    $JD_candidateID = 0;
    if($candidateData[0]->id){
        $JD_candidateID = $candidateData[0]->id;
    }
    
    $user_id = $data['userID'];
    $key = 'JD_candidateID';
    $single = true;
    $user_last = get_user_meta( $user_id, $key, $single );

    if ($user_last){
        $JD_candidateID = $user_last;
    }

    // add CV and candidate
    $contents = file_get_contents($cv_file['path']);
    $file_content = base64_encode($contents);
    
    $resume_data = [
        'candidateid'   => $JD_candidateID,
        'filename'      => $cv_file['name'],
        'filecontent'   => $file_content,
        'resumesource'  => 11679
    ];

    $candidate_id = thjb_post_remote_data($add_resume_url, json_encode($resume_data), [
        'Authorization: ' . $auth_token,
        'Content-Type: application/json'
    ], 0 );

    if ( empty($candidate_id) ) {
        return;
    }

    if ($data['userID']){
        update_user_meta($data['userID'], 'JD_candidateID', $candidate_id);
    }
    
//     error_log(' ** Candidate ID - ' . $candidate_id);

//     error_log(' ** Candidate Name - ' . $data['first_name'] . " " . $data['last_name']);

    $dataToBeAdded = "";

    // Get the current directory path
    $directory = dirname(__FILE__);

    // Set the file path
    $file = $directory . '/data.txt';

    $dataToBeAdded .= $candidateData[0]->id . " ----------- User ID: " . $JD_candidateID ." First Name: " . $data['first_name'] . " - Last Name: " . $data['last_name'] . " - Email: " . $data['email'] . " Job ID: " . $job_external_id . "\n";

    // Write content to the file
    file_put_contents($file, $dataToBeAdded, FILE_APPEND);

	// curl_close($curl);

    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $update_candidate_url . '?candidateid=' . $candidate_id . '&firstName=' . $data['first_name'] . '&lastName=' . $data['last_name'] . '&email=' . $data['email'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $auth_token
    ),
    ));

    $r = curl_exec($curl);

    curl_close($curl);

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $application_url . '?candidateid=' . $candidate_id . '&jobid=' . $job_external_id . '&resumesource=11679',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $auth_token
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    if ($_COOKIE["utm_source"]){

		$curl = curl_init();

        $source = $_COOKIE["utm_source"];
        $medium = $_COOKIE["utm_medium"];
        $campaign = $_COOKIE["utm_campaign"];
        $content = $_COOKIE["utm_content"];

        $userField = "%7B%0A%20%20%22userfieldId%22%3A%20360%2C%0A%20%20%22userfieldValue%22%3A%20%22" . $source . "%22%0A%7D&Userfields=%7B%0A%20%20%22userfieldId%22%3A%20361%2C%0A%20%20%22userfieldValue%22%3A%20%22" . $medium . "%22%0A%7D&Userfields=%7B%0A%20%20%22userfieldId%22%3A%20362%2C%0A%20%20%22userfieldValue%22%3A%20%22" . $campaign . "%22%0A%7D&Userfields=%7B%0A%20%20%22userfieldId%22%3A%20363%2C%0A%20%20%22userfieldValue%22%3A%20%22" . $content . "%22%0A%7D";

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.jobdiva.com/api/jobdiva/updateCandidateUserfields?candidateid=' . $candidate_id . '&overwrite=false&Userfields=' . $userField,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Authorization:' . $auth_token
            ),
          ));

          $response = curl_exec($curl);

          curl_close($curl);
    }

    // add candidate to job
    // $res = thjb_post_remote_data($application_url, [
    //     'candidateid'   => (int) $candidate_id,
    //     'jobid'         => (int) $job_external_id,
    //     'resumesource'  => 11679
    // ], ['Authorization: ' . $auth_token] );

    if ( $response ) {
        $thjb_is_send_candidate_success = true;
    }

}

function thjb_get_jobdiva_post_status($api_status)
{
    /*
     * 0 OPEN
     * 1 ON HOLD
     * 2 FILLED
     * 3 CANCELLED
     * 4 CLOSED
     * 5 EXPIRED
     * 6 IGNORED
     */
    if ( "open" == strtolower($api_status) ) {
        return 'publish';
    } elseif ( "expired" == strtolower($api_status) ) {
        return 'expired';
    }

    return 'draft';
}

function thjb_prepare_jobdiva_data_for_import($job_details)
{
    // if ( empty($job_details['POSTINGDESCRIPTION']) ) {
    //     $description =  html_entity_decode( $job_details["JOBDESCRIPTION"] );
    // } else {
    //     $description = html_entity_decode($job_details['POSTINGDESCRIPTION']);
    // }

    $description = html_entity_decode($job_details['POSTINGDESCRIPTION']);
    // if ( empty($job_details['POSTINGDESCRIPTION']) ) {
    //     $description =  html_entity_decode( $job_details["JOBDESCRIPTION"] );
    // } else {
    // }

    if ( empty($job_details['POSTING_TITLE']) ) {
        $title = html_entity_decode($job_details["JOBTITLE"]);
    } else {
        $title = html_entity_decode($job_details['POSTING_TITLE']);
    }

    $start_date = '';
    $end_date = '';

    if ( ! empty($job_details["STARTDATE"]) ) {
        $date = new DateTime($job_details["STARTDATE"]);
        $start_date = $date->format('Ymd');
    }

    if ( ! empty($job_details["ENDDATE"]) ) {
        $date = new DateTime($job_details["ENDDATE"]);
        $end_date = $date->format('Ymd');
    }

    $result = [
        'id'             => $job_details["ID"],
        'ref_num'        => $job_details["JOBDIVANO"],
        'title'          => $title,
        'description'    => $description,
        'start_date'     => $start_date,
        'end_date'       => $end_date,
        'status'         => strtolower($job_details['JOBSTATUS']),
        'min_rate'       => thjb_format_salary_string($job_details['PAYRATEMIN']),
        'max_rate'       => thjb_format_salary_string($job_details['PAYRATEMAX']),
        'rate_per'       => strtolower($job_details['PAYRATEPER']),
        'industry'       => $job_details['Industry'],
        'job_type'       => $job_details["POSITIONTYPE"],
        'state'          => $job_details["STATE"],
        'city'           => $job_details["CITY"],
        'updated_at'     => $job_details['DATEUPDATED'],
        'fields_updated_at'     => $job_details['DATEUSERFIELDUPDATED'],
    ];

    return $result;
}

function thjb_clear_temp_files_function($filename)
{
    unlink($filename);
}


function thjb_generate_job_salary_string($data)
{
    $salary = '';

    if ( ! empty($data['min_rate']) ) {

        $salary = $data['min_rate'];

        if ( empty($data['max_rate']) ) {
            $salary .= ' - ' . $data['min_rate'];
        } else {
            $salary .= ' - ' . $data['max_rate'];
        }

    } elseif ( ! empty($data['max_rate']) ) {
        $salary = $data['max_rate'] . ' - ' . $data['max_rate'];
    }

    if ( ! empty($salary) && ! empty($data['rate_per']) ) {
        $salary .= ' per ' . $data['rate_per'];
    }

    return $salary;
}

function thjb_format_salary_string($salary)
{
    if ( ! empty($salary) && fmod($salary, 1) !== 0.00 ) {
        return number_format($salary,2,'.', '');
    }

    return $salary;
}
