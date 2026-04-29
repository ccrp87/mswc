<?php
/**
 * Plugin Name: WooCommerce Multi store HPOS
 * Description: Gestión de múltiples stores con stock y precios dinámicos.
 * Version: 1.0.0
 * Author: Carlos Romero
 * WC requires at least: 8.0
 * WC tested up to: 8.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clase Principal del Plugin
 */
class MSWC_Plugin {

    public function __construct() {
        // 1. Declarar compatibilidad con HPOS (Fundamental) [cite: 1183, 1184]
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

        // 2. Cargar dependencias [cite: 1182, 1302]
        $this->load_dependencies();

        // 3. Inicializar componentes en el hook correcto 
        add_action( 'plugins_loaded', array( $this, 'init_components' ) );

        // 4. Hook de activación para la DB [cite: 1288]
        register_activation_hook( __FILE__, array( $this, 'create_db_tables' ) );
    }

    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }

    private function load_dependencies() {
        $path = plugin_dir_path( __FILE__ );
        require_once $path . 'includes/class-mswc-session.php';
        require_once $path . 'includes/class-mswc-filters.php';
        require_once $path . 'includes/class-mswc-orders.php';
        require_once $path . 'includes/class-mswc-admin.php';
        require_once $path . 'includes/class-mswc-frontend.php';

    }

    public function init_components() {
        new mswc_Session();
        new mswc_Filters();
        new mswc_Orders();
        new mswc_Admin(); 
        new mswc_Frontend();
    }

    /**
     * MÉTODO ESTÁTICO: Corrige el error fatal en modal-selector.php [cite: 1862, 1863]
     * Ahora puedes llamarlo como MSWC_Plugin::get_active_stores();
     */
    public static function get_active_stores() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mswc_stores';
        
        $results = $wpdb->get_results( "SELECT id, code, name FROM $table_name WHERE enabled = 1", ARRAY_A );
        
        return $results ? $results : array();
    }

    /**
     * Creación de tablas al activar el plugin
     */
    public function create_db_tables() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $charset_collate = $wpdb->get_charset_collate();

        // Tabla de Stores
        $table_stores = $wpdb->prefix . 'mswc_stores';
        $sql_stores = "CREATE TABLE $table_stores (
            id int NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            enabled boolean NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        dbDelta( $sql_stores );

        // Tabla de Stock/Precios [cite: 1691]
        $table_stock = $wpdb->prefix . 'mswc_stores_stock';
        $sql_stock = "CREATE TABLE $table_stock (
            id INT NOT NULL AUTO_INCREMENT,
            product_id INT NOT NULL,
            store_id INT NOT NULL,
            stock INT NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product_store (store_id, product_id)
        ) $charset_collate;";
        dbDelta( $sql_stock );

        // Datos iniciales
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_stores");
        if ( $exists == 0 ) {
            $wpdb->insert( $table_stores, array( 'code' => 'norte', 'name' => 'Store Norte' ) );
            $wpdb->insert( $table_stores, array( 'code' => 'sur', 'name' => 'Store Sur' ) );
        }
    }
}

// Iniciar el Plugin
new MSWC_Plugin();