<?php
/**
 * Fake wpdb for sitemap exclusion tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

/**
 * Minimal wpdb double that filters fixtures using the real SQL text.
 */
final class SitemapExclFakeWpdb {
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
	 * Posts Rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $postsRows = [];
	/**
	 * Postmeta Rows.
	 *
	 * @var array<int, string>
	 */
	public array $postmetaRows = [];
	/**
	 * Terms List.
	 *
	 * @var array<int, string>
	 */
	public array $termsList = [];
	/**
	 * Term Taxonomy.
	 *
	 * @var array<int, string>
	 */
	public array $termTaxonomy = [];
	/**
	 * Relationships.
	 *
	 * @var array<int, int[]>
	 */
	public array $relationships = [];
	/**
	 * Termmeta Rows.
	 *
	 * @var array<int, string>
	 */
	public array $termmetaRows = [];

	/**
	 * Last Sql.
	 *
	 * @var string
	 */
	public string $lastSql = '';

	/**
	 * Queries.
	 *
	 * @var string[]
	 */
	public array $queries = [];

	/**
	 * Esc like.
	 *
	 * @param string $text Text.
	 * @return array<int, array<string, mixed>>
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

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
			if ( is_int( $arg ) || ( is_string( $arg ) && ctype_digit( $arg ) ) ) {
				$query = (string) preg_replace( '/%d/', (string) (int) $arg, $query, 1 );
			} else {
				$query = (string) preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $query, 1 );
			}
		}

		$this->lastSql   = $query;
		$this->queries[] = $query;

		return $query;
	}

	/**
	 * Extract a LIKE pattern from the stored SQL as a matcher regex.
	 *
	 * @param string $sql Sql.
	 * @return array<int, array<string, mixed>>
	 */
	private function likePattern( string $sql ): ?string {
		if ( 1 !== preg_match( "/LIKE '((?:[^'\\\\]|\\\\.)*)'/", $sql, $m ) ) {
			return null;
		}

		$parts = explode( '%', stripcslashes( $m[1] ) );
		$regex = '/^';

		foreach ( $parts as $i => $part ) {
			if ( $i > 0 ) {
				$regex .= '.*';
			}

			$regex .= preg_quote( $part, '/' );
		}

		return $regex . '$/s';
	}

	/**
	 * Whether a meta value is excluded by the SQL LIKE clause.
	 *
	 * @param string|null $meta  Meta.
	 * @param string|null $regex Regex.
	 * @return int[] The result.
	 */
	private function likeExcludes( ?string $meta, ?string $regex ): bool {
		if ( null === $regex || null === $meta ) {
			return false;
		}

		return 1 === preg_match( $regex, $meta );
	}

