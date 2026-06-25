<?php
/**
 * PHPUnit bootstrap for WooCommerce Multi-Store HPOS.
 *
 * Loads Composer autoloader, defines constants, provides WooCommerce stubs,
 * and requires the plugin classes under test — all without a full WordPress
 * or WooCommerce installation.
 */

// ---------------------------------------------------------------------------
// Composer autoloader (PHPUnit, Brain Monkey, Mockery, test namespaces)
// ---------------------------------------------------------------------------
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Patchwork MUST be loaded before any user-defined functions that Brain Monkey
// will later need to override. Require it explicitly right after the autoloader
// so it can instrument functions defined in our stubs file.
// ---------------------------------------------------------------------------
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// ---------------------------------------------------------------------------
// WordPress constants expected by the plugin classes
// ---------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__, 5 ) . '/' ); // points to WP root
}

if ( ! defined( 'MSWC_VERSION' ) ) {
    define( 'MSWC_VERSION', '1.0.0' );
}

if ( ! defined( 'MSWC_DB_VERSION' ) ) {
    define( 'MSWC_DB_VERSION', '1.0.0' );
}

if ( ! defined( 'MSWC_PLUGIN_DIR' ) ) {
    define( 'MSWC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'MSWC_PLUGIN_URL' ) ) {
    define( 'MSWC_PLUGIN_URL', 'http://example.com/wp-content/plugins/woocommerce-multi-store/' );
}

// ---------------------------------------------------------------------------
// WooCommerce + WordPress stub definitions
// (loaded AFTER Patchwork so Brain Monkey can override these functions)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/stubs/woocommerce.php';

// ---------------------------------------------------------------------------
// Plugin classes under test
// (Excluded: woocommerce-multi-store.php main file and class-mswc-admin-stores.php
//  which requires WP_List_Table with a real WP environment;
//  class-mswc-stores-list-table.php likewise.)
// ---------------------------------------------------------------------------
require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-filters.php';
require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-orders.php';
require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-session.php';
require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-checkout-validation.php';
require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-admin.php';
