<?php

namespace MSWC\Tests\Unit;

use Brain\Monkey\Functions;
use MSWC\Tests\TestCase;
use MSWC_Filters;
use Mockery;
use ReflectionClass;

/**
 * Unit tests for MSWC_Filters.
 *
 * Tests cover:
 *  - mswc_apply_stock_by_store
 *  - mswc_filter_product_is_in_stock
 *  - mswc_apply_price_by_store
 *  - mswc_store_availability_by_store
 *  - mswc_rename_product_columns
 *  - mswc_format_admin_stock_column
 *  - mswc_format_admin_price_column
 */
class FiltersTest extends TestCase {

    /** @var \Mockery\MockInterface&\wpdb */
    private object $wpdb;

    private MSWC_Filters $filters;

    /** @var \Mockery\MockInterface&\WC_Product */
    private object $product;

    /** @var object{session: \Mockery\MockInterface&\WC_Session_Handler} */
    private object $wc;

    protected function setUp(): void {
        parent::setUp();

        // Reset the static cache between tests.
        $reflection = new ReflectionClass( MSWC_Filters::class );
        $prop = $reflection->getProperty( 'store_data_cache' );
        $prop->setAccessible( true );
        $prop->setValue( null, [] );

        // Build a wpdb mock with prefix and the methods we need.
        $this->wpdb = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;

        // Instantiate filters (constructor calls add_filter/add_action which
        // are stubbed by Brain Monkey in the base TestCase).
        $this->filters = new MSWC_Filters();

        // Product mock: returns id=42, parent_id=0 by default.
        $this->product = Mockery::mock( 'WC_Product' );
        $this->product->shouldReceive( 'get_id' )->andReturn( 42 )->byDefault();
        $this->product->shouldReceive( 'get_parent_id' )->andReturn( 0 )->byDefault();

        // WC() mock with a session mock attached.
        $session = Mockery::mock( 'WC_Session_Handler' );
        $session->shouldReceive( 'get' )->with( 'mswc_selected_store' )->andReturn( null )->byDefault();

        $this->wc = new \stdClass();
        $this->wc->session = $session;

        Functions\when( 'WC' )->justReturn( $this->wc );
    }

    protected function tearDown(): void {
        unset( $GLOBALS['wpdb'] );
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Configure the wpdb mock to return a row for the stock+price query.
     *
     * @param array<string,string>|null $row
     */
    private function expectDbRow( ?array $row ): void {
        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturnUsing( static fn( string $sql, ...$args ): string => $sql );

        $this->wpdb
            ->shouldReceive( 'get_row' )
            ->andReturn( $row );
    }

    /** Make the session return a specific store id (string). */
    private function withStoreInSession( string $storeId ): void {
        $this->wc->session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( $storeId );
    }

    // -----------------------------------------------------------------------
    // mswc_apply_stock_by_store
    // -----------------------------------------------------------------------

    public function test_apply_stock_returns_original_when_in_admin(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        $result = $this->filters->mswc_apply_stock_by_store( 10, $this->product );

        $this->assertSame( 10, $result );
    }

    public function test_apply_stock_returns_zero_when_admin_and_null_quantity(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        $result = $this->filters->mswc_apply_stock_by_store( null, $this->product );

        $this->assertSame( 0, $result );
    }

    public function test_apply_stock_returns_original_when_no_session(): void {
        Functions\when( 'is_admin' )->justReturn( false );

        $wc = new \stdClass();
        $wc->session = null;
        Functions\when( 'WC' )->justReturn( $wc );

        $result = $this->filters->mswc_apply_stock_by_store( 5, $this->product );

        $this->assertSame( 5, $result );
    }

    public function test_apply_stock_returns_original_when_no_store_in_session(): void {
        // Session returns null for store key (already the default).
        $result = $this->filters->mswc_apply_stock_by_store( 7, $this->product );

        $this->assertSame( 7, $result );
    }

    public function test_apply_stock_returns_store_stock_when_store_selected_and_row_exists(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '15', 'price' => '25.99' ] );

        $result = $this->filters->mswc_apply_stock_by_store( 0, $this->product );

