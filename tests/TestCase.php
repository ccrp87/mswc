<?php

namespace MSWC\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for all MSWC unit tests.
 *
 * Sets up Brain Monkey (WordPress function stubs) and Mockery before each test,
 * and tears them down afterwards.
 */
abstract class TestCase extends PHPUnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Stub the most common WordPress/WooCommerce functions so individual
        // tests don't need to re-declare them every time.
        Functions\stubs( [
            'add_filter'         => true,
            'add_action'         => true,
            'add_shortcode'      => true,
            'is_admin'           => false,
            'wp_doing_ajax'      => false,
            'esc_html'           => static fn( string $s ): string => $s,
            'esc_attr'           => static fn( string $s ): string => $s,
            'esc_html__'         => static fn( string $s ): string => $s,
            '__'                 => static fn( string $s ): string => $s,
            // NOTE: wc_add_notice is intentionally NOT stubbed here.
            // Tests that need to assert on it use Functions\expect() directly,
            // and the native stub in tests/stubs/woocommerce.php provides the
            // no-op default for tests that don't care about this call.
            'get_edit_post_link' => static fn( int $id ): string => 'https://ex.com/?p=' . $id . '&action=edit',
            'admin_url'          => static fn(): string => 'https://ex.com/wp-admin/',
        ] );
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }
}
