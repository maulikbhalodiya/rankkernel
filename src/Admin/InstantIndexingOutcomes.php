<?php
/**
 * Instant Indexing outcome taxonomy, codes to display state.
 *
 * Maps every stored status code to one display category, reverses the same
 * map into SQL facing status codes for the query layer, derives the stats
 * strip numbers from those counts, and labels the pills. No WordPress APIs
 * are called here, only translation functions, so the whole class stays unit
 * testable without a loaded WordPress.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Translates IndexNow outcome codes for the admin screen.
 */
final class InstantIndexingOutcomes {
	/**
	 * Display category for accepted codes.
	 */
	public const CATEGORY_ACCEPTED = 'accepted';

	/**
	 * Display category for the key pending code.
	 */
	public const CATEGORY_PENDING = 'pending';

	/**
	 * Display category for rejected codes plus refused rows.
	 */
	public const CATEGORY_REJECTED = 'rejected';

	/**
	 * Display category for the rate limited code.
	 */
	public const CATEGORY_LIMITED = 'limited';

	/**
	 * Display category for any other temporary outcome.
	 */
	public const CATEGORY_RETRY = 'retry';

	/**
	 * Permanent rejection codes, mirroring the client boundary.
	 *
	 * @var int[]
	 */
	private const PERMANENT_CODES = [ 400, 403, 405, 422 ];

	/**
	 * Display category for one stored status code.
	 *
	 * Code zero marks a refused row that never reached the network, so it
	 * reads as rejected rather than as a temporary outcome.
	 *
	 * @param int $code Stored status code.
	 * @return string The result.
	 */
	public static function categoryFor( int $code ): string {
		if ( 200 === $code ) {
			return self::CATEGORY_ACCEPTED;
		}

		if ( 202 === $code ) {
			return self::CATEGORY_PENDING;
		}

		if ( 429 === $code ) {
			return self::CATEGORY_LIMITED;
		}

		if ( 0 === $code || in_array( $code, self::PERMANENT_CODES, true ) ) {
			return self::CATEGORY_REJECTED;
		}

		return self::CATEGORY_RETRY;
	}

	/**
	 * Stored status codes per display category, the SQL facing reverse.
	 *
	 * The query layer builds its status filter from this map, and the
	 * categoryFor agreement test proves the two directions can never
	 * drift. The retry category has no entry because it is defined as
	 * everything else, it has no tab.
	 *
	 * @return array<string, int[]> The result.
	 */
	public static function categoryCodes(): array {
		return [
			self::CATEGORY_ACCEPTED => [ 200 ],
			self::CATEGORY_PENDING  => [ 202 ],
			self::CATEGORY_REJECTED => array_merge( [ 0 ], self::PERMANENT_CODES ),
			self::CATEGORY_LIMITED  => [ 429 ],
		];
	}

	/**
	 * Translated pill label for one display category.
	 *
	 * @param string $category Category slug from categoryFor.
	 * @return string The result.
	 */
	public static function statusLabel( string $category ): string {
		if ( self::CATEGORY_PENDING === $category ) {
			return __( 'Key pending', 'rankkernel' );
		}

		if ( self::CATEGORY_REJECTED === $category ) {
			return __( 'Rejected', 'rankkernel' );
		}

		if ( self::CATEGORY_LIMITED === $category ) {
			return __( 'Rate limited', 'rankkernel' );
		}

		if ( self::CATEGORY_RETRY === $category ) {
			return __( 'Retry later', 'rankkernel' );
		}

		return __( 'Accepted', 'rankkernel' );
	}

	/**
	 * Pill class for one display category.
	 *
	 * @param string $category Category slug from categoryFor.
	 * @return string The result.
	 */
	public static function statusPill( string $category ): string {
		$map = [
			self::CATEGORY_ACCEPTED => 'rk-ui-pill rk-ui-pill-success',
			self::CATEGORY_PENDING  => 'rk-ui-pill rk-ui-pill-info',
			self::CATEGORY_REJECTED => 'rk-ui-pill rk-ui-pill-danger',
			self::CATEGORY_LIMITED  => 'rk-ui-pill rk-ui-pill-warning',
			self::CATEGORY_RETRY    => 'rk-ui-pill rk-ui-pill-warning',
		];

		return $map[ $category ] ?? 'rk-ui-pill rk-ui-pill-success';
	}

	/**
	 * Translated source label, auto or manual.
	 *
	 * @param string $source Stored source value.
	 * @return string The result.
	 */
	public static function sourceLabel( string $source ): string {
		if ( 'manual' === $source ) {
			return __( 'Manual', 'rankkernel' );
		}

		return __( 'Auto', 'rankkernel' );
	}

	/**
	 * Pill class for one source value.
	 *
	 * @param string $source Stored source value.
	 * @return string The result.
	 */
	public static function sourcePill( string $source ): string {
		if ( 'manual' === $source ) {
			return 'rk-ui-pill rk-pill-source-manual';
		}

		return 'rk-ui-pill rk-ui-pill-neutral';
	}

	/**
	 * Stats strip numbers derived from per category counts.
	 *
	 * The query layer supplies the counts, so the strip covers the whole
	 * table rather than a page. Accepted is the accepted plus pending
	 * count, rejected is the rejected count, limited is the limited
	 * count, and total is every row including the retry category, which
	 * has no card of its own.
	 *
	 * @param array<string, int> $counts Category counts keyed by category slug plus all.
	 * @return array{total: int, accepted: int, rejected: int, limited: int} The result.
	 */
	public static function statsFromCounts( array $counts ): array {
		return [
			'total'    => (int) ( $counts['all'] ?? 0 ),
			'accepted' => (int) ( $counts[ self::CATEGORY_ACCEPTED ] ?? 0 ) + (int) ( $counts[ self::CATEGORY_PENDING ] ?? 0 ),
			'rejected' => (int) ( $counts[ self::CATEGORY_REJECTED ] ?? 0 ),
			'limited'  => (int) ( $counts[ self::CATEGORY_LIMITED ] ?? 0 ),
		];
	}
}
