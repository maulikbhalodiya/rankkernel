<?php
/**
 * MigrationRunner tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Database\Migrations\MigrationRunner;

/**
 * Migration Runner Test.
 */
final class MigrationRunnerTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
			define( 'RANKKERNEL_VERSION', '0.1.0' );
		}

		// The runner stamps a recorded failure with a timestamp.
		Functions\when( 'current_time' )->justReturn( '2026-01-01 00:00:00' );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test no pending ledger current single get option.
	 */
	public function test_no_pending_ledger_current_single_get_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( MigrationRunner::LEDGER, '0.0.0' )
			->andReturn( '0.1.0' );

		Functions\expect( 'update_option' )->never();

		$runner = new MigrationRunner();
		$called = false;
		$runner->register(
			'0.1.0',
			static function () use ( &$called ): void {
				$called = true;
			}
		);

		$runner->maybeRun();

		$this->assertFalse( $called, 'Migration at current version must not run' );
	}

	/**
	 * Test two pending run in ascending version order.
	 */
	public function test_two_pending_run_in_ascending_version_order(): void {
		Functions\when( 'get_option' )->justReturn( '0.0.0' );

		$order = [];
		$saved = [];

		Functions\when( 'update_option' )->alias(
			static function ( string $option, mixed $value, mixed $autoload = null ) use ( &$saved ): bool {
				unset( $option, $autoload );

				$saved[] = $value;

				return true;
			}
		);

		$runner = new MigrationRunner();
		// Register out of order to verify sorting.
		$runner->register(
			'0.2.0',
			static function () use ( &$order ): void {
				$order[] = '0.2.0';
			}
		);
		$runner->register(
			'0.1.0',
			static function () use ( &$order ): void {
				$order[] = '0.1.0';
			}
		);

		$runner->maybeRun();

		$this->assertSame( [ '0.1.0', '0.2.0' ], $order );
		// Each success is persisted before the next migration runs.
		$this->assertSame( [ '0.1.0', '0.2.0' ], $saved );
	}

	/**
	 * A success before a failure is persisted, and only the failure onward retries.
	 */
	public function test_failure_at_version_n_persists_the_versions_before_n(): void {
		$store    = '0.0.0';
		$saved    = [];
		$failures = [];

		Functions\when( 'get_option' )->alias(
			static function ( string $option, mixed $fallback = false ) use ( &$store, &$failures ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_option signature.
				if ( MigrationRunner::FAILURES === $option ) {
					return $failures;
				}

				return $store;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $option, mixed $value, mixed $autoload = null ) use ( &$store, &$saved, &$failures ): bool {
				unset( $autoload );

				if ( MigrationRunner::FAILURES === $option ) {
					$failures = is_array( $value ) ? $value : [];

					return true;
				}

				$store   = $value;
				$saved[] = $value;

				return true;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'wp_trigger_error' )->justReturn( null );

		$fail      = true;
		$firstRuns = [];

		$first = new MigrationRunner();
		$first->register(
			'0.1.0',
			static function () use ( &$firstRuns ): void {
				$firstRuns[] = '0.1.0';
			}
		);
		$first->register(
			'0.2.0',
			static function () use ( &$fail ): void {
				if ( $fail ) {
					throw new \RuntimeException( 'boom' );
				}
			}
		);
		$first->register(
			'0.3.0',
			static function () use ( &$firstRuns ): void {
				$firstRuns[] = '0.3.0';
			}
		);

		$first->maybeRun();

		$this->assertSame( [ '0.1.0' ], $firstRuns, 'Migrations after the failure must not run' );
		$this->assertSame( [ '0.1.0' ], $saved, 'The success before the failure must be persisted' );
		$this->assertSame( '0.1.0', $store, 'Storage holds the version before the failure' );
		$this->assertSame( '0.1.0', $first->currentVersion(), 'The in memory version matches storage' );

		// The failure is now recorded, so it survives log rotation and is
		// visible to the operator instead of only ever reaching the PHP log.
		$this->assertArrayHasKey( '0.2.0', $failures, 'The failed version must be persisted' );
		$this->assertSame( 1, (int) $failures['0.2.0']['attempts'] );
		$this->assertSame( 'boom', (string) $failures['0.2.0']['message'] );

		// The next request starts from the persisted ledger and retries from the failure.
		$fail       = false;
		$secondRuns = [];

		$second = new MigrationRunner();
		$second->register(
			'0.1.0',
			static function () use ( &$secondRuns ): void {
				$secondRuns[] = '0.1.0';
			}
		);
		$second->register(
			'0.2.0',
			static function () use ( &$secondRuns ): void {
				$secondRuns[] = '0.2.0';
			}
		);
		$second->register(
			'0.3.0',
			static function () use ( &$secondRuns ): void {
				$secondRuns[] = '0.3.0';
			}
		);

		$second->maybeRun();

		$this->assertSame( [ '0.2.0', '0.3.0' ], $secondRuns, 'Only the failed version and later must retry' );
		$this->assertSame( '0.3.0', $store );

		// Once the version runs cleanly its failure record is dropped, so a
		// healthy ledger leaves no stale error on the dashboard.
		$this->assertSame( [], $failures, 'A version that ran cleanly keeps no failure record' );
	}

	/**
	 * A failed ledger write must not advance the in memory version.
	 */
	public function test_ledger_does_not_advance_in_memory_when_the_write_fails(): void {
		Functions\when( 'get_option' )->justReturn( '0.0.0' );

		Functions\expect( 'update_option' )
			->once()
			->with( MigrationRunner::LEDGER, '0.1.0', false )
			->andReturn( false );

		$ran = false;

		$runner = new MigrationRunner();
		$runner->register(
			'0.1.0',
			static function () use ( &$ran ): void {
				$ran = true;
			}
		);

		$runner->maybeRun();

		$this->assertTrue( $ran, 'The migration itself still runs' );
		$this->assertSame( '0.0.0', $runner->currentVersion(), 'A write that did not land must not be claimed in memory' );
	}

	/**
	 * Test migration failure fires action and does not advance ledger.
	 */
	public function test_migration_failure_fires_action_and_does_not_advance_ledger(): void {
		Functions\when( 'get_option' )->justReturn( '0.0.0' );

		$throwable = new \RuntimeException( 'boom' );

		$runner = new MigrationRunner();
		$runner->register(
			'0.1.0',
			static function () use ( $throwable ): void {
				throw $throwable;
			}
		);

		$secondCalled = false;
		$runner->register(
			'0.2.0',
			static function () use ( &$secondCalled ): void {
				$secondCalled = true;
			}
		);

		$actionFired = false;
		$actionArgs  = [];

		Functions\when( 'do_action' )->alias(
			static function ( string $hook, ...$args ) use ( &$actionFired, &$actionArgs ): void {
				if ( 'rankkernel/migration/failed' === $hook ) {
					$actionFired = true;
					$actionArgs  = $args;
				}
			}
		);

		$triggered = false;

		Functions\when( 'wp_trigger_error' )->alias(
			static function ( string $functionName, string $message, int $type ) use ( &$triggered ): void {
				$triggered = true;
				// Verify sanitized plain message passed through.
				if ( 'boom' !== $message ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test-only exception message, never rendered to the browser.
					throw new \RuntimeException( 'Unexpected wp_trigger_error message: ' . $message );
				}
				if ( E_USER_WARNING !== $type ) {
					throw new \RuntimeException( 'Unexpected error type' );
				}
			}
		);

		// The ledger is never written on failure, but the failure itself is
		// now persisted so the operator can see it and clear it.
		$failureWrites = 0;

		Functions\expect( 'update_option' )
			->with( MigrationRunner::LEDGER, \Mockery::any(), \Mockery::any() )
			->never();

		Functions\expect( 'update_option' )
			->with( MigrationRunner::FAILURES, \Mockery::type( 'array' ), false )
			->andReturnUsing(
				static function () use ( &$failureWrites ): bool {
					++$failureWrites;

					return true;
				}
			)
			->once();

		$runner->maybeRun();

		$this->assertTrue( $actionFired, 'rankkernel/migration/failed action should fire' );
		$this->assertSame( '0.1.0', $actionArgs[0] ?? null );
		$this->assertSame( $throwable, $actionArgs[1] ?? null );
		$this->assertTrue( $triggered, 'wp_trigger_error should be called' );
		$this->assertFalse( $secondCalled, 'Migrations after failure must not run' );
		$this->assertSame( 1, $failureWrites, 'The failure must be persisted once' );
	}

	/**
	 * Test a repeated failure stops emitting the per request warning.
	 *
	 * A permanently failing migration used to write an E_USER_WARNING on
	 * every request, front and back, forever, inside the component whose
	 * job is bloat prevention.
	 */
	public function test_repeated_failure_caps_the_warning(): void {
		$store    = '0.1.0';
		$failures = [];
		$warnings = 0;
		$actions  = 0;

		Functions\when( 'get_option' )->alias(
			static function ( string $option, mixed $fallback = false ) use ( &$store, &$failures ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_option signature.
				if ( MigrationRunner::FAILURES === $option ) {
					return $failures;
				}

				return $store;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $option, mixed $value, mixed $autoload = null ) use ( &$store, &$failures ): bool {
				unset( $autoload );

				if ( MigrationRunner::FAILURES === $option ) {
					$failures = is_array( $value ) ? $value : [];

					return true;
				}

				$store = $value;

				return true;
			}
		);
		Functions\when( 'do_action' )->alias(
			static function ( string $hook ) use ( &$actions ): void {
				if ( 'rankkernel/migration/failed' === $hook ) {
					++$actions;
				}
			}
		);
		Functions\when( 'wp_trigger_error' )->alias(
			static function () use ( &$warnings ): void {
				++$warnings;
			}
		);

		$total = MigrationRunner::WARNING_LIMIT + 3;

		for ( $i = 0; $i < $total; $i++ ) {
			$runner = new MigrationRunner();
			$runner->register(
				'0.2.0',
				static function (): void {
					throw new \RuntimeException( 'boom' );
				}
			);

			$runner->maybeRun();
		}

		$this->assertSame(
			MigrationRunner::WARNING_LIMIT,
			$warnings,
			'The warning must stop after the configured attempt limit'
		);
		$this->assertSame(
			$total,
			$actions,
			'The failure action must still fire on every attempt so the state stays observable'
		);
		$this->assertSame( $total, (int) $failures['0.2.0']['attempts'], 'Every attempt must be counted' );
	}

	/**
	 * Test a verbose failure message cannot grow the option.
	 */
	public function test_verbose_failure_message_is_truncated(): void {
		$store    = '0.1.0';
		$failures = [];

		Functions\when( 'get_option' )->alias(
			static function ( string $option, mixed $fallback = false ) use ( &$store, &$failures ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_option signature.
				if ( MigrationRunner::FAILURES === $option ) {
					return $failures;
				}

				return $store;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $option, mixed $value, mixed $autoload = null ) use ( &$store, &$failures ): bool {
				unset( $autoload );

				if ( MigrationRunner::FAILURES === $option ) {
					$failures = is_array( $value ) ? $value : [];

					return true;
				}

				$store = $value;

				return true;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'wp_trigger_error' )->justReturn( null );

		$verbose = str_repeat( 'a very long failure message ', 200 );

		$runner = new MigrationRunner();
		$runner->register(
			'0.2.0',
			static function () use ( $verbose ): void {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test only exception message, never rendered to the browser.
				throw new \RuntimeException( $verbose );
			}
		);

		$runner->maybeRun();

		$stored = (string) ( $failures['0.2.0']['message'] ?? '' );

		$this->assertStringEndsWith( '...', $stored, 'A bounded message must show that it was cut' );
		$this->assertLessThanOrEqual(
			MigrationRunner::MAX_MESSAGE_LENGTH + 3,
			strlen( $stored ),
			'The stored message must stay bounded no matter how verbose the throwable is'
		);
		$this->assertLessThan(
			strlen( $verbose ),
			strlen( $stored ),
			'A message inside the bound must be stored whole'
		);
	}

	/**
	 * Test a short failure message is stored whole.
	 */
	public function test_short_failure_message_is_stored_whole(): void {
		$store    = '0.1.0';
		$failures = [];

		Functions\when( 'get_option' )->alias(
			static function ( string $option, mixed $fallback = false ) use ( &$store, &$failures ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_option signature.
				if ( MigrationRunner::FAILURES === $option ) {
					return $failures;
				}

				return $store;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $option, mixed $value, mixed $autoload = null ) use ( &$store, &$failures ): bool {
				unset( $autoload );

				if ( MigrationRunner::FAILURES === $option ) {
					$failures = is_array( $value ) ? $value : [];

					return true;
				}

				$store = $value;

				return true;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'wp_trigger_error' )->justReturn( null );

		$runner = new MigrationRunner();
		$runner->register(
			'0.2.0',
			static function (): void {
				throw new \RuntimeException( 'boom' );
			}
		);

		$runner->maybeRun();

		$this->assertSame( 'boom', (string) ( $failures['0.2.0']['message'] ?? '' ) );
	}

	/**
	 * Test a recorded failure can be cleared so the version runs again.
	 */
	public function test_clearing_a_failure_lets_the_version_run_again(): void {
		$store    = '0.1.0';
		$failures = [
			'0.2.0' => [
				'attempts' => 5,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		Functions\when( 'get_option' )->alias(
			static function ( string $option, mixed $fallback = false ) use ( &$store, &$failures ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_option signature.
				if ( MigrationRunner::FAILURES === $option ) {
					return $failures;
				}

				return $store;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $option, mixed $value, mixed $autoload = null ) use ( &$store, &$failures ): bool {
				unset( $autoload );

				if ( MigrationRunner::FAILURES === $option ) {
					$failures = is_array( $value ) ? $value : [];

					return true;
				}

				$store = $value;

				return true;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'wp_trigger_error' )->justReturn( null );

		$runs = 0;

		$runner = new MigrationRunner();
		$runner->register(
			'0.2.0',
			static function () use ( &$runs ): void {
				++$runs;
			}
		);

		$this->assertTrue( $runner->clearFailure( '0.2.0' ), 'The recorded failure must be clearable' );
		$this->assertSame( [], $failures, 'Clearing must empty the failures option' );
		$this->assertSame( '0.1.0', $store, 'Clearing a failure must never touch the ledger' );

		$runner->maybeRun();

		$this->assertSame( 1, $runs, 'After clearing, the pending version must run' );
		$this->assertSame( '0.2.0', $store, 'A clean run must advance the ledger' );
	}

	/**
	 * Test clearing an unknown version changes nothing.
	 */
	public function test_clearing_an_unknown_version_is_a_no_op(): void {
		$failures = [
			'0.2.0' => [
				'attempts' => 1,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		Functions\when( 'get_option' )->justReturn( $failures );
		Functions\expect( 'update_option' )->never();

		$this->assertFalse( ( new MigrationRunner() )->clearFailure( '9.9.9' ) );
	}

	/**
	 * Test a corrupt failures option reads as no failures rather than fatal.
	 */
	public function test_corrupt_failures_option_reads_empty(): void {
		Functions\when( 'get_option' )->justReturn( 'not an array' );

		$this->assertSame( [], ( new MigrationRunner() )->failures() );
	}

	/**
	 * Test ledger behind syncs when no migrations registered.
	 */
	public function test_ledger_behind_syncs_when_no_migrations_registered(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( MigrationRunner::LEDGER, '0.0.0' )
			->andReturn( '0.0.0' );

		Functions\expect( 'update_option' )
			->once()
			->with( MigrationRunner::LEDGER, RANKKERNEL_VERSION, false )
			->andReturn( true );

		$runner = new MigrationRunner();
		$runner->maybeRun();

		$this->assertSame( RANKKERNEL_VERSION, $runner->currentVersion() );
	}

	/**
	 * Test idempotent double maybe run.
	 */
	public function test_idempotent_double_maybe_run(): void {
		Functions\when( 'get_option' )->justReturn( '0.0.0' );

		$count = 0;

		Functions\expect( 'update_option' )
			->once()
			->with( MigrationRunner::LEDGER, '0.1.0', false )
			->andReturn( true );

		$runner = new MigrationRunner();
		$runner->register(
			'0.1.0',
			static function () use ( &$count ): void {
				++$count;
			}
		);

		$runner->maybeRun();
		$runner->maybeRun();

		$this->assertSame( 1, $count, 'Migration must be invoked exactly once across two maybeRun calls' );
	}

	/**
	 * Test current version caches.
	 */
	public function test_current_version_caches(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( MigrationRunner::LEDGER, '0.0.0' )
			->andReturn( '0.1.0' );

		$runner = new MigrationRunner();

		$first  = $runner->currentVersion();
		$second = $runner->currentVersion();

		$this->assertSame( '0.1.0', $first );
		$this->assertSame( '0.1.0', $second );
	}

	/**
	 * Test a ledger newer than the code fires the downgrade path loudly.
	 */
	public function test_downgrade_fires_action_and_warns(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( MigrationRunner::LEDGER === $key ) {
					return '0.2.0';
				}

				return $fallback;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );

		$runner = new MigrationRunner();
		$ran    = false;
		$runner->register(
			'0.1.0',
			static function () use ( &$ran ): void {
				$ran = true;
			}
		);

		$fired = [];
		Functions\when( 'do_action' )->alias(
			static function ( string $hook, mixed ...$args ) use ( &$fired ): void {
				$fired[] = [ $hook, $args ];
			}
		);

		$warned = [];
		Functions\when( 'wp_trigger_error' )->alias(
			static function ( string $method, string $message, int $type ) use ( &$warned ): void {
				$warned[] = [ $method, $message, $type ];
			}
		);

		$runner->maybeRun();

		$this->assertFalse( $ran, 'no migration runs on a downgraded install' );
		$this->assertSame( [ [ 'rankkernel/migration/downgrade', [ '0.2.0', '0.1.0' ] ] ], $fired );
		$this->assertCount( 1, $warned );
		$this->assertSame( E_USER_WARNING, $warned[0][2] );
	}

	/**
	 * Test the baseline schema ensures all three module tables.
	 */
	public function test_baseline_schema_ensures_all_three_tables(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double, restored below.
		$GLOBALS['wpdb'] = new MonitorFakeDb();

		\RankKernel\Modules\Monitor\LogTable::resetCache();
		\RankKernel\Modules\Redirects\RedirectTable::resetCache();
		\RankKernel\Modules\InstantIndexing\LogTable::resetCache();

		$calls = 0;
		Functions\when( 'dbDelta' )->alias(
			static function () use ( &$calls ): void {
				++$calls;
			}
		);

		MigrationRunner::baselineSchema();

		$this->assertSame( 3, $calls, 'baseline runs the diff for all three tables' );

		unset( $GLOBALS['wpdb'] );
	}
}
