<?php
//require_once(dirname(__FILE__) ."woocommerce-multi-store.php");
class mswc_Admin
{
    public function __construct()
    {

        add_action('woocommerce_product_data_tabs', array($this, 'mswc_add_stores_product_tab'), 10, 2);
        add_action('woocommerce_product_data_panels', array($this, 'mswc_render_stores_product_panel'));
        add_action('woocommerce_admin_process_product_object', array($this, 'save_store_stock_fields'));
        add_action('admin_enqueue_scripts', array($this, 'mswc_enqueue_admin_styles'));
        add_action('admin_footer', array($this, 'active_tab_stores_product_data'));




    }



    function mswc_add_stores_product_tab($tabs)
    {
        $tabs['mswc_stores_tab'] = array(
            'label' => __('Stores', 'mswc'),
            'target' => 'mswc_stores_product_data', // ID del contenedor HTML que crearemos abajo
            'class' => array('show_if_simple', 'show_if_variable'), // Visibilidad según tipo de producto
            'priority' => 20, // Aparece justo debajo de "Inventario" (que tiene prioridad 70)
        );
        return $tabs;
    }

    public function mswc_render_stores_product_panel()
    {
        global $product_object, $wpdb; // 

        $table_stores = $wpdb->prefix . 'mswc_stores';
        $table_stock = $wpdb->prefix . 'mswc_stores_stock';

        $query = $wpdb->prepare(
            "SELECT s.id, s.code, s.name, st.stock, st.price 
         FROM $table_stores s
         LEFT JOIN $table_stock st ON s.id = st.store_id AND st.product_id = %d
         ORDER BY s.name ASC",
            $product_object->get_id()
        );

        $stores = $wpdb->get_results($query, ARRAY_A);

        echo '<div id="mswc_stores_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<div class="options_group">';

        if (!empty($stores)) {
            echo '<table class="widefat fixed striped" style="margin:20px; width:95%;">';
            echo '<thead><tr><th></th><th>Stock</th><th>Precio</th></tr></thead><tbody>';

            foreach ($stores as $store) {
                echo '<tr>';
                echo '<td>' . esc_html($store['name']) . '</td>';
                echo '<td>';
                woocommerce_wp_text_input(array(
                    'id' => '_stock_store_' . $store['code'],
                    'label' => '',
                    'type' => 'text',
                    'class' => '',
                    'wrapper_class' => 'mswc_stock_input',
                    'value' => $store["stock"], // [cite: 645]
                ));
                echo '</td><td>';
                woocommerce_wp_text_input(array(
                    'id' => '_price_store_' . $store['code'],
                    'label' => '',
                    "class" => 'mswc_price_input',
                    'type' => 'text',
                    'value' => $store["price"], // [cite: 645]
                ));
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';
    }


    public function save_store_stock_fields($product)
    {
        global $wpdb;
        $stores = MSWC_Plugin::get_active_stores();


        $table_name = $wpdb->prefix . 'mswc_stores_stock';

        foreach ($stores as $store) {

            $field_stock = '_stock_store_' . $store['code'];
            $field_price = '_price_store_' . $store['code'];

            $store_id = $store['id'];

            // Recuperar valores del formulario (POST)
            $stock_val = isset($_POST[$field_stock]) ? intval($_POST[$field_stock]) : 0;
            $price_val = isset($_POST[$field_price]) ? wc_format_decimal($_POST[$field_price]) : 0;

            $wpdb->update(
                $table_name,
                array('stock' => $stock_val, 'price' => $price_val),
                array('product_id' => $product->get_id(), 'store_id' => $store_id),
                array('%d', '%f'),
                array('%d', '%d')
            );

        }

    }

    public function mswc_enqueue_admin_styles($hook)
    {
    

        wp_enqueue_style(
            'wmh-admin-css',
            plugins_url('/assets/css/admin-style.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );

    }


    public function active_tab_stores_product_data()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var hash = window.location.hash;
                if (hash) {
                    // Buscamos el tab que coincida con el hash (ej: #mswc_tab_bodegas)
                    // WooCommerce usa clases como .mswc_tab_bodegas_tab para los botones del menú
                    var tabClass = hash.replace('#', '') + '';

                    if (jQuery('.product_data_tabs .mswc_stores_tab_tab').length) {
                        // Simulamos el clic en la pestaña
                        jQuery('.product_data_tabs .mswc_stores_tab_tab a').click();

                        // Scroll suave hacia el panel de datos del producto
                        jQuery('html, body').animate({
                            scrollTop: jQuery("#woocommerce-product-data").offset().top - 50
                        }, 500);
                    }
                }
                //jQuery('td.is_in_stock.column-is_in_stock').html("");
                //jQuery('td.price.column-price > span').html("");
            });
        </script>
        <?php
    }


}