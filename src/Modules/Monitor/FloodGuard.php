<?php
/**
 * 404 flood protection, bounded new URI budget per time window.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

/**
 * Caps how many new URIs enter the log per time window, per site.
 *
 * State lives in the object cache under a Monitor specific group with a
 * transient fallback, so no table and no cron are needed. The budget covers
 * real content 404s only: the Logger calls into this class after every skip
 * category, so static assets, probes, and excluded URIs never spend it. When
 * the budget is exhausted, new URIs are suppressed while known URIs keep
 * incrementing, and a suppressed marker option is set for an admin notice.
 * No IP is read or stored anywhere in this class.
 */
final class FloodGuard {
	/**
	 * Object cache group, never shared with sitemaps or redirects.
	 */
	public const GROUP = 'rankkernel-monitor';

	/**
	 * Transient prefix for the no object cache fallback.
	 */
	public const TRANSIENT_PREFIX = 'rk404_flood_';

	/**
	 * Suppressed marker option, read by the admin notice in a later phase.
	 */
	public const SUPPRESSED_OPTION = 'rankkernel_404_suppressed';

	/**
	 * Module settings.
	 */
	private MonitorSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param MonitorSettings|null $settings Module settings.
	 */
	public function __construct( ?MonitorSettings $settings = null ) {
		$this->settings = $settings ?? new MonitorSettings();
	}

	/**
	 * Whether a new URI may enter the log, consuming one budget slot.
	 *
	 * Returns true and stores the incremented counter when budget remains.
	 * Returns false and refreshes the suppressed marker when the window is
	 * exhausted. Call only for new URIs: known URIs increment through the
	 * repository without touching the budget.
	 *
	 * @return bool True when the new URI may be logged.
	 */
	public function allowNew(): bool {
		$budget = $this->settings->getFloodBudget();
		$window = $this->settings->getFloodWindow();
		$now    = time();
		$state  = $this->readState();

		if ( ! is_array( $state ) || ! isset( $state['start'], $state['count'] ) ) {
			$state = [
				'start' => $now,
				'count' => 0,
			];
		}

		$start = (int) $state['start'];
		$count = (int) $state['count'];

		if ( $now - $start >= $window ) {
			$start = $now;
			$count = 0;
		}

		if ( $count >= $budget ) {
			update_option( self::SUPPRESSED_OPTION, $now, false );

			return false;
		}

		$this->writeState(
			[
				'start' => $start,
				'count' => $count + 1,
			],
			$window
		);

		return true;
	}

	/**
	 * Whether the suppressed marker is currently set.
	 *
	 * @return bool True when a flood suppression was recorded.
	 */
	public static function isSuppressed(): bool {
		$marker = get_option( self::SUPPRESSED_OPTION, false );

		return false !== $marker && '' !== (string) $marker;
	}

	/**
	 * Clear the suppressed marker, used after the admin notice is shown.
	 */
	public static function clearSuppressed(): void {
		delete_option( self::SUPPRESSED_OPTION );
	}

	/**
	 * Cache key for the current site.
	 */
	private function key(): string {
		$siteId = 1;

		if ( function_exists( 'get_current_blog_id' ) ) {
			$siteId = (int) get_current_blog_id();
		}

		return self::TRANSIENT_PREFIX . $siteId;
	}

	/**
	 * Read the window state, object cache first, transient fallback.
	 *
	 * @return array<string, int>|null Window state or null on a cold start.
	 */
	private function readState(): ?array {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$value = wp_cache_get( $this->key(), self::GROUP );

			if ( is_array( $value ) ) {
				return $value;
			}
		}

		$value = get_transient( $this->key() );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Write the window state, object cache plus transient fallback.
	 *
	 * @param array<string, int> $state Window state.
	 * @param int                $ttl   Time to live in seconds.
	 */
	private function writeState( array $state, int $ttl ): void {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			wp_cache_set( $this->key(), $state, self::GROUP, $ttl );
		}

		set_transient( $this->key(), $state, $ttl );
	}
}
