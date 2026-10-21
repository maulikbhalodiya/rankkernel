<?php
/**
 * In memory wpdb double for importer Detect tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * In memory wpdb double for importer Detect tests.
 *
 * Answers the narrow query set the source readers issue: prepared LIKE
 * counts over postmeta and termmeta, SHOW TABLES LIKE probes, and COUNT
 * queries over the source owned tables. Anything else returns null.
 *
 * The fetch seeders below extend the same double for chunk reads:
 * get_col answers DISTINCT id queries with LIMIT, get_results answers
 * row queries over posts, postmeta, termmeta and the owned tables.
 */
final class ImporterFakeDb {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Postmeta table name.
	 *
	 * @var string
	 */
	public string $postmeta = 'wp_postmeta';

	/**
	 * Termmeta table name.
	 *
	 * @var string
	 */
	public string $termmeta = 'wp_termmeta';

	/**
	 * Posts table name.
	 *
	 * @var string
	 */
	public string $posts = 'wp_posts';

	/**
	 * Seeded post types, id to type.
	 *
	 * @var array<int, string>
	 */
	private array $postTypes = [];

	/**
	 * Seeded postmeta, id to key to value.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $postmetaRows = [];

	/**
	 * Seeded term taxonomies, id to taxonomy.
	 *
	 * @var array<int, string>
	 */
	private array $termTaxonomies = [];

	/**
	 * Seeded termmeta, id to key to value.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $termmetaRows = [];

	/**
	 * Seeded aioseo_posts rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $aioseoRows = [];

	/**
	 * Seeded rank_math_redirections rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $rmRedirectRows = [];

	/**
	 * Constructor.
	 *
	 * @param int[] $yoastPostIds Post ids carrying Yoast meta keys.
	 * @param int[] $rmPostIds    Post ids carrying Rank Math meta keys.
	 * @param bool  $extraTables  Whether rank_math_redirections and aioseo_posts exist.
	 * @param int   $aioseoPosts  Rows in aioseo_posts.
	 * @param int   $rmRedirects  Active rows in rank_math_redirections.
	 * @param int[] $aioseoTermIds Term ids carrying AIOSEO meta keys.
	 */
	public function __construct(
		private array $yoastPostIds = [],
		private array $rmPostIds = [],
		private bool $extraTables = false,
		private int $aioseoPosts = 0,
		private int $rmRedirects = 0,
		private array $aioseoTermIds = []
	) {
	}

	/**
	 * Seed post types for chunk fetches.
	 *
	 * @param array<int, string> $types Id to type.
	 */
	public function seedPostTypes( array $types ): void {
		$this->postTypes = $types;
	}

	/**
	 * Seed postmeta rows for chunk fetches.
	 *
	 * @param array<int, array<string, mixed>> $rows Id to key to value.
	 */
	public function seedPostmeta( array $rows ): void {
		$this->postmetaRows = $rows;
	}

	/**
	 * Seed term taxonomies for chunk fetches.
	 *
	 * @param array<int, string> $taxonomies Id to taxonomy.
	 */
	public function seedTermTaxonomies( array $taxonomies ): void {
		$this->termTaxonomies = $taxonomies;
	}

	/**
	 * Seed termmeta rows for chunk fetches.
	 *
	 * @param array<int, array<string, mixed>> $rows Id to key to value.
	 */
	public function seedTermmeta( array $rows ): void {
		$this->termmetaRows = $rows;
	}

	/**
	 * Seed aioseo_posts rows for chunk fetches.
	 *
	 * @param array<int, array<string, mixed>> $rows Owned table rows.
	 */
	public function seedAioseoPosts( array $rows ): void {
		$this->aioseoRows = array_values( $rows );
	}

	/**
	 * Seed rank_math_redirections rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Owned table rows.
	 */
	public function seedRmRedirects( array $rows ): void {
		$this->rmRedirectRows = array_values( $rows );
	}

	/**
	 * Parse a LIMIT offset and count off a query.
	 *
	 * @param string $sql Query.
	 * @return array{int, int} Offset and count, count zero when absent.
	 */
	private function parseLimit( string $sql ): array {
		if ( preg_match( '/LIMIT\s+(\d+)\s*,\s*(\d+)/', $sql, $m ) ) {
			return [ (int) $m[1], (int) $m[2] ];
		}

		if ( preg_match( '/LIMIT\s+(\d+)/', $sql, $m ) ) {
			return [ 0, (int) $m[1] ];
		}

		return [ 0, 0 ];
	}

