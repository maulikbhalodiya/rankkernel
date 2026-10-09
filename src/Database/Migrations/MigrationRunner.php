<?php
/**
 * Migration runner with version ledger.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Database\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Runs versioned migrations against the rankkernel_db_version ledger.
 *
 * Idempotency is the migration author's duty, each closure must tolerate
 * being skipped when already applied. The ledger is persisted after each
 * success, so a failure only leaves the failed migration and the ones after
 * it pending for the next request. Sufficient for the v1 core which is
 * schema-less (blueprint §D.4).
 *
 * Extension contract: later modules (e.g. redirects/404 tables per
 * blueprint §D.4) register their table-creation migration by calling
 * register() with the target version before the `init` hook fires; the
 * runner sorts by version and executes every pending migration in order.
 */
final class MigrationRunner {
	/**
	 * Ledger option name.
	 */
	public const LEDGER = 'rankkernel_db_version';

	/**
	 * Option holding recorded migration failures.
	 *
	 * Autoload is off. A migration failure is rare and the option is empty
	 * on a healthy install, so there is no reason to put it in the
	 * alloptions payload every frontend request reads.
	 */
	public const FAILURES = 'rankkernel_migration_failures';

	/**
	 * Attempts after which the per request warning stops.
	 *
	 * A permanently failing migration used to emit an E_USER_WARNING on
	 * every request, front and back, forever, inside the component whose
	 * job is bloat prevention. The action still fires on every attempt, so
	 * the state is observable, but the log stops growing without bound.
	 */
	public const WARNING_LIMIT = 3;

	/**
	 * Longest failure message kept in the option.
	 *
	 * A verbose throwable, a full SQL statement or a stack trace style
	 * message, was stored verbatim, so the option grew on every attempt of a
	 * permanently failing migration. The dashboard only ever shows the message
	 * inline after "Last error", so a bound this size keeps the whole thing
	 * readable while stopping the growth.
	 */
	public const MAX_MESSAGE_LENGTH = 500;

	/**
	 * Registered migrations.
	 *
	 * @var array<string, callable>
	 */
	private array $migrations = [];

	/**
	 * Cached ledger version (null = not yet read).
	 *
	 * @var string|null
	 */
	private ?string $cachedVersion = null;

	/**
	 * Register a versioned migration.
	 *
	 * @param string   $version   Target version (e.g. "0.1.0").
	 * @param callable $migration Migration closure.
	 */
	public function register( string $version, callable $migration ): void {
		$this->migrations[ $version ] = $migration;
	}

	/**
	 * Run the 0.1.0 baseline schema for all module tables.
	 *
	 * Each table class diffs its own DDL, so this stays correct as columns
	 * are added in later versions without touching this method.
	 *
	 * @return void
	 */
	public static function baselineSchema(): void {
		\RankKernel\Modules\Monitor\LogTable::ensureTables();
		\RankKernel\Modules\Redirects\RedirectTable::ensureTables();
		\RankKernel\Modules\InstantIndexing\LogTable::ensureTables();
	}

	/**
	 * Get current ledger version (cached after first read).
	 *
	 * @return string Stored version or "0.0.0" when ledger absent.
	 */
	public function currentVersion(): string {
		if ( null !== $this->cachedVersion ) {
			return $this->cachedVersion;
		}

		$stored = get_option( self::LEDGER, '0.0.0' );

		if ( ! is_string( $stored ) || '' === $stored ) {
			$stored = '0.0.0';
		}

		$this->cachedVersion = $stored;

		return $this->cachedVersion;
	}

	/**
	 * Release core of a version string, without any pre-release suffix.
	 *
	 * Test and development builds tag the constant (e.g. 0.1.0-test), and
	 * version_compare ranks an unknown suffix below the bare release, which
	 * would misread every normal run as a downgrade. Comparing release cores
	 * keeps suffixed builds behaving like the release they track.
	 *
	 * @param string $version Full version.
	 * @return string Leading numeric release.
	 */
	private static function baseVersion( string $version ): string {
		$dash = strpos( $version, '-' );

		if ( false === $dash ) {
			return $version;
		}

		return substr( $version, 0, $dash );
	}

