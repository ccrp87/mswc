<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestiona la integración de la tienda seleccionada con los pedidos de WooCommerce.
 *
 * Responsabilidades:
 *  - Adjuntar el ID de la tienda activa al meta del pedido en el momento de su
 *    creación, con una última comprobación de que la tienda sigue habilitada.
 *  - Descontar el stock de la tabla `{prefix}mswc_stores_stock` cuando el pedido
 *    pasa a estado "Procesando", evitando dobles descuentos mediante un flag de
 *    idempotencia.
 *
 * Meta de pedido escrito:
 *  - `_mswc_store_dispatch_id` — ID (string) de la tienda desde la que se despacha.
 *  - `_mswc_stock_reduced`     — Flag '1' que evita que el stock se descuente dos veces.
 *
 * Hooks registrados:
 *  - `woocommerce_store_api_checkout_update_order_meta` → checkout en bloques.
 *  - `woocommerce_checkout_create_order`                → checkout clásico.
 *  - `woocommerce_payment_complete`                     → pago completado (p.ej. tarjeta).
 *  - `woocommerce_order_status_processing`              → cambio manual a "Procesando".
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Orders {

    /**
     * Registra todos los hooks de WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'woocommerce_store_api_checkout_update_order_meta', [ $this, 'mswc_attach_store_to_order' ], 10, 1 );
        add_action( 'woocommerce_checkout_create_order',                [ $this, 'mswc_attach_store_to_order' ], 10, 1 );
        add_action( 'woocommerce_payment_complete',                     [ $this, 'mswc_reduce_store_stock' ] );
        add_action( 'woocommerce_order_status_processing',              [ $this, 'mswc_reduce_store_stock' ] );
    }

    /**
     * Adjunta la tienda activa en sesión al meta del pedido recién creado.
     *
     * Realiza una última consulta a la DB para confirmar que la tienda sigue
     * habilitada en el instante exacto de crear el pedido. Cubre la ventana de
     * tiempo entre la validación del carrito y la creación del pedido: si la
     * tienda fue deshabilitada en ese intervalo, limpia la sesión y no adjunta
     * ninguna tienda al pedido.
     *
     * Se ejecuta tanto en el checkout clásico (`woocommerce_checkout_create_order`)
     * como en el checkout en bloques (`woocommerce_store_api_checkout_update_order_meta`).
     *
     * @since  1.0.0
     * @param  WC_Order $order Objeto del pedido que se está creando.
     * @global wpdb     $wpdb  Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_attach_store_to_order( WC_Order $order ): void {
        $store_id = WC()->session ? WC()->session->get( 'mswc_selected_store' ) : null;

        if ( ! $store_id ) {
            return;
        }

        global $wpdb;
        $store = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, enabled FROM {$wpdb->prefix}mswc_stores WHERE id = %d AND enabled = 1",
                (int) $store_id
            ),
            ARRAY_A
        );

        if ( ! $store ) {
            // La tienda fue deshabilitada entre la validación del carrito y la creación del pedido.
            WC()->session->set( 'mswc_selected_store', null );
            return;
        }

        $order->update_meta_data( '_mswc_store_dispatch_id', (string) $store_id );
        $order->save();
    }

    /**
     * Descuenta el stock de la tienda asignada al pedido cuando este se procesa.
     *
     * Se ejecuta tanto en `woocommerce_payment_complete` (pago online con tarjeta)
     * como en `woocommerce_order_status_processing` (métodos manuales como
     * transferencia bancaria). El meta `_mswc_stock_reduced` actúa como flag de
     * idempotencia para evitar dobles descuentos si ambos hooks se disparan sobre
     * el mismo pedido.
     *
     * El stock nunca baja de 0 gracias a la función SQL `GREATEST(0, stock - qty)`.
     *
     * @since  1.0.0
     * @param  int  $order_id ID del pedido que dispara el hook.
     * @global wpdb $wpdb     Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_reduce_store_stock( int $order_id ): void {
        global $wpdb;

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( $order->get_meta( '_mswc_stock_reduced' ) ) {
            return;
        }

        $store_id = $order->get_meta( '_mswc_store_dispatch_id' );
        if ( ! $store_id ) {
            return;
        }

        $table_stock = $wpdb->prefix . 'mswc_stores_stock';

        foreach ( $order->get_items() as $item ) {
            /** @var WC_Order_Item_Product $item */
            $product_id = $item->get_product_id();
            $qty_bought = $item->get_quantity();

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_stock}
                     SET stock = GREATEST(0, stock - %d)
                     WHERE product_id = %d AND store_id = %d",
                    $qty_bought,
                    $product_id,
                    (int) $store_id
                )
            );
        }

        $order->update_meta_data( '_mswc_stock_reduced', '1' );
        $order->save();
    }
}
