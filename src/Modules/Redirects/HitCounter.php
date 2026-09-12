<?php
/**
 * Coalesced redirect hit counter, single shutdown write per rule.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * Batches hit increments in memory and flushes once on shutdown.
 *
 * The redirect response never waits on a statistics write: record() only
 * touches memory (plus the object cache counter when an external cache is
 * present) and registers the shutdown hook, flush() sends one UPDATE per
 * touched rule. Without an object cache the shutdown UPDATE is the entire
 * mechanism. Counts stay exact except for an in flight increment lost on a
 * hard kill, which is acceptable for statistics.
 */
final class HitCounter {
	/**
	 * Pending increments keyed by rule id.
	 *
	 * @var array<int, int>
	 */
	private array $pending = [];

	/**
	 * Whether the shutdown hook is registered.
	 */
	private bool $hooked = false;

	/**
	 * Record one hit, coalesced until shutdown.
	 *
	 * @param int $ruleId Redirect rule id.
	 */
	public function record( int $ruleId ): void {
		if ( $ruleId <= 0 ) {
			return;
		}

		$this->pending[ $ruleId ] = ( $this->pending[ $ruleId ] ?? 0 ) + 1;

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			wp_cache_incr( 'hits_' . (string) $ruleId, 1, RedirectCache::GROUP );
		}

		if ( ! $this->hooked ) {
			$this->hooked = true;
			add_action( 'shutdown', [ $this, 'flush' ], 20 );
		}
	}

	/**
	 * Flush pending increments, one UPDATE per touched rule.
	 */
	public function flush(): void {
		if ( [] === $this->pending ) {
			return;
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			$this->pending = [];

			return;
		}

		$table = RedirectTable::name();
		$now   = function_exists( 'current_time' ) ? (string) current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

		foreach ( $this->pending as $ruleId => $increments ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, single coalesced counter UPDATE per rule with placeholders.
			$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET hits = hits + %d, last_accessed = %s WHERE id = %d", $increments, $now, $ruleId ) );
		}

		$this->pending = [];
	}

	/**
	 * Pending increments, for tests.
	 *
	 * @return array<int, int>
	 */
	public function pending(): array {
		return $this->pending;
	}
}
