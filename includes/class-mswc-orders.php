<?php
class mswc_Orders
{
    public function __construct()
    {
        add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'attach_store_to_order'), 10, 2);
        add_action('woocommerce_checkout_create_order', array($this, 'attach_store_to_order'), 10, 2);
        // Para cubrir también métodos de pago manuales (BACS, Cheque) que pasan a "Procesando"
        add_action('woocommerce_payment_complete', array($this, 'reduce_warehouse_stock'));

        add_action('woocommerce_order_status_processing', array($this, 'reduce_warehouse_stock'));
    }

    public function attach_store_to_order($order)
    {
        $store = WC()->session->get('mswc_selected_store');

        if ($store) {
            $order->update_meta_data('_mswc_store_dispatch_id', $store);
            $order->save();
        }
    }

    public function reduce_warehouse_stock($order_id)
    {
        global $wpdb;
        $table_stock = $wpdb->prefix . 'mswc_stores_stock';

        // 1. Obtener el objeto del pedido (Compatible con HPOS)
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // 2. Recuperar el ID de la bodega desde los metadatos del pedido [cite: 1293, 1311]
        $store_id = $order->get_meta('_mswc_store_dispatch_id');
        if (!$store_id)
            return;

        // 3. Recorrer los productos comprados
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $qty_bought = $item->get_quantity();

            // 4. Actualizar la tabla personalizada restando la cantidad [cite: 1828, 1841]
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_stock 
             SET stock = stock - %d 
             WHERE product_id = %d AND store_id = %d",
                $qty_bought,
                $product_id,
                $store_id
            ));

  
        }
        

    }
}