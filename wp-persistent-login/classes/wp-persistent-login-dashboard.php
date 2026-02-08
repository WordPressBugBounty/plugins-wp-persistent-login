<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Well, get lost.' );


/**
 * Class WP_Persistent_Login_Settings
 *
 * @since 2.2.0
 */
class WP_Persistent_Login_Dashboard {

    /**
     * Initialize the class and set its properties.
     *
     * @since  2.3.0
     * @access public
     *
     * @return void
     */
    public function __construct() {
        // Add AJAX hook for stopping user count
        add_action( 'wp_ajax_wppl_stop_user_count', array( $this, 'ajax_stop_user_count' ) );
    }

    /**
     * Renders the common page header used across all plugin pages
     * 
     * @since 2.3.0
     * @param string $title Optional page title to display in the breadcrumb
     * @return void
     */
    private function render_page_header($title = '') {
        ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
        <link href="<?php echo WPPL_PLUGIN_URL . 'css/dashboard.css'; ?>" rel="stylesheet">
        <?php 
        wp_enqueue_style('dashicons');
        wp_enqueue_script('jquery'); // Ensure jQuery is loaded
        ?>
        
        <script type="text/javascript">
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        </script>

        <div class="wppl-container">
            <div class="header">
                <div>
                    <h1><?php _e( 'Persistent Login', 'wp-persistent-login' ); ?></h1>
                    <p class="text-black">
                        <?php _e('Keeping users logged into WordPress since 2014', 'wp-persistent-login'); ?>
                    </p>
                </div>
                <div>
                    <a href="<?php echo WPPL_ACCOUNT_PAGE; ?>" class="button">
                        <?php _e('My account', 'wp-persistent-login' ); ?>
                    </a>
                    <a href="<?php echo WPPL_UPGRADE_PAGE; ?>" class="button">
                        <?php _e('Manage my plan', 'wp-persistent-login' ); ?>
                    </a>
                    <a href="<?php echo WPPL_SUPPORT_PAGE; ?>" class="button" target="_blank">
                        <?php _e('Support', 'wp-persistent-login' ); ?>
                    </a>
                </div>
            </div>


            <div class="wppl-wrap">

                 <?php if( isset($_GET['wppl-msg']) ) : ?>
                    <div class="notice notice-success is-dismissible" style="margin-bottom: 2rem;">
                        <p><strong><?php echo esc_html(urldecode($_GET['wppl-msg'])); ?></strong></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($title)) : ?>
                    <div class="breadcrumb">
                        <a href="<?php echo admin_url('users.php?page=wp-persistent-login'); ?>" class="breadcrumb-link">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php _e('Dashboard', 'wp-persistent-login'); ?>
                        </a>
                        <span class="breadcrumb-separator">›</span>
                        <span class="breadcrumb-current"><?php echo $title; ?></span>
                    </div>
                <?php endif; ?>

