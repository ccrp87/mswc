<?php

namespace MSWC\Tests\Unit;

use Brain\Monkey\Functions;
use MSWC\Tests\TestCase;
use MSWC_Checkout_Validation;
use Mockery;
use WP_Error;

/**
 * Unit tests for MSWC_Checkout_Validation.
 *
 * Tests cover:
 *  - mswc_validate_store (classic checkout / cart validation)
 *  - mswc_validate_store_for_blocks (Store API / blocks checkout)
 */
class CheckoutValidationTest extends TestCase {

    /** @var \Mockery\MockInterface */
    private object $wpdb;

    private MSWC_Checkout_Validation $validation;

    /** @var \Mockery\MockInterface&\WC_Session_Handler */
    private object $wc_session;

    /** @var object{session: \Mockery\MockInterface} */
    private object $wc;

    protected function setUp(): void {
        parent::setUp();

        $this->wpdb = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->wc_session = Mockery::mock( 'WC_Session_Handler' );
        $this->wc_session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( null )
            ->byDefault();

        $this->wc = new \stdClass();
        $this->wc->session = $this->wc_session;

        Functions\when( 'WC' )->justReturn( $this->wc );

        $this->validation = new MSWC_Checkout_Validation();
    }

    protected function tearDown(): void {
        unset( $GLOBALS['wpdb'] );
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Configure wpdb to return a specific store row for the session store query. */
    private function expectStoreRow( ?array $row ): void {
        $this->wpdb
            ->shouldReceive( 'prepare' )
            ->andReturn( 'SQL' );
        $this->wpdb
            ->shouldReceive( 'get_row' )
            ->andReturn( $row );
    }

    // -----------------------------------------------------------------------
    // mswc_validate_store
    // -----------------------------------------------------------------------

    public function test_validate_store_returns_early_in_admin(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        Functions\expect( 'wc_add_notice' )->never();

        $this->validation->mswc_validate_store();

        $this->addToAssertionCount( 1 );
    }

    public function test_validate_store_adds_error_when_no_store_in_session(): void {
        Functions\when( 'is_admin' )->justReturn( false );

        // Session returns null → mswc_get_session_store() returns null.
        // wc_add_notice must be called once with type 'error'.
        Functions\expect( 'wc_add_notice' )
            ->once()
            ->with( Mockery::type( 'string' ), 'error' );

        $this->validation->mswc_validate_store();

        $this->addToAssertionCount( 1 );
    }

    public function test_validate_store_adds_error_when_store_disabled(): void {
        Functions\when( 'is_admin' )->justReturn( false );

        $this->wc_session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( '1' );

        $this->expectStoreRow( [ 'id' => '1', 'name' => 'Norte', 'enabled' => '0' ] );

        // Session should be cleared.
        $this->wc_session
            ->shouldReceive( 'set' )
            ->with( 'mswc_selected_store', null )
            ->once();

        Functions\expect( 'wc_add_notice' )
            ->once()
            ->with( Mockery::type( 'string' ), 'error' );

        $this->validation->mswc_validate_store();

        $this->addToAssertionCount( 1 );
    }

    public function test_validate_store_passes_when_store_enabled(): void {
        Functions\when( 'is_admin' )->justReturn( false );

        $this->wc_session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( '1' );

        $this->expectStoreRow( [ 'id' => '1', 'name' => 'Norte', 'enabled' => '1' ] );

        Functions\expect( 'wc_add_notice' )->never();

        $this->validation->mswc_validate_store();

        $this->addToAssertionCount( 1 );
    }

    // -----------------------------------------------------------------------
    // mswc_validate_store_for_blocks
    // -----------------------------------------------------------------------

    public function test_validate_blocks_adds_error_when_no_session(): void {
        $wc = new \stdClass();
        $wc->session = null;
        Functions\when( 'WC' )->justReturn( $wc );

        $errors = new WP_Error();
        $result = $this->validation->mswc_validate_store_for_blocks( $errors, null );

        $this->assertTrue( $result->has_errors() );
        $this->assertContains( 'mswc_no_store', $result->get_error_codes() );
    }

    public function test_validate_blocks_adds_error_when_store_not_in_session(): void {
        // Session returns null for store key (default).
        $errors = new WP_Error();
        $result = $this->validation->mswc_validate_store_for_blocks( $errors, null );

        $this->assertTrue( $result->has_errors() );
        $this->assertContains( 'mswc_no_store', $result->get_error_codes() );
    }

    public function test_validate_blocks_adds_error_when_store_disabled(): void {
        $this->wc_session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( '1' );

        $this->expectStoreRow( [ 'id' => '1', 'name' => 'Norte', 'enabled' => '0' ] );

        $this->wc_session
            ->shouldReceive( 'set' )
            ->with( 'mswc_selected_store', null )
            ->once();

        $errors = new WP_Error();
        $result = $this->validation->mswc_validate_store_for_blocks( $errors, null );

        $this->assertTrue( $result->has_errors() );
        $this->assertContains( 'mswc_store_disabled', $result->get_error_codes() );
    }

    public function test_validate_blocks_passes_when_store_enabled(): void {
        $this->wc_session
            ->shouldReceive( 'get' )
            ->with( 'mswc_selected_store' )
            ->andReturn( '1' );

        $this->expectStoreRow( [ 'id' => '1', 'name' => 'Norte', 'enabled' => '1' ] );

        $errors = new WP_Error();
        $result = $this->validation->mswc_validate_store_for_blocks( $errors, null );

        $this->assertFalse( $result->has_errors() );
    }
}
