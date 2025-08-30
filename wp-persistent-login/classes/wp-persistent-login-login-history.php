<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Well, get lost.' );

/**
 * Class WP_Persistent_Login_Login_History
 *
 * @since 2.0.0
 */
class WP_Persistent_Login_Login_History {

    protected $history_enabled;
    protected $has_table;
    protected $table_name = 'wppl_login_history';

    /**
	 * __construct
	 *
     * @since  2.0.9
	 * @return void
	 */
	public function __construct() {

        // check if the login history feature is enabled
        $featureOptions = get_option( 'persistent_login_feature_options', array() );
        if( !isset($featureOptions['enableLoginHistory']) || $featureOptions['enableLoginHistory'] !== '1' ) {
            return; // stop processing if login history is not enabled
        }

        $this->history_enabled = $this->is_login_history_enabled();

        // empty the login history table if requested
        add_action( 'admin_post_wppl_empty_login_history_table', array($this, 'empty_login_history_table'));

        if( $this->history_enabled === true ) {
            // after login check if they user is using a known device
            add_action( 'wp_login', array($this, 'check_login_history'), 10, 2 );
        }

        // Hook into profile page to display login history (available for all users)
        if( $this->is_user_login_history_display_enabled() ) {
            add_action( 'show_user_profile', array($this, 'display_user_login_history_on_profile') );
            add_action( 'edit_user_profile', array($this, 'display_user_login_history_on_profile') );
        }

	}

