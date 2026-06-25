<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestiona la sesión de WooCommerce y la selección de tienda activa.
 *
 * Responsabilidades:
 *  - Iniciar la sesión de WooCommerce para visitantes anónimos, de modo que
 *    `WC()->session->get()` funcione antes de que el cliente inicie sesión.
 *  - Renderizar el modal de selección de tienda en el footer cuando no hay
 *    ninguna tienda guardada en sesión.
 *  - Encolar el script `selector.js` con el nonce AJAX necesario.
 *  - Manejar la llamada AJAX `mswc_save_store` que persiste en sesión la
 *    tienda elegida por el cliente, validando que exista y esté habilitada.
 *
 * Hooks registrados:
 *  - `init`                           → fuerza inicio de sesión de WooCommerce.
 *  - `wp_footer`                      → modal de selección si no hay tienda activa.
 *  - `wp_enqueue_scripts`             → encola `selector.js` y localiza variables.
 *  - `wp_ajax_mswc_save_store`        → manejador AJAX para usuarios autenticados.
 *  - `wp_ajax_nopriv_mswc_save_store` → manejador AJAX para usuarios anónimos.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Session {

    /**
     * Registra todos los hooks de WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'init',               [ $this, 'mswc_set_customer_session' ],    5 );
        add_action( 'wp_footer',          [ $this, 'mswc_render_selection_modal' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'mswc_enqueue_scripts' ] );

        add_action( 'wp_ajax_mswc_save_store',        [ $this, 'mswc_ajax_save_store' ] );
        add_action( 'wp_ajax_nopriv_mswc_save_store', [ $this, 'mswc_ajax_save_store' ] );
    }

    /**
     * Fuerza el inicio de la sesión de WooCommerce para visitantes anónimos.
     *
     * Sin sesión activa, `WC()->session->get()` devolvería null en todos los
     * filtros de stock y precio, mostrando valores incorrectos al cliente. Se
     * ejecuta en `init` con prioridad 5 para asegurar que la sesión existe
     * antes de que otros hooks comiencen a consultarla.
     *
     * No actúa en el contexto del administrador ni en peticiones AJAX para no
     * crear sesiones innecesarias durante tareas de gestión interna.
     *
     * @since 1.0.0
     */
    public function mswc_set_customer_session(): void {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }
        if ( isset( WC()->session ) && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }

    /**
     * Renderiza el modal de selección de tienda en el footer del frontend.
     *
     * El modal solo se muestra cuando no hay ninguna tienda guardada en la
     * sesión activa del cliente. El template `modal-selector.php` incluye el
     * `<select>` con las tiendas habilitadas y un elemento `aria-live` para
     * mensajes de error accesibles.
     *
     * @since 1.0.0
     */
    public function mswc_render_selection_modal(): void {
        if ( isset( WC()->session ) && ! WC()->session->get( 'mswc_selected_store' ) ) {
            include MSWC_PLUGIN_DIR . 'templates/modal-selector.php';
        }
    }

    /**
     * Encola el script `selector.js` y expone las variables necesarias para AJAX.
     *
     * Variables localizadas en el objeto global `mswc_vars`:
     *  - `ajax_url` → URL del endpoint `admin-ajax.php`.
     *  - `nonce`    → nonce generado con la acción `mswc_save_store`, verificado
     *                 por `mswc_ajax_save_store()` para prevenir CSRF.
     *
     * @since 1.0.0
     */
    public function mswc_enqueue_scripts(): void {
        wp_enqueue_script(
            'mswc-selector',
            MSWC_PLUGIN_URL . 'assets/js/selector.js',
            [ 'jquery' ],
            MSWC_VERSION,
            true
        );

        $selected_store = ( isset( WC()->session ) && WC()->session->get( 'mswc_selected_store' ) )
            ? WC()->session->get( 'mswc_selected_store' )
            : '';

        wp_localize_script( 'mswc-selector', 'mswc_vars', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'mswc_save_store' ),
            'selected_store' => $selected_store,
        ] );
    }

    /**
     * Manejador AJAX que valida y persiste la tienda seleccionada en sesión.
     *
     * Cadena de validación:
     *  1. Verifica el nonce `mswc_save_store` para prevenir CSRF.
     *  2. Comprueba que el parámetro POST `store` existe y es un entero positivo.
     *  3. Consulta la base de datos para confirmar que la tienda existe.
     *  4. Verifica que la tienda está habilitada (`enabled = 1`).
     *  5. Guarda el ID en `WC()->session` bajo la clave `mswc_selected_store`.
     *
     * En caso de error devuelve `wp_send_json_error` con una clave `message`
     * para que `selector.js` pueda mostrar el texto al usuario en el modal.
     * En caso de éxito devuelve `wp_send_json_success` con `store_name`.
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_ajax_save_store(): void {
        check_ajax_referer( 'mswc_save_store', 'nonce' );

        if ( ! isset( $_POST['store'] ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Parámetro de tienda no recibido.', 'woocommerce-multi-store' ) ] );
        }

        $store_id = absint( $_POST['store'] );

        if ( ! $store_id ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Selección no válida.', 'woocommerce-multi-store' ) ] );
        }

        global $wpdb;
        $store = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, enabled FROM {$wpdb->prefix}mswc_stores WHERE id = %d",
                $store_id
            ),
            ARRAY_A
        );

        if ( ! $store ) {
            wp_send_json_error( [ 'message' => esc_html__( 'La tienda seleccionada no existe.', 'woocommerce-multi-store' ) ] );
        }

        if ( ! (bool) $store['enabled'] ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %s: nombre de la tienda */
                    esc_html__( 'La tienda "%s" está deshabilitada y no acepta pedidos.', 'woocommerce-multi-store' ),
                    esc_html( $store['name'] )
                ),
            ] );
        }

        WC()->session->set( 'mswc_selected_store', (string) $store_id );
        
        // Recalcular precios del carrito después de cambiar la tienda.
        // Esto garantiza que los items existentes reflejen los nuevos precios de bodega.
        if ( isset( WC()->cart ) && ! WC()->cart->is_empty() ) {
            WC()->cart->calculate_totals();
        }
        
        wp_send_json_success( [ 'store_name' => esc_html( $store['name'] ) ] );
    }
}
