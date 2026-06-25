<?php

namespace MSWC\Tests\Unit;

use Brain\Monkey\Functions;
use MSWC\Tests\TestCase;
use MSWC_Admin;
use MSWC_Plugin;
use Mockery;

/**
 * Unit tests for MSWC_Admin.
 *
 * Tests cover:
 *  - mswc_add_stores_product_tab
 *  - mswc_save_store_stock_fields
 */
class AdminTest extends TestCase {

    /** @var \Mockery\MockInterface */
    private object $wpdb;

    private MSWC_Admin $admin;

    /** @var \Mockery\MockInterface&\WC_Product */
    private object $product;

    protected function setUp(): void {
        parent::setUp();

        $this->wpdb = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;

        // Reset the configurable stub to an empty store list.
        MSWC_Plugin::$active_stores = [];

        $this->admin = new MSWC_Admin();

        $this->product = Mockery::mock( 'WC_Product' );
        $this->product->shouldReceive( 'get_id' )->andReturn( 10 )->byDefault();
    }

    protected function tearDown(): void {
        unset( $GLOBALS['wpdb'] );
        // Clean up $_POST overrides.
        $_POST = [];
        // Reset active stores to empty.
        MSWC_Plugin::$active_stores = [];
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // mswc_add_stores_product_tab
    // -----------------------------------------------------------------------

    public function test_add_stores_product_tab_includes_required_keys(): void {
        $result = $this->admin->mswc_add_stores_product_tab( [] );

        $this->assertArrayHasKey( 'mswc_stores_tab', $result );
        $this->assertSame( 'mswc_stores_product_data', $result['mswc_stores_tab']['target'] );
        $this->assertArrayHasKey( 'label', $result['mswc_stores_tab'] );
    }

    public function test_add_stores_product_tab_does_not_overwrite_existing_tabs(): void {
        $existing = [ 'general' => [ 'label' => 'General', 'target' => 'general_product_data' ] ];

        $result = $this->admin->mswc_add_stores_product_tab( $existing );

        $this->assertArrayHasKey( 'general', $result );
        $this->assertArrayHasKey( 'mswc_stores_tab', $result );
    }

    // -----------------------------------------------------------------------
    // mswc_save_store_stock_fields
    // -----------------------------------------------------------------------

    public function test_save_fields_skips_without_permission(): void {
        Functions\when( 'current_user_can' )->justReturn( false );

        $this->wpdb->shouldNotReceive( 'replace' );

        $this->admin->mswc_save_store_stock_fields( $this->product );

        $this->addToAssertionCount( 1 );
    }

    public function test_save_fields_with_no_active_stores_does_nothing(): void {
        Functions\when( 'current_user_can' )->justReturn( true );

        // MSWC_Plugin::$active_stores is [] (set in setUp).
        $this->wpdb->shouldNotReceive( 'replace' );

        $this->admin->mswc_save_store_stock_fields( $this->product );

        $this->addToAssertionCount( 1 );
    }

    public function test_save_fields_calls_replace_for_each_store(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'wc_format_decimal' )->justReturn( '29.99' );

        // Configure two active stores.
        MSWC_Plugin::$active_stores = [
            [ 'id' => '1', 'code' => 'norte', 'name' => 'Norte' ],
            [ 'id' => '2', 'code' => 'sur',   'name' => 'Sur'   ],
        ];

        // Populate $_POST with the expected field values.
        $_POST['_stock_store_norte'] = '10';
        $_POST['_price_store_norte'] = '29.99';
        $_POST['_stock_store_sur']   = '5';
        $_POST['_price_store_sur']   = '15.00';

        // wp_unslash and sanitize_text_field are PHP native / stubbed.
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();

        // Expect replace() to be called exactly twice (once per store).
        $this->wpdb
            ->shouldReceive( 'replace' )
            ->twice()
            ->andReturn( 1 );

        $this->admin->mswc_save_store_stock_fields( $this->product );

        $this->addToAssertionCount( 1 );
    }

    public function test_save_fields_uses_zero_stock_when_post_field_absent(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'wc_format_decimal' )->justReturn( '' );

        MSWC_Plugin::$active_stores = [
            [ 'id' => '1', 'code' => 'norte', 'name' => 'Norte' ],
        ];

        // No $_POST fields for 'norte' → defaults to stock=0, price=0.0.
        $_POST = [];

        $this->wpdb
            ->shouldReceive( 'replace' )
            ->once()
            ->with(
                Mockery::type( 'string' ),
                Mockery::on( static function ( array $data ): bool {
                    return $data['stock'] === 0 && $data['price'] === 0.0;
                } ),
                Mockery::any()
            )
            ->andReturn( 1 );

        $this->admin->mswc_save_store_stock_fields( $this->product );

        $this->addToAssertionCount( 1 );
    }
}
