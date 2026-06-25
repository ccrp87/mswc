<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Integra el selector de tienda en el frontend de WooCommerce.
 *
 * Renderiza el widget de selección de tienda antes del contenido principal
 * de WooCommerce y también lo expone como shortcode `[mswc_selector]` para
 * poder embeberse en cualquier página o widget. Encola la hoja de estilos CSS
 * del frontend.
 *
 * El script `selector.js` y su localización (nonce + ajax_url) son
 * responsabilidad de `MSWC_Session::enqueue_scripts()` para centralizar
 * la gestión de variables AJAX en un único lugar.
 *
 * Hooks registrados:
 *  - `woocommerce_before_main_content` → selector antes del contenido principal.
 *  - `wp_enqueue_scripts`              → encola `style.css`.
 *  - Shortcode `[mswc_selector]`       → selector embebido en contenido arbitrario.
 *
 * @package WooCommerce_Multi_Store
 * @since   1.0.0
 */
class MSWC_Frontend {

    /**
     * Registra todos los hooks de WordPress y el shortcode.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'woocommerce_before_main_content', [ $this, 'mswc_render_store_selector' ], 5 );
        add_action( 'wp_enqueue_scripts',              [ $this, 'mswc_enqueue_frontend_assets' ] );
        add_shortcode( 'mswc_selector',                [ $this, 'mswc_render_store_selector' ] );
    }

    /**
     * Renderiza el selector de tienda con la lista de tiendas habilitadas.
     *
     * Muestra un `<select>` con todas las tiendas activas y un botón de
     * confirmación. Si ya hay una tienda guardada en la sesión del cliente,
     * su opción aparece preseleccionada. Se usa tanto como acción directa
     * (`woocommerce_before_main_content`) como retorno del shortcode
     * `[mswc_selector]`, por lo que el HTML se emite directamente con `echo`
     * en el caso de acción y se devuelve como cadena en el caso del shortcode
     * (WordPress captura el output del shortcode automáticamente cuando el
     * callback usa `echo` dentro de un buffer abierto por el sistema de
     * shortcodes).
     *
     * @since 1.0.0
     */
    public function mswc_render_store_selector(): void {
        $stores        = MSWC_Plugin::get_active_stores();
        $current_store = WC()->session ? WC()->session->get( 'mswc_selected_store' ) : '';
        ?>
        <div class="mswc-store-selector">
            <select class="mswc-store-select">
                <option value=""><?php esc_html_e( 'Seleccionar...', 'woocommerce-multi-store' ); ?></option>
                <?php foreach ( $stores as $store ) : ?>
                    <option value="<?php echo esc_attr( $store['id'] ); ?>"
                        <?php selected( $current_store, $store['id'] ); ?>>
                        <?php echo esc_html( $store['name'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="mswc-save-store button alt">
                <?php esc_html_e( 'Seleccionar Store', 'woocommerce-multi-store' ); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Encola la hoja de estilos CSS del frontend (`style.css`).
     *
     * Solo encola el CSS. El script `selector.js` y las variables AJAX
     * (`ajax_url`, `nonce`) son gestionados por `MSWC_Session::enqueue_scripts()`
     * para evitar duplicar la localización de variables en dos lugares distintos.
     *
     * @since 1.0.0
     */
    public function mswc_enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'mswc-frontend-css',
            MSWC_PLUGIN_URL . 'assets/css/style.css',
            [],
            MSWC_VERSION
        );
        // El script selector.js y su localización (nonce + ajax_url) los encola MSWC_Session.
    }
}
