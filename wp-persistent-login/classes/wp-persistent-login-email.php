<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Well, get lost.' );

/**
 * Class WP_Persistent_Login_Email
 *
 * @since 2.0.14
 */
class WP_Persistent_Login_Email {

    public function __construct() {

        // send test new login email on send_test_email action for authenticated users
        add_action( 'wp_ajax_wppl_send_test_email', array($this, 'send_test_email') );
        add_action( 'wp_ajax_nopriv_wppl_send_test_email', array($this, 'send_test_email') );

    }


    /**
     * send_test_email
     *
     * @return bool
     */
    public function send_test_email() {
        
        if ( !wp_verify_nonce( $_REQUEST['nonce'], 'update_login_history_settings_action')) {
            wp_send_json_error('Nonce not verified', 400);
        }
    
        if( !isset($_REQUEST['email']) ) {
            return false;
        }

        // check if the user is authenticated
        if( ! is_user_logged_in() ) {
            return false;
        }

        $recipient = $_REQUEST['email'];
        $subject = __('[TEST] New login detected', 'wp-persistent-login');

        $dummy_login_data = array(
            'ip' => '0.0.0.0.0',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
            'created_at' => date('Y-m-d H:i:s'),
        );

        // send email to $recipient
        $email_sent = $this->send_new_login_email($recipient, $subject, $dummy_login_data);

        if( $email_sent ) {
            $message = __('Test email sent!', 'wp-persistent-login');
            wp_send_json_success($message, 200);
        } else {
            $message = __('Test email failed to send', 'wp-persistent-login');
            wp_send_json_error($message, 400);
        }

        wp_die();

    }

    
    /**
     * send_new_login_email
     * 
     * @param string $recipient
     * @param object $login_data
     * @return bool
     */
    public function send_new_login_email($recipient, $subject, $login_data) {

        // set subject and headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>',
        );

        // get the device details from the user agent string
        $device = new WhichBrowser\Parser( $login_data['user_agent'] );
        $device_type = ucwords( str_replace( ':', ' ', $device->getType() ) );
        $device_name = $device->toString();
        $device_details = $device_type .' - '. $device_name;

        // get the email template
        $settings = new WP_Persistent_Login_Settings();
        $email_template = $settings->get_notification_email_template();

        // replace the variables in the email template with $login_data
        $email_template = str_replace( '{{SITE_NAME}}', get_bloginfo('name'), $email_template );
        $email_template = str_replace( '{{IP_ADDRESS}}', $login_data['ip'], $email_template );
        $email_template = str_replace( '{{DEVICE_DETAILS}}', $device_details, $email_template );
        $email_template = str_replace( '{{TIMESTAMP}}', $login_data['created_at'], $email_template );

        $sent = wp_mail( $recipient, $subject, $email_template, $headers );

        return $sent;        

    }

}