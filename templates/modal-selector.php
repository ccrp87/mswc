<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$stores = MSWC_Plugin::get_active_stores();
?>
<div class="mswc-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="mswc-modal-title"
    style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:none;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:30px;border-radius:8px;text-align:center;min-width:300px;">
        <h2 id="mswc-modal-title"><?php esc_html_e( 'Bienvenido, selecciona tu store', 'woocommerce-multi-store' ); ?></h2>
        <select class="mswc-store-select" style="margin-bottom:20px;display:block;width:100%;"
            aria-label="<?php esc_attr_e( 'Seleccionar tienda', 'woocommerce-multi-store' ); ?>">
            <option value=""><?php esc_html_e( 'Seleccionar...', 'woocommerce-multi-store' ); ?></option>
            <?php foreach ( $stores as $store ) : ?>
                <option value="<?php echo esc_attr( $store['id'] ); ?>">
                    <?php echo esc_html( $store['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="mswc-store-error" style="display:none;" role="alert" aria-live="assertive"></p>
        <button class="mswc-save-store button alt">
            <?php esc_html_e( 'Seleccionar Store', 'woocommerce-multi-store' ); ?>
        </button>
    </div>
</div>
