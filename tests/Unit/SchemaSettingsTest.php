<?php
/**
 * Schema settings tests, identity key defaults and sanitization.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Settings\SettingsStore;

/**
 * Schema Settings Test.
 */
final class SchemaSettingsTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $v ): string {
				$v = trim( $v );

				if ( '' === $v ) {
					return '';
				}

				if ( str_starts_with( $v, 'https://' ) || str_starts_with( $v, 'http://' ) ) {
					return $v;
				}

				return '';
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test identity defaults.
	 */
	public function test_identity_defaults(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$store = new SettingsStore();
		$all   = $store->all();

		$this->assertSame( 'organization', $all['site_represents'] );
		$this->assertSame( '', $all['org_name'] );
		$this->assertSame( '', $all['org_logo'] );
		$this->assertSame( [], $all['org_sameas'] );
		$this->assertTrue( $all['website_search_action'] );
	}

	/**
	 * Test unknown site represents falls back.
	 */
	public function test_unknown_site_represents_falls_back(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$captured = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$captured ): bool {
				$captured = $value;

				return true;
			}
		);

		$store = new SettingsStore();
		$this->assertTrue( $store->set( [ 'site_represents' => 'empire' ] ) );
		$this->assertSame( 'organization', $captured['site_represents'] );

		$captured = null;
		$this->assertTrue( $store->set( [ 'site_represents' => 'person' ] ) );
		$this->assertSame( 'person', $captured['site_represents'] );
	}

	/**
	 * Test org sameas drops bad urls.
	 */
	public function test_org_sameas_drops_bad_urls(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$captured = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$captured ): bool {
				$captured = $value;

				return true;
			}
		);

		$store = new SettingsStore();
		$this->assertTrue(
			$store->set( [ 'org_sameas' => [ 'https://example.com/a', 'not-a-url', '' ] ] )
		);
		$this->assertSame( [ 'https://example.com/a' ], $captured['org_sameas'] );
	}

	/**
	 * Test org logo uses raw url sanitize.
	 */
	public function test_org_logo_uses_raw_url_sanitize(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$captured = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$captured ): bool {
				$captured = $value;

				return true;
			}
		);

		$store = new SettingsStore();
		$this->assertTrue( $store->set( [ 'org_logo' => 'not-a-url' ] ) );
		$this->assertSame( '', $captured['org_logo'] );

		$this->assertTrue( $store->set( [ 'org_logo' => 'https://example.com/logo.png' ] ) );
		$this->assertSame( 'https://example.com/logo.png', $captured['org_logo'] );
	}

	/**
	 * Test website search action bool.
	 */
	public function test_website_search_action_bool(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$captured = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$captured ): bool {
				$captured = $value;

				return true;
			}
		);

		$store = new SettingsStore();
		$this->assertTrue( $store->set( [ 'website_search_action' => 'yes' ] ) );
		$this->assertTrue( $captured['website_search_action'] );

		$this->assertTrue( $store->set( [ 'website_search_action' => false ] ) );
		$this->assertFalse( $captured['website_search_action'] );
	}
}
