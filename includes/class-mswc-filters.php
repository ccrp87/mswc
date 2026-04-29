<?php
class mswc_Filters
{
    public function __construct()
    {
        // FRONTEND: Aplicar stock y precio según la bodega seleccionada en sesión
        add_filter('woocommerce_product_get_stock_quantity', array($this, 'apply_stock_by_store'), 100, 2);
        add_filter('woocommerce_product_variation_get_stock_quantity', array($this, 'apply_stock_by_store'), 100, 2);
        add_filter('woocommerce_product_get_price', array($this, 'apply_price_by_store'), 100, 2);
        add_filter('woocommerce_get_availability', array($this, 'store_availability_by_store'), 100, 2);

        //Muestra el promedio de stock y precio en las columnas del admin de productos
        //add_filter('manage_product_posts_custom_column', array($this, 'mswc_render_custom_column'), 99, 2);
        // add_filter('woocommerce_admin_stock_html', array($this, 'mswc_format_admin_stock_column'), 10, 2);
        add_filter('manage_edit-product_columns', array($this, 'mswc_rename_product_columns'), 20);

        /**
         * 1. Abrimos el buffer muy temprano (Prioridad 5)
         */
        add_action('manage_product_posts_custom_column', [$this, 'mswc_start_buffer'], 5);

        /**
         * 2. Cerramos, limpiamos y pintamos (Prioridad 999)
         */
        add_action('manage_product_posts_custom_column', [$this, 'mswc_end_buffer_and_render'], 999, 2);


    }
    public function mswc_start_buffer($column)
    {
        if (in_array($column, ['is_in_stock', 'price'])) {
            ob_start();
        }
    }

    public function mswc_end_buffer_and_render($column, $product_id)
    {
        if (!in_array($column, ['is_in_stock', 'price'])) {
            return;
        }

        // BORRAMOS el contenido de WooCommerce
        if (ob_get_length()) {
            ob_end_clean();
        }

        // PINTAMOS nuestro contenido
        switch ($column) {
            case 'is_in_stock':
                echo $this->mswc_format_admin_stock_column($product_id);
                break;
            case 'price':
                echo $this->mswc_format_admin_price_column($product_id);
                break;
        }
    }
    public function mswc_rename_product_columns($columns)
    {
        // 1. Cambiar el título de la columna de Stock
        if (isset($columns['is_in_stock'])) {
            $columns['is_in_stock'] = '<span>Stock</span><span class="mswc-helper" title="Stock Promedio por Bodegas">(?)</span>';
        }

        // 2. Cambiar el título de la columna de Precio
        if (isset($columns['price'])) {
            $columns['price'] = '<span>Precio</span><span class="mswc-helper" title="Precio Promedio por Bodegas">(?)</span>';
        }

        return $columns;
    }

    public function mswc_render_custom_column($column, $post_id)
    {
        switch ($column) {
            case 'price':
                echo $this->mswc_format_admin_price_column($post_id);
                break;
            case 'is_in_stock':
                echo $this->mswc_format_admin_stock_column($post_id);
                break;
            default:
                # code...
                break;
        }

    }

    /**
     * Lógica para FRONTEND: Filtra el stock basado en la bodega de la sesión
     */
    public function apply_stock_by_store($quantity, $product)
    {
        // En el admin, queremos ver el total o el stock base, no filtrar por sesión
        if (is_admin() && !wp_doing_ajax())
            return $quantity;

        if (!WC()->session)
            return $quantity;

        $store_id = WC()->session->get('mswc_selected_store');
        if (!$store_id)
            return $quantity;

        global $wpdb;
        $stock_especifico = $wpdb->get_var($wpdb->prepare(
            "SELECT stock FROM {$wpdb->prefix}mswc_stores_stock WHERE product_id = %d AND store_id = %d",
            $product->get_id(),
            $store_id
        ));

        return (!is_null($stock_especifico)) ? (int) $stock_especifico : $quantity;
    }

