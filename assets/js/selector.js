jQuery(document).ready(function($) {
    $('#mswc-save-store').on('click', function(e) {
        e.preventDefault();
        
        const store = $('#mswc-select-store').val();
        if(!store) {
            alert('Por favor selecciona una tienda');
            return;
        }

        $.ajax({
            url: mswc_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'mswc_save_store',
                store: store
            },
            success: function(response) {
                if (response.success) {
                    // Recargar la página para que los filtros de precio y stock se apliquen
                    location.reload();
                } else {
                    alert('Error al guardar la selección.');
                }
            }
        });
    });
});