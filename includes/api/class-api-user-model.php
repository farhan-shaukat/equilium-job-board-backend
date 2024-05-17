<?php

namespace THJB\Api;

use WP_Error;

class ApiUserModel extends \WP_User
{
    public function __construct( $user_id )
    {
        parent::__construct( $user_id );
    }

    private function get_user_meta( $key ) {
        $value = get_user_meta( $this->ID , $key , true );
        return ( $value !== '' ) ? $value : false;
    }

    private function set_user_meta( $key , $value ) {
        return update_user_meta( $this->ID , $key , $value );
    }

    private function delete_user_meta( $key ) {
        return delete_user_meta( $this->ID , $key );
    }

    public function set_email($new_email)
    {
        $this->user_email = esc_attr($new_email);
    }

    public function send_reset_code()
    {
        $code = thjb_get_token();

        $expiration = strtotime( '+5 minutes' );
        $expiration = apply_filters('thjb_reset_password_code_expiration_time', $expiration);

        $this->set_user_meta( 'thjb-api-pass-reset-code' , [
            'code' => $code,
            'expiry' => $expiration,
            'attempt' => 0
        ]);

        $from = get_option('thjb_job_alerts_email_from');

        $email_template_path = apply_filters('thjb_reset_pass_email_path', THJB_PLUGIN_DIR_PATH . 'partials/emails/reset-password.php');

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

        return wp_mail( $this->user_email, 'Reset Password Code', $message, $headers );
    }

    public function validate_code( $code )
    {
        $now = strtotime( 'now' );
        $stored_details = $this->get_user_meta( 'thjb-api-pass-reset-code' );

        if( empty($stored_details['code']) ) {
            return new WP_Error('restore_no_code_error', 'You must get reset code before try to reset password',  ['status' => 400]);
        }

        $stored_code = $stored_details['code'];
        $code_expiry = $stored_details['expiry'];
        $attempt = ( isset( $stored_details['attempt'] )) ? $stored_details['attempt'] : 0;
        $attempt++;

        $attempts_max = 3;

        if ( $code !== $stored_code ) {

            $stored_details['attempt'] = $attempt;
            $remaining_attempts = $attempts_max - $attempt;

            $this->set_user_meta( 'thjb-api-pass-reset-code', $stored_details );

            if ( $remaining_attempts <= 0 ) {
                $this->delete_user_meta( 'thjb-api-pass-reset-code' );
            }

            return new WP_Error('restore_code_invalid_error', 'Your reset code is invalid',  ['status' => 400]);
        }

        $expired = true;

        if( $code_expiry === -1 ) {
            $expired = false;
        }

        if( $now > $code_expiry ) {
            $expired = false;
        }

        if( ! $expired ) {
            $this->delete_user_meta( 'thjb-api-pass-reset-code' );
            return new WP_Error('restore_code_error', 'Your reset code is expired',  ['status' => 400]);
        }

        return true;

    }

    public function clear_restore_password_code()
    {
        $this->delete_user_meta( 'thjb-api-pass-reset-code' );
    }

    public function send_rtbf_request()
    {
        $from = get_option('options_request_rtbf_send_email_from');
        $to = get_option('options_request_rtbf_send_email_to');

        $email_template_path = apply_filters('thjb_rtbf_email_path', THJB_PLUGIN_DIR_PATH . 'partials/emails/rtbf-request.php');

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

        return wp_mail( $to, 'RTBF request', $message, $headers );
    }

}