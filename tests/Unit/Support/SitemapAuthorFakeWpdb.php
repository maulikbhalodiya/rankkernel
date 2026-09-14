<?php
/**
 * Fake wpdb for sitemap author tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

/**
 * Minimal wpdb double for author post listings.
 */
final class SitemapAuthorFakeWpdb {
	/**
	 * Posts.
	 *
	 * @var string
	 */
	public $posts = 'wp_posts';

	/**
	 * Posts Rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $postsRows = [];

	/**
	 * Prepare.
	 *
	 * @param string $query   Query.
	 * @param mixed  ...$args Args.
	 * @return array<int, array<string, mixed>>
	 */
	public function prepare( string $query, mixed ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		foreach ( $args as $arg ) {
			if ( is_int( $arg ) ) {
				$query = (string) preg_replace( '/%d/', (string) $arg, $query, 1 );
			} else {
				$query = (string) preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $query, 1 );
			}
		}

		return $query;
	}

	/**
	 * Eligible Posts.
	 *
	 * @param string $sql Sql.
	 * @return array<int, array<string, mixed>>
	 */
	private function eligiblePosts( string $sql ): array {
		$types = [];

		if ( 1 === preg_match( '/post_type IN \(([^)]+)\)/', $sql, $m ) ) {
			foreach ( explode( ',', $m[1] ) as $part ) {
				$types[] = trim( $part, " '" );
			}
		}

		$out = [];

		foreach ( $this->postsRows as $row ) {
			if ( ( $row['post_status'] ?? '' ) !== 'publish' ) {
				continue;
			}

			if ( ! in_array( $row['post_type'] ?? '', $types, true ) ) {
				continue;
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Get var.
	 *
	 * @param string|null $query Query.
	 * @return mixed The result.
	 */
	public function get_var( ?string $query = null ): mixed {
		$authors = [];

		foreach ( $this->eligiblePosts( (string) $query ) as $row ) {
			$authors[ (int) ( $row['post_author'] ?? 0 ) ] = true;
		}

		unset( $authors[0] );

		return count( $authors );
	}

	/**
	 * Get results.
	 *
	 * @param string|null $query  Query.
	 * @param mixed       $output Output.
	 * @return mixed The result.
	 */
	public function get_results( ?string $query = null, mixed $output = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb method signature.
		$sql      = (string) $query;
		$grouped  = [];
		$lastmods = [];

		foreach ( $this->eligiblePosts( $sql ) as $row ) {
			$author = (int) ( $row['post_author'] ?? 0 );

			if ( 0 === $author ) {
				continue;
			}

			$grouped[ $author ] = true;
			$stamp              = (string) ( $row['post_modified_gmt'] ?? '' );

			if ( ! isset( $lastmods[ $author ] ) || $stamp > $lastmods[ $author ] ) {
				$lastmods[ $author ] = $stamp;
			}
		}

		ksort( $grouped );

		$limit  = 1 === preg_match( '/LIMIT (\d+)/', $sql, $m ) ? (int) $m[1] : 1000;
		$offset = 1 === preg_match( '/OFFSET (\d+)/', $sql, $m ) ? (int) $m[1] : 0;

		$ids = array_slice( array_keys( $grouped ), $offset, $limit );
		$out = [];

		foreach ( $ids as $id ) {
			$out[] = [
				'post_author' => $id,
				'lastmod_gmt' => $lastmods[ $id ],
			];
		}

		return $out;
	}
}
