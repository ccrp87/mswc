<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestiona la integración del plugin con el editor de productos de WooCommerce.
 *
 * Añade una pestaña "Stores" en el panel de datos del producto que muestra
 * una tabla editable con el stock y el precio por tienda/bodega. Los valores
 * se almacenan en `{prefix}mswc_stores_stock` mediante `$wpdb->replace`, que
 * ejecuta INSERT ... ON DUPLICATE KEY UPDATE gracias a la clave única
 * `(store_id, product_id)`. Esto permite crear o actualizar el registro sin
 * comprobar previamente si ya existe.
 *
 * También encola los assets de administración (CSS + JS) exclusivamente en la
 * pantalla de edición de productos para no interferir con otras páginas.
 *
 * Hooks registrados:
 *  - `woocommerce_product_data_tabs`            → añade la pestaña "Stores".
 *  - `woocommerce_product_data_panels`          → renderiza el contenido del panel.
 *  - `woocommerce_admin_process_product_object` → guarda los campos al publicar/actualizar.
 *  - `admin_enqueue_scripts`                    → encola CSS y JS solo en la pantalla de producto.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Admin {

    /**
     * Registra todos los hooks de WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'woocommerce_product_data_tabs',            [ $this, 'mswc_add_stores_product_tab' ],    10, 1 );
        add_action( 'woocommerce_product_data_panels',          [ $this, 'mswc_render_stores_product_panel' ] );
        add_action( 'woocommerce_admin_process_product_object', [ $this, 'mswc_save_store_stock_fields' ] );
        add_action( 'admin_enqueue_scripts',                    [ $this, 'mswc_enqueue_admin_assets' ] );
    }

    /**
     * Añade la pestaña "Stores" al panel de datos del producto.
     *
     * La pestaña se muestra en productos simples y variables. Se inserta con
     * prioridad 20 para aparecer antes de las pestañas estándar de WooCommerce
     * de mayor prioridad numérica (p.ej. Inventario en prioridad 70).
     *
     * @since  1.0.0
     * @param  array<string, array<string, mixed>> $tabs Array asociativo de pestañas existentes.
     * @return array<string, array<string, mixed>>       Array de pestañas con la nueva entrada añadida.
     */
    public function mswc_add_stores_product_tab( array $tabs ): array {
        $tabs['mswc_stores_tab'] = [
            'label'    => esc_html__( 'Stores', 'woocommerce-multi-store' ),
            'target'   => 'mswc_stores_product_data',
            'class'    => [ 'show_if_simple', 'show_if_variable' ],
            'priority' => 20,
        ];
        return $tabs;
    }

    /**
     * Renderiza el panel de datos de stores dentro del editor de producto.
     *
     * Consulta todas las tiendas (habilitadas y deshabilitadas) con LEFT JOIN
     * sobre `mswc_stores_stock` para mostrar el stock y precio actuales, o
     * valores vacíos si el producto aún no tiene datos para esa tienda.
     *
     * Usa `woocommerce_wp_text_input()` para que los campos se rendericen con
     * el estilo y comportamiento estándar de WooCommerce.
     *
     * @since  1.0.0
     * @global WC_Product $product_object Objeto del producto en edición (inyectado por WooCommerce).
     * @global wpdb       $wpdb           Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_render_stores_product_panel(): void {
        global $product_object, $wpdb;

        $table_stores = $wpdb->prefix . 'mswc_stores';
        $table_stock  = $wpdb->prefix . 'mswc_stores_stock';

        $stores = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.id, s.code, s.name, st.stock, st.price
                 FROM {$table_stores} s
                 LEFT JOIN {$table_stock} st ON s.id = st.store_id AND st.product_id = %d
                 ORDER BY s.name ASC",
                $product_object->get_id()
            ),
            ARRAY_A
        );

        echo '<div id="mswc_stores_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<div class="options_group">';

        if ( ! empty( $stores ) ) {
            echo '<table class="widefat fixed striped mswc-stores-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Store', 'woocommerce-multi-store' ) . '</th>';
            echo '<th>' . esc_html__( 'Stock', 'woocommerce-multi-store' ) . '</th>';
            echo '<th>' . esc_html__( 'Precio', 'woocommerce-multi-store' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $stores as $store ) {
                echo '<tr>';
                echo '<td>' . esc_html( $store['name'] ) . '</td>';
                echo '<td>';
                woocommerce_wp_text_input( [
                    'id'            => '_stock_store_' . esc_attr( $store['code'] ),
                    'label'         => '',
                    'type'          => 'number',
                    'wrapper_class' => 'mswc-stock-input',
                    'value'         => isset( $store['stock'] ) ? (int) $store['stock'] : 0,
                ] );
                echo '</td><td>';
                woocommerce_wp_text_input( [
                    'id'            => '_price_store_' . esc_attr( $store['code'] ),
                    'label'         => '',
                    'type'          => 'text',
                    'class'         => 'wc_input_price',
                    'wrapper_class' => 'mswc-price-input',
                    'value'         => isset( $store['price'] ) ? wc_format_localized_price( $store['price'] ) : '',
                ] );
                echo '</td></tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div></div>';
    }

    /**
     * Guarda los campos de stock y precio por tienda al publicar o actualizar un producto.
     *
     * Lee los campos `_stock_store_{code}` y `_price_store_{code}` del POST y
     * los persiste en `{prefix}mswc_stores_stock` usando `$wpdb->replace`, que
     * ejecuta INSERT ... ON DUPLICATE KEY UPDATE gracias a la clave única
     * `(store_id, product_id)`. Esto elimina la necesidad de comprobar si el
     * registro ya existe antes de decidir entre INSERT y UPDATE.
     *
     * Solo actúa si el usuario tiene el permiso `edit_products`.
     * El nonce ya ha sido verificado por WooCommerce antes de disparar este hook.
     *
     * @since  1.0.0
     * @param  WC_Product $product Objeto del producto que se está guardando.
     * @global wpdb       $wpdb    Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_save_store_stock_fields( WC_Product $product ): void {
        if ( ! current_user_can( 'edit_products' ) ) {
            return;
        }

        global $wpdb;
        $stores     = MSWC_Plugin::get_active_stores();
        $table_name = $wpdb->prefix . 'mswc_stores_stock';
        $product_id = $product->get_id();

        foreach ( $stores as $store ) {
            $field_stock = '_stock_store_' . $store['code'];
            $field_price = '_price_store_' . $store['code'];

            $stock_val = isset( $_POST[ $field_stock ] )
                ? absint( $_POST[ $field_stock ] )
                : 0;

            $price_raw = isset( $_POST[ $field_price ] )
                ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ $field_price ] ) ) )
                : '';
            $price_val = ( '' !== $price_raw && (float) $price_raw > 0 ) ? (float) $price_raw : 0.0;

            $wpdb->replace(
                $table_name,
                [
                    'product_id' => $product_id,
                    'store_id'   => (int) $store['id'],
                    'stock'      => $stock_val,
                    'price'      => $price_val,
                ],
                [ '%d', '%d', '%d', '%f' ]
            );
        }
    }

    /**
     * Encola los assets de administración (CSS y JS) en la pantalla de edición de producto.
     *
     * La carga se limita a páginas cuyo `post_type` sea `product` para no
     * añadir recursos innecesarios en el resto del panel de administración.
     *
     * Assets encolados:
     *  - `mswc-admin-css` → `assets/css/admin-style.css`
     *  - `mswc-admin-js`  → `assets/js/admin.js` (en el footer, depende de jQuery)
     *
     * @since 1.0.0
     */
    public function mswc_enqueue_admin_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_style(
            'mswc-admin-css',
            MSWC_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            MSWC_VERSION
        );

        wp_enqueue_script(
            'mswc-admin-js',
            MSWC_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            MSWC_VERSION,
            true
        );
    }
}