	/**
	 * Run pending migrations in ascending version order.
	 *
	 * Fires on `init` priority 10. The ledger is persisted after each
	 * successful migration, so a later failure never causes an already
	 * applied migration to run again. On failure the ledger is not advanced
	 * past the failed version, so the failed version stays pending and keeps
	 * blocking the ones after it until an operator clears it. That blocking
	 * is deliberate, since skipping a migration silently would be worse than
	 * stopping, but it is now recoverable: the failure is persisted with its
	 * attempt count, surfaced on the dashboard, and can be cleared so the
	 * version runs again. The per request warning stops after WARNING_LIMIT
	 * attempts so a permanent failure cannot grow the log without bound, and
	 * the site never goes down. With no registered migrations
	 * but a stale ledger, the ledger is synced to RANKKERNEL_VERSION.
	 */
	public function maybeRun(): void {
		$current = $this->currentVersion();

		// A ledger newer than the running code means a downgrade. Migrations
		// only run forward, so there is nothing safe to execute — but
		// silently returning would hide a broken install, so the downgrade is
		// announced loudly instead.
		$code = self::baseVersion( \RankKernel\Plugin::version() );

		if ( version_compare( self::baseVersion( $current ), $code, '>' ) ) {
			do_action( 'rankkernel/migration/downgrade', $current, $code ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

			if ( function_exists( 'wp_trigger_error' ) ) {
				wp_trigger_error(
					__METHOD__,
					sprintf( 'RankKernel database ledger %s is newer than plugin version %s; migrations skipped.', $current, $code ),
					E_USER_WARNING
				);
			}

			return;
		}

		// No migrations registered: sync ledger to plugin version if behind.
		if ( [] === $this->migrations ) {
			if ( defined( 'RANKKERNEL_VERSION' ) && version_compare( $current, RANKKERNEL_VERSION, '<' ) ) {
				$version = \RankKernel\Plugin::version();

				if ( true === update_option( self::LEDGER, $version, false ) ) {
					$this->cachedVersion = $version;
				}
			}

			return;
		}

		// Collect pending migrations (version > current), sorted ascending.
		$pending = [];

		foreach ( $this->migrations as $version => $migration ) {
			if ( version_compare( $version, $current, '>' ) ) {
				$pending[ $version ] = $migration;
			}
		}

		if ( [] === $pending ) {
			return;
		}

		uksort( $pending, 'version_compare' );

		foreach ( $pending as $version => $migration ) {
			try {
				$migration();
			} catch ( \Throwable $throwable ) {
				$attempts = $this->recordFailure( $version, $throwable );

				do_action( 'rankkernel/migration/failed', $version, $throwable ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

				if ( $attempts <= self::WARNING_LIMIT && function_exists( 'wp_trigger_error' ) ) {
					wp_trigger_error( __METHOD__, $throwable->getMessage(), E_USER_WARNING );
				}

				return;
			}

			// A version that now runs cleanly has no recorded failure to
			// carry forward, so the record is dropped as it succeeds.
			$this->clearFailure( $version );

			// Persist after each success, so a failure later in the run cannot
			// leave an earlier success unrecorded. Without this the next
			// request would re-run every migration in the run, and any that is
			// not idempotent would write twice. Only trust storage: the cached
			// version advances only when the write actually landed, otherwise
			// the in memory state would claim progress storage does not have.
			if ( true === update_option( self::LEDGER, $version, false ) ) {
				$this->cachedVersion = $version;
			}
		}
	}

	/**
	 * Recorded failures, newest attempt counts included.
	 *
	 * @return array<string, array{attempts: int, message: string, failedAt: string}> The result, keyed by version.
	 */
	public function failures(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return [];
		}

		$stored = get_option( self::FAILURES, [] );

		if ( ! is_array( $stored ) ) {
			return [];
		}

		$failures = [];

		foreach ( $stored as $version => $entry ) {
			if ( ! is_string( $version ) || ! is_array( $entry ) ) {
				continue;
			}

			$failures[ $version ] = [
				'attempts' => max( 1, (int) ( $entry['attempts'] ?? 1 ) ),
				'message'  => isset( $entry['message'] ) ? (string) $entry['message'] : '',
				'failedAt' => isset( $entry['failedAt'] ) ? (string) $entry['failedAt'] : '',
			];
		}

		return $failures;
	}

	/**
	 * Record one failed attempt and return the running attempt count.
	 *
	 * The count is what caps the warning, so a permanently failing
	 * migration stops writing to the log after WARNING_LIMIT attempts
	 * while remaining visible on the dashboard.
	 *
	 * @param string     $version   Failing version.
	 * @param \Throwable $throwable Captured failure.
	 * @return int Attempt count including this one.
	 */
	private function recordFailure( string $version, \Throwable $throwable ): int {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return 1;
		}

		$failures = $this->failures();
		$previous = $failures[ $version ]['attempts'] ?? 0;

		$failures[ $version ] = [
			'attempts' => $previous + 1,
			'message'  => self::boundMessage( $throwable->getMessage() ),
			'failedAt' => function_exists( 'current_time' ) ? (string) current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
		];

		update_option( self::FAILURES, $failures, false );

		return $previous + 1;
	}

	/**
	 * Bound a failure message so the option cannot grow with it.
	 *
	 * Multibyte safe where mbstring is present, so a cut never lands inside a
	 * character and leaves a broken sequence in the stored string.
	 *
	 * @param string $message Raw throwable message.
	 * @return string Message bounded to MAX_MESSAGE_LENGTH.
	 */
	private static function boundMessage( string $message ): string {
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $message ) <= self::MAX_MESSAGE_LENGTH ) {
				return $message;
			}

			return mb_substr( $message, 0, self::MAX_MESSAGE_LENGTH ) . '...';
		}

		if ( strlen( $message ) <= self::MAX_MESSAGE_LENGTH ) {
			return $message;
		}

		return substr( $message, 0, self::MAX_MESSAGE_LENGTH ) . '...';
	}

	/**
	 * Drop the recorded failure for one version, no others.
	 *
	 * Called on a success, and by the dashboard clear and rerun action.
	 * The ledger is deliberately untouched, so clearing a failure cannot
	 * make the runner skip work that has not run.
	 *
	 * @param string $version Version to clear.
	 * @return bool True when a record was removed.
	 */
	public function clearFailure( string $version ): bool {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}

		$failures = $this->failures();

		if ( ! isset( $failures[ $version ] ) ) {
			return false;
		}

		unset( $failures[ $version ] );

		update_option( self::FAILURES, $failures, false );

		return true;
	}
}
