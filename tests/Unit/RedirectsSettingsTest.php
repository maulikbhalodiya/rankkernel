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
		RedirectsSettings::resetCache();

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
		RedirectsSettings::resetCache();
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

	/**
	 * Test request-level static memoization across instances and resetCache().
	 */
	public function test_static_request_level_memoization_and_reset(): void {
		$calls = 0;
		$value = [ 'preserve_query' => false ];
		Functions\when( 'get_current_blog_id' )->justReturn( 10 );
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ) use ( &$calls, &$value ): mixed {
				if ( RedirectsSettings::OPTION === $key ) {
					++$calls;
					return $value;
				}
				return $fallback;
			}
		);

		$instance1 = new RedirectsSettings();
		$this->assertFalse( $instance1->get( 'preserve_query' ) );
		$this->assertSame( 1, $calls );

		// Multiple instances share static request-level memoization for blog 10.
		$instance2 = new RedirectsSettings();
		$this->assertFalse( $instance2->get( 'preserve_query' ) );
		$this->assertSame( 1, $calls );

		// Mutate underlying stored option to test resetCache() re-fetching fresh value.
		$value = [ 'preserve_query' => true ];
		RedirectsSettings::resetCache();

		$instance3 = new RedirectsSettings();
		$this->assertTrue( $instance3->get( 'preserve_query' ) );
		$this->assertSame( 2, $calls );
	}

	/**
	 * Test set() updates both instance and static request-level cache.
	 */
	public function test_set_updates_static_and_instance_cache(): void {
		$store = [];
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$store ): mixed {
				return $store[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $val ) use ( &$store ): bool {
				$store[ $key ] = $val;
				return true;
			}
		);

		$instance1 = new RedirectsSettings();
		$instance1->set( [ 'preserve_query' => false ] );
		$this->assertFalse( $instance1->get( 'preserve_query' ) );

		$calls = 0;
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$calls, &$store ): mixed {
				if ( RedirectsSettings::OPTION === $key ) {
					++$calls;
				}
				return $store[ $key ] ?? $fallback;
			}
		);

		// Second instance created afterwards reads updated static cache without get_option.
		$instance2 = new RedirectsSettings();
		$this->assertFalse( $instance2->get( 'preserve_query' ) );
		$this->assertSame( 0, $calls );
	}

	/**
	 * Test an unavailable blog id never shares a static cache bucket.
	 */
	public function test_unknown_blog_id_does_not_share_static_cache(): void {
		RedirectsSettings::resetCache();

		Functions\when( 'get_current_blog_id' )->alias(
			static function (): int {
				throw new \RuntimeException( 'No blog context.' );
			}
		);

		$calls  = 0;
		$values = [
			[ 'preserve_query' => true ],
			[ 'preserve_query' => false ],
		];

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$calls, $values ): mixed {
				if ( RedirectsSettings::OPTION !== $key ) {
					return $fallback;
				}

				return $values[ $calls++ ] ?? [];
			}
		);

		$first = new RedirectsSettings();
		$this->assertTrue( $first->get( 'preserve_query' ) );

		$second = new RedirectsSettings();
		$this->assertFalse( $second->get( 'preserve_query' ), 'Unknown contexts must not share the static cache.' );
		$this->assertSame( 2, $calls );
	}

	/**
	 * Test multisite isolation across switch_to_blog().
	 */
	public function test_multisite_blog_isolation(): void {
		$blogId = 1;
		Functions\when( 'get_current_blog_id' )->alias(
			static function () use ( &$blogId ): int {
				return $blogId;
			}
		);
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$blogId ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors WordPress signature.
				if ( RedirectsSettings::OPTION !== $key ) {
					return $fallback;
				}

				if ( 1 === $blogId ) {
					return [ 'preserve_query' => true ];
				}

				return [ 'preserve_query' => false ];
			}
		);

		$instance = new RedirectsSettings();
		$this->assertTrue( $instance->get( 'preserve_query' ) );

		$blogId = 2;
		$this->assertFalse( $instance->get( 'preserve_query' ) );
	}
}
