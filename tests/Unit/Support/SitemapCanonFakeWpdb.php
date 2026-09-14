<?php
/**
 * Fake wpdb for sitemap canonical tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

/**
 * Minimal wpdb double serving fixed rows plus a meta map.
 */
final class SitemapCanonFakeWpdb {
	/**
	 * Posts.
	 *
	 * @var string
	 */
	public $posts = 'wp_posts';
	/**
	 * Postmeta.
	 *
	 * @var string
	 */
	public $postmeta = 'wp_postmeta';
	/**
	 * Terms.
	 *
	 * @var string
	 */
	public $terms = 'wp_terms';
	/**
	 * Term taxonomy.
	 *
	 * @var string
	 */
	public $term_taxonomy = 'wp_term_taxonomy';
	/**
	 * Term relationships.
	 *
	 * @var string
	 */
	public $term_relationships = 'wp_term_relationships';
	/**
	 * Termmeta.
	 *
	 * @var string
	 */
	public $termmeta = 'wp_termmeta';

	/**
	 * Post Rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $postRows = [];
	/**
	 * Postmeta Rows.
	 *
	 * @var array<int, string>
	 */
	public array $postmetaRows = [];
	/**
	 * Term Rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $termRows = [];
	/**
	 * Termmeta Rows.
	 *
	 * @var array<int, string>
	 */
	public array $termmetaRows = [];

	/**
	 * Esc like.
	 *
	 * @param string $text Text.
	 * @return string The result.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Prepare.
	 *
	 * @param string $query   Query.
	 * @param mixed  ...$args Args.
	 * @return string The result.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || ( is_string( $arg ) && ctype_digit( $arg ) ) ) {
				$query = (string) preg_replace( '/%d/', (string) (int) $arg, $query, 1 );
			} else {
				$query = (string) preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $query, 1 );
			}
		}

		return $query;
	}

	/**
	 * Get var.
	 *
	 * @param string|null $query Query.
	 * @return mixed The result.
	 */
	public function get_var( ?string $query = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- test double mirrors the $wpdb method signature.
		return 1;
	}

	/**
	 * Get results.
	 *
	 * @param string|null $query  Query.
	 * @param mixed       $output Output.
	 * @return mixed The result.
	 */
	public function get_results( ?string $query = null, mixed $output = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb method signature.
		$sql = (string) $query;

		if ( 1 === preg_match( '/SELECT (?:post_id|term_id), meta_value/', $sql ) ) {
			$ids = [];

			if ( 1 === preg_match( '/IN \(([\d,\s]+)\)/', $sql, $m ) ) {
				foreach ( explode( ',', $m[1] ) as $part ) {
					$id = (int) trim( $part );

					if ( 0 !== $id ) {
						$ids[] = $id;
					}
				}
			}

			$isTerm = str_contains( $sql, 'wp_termmeta' );
			$key    = $isTerm ? 'term_id' : 'post_id';
			$map    = $isTerm ? $this->termmetaRows : $this->postmetaRows;
			$out    = [];

			foreach ( $ids as $id ) {
				if ( array_key_exists( $id, $map ) ) {
					$out[] = [
						$key         => $id,
						'meta_value' => $map[ $id ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- array key named meta_value in a test fixture, not a database query.
					];
				}
			}

			return $out;
		}

		if ( str_contains( $sql, 't.term_id' ) ) {
			return $this->termRows;
		}

		return $this->postRows;
	}
}
