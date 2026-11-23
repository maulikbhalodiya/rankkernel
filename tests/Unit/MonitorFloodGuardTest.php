<?php
/**
 * 404 flood guard tests, budget cap plus window reset.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\FloodGuard;
use RankKernel\Modules\Monitor\MonitorSettings;

/**
 * Monitor Flood Guard Test.
 */
final class MonitorFloodGuardTest extends TestCase {
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
	 * Object-cache store used when simulating a persistent cache.
	 *
	 * @var array<string, mixed>
	 */
	private array $objectCache = [];

	/**
	 * Whether the test simulates an external object cache.
	 *
	 * @var bool
	 */
	private bool $extCache = false;

	/**
	 * Current blog id for the test.
	 *
	 * @var int
	 */
	private int $blogId = 1;

	/**
	 * Recorded set_transient TTLs by key.
	 *
	 * @var array<string, int>
	 */
	private array $transientTtls = [];

	/**
	 * Number of set_transient() calls in the current test.
	 *
	 * @var int
	 */
	private int $setTransientCalls = 0;

	/**
	 * Optional hook fired before a transient write, to simulate an interleaved
	 * concurrent request committing its own read-modify-write first.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $beforeTransientWrite = null;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->options              = [];
		$this->transients           = [];
		$this->objectCache          = [];
		$this->extCache             = false;
		$this->blogId               = 1;
		$this->transientTtls        = [];
		$this->setTransientCalls    = 0;
		$this->beforeTransientWrite = null;

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
		Functions\when( 'delete_option' )->alias(
			function ( string $key ): bool {
				unset( $this->options[ $key ] );

				return true;
			}
		);
		// Model WordPress's real transient delegation: with a persistent object
		// cache, set_transient/get_transient store the value in the object cache
		// under the 'transient' group rather than in the options table.
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				if ( $this->extCache ) {
					return $this->objectCache[ $key ] ?? false;
				}

				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value, int $ttl = 0 ): bool {
				++$this->setTransientCalls;

				if ( null !== $this->beforeTransientWrite ) {
					$hook                       = $this->beforeTransientWrite;
					$this->beforeTransientWrite = null;
					$hook();
				}

				$this->transientTtls[ $key ] = $ttl;

				if ( $this->extCache ) {
					$this->objectCache[ $key ] = $value;

					return true;
				}

				$this->transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_using_ext_object_cache' )->alias(
			function (): bool {
				return $this->extCache;
			}
		);
		Functions\when( 'get_current_blog_id' )->alias(
			function (): int {
				return $this->blogId;
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
	 * Test budget caps new uris and sets marker.
	 */
	public function test_budget_caps_new_uris_and_sets_marker(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 2 ];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertTrue( $guard->allowNew() );
		$this->assertFalse( $guard->allowNew() );
		$this->assertFalse( $guard->allowNew() );

