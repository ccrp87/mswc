<?php
/**
 * Plugin Name:       WooCommerce Multi-Store HPOS
 * Plugin URI:        https://github.com/ccrp87/mswc
 * Description:       Gestión de múltiples stores con stock y precios dinámicos, compatible con HPOS.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Carlos Romero
 * Author URI:        https://github.com/ccrp87/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-multi-store
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   8.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSWC_VERSION',    '1.0.0' );
define( 'MSWC_DB_VERSION', '1.0.0' );
define( 'MSWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Clase principal del plugin WooCommerce Multi-Store HPOS.
 *
 * Actúa como punto de entrada único: declara compatibilidad con HPOS,
 * carga el textdomain, requiere las clases de soporte e instancia los
 * componentes solo cuando WooCommerce está activo. También gestiona el
 * ciclo de vida del plugin (activación, desactivación, desinstalación) y
 * expone el helper estático `get_active_stores()` que el resto de clases
 * utilizan para obtener la lista de tiendas habilitadas sin duplicar la
 * consulta SQL.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Plugin {

    /**
     * Inicializa el plugin: carga dependencias, registra hooks de ciclo de vida
     * y programa la inicialización de componentes.
     *
     * Las dependencias se cargan en el constructor (antes de cualquier hook)
     * para garantizar que todas las clases existen cuando WordPress las necesita.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
        add_action( 'plugins_loaded',          [ $this, 'load_textdomain' ] );
        add_action( 'plugins_loaded',          [ $this, 'init_components' ] );

        register_activation_hook(   __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        register_uninstall_hook(    __FILE__, [ 'MSWC_Plugin', 'uninstall' ] );

        $this->load_dependencies();
    }

    /**
     * Declara compatibilidad con High-Performance Order Storage (HPOS).
     *
     * Se ejecuta en `before_woocommerce_init` para que la declaración llegue
     * antes de que WooCommerce evalúe las compatibilidades de los plugins activos.
     * Si la clase `FeaturesUtil` no existe (versiones antiguas de WooCommerce),
     * no hace nada y no genera ningún error.
     *
     * @since 1.0.0
     */
    public function declare_hpos_compatibility(): void {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }

    /**
     * Carga el textdomain del plugin para que las cadenas sean traducibles.
     *
     * Se enlaza a `plugins_loaded` para garantizar que WordPress ha terminado
     * de cargar todos los plugins antes de intentar cargar las traducciones.
     *
     * @since 1.0.0
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'woocommerce-multi-store',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    /**
     * Requiere todos los archivos de clase del plugin.
     *
     * Se llama desde el constructor antes de que ningún hook se haya
     * disparado, de modo que todas las clases quedan definidas antes de
     * que `init_components` las instancie en `plugins_loaded`.
     *
     * @since 1.0.0
     */
    private function load_dependencies(): void {
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-session.php';
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-filters.php';
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-orders.php';
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-checkout-validation.php';
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-admin.php';
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-admin-stores.php';
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-frontend.php';
    }

    /**
     * Instancia todos los componentes del plugin si WooCommerce está activo.
     *
     * Si WooCommerce no está instalado o activo, registra un aviso de error
     * en el panel de administración y aborta la inicialización para evitar
     * errores fatales por clases no disponibles.
     *
     * @since 1.0.0
     */
    public function init_components(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [ $this, 'notice_woocommerce_required' ] );
            return;
        }
        new MSWC_Session();
        new MSWC_Filters();
        new MSWC_Orders();
        new MSWC_Checkout_Validation();
        new MSWC_Admin();
        new MSWC_Admin_Stores();
        new MSWC_Frontend();
    }

    /**
     * Muestra un aviso de error en el panel de administración cuando
     * WooCommerce no está instalado o activo.
     *
     * @since 1.0.0
     */
    public function notice_woocommerce_required(): void {
        echo '<div class="notice notice-error"><p>' .
            esc_html__( 'WooCommerce Multi-Store requiere que WooCommerce esté instalado y activo.', 'woocommerce-multi-store' ) .
            '</p></div>';
    }

    /**
     * Se ejecuta al activar el plugin: crea las tablas de la base de datos.
     *
     * @since 1.0.0
     */
    public function activate(): void {
        $this->create_db_tables();
    }

    /**
     * Reservado para limpieza al desactivar el plugin.
     *
     * La eliminación de datos se realiza en `uninstall.php`, no aquí,
     * para respetar el comportamiento esperado por WordPress: la desactivación
     * no debe borrar datos del usuario.
     *
     * @since 1.0.0
     */
    public function deactivate(): void {
        // Reservado para limpieza en desactivación.
    }

    /**
     * Delega la desinstalación del plugin a `uninstall.php`.
     *
     * `uninstall.php` elimina las tablas `mswc_stores` y `mswc_stores_stock`
     * y borra la opción `mswc_db_version`.
     *
     * @since 1.0.0
     */
    public static function uninstall(): void {
        if ( file_exists( MSWC_PLUGIN_DIR . 'uninstall.php' ) ) {
            include MSWC_PLUGIN_DIR . 'uninstall.php';
        }
    }

    /**
     * Devuelve todas las tiendas habilitadas desde la base de datos.
     *
     * Método estático accesible globalmente, utilizado por `MSWC_Frontend`,
     * `MSWC_Admin` y `MSWC_Session` para obtener la lista de tiendas activas
     * sin duplicar la consulta SQL en cada clase.
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     * @return array<int, array{id: string, code: string, name: string}> Array indexado de tiendas activas.
     */
    public static function get_active_stores(): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mswc_stores';
        $results    = $wpdb->get_results(
            $wpdb->prepare( "SELECT id, code, name FROM {$table_name} WHERE enabled = %d", 1 ),
            ARRAY_A
        );
        return $results ? $results : [];
    }

    /**
     * Crea (o actualiza) las tablas `{prefix}mswc_stores` y `{prefix}mswc_stores_stock`.
     *
     * Usa `dbDelta()` para que las modificaciones de esquema en futuras versiones
     * sean no destructivas (solo añade columnas o índices, nunca los elimina).
     * Inserta dos tiendas de ejemplo ("Store Norte" y "Store Sur") si la tabla
     * está vacía al activar el plugin por primera vez.
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    private function create_db_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $table_stores = $wpdb->prefix . 'mswc_stores';
        $sql_stores   = "CREATE TABLE {$table_stores} (
            id      INT          NOT NULL AUTO_INCREMENT,
            code    VARCHAR(50)  NOT NULL,
            name    VARCHAR(100) NOT NULL,
            enabled BOOLEAN      NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) {$charset_collate};";
        dbDelta( $sql_stores );

        $table_stock = $wpdb->prefix . 'mswc_stores_stock';
        $sql_stock   = "CREATE TABLE {$table_stock} (
            id         INT            NOT NULL AUTO_INCREMENT,
            product_id INT            NOT NULL,
            store_id   INT            NOT NULL,
            stock      INT            NOT NULL DEFAULT 0,
            price      DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_product_store (store_id, product_id)
        ) {$charset_collate};";
        dbDelta( $sql_stock );

        $exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_stores}" );
        if ( (int) $exists === 0 ) {
            $wpdb->insert( $table_stores, [ 'code' => 'norte', 'name' => 'Store Norte' ], [ '%s', '%s' ] );
            $wpdb->insert( $table_stores, [ 'code' => 'sur',   'name' => 'Store Sur'   ], [ '%s', '%s' ] );
        }

        update_option( 'mswc_db_version', MSWC_DB_VERSION );
    }
}

new MSWC_Plugin();
