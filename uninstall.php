<?php
/**
 * Pulseem Uninstall
 *
 * Handles cleanup when the plugin is uninstalled.
 *
 * @since 1.3.7
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove options
delete_option( 'pulseem_settings' );
delete_option( 'pulseem_needs_api_setup' );
delete_option( 'pulseem_ab_db_version' );
delete_option( 'pulseem_ab_db_db_version' );

// Remove custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pulseem_abandoned" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pulseem_logs" );

// Clear cron events
wp_clear_scheduled_hook( 'pulseem_abandoned_cron_hook' );

// Clean up transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pulseem\_%' OR option_name LIKE '_transient_timeout_pulseem\_%'"
);
