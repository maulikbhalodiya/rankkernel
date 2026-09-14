<?php
/**
 * Redirect cache tests, positive only plus invalidation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Sitemaps\SitemapCache;

final class RedirectsCacheTest extends TestCase {
	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Captured transient TTLs.
	 *
	 * @var array<string, int>
	 */
	private array $ttls = [];

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value, int $ttl = 0 ): bool {
				$this->transients[ $key ] = $value;
				$this->ttls[ $key ]       = $ttl;

				return true;
			}
		);
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_miss_returns_null_without_writing(): void {
		$cache = new RedirectCache();

		$this->assertNull( $cache->get( '/unknown' ) );
		$this->assertSame( [], $this->transients, 'Misses must never populate storage' );
	}

	public function test_set_then_get_returns_rule(): void {
		$cache = new RedirectCache();
		$rule  = [
			'id'     => 1,
			'source' => '/old',
			'target' => '/new',
			'code'   => '301',
		];

		$cache->set( '/old', $rule );

		$this->assertSame( $rule, $cache->get( '/old' ) );
	}

	public function test_transient_fallback_serves_new_instance(): void {
		$cache = new RedirectCache();
		$rule  = [
			'id'     => 2,
			'source' => '/old',
			'target' => '/new',
			'code'   => '302',
		];

		$cache->set( '/old', $rule );

		$second = new RedirectCache();

		$this->assertSame( $rule, $second->get( '/old' ) );
	}

	public function test_invalidation_drops_cached_rule(): void {
		$cache = new RedirectCache();
		$rule  = [
			'id'     => 3,
			'source' => '/old',
			'target' => '/new',
			'code'   => '301',
		];

		$cache->set( '/old', $rule );
		$cache->invalidate();

		$fresh = new RedirectCache();

		$this->assertNull( $fresh->get( '/old' ) );
	}

	public function test_static_invalidation_bumps_validator(): void {
		$before = (string) get_option( RedirectCache::VALIDATOR_OPTION, '' );

		RedirectCache::invalidateAll();

		$after = (string) ( $this->options[ RedirectCache::VALIDATOR_OPTION ] ?? '' );

		$this->assertNotSame( $before, $after );
	}

	public function test_ttl_is_bounded(): void {
		$cache = new RedirectCache();

		$cache->set( '/old', [ 'id' => 4 ] );

		$this->assertSame( [ RedirectCache::TTL ], array_values( $this->ttls ) );
		$this->assertSame( 43200, RedirectCache::TTL );
	}

	public function test_group_is_separate_from_sitemaps(): void {
		$this->assertNotSame( SitemapCache::GROUP, RedirectCache::GROUP );
		$this->assertSame( 'rankkernel-redirects', RedirectCache::GROUP );
	}

	public function test_keys_are_stable_and_distinct(): void {
		$this->assertSame( RedirectCache::cacheKey( '/old' ), RedirectCache::cacheKey( '/old' ) );
		$this->assertNotSame( RedirectCache::cacheKey( '/old' ), RedirectCache::cacheKey( '/new' ) );
	}
}
