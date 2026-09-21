<?php
/**
 * Minimal wpdb stand in for the keyword index.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * Records the prepared query and returns the ids the test asked for, so the
 * index can be tested without a database.
 */
final class AnalysisKeywordIndexFakeDb {
	/**
	 * Postmeta table name.
	 *
	 * @var string
	 */
	public string $postmeta = 'wp_postmeta';

	/**
	 * Posts table name.
	 *
	 * @var string
	 */
	public string $posts = 'wp_posts';

	/**
	 * Ids the fake reports, in order, when no candidate rows are set.
	 *
	 * @var int[]
	 */
	public array $ids = [];

	/**
	 * Candidate rows, meta value keyed by post id. When set, the fake applies
	 * the LIKE filter instead of returning ids as is.
	 *
	 * @var array<int, string>
	 */
	public array $rows = [];

	/**
	 * Queries the fake was asked to run.
	 *
	 * @var string[]
	 */
	public array $queries = [];

	/**
	 * Escape a value for a LIKE comparison.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Fill one placeholder per argument.
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Replacement values.
	 * @return string Prepared query.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		foreach ( $args as $arg ) {
			$replacement = is_int( $arg ) ? (string) $arg : "'" . (string) $arg . "'";
			$query       = (string) preg_replace( '/%[sdf]/', $replacement, $query, 1 );
		}

		return $query;
	}

	/**
	 * Return the configured ids, filtering candidate rows by the query LIKE
	 * pattern when rows are set.
	 *
	 * @param string $query Prepared query.
	 * @return int[] The result.
	 */
	public function get_col( string $query ): array {
		$this->queries[] = $query;

		if ( [] === $this->rows ) {
			return $this->ids;
		}

		if ( 1 !== preg_match( "/meta_value LIKE '([^']*)'/", $query, $matches ) ) {
			return [];
		}

		$needle = strtr(
			trim( $matches[1], '%' ),
			[
				'\\_'  => '_',
				'\\%'  => '%',
				'\\\\' => '\\',
			]
		);

		$ids = [];

		foreach ( $this->rows as $id => $meta_value ) {
			if ( false !== stripos( $meta_value, $needle ) ) {
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}
}
