<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Process terminated.' );

/**
 * Class WP_Persistent_login
 *
 * @since 2.0.0
 */
class WP_Persistent_Login {


	public int $expiration;
	public ?string $device_id = NULL;
	public ?int $device_id_expiration = NULL;


    /**
	 * Initialize the class and set its properties.
	 *
	 * We register all our common hooks here.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {

		// set the default expiration time to 1 year
		$this->expiration = YEAR_IN_SECONDS;

		// fetch or create the device_id cookie and store its value in the class property
		if (isset($_COOKIE['wppl_device_id']) ) {
			$clean_cookie = stripslashes( $_COOKIE[ "wppl_device_id" ] );
			$cookie = json_decode( $clean_cookie );
			$device_id = $cookie->data->wppl_device_id ?? NULL;
			$this->device_id = $device_id;
		} else {
			// $this->device_id = wp_generate_uuid4();
			$this->device_id = NULL; // device_id will be generated later when the cookie is created
		}

		// fetch or create the device_id cookie expiration and store its value in the class property
		if (isset($_COOKIE['wppl_device_id']) ) {
			$clean_cookie = stripslashes( $_COOKIE[ "wppl_device_id" ] );
			$cookie = json_decode( $clean_cookie );
			$cookie_expiration = $cookie->expiry ?? NULL;
			$this->device_id_expiration = $cookie_expiration;
		} else {
			$this->device_id_expiration = time() + $this->expiration;
		}

		// Check if persistent login feature is enabled
		$featureOptions = get_option('persistent_login_feature_flags', array());
		if( !isset($featureOptions['enablePersistentLogin']) || $featureOptions['enablePersistentLogin'] !== '1' ) {
			return; // stop processing if persistent login is not enabled
		}

		// set a cookie with a unique identifier for the device when a user logs in.
		add_action( 'wp_login', array( $this, 'set_device_uuid_cookie' ), 10, 2 );
		
		// set the expiration time when a user logs in
		add_filter( 'auth_cookie_expiration', array( $this, 'set_login_expiration' ), 10, 3 );

		// increase the cookie time when a user revisits
		add_action( 'set_current_user', array( $this, 'update_auth_cookie' ), 10, 0 );

		// update the device UUID cookie expiration time when a user logs in again to keep the cookie alive
		add_action( 'set_current_user', array( $this, 'update_device_uuid_cookie_expiration' ), 20, 0 );

		// add device_id to the session tokens when a user logs in
		add_filter( 'attach_session_information', array( $this, 'update_session_tokens_with_device_id' ), 10, 2 );

		// set user meta if the user want to be remembered
		add_filter( 'secure_signon_cookie', array( $this, 'remember_me_meta' ), 20, 2 ); 

		// pre-check the remember me box
		add_action( 'wp_footer', array( $this, 'precheck_remember_me' ) );
		add_filter( 'login_footer', array( $this, 'precheck_remember_me' ) );
		
		// logout management
		add_action( 'clear_auth_cookie', array( $this, 'logout' ) );

		// woocommerce auto remember users on register
		add_filter( 'woocommerce_login_credentials', array( $this, 'woocommerce_remember_on_login' ), 20, 1 );

		// add support for persistent login with WP Web WooCommerce Social Login
		add_action('woo_slg_login_user_authenticated', array( $this, 'wpweb_woocommerce_remember_on_login' ), 20, 2 );

	}



	/**
	 * create_device_uuid_cookie
	 * 
	 * Either creates a new device UUID cookie or updates the existing one with a new expiration time.
	 */
	public function create_device_uuid_cookie() {

		$data = (object) array( "wppl_device_id" => $this->device_id );
		$cookieData = (object) array( "data" => $data, "expiry" => $this->device_id_expiration );

		setcookie(
			'wppl_device_id',
			json_encode( $cookieData ),
			time() + $this->expiration,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		return $this->device_id;

	}


	/**
	 * set_device_uuid_cookie
	 */
	public function set_device_uuid_cookie($user_login, $user) {

		// update existing cookie or create a new cookie if the device ID is not set
		$this->create_device_uuid_cookie();
		
	}

	/**
	 * update_device_uuid_cookie_expiration
	 */
	public function update_device_uuid_cookie_expiration() {

		// 1. Handle logged-in users who do NOT have a device_id cookie yet
		if ( ! isset( $_COOKIE['wppl_device_id'] ) && is_user_logged_in() ) {

			$user_id = get_current_user_id();

			// Concurrency Lock: Prevent multiple background requests from running this simultaneously
			// if ( get_transient( 'wppl_backfill_lock_' . $user_id ) ) {
			// 	return;
			// }
			// set_transient( 'wppl_backfill_lock_' . $user_id, 'locked', 10 ); // Lock for 10 seconds

			// Runtime Loop Lock: Stop recursion loops or multiple hooks in the same request thread
			static $is_processing = false;
			if ( $is_processing ) {
				return;
			}
			$is_processing = true;

			// Generate the device_id if it doesn't exist yet
			$this->device_id = wp_generate_uuid4();
			
			// Generate a fresh cookie
			$this->create_device_uuid_cookie();
			
			// Update their active session data in the DB so it includes this new device_id
			$manager = WP_Session_Tokens::get_instance( $user_id );
			$cookie  = wp_parse_auth_cookie( '', 'logged_in' );
			
			if ( $cookie && ! empty( $cookie['token'] ) ) {
				$session = $manager->get( $cookie['token'] );
				if ( $session ) {
					$session['wppl_device_id'] = $this->device_id;
					$manager->update( $cookie['token'], $session );
				}
			}

			// Backfill the custom login history table so history features don't break
			global $wpdb;
			$history_table = $wpdb->prefix . 'wppl_login_history';

			// Verify table exists before inserting to avoid crashes during updates
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $history_table ) );

			if ( $table_exists ) {
				// Check if a record already exists to prevent duplicate rows on page refreshes
				$record_exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $history_table WHERE user_id = %d AND device_id = %s",
					$user_id,
					$this->device_id
				) );