        <?php    
    }    
    
    public function display_dashboard() {
        $this->render_page_header();
        ?>
            <h2><?php _e('Dashboard', 'wp-persistent-login'); ?></h2>
            <p><?php _e( 'Persistent login will keep all users logged in automatically. For free. Forever.', 'wp-persistent-login' ); ?></p>
            <?php 
                $support_page_url = WPPL_SUPPORT_PAGE;
                $review_page_url = WPPL_REVIEW_PAGE;
            ?>
            <p>
                <?php
                    printf(
                        /* translators: %s: Support page URL */
                        __( 'If you have any questions or need help, please visit our support page:', 'wp-persistent-login' ) . ' <a href="%s" target="_blank">' . __( 'Support', 'wp-persistent-login' ) . '</a>',
                        esc_url( $support_page_url )
                    );
                ?>
                <br/>
                <?php
                    printf(
                        /* translators: %s: Review page URL */
                        __( 'If you like this plugin, please consider leaving a review:', 'wp-persistent-login' ) . ' <a href="%s" target="_blank">' . __( 'Leave a review', 'wp-persistent-login' ) . '</a>',
                        esc_url( $review_page_url )
                    );
                ?>
            </p>   

            <?php
                // check if WP_CRON is enabled and running
                if( defined('DISABLE_WP_CRON') ) {
                    if( DISABLE_WP_CRON == true ) {
                        echo sprintf(
                            '<p class="wppl-cron-check--warning">%s</p>', 
                            __('Notice: WP Cron is disabled. The user count below will not work without it. Persistent Login will still function normally. Please enable WP Cron to view logged in user metrics.', 'wp-persistent-login' )
                        );
                    }
                }

                // Check user count cron job status
                $count = new WP_Persistent_Login_User_Count();
                $cron_status = $count->get_cron_status();
                
                // Check if main cron job is missing or overdue
                if ( !$cron_status['main_cron_scheduled'] || $count->is_main_cron_overdue() ) {
                    // Attempt to fix the cron schedule
                    $fixed = $count->fix_cron_schedule();
                    
                    if ( $fixed ) {
                        // Also start the count immediately instead of waiting for the next scheduled time
                        $count_started = $count->force_start_count();
                        
                        if ( $count_started ) {
                            echo sprintf(
                                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                                __('Notice: The user count cron job was missing or overdue and has been automatically rescheduled. The user count is now starting immediately and will be updated shortly.', 'wp-persistent-login')
                            );
                        } else {
                            echo sprintf(
                                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                                __('Notice: The user count cron job was missing or overdue and has been automatically rescheduled. The user count should start running again within 12 hours.', 'wp-persistent-login')
                            );
                        }
                    } else {
                        echo sprintf(
                            '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                            __('Warning: The user count cron job appears to be missing and could not be automatically rescheduled. User count statistics may not update. Please contact support if this persists.', 'wp-persistent-login')
                        );
                    }
                }
                
                // Check if count is running but update cron is missing
                if ( $cron_status['is_count_running'] && !$cron_status['update_cron_scheduled'] ) {
                    echo sprintf(
                        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                        __('Notice: User count is running but the update process appears to be stuck. The count may restart automatically at the next scheduled time.', 'wp-persistent-login')
                    );
                }
            ?>  

            <div class="dashboard-main-content">
                <div class="wppl-box-outline bg-light-green usage-breakdown">
                    <div class="usage-breakdown-header">
                        <h3><?php _e('Usage Breakdown', 'wp-persistent-login'); ?></h3>
                        <?php if( $count->is_user_count_running() ) : ?>
                            <div class="stop-count-container">
                                <button id="wppl-stop-count-btn" class="button button-secondary" type="button">
                                    <span class="dashicons dashicons-no"></span>
                                    <?php _e('Stop Count', 'wp-persistent-login'); ?>
                                </button>
                                <span id="stop-count-spinner" class="spinner" style="display: none; visibility: visible; float: none; margin-left: 10px;"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="stop-count-messages" style="display: none; margin-top: 10px;"></div>
                    
                    <?php
                        // Use the existing $count variable from the cron status check above
                        if( $count->is_user_count_running() ) {
                            echo sprintf('<p class="wppl-user-count-status">%s</p>', $count->output_current_counting_role());
                        } else {
                            $logged_in_user_count = $count->output_loggedin_user_count();
                            $next_count_in = $count->get_next_count_time_difference();
                            echo sprintf('<p class="wppl-user-count-status">%s <br/>The next logged in user count will start in approximately %s hours.</p>', $logged_in_user_count, $next_count_in);
                        }

                    ?>

                    <h4><?php _e('User Roles', 'wp-persistent-login'); ?></h4>
                    <div class="user-roles-breakdown">
                        <?php echo $count->output_user_count_breakdown(); ?>
                    </div> 
                    
                    <div class="login-types-legend">
                        <h5><?php _e('Login Types', 'wp-persistent-login'); ?></h5>
                        <div class="legend-items">
                            <div class="legend-item persistent">
                                <span class="legend-icon">🔒</span>
                                <span class="legend-text">
                                    <?php _e('Persistent Login:', 'wp-persistent-login'); ?><br/>
                                    <?php _e('Users stay logged in automatically', 'wp-persistent-login'); ?>
                            </span>
                            </div>
                            <div class="legend-item normal">
                                <span class="legend-icon">⏱️</span>
                                <span class="legend-text">
                                    <?php _e('Standard Login:', 'wp-persistent-login'); ?><br/>
                                    <?php _e('Users must log in again after 14 days', 'wp-persistent-login'); ?>
                                </span>
                            </div>
                        </div>
                        <?php if( WPPL_PR === false ) : ?>
                            <p class="legend-note"><?php _e('Note: All user roles have persistent login in the free version. Upgrade to premium to configure which roles have persistent login.', 'wp-persistent-login'); ?></p>
                        <?php endif; ?>
                    </div> 
                                        
                </div>
                
                <?php if( WPPL_PR === false ) : ?>
                    <div class="wppl-box-outline premium-advert">
                        <h3><?php _e('Try premium for 7 days, free', 'wp-persistent-login'); ?></h3>
                        <p><strong><?php _e('Persistent Login is great, but we\'ve made it even better!', 'wp-persistent-login'); ?></strong></p>
                        
                        <p><?php _e('If you love Persistent Login, but want more control, have a look at the features in our premium version.', 'wp-persistent-login'); ?></p>
                        
                        <div class="action-buttons">
                            <a href="<?php echo esc_url(WPPL_TRIAL_UPGRADE_PAGE); ?>" class="button try-free-button">
                                <span class="dashicons dashicons-backup"></span>
                                <?php _e('7 Day Free Trial', 'wp-persistent-login'); ?>
                            </a>
                            
                            <span class="button-separator">or</span>
                            
                            <a href="<?php echo esc_url(WPPL_UPGRADE_PAGE); ?>" class="button button-primary upgrade-button">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php _e('Purchase Premium', 'wp-persistent-login'); ?>
                            </a>
                        </div>
                    </div>
                    <p style="margin-top: 0.5rem;">
                        <?php 
                            echo sprintf( 
                                __('Control which user roles are kept logged in, run login counts on demand and access advanced Active Login and Login History settings by <a href="%s">upgrading to premium</a>.', 'wp-persistent-login'),
                                esc_url( WPPL_UPGRADE_PAGE )
                            ); 
                        ?>
                    </p>
                <?php endif; ?>
            </div>

                <h2><?php _e('Features', 'wp-persistent-login'); ?></h2>
                <p><?php _e('Quickly enable or disable persistent login features using the settings below.', 'wp-persistent-login'); ?></p>
                
                <div class="features-grid">
                    <?php
                    // Get plugin options - should be initialized by database upgrade
                    $options = get_option('persistent_login_feature_flags', array());
                    
                    // These should be set by the database upgrade, but provide safe defaults
                    $persistent_login_enabled = isset($options['enablePersistentLogin']) ? $options['enablePersistentLogin'] : '1';
                    $active_logins_enabled = isset($options['enableActiveLogins']) ? $options['enableActiveLogins'] : '0';
                    $login_history_enabled = isset($options['enableLoginHistory']) ? $options['enableLoginHistory'] : '0';
                    
                    // Persistent Login Feature Box
                    ?>                    
                    <div class="feature-box<?php echo $persistent_login_enabled ? '' : ' feature-disabled'; ?>">
                        <h4><?php _e('Persistent Login', 'wp-persistent-login'); ?></h4>
                        <p><?php _e('Keep users logged in automatically. The core functionality of this plugin.', 'wp-persistent-login'); ?></p>
                        <div class="feature-controls">
                            <div class="toggle-switch-container">
                                <span id="persistent-login-label" class="screen-reader-text">
                                    <?php echo $persistent_login_enabled ? 
                                        __('Persistent Login is enabled. Click to disable.', 'wp-persistent-login') : 
                                        __('Persistent Login is disabled. Click to enable.', 'wp-persistent-login'); ?>
                                </span>
                                <label class="toggle-switch" for="persistent-login-toggle">
                                    <input type="checkbox" 
                                        id="persistent-login-toggle"
                                        class="feature-toggle" 
                                        data-feature="persistent_login" 
                                        data-option-name="enablePersistentLogin" 
                                        aria-labelledby="persistent-login-label"
                                        <?php checked($persistent_login_enabled, 1); ?>>
                                    <span class="slider" aria-hidden="true"></span>
                                    <span class="toggle-text toggle-text-on"><?php _e('On', 'wp-persistent-login'); ?></span>
                                    <span class="toggle-text toggle-text-off"><?php _e('Off', 'wp-persistent-login'); ?></span>
                                </label>                            </div>                            <a href="<?php echo admin_url('users.php?page=wp-persistent-login&tab=persistent-login'); ?>" 
                                class="settings-btn"
                                aria-label="<?php _e('Persistent Login Settings', 'wp-persistent-login'); ?>">
                                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                                <?php _e('Settings', 'wp-persistent-login'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <?php // Active Logins Feature Box ?>                    
                    <div class="feature-box<?php echo $active_logins_enabled ? '' : ' feature-disabled'; ?>">
                        <h4><?php _e('Active Logins', 'wp-persistent-login'); ?></h4>
                        <p><?php _e('Control the number of active logins users are allowed at one time.', 'wp-persistent-login'); ?></p>
                        <div class="feature-controls">
                            <div class="toggle-switch-container">
                                <span id="active-logins-label" class="screen-reader-text">
                                    <?php echo $active_logins_enabled ? 
                                        __('Active Logins is enabled. Click to disable.', 'wp-persistent-login') : 
                                        __('Active Logins is disabled. Click to enable.', 'wp-persistent-login'); ?>
                                </span>
                                <label class="toggle-switch" for="active-logins-toggle">
                                    <input type="checkbox" 
                                        id="active-logins-toggle"
                                        class="feature-toggle" 
                                        data-feature="active_logins" 
                                        data-option-name="enableActiveLogins" 
                                        aria-labelledby="active-logins-label"
                                        <?php checked($active_logins_enabled, 1); ?>>
                                    <span class="slider" aria-hidden="true"></span>
                                    <span class="toggle-text toggle-text-on"><?php _e('On', 'wp-persistent-login'); ?></span>
                                    <span class="toggle-text toggle-text-off"><?php _e('Off', 'wp-persistent-login'); ?></span>
                                </label>
                            </div>                            <a href="<?php echo admin_url('users.php?page=wp-persistent-login&tab=active_logins'); ?>" 
                                class="settings-btn"
                                aria-label="<?php _e('Active Logins Settings', 'wp-persistent-login'); ?>">
                                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                                <?php _e('Settings', 'wp-persistent-login'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <?php // Login History Feature Box ?>                    <div class="feature-box<?php echo $login_history_enabled ? '' : ' feature-disabled'; ?>">
                        <h4><?php _e('Login History', 'wp-persistent-login'); ?></h4>
                        <p><?php _e('Track login activity and notify users of new devices using their account.', 'wp-persistent-login'); ?></p>
                        <div class="feature-controls">
                            <div class="toggle-switch-container">
                                <span id="login-history-label" class="screen-reader-text">
                                    <?php echo $login_history_enabled ? 
                                        __('Login History is enabled. Click to disable.', 'wp-persistent-login') : 
                                        __('Login History is disabled. Click to enable.', 'wp-persistent-login'); ?>
                                </span>
                                <label class="toggle-switch" for="login-history-toggle">
                                    <input type="checkbox" 
                                        id="login-history-toggle"
                                        class="feature-toggle" 
                                        data-feature="login_history" 
                                        data-option-name="enableLoginHistory" 
                                        aria-labelledby="login-history-label"
                                        <?php checked($login_history_enabled, 1); ?>>
                                    <span class="slider" aria-hidden="true"></span>
                                    <span class="toggle-text toggle-text-on"><?php _e('On', 'wp-persistent-login'); ?></span>
                                    <span class="toggle-text toggle-text-off"><?php _e('Off', 'wp-persistent-login'); ?></span>
                                </label>
                            </div>                            
                            
                            <a href="<?php echo admin_url('users.php?page=wp-persistent-login&tab=login_history'); ?>" 
                                class="settings-btn"
                                aria-label="<?php _e('Login History Settings', 'wp-persistent-login'); ?>">
                                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                                <?php _e('Settings', 'wp-persistent-login'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                

            </div>
        </div>
        <?php
    }

    /**
     * Display Persistent Login settings page
     * 
     * @since 2.2.0
     * @return void
     */
    public function display_persistent_login_settings() {
        // Get plugin options
        $settings = new WP_Persistent_Login_Settings();
        $options = get_option('persistent_login_options', array());
        $duplicate_sessions = isset($options['duplicateSessions']) ? $options['duplicateSessions'] : '0';
        $dashboard_stats = $settings->get_dashboard_stats();

        $persistent_login_features = get_option('persistent_login_feature_flags', array());
        
        $this->render_page_header( __( 'Persistent Login Settings', 'wp-persistent-login' ) );
        ?>

                <h2><?php _e('Persistent Login Settings', 'wp-persistent-login'); ?></h2>
                <p><?php _e('Configure how the persistent login functionality works across your site.', 'wp-persistent-login'); ?></p>

                <!-- Warning to use if Persistent Login is not enabled -->
                <?php if( !isset($persistent_login_features['enablePersistentLogin']) || $persistent_login_features['enablePersistentLogin'] !== '1' ) : ?>
                    <div class="notice notice-warning is-dismissible" style="margin-bottom: 2rem;">
                        <p><?php _e('Persistent Login is currently disabled. Please enable it on the Dashboard for the settings below to take effect.', 'wp-persistent-login'); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo admin_url('users.php?page=wp-persistent-login&tab=persistent-login'); ?>" class="settings-grid">
                    <input type="hidden" name="wppl_method" value="update_general_settings" />
                    <?php wp_nonce_field('update_general_settings_action', 'update_general_settings_nonce'); ?>
                    <?php wp_referer_field(); ?>
                    
                    <div class="wppl-box-outline">
                        <h3><?php _e('General Settings', 'wp-persistent-login'); ?></h3>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="duplicate-sessions"><?php _e('Allow Duplicate Sessions', 'wp-persistent-login'); ?></label>
                                <p class="setting-description"><?php _e('When enabled, users can have multiple persistent logins across different devices.', 'wp-persistent-login'); ?></p>
                            </div>
                            <div class="setting-control">
                                <div class="toggle-switch-container">
                                    <span id="duplicate-sessions-label" class="screen-reader-text">
                                        <?php echo $duplicate_sessions == '1' ? 
                                            __('Duplicate Sessions is enabled. Click to disable.', 'wp-persistent-login') : 
                                            __('Duplicate Sessions is disabled. Click to enable.', 'wp-persistent-login'); ?>
                                    </span>
                                    <label class="toggle-switch" for="duplicate-sessions-toggle" title="<?php echo $duplicate_sessions == '1' ? 
                                            __('Duplicate Sessions is enabled. Click to disable.', 'wp-persistent-login') : 
                                            __('Duplicate Sessions is disabled. Click to enable.', 'wp-persistent-login'); ?>">
                                        <input type="checkbox" 
                                            id="duplicate-sessions-toggle"
                                            name="duplicateSessions"
                                            value="1"
                                            aria-labelledby="duplicate-sessions-label"
                                            <?php checked($duplicate_sessions, '1'); ?>>
                                        <span class="slider" aria-hidden="true"></span>
                                        <span class="toggle-text toggle-text-on"><?php _e('On', 'wp-persistent-login'); ?></span>
                                        <span class="toggle-text toggle-text-off"><?php _e('Off', 'wp-persistent-login'); ?></span>
                                    </label>

                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="hide-dashboard-stats"><?php _e('Hide Dashboard Stats', 'wp-persistent-login'); ?></label>
                                <p class="setting-description"><?php _e('When enabled, user count statistics will be hidden from the WordPress dashboard.', 'wp-persistent-login'); ?></p>
                            </div>
                            <div class="setting-control">
                                <div class="toggle-switch-container">
                                    <span id="hide-dashboard-stats-label" class="screen-reader-text">
                                        <?php echo $dashboard_stats == '1' ? 
                                            __('Dashboard Stats are hidden. Click to show.', 'wp-persistent-login') : 
                                            __('Dashboard Stats are visible. Click to hide.', 'wp-persistent-login'); ?>
                                    </span>
                                    <label class="toggle-switch" for="hide-dashboard-stats-toggle" title="<?php echo $dashboard_stats == '1' ? 
                                            __('Dashboard Stats are hidden. Click to show.', 'wp-persistent-login') : 
                                            __('Dashboard Stats are visible. Click to hide.', 'wp-persistent-login'); ?>">
                                        <input type="checkbox" 
                                            id="hide-dashboard-stats-toggle"
                                            name="hidedashboardstats"
                                            value="1"
                                            aria-labelledby="hide-dashboard-stats-label"
                                            <?php checked($dashboard_stats, '1'); ?>>
                                        <span class="slider" aria-hidden="true"></span>
                                        <span class="toggle-text toggle-text-on"><?php _e('On', 'wp-persistent-login'); ?></span>
                                        <span class="toggle-text toggle-text-off"><?php _e('Off', 'wp-persistent-login'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                      <div class="wppl-box-outline bg-light-green">
                        <h3><?php _e('Session Management', 'wp-persistent-login'); ?></h3>
                        
                        <div class="session-management-controls">
                            <p><?php _e('Need to log everyone out? Use this button to end all active sessions across your site.', 'wp-persistent-login'); ?></p>
                            
                            <div class="warning-box">
                                <div class="warning-icon">
                                    <span class="dashicons dashicons-warning"></span>
                                </div>
                                <div class="warning-content">
                                    <p><strong><?php _e('Warning:', 'wp-persistent-login'); ?></strong> <?php _e('This will log out ALL users, including yourself. You will need to log back in afterward.', 'wp-persistent-login'); ?></p>
                                </div>
                            </div>
                            
                            <button type="button" id="end-all-sessions-btn" class="button button-secondary">
                                <span class="dashicons dashicons-exit"></span>
                                <?php _e('End All Sessions', 'wp-persistent-login'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <?php if (WPPL_PR === false) : ?>
                        <div class="wppl-box-outline premium-feature">
                            
                            <h3><?php _e('Cookie Settings', 'wp-persistent-login'); ?></h3>
                            
                            <div class="premium-feature-content">
                                <p><?php _e('Upgrade to Premium to customize how persistent login cookies work:', 'wp-persistent-login'); ?></p>
                                <ul class="premium-feature-list">
                                    <li><?php _e('Customise the cookie expiration time, up to 400 days', 'wp-persistent-login'); ?></li>
                                    <li><?php _e('Control which user roles get persistent login and which don\'t', 'wp-persistent-login'); ?></li>
                                </ul>
                                
                                <a href="<?php echo WPPL_UPGRADE_PAGE; ?>" class="button button-primary upgrade-button">
                                    <span class="dashicons dashicons-unlock"></span>
                                    <?php _e('Upgrade to Premium', 'wp-persistent-login'); ?>
                                </a>
                            </div>
                            <div class="premium-badge">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php _e('Premium', 'wp-persistent-login'); ?>
                            </div>
                        </div>                   
                    <?php endif; ?>
                    
                    <div class="form-footer">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Settings', 'wp-persistent-login'); ?>
                        </button>
                    </div>
                </form>

                <!-- Hidden form for the end sessions action -->
                <div id="end-sessions-form-container">
                    <form id="end-sessions-form" method="POST" action="<?php echo admin_url('users.php?page=wp-persistent-login&tab=persistent-login'); ?>">
                        <?php wp_nonce_field('end_sessions_action', 'end_sessions_nonce'); ?>
                        <input type="hidden" name="wppl_method" value="end_sessions" />
                        <input type="hidden" name="value" value="true" />
                        <?php wp_referer_field(); ?>
                    </form>
                </div>
                
                <script>
                    jQuery(document).ready(function($) {
                        // Handle End All Sessions button click
                        $('#end-all-sessions-btn').on('click', function(e) {
                            e.preventDefault();
                            if (confirm('<?php _e("Are you sure you want to end all sessions? This will log out ALL users including yourself.", "wp-persistent-login"); ?>')) {
                                $('#end-sessions-form').submit();
                            }
                        });
                    });
                </script>
            </div>
        </div>
        <style>
                    #end-sessions-form-container {
                        position: absolute;
                        left: -9999px;
                        height: 1px;
                        width: 1px;
                        overflow: hidden;
                    }
                </style>
        <?php
    }
    
    /**
     * Display Active Logins settings page
     * 
     * @since 2.2.0
     * @return void
     */
    public function display_active_logins_settings() {
        $settings = new WP_Persistent_Login_Settings();
        $limit_active_logins = $settings->get_limit_active_logins();
        $limit_reached_logic = $settings->get_limit_reached_logic();
        $active_logins_features = get_option('persistent_login_feature_flags', array());
        
        $this->render_page_header( __( 'Active Login Settings', 'wp-persistent-login' ) );
        ?>

                <?php if( !isset($active_logins_features['enableActiveLogins']) || $active_logins_features['enableActiveLogins'] !== '1' ) : ?>
                    <div class="notice notice-warning is-dismissible" style="margin-bottom: 2rem;">
                        <p><?php _e('Active Logins is currently disabled. Please enable it on the Dashboard for the settings below to take effect.', 'wp-persistent-login'); ?></p>
                    </div>
                <?php endif; ?>
                                
                <form method="POST" action="<?php echo admin_url('users.php?page=wp-persistent-login&tab=active_logins'); ?>" class="settings-grid">
                    <input type="hidden" name="wppl_method" value="update_active_login_settings" />
                    <?php wp_nonce_field('update_active_login_settings_action', 'update_active_login_settings_nonce'); ?>
                    <?php wp_referer_field(); ?>
                    
                    <div class="wppl-box-outline">
                        <h3><?php _e('Login Limits', 'wp-persistent-login'); ?></h3>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="limit-active-logins"><?php _e('Limit users to 1 active login', 'wp-persistent-login'); ?></label>
                                <p class="setting-description"><?php _e('When enabled, users will be limited to 1 active login session at a time.', 'wp-persistent-login'); ?></p>
                            </div>
                            <div class="setting-control">
                                <div class="toggle-switch-container">
                                    <span id="limit-active-logins-label" class="screen-reader-text">
                                        <?php echo $limit_active_logins == '1' ? 
                                            __('Limit Active Logins is enabled. Click to disable.', 'wp-persistent-login') : 
                                            __('Limit Active Logins is disabled. Click to enable.', 'wp-persistent-login'); ?>
                                    </span>
                                    <label class="toggle-switch" for="limit-active-logins-toggle" title="<?php echo $limit_active_logins == '1' ? 
                                            __('Limit Active Logins is enabled. Click to disable.', 'wp-persistent-login') : 
                                            __('Limit Active Logins is disabled. Click to enable.', 'wp-persistent-login'); ?>">
                                        <input type="checkbox" 
                                            id="limit-active-logins-toggle"
                                            name="limitActiveLogins"
                                            value="1"
                                            aria-labelledby="limit-active-logins-label"
                                            <?php checked($limit_active_logins, '1'); ?>>
                                        <span class="slider" aria-hidden="true"></span>
                                        <span class="toggle-text toggle-text-on"><?php _e('On', 'wp-persistent-login'); ?></span>
                                        <span class="toggle-text toggle-text-off"><?php _e('Off', 'wp-persistent-login'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wppl-box-outline bg-light-green">
                        <h3><?php _e('When Login Limit is Reached', 'wp-persistent-login'); ?></h3>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label><?php _e('Login Limit Action', 'wp-persistent-login'); ?></label>
                                <p class="setting-description"><?php _e('Choose what happens when a user tries to log in and already has an active session.', 'wp-persistent-login'); ?></p>
                            </div>
                            <div class="setting-control setting-control--stack">
                                <p>
                                    <label>
                                        <input type="radio" name="activeLoginLogic" value="automatic" <?php checked($limit_reached_logic, 'automatic'); ?>>
                                        <?php _e('Automatically end the oldest active login for the user.', 'wp-persistent-login'); ?>
                                    </label>
                                </p>
                                <p>
                                    <label>
                                        <input type="radio" name="activeLoginLogic" value="block" <?php checked($limit_reached_logic, 'block'); ?>>
                                        <?php _e('Block new logins if active login limit reached.', 'wp-persistent-login'); ?>
                                    </label>
                                </p>
                            </div>
                        </div>
                    </div>                    
                    <div class="wppl-box-outline bg-light-green">
                        <h3><?php _e('Manage Active Logins', 'wp-persistent-login'); ?></h3>
                        
                        <div class="session-management-controls">
                            <p><?php _e('You can manage your own active logins from your profile page in the dashboard.', 'wp-persistent-login'); ?></p>
                            
                            <div class="action-buttons">
                                <a href="<?php echo admin_url('profile.php'); ?>" class="button button-secondary manage-button">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php _e('Manage your active logins', 'wp-persistent-login'); ?>
                                </a>
                                
                                <?php if (WPPL_PR === false) : ?>
                                <span class="button-separator">or</span>
                                <a href="<?php echo WPPL_UPGRADE_PAGE; ?>" class="button upgrade-button">
                                    <span class="dashicons dashicons-unlock"></span>
                                    <?php _e('Upgrade', 'wp-persistent-login'); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (WPPL_PR === false) : ?>
                            <p class="upgrade-note">
                                <?php _e('To manage all active logins & allow users to manage their own active logins from the front-end, please consider upgrading.', 'wp-persistent-login'); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (defined('WPPL_PR') && WPPL_PR === false) : ?>
                        <div class="wppl-box-outline premium-feature">
                            
                            <h3><?php _e('Advanced Active Login Control', 'wp-persistent-login'); ?></h3>
                            
                            <div class="premium-feature-content">
                                <p><?php _e('Upgrade to Premium for advanced active login controls:', 'wp-persistent-login'); ?></p>
                                <ul class="premium-feature-list">
                                    <li><?php _e('Select how many active logins users can have', 'wp-persistent-login'); ?></li>
                                    <li><?php _e('Allow users to select which login session to end when limit is reached', 'wp-persistent-login'); ?></li>
                                    <li><?php _e('Allow users to view their login history on the front-end', 'wp-persistent-login'); ?></li>
                                    <li><?php _e('Allow users to manage their own sessions on the front-end with blocks and shortcodes', 'wp-persistent-login'); ?></li>
                                </ul>
                                  <a href="<?php echo WPPL_UPGRADE_PAGE; ?>" class="button button-primary upgrade-button">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php _e('Upgrade to Premium', 'wp-persistent-login'); ?>
                                </a>
                            </div>
                            <span class="premium-badge"><?php _e('Premium', 'wp-persistent-login'); ?></span>
                        </div>                   
                    <?php endif; ?>
                    
                    <div class="form-footer">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Changes', 'wp-persistent-login'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display Login History settings page
     * 
     * @since 2.3.0
     * @return void
     */    
    public function display_login_history_settings() {
        $settings = new WP_Persistent_Login_Settings();
        $login_history_enabled = $settings->get_login_history();
        
        // Get options directly
        $options = get_option('persistent_login_options', array());
        $notify_new_logins = isset($options['notifyNewLogins']) ? $options['notifyNewLogins'] : '0';
        $display_user_login_history = $settings->get_display_user_login_history();
        
        $notification_email_subject = $settings->get_notification_email_subject();
        $notification_email_template = $settings->get_notification_email_template(false);

        $login_history_features = get_option('persistent_login_feature_flags', array());
        
        $this->render_page_header( __( 'Login History Settings', 'wp-persistent-login' ) );
        ?>

        <?php if( !isset($login_history_features['enableLoginHistory']) || $login_history_features['enableLoginHistory'] !== '1' ) : ?>
            <div class="notice notice-warning is-dismissible" style="margin-bottom: 2rem;">
                <p><?php _e('Login History is currently disabled. Please enable it on the Dashboard for the settings below to take effect.', 'wp-persistent-login'); ?></p>
            </div>
        <?php endif; ?>
                                
                <form method="POST" action="<?php echo admin_url('users.php?page=wp-persistent-login&tab=login_history'); ?>" class="settings-grid">
                    <input type="hidden" name="wppl_method" value="update_login_history_settings" />
                    <?php wp_nonce_field('update_login_history_settings_action', 'update_login_history_settings_nonce'); ?>
                    <?php wp_referer_field(); ?>
                    
                    <div class="wppl-box-outline">
                        <h3><?php _e('Login History Settings', 'wp-persistent-login'); ?></h3>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="enable-login-history"><?php _e('Enable Login History', 'wp-persistent-login'); ?></label>
                                <p class="setting-description"><?php _e('When enabled, the plugin will track login activity across different devices for each user.', 'wp-persistent-login'); ?></p>
                            </div>
                            <div class="setting-control">
                                <div class="toggle-switch-container">
                                    <span id="enable-login-history-label" class="screen-reader-text">
                                        <?php _e('Enable Login History', 'wp-persistent-login'); ?>
                                    </span>
                                    <label class="toggle-switch" for="enable-login-history-toggle" title="<?php echo $login_history_enabled == '1' ? __('Enabled', 'wp-persistent-login') : __('Disabled', 'wp-persistent-login'); ?>">
                                        <input type="checkbox" id="enable-login-history-toggle" name="enableLoginHistory" value="1" class="toggle-switch-input" <?php checked('1', $login_history_enabled); ?> />
                                        <span class="slider"></span>
                                        <span class="toggle-text toggle-text-on"><?php _e('ON', 'wp-persistent-login'); ?></span>
                                        <span class="toggle-text toggle-text-off"><?php _e('OFF', 'wp-persistent-login'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="notify-new-logins"><?php _e('Notify Users of New Logins', 'wp-persistent-login'); ?></label>
                                <p class="setting-description"><?php _e('When enabled, users will receive an email notification when their account is accessed from a new device.', 'wp-persistent-login'); ?></p>
                            </div>
                            <div class="setting-control">
                                <div class="toggle-switch-container">
                                    <span id="notify-new-logins-label" class="screen-reader-text">
                                        <?php _e('Notify Users of New Logins', 'wp-persistent-login'); ?>
                                    </span>
                                    <label class="toggle-switch" for="notify-new-logins-toggle" title="<?php echo $notify_new_logins == '1' ? __('Enabled', 'wp-persistent-login') : __('Disabled', 'wp-persistent-login'); ?>">
                                        <input type="checkbox" id="notify-new-logins-toggle" name="notifyNewLogins" value="1" class="toggle-switch-input" <?php checked('1', $notify_new_logins); ?> />
                                        <span class="slider"></span>
                                        <span class="toggle-text toggle-text-on"><?php _e('ON', 'wp-persistent-login'); ?></span>
                                        <span class="toggle-text toggle-text-off"><?php _e('OFF', 'wp-persistent-login'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-label">
                                <label for="display-user-login-history"><?php _e('Allow users to see their Login History', 'wp-persistent-login'); ?></label>
                                <p class="setting-description">
                                    <?php _e('Users can view their login history on their WP Admin Profile page.', 'wp-persistent-login'); ?>
                                </p>
                            </div>
                            <div class="setting-control">
                                <div class="toggle-switch-container">
                                    <span id="display-user-login-history-label" class="screen-reader-text">
                                        <?php _e('Enable User Login History Display', 'wp-persistent-login'); ?>
                                    </span>
                                    <label class="toggle-switch" for="display-user-login-history-toggle" title="<?php echo $display_user_login_history == '1' ? __('Enabled', 'wp-persistent-login') : __('Disabled', 'wp-persistent-login'); ?>">
                                        <input type="checkbox" id="display-user-login-history-toggle" name="displayUserLoginHistory" value="1" class="toggle-switch-input" <?php checked('1', $display_user_login_history); ?> />
                                        <span class="slider"></span>
                                        <span class="toggle-text toggle-text-on"><?php _e('ON', 'wp-persistent-login'); ?></span>
                                        <span class="toggle-text toggle-text-off"><?php _e('OFF', 'wp-persistent-login'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wppl-box-outline bg-light-green">
                        <h3><?php _e('Email Notification Settings', 'wp-persistent-login'); ?></h3>
                        
                        <div class="setting-row equal-columns">
                            <div class="setting-label">
                                <label for="notification-email-subject"><?php _e('Email Subject', 'wp-persistent-login'); ?></label>
                                <p class="setting-description"><?php _e('The subject line for login notification emails.', 'wp-persistent-login'); ?></p>
                            </div>
                            <div class="setting-control setting-control--stack">
                                <input type="text" id="notification-email-subject" name="notification_email_subject" value="<?php echo esc_attr($notification_email_subject); ?>" class="regular-text" style="width: 100%" />
                            </div>
                        </div>
                        
                        <div class="setting-row equal-columns">
                            <div class="setting-label">
                                <label for="notification-email-template"><?php _e('Email Template', 'wp-persistent-login'); ?></label>
                                <p class="setting-description">
                                    <?php _e('Customize the email template sent to users when a new device login is detected.', 'wp-persistent-login'); ?>
                                    <br>
                                    <?php _e('Available variables:', 'wp-persistent-login'); ?> 
                                    <div class="grid grid-cols-2">
                                        <code>{{SITE_NAME}}</code>
                                        <code>{{IP_ADDRESS}}</code>
                                        <code>{{DEVICE_DETAILS}}</code>
                                        <code>{{TIMESTAMP}}</code>
                                        <code>{{USER_EMAIL}}</code>
                                        <code>{{USERNAME}}</code>
                                        <code>{{USER_DISPLAY_NAME}}</code>
                                        <code>{{USER_FIRST_NAME}}</code>
                                        <code>{{USER_LAST_NAME}}</code>
                                    </div>
                                </p>
                            </div>
                            <div class="setting-control setting-control--stack">
                                <textarea id="notification-email-template" name="notification_email_template" rows="18" class="large-text code" style="width: 100%"><?php echo esc_textarea($notification_email_template); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wppl-box-outline bg-light-green">
                        <h3><?php _e('Login History Management', 'wp-persistent-login'); ?></h3>
                        
                        <div class="session-management-controls">
                            <p><?php _e('Need to clear all login history data? Use this button to empty the login history table.', 'wp-persistent-login'); ?></p>
                            
                            <div class="warning-box">
                                <div class="warning-icon">
                                    <span class="dashicons dashicons-warning"></span>
                                </div>
                                <div class="warning-content">
                                    <p><?php _e('This action cannot be undone. All login history data will be permanently deleted.', 'wp-persistent-login'); ?></p>
                                </div>
                            </div>

                            <span class="button button-primary" id="empty-login-history-btn">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Empty Login History', 'wp-persistent-login'); ?>
                            </span>                            
                            
                        </div>                    
                    </div>
                    
                    <?php if (defined('WPPL_PR') && WPPL_PR === false) : ?>
                        <div class="wppl-box-outline premium-feature">
                            
                            <h3><?php _e('Premium Login History Features', 'wp-persistent-login'); ?></h3>
                            
                            <div class="premium-feature-content">
                                <p><?php _e('Upgrade to Premium to unlock advanced login history features:', 'wp-persistent-login'); ?></p>
                                <ul class="premium-feature-list">
                                    <li><?php _e('Account Inactivity Emails: Notify users if they haven\'t loggged into your website for X days', 'wp-persistent-login'); ?></li>
                                    <li><?php _e('Allow users to view their login history on the front-end with shortcodes', 'wp-persistent-login'); ?></li>
                                    <li><?php _e('Gutenberg block for displaying user login history on any page', 'wp-persistent-login'); ?></li>
                                    <li><?php _e('Enhanced profile page integration with detailed login information', 'wp-persistent-login'); ?></li>
                                </ul>
                                
                                <a href="<?php echo WPPL_UPGRADE_PAGE; ?>" class="button button-primary upgrade-button">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php _e('Upgrade to Premium', 'wp-persistent-login'); ?>
                                </a>
                            </div>
                            <div class="premium-badge">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php _e('Premium', 'wp-persistent-login'); ?>
                            </div>
                        </div>                   
                    <?php endif; ?>
                    
                    <div class="form-footer">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Settings', 'wp-persistent-login'); ?>
                        </button>
                    </div>
                </form>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="clear-login-history-form" id="empty-login-history-form">
                    <input type="hidden" name="action" value="wppl_empty_login_history_table" />
                    <?php wp_nonce_field('wppl_empty_login_history_table'); ?>
                </form>
                
                <script>
                    jQuery(document).ready(function($) {
                        // Handle Empty Login History button click
                        $('#empty-login-history-btn').on('click', function(e) {
                            e.preventDefault();
                            if (confirm('<?php _e("Are you sure you want to clear all login history data? This action cannot be undone.", "wp-persistent-login"); ?>')) {
                                $('#empty-login-history-form').submit();
                            }
                        });

                        // Handle Stop Count button click
                        $('#wppl-stop-count-btn').on('click', function(e) {
                            e.preventDefault();
                            
                            var button = $(this);
                            var spinner = $('#stop-count-spinner');
                            var messageContainer = $('#stop-count-messages');
                            
                            // Confirm the action
                            if (!confirm('<?php _e("Are you sure you want to stop the current user count? The count data will be saved.", "wp-persistent-login"); ?>')) {
                                return;
                            }
                            
                            // Disable button and show spinner
                            button.prop('disabled', true);
                            spinner.show();
                            messageContainer.hide().empty();
                            
                            // Make AJAX request to stop the count
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'wppl_stop_user_count',
                                    nonce: '<?php echo wp_create_nonce('wppl_stop_user_count'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        messageContainer
                                            .removeClass('notice-error')
                                            .addClass('notice notice-success')
                                            .html('<p>' + response.data.message + '</p>')
                                            .show();
                                        
                                        // Reload the page after 2 seconds to show updated count
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    } else {
                                        messageContainer
                                            .removeClass('notice-success')
                                            .addClass('notice notice-error')
                                            .html('<p>' + response.data.message + '</p>')
                                            .show();
                                        button.prop('disabled', false);
                                    }
                                },
                                error: function() {
                                    messageContainer
                                        .removeClass('notice-success')
                                        .addClass('notice notice-error')
                                        .html('<p><?php _e("An error occurred while trying to stop the count. Please try again.", "wp-persistent-login"); ?></p>')
                                        .show();
                                    button.prop('disabled', false);
                                },
                                complete: function() {
                                    spinner.hide();
                                }
                            });
                        });
                    });
                </script>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to stop user count on demand
     *
     * @since 2.3.0
     * @return void
     */
    public function ajax_stop_user_count() {
        // Verify nonce for security
        if ( ! wp_verify_nonce( $_POST['nonce'], 'wppl_stop_user_count' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'wp-persistent-login' ) ) );
            return;
        }

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