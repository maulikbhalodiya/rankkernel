<?php
/**
 * Yoast SEO source reader.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Counts Yoast importable rows: legacy postmeta, taxonomy meta option,
 * sitewide titles option, and Premium redirect rows.
 *
 * Reads the legacy `_yoast_wpseo_*` postmeta keys, not the indexable
 * table: the legacy keys are Yoast's stable backward compatibility
 * contract and exist even when indexable rows were never built.
 */
final class YoastReader implements SourceReader {
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
		return 'yoast';
	}

	/**
	 * Human label.
	 *
	 * @return string The label.
	 */
	public function label(): string {
		return 'Yoast SEO';
	}

	/**
	 * Count importable rows per kind.
	 *
	 * @return array{posts:int,terms:int,settings:bool,redirects:int} The counts.
	 */
	public function detect(): array {
		$db    = $this->db();
		$like  = $this->likeEscape( '_yoast_wpseo_' ) . '%';
		$posts = (int) $db->get_var(
			$db->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$db->postmeta} WHERE meta_key LIKE %s",
				$like
			)
		);

		$tax   = get_option( 'wpseo_taxonomy_meta', [] );
		$terms = 0;
		if ( is_array( $tax ) ) {
			foreach ( $tax as $entries ) {
				if ( is_array( $entries ) ) {
					$terms += count( $entries );
				}
			}
		}

		$settings  = get_option( 'wpseo_titles', false ) !== false;
		$redirects = get_option( 'wpseo-premium-redirects-base', [] );

		return [
			'posts'     => $posts,
			'terms'     => $terms,
			'settings'  => $settings,
			'redirects' => is_array( $redirects ) ? count( $redirects ) : 0,
		];
	}

	/**
	 * Fetch one post chunk with types and legacy meta rows.
	 *
	 * @param int    $offset Chunk offset.
	 * @param int    $limit Chunk size.
	 * @param string $search Optional search string.
	 * @return array<int, array{id: int, type: string, data: array<string, mixed>}> The items.
	 */
	public function fetchPosts( int $offset, int $limit, string $search = '' ): array {
		$db     = $this->db();
		$like   = $this->likeEscape( '_yoast_wpseo_' ) . '%';
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
	 * Fetch one term chunk flattened from the taxonomy meta option.
	 *
	 * @param int    $offset Chunk offset.
	 * @param int    $limit Chunk size.
	 * @param string $search Optional search string.
	 * @return array<int, array{id: int, taxonomy: string, data: array<string, mixed>}> The items.
	 */
	public function fetchTerms( int $offset, int $limit, string $search = '' ): array {
		$tax  = get_option( 'wpseo_taxonomy_meta', [] );
		$flat = [];

		if ( is_array( $tax ) ) {
			ksort( $tax );

			foreach ( $tax as $taxonomy => $entries ) {
				if ( ! is_array( $entries ) ) {
					continue;
				}

				ksort( $entries );

				foreach ( $entries as $termId => $row ) {
					$flat[] = [
						'id'       => (int) $termId,
						'taxonomy' => (string) $taxonomy,
						'data'     => is_array( $row ) ? $row : [],
					];
				}
			}
		}

		$search = trim( $search );

		if ( '' !== $search && [] !== $flat ) {
			$db        = $this->db();
			$ids       = array_values( array_unique( array_map( static fn( $item ) => (int) $item['id'], $flat ) ) );
			$in        = implode( ',', array_map( 'intval', $ids ) );
			$titleLike = '%' . $this->likeEscape( $search ) . '%';
			$allowed   = $db->get_col(
				$db->prepare(
					"SELECT term_id FROM {$db->terms} WHERE term_id IN ({$in}) AND name LIKE %s",
					$titleLike
				)
			);
			$allowed   = array_map( 'intval', is_array( $allowed ) ? $allowed : [] );
			$flat      = array_values(
				array_filter(
					$flat,
					static fn( $item ): bool => in_array( (int) $item['id'], $allowed, true )
				)
			);
		}

		return array_slice( $flat, $offset, $limit );
	}

	/**
	 * Fetch the raw titles blob the sitewide mapper accepts.
	 *
	 * @return array<string, mixed> The raw blob.
	 */
	public function fetchSitewideRaw(): array {
		$titles = get_option( 'wpseo_titles', [] );

		return is_array( $titles ) ? $titles : [];
	}

	/**
	 * Fetch raw Premium redirect rows the redirect mapper accepts.
	 *
	 * @return array<int, array<string, mixed>> The raw rows.
	 */
	public function fetchRedirectsRaw(): array {
		$rows = get_option( 'wpseo-premium-redirects-base', [] );

		return is_array( $rows ) ? array_values( $rows ) : [];
	}
}