    /**
     * Lógica para FRONTEND: Filtra el precio basado en la bodega de la sesión
     */
    public function apply_price_by_store($price, $product)
    {
        if (is_admin() && !wp_doing_ajax())
            return $price;
        if (!WC()->session)
            return $price;

        $store_id = WC()->session->get('mswc_selected_store');
        if (!$store_id)
            return $price;

        global $wpdb;
        $precio_especifico = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM {$wpdb->prefix}mswc_stores_stock WHERE product_id = %d AND store_id = %d",
            $product->get_id(),
            $store_id
        ));

        return (!is_null($precio_especifico)) ? $precio_especifico : $price;
    }

    public function store_availability_by_store($availability, $product)
    {
        // Solo aplicar en frontend
        if (is_admin() && !wp_doing_ajax())
            return $availability;

        $stock = $this->apply_stock_by_store($product->get_stock_quantity(), $product);

        if ($stock <= 0) {
            $availability['availability'] = __('Agotado en esta bodega', 'wmh');
            $availability['class'] = 'out-of-stock';
        } else {
            $availability['availability'] = sprintf(__('%s unidades en bodega', 'wmh'), $stock);
            $availability['class'] = 'in-stock';
        }
        return $availability;
    }

    public function mswc_format_admin_stock_column($product_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mswc_stores_stock';

        // 1. Obtenemos los datos consolidados de tu tabla
        $resumen = $wpdb->get_row($wpdb->prepare(
            "SELECT IFNULL(SUM(stock), 0) as total, IFNULL(AVG(stock), 0) as promedio FROM $table_name WHERE product_id = %d",
            $product_id
        ));
        $total_real = (isset($resumen->total)) ? (int) $resumen->total : 0;        // 2. Si no hay datos en tus bodegas, mostramos un mensaje de advertencia
        error_log("Datos de la bodega: " . $resumen->promedio . "");

        if (is_null($resumen->total)) {
            return '<mark class="instock" style="background:none; color:red;">Sin datos en bodegas</mark>';
        }


        // 3. Construimos el nuevo HTML
        $total = (int) $resumen->total;
        $promedio = number_format($resumen->promedio, 2);
        $clase = ($total > 0) ? 'mswc_instock' : 'mswc_outofstock';

        // El enlace al detalle que pediste
        $url_detalle = get_edit_post_link($product_id) . '#mswc_stores_product_data';

        $nuevo_html = '<div class="mswc-admin-price-wrapper">';
        $nuevo_html .= '<span class="mswc-stock ' . $clase . '">';
        $nuevo_html .=  $promedio ;
        $nuevo_html .= '</span><br>';
        $nuevo_html .= '<a href="' . $url_detalle . '" class="mswc-view-details-link">Ver detalle</a>';
        $nuevo_html .= '</div>';

        return $nuevo_html;
    }

    public function mswc_format_admin_price_column($product_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mswc_stores_stock';

        // 1. Obtener el promedio de precios de la tabla personalizada (solo precios > 0)
        $promedio_precio = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(price) FROM $table_name WHERE product_id = %d AND price > 0",
            $product_id
        ));

        // 2. Si no hay datos, devolvemos el precio original de WC
        if (is_null($promedio_precio) || $promedio_precio == 0) {
            return 0;
        }

        // 3. Formatear el promedio y el enlace
        $precio_formateado = wc_price($promedio_precio);
        $tab_id = 'mswc_stores_product_data'; // El ID que definiste para tu pestaña
        $url_detalle = get_edit_post_link($product_id) . '#' . $tab_id;

        // 4. Construir el HTML
        $nuevo_html = '<div class="mswc-admin-price-wrapper">';
        $nuevo_html .=  $precio_formateado . '<br>';
        $nuevo_html .= '<a href="' . $url_detalle . '" class="mswc-view-details-link">Ver detalle</a>';
        $nuevo_html .= '</div>';

        return $nuevo_html;
    }

}