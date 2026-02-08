<?php

/**
 * Run activation function to setup 
 */
function persistent_login_activate() {
    // Run cleanup first in case of re-activation, check if uninstall cleanup needed
    persistent_login_uninstall_cleanup();
    // add db version for future reference
    update_option( 'persistent_login_db_version', WPPL_DATABASE_VERSION );
    // set detaults for permissions - all roles are available for persistent login by default
    // free options
    if ( !get_option( 'persistent_login_options' ) ) {
        $defaultOptions = array(
            'duplicateSessions'  => '0',
            'limitActiveLogins'  => '0',
            'activeLoginLogic'   => 'automatic',
            'enableLoginHistory' => '0',
            'notifyNewLogins'    => '0',
        );
        update_option( 'persistent_login_options', $defaultOptions );
    }
    // feature options (debug logging removed)
    if ( !get_option( 'persistent_login_feature_flags' ) ) {
        $defaultFeatureOptions = array(
            'enablePersistentLogin' => '1',
            'enableActiveLogins'    => '0',
            'enableLoginHistory'    => '0',
        );
        update_option( 'persistent_login_feature_flags', $defaultFeatureOptions );
    }
    // setup CRON to check how many users are logged in
    // Use wp_next_scheduled to check if the event is already scheduled
    $timestamp = wp_next_scheduled( 'persistent_login_user_count' );
    // If $timestamp == false schedule the user count since it hasn't been done previously
    if ( $timestamp == false ) {
        // Schedule the event for right now, then to repeat twice daily using the hook 'persistent_login_user_count'
        wp_schedule_event( time(), 'twicedaily', 'persistent_login_user_count' );
    }
}
