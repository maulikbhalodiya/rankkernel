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

		Functions\expect( 'update_option' )
			->once()
			->with( MigrationRunner::LEDGER, '0.2.0', false )
			->andReturn( true );

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

		Functions\expect( 'update_option' )->never();

		$runner->maybeRun();

		$this->assertTrue( $actionFired, 'rankkernel/migration/failed action should fire' );
		$this->assertSame( '0.1.0', $actionArgs[0] ?? null );
		$this->assertSame( $throwable, $actionArgs[1] ?? null );
		$this->assertTrue( $triggered, 'wp_trigger_error should be called' );
		$this->assertFalse( $secondCalled, 'Migrations after failure must not run' );
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
}
