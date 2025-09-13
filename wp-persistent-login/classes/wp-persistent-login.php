<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Process terminated.' );

/**
 * Class WP_Persistent_login
 *
 * @since 2.0.0
 */
class WP_Persistent_Login {


	public $expiration;


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

		$this->expiration = YEAR_IN_SECONDS;

		// Check if persistent login feature is enabled
		$featureOptions = get_option('persistent_login_feature_flags', array());
		if( !isset($featureOptions['enablePersistentLogin']) || $featureOptions['enablePersistentLogin'] !== '1' ) {
			return; // stop processing if persistent login is not enabled
		}
		
		// set the expiration time when a user logs in
		add_filter( 'auth_cookie_expiration', array( $this, 'set_login_expiration' ), 10, 3 );

		// increase the cookie time when a user revisits
		add_action( 'set_current_user', array( $this, 'update_auth_cookie' ), 10, 0 );

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
	protected function remove_duplicate_sessions( $sessions, $verifier, $user_id ) {

		// if the verifier doesn't exist, stop processing
		if( 
			!isset( $sessions[$verifier]['ip'] )
			||
			!isset( $sessions[$verifier]['ua'] )
		 ) {
			return;
		}

		foreach( $sessions as $key => $session ) {
			if( $key !== $verifier ) { // excludes the current session

				// check if the $session has an IP and UA
				if( isset($session['ip']) && isset($session['ua']) ) {

					// if we're on the same user agent and same IP, we're probably on the same device
					if( 
                        ($session['ip'] === $sessions[$verifier]['ip']) 
						&&
                        ($session['ua'] === $sessions[$verifier]['ua'])
                    ) {

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