<?php

/**
 * Database upgrade routines for Persistent Login.
 */
/**
 * persistent_login_update_db_check
 *
 * @return void
 */
function persistent_login_update_db_check() {
    // get the installed database version (not the plugin version)
    $persistent_login_db_version = get_option( 'persistent_login_db_version', '2.0.10' );
    $plugin_version = WPPL_DATABASE_VERSION;
    // This is the target version from the plugin
    // test db version number against plugin
    if ( $persistent_login_db_version !== $plugin_version ) {
        // if different, run the update function
        $persistent_login_db_update = persistent_login_update_db( $persistent_login_db_version );
    }
}

add_action( 'plugins_loaded', 'persistent_login_update_db_check' );
/**
 * persistent_login_cleanup_duplicate_sessions
 *
 * Removes stale duplicate session rows that were created by older login flows.
 *
 * @return int Number of removed sessions.
 */
function persistent_login_cleanup_duplicate_sessions() {
    $users = get_users( array(
        'fields'   => 'ID',
        'number'   => -1,
        'meta_key' => 'session_tokens',
    ) );
    if ( empty( $users ) || !is_array( $users ) ) {
        return 0;
    }
    $removed_sessions = 0;
    foreach ( $users as $user_id ) {
        $removed_sessions += persistent_login_cleanup_duplicate_sessions_for_user( (int) $user_id );
    }
    return $removed_sessions;
}

/**
 * persistent_login_cleanup_duplicate_sessions_for_user
 *
 * Keeps the newest session for each device signature and removes older duplicates.
 *
 * @param  int $user_id User ID.
 * @return int Number of removed sessions.
 */
function persistent_login_cleanup_duplicate_sessions_for_user(  $user_id  ) {
    $sessions = get_user_meta( $user_id, 'session_tokens', true );
    if ( !is_array( $sessions ) || empty( $sessions ) ) {
        return 0;
    }
    // Sort newest first so the first session we encounter for a device is preserved.
    uasort( $sessions, function ( $session_a, $session_b ) {
        $login_a = ( isset( $session_a['login'] ) ? (int) $session_a['login'] : 0 );
        $login_b = ( isset( $session_b['login'] ) ? (int) $session_b['login'] : 0 );
        if ( $login_a === $login_b ) {
            return 0;
        }
        return ( $login_a > $login_b ? -1 : 1 );
    } );
    $seen_signatures = array();
    $cleaned_sessions = array();
    $removed_sessions = 0;
    foreach ( $sessions as $verifier => $session ) {
        if ( empty( $session['ip'] ) || empty( $session['ua'] ) ) {
            $cleaned_sessions[$verifier] = $session;
            continue;
        }
        $signature = sha1( $session['ip'] . '|' . $session['ua'] );
        if ( isset( $seen_signatures[$signature] ) ) {
            $removed_sessions++;
            continue;
        }
        $seen_signatures[$signature] = $verifier;
        $cleaned_sessions[$verifier] = $session;
    }
    if ( $removed_sessions > 0 ) {
        if ( !empty( $cleaned_sessions ) ) {
            update_user_meta( $user_id, 'session_tokens', $cleaned_sessions );
        } else {
            delete_user_meta( $user_id, 'session_tokens' );
        }
    }
    return $removed_sessions;
}

/**
 * persistent_login_update_db
 *
 * @param  mixed $persistent_login_db_version
 * @return void
 */
