<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Aplica filtros de stock, precio y disponibilidad por tienda activa en sesión.
 *
 * Esta clase intercepta los filtros de WooCommerce para reemplazar los valores
 * globales del producto (stock, precio, disponibilidad) con los valores
 * específicos de la tienda seleccionada en sesión por el cliente.
 *
 * También bloquea el botón "Añadir al carrito" cuando la tienda activa no
 * tiene stock del producto, y personaliza las columnas de la lista de
 * productos en el administrador.
 *
 * Filtros y acciones registrados:
 *  - `woocommerce_product_get_stock_quantity`           → stock por tienda (simple).
 *  - `woocommerce_product_variation_get_stock_quantity` → stock por tienda (variación).
 *  - `woocommerce_product_is_in_stock`                  → bloquea "Añadir al carrito" sin stock.
 *  - `woocommerce_product_get_price`                    → precio por tienda.
 *  - `woocommerce_get_availability`                     → mensaje de disponibilidad por tienda.
 *  - `manage_edit-product_columns`                      → renombra columnas en lista admin.
 *  - `manage_product_posts_custom_column`               → renderiza columnas personalizadas.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Filters {

    /**
     * Caché estática de stock y precio por tienda para evitar consultas DB redundantes.
     * Obtiene ambos campos en una sola consulta por par producto/tienda.
     *
     * Clave: `"{product_id}_{store_id}"`, valor: array{stock:int|null, price:float|null} o null si no hay fila.
     *
     * @since 1.0.0
     * @var array<string, array{stock:int|null, price:float|null}|null>
     */
    private static array $store_data_cache = [];

    /**
     * Registra todos los hooks de WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_filter( 'woocommerce_product_get_stock_quantity',           [ $this, 'mswc_apply_stock_by_store' ],        100, 2 );
        add_filter( 'woocommerce_product_variation_get_stock_quantity', [ $this, 'mswc_apply_stock_by_store' ],        100, 2 );
        add_filter( 'woocommerce_product_is_in_stock',                  [ $this, 'mswc_filter_product_is_in_stock' ],  100, 2 );
        
        // Filtros de precio: aplican a todos los contextos (producto, carrito, etc.)
        add_filter( 'woocommerce_product_get_price',                    [ $this, 'mswc_apply_price_by_store' ],        100, 2 );
        add_filter( 'woocommerce_product_get_regular_price',            [ $this, 'mswc_apply_price_by_store' ],        100, 2 );
        add_filter( 'woocommerce_product_get_sale_price',               [ $this, 'mswc_apply_price_by_store' ],        100, 2 );
        add_filter( 'woocommerce_product_variation_get_price',          [ $this, 'mswc_apply_price_by_store' ],        100, 2 );
        add_filter( 'woocommerce_product_variation_get_regular_price',  [ $this, 'mswc_apply_price_by_store' ],        100, 2 );
        add_filter( 'woocommerce_product_variation_get_sale_price',     [ $this, 'mswc_apply_price_by_store' ],        100, 2 );
        
        add_filter( 'woocommerce_get_availability',                     [ $this, 'mswc_store_availability_by_store' ], 100, 2 );

        add_filter( 'manage_edit-product_columns', [ $this, 'mswc_rename_product_columns' ], 20 );

        add_action( 'manage_product_posts_custom_column', [ $this, 'mswc_start_buffer' ],          5 );
        add_action( 'manage_product_posts_custom_column', [ $this, 'mswc_end_buffer_and_render' ], 999, 2 );
    }

    /**
     * Obtiene stock y precio del producto para la tienda activa en una sola consulta, con caché.
     *
     * @since  1.0.0
     * @param  int   $product_id ID del producto (o variación).
     * @param  int   $store_id   ID de la tienda activa en sesión.
     * @return array{stock:int|null, price:float|null}|null  null si no hay fila en la tabla.
     */
    private function mswc_get_store_data( int $product_id, int $store_id ): ?array {
        $cache_key = "{$product_id}_{$store_id}";

        if ( array_key_exists( $cache_key, self::$store_data_cache ) ) {
            return self::$store_data_cache[ $cache_key ];
        }

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT stock, price FROM {$wpdb->prefix}mswc_stores_stock WHERE product_id = %d AND store_id = %d",
            $product_id,
            $store_id
        ), ARRAY_A );

        if ( $row === null ) {
            self::$store_data_cache[ $cache_key ] = null;
        } else {
            self::$store_data_cache[ $cache_key ] = [
                'stock' => (int) $row['stock'],
                'price' => ( $row['price'] !== null && (float) $row['price'] > 0 ) ? (float) $row['price'] : null,
            ];
        }

        return self::$store_data_cache[ $cache_key ];
    }

    /**
     * @since  1.0.0
     * @param  int      $product_id
     * @param  int      $store_id
     * @return int|null Stock disponible, o null si no hay registro en la tabla.
     */
    private function mswc_get_store_stock( int $product_id, int $store_id ): ?int {
        $data = $this->mswc_get_store_data( $product_id, $store_id );
        return $data !== null ? $data['stock'] : null;
    }

    /**
     * @since  1.0.0
     * @param  int        $product_id
     * @param  int        $store_id
     * @return float|null Precio configurado, o null si no hay registro o precio es 0.
     */
    private function mswc_get_store_price( int $product_id, int $store_id ): ?float {
        $data = $this->mswc_get_store_data( $product_id, $store_id );
        return $data !== null ? $data['price'] : null;
    }

    /**
     * Inicia el buffer de salida para interceptar las columnas administradas.
     *
     * Se engancha con prioridad 5 en `manage_product_posts_custom_column`
     * para capturar cualquier HTML que WooCommerce renderice antes de que
     * `mswc_end_buffer_and_render` lo descarte y lo reemplace.
     *
     * @since  1.0.0
     * @param  string $column Slug de la columna que se está renderizando.
     */
    public function mswc_start_buffer( string $column ): void {
        if ( in_array( $column, [ 'is_in_stock', 'price' ], true ) ) {
            ob_start();
        }
    }

    /**
     * Termina el buffer de salida y emite el HTML personalizado por tienda.
     *
     * Se ejecuta con prioridad 999 para garantizar que todos los demás hooks
     * de la columna ya han finalizado antes de descartar su salida y sustituirla
     * por los valores promediados desde `mswc_stores_stock`.
     *
     * @since  1.0.0
     * @param  string $column     Slug de la columna que se está renderizando.
     * @param  int    $product_id ID del producto de la fila actual.
     */
    public function mswc_end_buffer_and_render( string $column, int $product_id ): void {
        if ( ! in_array( $column, [ 'is_in_stock', 'price' ], true ) ) {
            return;
        }

        if ( ob_get_length() ) {
            ob_end_clean();
        }

        switch ( $column ) {
            case 'is_in_stock':
                echo $this->mswc_format_admin_stock_column( $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'price':
                echo $this->mswc_format_admin_price_column( $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
        }
    }

    /**
     * Renombra las columnas "Stock" y "Precio" de la lista de productos admin.
     *
     * Sustituye el encabezado predeterminado por una versión con etiqueta
     * traducida y un tooltip `(?)` que explica que los valores son promedios
     * por bodega.
     *
     * @since  1.0.0
     * @param  array<string, string> $columns Mapa de slug → etiqueta de columnas existentes.
     * @return array<string, string>          Mapa actualizado con los nuevos encabezados.
     */
    public function mswc_rename_product_columns( array $columns ): array {
        if ( isset( $columns['is_in_stock'] ) ) {
            $columns['is_in_stock'] = '<span>' . esc_html__( 'Stock', 'woocommerce-multi-store' ) . '</span>'
                . '<span class="mswc-helper" title="' . esc_attr__( 'Stock Promedio por Bodegas', 'woocommerce-multi-store' ) . '">(?)</span>';
        }
        if ( isset( $columns['price'] ) ) {
            $columns['price'] = '<span>' . esc_html__( 'Precio', 'woocommerce-multi-store' ) . '</span>'
                . '<span class="mswc-helper" title="' . esc_attr__( 'Precio Promedio por Bodegas', 'woocommerce-multi-store' ) . '">(?)</span>';
        }
        return $columns;
    }

    /**
     * Reemplaza la cantidad de stock global del producto por la de la tienda activa.
     *
     * Se aplica a productos simples (`woocommerce_product_get_stock_quantity`)
     * y a variaciones (`woocommerce_product_variation_get_stock_quantity`).
     *
     * Cuando no hay tienda seleccionada en sesión o no existe registro para el
     * par producto/tienda, devuelve el valor original de WooCommerce para no
     * interferir con el comportamiento por defecto fuera del contexto multi-store.
     *
     * @since  1.0.0
     * @param  float|int|null $quantity Cantidad de stock original del producto.
     * @param  WC_Product     $product  Objeto del producto o variación.
     * @return float|int                Stock de la tienda activa, o el valor original si no hay datos.
     */
    public function mswc_apply_stock_by_store( float|int|null $quantity, WC_Product $product ): float|int {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $quantity ?? 0;
        }
        if ( ! WC()->session ) {
            return $quantity ?? 0;
        }

        $store_id = WC()->session->get( 'mswc_selected_store' );
        if ( ! $store_id ) {
            return $quantity ?? 0;
        }

        $store_stock = $this->mswc_get_store_stock( $product->get_id(), (int) $store_id );

        // Si hay fila en la tabla, usar el stock de la tienda; si no, caer al stock original de WC.
        return $store_stock ?? ( $quantity ?? 0 );
    }

    /**
     * Devuelve false cuando la tienda activa no tiene stock del producto.
     *
     * Esto desactiva el botón "Añadir al carrito" en la página de producto y
     * en los archivos de tienda, usando el mismo mecanismo que WooCommerce
     * emplea para productos agotados.
     *
     * No actúa en el administrador (fuera de AJAX), cuando no hay sesión
     * activa o cuando no hay tienda seleccionada, para no interferir con
     * la gestión interna de productos.
     *
     * @since  1.0.0
     * @param  bool       $is_in_stock Estado de stock actual del producto.
     * @param  WC_Product $product     Objeto del producto evaluado.
     * @return bool                    false si la tienda activa no tiene stock; $is_in_stock en otro caso.
     */
    public function mswc_filter_product_is_in_stock( bool $is_in_stock, WC_Product $product ): bool {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $is_in_stock;
        }
        if ( ! WC()->session ) {
            return $is_in_stock;
        }

        $store_id = WC()->session->get( 'mswc_selected_store' );
        if ( ! $store_id ) {
            return $is_in_stock;
        }

        $store_stock = $this->mswc_get_store_stock( $product->get_id(), (int) $store_id );
        $store_price = $this->mswc_get_store_price( $product->get_id(), (int) $store_id );

        // Deshabilitado si: no hay fila en la tabla, stock agotado, o precio sin configurar.
        if ( $store_stock === null || $store_stock <= 0 || $store_price === null ) {
            return false;
        }

        return $is_in_stock;
    }

    /**
     * Reemplaza el precio global del producto por el de la tienda activa en sesión.
     *
     * Si la tienda no tiene precio asignado (null en la tabla), devuelve el
     * precio original del producto sin modificarlo.
     *
     * @since  1.0.0
     * @param  float|string $price   Precio original del producto.
     * @param  WC_Product   $product Objeto del producto.
     * @return float|string          Precio de la tienda activa, o el precio original si no hay dato.
     */
    public function mswc_apply_price_by_store( float|string $price, WC_Product $product ): float|string {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $price;
        }
        if ( ! WC()->session ) {
            return $price;
        }

        $store_id = WC()->session->get( 'mswc_selected_store' );
        if ( ! $store_id ) {
            return $price;
        }

        $store_price = $this->mswc_get_store_price( $product->get_id(), (int) $store_id );

        return $store_price !== null ? (string) $store_price : $price;
    }

    /**
     * Aplica el precio de la bodega al precio regular (reutiliza mswc_apply_price_by_store).
     *
     * El mismo método se utiliza para filtros de precio regular:
     *  - `woocommerce_product_get_regular_price`
     *  - `woocommerce_product_variation_get_regular_price`
     *
     * Esto garantiza que el precio regular coincida con el precio de venta cuando
     * hay un precio de bodega definido, evitando que WooCommerce interprete esto
     * como un descuento falso en el carrito.
     *
     * @since 1.0.0
     */

    /**
     * Personaliza el mensaje de disponibilidad del producto para la tienda activa.
     *
     * Sustituye el texto genérico de WooCommerce ("En stock" / "Agotado") por
     * mensajes específicos que indican la cantidad disponible en la bodega
     * seleccionada o que el producto está agotado en ella.
     *
     * @since  1.0.0
     * @param  array{availability: string, class: string} $availability Datos de disponibilidad originales.
     * @param  WC_Product                                 $product      Objeto del producto.
     * @return array{availability: string, class: string}               Datos de disponibilidad modificados.
     */
    public function mswc_store_availability_by_store( array $availability, WC_Product $product ): array {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $availability;
        }
        if ( ! WC()->session ) {
            return $availability;
        }

        $store_id = WC()->session->get( 'mswc_selected_store' );
        if ( ! $store_id ) {
            return $availability;
        }

        $store_stock = $this->mswc_get_store_stock( $product->get_id(), (int) $store_id );

        if ( $store_stock === null || $store_stock <= 0 ) {
            $availability['availability'] = esc_html__( 'Agotado en esta bodega', 'woocommerce-multi-store' );
            $availability['class']        = 'out-of-stock';
        } else {
            $availability['availability'] = sprintf(
                /* translators: %s: número de unidades disponibles */
                esc_html__( '%s unidades en bodega', 'woocommerce-multi-store' ),
                $store_stock
            );
            $availability['class'] = 'in-stock';
        }

        return $availability;
    }

    /**
     * Genera el HTML de la columna "Stock" en la lista de productos del admin.
     *
     * Muestra el stock promedio entre todas las bodegas junto con un enlace
     * "Ver detalle" que apunta al panel de Stores en el editor del producto.
     * Si no hay datos en `mswc_stores_stock`, muestra un aviso en rojo.
     *
     * @since  1.0.0
     * @param  int    $product_id ID del producto.
     * @global wpdb   $wpdb       Objeto global de acceso a la base de datos.
     * @return string             HTML de la celda de stock.
     */
    public function mswc_format_admin_stock_column( int $product_id ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mswc_stores_stock';

        $summary = $wpdb->get_row( $wpdb->prepare(
            "SELECT IFNULL(SUM(stock), 0) AS total, IFNULL(AVG(stock), 0) AS promedio
             FROM {$table_name}
             WHERE product_id = %d",
            $product_id
        ) );

        if ( ! $summary || $summary->total === null ) {
            return '<mark class="instock" style="background:none; color:red;">'
                . esc_html__( 'Sin datos en bodegas', 'woocommerce-multi-store' )
                . '</mark>';
        }

        $total      = (int) $summary->total;
        $average    = number_format( (float) $summary->promedio, 2 );
        $css_class  = ( $total > 0 ) ? 'mswc_instock' : 'mswc_outofstock';
        $detail_url = esc_url( get_edit_post_link( $product_id ) . '#mswc_stores_product_data' );

        return '<div class="mswc-admin-price-wrapper">'
            . '<span class="mswc-stock ' . esc_attr( $css_class ) . '">' . esc_html( $average ) . '</span><br>'
            . '<a href="' . $detail_url . '" class="mswc-view-details-link">'
            . esc_html__( 'Ver detalle', 'woocommerce-multi-store' )
            . '</a></div>';
    }

    /**
     * Genera el HTML de la columna "Precio" en la lista de productos del admin.
     *
     * Muestra el precio promedio entre todas las bodegas con precio mayor a 0,
     * formateado con `wc_price()`, y un enlace "Ver detalle" al panel de Stores
     * del producto. Devuelve cadena vacía si no hay precios registrados.
     *
     * @since  1.0.0
     * @param  int    $product_id ID del producto.
     * @global wpdb   $wpdb       Objeto global de acceso a la base de datos.
     * @return string             HTML de la celda de precio, o cadena vacía.
     */
    public function mswc_format_admin_price_column( int $product_id ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mswc_stores_stock';

        $avg_price = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(price) FROM {$table_name} WHERE product_id = %d AND price > 0",
            $product_id
        ) );

        if ( $avg_price === null || (float) $avg_price === 0.0 ) {
            return '';
        }

        $formatted_price = wc_price( $avg_price );
        $detail_url      = esc_url( get_edit_post_link( $product_id ) . '#mswc_stores_product_data' );

        return '<div class="mswc-admin-price-wrapper">'
            . $formatted_price . '<br>'
            . '<a href="' . $detail_url . '" class="mswc-view-details-link">'
            . esc_html__( 'Ver detalle', 'woocommerce-multi-store' )
            . '</a></div>';
    }
}
