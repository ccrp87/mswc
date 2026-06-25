<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestiona la interfaz de administración para crear, editar y eliminar tiendas.
 *
 * Registra un submenú bajo "WooCommerce" con una página de gestión completa
 * que incluye:
 *  - Listado paginado con búsqueda y ordenamiento (via `MSWC_Stores_List_Table`).
 *  - Formulario de creación y edición de tiendas.
 *  - Acciones individuales: editar, eliminar, toggle de estado activo/inactivo.
 *  - Acciones masivas: habilitar, deshabilitar, eliminar.
 *
 * Todas las acciones de escritura siguen el patrón PRG (Post/Redirect/Get)
 * usando `admin-post.php`, lo que evita reenvíos accidentales al recargar
 * la página tras un POST y mantiene la URL limpia.
 *
 * Hooks registrados:
 *  - `admin_menu`                   → registra el submenú y captura el hook suffix.
 *  - `admin_print_scripts-{$hook}`  → encola CSS/JS exclusivamente en esta página.
 *  - `admin_post_mswc_save_store`   → guarda (crea o actualiza) una tienda.
 *  - `admin_post_mswc_delete_store` → elimina una tienda y su stock asociado.
 *  - `admin_post_mswc_toggle_store` → alterna el estado habilitado/deshabilitado.
 *  - `admin_post_mswc_bulk_stores`  → aplica acciones masivas sobre tiendas seleccionadas.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Admin_Stores {

    /**
     * Registra todos los hooks de WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'mswc_register_menu' ] );

        // Acciones individuales via admin-post.php (PRG pattern).
        add_action( 'admin_post_mswc_save_store',   [ $this, 'mswc_handle_save_store' ] );
        add_action( 'admin_post_mswc_delete_store', [ $this, 'mswc_handle_delete_store' ] );
        add_action( 'admin_post_mswc_toggle_store', [ $this, 'mswc_handle_toggle_store' ] );

        // Acción masiva (POST desde la misma página).
        add_action( 'admin_post_mswc_bulk_stores',  [ $this, 'mswc_handle_bulk_action' ] );
    }

    // -------------------------------------------------------------------------
    // Menú
    // -------------------------------------------------------------------------

    /**
     * Registra el submenú "Multi-Store" bajo "WooCommerce" y enlaza los assets.
     *
     * Captura el hook suffix devuelto por `add_submenu_page()` y lo usa para
     * registrar `mswc_enqueue_page_assets` únicamente en la pantalla de gestión de
     * stores, evitando cargar CSS/JS en el resto del panel de administración.
     * Esto es más preciso que usar `admin_enqueue_scripts` con una comprobación
     * de `get_current_screen()`.
     *
     * @since 1.0.0
     */
    public function mswc_register_menu(): void {
        $hook = add_submenu_page(
            'woocommerce',
            esc_html__( 'Gestión de Stores', 'woocommerce-multi-store' ),
            esc_html__( 'Multi-Store', 'woocommerce-multi-store' ),
            'manage_woocommerce',
            'mswc-stores',
            [ $this, 'mswc_render_page' ]
        );

        // Encolar assets solo en esta página concreta.
        add_action( "admin_print_scripts-{$hook}", [ $this, 'mswc_enqueue_page_assets' ] );
    }

    /**
     * Encola CSS y JS específicos de la página de gestión de stores.
     *
     * Se enlaza a `admin_print_scripts-{hook}`, que solo se dispara en la
     * pantalla concreta de este submenú. Encola los mismos assets que
     * `MSWC_Admin` para la pantalla de producto: `mswc-admin-css` y
     * `mswc-admin-js` (con jQuery como dependencia).
     *
     * @since 1.0.0
     */
    public function mswc_enqueue_page_assets(): void {
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

    // -------------------------------------------------------------------------
    // Router principal
    // -------------------------------------------------------------------------

    /**
     * Enrutador principal de la página de gestión de stores.
     *
     * Lee el parámetro `action` de la URL y delega el renderizado a la vista
     * correspondiente: formulario de creación (`add`), formulario de edición
     * (`edit`) o listado (cualquier otro valor o ausencia del parámetro).
     *
     * @since 1.0.0
     */
    public function mswc_render_page(): void {
        $view = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

        switch ( $view ) {
            case 'add':
            case 'edit':
                $this->mswc_render_form( $view );
                break;
            default:
                $this->mswc_render_list();
        }
    }

    // -------------------------------------------------------------------------
    // Vista: listado
    // -------------------------------------------------------------------------

    /**
     * Renderiza la vista de listado de tiendas con búsqueda y acciones masivas.
     *
     * Instancia `MSWC_Stores_List_Table`, llama a `prepare_items()` y renderiza
     * la tabla dentro de un formulario POST que apunta a `admin-post.php` para
     * manejar las acciones masivas. El formulario de búsqueda usa GET para ser
     * compatible con el sistema de paginación de `WP_List_Table`, que espera
     * los parámetros `paged` y `s` en la query string.
     *
     * @since 1.0.0
     */
    private function mswc_render_list(): void {
        require_once MSWC_PLUGIN_DIR . 'includes/class-mswc-stores-list-table.php';

        $table = new MSWC_Stores_List_Table();
        $table->prepare_items();

        $add_url      = add_query_arg( [ 'page' => 'mswc-stores', 'action' => 'add' ], admin_url( 'admin.php' ) );
        $bulk_post_url = add_query_arg( 'page', 'mswc-stores', admin_url( 'admin.php' ) );
        ?>
        <div class="wrap mswc-stores-wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Gestión de Stores', 'woocommerce-multi-store' ); ?>
            </h1>
            <a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
                <?php esc_html_e( 'Añadir nueva', 'woocommerce-multi-store' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php $this->mswc_render_notices(); ?>

            <form method="get">
                <input type="hidden" name="page" value="mswc-stores">
                <?php $table->search_box( esc_html__( 'Buscar stores', 'woocommerce-multi-store' ), 'mswc-store' ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'mswc_bulk_stores', 'mswc_bulk_nonce' ); ?>
                <input type="hidden" name="action" value="mswc_bulk_stores">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Vista: formulario crear / editar
    // -------------------------------------------------------------------------

    /**
     * Renderiza el formulario de creación o edición de una tienda.
     *
     * En modo edición (`$view === 'edit'`), carga los datos actuales desde la
     * base de datos. Si el ID solicitado no existe, redirige al listado con el
     * aviso `not_found`. El campo `code` se muestra como solo lectura en edición
     * porque es inmutable: cambiarlo invalidaría los meta de pedido y los campos
     * de stock de productos que ya lo referencian.
     *
     * En modo creación (`$view === 'add'`), el formulario se muestra vacío y el
     * campo `code` acepta entrada libre sujeta al patrón `[a-z0-9_\-]+`.
     *
     * @since  1.0.0
     * @param  string $view `'add'` para crear una nueva tienda; `'edit'` para editar una existente.
     * @global wpdb   $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    private function mswc_render_form( string $view ): void {
        $store    = [ 'id' => 0, 'code' => '', 'name' => '', 'enabled' => 1 ];
        $is_edit  = ( 'edit' === $view );

        if ( $is_edit && ! empty( $_GET['id'] ) ) {
            global $wpdb;
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}mswc_stores WHERE id = %d",
                    absint( $_GET['id'] )
                ),
                ARRAY_A
            );
            if ( $row ) {
                $store = $row;
            } else {
                wp_redirect( add_query_arg( 'mswc_notice', 'not_found', admin_url( 'admin.php?page=mswc-stores' ) ) );
                exit;
            }
        }

        $list_url = add_query_arg( 'page', 'mswc-stores', admin_url( 'admin.php' ) );
        $title    = $is_edit
            ? esc_html__( 'Editar Store', 'woocommerce-multi-store' )
            : esc_html__( 'Añadir Store', 'woocommerce-multi-store' );
        ?>
        <div class="wrap mswc-stores-wrap">
            <h1><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
            <a href="<?php echo esc_url( $list_url ); ?>" class="mswc-back-link">
                &larr; <?php esc_html_e( 'Volver al listado', 'woocommerce-multi-store' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php $this->mswc_render_notices(); ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                  class="mswc-store-form">
                <?php wp_nonce_field( 'mswc_save_store', 'mswc_store_nonce' ); ?>
                <input type="hidden" name="action"   value="mswc_save_store">
                <input type="hidden" name="store_id" value="<?php echo absint( $store['id'] ); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="store_name">
                                <?php esc_html_e( 'Nombre', 'woocommerce-multi-store' ); ?>
                                <span class="mswc-required">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="store_name" name="store_name" class="regular-text"
                                value="<?php echo esc_attr( $store['name'] ); ?>" maxlength="100" required>
                            <p class="description">
                                <?php esc_html_e( 'Nombre descriptivo de la tienda o bodega.', 'woocommerce-multi-store' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="store_code">
                                <?php esc_html_e( 'Código', 'woocommerce-multi-store' ); ?>
                                <?php if ( ! $is_edit ) : ?>
                                    <span class="mswc-required">*</span>
                                <?php endif; ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="store_code" name="store_code"
                                class="regular-text <?php echo $is_edit ? 'mswc-readonly-field' : ''; ?>"
                                value="<?php echo esc_attr( $store['code'] ); ?>"
                                maxlength="50"
                                pattern="[a-z0-9_\-]+"
                                title="<?php esc_attr_e( 'Solo letras minúsculas, números, guiones y guiones bajos.', 'woocommerce-multi-store' ); ?>"
                                <?php echo $is_edit ? 'readonly' : 'required'; ?>>
                            <p class="description">
                                <?php if ( $is_edit ) : ?>
                                    <?php esc_html_e( 'El código no puede modificarse una vez creada la store.', 'woocommerce-multi-store' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Identificador único: solo minúsculas, números, - y _. Ej: bodega-norte', 'woocommerce-multi-store' ); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Estado', 'woocommerce-multi-store' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="store_enabled" value="1"
                                    <?php checked( 1, (int) $store['enabled'] ); ?>>
                                <?php esc_html_e( 'Habilitada (visible en el selector de tienda)', 'woocommerce-multi-store' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php
                    $btn_label = $is_edit
                        ? esc_html__( 'Actualizar Store', 'woocommerce-multi-store' )
                        : esc_html__( 'Crear Store', 'woocommerce-multi-store' );
                    submit_button( $btn_label, 'primary', 'submit', false );
                    ?>
                    <a href="<?php echo esc_url( $list_url ); ?>" class="button button-secondary">
                        <?php esc_html_e( 'Cancelar', 'woocommerce-multi-store' ); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Notificaciones
    // -------------------------------------------------------------------------

    /**
     * Renderiza una notificación de resultado tras una redirección PRG.
     *
     * Lee el parámetro `mswc_notice` de la URL y muestra el mensaje
     * correspondiente con el estilo de notificaciones nativo de WordPress
     * (`notice-success` o `notice-error`). Si el parámetro no existe o no
     * coincide con ninguna clave del mapa, no renderiza nada.
     *
     * Códigos de aviso disponibles:
     *  - `created`      → tienda creada correctamente (éxito).
     *  - `updated`      → tienda actualizada correctamente (éxito).
     *  - `deleted`      → tienda eliminada correctamente (éxito).
     *  - `toggled`      → estado de la tienda actualizado (éxito).
     *  - `bulk_done`    → acción masiva aplicada correctamente (éxito).
     *  - `code_exists`  → ya existe una tienda con ese código (error).
     *  - `not_found`    → tienda no encontrada (error).
     *  - `invalid_data` → datos inválidos en el formulario (error).
     *
     * @since 1.0.0
     */
    private function mswc_render_notices(): void {
        if ( empty( $_GET['mswc_notice'] ) ) {
            return;
        }

        $map = [
            'created'      => [ 'success', __( 'Store creada correctamente.',                   'woocommerce-multi-store' ) ],
            'updated'      => [ 'success', __( 'Store actualizada correctamente.',              'woocommerce-multi-store' ) ],
            'deleted'      => [ 'success', __( 'Store eliminada correctamente.',                'woocommerce-multi-store' ) ],
            'toggled'      => [ 'success', __( 'Estado de la store actualizado.',               'woocommerce-multi-store' ) ],
            'bulk_done'    => [ 'success', __( 'Acción masiva aplicada correctamente.',         'woocommerce-multi-store' ) ],
            'code_exists'  => [ 'error',   __( 'Ya existe una store con ese código.',           'woocommerce-multi-store' ) ],
            'not_found'    => [ 'error',   __( 'Store no encontrada.',                          'woocommerce-multi-store' ) ],
            'invalid_data' => [ 'error',   __( 'Datos inválidos. Comprueba el formulario.',    'woocommerce-multi-store' ) ],
        ];

        $key = sanitize_key( $_GET['mswc_notice'] );
        if ( isset( $map[ $key ] ) ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $map[ $key ][0] ),
                esc_html( $map[ $key ][1] )
            );
        }
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    /**
     * Manejador POST para crear o actualizar una tienda.
     *
     * Proceso:
     *  1. Verifica el nonce `mswc_save_store` via `check_admin_referer`.
     *  2. Comprueba el permiso `manage_woocommerce`.
     *  3. Sanitiza los campos del formulario (`store_name`, `store_code`, `store_enabled`).
     *  4. Si `store_id > 0`: actualiza `name` y `enabled` (el código es inmutable).
     *  5. Si `store_id === 0` (nueva tienda): valida el formato del código, comprueba
     *     que no existe ya en la tabla y realiza el INSERT.
     *  6. Redirige al listado con el aviso de resultado correspondiente (PRG).
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_handle_save_store(): void {
        check_admin_referer( 'mswc_save_store', 'mswc_store_nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'woocommerce-multi-store' ) );
        }

        global $wpdb;
        $table    = "{$wpdb->prefix}mswc_stores";
        $store_id = absint( $_POST['store_id'] ?? 0 );
        $name     = sanitize_text_field( wp_unslash( $_POST['store_name'] ?? '' ) );
        $code     = sanitize_key( wp_unslash( $_POST['store_code'] ?? '' ) );
        $enabled  = isset( $_POST['store_enabled'] ) ? 1 : 0;
        $list_url = admin_url( 'admin.php?page=mswc-stores' );

        if ( empty( $name ) ) {
            wp_redirect( add_query_arg( 'mswc_notice', 'invalid_data', $list_url ) );
            exit;
        }

        if ( $store_id > 0 ) {
            $wpdb->update(
                $table,
                [ 'name' => $name, 'enabled' => $enabled ],
                [ 'id'   => $store_id ],
                [ '%s', '%d' ],
                [ '%d' ]
            );
            wp_redirect( add_query_arg( 'mswc_notice', 'updated', $list_url ) );
            exit;
        }

        // Nueva store: validar código.
        if ( empty( $code ) || ! preg_match( '/^[a-z0-9_\-]+$/', $code ) ) {
            wp_redirect( add_query_arg( [ 'mswc_notice' => 'invalid_data', 'action' => 'add' ], $list_url ) );
            exit;
        }

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) );
        if ( $exists ) {
            wp_redirect( add_query_arg( [ 'mswc_notice' => 'code_exists', 'action' => 'add' ], $list_url ) );
            exit;
        }

        $wpdb->insert(
            $table,
            [ 'code' => $code, 'name' => $name, 'enabled' => $enabled ],
            [ '%s', '%s', '%d' ]
        );
        wp_redirect( add_query_arg( 'mswc_notice', 'created', $list_url ) );
        exit;
    }

    /**
     * Manejador GET para eliminar una tienda y todos sus registros de stock.
     *
     * Proceso:
     *  1. Verifica el nonce `mswc_delete_store_{id}` (específico por tienda
     *     para evitar ataques CSRF que eliminen múltiples tiendas con un solo
     *     enlace falsificado).
     *  2. Comprueba el permiso `manage_woocommerce`.
     *  3. Elimina el registro de `mswc_stores` y en cascada todos los registros
     *     relacionados en `mswc_stores_stock`.
     *  4. Redirige al listado con el aviso `deleted` (PRG).
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_handle_delete_store(): void {
        $store_id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( "mswc_delete_store_{$store_id}" );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'woocommerce-multi-store' ) );
        }

        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}mswc_stores",       [ 'id'       => $store_id ], [ '%d' ] );
        $wpdb->delete( "{$wpdb->prefix}mswc_stores_stock", [ 'store_id' => $store_id ], [ '%d' ] );

        wp_redirect( add_query_arg( 'mswc_notice', 'deleted', admin_url( 'admin.php?page=mswc-stores' ) ) );
        exit;
    }

    /**
     * Manejador GET para alternar el estado habilitado/deshabilitado de una tienda.
     *
     * Proceso:
     *  1. Verifica el nonce `mswc_toggle_store_{id}` (específico por tienda).
     *  2. Comprueba el permiso `manage_woocommerce`.
     *  3. Lee `$_GET['enabled']` (0 o 1) y actualiza el campo `enabled` en la DB.
     *  4. Redirige al listado con el aviso `toggled` (PRG).
     *
     * Cuando se deshabilita una tienda, los clientes con esa tienda en sesión
     * verán el error en el carrito y checkout gracias a `MSWC_Checkout_Validation`.
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_handle_toggle_store(): void {
        $store_id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( "mswc_toggle_store_{$store_id}" );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'woocommerce-multi-store' ) );
        }

        $enabled = absint( $_GET['enabled'] ?? 0 ) ? 1 : 0;

        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}mswc_stores",
            [ 'enabled' => $enabled ],
            [ 'id'      => $store_id ],
            [ '%d' ],
            [ '%d' ]
        );

        wp_redirect( add_query_arg( 'mswc_notice', 'toggled', admin_url( 'admin.php?page=mswc-stores' ) ) );
        exit;
    }

    /**
     * Manejador POST para aplicar acciones masivas sobre tiendas seleccionadas.
     *
     * `WP_List_Table` genera dos selectores de acción (`action` y `action2`,
     * uno en la cabecera y otro en el pie de la tabla). Este handler toma el
     * primero que no sea `-1`. Si ambos son `-1` o no hay IDs seleccionados,
     * redirige sin realizar ninguna operación.
     *
     * Acciones disponibles:
     *  - `delete`  → elimina tiendas y su stock asociado en `mswc_stores_stock`.
     *  - `enable`  → pone `enabled = 1` en las tiendas seleccionadas.
     *  - `disable` → pone `enabled = 0` en las tiendas seleccionadas.
     *
     * Los placeholders de la cláusula IN se construyen dinámicamente y se
     * validan con `$wpdb->prepare` usando spread operator.
     *
     * @since  1.0.0
     * @global wpdb $wpdb Objeto global de acceso a la base de datos de WordPress.
     */
    public function mswc_handle_bulk_action(): void {
        check_admin_referer( 'mswc_bulk_stores', 'mswc_bulk_nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'woocommerce-multi-store' ) );
        }

        // WP_List_Table genera dos selectores (action, action2); tomamos el que no sea -1.
        $bulk_action = '-1' !== ( $_POST['action'] ?? '-1' )
            ? sanitize_key( $_POST['action'] )
            : sanitize_key( $_POST['action2'] ?? '-1' );

        $store_ids = array_map( 'absint', (array) ( $_POST['store_ids'] ?? [] ) );
        $list_url  = admin_url( 'admin.php?page=mswc-stores' );

        if ( '-1' === $bulk_action || empty( $store_ids ) ) {
            wp_redirect( $list_url );
            exit;
        }

        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $store_ids ), '%d' ) );

        switch ( $bulk_action ) {
            case 'delete':
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}mswc_stores WHERE id IN ({$placeholders})", ...$store_ids ) );
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}mswc_stores_stock WHERE store_id IN ({$placeholders})", ...$store_ids ) );
                break;

            case 'enable':
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mswc_stores SET enabled = 1 WHERE id IN ({$placeholders})", ...$store_ids ) );
                break;

            case 'disable':
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mswc_stores SET enabled = 0 WHERE id IN ({$placeholders})", ...$store_ids ) );
                break;
        }

        wp_redirect( add_query_arg( 'mswc_notice', 'bulk_done', $list_url ) );
        exit;
    }
}
