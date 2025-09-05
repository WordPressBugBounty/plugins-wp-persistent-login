<?php


// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Well, get lost.' );


/**
 * Class WP_Persistent_Login_Profile
 *
 * @since 2.0.0
 */
class WP_Persistent_Login_Profile {

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

        // output the user sessions table
        add_action( 'show_user_profile', array($this, 'output_user_sessions') );

        // update the user sessions
        add_action( 'personal_options_update', array($this, 'save_user_sessions') );
        add_action( 'edit_user_profile_update', array($this, 'save_user_sessions') );
	
    }

        
    /**
     * get_session_verifier
     *
     * @return string
     */
    private function get_session_verifier() {

        $sessionToken = wp_get_session_token();
        
        if ( function_exists( 'hash' ) ) {
            $verifier = hash('sha256', $sessionToken);
        } else {
            $verifier = sha1( $sessionToken);
        }

        return $verifier;
    
    }

    
    /**
     * get_user_sessions
     *
     * @return array|bool
     */
    private function get_user_sessions($user, $sort_order = SORT_DESC) {

        $sessions = get_user_meta($user->ID, 'session_tokens', true);

        if( is_array($sessions) ) {

            // fetch the login time column from the array
            $login_times = array_column($sessions, 'login');

            // sort the sessions by login times (newest first)
            array_multisort( $login_times, $sort_order, $sessions );

            return $sessions;

        } else {

            return false;
        
        }

    }

    
    /**
     * get_session_data
     * 
     * Takes a users sessions and outputs the required data for the session management table.
     *
     * @param  array $sessions
     * @return array
     */
    public function get_session_data($user) {

        $sessions = $this->get_user_sessions($user);
        
        if( $sessions !== false ) {

            $data = array();

            foreach( $sessions as $key => $session ) {

                if( isset($session['ua']) ) {
                    $device = $this->get_user_device($session['ua']);
                } else {
                    $device = __('Unknown Device', 'wp-persistent-login' );
                }
                $ip_address = $session['ip'];
                $login_time = $this->get_human_readable_login_duration($session['login']);
                $session_key = $key;

                if ( $session_key === $this->get_session_verifier() )  :
                    $current_device = true;
                else :
                    $current_device = false;
                endif;

                $data[] = array(
                    'device' => $device,
                    'ip' => $ip_address,
                    'login_time' => $login_time,
                    'session_key' => $session_key,
                    'current_device' => $current_device
                );

            }

            return $data;

        } else {

            return false;

        }
        

    }

    
    /**
     * get_human_readable_login_duration
     * 
     * Gets the amount of time since a session was last active.
     *
     * @param  int $seconds_ago
     * @return string
     */
    private function get_human_readable_login_duration($login_time) {

        $seconds_ago = (time() - $login_time);

        $time_breaks = array(
            31536000 => __(' years ago', 'wp-persistent-login' ),
            2419200 => __(' months ago', 'wp-persistent-login' ),
            86400 => __(' days ago', 'wp-persistent-login' ),
            3600 => __(' hours ago', 'wp-persistent-login' ),
            60 => __(' mins ago', 'wp-persistent-login' ),
            0 => __('Active now', 'wp-persistent-login' )
        );

        foreach( $time_breaks as $key => $value ) {

            if( $key === 0 ) {

                return $value;
            
            } elseif( $seconds_ago >= $key ) {
            
                $login_duration = intval( $seconds_ago / $key ) . $value;
                return $login_duration;
            
            }
        
        }

    }
    


    /**
     * get_user_device
     * 
     * Takes the user agent and returns the device type and a description of the device.
     *
     * @param  string $user_agent
     * @return string
     */
    private function get_user_device($user_agent) {

        $device = new WhichBrowser\Parser($user_agent);
        $device_type = ucwords( str_replace( ':', ' ', $device->getType() ) );
        $device_name = $device->toString();

        return $device_type .' - '. $device_name;

    }


    
    /**
     * output_user_sessions
     *
     * @param  object $user
     * @return void
     */
    public function output_user_sessions($user) {

        $sessions = $this->get_session_data($user);
				
        ?>

            <h2 id="sessions" style="margin: 2rem 0 0;">
                <?php _e('Active Logins - Persistent Login', 'wp-persistent-login' ); ?>
            </h2>

            <p class="description">
                <?php _e('Select the active logins you want to end, and click update profile', 'wp-persistent-login' ); ?>
            </p>

            <?php if( $sessions ) : ?>

                <div class="wppl-table-container">
                    <table class="wppl-table wppl-manage-sessions wp-list-table widefat fixed striped">

                        <thead>
                            <tr>
                                <th>
                                    <?php _e('Session Details', 'wp-persistent-login' ); ?>
                                </th>
                                <th>
                                    <?php _e('Last Active', 'wp-persistent-login' ); ?>
                                </th>
                                <th>
                                    <?php _e('Manage', 'wp-persistent-login' ); ?>
                                </th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach( $sessions as $session ) : ?>

                                <tr>

                                    <td data-label="<?php echo esc_attr(__('Session Details', 'wp-persistent-login')); ?>">
                                        <?php echo $session['device']; ?><br/>
                                        <small class="wppl-table-meta">
                                            <?php 
                                                _e('IP Address: ', 'wp-persistent-login' );
                                                echo $session['ip']; 
                                                
                                                if( isset($session['location']) ) {
                                                    echo '<br/>';
                                                    _e('Approximate location: ', 'wp-persistent-login' );
                                                    echo $session['location'];
                                                }
                                            ?> 
                                        </small>
                                    </td>
                                
                                    <td data-label="<?php echo esc_attr(__('Last Active', 'wp-persistent-login')); ?>">
                                        <?php echo $session['login_time']; ?>
                                        <?php if( $session['current_device'] === true ) : ?>
                                            <br/>
                                            <small class="wppl-current-device">
                                                <strong>
                                                    <?php _e('(this device)', 'wp-persistent-login' ); ?>
                                                </strong>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                
                                    <td data-label="<?php echo esc_attr(__('Manage', 'wp-persistent-login')); ?>">
                                        <label 
                                            title="<?php _e('End Session', 'wp-persistent-login' ); ?>" 
                                            class="button wppl-end-session-btn"
                                        >
                                            <input 
                                                type="checkbox" 
                                                name="endSessions[]" 
                                                value="<?php echo $session['session_key']; ?>" 
                                                title="<?php _e('End Session', 'wp-persistent-login' ); ?>" 
                                            />
                                            <?php _e('End Session', 'wp-persistent-login' ); ?>
                                        </label>
                                    </td>
                                
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>
                </div>

            <?php else : ?>

                <p><?php _e('You don\'t have any active logins at the moment.', 'wp-persistent-login' ); ?></p>

            <?php endif; ?>

        <?php
            
    }


    
        
    /**
     * save_user_sessions
     *
     * @param  int $user_id
     * @return void
     */
    public function save_user_sessions( $user_id ) {
    
        if ( !current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }
    
        // remove session if requested
        if( isset($_POST['endSessions']) ) {

            // setup vars
            $tokens = $_POST['endSessions'];
            
            foreach( $tokens as $token ) {
                // remove that session
                $updateSession = new WP_Persistent_Login_Manage_Sessions($user_id);
                $updateSession->persistent_login_update_session($token);	
            }
            
        }
        
    }

}