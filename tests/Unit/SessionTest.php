<?php

namespace MSWC\Tests\Unit;

use Brain\Monkey\Functions;
use MSWC\Tests\TestCase;
use MSWC_Session;
use Mockery;

/**
 * Unit tests for MSWC_Session.
 *
 * Tests cover mswc_set_customer_session() — the method that forces a
 * WooCommerce session cookie for anonymous visitors.
 */
class SessionTest extends TestCase {

    private MSWC_Session $session_handler;

    /** @var \Mockery\MockInterface&\WC_Session_Handler */
    private object $wc_session;

    /** @var object{session: \Mockery\MockInterface} */
    private object $wc;

    protected function setUp(): void {
        parent::setUp();

        $this->session_handler = new MSWC_Session();

        $this->wc_session = Mockery::mock( 'WC_Session_Handler' );

        $this->wc = new \stdClass();
        $this->wc->session = $this->wc_session;

        Functions\when( 'WC' )->justReturn( $this->wc );
    }

    // -----------------------------------------------------------------------
    // mswc_set_customer_session
    // -----------------------------------------------------------------------

    public function test_set_customer_session_skips_when_is_admin(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        $this->wc_session->shouldNotReceive( 'set_customer_session_cookie' );

        $this->session_handler->mswc_set_customer_session();

        $this->addToAssertionCount( 1 );
    }

    public function test_set_customer_session_skips_when_doing_ajax(): void {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'wp_doing_ajax' )->justReturn( true );

        $this->wc_session->shouldNotReceive( 'set_customer_session_cookie' );

        $this->session_handler->mswc_set_customer_session();

        $this->addToAssertionCount( 1 );
    }

    public function test_set_customer_session_forces_cookie_when_no_session(): void {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        $this->wc_session
            ->shouldReceive( 'has_session' )
            ->once()
            ->andReturn( false );

        $this->wc_session
            ->shouldReceive( 'set_customer_session_cookie' )
            ->with( true )
            ->once();

        $this->session_handler->mswc_set_customer_session();

        $this->addToAssertionCount( 1 );
    }

    public function test_set_customer_session_does_nothing_when_session_exists(): void {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        $this->wc_session
            ->shouldReceive( 'has_session' )
            ->once()
            ->andReturn( true );

        $this->wc_session->shouldNotReceive( 'set_customer_session_cookie' );

        $this->session_handler->mswc_set_customer_session();

        $this->addToAssertionCount( 1 );
    }

    public function test_set_customer_session_does_nothing_when_wc_session_is_null(): void {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );

        $wc = new \stdClass();
        $wc->session = null;
        Functions\when( 'WC' )->justReturn( $wc );

        // Should not throw — the method checks isset(WC()->session).
        $this->session_handler->mswc_set_customer_session();

        // If no exception was thrown, the test passes.
        $this->addToAssertionCount( 1 );
    }
}
