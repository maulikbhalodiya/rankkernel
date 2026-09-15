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
 * being skipped when already applied. Failures do not advance the ledger so
 * subsequent requests retry pending migrations. Sufficient for the v1 core
 * which is schema-less (blueprint §D.4).
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
	 * Run pending migrations in ascending version order.
	 *
	 * Fires on `init` priority 10. Ledger is updated once to the highest
	 * executed version on success. On failure the ledger is not advanced
	 * past the failed version, an action is fired, and a warning is
	 * triggered, the site never goes down. With no registered migrations
	 * but a stale ledger, the ledger is synced to RANKKERNEL_VERSION.
	 */
	public function maybeRun(): void {
		$current = $this->currentVersion();

		// No migrations registered: sync ledger to plugin version if behind.
		if ( [] === $this->migrations ) {
			if ( defined( 'RANKKERNEL_VERSION' ) && version_compare( $current, RANKKERNEL_VERSION, '<' ) ) {
				update_option( self::LEDGER, \RankKernel\Plugin::version(), false );
				$this->cachedVersion = \RankKernel\Plugin::version();
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

		$highest = null;

		foreach ( $pending as $version => $migration ) {
			try {
				$migration();
				$highest = $version;
			} catch ( \Throwable $throwable ) {
				do_action( 'rankkernel/migration/failed', $version, $throwable ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
				wp_trigger_error( __METHOD__, $throwable->getMessage(), E_USER_WARNING );
				return;
			}
		}

		// All pending succeeded, advance ledger once.
		if ( null !== $highest ) {
			update_option( self::LEDGER, $highest, false );
			$this->cachedVersion = $highest;
		}
	}
}