        $this->assertSame( 15, $result );
    }

    public function test_apply_stock_falls_back_to_wc_quantity_when_no_table_row(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( null );

        $result = $this->filters->mswc_apply_stock_by_store( 20, $this->product );

        $this->assertSame( 20, $result );
    }

    // -----------------------------------------------------------------------
    // mswc_filter_product_is_in_stock
    // -----------------------------------------------------------------------

    public function test_filter_is_in_stock_returns_original_when_in_admin(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        $result = $this->filters->mswc_filter_product_is_in_stock( true, $this->product );

        $this->assertTrue( $result );
    }

    public function test_filter_is_in_stock_returns_false_when_no_row_in_table(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( null );

        $result = $this->filters->mswc_filter_product_is_in_stock( true, $this->product );

        $this->assertFalse( $result );
    }

    public function test_filter_is_in_stock_returns_false_when_stock_is_zero(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '0', 'price' => '25.99' ] );

        $result = $this->filters->mswc_filter_product_is_in_stock( true, $this->product );

        $this->assertFalse( $result );
    }

    public function test_filter_is_in_stock_returns_false_when_price_not_configured(): void {
        // price '0.00' → (float)0 → not > 0 → stored as null in cache.
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '10', 'price' => '0.00' ] );

        $result = $this->filters->mswc_filter_product_is_in_stock( true, $this->product );

        $this->assertFalse( $result );
    }

    public function test_filter_is_in_stock_returns_true_when_stock_and_price_valid(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '5', 'price' => '19.99' ] );

        $result = $this->filters->mswc_filter_product_is_in_stock( true, $this->product );

        $this->assertTrue( $result );
    }

    public function test_filter_is_in_stock_respects_incoming_is_in_stock_when_conditions_pass(): void {
        // When store has stock+price, the method returns the original $is_in_stock.
        // If WC already says false (e.g. backorder rules), we respect that.
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '5', 'price' => '19.99' ] );

        $result = $this->filters->mswc_filter_product_is_in_stock( false, $this->product );

        $this->assertFalse( $result );
    }

    // -----------------------------------------------------------------------
    // mswc_apply_price_by_store
    // -----------------------------------------------------------------------

    public function test_apply_price_returns_original_when_no_store(): void {
        // Session returns null (default) → no store.
        $result = $this->filters->mswc_apply_price_by_store( '10.00', $this->product );

        $this->assertSame( '10.00', $result );
    }

    public function test_apply_price_returns_store_price_when_configured(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '5', 'price' => '29.99' ] );

        $result = $this->filters->mswc_apply_price_by_store( '10.00', $this->product );

        // The method returns (string) store_price → '29.99'.
        $this->assertSame( '29.99', $result );
    }

    public function test_apply_price_returns_original_when_store_price_is_zero(): void {
        // price '0.00' → cache stores null → falls back to original.
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '5', 'price' => '0.00' ] );

        $result = $this->filters->mswc_apply_price_by_store( '15.00', $this->product );

        $this->assertSame( '15.00', $result );
    }

    // -----------------------------------------------------------------------
    // mswc_store_availability_by_store
    // -----------------------------------------------------------------------

    private function defaultAvailability(): array {
        return [ 'availability' => 'In stock', 'class' => 'in-stock' ];
    }

    public function test_availability_returns_unchanged_when_no_store(): void {
        $input = $this->defaultAvailability();

        $result = $this->filters->mswc_store_availability_by_store( $input, $this->product );

        $this->assertSame( $input, $result );
    }

    public function test_availability_shows_agotado_when_store_has_no_stock(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '0', 'price' => '25.99' ] );

        $result = $this->filters->mswc_store_availability_by_store( $this->defaultAvailability(), $this->product );

        $this->assertStringContainsString( 'Agotado', $result['availability'] );
        $this->assertSame( 'out-of-stock', $result['class'] );
    }

    public function test_availability_shows_agotado_when_no_row_in_table(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( null );

        $result = $this->filters->mswc_store_availability_by_store( $this->defaultAvailability(), $this->product );

        $this->assertStringContainsString( 'Agotado', $result['availability'] );
    }

    public function test_availability_shows_units_when_store_has_stock(): void {
        $this->withStoreInSession( '1' );
        $this->expectDbRow( [ 'stock' => '8', 'price' => '25.99' ] );

        $result = $this->filters->mswc_store_availability_by_store( $this->defaultAvailability(), $this->product );

        $this->assertStringContainsString( '8', $result['availability'] );
        $this->assertSame( 'in-stock', $result['class'] );
    }

    // -----------------------------------------------------------------------
    // mswc_rename_product_columns
    // -----------------------------------------------------------------------

    public function test_rename_product_columns_adds_helper_tooltips(): void {
        $columns = [
            'is_in_stock' => 'Stock',
            'price'       => 'Price',
            'name'        => 'Name',
        ];

        $result = $this->filters->mswc_rename_product_columns( $columns );

        $this->assertStringContainsString( '(?)', $result['is_in_stock'] );
        $this->assertStringContainsString( '(?)', $result['price'] );
        $this->assertSame( 'Name', $result['name'] );
    }

    // -----------------------------------------------------------------------
    // mswc_format_admin_price_column
    // -----------------------------------------------------------------------

    public function test_format_admin_price_column_returns_empty_when_avg_is_null(): void {
        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturn( 'SQL' );
        $this->wpdb
            ->shouldReceive( 'get_var' )
            ->andReturn( null );

        $result = $this->filters->mswc_format_admin_price_column( 42 );

        $this->assertSame( '', $result );
    }

    public function test_format_admin_price_column_returns_formatted_when_prices_exist(): void {
        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturn( 'SQL' );
        $this->wpdb
            ->shouldReceive( 'get_var' )
            ->andReturn( '25.99' );

        Functions\when( 'wc_price' )->justReturn( '$25.99' );

        $result = $this->filters->mswc_format_admin_price_column( 42 );

        $this->assertStringContainsString( '$25.99', $result );
    }

    // -----------------------------------------------------------------------
    // mswc_format_admin_stock_column
    // -----------------------------------------------------------------------

    public function test_format_admin_stock_column_shows_sin_datos_when_no_rows(): void {
        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturn( 'SQL' );
        $this->wpdb
            ->shouldReceive( 'get_row' )
            ->andReturn( null );

        $result = $this->filters->mswc_format_admin_stock_column( 42 );

        $this->assertStringContainsString( 'Sin datos', $result );
    }

    public function test_format_admin_stock_column_shows_average_when_rows_exist(): void {
        $summary = new \stdClass();
        $summary->total   = '20';
        $summary->promedio = '10.0';

        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturn( 'SQL' );
        $this->wpdb
            ->shouldReceive( 'get_row' )
            ->andReturn( $summary );

        $result = $this->filters->mswc_format_admin_stock_column( 42 );

        $this->assertStringContainsString( '10.00', $result );
    }
}