	/**
	 * Get var.
	 *
	 * @param string|null $query Query.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_var( ?string $query = null ): mixed {
		$sql = (string) $query;

		if ( str_contains( $sql, 'COUNT(DISTINCT t.term_id)' ) ) {
			return count( $this->matchingTerms( $sql ) );
		}

		return count( $this->matchingPosts( $sql ) );
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
			return $this->metaBatchRows( $sql );
		}

		if ( str_contains( $sql, 't.term_id' ) ) {
			return $this->matchingTermRows( $sql );
		}

		return $this->matchingPostRows( $sql );
	}

	/**
	 * Matching Posts.
	 *
	 * @param string $sql Sql.
	 * @return array<int, array<string, mixed>>
	 */
	private function matchingPosts( string $sql ): array {
		$type = 1 === preg_match( "/post_type = '([^']+)'/", $sql, $m ) ? $m[1] : '';
		$like = $this->likePattern( $sql );
		$out  = [];

		foreach ( $this->postsRows as $row ) {
			if ( ( $row['post_type'] ?? '' ) !== $type ) {
				continue;
			}

			if ( ( $row['post_status'] ?? '' ) !== 'publish' ) {
				continue;
			}

			if ( str_contains( $sql, 'post_password' ) && '' !== ( $row['post_password'] ?? '' ) ) {
				continue;
			}

			$id   = (int) ( $row['ID'] ?? 0 );
			$meta = $this->postmetaRows[ $id ] ?? null;

			if ( $this->likeExcludes( $meta, $like ) ) {
				continue;
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Matching Post Rows.
	 *
	 * @param string $sql Sql.
	 * @return array<int, array<string, mixed>>
	 */
	private function matchingPostRows( string $sql ): array {
		$rows = $this->matchingPosts( $sql );

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				$cmp = strcmp( (string) ( $b['post_modified_gmt'] ?? '' ), (string) ( $a['post_modified_gmt'] ?? '' ) );

				if ( 0 !== $cmp ) {
					return $cmp;
				}

				return (int) ( $b['ID'] ?? 0 ) - (int) ( $a['ID'] ?? 0 );
			}
		);

		$limit  = 1 === preg_match( '/LIMIT (\d+)/', $sql, $m ) ? (int) $m[1] : 1000;
		$offset = 1 === preg_match( '/OFFSET (\d+)/', $sql, $m ) ? (int) $m[1] : 0;

		$slice = array_slice( $rows, $offset, $limit );
		$out   = [];

		foreach ( $slice as $row ) {
			$out[] = [
				'ID'                => $row['ID'],
				'post_modified_gmt' => $row['post_modified_gmt'],
			];
		}

		return $out;
	}

	/**
	 * Meta Batch Rows.
	 *
	 * @param string $sql Sql.
	 * @return array<int, array<string, mixed>>
	 */
	private function metaBatchRows( string $sql ): array {
		$ids = [];

		if ( 1 === preg_match( '/IN \(([\d,\s]+)\)/', $sql, $m ) ) {
			foreach ( explode( ',', $m[1] ) as $part ) {
				$id = (int) trim( $part );

				if ( 0 !== $id ) {
					$ids[] = $id;
				}
			}
		}

		$key = str_contains( $sql, 'wp_termmeta' ) ? 'term_id' : 'post_id';
		$map = str_contains( $sql, 'wp_termmeta' ) ? $this->termmetaRows : $this->postmetaRows;
		$out = [];

		foreach ( $ids as $id ) {
			if ( ! array_key_exists( $id, $map ) ) {
				continue;
			}

			$out[] = [
				$key         => $id,
				'meta_value' => $map[ $id ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- array key named meta_value in a test fixture, not a database query.
			];
		}

		return $out;
	}

	/**
	 * Matching Terms.
	 *
	 * @param string $sql Sql.
	 * @return int[] The result.
	 */
	private function matchingTerms( string $sql ): array {
		$taxonomy = 1 === preg_match( "/taxonomy = '([^']+)'/", $sql, $m ) ? $m[1] : '';
		$like     = $this->likePattern( $sql );
		$out      = [];

		foreach ( $this->termsList as $termId ) {
			if ( ( $this->termTaxonomy[ $termId ] ?? '' ) !== $taxonomy ) {
				continue;
			}

			if ( ! $this->termHasPublishedPost( $termId, $sql ) ) {
				continue;
			}

			$meta = $this->termmetaRows[ $termId ] ?? null;

			if ( $this->likeExcludes( $meta, $like ) ) {
				continue;
			}

			$out[] = $termId;
		}

		return $out;
	}

	/**
	 * Term Has Published Post.
	 *
	 * @param int    $termId Term Id.
	 * @param string $sql    Sql.
	 * @return bool The result.
	 */
	private function termHasPublishedPost( int $termId, string $sql ): bool {
		$types = [];

		if ( 1 === preg_match( '/post_type IN \(([^)]+)\)/', $sql, $m ) ) {
			foreach ( explode( ',', $m[1] ) as $part ) {
				$types[] = trim( $part, " '" );
			}
		}

		foreach ( $this->relationships[ $termId ] ?? [] as $postId ) {
			foreach ( $this->postsRows as $row ) {
				if ( (int) ( $row['ID'] ?? 0 ) !== $postId ) {
					continue;
				}

				if ( ( $row['post_status'] ?? '' ) === 'publish' && in_array( $row['post_type'] ?? '', $types, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Matching Term Rows.
	 *
	 * @param string $sql Sql.
	 * @return array<int, array<string, mixed>>
	 */
	private function matchingTermRows( string $sql ): array {
		$rows = [];

		foreach ( $this->matchingTerms( $sql ) as $termId ) {
			$latest = '';

			foreach ( $this->relationships[ $termId ] ?? [] as $postId ) {
				foreach ( $this->postsRows as $row ) {
					if ( (int) ( $row['ID'] ?? 0 ) === $postId ) {
						$latest = max( $latest, (string) ( $row['post_modified_gmt'] ?? '' ) );
					}
				}
			}

			if ( '' === $latest ) {
				continue;
			}

			$rows[] = [
				'term_id'     => $termId,
				'lastmod_gmt' => $latest,
			];
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				$cmp = strcmp( (string) $b['lastmod_gmt'], (string) $a['lastmod_gmt'] );

				if ( 0 !== $cmp ) {
					return $cmp;
				}

				return (int) $b['term_id'] - (int) $a['term_id'];
			}
		);

		$limit  = 1 === preg_match( '/LIMIT (\d+)/', $sql, $m ) ? (int) $m[1] : 1000;
		$offset = 1 === preg_match( '/OFFSET (\d+)/', $sql, $m ) ? (int) $m[1] : 0;

		return array_slice( $rows, $offset, $limit );
	}
}
