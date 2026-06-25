<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Tabla de listado de tiendas para la página de gestión del admin.
 *
 * Extiende `WP_List_Table` para mostrar el catálogo de tiendas/bodegas con
 * soporte para búsqueda, ordenamiento, paginación y acciones masivas.
 *
 * Columnas:
 *  - Checkbox de selección masiva.
 *  - Nombre (enlace a editar + row actions: editar / eliminar).
 *  - Código (identificador único, inmutable).
 *  - Estado (badge Activa/Inactiva + enlace de toggle rápido).
 *  - Productos (número de productos con stock asignado a esa tienda).
 *
 * Acciones masivas disponibles: habilitar, deshabilitar, eliminar.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Stores_List_Table extends WP_List_Table {

    /**
     * Inicializa la tabla con la configuración singular/plural.
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'store',
            'plural'   => 'stores',
            'ajax'     => false,
        ] );
    }

    /**
     * Define las columnas visibles de la tabla.
     *
     * @since  1.0.0
     * @return array<string, string> Mapa de slug de columna → etiqueta HTML.
     */
    public function get_columns(): array {
        return [
            'cb'       => '<input type="checkbox">',
            'name'     => esc_html__( 'Nombre',    'woocommerce-multi-store' ),
            'code'     => esc_html__( 'Código',    'woocommerce-multi-store' ),
            'status'   => esc_html__( 'Estado',    'woocommerce-multi-store' ),
            'products' => esc_html__( 'Productos', 'woocommerce-multi-store' ),
        ];
    }

    /**
     * Define qué columnas pueden ordenarse y su dirección predeterminada.
     *
     * El valor del array es `[ campo_db, orden_desc_por_defecto ]`.
     * `name` se ordena ascendente por defecto (segundo elemento `true` indica
     * que el orden inicial al hacer clic es ASC); `code` también.
     *
     * @since  1.0.0
     * @return array<string, array{0: string, 1: bool}> Mapa de columna → [campo, desc_por_defecto].
     */
    protected function get_sortable_columns(): array {
        return [
            'name' => [ 'name', true ],
            'code' => [ 'code', false ],
        ];
    }

    /**
     * Define las acciones masivas disponibles en los selectores superior e inferior.
     *
     * @since  1.0.0
     * @return array<string, string> Mapa de acción → etiqueta traducida.
     */
    protected function get_bulk_actions(): array {
        return [
            'enable'  => esc_html__( 'Habilitar',    'woocommerce-multi-store' ),
            'disable' => esc_html__( 'Deshabilitar', 'woocommerce-multi-store' ),
            'delete'  => esc_html__( 'Eliminar',     'woocommerce-multi-store' ),
        ];
    }

    /**
     * Renderizador predeterminado para columnas sin método propio.
     *
     * Escapa el valor y lo devuelve como cadena de texto plano.
     *
     * @since  1.0.0
     * @param  array<string, mixed> $item        Fila actual como array asociativo.
     * @param  string               $column_name Slug de la columna a renderizar.
     * @return string                            HTML escapado de la celda.
     */
    protected function column_default( $item, $column_name ): string {
        return esc_html( $item[ $column_name ] ?? '' );
    }

    /**
     * Renderiza la columna de checkbox para acciones masivas.
     *
     * @since  1.0.0
     * @param  array<string, mixed> $item Fila actual como array asociativo.
     * @return string                     HTML del input checkbox.
     */
    protected function column_cb( $item ): string {
        return sprintf(
            '<input type="checkbox" name="store_ids[]" value="%d">',
            absint( $item['id'] )
        );
    }

    /**
     * Renderiza la columna "Nombre" con enlace a edición y row actions.
     *
     * Row actions disponibles:
     *  - Editar   → `admin.php?page=mswc-stores&action=edit&id={id}`
     *  - Eliminar → `admin-post.php?action=mswc_delete_store&id={id}` (con nonce)
     *
     * El enlace de eliminación lleva la clase `mswc-confirm-delete` y el
     * atributo `data-name` para que `admin.js` muestre un `confirm()` antes
     * de navegar.
     *
     * @since  1.0.0
     * @param  array<string, mixed> $item Fila actual como array asociativo.
     * @return string                     HTML de la celda con enlace y row actions.
     */
    protected function column_name( $item ): string {
        $store_id = absint( $item['id'] );

        $edit_url = add_query_arg(
            [ 'page' => 'mswc-stores', 'action' => 'edit', 'id' => $store_id ],
            admin_url( 'admin.php' )
        );
        $delete_url = wp_nonce_url(
            add_query_arg(
                [ 'action' => 'mswc_delete_store', 'id' => $store_id ],
                admin_url( 'admin-post.php' )
            ),
            'mswc_delete_store_' . $store_id
        );

        $actions = [
            'edit'   => '<a href="' . esc_url( $edit_url ) . '">'
                      . esc_html__( 'Editar', 'woocommerce-multi-store' ) . '</a>',
            'delete' => '<a href="' . esc_url( $delete_url ) . '" class="mswc-confirm-delete"'
                      . ' data-name="' . esc_attr( $item['name'] ) . '">'
                      . esc_html__( 'Eliminar', 'woocommerce-multi-store' ) . '</a>',
        ];

        return '<strong><a href="' . esc_url( $edit_url ) . '">'
            . esc_html( $item['name'] ) . '</a></strong>'
            . $this->row_actions( $actions );
    }

    /**
     * Renderiza la columna "Estado" con badge y enlace de toggle rápido.
     *
     * Muestra un badge visual (Activa/Inactiva) y un enlace que alterna el
     * estado sin pasar por el formulario de edición. El enlace apunta a
     * `admin-post.php?action=mswc_toggle_store` con nonce por tienda para
     * prevenir CSRF.
     *
     * @since  1.0.0
     * @param  array<string, mixed> $item Fila actual como array asociativo.
     * @return string                     HTML del badge y el enlace de toggle.
     */
    protected function column_status( $item ): string {
        $store_id = absint( $item['id'] );
        $enabled  = (bool) $item['enabled'];

        if ( $enabled ) {
            $toggle_url = wp_nonce_url(
                add_query_arg(
                    [ 'action' => 'mswc_toggle_store', 'id' => $store_id, 'enabled' => '0' ],
                    admin_url( 'admin-post.php' )
                ),
                'mswc_toggle_store_' . $store_id
            );
            return '<span class="mswc-badge mswc-badge--active">'
                . esc_html__( 'Activa', 'woocommerce-multi-store' ) . '</span> '
                . '<a href="' . esc_url( $toggle_url ) . '" class="mswc-toggle-link">'
                . esc_html__( 'Deshabilitar', 'woocommerce-multi-store' ) . '</a>';
        }

        $toggle_url = wp_nonce_url(
            add_query_arg(
                [ 'action' => 'mswc_toggle_store', 'id' => $store_id, 'enabled' => '1' ],
                admin_url( 'admin-post.php' )
            ),
            'mswc_toggle_store_' . $store_id
        );
        return '<span class="mswc-badge mswc-badge--inactive">'
            . esc_html__( 'Inactiva', 'woocommerce-multi-store' ) . '</span> '
            . '<a href="' . esc_url( $toggle_url ) . '" class="mswc-toggle-link">'
            . esc_html__( 'Habilitar', 'woocommerce-multi-store' ) . '</a>';
    }

    /**
     * Renderiza la columna "Productos" con el número de productos que tienen
     * stock asignado a esta tienda en `{prefix}mswc_stores_stock`.
     *
     * @since  1.0.0
     * @param  array<string, mixed> $item Fila actual como array asociativo.
     * @global wpdb                 $wpdb Objeto global de acceso a la base de datos de WordPress.
     * @return string                     Número de productos como cadena escapada.
     */
    protected function column_products( $item ): string {
        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT product_id) FROM {$wpdb->prefix}mswc_stores_stock WHERE store_id = %d",
            absint( $item['id'] )
        ) );
        return esc_html( "$count" );
    }

    /**
     * Obtiene y pagina los registros de tiendas desde la base de datos.
     *
     * Soporta:
     *  - Búsqueda por nombre o código (`$_GET['s']`).
     *  - Ordenamiento por `name`, `code` o `enabled` (`$_GET['orderby']`, `$_GET['order']`).
     *  - Paginación (20 registros por página).
     *
     * El campo `orderby` se valida contra una lista blanca para evitar inyección SQL,
     * ya que `$wpdb->prepare()` no acepta nombres de columna como placeholders.
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    public function prepare_items(): void {
        global $wpdb;

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $table        = $wpdb->prefix . 'mswc_stores';

        $allowed_orderby = [ 'name', 'code', 'enabled' ];
        $orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true )
            ? sanitize_key( $_GET['orderby'] )
            : 'name';
        $order = isset( $_GET['order'] ) && 'desc' === $_GET['order'] ? 'DESC' : 'ASC';

        $search = ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        if ( $search ) {
            $like  = '%' . $wpdb->esc_like( $search ) . '%';
            $total = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE name LIKE %s OR code LIKE %s", $like, $like )
            );
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE name LIKE %s OR code LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                    $like, $like, $per_page, ( $current_page - 1 ) * $per_page
                ),
                ARRAY_A
            );
        } else {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                    $per_page, ( $current_page - 1 ) * $per_page
                ),
                ARRAY_A
            );
        }

        $this->set_pagination_args( [ 'total_items' => $total, 'per_page' => $per_page ] );
        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
        $this->items = $items ?: [];
    }
}
