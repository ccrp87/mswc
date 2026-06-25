<?php

namespace MSWC\Tests\Unit;

use MSWC\Tests\TestCase;
use MSWC_Plugin;

/**
 * Unit tests for MSWC_Plugin stub (get_active_stores).
 *
 * Since the real MSWC_Plugin main file requires a full WordPress environment
 * to instantiate, the test suite uses a configurable stub class defined in
 * tests/stubs/woocommerce.php.  These tests verify that the stub behaves
 * correctly, which is the contract the rest of the test suite relies on.
 */
class PluginTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // Ensure a clean slate before each test.
        MSWC_Plugin::$active_stores = [];
    }

    protected function tearDown(): void {
        MSWC_Plugin::$active_stores = [];
        parent::tearDown();
    }

    public function test_get_active_stores_returns_empty_array_by_default(): void {
        $this->assertSame( [], MSWC_Plugin::get_active_stores() );
    }

    public function test_get_active_stores_returns_configured_stores(): void {
        $stores = [
            [ 'id' => '1', 'code' => 'norte', 'name' => 'Norte' ],
        ];

        MSWC_Plugin::$active_stores = $stores;

        $this->assertSame( $stores, MSWC_Plugin::get_active_stores() );
    }

    public function test_get_active_stores_returns_multiple_stores(): void {
        $stores = [
            [ 'id' => '1', 'code' => 'norte', 'name' => 'Norte' ],
            [ 'id' => '2', 'code' => 'sur',   'name' => 'Sur'   ],
        ];

        MSWC_Plugin::$active_stores = $stores;

        $result = MSWC_Plugin::get_active_stores();

        $this->assertCount( 2, $result );
        $this->assertSame( 'norte', $result[0]['code'] );
        $this->assertSame( 'sur',   $result[1]['code'] );
    }

    public function test_get_active_stores_can_be_reset_to_empty(): void {
        MSWC_Plugin::$active_stores = [
            [ 'id' => '1', 'code' => 'norte', 'name' => 'Norte' ],
        ];

        MSWC_Plugin::$active_stores = [];

        $this->assertSame( [], MSWC_Plugin::get_active_stores() );
    }
}
