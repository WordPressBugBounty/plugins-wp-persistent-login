<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die( 'Well, get lost.' );


/**
 * Class WP_Persistent_Login_Settings
 *
 * @since 2.2.0
 */
class WP_Persistent_Login_Dashboard {

    public function display_dashboard() {
        ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
        <style>
            .users_page_wp-persistent-login #wpcontent {
                padding-left: 0;
            }
            .users_page_wp-persistent-login #wpbody-content {
                height: 100%;
                padding-bottom: 0;
            }
            .wppl-container, .wppl-container * {
                box-sizing: border-box;
            }
            .wppl-container {
                min-height: 100%;
                background-color: var(--white);
                --green: #4fe1ae;
                --dark-green: #04b399;
                --white: #ffffff;
                --black: #333333;
            }

            .text-white {
                color: var(--white);
            }
            .text-green {
                color: var(--green);
            }
            .text-black {
                color: var(--black);
            }
            .bg-white {
                background-color: var(--white);
            }
            .bg-green {
                background-color: var(--green);
            }
            .bg-black {
                background-color: var(--black);
            }
            
            .wppl-container h1,
            .wppl-container h2,
            .wppl-container h3,
            .wppl-container h4,
            .wppl-container h5 {
                font-family: "Poppins", sans-serif;
                font-weight: 700;
            }
            .wppl-container .header {
                width: calc(100% - 26px);
                border-radius: 0 0 10px 10px;
                margin-inline: auto;
                background-color: var(--green);
                color: var(--white);
                padding: 2rem 3%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                flex-wrap: wrap;
                box-shadow: 5px 5px var(--dark-green), 5px 0 var(--dark-green);
                overflow: visible;
            }
            .wppl-container .header h1 {
                margin: 0 0 0.5rem;
            }
            .wppl-container .header p {
                margin: 0;
            }
            .wppl-container .header a.button {
                background-color: var(--white);
                color: var(--black);
                border: none;
                padding: 0.3rem 1rem;
                border-radius: 0.25rem;
                text-decoration: none;
                display: inline-block;
                transition: 0.2s all ease;
            }
            .wppl-container .header a.button:hover {
                background-color: var(--black);
                color: var(--white);
            }
            .wppl-wrap {
                padding: 2rem 4%;
            }
        </style>
        <div class="wppl-container">
            <div class="header">
                <div>
                    <h1><?php _e( 'WP Persistent Login', 'wp-persistent-login' ); ?></h1>
                    <p class="text-black">Keeping users logged into WordPress since 2014</p>
                </div>
                <div>
                    <a href="<?php echo WPPL_ACCOUNT_PAGE; ?>" class="button">
                        <?php _e('My account', 'wp-persistent-login' ); ?>
                    </a>
                    <a href="<?php echo WPPL_UPGRADE_PAGE; ?>" class="button">
                        <?php _e('Manage my plan', 'wp-persistent-login' ); ?>
                    </a>
                    <a href="<?php echo WPPL_SUPPORT_PAGE; ?>" class="button">
                        <?php _e('Support', 'wp-persistent-login' ); ?>
                    </a>
                </div>
            </div>
            <div class="wppl-wrap">
                <p><?php _e( 'Welcome to the WP Persistent Login Dashboard. From here you can manage your persistent login settings and view active logins.', 'wp-persistent-login' ); ?></p>
                <p><?php _e( 'If you have any questions or need help, please visit our support page.', 'wp-persistent-login' ); ?></p>
                <p><?php _e( 'If you like this plugin, please consider leaving a review.', 'wp-persistent-login' ); ?></p>
            </div>
        </div>
        <?php
    }

}

?>