function persistent_login_update_db(  $persistent_login_db_version  ) {
    // multi-device support
    if ( $persistent_login_db_version === '1.1.3' ) {
        // load required global vars
        global $wpdb;
        $tableRef = WPPL_DATABASE_NAME;
        // set table name
        $table = $wpdb->prefix . $tableRef;
        // fetch charset for db
        $charset_collate = $wpdb->get_charset_collate();
        // run query
        $table_update = $wpdb->query( "\r\n                    ALTER TABLE {$table} \r\n                    ADD `ip` INT(11) NOT NULL AFTER `login_key`,\r\n                    ADD `user_agent` varchar(255) NOT NULL AFTER `ip`\r\n                " );
        // update db version option
        update_option( 'persistent_login_db_version', '1.1.3' );
        $persistent_login_db_version = '1.1.3';
    }
    // 1.1.3 update
    // timestamps
    if ( $persistent_login_db_version === '1.1.3' ) {
        // load required global vars
        global $wpdb;
        $tableRef = WPPL_DATABASE_NAME;
        // set table name
        $table = $wpdb->prefix . $tableRef;
        // fetch charset for db
        $charset_collate = $wpdb->get_charset_collate();
        // run query
        $table_update = $wpdb->query( "\r\n                    ALTER TABLE {$table} \r\n                    ADD `timestamp` CHAR(19) NOT NULL AFTER `user_agent`\r\n                " );
        // update db version option
        update_option( 'persistent_login_db_version', '1.2.3' );
        $persistent_login_db_version = '1.2.3';
    }
    // 1.2.3 update
    // remove db, no longer needed
    if ( $persistent_login_db_version === '1.2.3' ) {
        // remove all existing logins
        global $wpdb;
        $tableRef = WPPL_DATABASE_NAME;
        $table = $wpdb->prefix . $tableRef;
        // drop the table, we don't need it anymore!
        $sql = "DROP TABLE IF EXISTS {$table};";
        $drop = $wpdb->query( $sql );
        if ( $drop ) {
            // update db version option
            update_option( 'persistent_login_db_version', '1.3.0' );
            $persistent_login_db_version = '1.3.0';
            return true;
        } else {
            return false;
        }
    }
    // 1.3.0 update
    // fixing options in options table
    if ( $persistent_login_db_version === '1.3.0' ) {
        // fetching the current settings, which we don't need any more!
        $current_settings = get_option( 'persistent_login_options_user_access' );
        if ( $current_settings ) {
            // now delete the old free option, not needed anymore
            delete_option( 'persistent_login_options_user_access' );
            // update db version option
            update_option( 'persistent_login_db_version', '1.3.10' );
            $persistent_login_db_version = '1.3.10';
        }
    }
    // 1.3.10 update
    if ( $persistent_login_db_version === '1.3.10' ) {
        // Use wp_next_scheduled to check if the event is already scheduled
        $timestamp = wp_next_scheduled( 'persistent_login_user_count' );
        // If $timestamp == false schedule the user count since it hasn't been done previously
        if ( $timestamp == false ) {
            // Schedule the event for right now, then to repeat twice daily using the hook 'persistent_login_user_count'
            wp_schedule_event( time(), 'twicedaily', 'persistent_login_user_count' );
        }
        // update db version option
        update_option( 'persistent_login_db_version', '1.3.12' );
        $persistent_login_db_version = '1.3.12';
    }
    // 1.3.12 update
    if ( $persistent_login_db_version === '1.3.12' ) {
        $options = get_option( 'persistent_login_options' );
        if ( !isset( $options['limitActiveLogins'] ) ) {
            $options['limitActiveLogins'] = '0';
        }
        if ( !isset( $options['duplicateSessions'] ) ) {
            $options['duplicateSessions'] = '0';
        }
        update_option( 'persistent_login_options', $options );
        // update db version option
        update_option( 'persistent_login_db_version', '2.0.0' );
        $persistent_login_db_version = '2.0.0';
    }
    // 2.0.0 update
    if ( $persistent_login_db_version === '2.0.0' ) {
        // update db version option
        update_option( 'persistent_login_db_version', '2.0.9' );
        $persistent_login_db_version = '2.0.9';
    }
    // 2.0.9 update
    if ( $persistent_login_db_version === '2.0.9' ) {
        $options = get_option( 'persistent_login_options' );
        // add enable login history to db
        if ( !isset( $options['enableLoginHistory'] ) ) {
            $options['enableLoginHistory'] = '0';
        }
        // add notify new logins checkbox
        if ( !isset( $options['notifyNewLogins'] ) ) {
            $options['notifyNewLogins'] = '0';
        }
        update_option( 'persistent_login_options', $options );
        // add new login history template to table
        update_option( 'persistent_login_notification_email_template', '' );
        // update db version option
        update_option( 'persistent_login_db_version', '2.0.10' );
        $persistent_login_db_version = '2.0.10';
    }
    // 2.0.10 update
    // Feature option naming consistency update for version 3.0.1
    // Steps: Fetch existing -> derive autoload -> delete -> rebuild camelCase array -> add option -> log result
    if ( $persistent_login_db_version === '2.0.10' ) {
        $old_option_name = 'persistent_login_feature_options';
        $new_option_name = 'persistent_login_feature_flags';
        // 1. Fetch existing raw value and autoload.
        $existing_options = get_option( $old_option_name, array() );
        // 2. Delete existing option completely (removes any duplicates or stale values).
        delete_option( $old_option_name );
        if ( function_exists( 'wp_cache_delete' ) ) {
            wp_cache_delete( $old_option_name, 'options' );
        }
        // check if the option is still accessible
        $check = get_option( $old_option_name, 'NOT_SET' );
        if ( $check !== 'NOT_SET' ) {
            // if it's still there, try deleting again through wpdb
            global $wpdb;
            $wpdb->delete( $wpdb->options, array(
                'option_name' => $old_option_name,
            ) );
            if ( function_exists( 'wp_cache_delete' ) ) {
                wp_cache_delete( $old_option_name, 'options' );
            }
        }
        // 3. Rebuild canonical camelCase structure from whatever keys we had.
        // Rebuild canonical feature flag array with consistent camelCase keys.
        // Priority for each flag:
        // 1. Existing camelCase key.
        // 2. Legacy snake_case key (if applicable).
        // 3. Sensible default.
        $rebuilt_options = array();
        $feature_map = array(
            'enablePersistentLogin' => array(
                'legacy'  => 'enable_persistent_login',
                'default' => '1',
            ),
            'enableActiveLogins'    => array(
                'legacy'  => 'enable_active_logins',
                'default' => '0',
            ),
            'enableLoginHistory'    => array(
                'legacy'  => null,
                'default' => '0',
            ),
        );
        foreach ( $feature_map as $camel_key => $meta ) {
            if ( isset( $existing_options[$camel_key] ) ) {
                // Already stored in new canonical form.
                $rebuilt_options[$camel_key] = $existing_options[$camel_key];
            } elseif ( !empty( $meta['legacy'] ) && isset( $existing_options[$meta['legacy']] ) ) {
                // Use legacy snake_case value if present.
                $rebuilt_options[$camel_key] = $existing_options[$meta['legacy']];
            } else {
                // Fallback to default.
                $rebuilt_options[$camel_key] = $meta['default'];
            }
        }
        // 4. Add the option fresh.
        $added = add_option( $new_option_name, $rebuilt_options );
        // 5. If add failed (edge case: race condition created it), fallback to update_option to enforce value.
        if ( !$added ) {
            $updated = update_option( $new_option_name, $rebuilt_options );
        }
        // 6. Final read-back for verification.
        $final = get_option( $new_option_name, array() );
        // 7. Update DB version.
        update_option( 'persistent_login_db_version', '3.0.1' );
        $persistent_login_db_version = '3.0.1';
    }
    // 3.0.1 update
    // One-time cleanup for installs that accumulated duplicate same-device sessions.
    if ( $persistent_login_db_version === '3.0.1' ) {
        persistent_login_cleanup_duplicate_sessions();
        update_option( 'persistent_login_db_version', '3.0.5' );
        $persistent_login_db_version = '3.0.5';
    }
    // 3.0.5 cleanup
    // Return true to indicate that the update was performed
    return true;
}