    /**
     * check_login_history
     */
    public function check_login_history($user_login, $user) {

        // check if the user is logging in and if so, check if they are using a known device
        $is_known_device = $this->is_known_device($user->ID);

        // if the user is not using a known device, add a new entry to the login history table
        if( $is_known_device === false ) {
            
            $login_history_id = $this->add_device_to_login_history($user->ID);

            if( $this->is_notify_new_logins_enabled() === true ) {
                $this->notify_user_of_new_login($user->ID, $login_history_id);
            }
        
        } else {

            // if the user is using a known device, update the last login date
            
            // get the current device id
            $device_id = $this->create_login_history_device_id( $user->ID, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'] );

            // update the last login date
            global $wpdb;
            $table_name = $wpdb->prefix . $this->table_name;
            $wpdb->update( $table_name, array('created_at' => current_time('mysql')), array('device_id' => $device_id) );

        }

    }

    
    /**
     * is_login_history_enabled
     *
     * @return bool
     */
    private function is_login_history_enabled() {

        // check if login history is enabled
        $options = get_option('persistent_login_options');

        if( isset($options['enableLoginHistory']) ) {
            $is_login_history_enabled = $options['enableLoginHistory'];
        } else {
            $is_login_history_enabled = '0';
        }

        return (bool) $is_login_history_enabled;

    }


    /**
     * is_notify_new_logins_enabled
     */
    private function is_notify_new_logins_enabled() {

        // check if login history is enabled
        $options = get_option('persistent_login_options');

        if( isset($options['notifyNewLogins']) ) {
            $is_notify_new_logins_enabled = $options['notifyNewLogins'];
        } else {
            $is_notify_new_logins_enabled = '0';
        }

        return (bool) $is_notify_new_logins_enabled;

    }



    
    /**
     * has_login_history_table
     *
     * @return bool
     */
    public function has_login_history_table() {

        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_name;

        // SQL query to check if the table exists
        $sql = "SHOW TABLES LIKE '$table_name';";

        // Get the result of the query
        $result = $wpdb->get_var( $sql );

        // Return true if the table exists, false otherwise
        return (bool) $result;

    }

    
    /**
     * create_login_history_table
     *
     * @return bool
     */
    public function create_login_history_table() {

        global $wpdb;
    
        $table_name = $wpdb->prefix . $this->table_name;
        $charset_collate = $wpdb->get_charset_collate();
    
        // SQL query to create the table
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_id VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            ip VARCHAR(25) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
    
        // Execute the query
        $wpdb->query( $sql );

        return true;
    }

    
    /**
     * empty_login_history_table
     *
     * @return bool
     */
    public function empty_login_history_table() {

        $nonce_check = wp_verify_nonce( $_POST['_wpnonce'], 'wppl_empty_login_history_table' );
        if( $nonce_check === false ) {
            echo 'Error 980: Sorry, there was an error, please try again.';
            die;
        }

        // empty the login history table
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $result = $wpdb->query( "TRUNCATE TABLE $table_name" );

        if( $result === false ) {
            $message = 'Error 981: Sorry, there was an error emptying the login history table. Please try again later.';
            $type = 'error';
        } else {
            $message = 'All login history has been cleared.';
            $type = 'updated';
        }
        
        // return true;
        // redirect the user and notify setting updated
        $code = 302;
        $redirect_url = esc_url_raw($_POST['_wp_http_referer']);

        // Add query parameters for message and type
        $redirect_url = add_query_arg(
            array(
                'wppl-msg' => urlencode($message),
                'type' => $type
            ),
            $redirect_url
        );
        
        // Perform the redirect
        wp_redirect($redirect_url, $code);

    }

    
    /**
     * add_device_to_login_history
     *
     * @return bool
     */
    public function add_device_to_login_history($user_id) {
        
        // add a new entry to the login history table
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $ip = $_SERVER['REMOTE_ADDR'];
        $device_id = $this->create_login_history_device_id( $user_id, $ua, $ip );

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $data = array('user_id' => $user_id, 'device_id' => $device_id, 'user_agent' => $ua, 'ip' => $ip);
        $format = array('%d', '%s', '%s','%s');
        $wpdb->insert($table_name, $data, $format);
        $item_id = $wpdb->insert_id;

        return $item_id;

    }

    /**
     * fetch_login_history
     * 
     * @param int $id
     */
    private function fetch_login_history($id) {

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $sql = "SELECT * FROM $table_name WHERE id = '$id' LIMIT 1;";
        $result = $wpdb->get_results( $sql, ARRAY_A );

        if( $result ) {
            return $result[0];
        } else {
            return false;
        }

    }

    
    /**
     * is_known_device
     *
     * @param  int $user_id
     * @param  string $cookie
     * @return bool
     */
    public function is_known_device($user_id) {
        
        // get the current user agent string
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        // get the current ip address
        $ip = $_SERVER['REMOTE_ADDR'];

        // create a unique id for the login history table
        $device_id = $this->create_login_history_device_id( $user_id, $user_agent, $ip );

        // check if the device_id exists in the login history table
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $sql = "SELECT * FROM $table_name WHERE device_id = '$device_id' AND user_id = '$user_id' LIMIT 1;";
        $result = $wpdb->get_results( $sql );

        if( count($result) > 0 ) {
            return true;
        } else {
            return false;
        }

    }

    
    /**
     * notify_user_of_new_login
     *
     * @param  int $user_id
     * @param  string $cookie
     * @param  array $login_data
     * @return bool
     */
    private function notify_user_of_new_login($user_id, $login_history_id) {

        // get the users email
        $user = get_user_by( 'id', $user_id );
        $user_email = $user->user_email;

        // $subject = __('New Login Detected', 'wp-persistent-login');
        $subject = get_option('persistent_login_notification_email_subject') ?: __('New Login Detected on your account', 'wp-persistent-login');

        // fetch the login history data
        $login_data = $this->fetch_login_history($login_history_id);

        if( $login_data === false ) {
            return false;
        }

        // email the user about a new login so they are aware.
        $email = new WP_Persistent_Login_Email();
        $email->send_new_login_email($user_email, $login_data);

    }


    /**
     * create_login_history_device_id
     * 
     * @param int $user_id
     * @param string $ua
     * @param string $ip
     */
    private function create_login_history_device_id($user_id, $ua, $ip) {

        // create a unique id for the login history table
        $device_id = $user_id . '_' . md5( $user_id . $ua );

        return $device_id;
    
    }


    /**
     * get_user_count_with_login_history
     * 
     * @return int
     */
    public function get_user_count_with_login_history() {

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $sql = "SELECT COUNT(DISTINCT user_id) FROM $table_name;";
        $result = $wpdb->get_var( $sql );

        return $result;

    }


    /**
     * get_logged_in_users_count
     * 
     * @return int
     */
    public function get_logged_in_users_count() {

        $user_count = new WP_Persistent_Login_User_Count();
        $total_logged_in_users = $user_count->get_user_count();

        return $total_logged_in_users;

    }


    /**
     * is_user_login_history_display_enabled
     * 
     * Check if the user login history display feature is enabled
     * 
     * @since 2.1.3
     * @return bool
     */
    protected function is_user_login_history_display_enabled() {

        // check if login history display is enabled
        $options = get_option('persistent_login_options');

        if( isset($options['displayUserLoginHistory']) ) {
            $display_user_login_history = $options['displayUserLoginHistory'];
        } else {
            $display_user_login_history = '0';
        }

        return (bool) $display_user_login_history;

    }


    /**
     * get_user_login_history_data
     * 
     * Get login history data for a specific user and return formatted table HTML
     * 
     * @since 2.1.3
     * @param int $user_id User ID to get login history for
     * @param int $limit Optional limit for number of records (default: 50)
     * @return string HTML table or empty string if feature disabled
     */
    public function get_user_login_history_data($user_id, $limit = 50) {

        // Check if the feature is enabled
        if( !$this->is_user_login_history_display_enabled() ) {
            return '';
        }

        // Validate user ID
        if( !is_numeric($user_id) || $user_id <= 0 ) {
            return '<p>' . __('Invalid user ID.', 'wp-persistent-login') . '</p>';
        }

        global $wpdb;

        // Get login history for the specific user
        $table_name = $wpdb->prefix . $this->table_name;
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        );
        $results = $wpdb->get_results($sql);

        if( empty($results) ) {
            return '<p>' . __('No login history found for this user.', 'wp-persistent-login') . '</p>';
        }

        // Generate HTML table with unified wppl-table class and WordPress core classes
        $html = '<div class="wppl-table-container">';
        $html .= '<table class="wppl-table wp-list-table widefat fixed striped">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>' . __('Date & Time', 'wp-persistent-login') . '</th>';
        $html .= '<th>' . __('IP Address', 'wp-persistent-login') . '</th>';
        $html .= '<th>' . __('Browser/Device', 'wp-persistent-login') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach( $results as $record ) {
            // Parse user agent for better display
            $user_agent_info = $this->parse_user_agent($record->user_agent);
            
            // Format the date
            $formatted_date = date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($record->created_at)
            );

            $html .= '<tr>';
            $html .= '<td data-label="' . esc_attr(__('Date & Time', 'wp-persistent-login')) . '">' . esc_html($formatted_date) . '</td>';
            $html .= '<td data-label="' . esc_attr(__('IP Address', 'wp-persistent-login')) . '">' . esc_html($record->ip) . '</td>';
            $html .= '<td data-label="' . esc_attr(__('Browser/Device', 'wp-persistent-login')) . '">' . esc_html($user_agent_info) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '<p class="wppl-table-description">' . sprintf(__('Showing last %d login attempts.', 'wp-persistent-login'), count($results)) . '</p>';
        $html .= '</div>';

        return $html;

    }


