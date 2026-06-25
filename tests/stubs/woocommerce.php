<?php
/**
 * WooCommerce and WordPress stub definitions for unit testing.
 *
 * These stubs allow the plugin classes to be loaded and tested in isolation,
 * without a full WordPress + WooCommerce bootstrap.
 */

// ---------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        /** @var array<string, list<string>> */
        private array $errors = [];

        public function add( string $code, string $message, mixed $data = '' ): void {
            $this->errors[ $code ][] = $message;
        }

        public function has_errors(): bool {
            return ! empty( $this->errors );
        }

        /** @return list<string> */
        public function get_error_codes(): array {
            return array_keys( $this->errors );
        }
    }
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    class WP_List_Table {
        public function __construct( array $args = [] ) {}
    }
}

// ---------------------------------------------------------------------------
// WooCommerce product stubs
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WC_Product' ) ) {
    abstract class WC_Product {
        abstract public function get_id(): int;
        abstract public function get_parent_id(): int;
        abstract public function get_stock_quantity( ?string $context = 'view' ): ?int;
    }
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
    class WC_Product_Simple extends WC_Product {
        public function get_id(): int { return 0; }
        public function get_parent_id(): int { return 0; }
        public function get_stock_quantity( ?string $context = 'view' ): ?int { return null; }
    }
}

// ---------------------------------------------------------------------------
// WooCommerce order stubs
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WC_Order' ) ) {
    abstract class WC_Order {
        abstract public function get_meta( string $key, bool $single = true ): mixed;
        abstract public function update_meta_data( string $key, mixed $value ): void;
        abstract public function save(): int;
        abstract public function get_items(): array;
    }
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
    abstract class WC_Order_Item_Product {
        abstract public function get_product_id(): int;
        abstract public function get_quantity(): int;
    }
}

// ---------------------------------------------------------------------------
// WooCommerce session stub
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WC_Session_Handler' ) ) {
    class WC_Session_Handler {
        public function get( string $key ): mixed {
            return null;
        }

        public function set( string $key, mixed $value ): void {}

        public function has_session(): bool {
            return false;
        }

        public function set_customer_session_cookie( bool $set ): void {}
    }
}

// ---------------------------------------------------------------------------
// MSWC_Plugin stub (configurable via static property)
// ---------------------------------------------------------------------------

if ( ! class_exists( 'MSWC_Plugin' ) ) {
    class MSWC_Plugin {
        /** @var list<array<string, mixed>> */
        public static array $active_stores = [];

        /** @return list<array<string, mixed>> */
        public static function get_active_stores(): array {
            return self::$active_stores;
        }
    }
}

// ---------------------------------------------------------------------------
// WooCommerce global function stubs
// ---------------------------------------------------------------------------

if ( ! function_exists( 'WC' ) ) {
    function WC(): object {
        return new stdClass();
    }
}

if ( ! function_exists( 'wc_price' ) ) {
    function wc_price( float $price ): string {
        return '$' . number_format( $price, 2 );
    }
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
    function wc_format_decimal( mixed $v, mixed $dp = false ): string {
        return (string) $v;
    }
}

if ( ! function_exists( 'wc_format_localized_price' ) ) {
    function wc_format_localized_price( mixed $v ): string {
        return (string) $v;
    }
}

if ( ! function_exists( 'wc_add_notice' ) ) {
    // No void return type — Patchwork/Brain Monkey need to intercept this function
    // and a void-typed redefinition will throw NonNullToVoid errors.
    function wc_add_notice( string $msg, string $type = 'success' ) {} // phpcs:ignore
}

if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order( int $id ): mixed {
        return false;
    }
}

if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
    function woocommerce_wp_text_input( array $args ): void {}
}

if ( ! function_exists( 'wc_get_stock_html' ) ) {
    function wc_get_stock_html( object $product ): string {
        return '';
    }
}

// ---------------------------------------------------------------------------
// Additional WordPress function stubs not covered by Brain Monkey's defaults
// ---------------------------------------------------------------------------

if ( ! function_exists( 'absint' ) ) {
    function absint( mixed $v ): int {
        return abs( (int) $v );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $str ): string {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( mixed $value ): mixed {
        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( string $url ): string {
        return $url;
    }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( string $text, string $domain = 'default' ): string {
        return $text;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( string $capability ): bool {
        return false;
    }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( string $action, mixed $query_arg = false, bool $die = true ): int|false {
        return 1;
    }
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( mixed $data = null ): void {}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( mixed $data = null ): void {}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( mixed $action = -1 ): string {
        return 'test_nonce';
    }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( string $handle, string $src = '', array $deps = [], mixed $ver = false, bool $in_footer = false ): void {}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( string $handle, string $src = '', array $deps = [], mixed $ver = false, string $media = 'all' ): void {}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
        return true;
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( string $path = '', string $scheme = null ): string {
        return 'https://example.com' . $path;
    }
}

if ( ! function_exists( 'get_current_screen' ) ) {
    function get_current_screen(): ?object {
        return null;
    }
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}