				if ( ! $record_exists ) {
					$wpdb->insert(
						$history_table,
						array(
							'user_id'    => $user_id,
							'device_id'  => $this->device_id,
							'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
							'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
							'created_at' => current_time( 'mysql' )
						),
						array( '%d', '%s', '%s', '%s', '%s' )
					);
				}
			}

			// Release the lock
			// delete_transient( 'wppl_backfill_lock_' . $user_id );
			
			return;
		}

		// 2. Handle logged-in users who already have a device_id cookie
		if (isset($_COOKIE['wppl_device_id']) ) {

			$does_cookie_need_updating = $this->does_cookie_need_updating( $this->device_id_expiration );

			if( $does_cookie_need_updating == true ) {
				$this->create_device_uuid_cookie();
			}

		}

	}


	/**
	 * update_session_tokens_with_device_id
	 * 	 * 
	 * Sets a cookie with a unique identifier for the device. This is used to identify the device for active login management and login history features.
	 * 
	 * @param array $session_data The session data array that will be stored in the database for the current session.
	 * @param int $user_id The ID of the user who is logging in.
	 */
	public function update_session_tokens_with_device_id($session_data, $user_id) {

		$session_data['wppl_device_id'] = $this->device_id; // add the device ID to the session data

		return $session_data;

	}


	/**
	 * set_login_expiration
	 * 
	 * Adjust the login expiration time if the user selected to be remembered.
	 *
	 * @param  int $expiration
	 * @param  int $user_id
	 * @param  bool $remember
	 * @return int $expiration
	 */
	public function set_login_expiration( $expiration, $user_id, $remember ) {
			
		// the the user wants to be remembered, set the expiration time to 1 year
		if( $remember ) :
						
			// default expiration time to 1 year
			$expiration = $this->expiration;
												
		endif;
	  
		/**
		 * Filter hook to change the expiration time manually
		 *
		 * @param int $expiration Expiration time in seconds.
		 * @param int $user_id The current Users ID.
		 * @param bool $remember Boolean value for if the user selected to be remembered.
		 *
		 * @since 1.4.0
		 */
		return apply_filters( 'wp_persistent_login_auth_cookie_expiration', $expiration, $user_id, $remember );
	  
	}



	
	/**
	 * remember_me_meta
	 * 
	 * Adds meta data to the user. If set, extends their login cookie every time they login.
	 *
	 * @param  bool $secure_cookie
	 * @param  array $credentials
	 * @return bool
	 */
	public function remember_me_meta( $secure_cookie, $credentials ) { 

		if( $credentials['user_login'] != null ) {

			$user = get_user_by('login', $credentials['user_login']);

			if ( !empty( $user ) ) {			
			
				if( $credentials['remember'] === true ) {
					update_user_meta( $user->ID, 'persistent_login_remember_me', 'true');
				}
				
				if( $credentials['remember'] === false ) {
					delete_user_meta( $user->ID, 'persistent_login_remember_me', 'true');
				}
			
			}
			
			return $secure_cookie; 

		}
				
		

	} 


		
	/**
	 * update_auth_cookie
	 * 
	 * Reset authentication cookie expiry to keep the user logged in
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function update_auth_cookie() {

		$user = wp_get_current_user();
				
		if( !is_wp_error($user) && NULL !== $user ) :

			// set users local cookie again - checks if they should be remembered
			$remember_user_check = get_user_meta( $user->ID, 'persistent_login_remember_me', true );
			$cookie = wp_parse_auth_cookie('', 'logged_in');
			
			// if there's no cookie, stop processing
			if( !$cookie ) {
				return;
			}

			$cookie_expiration = $cookie['expiration'];
			$does_cookie_need_updating = $this->does_cookie_need_updating( $cookie_expiration );
						
			if( $remember_user_check === 'true' && $does_cookie_need_updating == true ) :
				
				// get the session verifier from the token
					$session_token = $cookie['token'];
					$verifier = $this->get_session_verifier_from_token( $session_token );		
					
				// get the current users sessions
					$sessions = get_user_meta( $user->ID, 'session_tokens', true );
						
					if( $sessions != '' ) {

						// update the login time, expires time if the user has sessions
							$this->update_cookie_expiry($sessions, $session_token, $user->ID, $verifier);

						// apply filter for allowing duplicate sessions, default false
							$currentOptions = get_option( 'persistent_login_options' );
							$allowDuplicateSessions = $currentOptions['duplicateSessions'];
								
						// remove any exact matches to this session
							if( $allowDuplicateSessions === '0' ) :
								$this->remove_duplicate_sessions($sessions, $verifier, $user->ID);
							endif;

					}				
				
				// if the user should be remembered, reset the cookie so the cookie time is reset
					wp_set_auth_cookie( $user->ID, true, is_ssl(), $session_token );


			endif; // end if remember me is set	

		endif; // endif user
	}





	/**
	 * precheck_remember_me
	 * 
	 * Pre-check the Remember me checkbox on login forms
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function precheck_remember_me() {
			
		// Default remember me input names that can be filtered
		$remember_me_input_names = array('rememberme', 'remember', 'rcp_user_remember');
		
		/**
		 * Filter to allow adding custom remember me input names for pre-checking
		 *
		 * @param array $remember_me_input_names Array of input names to pre-check
		 * @since 1.0.0
		 */
		$remember_me_input_names = apply_filters( 'wppl_precheck_remember_me_names', $remember_me_input_names );

		// Start output buffering for cleaner JavaScript
		ob_start();
		?>
		<script id="wppl-precheck-remember-me">
		(function() {
			'use strict';
			
			var wppl_precheck_remember_me = function() {
				var rememberMeNames = <?php echo json_encode($remember_me_input_names); ?>;
				var processedElements = new Set(); // Track processed elements to avoid duplicates
				
				/**
				 * Check/enable a checkbox element
				 */
				function checkElement(element) {
					if (processedElements.has(element)) return;
					processedElements.add(element);
					
					if (element.type === 'checkbox' && !element.checked) {
						element.checked = true;
					}
				}
				
				/**
				 * Process standard remember me inputs
				 */
				function processRememberMeElements() {
					rememberMeNames.forEach(function(inputName) {
						// Find inputs by exact name match
						var inputs = document.querySelectorAll('input[name="' + inputName + '"]');
						inputs.forEach(function(input) {
							checkElement(input);
						});
						
						// Also find inputs where name contains the input name (partial match)
						var partialInputs = document.querySelectorAll('input[type="checkbox"]');
						partialInputs.forEach(function(input) {
							if (input.name && input.name.includes(inputName)) {
								checkElement(input);
							}
						});
					});
				}
				
				/**
				 * Handle WooCommerce specific elements
				 */
				function processWooCommerce() {
					var wooInputs = document.querySelectorAll('.woocommerce-form-login__rememberme input[type="checkbox"]');
					wooInputs.forEach(function(input) {
						checkElement(input);
					});
				}
				
				/**
				 * Handle Ultimate Member Plugin
				 */
				function processUltimateMember() {
					var umCheckboxLabels = document.querySelectorAll('.um-field-checkbox');
					
					umCheckboxLabels.forEach(function(label) {
						var input = label.querySelector('input');
						if (input && rememberMeNames.includes(input.name)) {
							// Set as active and checked
							checkElement(input);
							label.classList.add('active');
							
							// Update icon classes
							var icon = label.querySelector('.um-icon-android-checkbox-outline-blank');
							if (icon) {
								icon.classList.add('um-icon-android-checkbox-outline');
								icon.classList.remove('um-icon-android-checkbox-outline-blank');
							}
						}
					});
				}
				
				/**
				 * Handle ARMember Forms
				 */
				function processARMember() {
					var armContainers = document.querySelectorAll('.arm_form_input_container_rememberme');
					
					armContainers.forEach(function(container) {
						var checkboxes = container.querySelectorAll('md-checkbox');
						
						checkboxes.forEach(function(checkbox) {
							if (checkbox.classList.contains('ng-empty')) {
								checkbox.click(); // Activate the checkbox
							}
						});
					});
				}
				
				// Execute all processing functions
				processRememberMeElements();
				processWooCommerce();
				processUltimateMember();
				processARMember();
			};
			
			// Run when DOM is ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', wppl_precheck_remember_me);
			} else {
				wppl_precheck_remember_me();
			}
			
			// Also run after a short delay to catch dynamically loaded forms
			setTimeout(wppl_precheck_remember_me, 500);
			
		})();
		</script>
		<?php
		echo ob_get_clean();
	}

	
	/**
	 * logout
	 *
	 * deletes the user meta to re-login automatically when they visit
	 * 
	 * @return void
	 */
	public function logout() {
		delete_user_meta( get_current_user_id(), 'persistent_login_remember_me', 'true' );
	}
	
	
	
	/**
	 * woocommerce_remember_on_login
	 *
	 * @param  array $credentials
	 * @return array
	 */
	public function woocommerce_remember_on_login( $credentials ) {

		$credentials['remember'] = true;
		return $credentials;

	}


	/**
	 * wpweb_woocommerce_remember_on_login
	 *
	 * @param  int $user_id
	 * @param  string $type
	 * @return void
	 */
	public function wpweb_woocommerce_remember_on_login( $user_id, $type ) {

		// get the users latest session from the database
		$sessions = get_user_meta( $user_id, 'session_tokens', true );
		if( is_array($sessions) ) {

			// fetch the login time column from the array
			$login_times = array_column($sessions, 'login');

			// sort the sessions by login times (newest first)
			array_multisort( $login_times, SORT_DESC, $sessions );

			// get the key (verifier) of the first session
			$session_verifier = array_key_first($sessions);
		
			//remove the session from the database
			$wp_login_manage_sessions = new WP_Persistent_Login_Manage_Sessions( $user_id );
			$wp_login_manage_sessions->persistent_login_update_session( $session_verifier, null );

			// set a new cookie with remember me checked
			wp_set_auth_cookie( $user_id, true );

		}

	}


	/**
	 * does_cookie_need_updating
	 * 
	 * Checks to see if the cookies was set less than a day ago
	 * If it was, the cookie does not need to be updated.
	 *
	 * @param  array $cookieElements
	 * @return bool
	 * 
	 * @since 2.0.11
	 */
	protected function does_cookie_need_updating( $cookie_expiration = NULL ) {

		if( !$cookie_expiration ) {
			return true; // update the cookie if we don't know the expirtaion
		}

		$expiration_minus_one_day = time() + $this->expiration - DAY_IN_SECONDS;
		if( $cookie_expiration < $expiration_minus_one_day ) {
			return true; // update the cookie if it was set more than a day ago
		}

		// otherwise, don't update the cookie
		return false;

	}

	
	/**
	 * get_session_verifier_from_token
	 *
	 * @param  string $session_token
	 * @return string
	 */
	protected function get_session_verifier_from_token( $session_token ) {
						
		if ( function_exists( 'hash' ) ) :
			$verifier = hash( 'sha256', $session_token );
		else :
			$verifier = sha1( $session_token );
		endif;		

		return $verifier;

	}

	
	/**
	 * update_cookie_expiry
	 *
	 * @param  array $sessions
	 * @param  string $session_token
	 * @param  int $user_id
	 * @param  string $verifier
	 * @return void
	 */
	protected function update_cookie_expiry($sessions, $session_token, $user_id, $verifier) {

		// update the login time, expires time
		$sessions[$verifier]['login'] = time();
		$sessions[$verifier]['expiration'] = time()+$this->expiration;
		$sessions[$verifier]['ip'] = $_SERVER["REMOTE_ADDR"];
			
		// update the token with new data
		$wp_session_token = WP_Session_Tokens::get_instance( $user_id );
		$wp_session_token->update( $session_token, $sessions[$verifier] );

	}

	
	/**
	 * remove_duplicate_sessions
	 *
	 * @param  array $sessions
	 * @param  string $verifier
	 * @param  int $user_id
	 * @return void
	 */
	public static function remove_duplicate_sessions( $sessions, $verifier, $user_id ) {


		// if the verifier doesn't exist, stop processing
		if( !isset( $sessions[$verifier]['device_id'] )) {
			return;
		}

		foreach( $sessions as $key => $session ) {
			if( $key !== $verifier ) { // excludes the current session

				// check if the $session has a device ID
				if( isset($session['device_id']) ) {

					// if we're on the same device id, we're probably on the same device
					if( 
                        ($session['device_id'] === $sessions[$verifier]['device_id']) ) {

						// delete the duplicate session
						$updateSession = new WP_Persistent_Login_Manage_Sessions( $user_id );
						$updateSession->persistent_login_update_session( $key );
					
					}

				}
															
			}
		}

	}


}

?>