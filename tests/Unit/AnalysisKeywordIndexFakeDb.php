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
	 * Ids the fake reports, in order.
	 *
	 * @var int[]
	 */
	public array $ids = [];

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
	 * Return the configured ids.
	 *
	 * @param string $query Prepared query.
	 * @return int[] The result.
	 */
	public function get_col( string $query ): array {
		$this->queries[] = $query;

		return $this->ids;
	}
}