    /**
     * parse_user_agent
     * 
     * Parse user agent string to extract browser and device information
     * 
     * @since 2.1.3
     * @param string $user_agent
     * @return string Formatted browser/device info
     */
    protected function parse_user_agent($user_agent) {

        if( empty($user_agent) ) {
            return __('Unknown Browser', 'wp-persistent-login');
        }

        // Check if WhichBrowser is available (premium feature)
        if( class_exists('WhichBrowser\Parser') ) {
            // Use WhichBrowser library for accurate device detection
            $device = new WhichBrowser\Parser($user_agent);
            $device_type = ucfirst($device->device->type);
            $browser = $device->browser->name;
            $os = $device->os->toString();
            $the_device = $device->device->toString().' ';

            return $browser .' '. __('on', 'wp-persistent-login' ) .' ' . $the_device . $os .' ('. $device_type .')';
        }

        // Fallback to basic user agent parsing for non-premium
        if( preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent) ) {
            $browser = 'Internet Explorer';
        } elseif( preg_match('/Firefox/i', $user_agent) ) {
            $browser = 'Firefox';
        } elseif( preg_match('/Chrome/i', $user_agent) ) {
            $browser = 'Chrome';
        } elseif( preg_match('/Safari/i', $user_agent) ) {
            $browser = 'Safari';
        } elseif( preg_match('/Opera/i', $user_agent) ) {
            $browser = 'Opera';
        } elseif( preg_match('/Netscape/i', $user_agent) ) {
            $browser = 'Netscape';
        } else {
            $browser = __('Unknown Browser', 'wp-persistent-login');
        }

        // Basic OS detection
        if( preg_match('/linux/i', $user_agent) ) {
            $os = 'Linux';
        } elseif( preg_match('/macintosh|mac os x/i', $user_agent) ) {
            $os = 'Mac';
        } elseif( preg_match('/windows|win32/i', $user_agent) ) {
            $os = 'Windows';
        } else {
            $os = __('Unknown OS', 'wp-persistent-login');
        }

        return $browser . ' ' . __('on', 'wp-persistent-login') . ' ' . $os;

    }


    /**
     * display_user_login_history_on_profile
     * 
     * Display login history on user profile page
     * 
     * @since 2.1.3
     * @param WP_User $user User object
     * @return void
     */
    public function display_user_login_history_on_profile($user) {

        // Check if feature is enabled
        if( !$this->is_user_login_history_display_enabled() ) {
            return;
        }

        // Check permissions - only show to the user themselves or administrators
        $current_user_id = get_current_user_id();
        if( $current_user_id !== $user->ID && !current_user_can('manage_options') ) {
            return;
        }

        echo '<h2>' . __('Login History', 'wp-persistent-login') . '</h2>';
        echo $this->get_user_login_history_data($user->ID, 25);

    }


}

?>