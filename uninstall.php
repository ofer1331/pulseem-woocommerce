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
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping plugin tables on uninstall is expected.
$wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $wpdb->prefix . 'pulseem_abandoned' ) . "`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping plugin tables on uninstall is expected.
$wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $wpdb->prefix . 'pulseem_logs' ) . "`" );

// Clear cron events
wp_clear_scheduled_hook( 'pulseem_abandoned_cron_hook' );

// Clean up transients
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup on uninstall requires direct query.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_pulseem_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_pulseem_' ) . '%'
    )
);
