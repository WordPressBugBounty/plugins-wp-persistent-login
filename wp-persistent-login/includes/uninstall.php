<?php

function persistent_login_uninstall_cleanup() {
    // remove database options
    $options = array(
        'persistent_login_db_version',
        'persistent_login_options',
        'persistent_login_user_count',
        'persistent_login_feature_flags',
        '_transient_persistent_login_last_count',
        '_transient_persistent_login_user_count_running',
        '_transient_persistent_login_allowed_roles_reference',
        '_transient_persistent_login_user_count_temporary',
        '_transient_persistent_login_user_count_current_role',
        '_transient_persistent_login_user_count_offset'
    );
    foreach ( $options as $option ) {
        delete_option( $option );
    }
    // unschedule cron event
    wp_clear_scheduled_hook( 'persistent_login_user_count' );
}
