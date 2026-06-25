/**
 * Initializes the store selector modal and widget on frontend.
 *
 * @function
 * @since 1.0.0
 * @description Displays modal when no store is selected, handles AJAX store selection for both
 *              modal and widget selectors using class-based selectors, manages error display,
 *              and prevents conflicts when multiple selectors are present on the same page.
 */
jQuery( document ).ready( function ( $ ) {
    // Show the modal only when no store is active in the session.
    if ( ! mswc_vars.selected_store ) {
        $( '.mswc-modal-overlay' ).css( 'display', 'flex' );
    }

    /**
     * Event delegation for store selection buttons.
     *
     * Uses class-based selectors to find the appropriate select, error element,
     * and handle AJAX submission for both modal and widget independently.
     *
     * @since 1.0.0
     */
    $( document ).on( 'click', '.mswc-save-store', function ( e ) {
        e.preventDefault();

        var $button    = $( this );
        // Find the container - check for modal first, then widget
        var $container = $button.closest( '.mswc-modal-overlay' ).length 
            ? $button.closest( '.mswc-modal-overlay' )
            : $button.closest( '.mswc-store-selector' );
        
        var $select    = $container.find( '.mswc-store-select' );
        var $error     = $container.find( '.mswc-store-error' );
        var store      = $select.val();

        if ( ! store ) {
            alert( 'Por favor selecciona una tienda.' );
            return;
        }

        $button.prop( 'disabled', true );
        $error.hide().text( '' );

        $.ajax( {
            url:  mswc_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'mswc_save_store',
                nonce:  mswc_vars.nonce,
                store:  store,
            },
            success: function ( response ) {
                if ( response.success ) {
                    location.reload();
                } else {
                    // Mostrar el mensaje de error exacto devuelto por el servidor
                    // (p.ej. "La tienda X está deshabilitada y no acepta pedidos.")
                    var msg = response.data && response.data.message
                        ? response.data.message
                        : 'Error al guardar la selección.';

                    if ( $error.length ) {
                        $error.text( msg ).show();
                    } else {
                        alert( msg );
                    }

                    $select.val( '' ); // Limpiar la selección inválida
                    $button.prop( 'disabled', false );
                }
            },
            error: function () {
                var errorMsg = 'Error de conexión. Intenta nuevamente.';
                if ( $error.length ) {
                    $error.text( errorMsg ).show();
                } else {
                    alert( errorMsg );
                }
                $button.prop( 'disabled', false );
            },
        } );
    } );
} );