	/**
	 * Parse an IN id list off a query.
	 *
	 * @param string $sql Query.
	 * @return int[] The ids.
	 */
	private function parseIds( string $sql ): array {
		if ( ! preg_match( '/IN\s*\(([\d\s,]+)\)/', $sql, $m ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map( 'intval', explode( ',', $m[1] ) ),
				static fn ( int $id ): bool => 0 < $id
			)
		);
	}

	/**
	 * Prepare.
	 *
	 * @param string $query Query.
	 * @param mixed  ...$args Args.
	 * @return string Query with the single %s placeholder filled raw.
	 *
	 * Args pass through unescaped on purpose: get_var strips one escape
	 * level, which exactly undoes the reader side addcslashes, mirroring
	 * how the real prepare plus query execution net out.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		if ( [] === $args ) {
			return $query;
		}

		$out = $query;

		foreach ( $args as $arg ) {
			$posS = strpos( $out, '%s' );
			$posD = strpos( $out, '%d' );

			if ( false === $posS && false === $posD ) {
				break;
			}

			if ( false !== $posS && ( false === $posD || $posS < $posD ) ) {
				$out = substr_replace( $out, "'" . (string) $arg . "'", $posS, 2 );
			} else {
				$out = substr_replace( $out, (string) (int) $arg, $posD, 2 );
			}
		}

		return $out;
	}

	/**
	 * Get var.
	 *
	 * @param string|null $query Query.
	 * @return mixed Seeded answer or null.
	 */
	public function get_var( ?string $query = null ): mixed {
		$sql = stripslashes( (string) $query );

		if ( str_contains( $sql, 'SHOW TABLES LIKE' ) ) {
			if ( str_contains( $sql, 'rank_math_redirections' ) || str_contains( $sql, 'aioseo_posts' ) ) {
				return $this->extraTables ? 'wp_table' : null;
			}

			return null;
		}

		if ( str_contains( $sql, 'aioseo_posts' ) ) {
			return $this->extraTables ? $this->aioseoPosts : null;
		}

		if ( str_contains( $sql, 'rank_math_redirections' ) ) {
			return $this->extraTables ? $this->rmRedirects : null;
		}

		if ( str_contains( $sql, 'COUNT(DISTINCT' ) && str_contains( $sql, 'post_id' ) ) {
			if ( str_contains( $sql, '_yoast_wpseo' ) ) {
				return count( array_unique( $this->yoastPostIds ) );
			}

			if ( str_contains( $sql, 'rank_math_' ) ) {
				return count( array_unique( $this->rmPostIds ) );
			}

			return 0;
		}

		if ( str_contains( $sql, 'COUNT(DISTINCT' ) && str_contains( $sql, 'term_id' ) ) {
			if ( str_contains( $sql, '_aioseo_' ) ) {
				return count( array_unique( $this->aioseoTermIds ) );
			}

			return 0;
		}

		return null;
	}

