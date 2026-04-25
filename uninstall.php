<?php
/**
 * Accelvia DataForge – Uninstall
 *
 * Cleans up all plugin data when the plugin is deleted via the WordPress admin.
 * This file is executed automatically by WordPress core on plugin deletion.
 *
 * @package Accelvia_DataForge
 */

// Abort if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom database tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}accelvia_df_cache" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}accelvia_df_dashboards" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}accelvia_df_charts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

// Remove all plugin options
$accelvia_df_options = array(
    'accelvia_df_db_version',
    'accelvia_df_default_theme',
    'accelvia_df_default_animation',
);

foreach ( $accelvia_df_options as $accelvia_df_option ) {
    delete_option( $accelvia_df_option );
}
