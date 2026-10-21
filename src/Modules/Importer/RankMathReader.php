<?php
/**
 * Rank Math source reader.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Counts Rank Math importable rows: rank_math_* postmeta and termmeta,
 * the titles option, and active rows in the redirections table.
 *
 * The redirections table only exists when the module ran at least once,
 * so a missing table reads as zero rows, never as an error.
 */
final class RankMathReader implements SourceReader {
	/**
	 * Database handle, global $wpdb unless a double is injected.
	 *
	 * @var mixed
	 */
	private $db = null;

	/**
	 * Constructor.
	 *
	 * @param mixed $db Database handle, global $wpdb when null.
	 */
	public function __construct( $db = null ) {
		$this->db = $db;
	}

	/**
	 * Resolve the database handle.
	 *
	 * @return mixed The handle.
	 */
	private function db() {
		if ( null !== $this->db ) {
			return $this->db;
		}

		global $wpdb;

		return $wpdb;
	}

	/**
	 * Escape a search needle for a LIKE comparison.
	 *
	 * @param string $search Raw needle.
	 * @return string Escaped needle.
	 */
	private function likeEscape( string $search ): string {
		$db = $this->db();

		if ( null !== $db && method_exists( $db, 'esc_like' ) ) {
			return (string) $db->esc_like( $search );
		}

		return addcslashes( $search, '_%\\' );
	}

	/**
	 * Machine id.
	 *
	 * @return string The id.
	 */
	public function id(): string {
		return 'rank-math';
	}

	/**
	 * Human label.
	 *
	 * @return string The label.
	 */
	public function label(): string {
		return 'Rank Math';
	}

