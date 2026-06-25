<?php

namespace MSWC\Tests\Unit;

use Brain\Monkey\Functions;
use MSWC\Tests\TestCase;
use MSWC_Orders;
use Mockery;

/**
 * Unit tests for MSWC_Orders.
 *
 * Tests cover:
 *  - mswc_attach_store_to_order
 *  - mswc_reduce_store_stock
 */
class OrdersTest extends TestCase {

    /** @var \Mockery\MockInterface */
    private object $wpdb;

    private MSWC_Orders $orders;

    /** @var object{session: \Mockery\MockInterface} */
    private object $wc;

    /** @var \Mockery\MockInterface&\WC_Order */
    private object $order;

    protected function setUp(): void {
        parent::setUp();

        $this->wpdb = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->orders = new MSWC_Orders();

        // Default WC() stub: session returns null for store key.
        $session = Mockery::mock( 'WC_Session_Handler' );
        $session->shouldReceive( 'get' )->with( 'mswc_selected_store' )->andReturn( null )->byDefault();

        $this->wc = new \stdClass();
        $this->wc->session = $session;

        Functions\when( 'WC' )->justReturn( $this->wc );

        $this->order = Mockery::mock( 'WC_Order' );
    }

    protected function tearDown(): void {
        unset( $GLOBALS['wpdb'] );
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // mswc_attach_store_to_order
    // -----------------------------------------------------------------------

    public function test_attach_store_skips_when_no_session(): void {
        $wc = new \stdClass();
        $wc->session = null;
        Functions\when( 'WC' )->justReturn( $wc );

        $this->order->shouldNotReceive( 'update_meta_data' );

        $this->orders->mswc_attach_store_to_order( $this->order );

        // No assertions needed — Mockery verifies shouldNotReceive in tearDown.
        $this->addToAssertionCount( 1 );
    }

    public function test_attach_store_skips_when_no_store_in_session(): void {
        // Session returns null (already the default).
        $this->order->shouldNotReceive( 'update_meta_data' );

        $this->orders->mswc_attach_store_to_order( $this->order );

        $this->addToAssertionCount( 1 );
    }

    public function test_attach_store_skips_when_store_not_found_or_disabled(): void {
        $this->wc->session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( '1' );

        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturn( 'SQL' );
        $this->wpdb
            ->shouldReceive( 'get_row' )
            ->andReturn( null );

        // Session should be cleared.
        $this->wc->session
            ->shouldReceive( 'set' )
            ->with( 'mswc_selected_store', null )
            ->once();

        $this->order->shouldNotReceive( 'update_meta_data' );

        $this->orders->mswc_attach_store_to_order( $this->order );

        $this->addToAssertionCount( 1 );
    }

    public function test_attach_store_saves_meta_when_store_enabled(): void {
        $this->wc->session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( '2' );

        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturn( 'SQL' );
        $this->wpdb
            ->shouldReceive( 'get_row' )
            ->andReturn( [ 'id' => '2', 'enabled' => '1' ] );

        $this->order
            ->shouldReceive( 'update_meta_data' )
            ->with( '_mswc_store_dispatch_id', '2' )
            ->once();
        $this->order
            ->shouldReceive( 'save' )
            ->once()
            ->andReturn( 1 );

        $this->orders->mswc_attach_store_to_order( $this->order );

        $this->addToAssertionCount( 1 );
    }

    // -----------------------------------------------------------------------
    // mswc_reduce_store_stock
    // -----------------------------------------------------------------------

    public function test_reduce_stock_skips_when_order_not_found(): void {
        Functions\when( 'wc_get_order' )->justReturn( false );

        $this->wpdb->shouldNotReceive( 'query' );
        $this->wpdb->shouldNotReceive( 'prepare' );

        $this->orders->mswc_reduce_store_stock( 999 );

        $this->addToAssertionCount( 1 );
    }

    public function test_reduce_stock_skips_when_already_reduced(): void {
        Functions\when( 'wc_get_order' )->justReturn( $this->order );

        $this->order
            ->shouldReceive( 'get_meta' )
            ->with( '_mswc_stock_reduced' )
            ->andReturn( '1' );

        $this->wpdb->shouldNotReceive( 'query' );

        $this->orders->mswc_reduce_store_stock( 1 );

        $this->addToAssertionCount( 1 );
    }

    public function test_reduce_stock_skips_when_no_store_dispatch(): void {
        Functions\when( 'wc_get_order' )->justReturn( $this->order );

        $this->order
            ->shouldReceive( 'get_meta' )
            ->with( '_mswc_stock_reduced' )
            ->andReturn( '' );

        $this->order
            ->shouldReceive( 'get_meta' )
            ->with( '_mswc_store_dispatch_id' )
            ->andReturn( '' );

        $this->wpdb->shouldNotReceive( 'query' );

        $this->orders->mswc_reduce_store_stock( 1 );

        $this->addToAssertionCount( 1 );
    }

    public function test_reduce_stock_updates_db_for_each_item_and_sets_flag(): void {
        Functions\when( 'wc_get_order' )->justReturn( $this->order );

        $this->order
            ->shouldReceive( 'get_meta' )
            ->with( '_mswc_stock_reduced' )
            ->andReturn( '' );

        $this->order
            ->shouldReceive( 'get_meta' )
            ->with( '_mswc_store_dispatch_id' )
            ->andReturn( '3' );

        // Two order items.
        $item1 = Mockery::mock( 'WC_Order_Item_Product' );
        $item1->shouldReceive( 'get_product_id' )->andReturn( 10 );
        $item1->shouldReceive( 'get_quantity' )->andReturn( 2 );

        $item2 = Mockery::mock( 'WC_Order_Item_Product' );
        $item2->shouldReceive( 'get_product_id' )->andReturn( 20 );
        $item2->shouldReceive( 'get_quantity' )->andReturn( 1 );

        $this->order
            ->shouldReceive( 'get_items' )
            ->andReturn( [ $item1, $item2 ] );

        // Expect exactly 2 prepare + query calls (one per item).
        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->twice()
            ->andReturn( 'SQL' );

        $this->wpdb
            ->shouldReceive( 'query' )
            ->twice()
            ->andReturn( 1 );

        // Flag and save.
        $this->order
            ->shouldReceive( 'update_meta_data' )
            ->with( '_mswc_stock_reduced', '1' )
            ->once();

        $this->order
            ->shouldReceive( 'save' )
            ->once()
            ->andReturn( 1 );

        $this->orders->mswc_reduce_store_stock( 1 );

        $this->addToAssertionCount( 1 );
    }
}
