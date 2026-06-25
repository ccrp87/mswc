<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Valida que la tienda seleccionada en sesión exista y esté habilitada en todos
 * los puntos críticos del proceso de compra (defensa en profundidad).
 *
 * Cadena de validación:
 *  1. Selección AJAX      → `MSWC_Session::mswc_ajax_save_store()`       — primera barrera.
 *  2. Página de carrito   → `woocommerce_check_cart_items`                — aviso si cambia el estado.
 *  3. Checkout clásico    → `woocommerce_checkout_process`                — bloquea el envío del formulario.
 *  4. Checkout en bloques → `woocommerce_store_api_cart_errors`           — bloquea "Realizar pedido".
 *  5. Creación del pedido → `MSWC_Orders::mswc_attach_store_to_order()`  — última comprobación.
 *
 * Cuando se detecta una tienda deshabilitada, esta clase limpia la clave de sesión
 * `mswc_selected_store` para que el modal de selección vuelva a aparecer.
 *
 * Hooks registrados:
 *  - `woocommerce_check_cart_items`                 → valida en carrito y checkout clásico.
 *  - `woocommerce_checkout_process`                 → valida en el POST del formulario clásico.
 *  - `woocommerce_store_api_cart_errors`            → valida en el checkout en bloques.
 *  - `woocommerce_checkout_before_customer_details` → muestra aviso informativo de tienda activa.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Checkout_Validation {

    /**
     * Registra todos los hooks de WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'woocommerce_check_cart_items',               [ $this, 'mswc_validate_store' ] );
        add_action( 'woocommerce_checkout_process',               [ $this, 'mswc_validate_store' ] );
        add_filter( 'woocommerce_store_api_cart_errors',          [ $this, 'mswc_validate_store_for_blocks' ], 10, 2 );
        add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'mswc_display_selected_store_notice' ] );
    }

    /**
     * Obtiene los datos de la tienda activa en sesión desde la base de datos.
     *
     * Consulta siempre la DB (nunca usa únicamente la sesión) para detectar
     * cambios de estado en tiempo real (p.ej. tienda deshabilitada por el admin
     * mientras el cliente navega).
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     * @return array{id: string, name: string, enabled: string}|null Datos de la tienda, o null si no hay sesión activa o no existe.
     */
    private function mswc_get_session_store(): ?array {
        if ( ! WC()->session ) {
            return null;
        }

        $store_id = WC()->session->get( 'mswc_selected_store' );
        if ( ! $store_id ) {
            return null;
        }

        global $wpdb;
        $store = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, enabled FROM {$wpdb->prefix}mswc_stores WHERE id = %d",
                (int) $store_id
            ),
            ARRAY_A
        );

        return $store ?: null;
    }

    /**
     * Valida la tienda seleccionada para checkout clásico y página de carrito.
     *
     * Se ejecuta en `woocommerce_check_cart_items` (carrito + checkout clásico)
     * y en `woocommerce_checkout_process` (POST del formulario clásico).
     *
     * Casos de error manejados:
     *  - Sin tienda en sesión: solicita seleccionar una antes de continuar.
     *  - Tienda deshabilitada: limpia la sesión y solicita elegir otra.
     *
     * Usa `wc_add_notice( ..., 'error' )` para bloquear el proceso de checkout.
     *
     * @since 1.0.0
     */
    public function mswc_validate_store(): void {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        $store = $this->mswc_get_session_store();

        if ( ! $store ) {
            wc_add_notice(
                esc_html__( 'Debes seleccionar una tienda antes de continuar con la compra.', 'woocommerce-multi-store' ),
                'error'
            );
            return;
        }

        if ( ! (bool) $store['enabled'] ) {
            WC()->session->set( 'mswc_selected_store', null );

            wc_add_notice(
                sprintf(
                    /* translators: %s: nombre de la tienda */
                    esc_html__( 'La tienda "%s" ya no está disponible. Por favor selecciona otra tienda.', 'woocommerce-multi-store' ),
                    esc_html( $store['name'] )
                ),
                'error'
            );
        }
    }

    /**
     * Valida la tienda seleccionada para el checkout en bloques (Store API).
     *
     * Se aplica como filtro de `woocommerce_store_api_cart_errors`. Cuando el
     * objeto `WP_Error` contiene errores, WooCommerce bloquea automáticamente el
     * botón "Realizar pedido" en el bloque de checkout.
     *
     * Códigos de error añadidos al WP_Error:
     *  - `mswc_no_store`       — no hay tienda en sesión.
     *  - `mswc_store_disabled` — la tienda existe pero está deshabilitada.
     *
     * @since  1.0.0
     * @param  WP_Error $errors           Objeto de errores del carrito (Store API).
     * @param  mixed    $_cart_controller Instancia del CartController de la Store API (no usado directamente).
     * @return WP_Error                   Objeto de errores, posiblemente con nuevos errores añadidos.
     */
    public function mswc_validate_store_for_blocks( WP_Error $errors, mixed $_cart_controller ): WP_Error {
        if ( ! WC()->session ) {
            $errors->add(
                'mswc_no_store',
                esc_html__( 'Debes seleccionar una tienda antes de continuar con la compra.', 'woocommerce-multi-store' )
            );
            return $errors;
        }

        $store = $this->mswc_get_session_store();

        if ( ! $store ) {
            $errors->add(
                'mswc_no_store',
                esc_html__( 'Debes seleccionar una tienda antes de continuar con la compra.', 'woocommerce-multi-store' )
            );
            return $errors;
        }

        if ( ! (bool) $store['enabled'] ) {
            WC()->session->set( 'mswc_selected_store', null );

            $errors->add(
                'mswc_store_disabled',
                sprintf(
                    /* translators: %s: nombre de la tienda */
                    esc_html__( 'La tienda "%s" ya no está disponible. Por favor selecciona otra tienda.', 'woocommerce-multi-store' ),
                    esc_html( $store['name'] )
                )
            );
        }

        return $errors;
    }

    /**
     * Muestra un aviso informativo con la tienda activa antes del formulario de checkout.
     *
     * Se renderiza justo antes del formulario de datos del cliente
     * (`woocommerce_checkout_before_customer_details`). Solo se muestra si hay
     * una tienda habilitada en sesión; si está deshabilitada o no existe, no
     * renderiza nada.
     *
     * El enlace "Cambiar tienda" redirige a la portada del sitio, donde el modal
     * de selección volverá a aparecer al no haber tienda activa.
     *
     * @since 1.0.0
     */
    public function mswc_display_selected_store_notice(): void {
        $store = $this->mswc_get_session_store();

        if ( ! $store || ! (bool) $store['enabled'] ) {
            return;
        }

        $change_url = home_url( '/' );
        ?>
        <div class="mswc-checkout-store-info woocommerce-info">
            <?php
            printf(
                /* translators: 1: nombre de la tienda, 2: URL para cambiar tienda */
                wp_kses(
                    __( 'Tienda seleccionada: <strong>%1$s</strong> &mdash; <a href="%2$s">Cambiar tienda</a>', 'woocommerce-multi-store' ),
                    [ 'strong' => [], 'a' => [ 'href' => [] ] ]
                ),
                esc_html( $store['name'] ),
                esc_url( $change_url )
            );
            ?>
        </div>
        <?php
    }
}
