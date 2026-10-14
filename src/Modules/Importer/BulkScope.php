<?php
/**
 * Bulk scope helpers, bounded id slices and counts for bulk rules.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

/**
 * Enumerate local objects for bulk noindex rules in bounded slices.
 *
 * Bulk rules stamp every object of a post type or taxonomy, which can
 * be tens of thousands of ids. These helpers page through them in
 * runner sized slices instead of loading one capped array, so no
 * record is silently truncated and memory stays bounded.
 *
 * Scope decision: publish, draft, pending, private and future posts
 * are in scope. Trash, auto drafts and attachments (inherit) are
 * never indexed, so stamping them would only churn data.
 */
final class BulkScope {
	/**
	 * Post statuses a bulk noindex rule may stamp.
	 *
	 * @var string[]
	 */
	public const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

	/**
	 * Count local objects of one post type or taxonomy.
	 *
	 * @param string $kind Post or term.
	 * @param string $type Post type or taxonomy.
	 * @return int The count, zero when WordPress is unavailable or the taxonomy is unknown.
	 */
	public static function count( string $kind, string $type ): int {
		if ( 'term' === $kind ) {
			if ( ! function_exists( 'taxonomy_exists' ) || ! function_exists( 'get_terms' ) || ! taxonomy_exists( $type ) ) {
				return 0;
			}

			/**
			 * Term query counter, an int when count is true.
			 *
			 * @var int|\WP_Error $count
			 */
			$count = get_terms(
				[
					'taxonomy'   => $type,
					'hide_empty' => false,
					'count'      => true,
				]
			);

			if ( $count instanceof \WP_Error ) {
				return 0;
			}

			return max( 0, (int) $count );
		}

		if ( ! function_exists( 'wp_count_posts' ) ) {
			return 0;
		}

		$counts = wp_count_posts( $type );
		$total  = 0;

		foreach ( self::STATUSES as $status ) {
			$total += (int) ( $counts->$status ?? 0 );
		}

		return $total;
	}

	/**
	 * List one bounded slice of local ids, oldest first.
	 *
	 * @param string $kind   Post or term.
	 * @param string $type   Post type or taxonomy.
	 * @param int    $offset Slice offset.
	 * @param int    $limit  Slice size.
	 * @return int[] The ids, possibly empty.
	 */
	public static function ids( string $kind, string $type, int $offset, int $limit ): array {
		if ( 'term' === $kind ) {
			if ( ! function_exists( 'taxonomy_exists' ) || ! function_exists( 'get_terms' ) || ! taxonomy_exists( $type ) ) {
				return [];
			}

			$ids = get_terms(
				[
					'taxonomy'   => $type,
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => max( 1, $limit ),
					'offset'     => max( 0, $offset ),
					'orderby'    => 'term_id',
					'order'      => 'ASC',
				]
			);

			return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
		}

		if ( ! function_exists( 'get_posts' ) ) {
			return [];
		}

		$ids = get_posts(
			[
				'post_type'      => $type,
				'post_status'    => self::STATUSES,
				'posts_per_page' => max( 1, $limit ),
				'offset'         => max( 0, $offset ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		return array_map( 'intval', $ids );
	}
}