		$this->assertArrayHasKey( FloodGuard::SUPPRESSED_OPTION, $this->options );
		$this->assertTrue( FloodGuard::isSuppressed() );
	}

	/**
	 * Test expired window resets the budget.
	 */
	public function test_expired_window_resets_the_budget(): void {
		$this->options['rankkernel_404_settings']  = [
			'flood_budget' => 1,
			'flood_window' => 300,
		];
		$this->transients['rankkernel_404flood_1'] = [
			'start' => time() - 400,
			'count' => 1,
		];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertFalse( FloodGuard::isSuppressed() );
	}

	/**
	 * Test marker clears.
	 */
	public function test_marker_clears(): void {
		$this->options[ FloodGuard::SUPPRESSED_OPTION ] = time();

		$this->assertTrue( FloodGuard::isSuppressed() );

		FloodGuard::clearSuppressed();

		$this->assertFalse( FloodGuard::isSuppressed() );
	}

	/**
	 * Test state is keyed per site.
	 */
	public function test_state_is_keyed_per_site(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 1 ];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertArrayHasKey( 'rankkernel_404flood_1', $this->transients );
	}

	/**
	 * A fresh guard starts a new window at count 1.
	 */
	public function test_new_window_starts_at_count_one(): void {
		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );

		$state = $this->transients['rankkernel_404flood_1'];

		$this->assertSame( 1, $state['count'] );
		$this->assertLessThanOrEqual( time(), $state['start'] );
	}

	/**
	 * Existing state is read from the transient and decremented remaining budget.
	 */
	public function test_existing_state_read_from_transient(): void {
		$this->options['rankkernel_404_settings']  = [ 'flood_budget' => 2 ];
		$this->transients['rankkernel_404flood_1'] = [
			'start' => time(),
			'count' => 1,
		];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertSame( 2, $this->transients['rankkernel_404flood_1']['count'] );
		$this->assertFalse( $guard->allowNew() );
	}

	/**
	 * The transient key and TTL are preserved across the storage change.
	 */
	public function test_transient_key_and_ttl_preserved(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_window' => 300 ];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertArrayHasKey( 'rankkernel_404flood_1', $this->transientTtls );
		$this->assertSame( 300, $this->transientTtls['rankkernel_404flood_1'] );
	}

	/**
	 * Persistent object cache: FloodGuard performs one transient write and no
	 * direct wp_cache_* calls; set_transient delegates storage to the cache.
	 *
	 * WordPress stores transients in the object cache when a persistent cache
	 * is active, so the single set_transient() call is the one storage write.
	 */
	public function test_persistent_cache_uses_single_transient_write_without_direct_cache_calls(): void {
		$this->extCache = true;

		Functions\expect( 'wp_cache_set' )->never();
		Functions\expect( 'wp_cache_get' )->never();

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertSame( 1, $this->setTransientCalls, 'Exactly one transient write' );
		$this->assertArrayHasKey( 'rankkernel_404flood_1', $this->objectCache );
		$this->assertSame( 1, $this->objectCache['rankkernel_404flood_1']['count'] );
		// The database-backed path was not touched.
		$this->assertArrayNotHasKey( 'rankkernel_404flood_1', $this->transients );
	}

	/**
	 * Database-backed transient path still works without an object cache.
	 */
	public function test_database_backed_transient_path_works(): void {
		$this->extCache = false;

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertArrayHasKey( 'rankkernel_404flood_1', $this->transients );
		$this->assertArrayNotHasKey( 'rankkernel_404flood_1', $this->objectCache );
	}

	/**
	 * Different site ids use separate keys.
	 */
	public function test_different_site_ids_use_separate_keys(): void {
		$guard = new FloodGuard( new MonitorSettings() );

		$this->blogId = 1;
		$this->assertTrue( $guard->allowNew() );

		$this->blogId = 2;
		$this->assertTrue( $guard->allowNew() );

		$this->assertArrayHasKey( 'rankkernel_404flood_1', $this->transients );
		$this->assertArrayHasKey( 'rankkernel_404flood_2', $this->transients );
		$this->assertSame( 1, $this->transients['rankkernel_404flood_1']['count'] );
		$this->assertSame( 1, $this->transients['rankkernel_404flood_2']['count'] );
	}

	/**
	 * Suppression marker behavior is unchanged by the storage refactor.
	 */
	public function test_suppression_marker_behavior_unchanged(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 1 ];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertFalse( FloodGuard::isSuppressed() );
		$this->assertFalse( $guard->allowNew() );
		$this->assertTrue( FloodGuard::isSuppressed() );

		FloodGuard::clearSuppressed();
		$this->assertFalse( FloodGuard::isSuppressed() );
	}

	/**
	 * Documented limitation: concurrent admissions are NOT strictly capped.
	 *
	 * The state update is a non-atomic read-modify-write, so two workers can
	 * both read the same count and both write count+1, admitting one URI over
	 * the budget. This test records the current best-effort behavior; it is a
	 * known limitation, not an atomic guarantee. The strict-concurrency redesign
	 * is deliberately out of scope for the duplicate-storage fix.
	 */
	public function test_concurrent_admissions_can_exceed_budget_best_effort(): void {
		$this->options['rankkernel_404_settings']  = [ 'flood_budget' => 2 ];
		$this->transients['rankkernel_404flood_1'] = [
			'start' => time(),
			'count' => 1,
		];

		$guardA = new FloodGuard( new MonitorSettings() );
		$guardB = new FloodGuard( new MonitorSettings() );

		// guardB reads the stale count=1 during guardA's write, so both are
		// admitted and both write count=2. Two admissions, one net increment.
		$this->beforeTransientWrite = function () use ( $guardB ): void {
			$this->assertTrue( $guardB->allowNew() );
		};

		$this->assertTrue( $guardA->allowNew() );
		$this->assertSame( 2, $this->transients['rankkernel_404flood_1']['count'] );
	}
}
