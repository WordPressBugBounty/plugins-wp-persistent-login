<?php


// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Well, get lost.' );

/**
 * Class WP_Persistent_Login_Admin
 *
 * @since 2.0.0
 */
class WP_Persistent_Login_Admin {


	
    /**
	 * Initialize the class and set its properties.
	 *
	 * We register all our common hooks here.
	 *
	 * @since  1.4.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'plugin_action_links_'.WPPL_PLUGIN_BASENAME, array($this, 'add_settings_link') );
		add_action('admin_menu', array($this, 'create_menu_page') );

		add_action( 'wp_ajax_wppl_stop_user_count', array( $this, 'ajax_stop_user_count' ) );
		
	}


		
	/**
	 * add_settings_link
	 *
	 * @since 2.0.0
	 * @param  array $links
	 * @return array
	 */
	public function add_settings_link( $links ) {

		$settings_link = '<a href="'.WPPL_SETTINGS_PAGE.'">' . __('Settings', 'wp-persistent-login' ) . '</a>';
		array_unshift($links, $settings_link);
		
		return $links;
	
	}


	
	/**
	 * create_menu_page
	 *
	 * @since 2.0.0
	 * @return void
	 */	public function create_menu_page() {

		add_submenu_page( 
			'users.php', 
			'Persistent Login', 
			'Persistent Login', 
			'administrator',
			'wp-persistent-login', 
			array($this, 'display_settings_page')
		); 
	
	}
    /**
     * Display the settings page content based on the current tab
     * 
     * @since 2.2.0
     * @return void
     */
    public function display_settings_page() {
        $default_tab = NULL;
        $tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
        
        $dashboard = new WP_Persistent_Login_Dashboard();
          if ($tab === 'persistent-login') {
            $dashboard->display_persistent_login_settings();
        } elseif ($tab === 'active_logins') {
            $dashboard->display_active_logins_settings();
        } elseif ($tab === 'login_history') {
            $dashboard->display_login_history_settings();
        } else {
            $dashboard->display_dashboard();
        }
    }


	/**
     * AJAX handler to stop user count on demand
     *
     * @since 2.3.0
     * @return void
     */
    public function ajax_stop_user_count() {

        // Verify nonce for security
        if ( ! check_ajax_referer( 'wppl_stop_user_count', 'nonce', false ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Security check failed', 'wp-persistent-login' )
                )
            );
        }
        // if ( ! wp_verify_nonce( $_POST['nonce'], 'wppl_stop_user_count' ) ) {
        //     wp_send_json_error( array( 'message' => __( 'Security check failed', 'wp-persistent-login' ) ) );
        //     return;
        // }

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to perform this action.', 'wp-persistent-login' ) ) );
            return;
        }

        try {
            // Initialize the user count class
            $count = new WP_Persistent_Login_User_Count();
            
            // Check if a count is actually running
            if ( ! $count->is_user_count_running() ) {
                wp_send_json_error( array( 
                    'message' => __( 'No user count is currently running.', 'wp-persistent-login' )
                ) );
                return;
            }
            
            // Stop the count and update the user count breakdown
            $count->stop_count(true);

            wp_send_json_success( array(
                'message' => __( 'User count stopped successfully! The count data has been saved.', 'wp-persistent-login' )
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array( 
                'message' => sprintf( __( 'Failed to stop user count: %s', 'wp-persistent-login' ), $e->getMessage() )
            ) );
        }
    }

	
}

?>