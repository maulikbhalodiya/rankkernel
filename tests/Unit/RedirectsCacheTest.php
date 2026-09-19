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
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Sitemaps\SitemapCache;

/**
 * Redirects Cache Test.
 */
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

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		Functions\when( 'current_time' )->justReturn( '2026-01-01 00:00:00' );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
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

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test miss returns null without writing.
	 */
	public function test_miss_returns_null_without_writing(): void {
		$cache = new RedirectCache();

		$this->assertNull( $cache->get( '/unknown' ) );
		$this->assertSame( [], $this->transients, 'Misses must never populate storage' );
	}

	/**
	 * Test set then get returns rule.
	 */
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

	/**
	 * Test transient fallback serves new instance.
	 */
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

	/**
	 * Test invalidation drops cached rule.
	 */
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

	/**
	 * Test static invalidation bumps validator.
	 */
	public function test_static_invalidation_bumps_validator(): void {
		$before = (string) get_option( RedirectCache::VALIDATOR_OPTION, '' );

		RedirectCache::invalidateAll();

		$after = (string) ( $this->options[ RedirectCache::VALIDATOR_OPTION ] ?? '' );

		$this->assertNotSame( $before, $after );
	}

	/**
	 * Test the validator option is read once per request while memoized, and
	 * that a static invalidation forces the next read to be fresh.
	 */
	public function test_validator_option_is_memoized_within_a_request(): void {
		// Deterministic starting point: clears the process wide memo left by earlier tests.
		RedirectCache::invalidateAll();
		$this->options[ RedirectCache::VALIDATOR_OPTION ] = 'validator-1';

		$reads = 0;
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ) use ( &$reads ): mixed {
				if ( RedirectCache::VALIDATOR_OPTION === $key ) {
					++$reads;
				}

				return $this->options[ $key ] ?? $fallback;
			}
		);

		$cache = new RedirectCache();
		$cache->set( '/a', [ 'id' => 1 ] );
		$cache->set( '/b', [ 'id' => 2 ] );

		$this->assertSame( 1, $reads, 'Two writes in one request must share one validator read.' );

		RedirectCache::invalidateAll();
		$cache->set( '/c', [ 'id' => 3 ] );

		$this->assertSame( 2, $reads, 'A static invalidation must reset the memo so the next read is fresh.' );
	}

	/**
	 * Test ttl is bounded.
	 */
	public function test_ttl_is_bounded(): void {
		$cache = new RedirectCache();

		$cache->set( '/old', [ 'id' => 4 ] );

		$this->assertSame( [ RedirectCache::TTL ], array_values( $this->ttls ) );
		$this->assertSame( 43200, RedirectCache::TTL );
	}

	/**
	 * Test group is separate from sitemaps.
	 */
	public function test_group_is_separate_from_sitemaps(): void {
		$this->assertNotSame( SitemapCache::GROUP, RedirectCache::GROUP );
		$this->assertSame( 'rankkernel-redirects', RedirectCache::GROUP );
	}

	/**
	 * Test keys are stable and distinct.
	 */
	public function test_keys_are_stable_and_distinct(): void {
		$this->assertSame( RedirectCache::cacheKey( '/old' ), RedirectCache::cacheKey( '/old' ) );
		$this->assertNotSame( RedirectCache::cacheKey( '/old' ), RedirectCache::cacheKey( '/new' ) );
	}

	/**
	 * Test a stored pattern set survives across requests without a new read.
	 */
	public function test_stored_rule_served_from_cache_without_new_database_read(): void {
		$db          = new RedirectsFakeDb();
		$db->rows[1] = [
			'id'            => 1,
			'match_type'    => 'prefix',
			'source_hash'   => hash( 'sha256', 'prefix|/blog' ),
			'source'        => '/blog',
			'target'        => '/news',
			'code'          => '301',
			'is_active'     => 1,
			'hits'          => 0,
			'last_accessed' => null,
		];

		$repo  = new RedirectRepository( $db, new RedirectCache() );
		$first = $repo->all_patterns();

		$this->assertCount( 1, $first );
		$readsAfterFirst = $db->ruleReads;

		$second = $repo->all_patterns();

		$this->assertSame( $readsAfterFirst, $db->ruleReads, 'A warm lookup must be served from cache without a new database read' );
		$this->assertSame( $first, $second );

		// A fresh cache instance models the next request, the transient survives.
		$nextRequest = new RedirectRepository( $db, new RedirectCache() );

		$this->assertSame( $first, $nextRequest->all_patterns() );
		$this->assertSame( $readsAfterFirst, $db->ruleReads, 'The cache must survive across requests without a new database read' );
	}

	/**
	 * Test saving, updating and deleting a rule each invalidate the cache.
	 */
	public function test_saving_updating_and_deleting_a_rule_invalidates_the_cache(): void {
		$db    = new RedirectsFakeDb();
		$cache = new RedirectCache();
		$repo  = new RedirectRepository( $db, $cache );

		$rule = [
			'id'     => 1,
			'source' => '/old',
			'target' => '/new',
			'code'   => '301',
		];

		$cache->set( '/old', $rule );

		$this->assertSame( $rule, $cache->get( '/old' ) );

		$id = $repo->insert(
			[
				'source' => '/a',
				'target' => '/b',
			]
		);

		$this->assertGreaterThan( 0, $id );
		$this->assertNull( $cache->get( '/old' ), 'A create must invalidate the cached matches' );

		$cache->set( '/old', $rule );

		$this->assertTrue( $repo->update( $id, [ 'target' => '/c' ] ) );
		$this->assertNull( $cache->get( '/old' ), 'An update must invalidate the cached matches' );

		$cache->set( '/old', $rule );

		$this->assertTrue( $repo->delete( $id ) );
		$this->assertNull( $cache->get( '/old' ), 'A delete must invalidate the cached matches' );
	}
}
