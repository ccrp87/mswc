<?php
class mswc_Session {
    public function __construct() {
        // El nombre aquí debe ser idéntico al de la función de abajo
        add_action( 'init', array( $this, 'set_customer_session' ), 5 );
        add_action( 'wp_footer', array( $this, 'render_selection_modal' ) );
        
        // Endpoints AJAX
        add_action( 'wp_ajax_mswc_save_store', array( $this, 'ajax_save_store' ) );
        add_action( 'wp_ajax_nopriv_mswc_save_store', array( $this, 'ajax_save_store' ) );
        
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function set_customer_session() { // <-- Verifica que este nombre no tenga errores
        if ( is_admin() || is_ajax() ) return;
        if ( isset( WC()->session ) && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }

    public function render_selection_modal() {
        if ( isset( WC()->session ) && ! WC()->session->get( 'mswc_mswc_selected_store' ) ) {
            include plugin_dir_path( __DIR__ ) . 'templates/modal-selector.php';
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script( 'mswc-selector', plugin_dir_url( __DIR__ ) . 'assets/js/selector.js', array('jquery'), '1.0', true );
        wp_localize_script( 'mswc-selector', 'mswc_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' )
        ));
    }

    public function ajax_save_store() {
        if ( isset( $_POST['store'] ) ) {
            $store = sanitize_text_field( $_POST['store'] );
            WC()->session->set( 'mswc_mswc_selected_store', $store );
            wp_send_json_success();
        }
        wp_send_json_error();
    }
}