/**
 * Initializes admin-side JavaScript functionality for WooCommerce Multi-Store.
 *
 * @function
 * @since 1.0.0
 * @description Handles auto-activation of store tabs, delete confirmations, and store code normalization.
 */
jQuery( document ).ready( function ( $ ) {

    /* --- Auto-activar pestaña Stores si el hash coincide --- */
    if ( window.location.hash && $( '.product_data_tabs .mswc_stores_tab_tab' ).length ) {
        $( '.product_data_tabs .mswc_stores_tab_tab a' ).trigger( 'click' );
        $( 'html, body' ).animate(
            { scrollTop: $( '#woocommerce-product-data' ).offset().top - 50 },
            500
        );
    }

    /* --- Confirmación antes de eliminar una store --- */
    $( document ).on( 'click', '.mswc-confirm-delete', function ( e ) {
        var name    = $( this ).data( 'name' ) || '';
        var message = name
            ? 'Vas a eliminar la store "' + name + '" y todos sus datos de stock. ¿Continuar?'
            : '¿Estás seguro de que quieres eliminar esta store?';

        if ( ! window.confirm( message ) ) {
            e.preventDefault();
        }
    } );

    /* --- Normalizar código de store a minúsculas mientras se escribe --- */
    $( '#store_code' ).on( 'input', function () {
        this.value = this.value.toLowerCase().replace( /[^a-z0-9_\-]/g, '' );
    } );

} );