	/**
	 * Count importable rows per kind.
	 *
	 * @return array{posts:int,terms:int,settings:bool,redirects:int} The counts.
	 */
	public function detect(): array {
		$db    = $this->db();
		$like  = $this->likeEscape( 'rank_math_' ) . '%';
		$posts = (int) $db->get_var(
			$db->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$db->postmeta} WHERE meta_key LIKE %s",
				$like
			)
		);
		$terms = (int) $db->get_var(
			$db->prepare(
				"SELECT COUNT(DISTINCT term_id) FROM {$db->termmeta} WHERE meta_key LIKE %s",
				$like
			)
		);

		$settings = get_option( 'rank-math-options-titles', false ) !== false;

		$table     = $db->prefix . 'rank_math_redirections';
		$exists    = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$redirects = null !== $exists
			? (int) $db->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" )
			: 0;

		return [
			'posts'     => $posts,
			'terms'     => $terms,
			'settings'  => $settings,
			'redirects' => $redirects,
		];
	}

	/**
	 * Fetch one post chunk with types and rank_math_ meta rows.
	 *
	 * @param int    $offset Chunk offset.
	 * @param int    $limit Chunk size.
	 * @param string $search Optional search string.
	 * @return array<int, array{id: int, type: string, data: array<string, mixed>}> The items.
	 */
	public function fetchPosts( int $offset, int $limit, string $search = '' ): array {
		$db     = $this->db();
		$like   = $this->likeEscape( 'rank_math_' ) . '%';
		$search = trim( $search );

		if ( '' === $search ) {
			$ids = $db->get_col(
				$db->prepare(
					"SELECT DISTINCT post_id FROM {$db->postmeta} WHERE meta_key LIKE %s ORDER BY post_id ASC LIMIT %d, %d",
					$like,
					$offset,
					$limit
				)
			);
		} else {
			$titleLike = '%' . $this->likeEscape( $search ) . '%';
			$ids       = $db->get_col(
				$db->prepare(
					"SELECT DISTINCT pm.post_id FROM {$db->postmeta} pm JOIN {$db->posts} p ON p.ID = pm.post_id WHERE pm.meta_key LIKE %s AND p.post_title LIKE %s ORDER BY pm.post_id ASC LIMIT %d, %d",
					$like,
					$titleLike,
					$offset,
					$limit
				)
			);
		}

		if ( ! is_array( $ids ) || [] === $ids ) {
			return [];
		}

		$ids   = array_map( 'intval', $ids );
		$in    = implode( ',', $ids );
		$metas = $db->get_results(
			$db->prepare(
				"SELECT post_id, meta_key, meta_value FROM {$db->postmeta} WHERE post_id IN ({$in}) AND meta_key LIKE %s",
				$like
			),
			ARRAY_A
		);
		$types = $db->get_results( "SELECT ID, post_type FROM {$db->posts} WHERE ID IN ({$in})", ARRAY_A );

		$byType = [];

		foreach ( is_array( $types ) ? $types : [] as $row ) {
			$byType[ (int) ( $row['ID'] ?? 0 ) ] = (string) ( $row['post_type'] ?? '' );
		}

		$items = [];

		foreach ( $ids as $id ) {
			$items[ $id ] = [
				'id'   => $id,
				'type' => $byType[ $id ] ?? '',
				'data' => [],
			];
		}

		foreach ( is_array( $metas ) ? $metas : [] as $row ) {
			$id = (int) ( $row['post_id'] ?? 0 );

			if ( isset( $items[ $id ] ) ) {
				$items[ $id ]['data'][ (string) ( $row['meta_key'] ?? '' ) ] = $row['meta_value'] ?? '';
			}
		}

		return array_values( $items );
	}

	/**
	 * Fetch one term chunk with taxonomies and rank_math_ meta rows.
	 *
	 * @param int    $offset Chunk offset.
	 * @param int    $limit Chunk size.
	 * @param string $search Optional search string.
	 * @return array<int, array{id: int, taxonomy: string, data: array<string, mixed>}> The items.
	 */
	public function fetchTerms( int $offset, int $limit, string $search = '' ): array {
		$db     = $this->db();
		$like   = $this->likeEscape( 'rank_math_' ) . '%';
		$search = trim( $search );

		$allIds = $db->get_col(
			$db->prepare(
				"SELECT DISTINCT term_id FROM {$db->termmeta} WHERE meta_key LIKE %s",
				$like
			)
		);

		$allIds = array_values( array_unique( array_map( 'intval', is_array( $allIds ) ? $allIds : [] ) ) );
		sort( $allIds );

		if ( '' !== $search && [] !== $allIds ) {
			$in        = implode( ',', $allIds );
			$titleLike = '%' . $this->likeEscape( $search ) . '%';
			$allowed   = $db->get_col(
				$db->prepare(
					"SELECT term_id FROM {$db->terms} WHERE term_id IN ({$in}) AND name LIKE %s",
					$titleLike
				)
			);
			$allowed   = array_map( 'intval', is_array( $allowed ) ? $allowed : [] );
			$allIds    = array_values( array_intersect( $allIds, $allowed ) );
		}

		$ids = array_slice( $allIds, $offset, $limit );

		if ( [] === $ids ) {
			return [];
		}

		$ids   = array_map( 'intval', $ids );
		$in    = implode( ',', $ids );
		$metas = $db->get_results(
			$db->prepare(
				"SELECT term_id, meta_key, meta_value FROM {$db->termmeta} WHERE term_id IN ({$in}) AND meta_key LIKE %s",
				$like
			),
			ARRAY_A
		);
		$tax   = $db->get_results( "SELECT term_id, taxonomy FROM {$db->prefix}term_taxonomy WHERE term_id IN ({$in})", ARRAY_A );

		$byTax = [];

		foreach ( is_array( $tax ) ? $tax : [] as $row ) {
			$byTax[ (int) ( $row['term_id'] ?? 0 ) ] = (string) ( $row['taxonomy'] ?? '' );
		}

		$items = [];

		foreach ( $ids as $id ) {
			$items[ $id ] = [
				'id'       => $id,
				'taxonomy' => $byTax[ $id ] ?? '',
				'data'     => [],
			];
		}

		foreach ( is_array( $metas ) ? $metas : [] as $row ) {
			$id = (int) ( $row['term_id'] ?? 0 );

			if ( isset( $items[ $id ] ) ) {
				$items[ $id ]['data'][ (string) ( $row['meta_key'] ?? '' ) ] = $row['meta_value'] ?? '';
			}
		}

		return array_values( $items );
	}

	/**
	 * Fetch the raw titles blob the sitewide mapper accepts.
	 *
	 * @return array<string, mixed> The raw blob.
	 */
	public function fetchSitewideRaw(): array {
		$titles = get_option( 'rank-math-options-titles', [] );

		return is_array( $titles ) ? $titles : [];
	}

	/**
	 * Fetch raw redirection table rows the redirect mapper accepts.
	 *
	 * @return array<int, array<string, mixed>> The raw rows.
	 */
	public function fetchRedirectsRaw(): array {
		$db     = $this->db();
		$table  = $db->prefix . 'rank_math_redirections';
		$exists = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( null === $exists ) {
			return [];
		}

		$rows = $db->get_results( "SELECT sources, url_to, header_code, status FROM {$table}", ARRAY_A );

		return is_array( $rows ) ? array_values( $rows ) : [];
	}
}
