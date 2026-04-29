<?php
class mswc_Frontend
{

    public function __construct()
    {
        // 1. Cargamos el selector en el header (Hook de WooCommerce)
        add_action('woocommerce_before_main_content', [$this, 'mswc_render_store_selector'], 5);

        // 2. Cargamos los estilos y scripts necesarios para el frontend
        add_action('wp_enqueue_scripts', [$this, 'mswc_enqueue_frontend_assets']);

        // 3. Shortcode para el selector (opcional, si quieres usarlo en otras partes)
        add_shortcode('mswc_selector', [$this, 'mswc_render_store_selector']);

    }

    public function mswc_render_store_selector()
    {
        $stores = MSWC_Plugin::get_active_stores();
        $current_store = WC()->session->get( 'mswc_mswc_selected_store' );
        ?>
        <select id="mswc-select-store" style="margin-bottom:20px; display:block; width:100%;">
            <option value="">Seleccionar...</option>
            <?php foreach ( $stores as $store ) : ?>
                <?php $selected = ($current_store == $store['id']) ? 'selected' : '';?>
                <option value="<?php echo esc_attr( $store['id'] ); ?>" <?php echo $selected; ?>>
                    <?php echo esc_html( $store['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button id="mswc-save-store" class="button alt"><?php _e( 'Seleccionar Store', 'wmh' ); ?></button>
        <?php
    }

    public function mswc_enqueue_frontend_assets()
    {
        wp_enqueue_style('mswc-frontend-css', plugin_dir_url(__FILE__) . '../assets/css/frontend.css');
        wp_enqueue_script( 'mswc-selector', plugin_dir_url( __DIR__ ) . 'assets/js/selector.js', array('jquery'), '1.0', true );

    }
}