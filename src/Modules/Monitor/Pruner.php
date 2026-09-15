<?php
/**
 * 404 log pruner, bounded age and count enforcement.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the two independent growth limits: retention age and maximum rows.
 *
 * Every delete carries an explicit LIMIT and targets the oldest rows first
 * by last_accessed then id. TRUNCATE and unbounded DELETE are never used.
 * The Logger schedules prune on shutdown after a 404 insert, and the admin
 * list view in a later phase calls prune opportunistically.
 */
final class Pruner {
	/**
	 * Rows per age prune pass.
	 */
	public const AGE_BATCH = 500;

	/**
	 * Cap for one count prune pass.
	 */
	public const COUNT_BATCH_CAP = 500;

	/**
	 * Log repository.
	 *
	 * @var MonitorRepository
	 */
	private MonitorRepository $repository;

	/**
	 * Module settings.
	 *
	 * @var MonitorSettings
	 */
	private MonitorSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param MonitorRepository|null $repository Log repository.
	 * @param MonitorSettings|null   $settings   Module settings.
	 */
	public function __construct( ?MonitorRepository $repository = null, ?MonitorSettings $settings = null ) {
		$this->repository = $repository ?? new MonitorRepository();
		$this->settings   = $settings ?? new MonitorSettings();
	}

	/**
	 * Enforce both limits, age first, then count.
	 *
	 * @return int Total deleted rows across both passes.
	 */
	public function prune(): int {
		$deleted = $this->pruneByAge();

		return $deleted + $this->pruneByCount();
	}

	/**
	 * Delete rows older than the retention cutoff, one bounded batch.
	 *
	 * @return int Deleted row count.
	 */
	public function pruneByAge(): int {
		return $this->repository->deleteOlderThan( $this->cutoff(), self::AGE_BATCH );
	}

	/**
	 * Delete the oldest rows past the maximum, one bounded batch.
	 *
	 * Removes the excess plus a margin of 20 percent of the maximum, capped
	 * at 500 rows per pass. The margin amortizes cost so a sustained flood
	 * performs one small delete per request instead of repruning the full
	 * excess every time.
	 *
	 * @return int Deleted row count.
	 */
	public function pruneByCount(): int {
		$max   = $this->settings->getMaxRows();
		$total = $this->repository->count();

		if ( $total <= $max ) {
			return 0;
		}

		$excess = $total - $max;
		$margin = (int) ceil( $max * 0.2 );
		$limit  = min( $excess + $margin, self::COUNT_BATCH_CAP );

		return $this->repository->deleteOldestOver( $max, $limit );
	}

	/**
	 * Retention cutoff as a MySQL datetime.
	 *
	 * @return string Cutoff, rows last seen before this are stale.
	 */
	public function cutoff(): string {
		$now = function_exists( 'current_time' ) ? (string) current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$cut = strtotime( $now );

		if ( false === $cut ) {
			$cut = time();
		}

		return gmdate( 'Y-m-d H:i:s', $cut - $this->settings->getRetentionDays() * 86400 );
	}
}