	/**
	 * Get column.
	 *
	 * Answers DISTINCT id chunk queries over postmeta and termmeta.
	 *
	 * @param string|null $query Query.
	 * @return array<int> The ids in ascending order.
	 */
	public function get_col( ?string $query = null ): array {
		$sql                = stripslashes( (string) $query );
		[ $offset, $count ] = $this->parseLimit( $sql );

		$ids = [];

		if ( str_contains( $sql, 'post_id' ) ) {
			$prefix = str_contains( $sql, '_yoast_wpseo' ) ? '_yoast_wpseo_' : ( str_contains( $sql, 'rank_math_' ) ? 'rank_math_' : '' );

			if ( '' !== $prefix ) {
				foreach ( $this->postmetaRows as $id => $meta ) {
					foreach ( array_keys( $meta ) as $key ) {
						if ( str_starts_with( (string) $key, $prefix ) ) {
							$ids[] = $id;

							break;
						}
					}
				}
			}
		}

		if ( str_contains( $sql, 'term_id' ) ) {
			$prefix = str_contains( $sql, '_aioseo_' ) ? '_aioseo_' : ( str_contains( $sql, 'rank_math_' ) ? 'rank_math_' : '' );

			if ( '' !== $prefix ) {
				foreach ( $this->termmetaRows as $id => $meta ) {
					foreach ( array_keys( $meta ) as $key ) {
						if ( str_starts_with( (string) $key, $prefix ) ) {
							$ids[] = $id;

							break;
						}
					}
				}
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids );

		if ( ! str_contains( strtoupper( $sql ), 'LIMIT' ) ) {
			return array_slice( $ids, $offset );
		}

		return array_slice( $ids, $offset, $count );
	}

	/**
	 * Get results.
	 *
	 * Answers row queries over posts, postmeta, termmeta and the owned
	 * source tables for the seeded fixtures.
	 *
	 * @param string|null $query Query.
	 * @param mixed       $output Ignored, rows always come back as arrays.
	 * @return array<int, array<string, mixed>> The rows.
	 */
	public function get_results( ?string $query = null, mixed $output = null ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb method signature.
		$sql                = stripslashes( (string) $query );
		[ $offset, $count ] = $this->parseLimit( $sql );

		if ( str_contains( $sql, 'aioseo_posts' ) && ! str_contains( $sql, 'COUNT' ) && ! str_contains( $sql, 'SHOW TABLES' ) ) {
			return array_slice( $this->aioseoRows, $offset, $count );
		}

		if ( str_contains( $sql, 'rank_math_redirections' ) && ! str_contains( $sql, 'COUNT' ) ) {
			return $this->rmRedirectRows;
		}

		if ( str_contains( $sql, 'FROM wp_postmeta' ) || str_contains( $sql, 'FROM ' . $this->postmeta ) ) {
			$ids    = $this->parseIds( $sql );
			$prefix = str_contains( $sql, '_yoast_wpseo' ) ? '_yoast_wpseo_' : ( str_contains( $sql, 'rank_math_' ) ? 'rank_math_' : '' );
			$rows   = [];

			foreach ( $ids as $id ) {
				foreach ( $this->postmetaRows[ $id ] ?? [] as $key => $value ) {
					if ( '' === $prefix || str_starts_with( (string) $key, $prefix ) ) {
						$rows[] = [
							'post_id'    => $id,
							'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fake result column name, no query involved.
							'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- fake result column name, no query involved.
						];
					}
				}
			}

			return $rows;
		}

		if ( str_contains( $sql, 'FROM wp_termmeta' ) || str_contains( $sql, 'FROM ' . $this->termmeta ) ) {
			$ids    = $this->parseIds( $sql );
			$prefix = str_contains( $sql, '_aioseo_' ) ? '_aioseo_' : ( str_contains( $sql, 'rank_math_' ) ? 'rank_math_' : '' );
			$rows   = [];

			foreach ( $ids as $id ) {
				foreach ( $this->termmetaRows[ $id ] ?? [] as $key => $value ) {
					if ( '' === $prefix || str_starts_with( (string) $key, $prefix ) ) {
						$rows[] = [
							'term_id'    => $id,
							'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fake result column name, no query involved.
							'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- fake result column name, no query involved.
						];
					}
				}
			}

			return $rows;
		}

		if ( str_contains( $sql, 'term_taxonomy' ) ) {
			$ids  = $this->parseIds( $sql );
			$rows = [];

			foreach ( $ids as $id ) {
				if ( isset( $this->termTaxonomies[ $id ] ) ) {
					$rows[] = [
						'term_id'  => $id,
						'taxonomy' => $this->termTaxonomies[ $id ],
					];
				}
			}

			return $rows;
		}

		if ( str_contains( $sql, 'FROM wp_posts' ) || str_contains( $sql, 'FROM ' . $this->posts ) ) {
			$ids  = $this->parseIds( $sql );
			$rows = [];

			foreach ( $ids as $id ) {
				if ( isset( $this->postTypes[ $id ] ) ) {
					$rows[] = [
						'ID'        => $id,
						'post_type' => $this->postTypes[ $id ],
					];
				}
			}

			return $rows;
		}

		return [];
	}
}
