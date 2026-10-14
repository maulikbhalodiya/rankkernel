<?php
/**
 * Redirect mapper, source-plugin rows into repository rows.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

/**
 * Turn source-plugin redirect rows into our redirect rows.
 *
 * A mapped row carries source, target, code and match_type, ready for the
 * Redirects repository. A row the importer cannot place carries a
 * skip_reason instead and surfaces on the NOT-imported list.
 */
final class RedirectMapper {
	/**
	 * Status codes our repository accepts.
	 *
	 * @var int[]
	 */
	private const ALLOWED_CODES = [ 301, 302, 307, 308 ];

	/**
	 * Map Yoast Premium redirect option rows.
	 *
	 * Each row looks like origin, url, type (status code string) and
	 * format (plain or regex), from the wpseo-premium-redirects-base
	 * option (older wpseo-premium-redirects keys share the shape).
	 *
	 * @param array<int, array<string, mixed>> $rows Raw option rows.
	 * @return array<int, array<string, mixed>> Mapped rows.
	 */
	public static function mapYoast( array $rows ): array {
		$mapped = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$mapped[] = self::buildRow(
				(string) ( $row['origin'] ?? '' ),
				(string) ( $row['url'] ?? '' ),
				(int) ( $row['type'] ?? 0 ),
				'regex' === ( $row['format'] ?? '' ) ? 'regex' : 'exact'
			);
		}

		return self::dedupe( $mapped );
	}

	/**
	 * Map Rank Math redirection table rows.
	 *
	 * Each row carries sources (pattern plus comparison pairs), url_to,
	 * header_code and status (active or inactive).
	 *
	 * @param array<int, array<string, mixed>> $rows Raw table rows.
	 * @return array<int, array<string, mixed>> Mapped rows.
	 */
	public static function mapRankMath( array $rows ): array {
		$mapped = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$sources = $row['sources'] ?? [];
			$first   = is_array( $sources ) ? ( $sources[0] ?? [] ) : [];
			$first   = is_array( $first ) ? $first : [];

			if ( 'active' !== ( $row['status'] ?? '' ) ) {
				$mapped[] = self::skipRow( (string) ( $first['pattern'] ?? '' ), 'inactive in Rank Math' );
				continue;
			}

			$comparison = (string) ( $first['comparison'] ?? 'exact' );

			$mapped[] = self::buildRow(
				(string) ( $first['pattern'] ?? '' ),
				(string) ( $row['url_to'] ?? '' ),
				(int) ( $row['header_code'] ?? 0 ),
				'regex' === $comparison ? 'regex' : 'exact'
			);
		}

		return self::dedupe( $mapped );
	}

	/**
	 * Build one mapped row, or a skip row for unsupported codes.
	 *
	 * @param string $source Source path or pattern.
	 * @param string $target Target URL.
	 * @param int    $code Status code.
	 * @param string $matchType Match type, exact or regex.
	 * @return array<string, mixed> Mapped or skipped row.
	 */
	private static function buildRow( string $source, string $target, int $code, string $matchType ): array {
		if ( ! in_array( $code, self::ALLOWED_CODES, true ) ) {
			return self::skipRow( $source, 'unsupported status code ' . $code );
		}

		return [
			'source'     => $source,
			'target'     => $target,
			'code'       => $code,
			'match_type' => $matchType,
		];
	}

	/**
	 * Build a skip row naming why the redirect stays behind.
	 *
	 * @param string $source Source path or pattern.
	 * @param string $reason Human reason for the preview list.
	 * @return array<string, mixed> Skip row.
	 */
	private static function skipRow( string $source, string $reason ): array {
		return [
			'source'      => $source,
			'skip_reason' => $reason,
		];
	}

	/**
	 * Drop later rows repeating an already mapped source.
	 *
	 * Skipped rows never dedupe: every skip reason stays visible.
	 *
	 * @param array<int, array<string, mixed>> $rows Mapped rows.
	 * @return array<int, array<string, mixed>> Deduped rows.
	 */
	private static function dedupe( array $rows ): array {
		$seen   = [];
		$unique = [];

		foreach ( $rows as $row ) {
			if ( isset( $row['skip_reason'] ) ) {
				$unique[] = $row;
				continue;
			}

			if ( isset( $seen[ $row['source'] ] ) ) {
				continue;
			}

			$seen[ $row['source'] ] = true;
			$unique[]               = $row;
		}

		return $unique;
	}
}
