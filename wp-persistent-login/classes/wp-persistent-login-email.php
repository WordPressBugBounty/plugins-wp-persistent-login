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

        // send test account inactivity email on wppl_send_inactivity_test_email action for authenticated users
        add_action( 'wp_ajax_wppl_send_inactivity_test_email', array($this, 'send_inactivity_test_email') );
        add_action( 'wp_ajax_nopriv_wppl_send_inactivity_test_email', array($this, 'send_inactivity_test_email') );

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

        $dummy_login_data = array(
            'user_id' => get_current_user_id(),
            'ip' => '0.0.0.0.0',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
            'created_at' => date('Y-m-d H:i:s'),
        );

        // send email to $recipient
        $email_sent = $this->send_new_login_email($recipient, $dummy_login_data);

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
    public function send_new_login_email($recipient, $login_data) {

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

        // get user details from the user_id inside $login_data
        $user = get_user_by('id', $login_data['user_id']);
        $user_email = $user->user_email;
        $username = $user->user_login;
        $user_display_name = $user->display_name;
        $user_first_name = $user->first_name;
        $user_last_name = $user->last_name;

        // setup an array of variables to replace in the email template and subject
        $email_variables = array(
            '{{SITE_NAME}}' => get_bloginfo('name'),
            '{{IP_ADDRESS}}' => $login_data['ip'],
            '{{DEVICE_DETAILS}}' => $device_details,
            '{{TIMESTAMP}}' => $login_data['created_at'],
            '{{USER_EMAIL}}' => $user_email,
            '{{USERNAME}}' => $username,
            '{{USER_DISPLAY_NAME}}' => $user_display_name,
            '{{USER_FIRST_NAME}}' => $user_first_name,
            '{{USER_LAST_NAME}}' => $user_last_name,
        );

        $settings = new WP_Persistent_Login_Settings();
        
        // get the email subject
        $subject = $settings->get_notification_email_subject();

        // replace the variables in the subject
        foreach( $email_variables as $key => $value ) {
            $subject = str_replace( $key, $value, $subject );
        }

        // get the email template
        $email_template = $settings->get_notification_email_template();

        // replace the variables in the email template
        foreach( $email_variables as $key => $value ) {
            $email_template = str_replace( $key, $value, $email_template );
        }

        $sent = wp_mail( $recipient, $subject, $email_template, $headers );

        return $sent;        

    }



    /**
     * send_inactivity_test_email
     *
     * @return bool
     */
    public function send_inactivity_test_email() {

        
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

        $dummy_user_data = array(
            'user_id' => get_current_user_id(),
            'last_login' => date('Y-m-d H:i:s', strtotime('-3 days')),
        );

        // setup dummy user data as an object
        $dummy_user_data = (object) $dummy_user_data;

        // send email to $recipient
        $email_sent = $this->send_account_inactivity_email($dummy_user_data);

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
     * send_account_inactivity_email
     * 
     * @param object $user
     * @return bool
     * @since 2.1.2
     */
    public function send_account_inactivity_email($user) {

        // set subject and headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>',
        );

        // get user details from the user_id inside $login_data
        $last_login = $user->last_login;
        $user = get_user_by('id', $user->user_id);
        $user_email = $user->user_email;
        $username = $user->user_login;
        $user_display_name = $user->display_name;
        $user_first_name = $user->first_name;
        $user_last_name = $user->last_name;

        // calculate the number of days since the last login
        $inactivity_days = floor( ( time() - strtotime($last_login) ) / 86400 );

        // setup an array of variables to replace in the email template and subject
        $email_variables = array(
            '{{SITE_NAME}}' => get_bloginfo('name'),
            '{{USER_EMAIL}}' => $user_email,
            '{{USERNAME}}' => $username,
            '{{USER_DISPLAY_NAME}}' => $user_display_name,
            '{{USER_FIRST_NAME}}' => $user_first_name,
            '{{USER_LAST_NAME}}' => $user_last_name,
            '{{INACTIVITY_DAYS}}' => $inactivity_days,
        );

        $settings = new WP_Persistent_Login_Settings_Premium();
        
        // get the email subject
        $subject = $settings->get_inactivity_email_subject();

        // replace the variables in the subject
        foreach( $email_variables as $key => $value ) {
            $subject = str_replace( $key, $value, $subject );
        }

        // get the email template
        $email_template = $settings->get_inactivity_email_template();

        // replace the variables in the email template
        foreach( $email_variables as $key => $value ) {
            $email_template = str_replace( $key, $value, $email_template );
        }

        $sent = wp_mail( $user_email, $subject, $email_template, $headers );

        return $sent;

    }

}