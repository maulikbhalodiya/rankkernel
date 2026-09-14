<?php
/**
 * SitemapCache tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\SitemapCache;

/**
 * Sitemap Cache Test.
 */
final class SitemapCacheTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'wp_rand' )->alias( static fn (): int => 12345 );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test default on cache hit skips builder.
	 */
	public function test_default_on_cache_hit_skips_builder(): void {
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );

		$store = [];

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$store ): mixed {
				return $store[ $key ] ?? $fallback;
			}
		);

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$store ): bool {
				$store[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'get_transient' )->alias(
			static function ( string $key ) use ( &$store ): mixed {
				return $store[ 'transient_' . $key ] ?? false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			static function ( string $key, mixed $value ) use ( &$store ): bool {
				$store[ 'transient_' . $key ] = $value;
				return true;
			}
		);

		Functions\when( 'add_action' )->justReturn( true );

		$cache = new SitemapCache();

		$this->assertTrue( $cache->isEnabled() );

		$calls = 0;
		$xml1  = $cache->get(
			'post',
			1,
			static function () use ( &$calls ): string {
				++$calls;
				return '<urlset>first</urlset>';
			}
		);

		$this->assertSame( '<urlset>first</urlset>', $xml1 );
		$this->assertSame( 1, $calls );

		$calls = 0;
		$xml2  = $cache->get(
			'post',
			1,
			static function () use ( &$calls ): string {
				++$calls;
				return '<urlset>second</urlset>';
			}
		);

		$this->assertSame( '<urlset>first</urlset>', $xml2 );
		$this->assertSame( 0, $calls, 'Builder should not be called on cache hit' );
	}

	/**
	 * Test validator mismatch triggers rebuild.
	 */
	public function test_validator_mismatch_triggers_rebuild(): void {
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );

		$store = [
			SitemapCache::VALIDATOR_GLOBAL          => 'old_global',
			SitemapCache::VALIDATOR_PREFIX . 'post' => 'old_set',
		];

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$store ): mixed {
				return $store[ $key ] ?? $fallback;
			}
		);

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$store ): bool {
				$store[ $key ] = $value;
				return true;
			}
		);

		// Seed cache with old validators.
		$store['transient_rankkernel_sitemap_xml_post_1'] = [
			'xml'              => '<cached>old</cached>',
			'validator_global' => 'old_global',
			'validator_set'    => 'old_set',
		];

		Functions\when( 'get_transient' )->alias(
			static function ( string $key ) use ( &$store ): mixed {
				return $store[ 'transient_' . $key ] ?? false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			static function ( string $key, mixed $value ) use ( &$store ): bool {
				$store[ 'transient_' . $key ] = $value;
				return true;
			}
		);

		Functions\when( 'add_action' )->justReturn( true );

		$cache = new SitemapCache();

		// Hit with matching validators, should return cached.
		$calls  = 0;
		$xmlHit = $cache->get(
			'post',
			1,
			static function () use ( &$calls ): string {
				++$calls;
				return '<new>rebuild</new>';
			}
		);

		$this->assertSame( '<cached>old</cached>', $xmlHit );
		$this->assertSame( 0, $calls );

		// Change global validator, now mismatch should rebuild.
		$store[ SitemapCache::VALIDATOR_GLOBAL ] = 'new_global';

		$calls      = 0;
		$xmlRebuilt = $cache->get(
			'post',
			1,
			static function () use ( &$calls ): string {
				++$calls;
				return '<new>rebuild</new>';
			}
		);

		$this->assertSame( '<new>rebuild</new>', $xmlRebuilt );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Test enable cache false bypasses cache.
	 */
	public function test_enable_cache_false_bypasses_cache(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/sitemap/enable_cache' === $hook ) {
					return false;
				}

				return $value;
			}
		);
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'add_action' )->justReturn( true );

		// set_transient should never be called when cache is disabled.
		Functions\expect( 'set_transient' )->never();
		Functions\when( 'update_option' )->justReturn( true );

		$cache = new SitemapCache();

		$this->assertFalse( $cache->isEnabled() );

		$calls = 0;
		$xml1  = $cache->get(
			'post',
			1,
			static function () use ( &$calls ): string {
				++$calls;
				return '<a>one</a>';
			}
		);
		$this->assertSame( '<a>one</a>', $xml1 );
		$this->assertSame( 1, $calls );

		$xml2 = $cache->get(
			'post',
			1,
			static function () use ( &$calls ): string {
				++$calls;
				return '<a>two</a>';
			}
		);
		$this->assertSame( '<a>two</a>', $xml2 );
		$this->assertSame( 2, $calls );
	}

	/**
	 * Test invalidation queue flushes on shutdown once.
	 */
	public function test_invalidation_queue_flushes_on_shutdown_once(): void {
		Functions\when( 'apply_filters' )->justReturn( true );
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$options = [];
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$options ): bool {
				$options[ $key ] = $value;
				return true;
			}
		);

		$shutdownCallback = null;
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, mixed $callback, int $prio = 10, int $accepted = 1 ) use ( &$shutdownCallback ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_action signature.
				if ( 'shutdown' === $hook ) {
					$shutdownCallback = $callback;
				}

				return true;
			}
		);

		$cache = new SitemapCache();
		$cache->queueInvalidation( 'global' );
		$cache->queueInvalidation( 'post' );
		$cache->queueInvalidation( 'post' );

		$this->assertIsArray( $shutdownCallback, 'Shutdown hook should be registered' );

		// First flush.
		$cache->flushQueue();

		$this->assertArrayHasKey( SitemapCache::VALIDATOR_GLOBAL, $options );
		$this->assertArrayHasKey( SitemapCache::VALIDATOR_PREFIX . 'post', $options );

		$firstGlobal = $options[ SitemapCache::VALIDATOR_GLOBAL ];
		$firstPost   = $options[ SitemapCache::VALIDATOR_PREFIX . 'post' ];

		// Second flush should do nothing.
		$cache->flushQueue();

		$this->assertSame( $firstGlobal, $options[ SitemapCache::VALIDATOR_GLOBAL ] );
		$this->assertSame( $firstPost, $options[ SitemapCache::VALIDATOR_PREFIX . 'post' ] );
	}

	/**
	 * Test get map caches until global validator changes.
	 */
	public function test_get_map_caches_until_global_validator_changes(): void {
		$options = [];
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$options ): mixed {
				return $options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$options ): bool {
				$options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'set_transient' )->alias(
			static function ( string $key, mixed $value, int $ttl = 0 ) use ( &$transients ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress set_transient signature.
				$transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			static function ( string $key ) use ( &$transients ): mixed {
				return $transients[ $key ] ?? false;
			}
		);
		$transients = [];

		$cache = new SitemapCache();

		$builds = 0;
		$map    = $cache->getMap(
			'sets',
			static function () use ( &$builds ): array {
				$builds++;

				return [ 'blog' => 3 ];
			}
		);

		$this->assertSame( [ 'blog' => 3 ], $map );
		$this->assertSame( 1, $builds );

		// Warm cache: builder not called again.
		$map = $cache->getMap(
			'sets',
			static function () use ( &$builds ): array {
				$builds++;

				return [ 'blog' => 3 ];
			}
		);
		$this->assertSame( 1, $builds );

		// Global validator change invalidates the map.
		$cache->queueInvalidation( 'global' );
		$cache->flushQueue();

		$cache->getMap(
			'sets',
			static function () use ( &$builds ): array {
				$builds++;

				return [ 'blog' => 4 ];
			}
		);
		$this->assertSame( 2, $builds );
	}
}
