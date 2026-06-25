<?php
/**
 * Plugin Uninstall Handler
 *
 * Removes all database tables and options created by the WooCommerce Multi-Store plugin.
 * This file is executed only when the plugin is deleted (not deactivated).
 *
 * @since 1.0.0
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mswc_stores_stock" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mswc_stores" );

delete_option( 'mswc_db_version' );
