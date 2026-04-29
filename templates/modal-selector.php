<?php
    $stores = MSWC_Plugin::get_active_stores();
?>
<div id="mswc-modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; display:flex; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:30px; border-radius:8px; text-align:center;">
        <h2><?php _e( 'Bienvenido, selecciona tu store', 'wmh' ); ?></h2>
        <select id="mswc-select-store" style="margin-bottom:20px; display:block; width:100%;">
            <option value="">Seleccionar...</option>
            <?php foreach ( $stores as $store ) : ?>
                <option value="<?php echo esc_attr( $store['id'] ); ?>">
                    <?php echo esc_html( $store['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button id="mswc-save-store" class="button alt"><?php _e( 'Seleccionar Store', 'wmh' ); ?></button>
    </div>
</div>