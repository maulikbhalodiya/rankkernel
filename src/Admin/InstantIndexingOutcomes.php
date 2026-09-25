<?php
/**
 * Instant Indexing outcome taxonomy, codes to display state.
 *
 * Maps every stored status code to one display category, derives the stats
 * strip numbers from the same rows the table renders, and labels the pills.
 * No WordPress APIs are called here, only translation functions, so the
 * whole class stays unit testable without a loaded WordPress.
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
	 * Stats strip numbers derived from the given rows.
	 *
	 * Accepted counts 200 plus 202, rejected counts the permanent codes
	 * plus refused rows, rate limited counts 429 only.
	 *
	 * @param array<int, array{url: string, code: int, source: string, time: string, message: string}> $rows Log rows, newest first.
	 * @return array{total: int, accepted: int, rejected: int, limited: int} The result.
	 */
	public static function stats( array $rows ): array {
		$stats = [
			'total'    => 0,
			'accepted' => 0,
			'rejected' => 0,
			'limited'  => 0,
		];

		foreach ( $rows as $row ) {
			++$stats['total'];

			$category = self::categoryFor( (int) $row['code'] );

			if ( self::CATEGORY_ACCEPTED === $category || self::CATEGORY_PENDING === $category ) {
				++$stats['accepted'];
			} elseif ( self::CATEGORY_REJECTED === $category ) {
				++$stats['rejected'];
			} elseif ( self::CATEGORY_LIMITED === $category ) {
				++$stats['limited'];
			}
		}

		return $stats;
	}
}
