<?php
/**
 * Redirects module settings tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\RedirectsSettings;

/**
 * Redirects Settings Test.
 */
final class RedirectsSettingsTest extends TestCase {
	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Captured update option calls.
	 *
	 * @var array<int, array{option: string, value: mixed, autoload: mixed}>
	 */
	private array $updates = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool {
				$this->options[ $key ] = $value;
				$this->updates[]       = [
					'option'   => $key,
					'value'    => $value,
					'autoload' => $autoload,
				];

				return true;
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
	 * Test defaults.
	 */
	public function test_defaults(): void {
		$settings = new RedirectsSettings();

		$this->assertTrue( $settings->get( 'preserve_query' ) );
		$this->assertTrue( $settings->get( 'auto_slug_redirect' ) );
		$this->assertSame( 20, $settings->get( 'rules_per_page' ) );
		$this->assertFalse( $settings->get( 'schema_ok' ) );
		$this->assertSame( 'fallback', $settings->get( 'missing', 'fallback' ) );
	}

	/**
	 * Test option name.
	 */
	public function test_option_name(): void {
		$this->assertSame( 'rankkernel_redirects_settings', RedirectsSettings::OPTION );
	}

	/**
	 * Test set whitelists and disables autoload.
	 */
	public function test_set_whitelists_and_disables_autoload(): void {
		$settings = new RedirectsSettings();

		$result = $settings->set(
			[
				'preserve_query' => false,
				'evil_key'       => 'x',
			]
		);

		$this->assertTrue( $result );
		$this->assertFalse( $settings->get( 'preserve_query' ) );
		$this->assertCount( 1, $this->updates );
		$this->assertSame( RedirectsSettings::OPTION, $this->updates[0]['option'] );
		$this->assertFalse( $this->updates[0]['autoload'] );
		$this->assertArrayNotHasKey( 'evil_key', (array) $this->updates[0]['value'] );
	}

	/**
	 * Test set unknown only returns false without write.
	 */
	public function test_set_unknown_only_returns_false_without_write(): void {
		Functions\expect( 'update_option' )->never();

		$settings = new RedirectsSettings();

		$this->assertFalse( $settings->set( [ 'evil_key' => 'x' ] ) );
	}

	/**
	 * Test rules per page clamped.
	 */
	public function test_rules_per_page_clamped(): void {
		$settings = new RedirectsSettings();

		$settings->set( [ 'rules_per_page' => 0 ] );

		$this->assertSame( 1, $settings->get( 'rules_per_page' ) );

		$settings->set( [ 'rules_per_page' => 500 ] );

		$this->assertSame( 100, $settings->get( 'rules_per_page' ) );

		$settings->set( [ 'rules_per_page' => 50 ] );

		$this->assertSame( 50, $settings->get( 'rules_per_page' ) );
	}

	/**
	 * Test boolean strings normalized.
	 */
	public function test_boolean_strings_normalized(): void {
		$settings = new RedirectsSettings();

		$settings->set( [ 'preserve_query' => '0' ] );

		$this->assertFalse( $settings->get( 'preserve_query' ) );
	}

	/**
	 * Test ensure schema seeds defaults.
	 */
	public function test_ensure_schema_seeds_defaults(): void {
		$settings = new RedirectsSettings();

		$settings->ensureSchema();

		$this->assertTrue( $settings->get( 'preserve_query' ) );
		$this->assertCount( 1, $this->updates );
		$this->assertFalse( $this->updates[0]['autoload'] );
	}

	/**
	 * Test ensure schema keeps stored values.
	 */
	public function test_ensure_schema_keeps_stored_values(): void {
		$this->options[ RedirectsSettings::OPTION ] = [ 'preserve_query' => false ];

		$settings = new RedirectsSettings();

		$settings->ensureSchema();

		$this->assertFalse( $settings->get( 'preserve_query' ) );
		$this->assertTrue( $settings->get( 'auto_slug_redirect' ) );
		$this->assertSame( [], $this->updates );
	}
}
