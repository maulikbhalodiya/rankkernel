<?php
/**
 * All in One SEO source reader.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Counts AIOSEO importable rows: the aioseo_posts table, _aioseo_*
 * termmeta, the options blob, and the Pro-only redirects table.
 *
 * Redirects live in a Pro-only table, so the free tier always reports
 * zero there and the Preview phase names that gap explicitly.
 */
final class AioseoReader implements SourceReader {
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
		return 'aioseo';
	}

	/**
	 * Human label.
	 *
	 * @return string The label.
	 */
	public function label(): string {
		return 'All in One SEO';
	}

	/**
	 * Count importable rows per kind.
	 *
	 * @return array{posts:int,terms:int,settings:bool,redirects:int} The counts.
	 */
	public function detect(): array {
		$db = $this->db();

		$postsTable = $db->prefix . 'aioseo_posts';
		$postsExist = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $postsTable ) );
		$posts      = null !== $postsExist
			? (int) $db->get_var( "SELECT COUNT(*) FROM {$postsTable}" )
			: 0;

		$like  = $this->likeEscape( '_aioseo_' ) . '%';
		$terms = (int) $db->get_var(
			$db->prepare(
				"SELECT COUNT(DISTINCT term_id) FROM {$db->termmeta} WHERE meta_key LIKE %s",
				$like
			)
		);

		$settings = get_option( 'aioseo_options', false ) !== false;

		$redirectsTable = $db->prefix . 'aioseo_redirects';
		$redirectsExist = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $redirectsTable ) );
		$redirects      = null !== $redirectsExist
			? (int) $db->get_var( "SELECT COUNT(*) FROM {$redirectsTable}" )
			: 0;

		return [
			'posts'     => $posts,
			'terms'     => $terms,
			'settings'  => $settings,
			'redirects' => $redirects,
		];
	}

	/**
	 * Fetch one post chunk from the owned posts table with types.
	 *
	 * @param int $offset Chunk offset.
	 * @param int $limit Chunk size.
	 * @return array<int, array{id: int, type: string, data: array<string, mixed>}> The items.
	 */
	public function fetchPosts( int $offset, int $limit ): array {
		$db    = $this->db();
		$table = $db->prefix . 'aioseo_posts';
		$rows  = $db->get_results(
			$db->prepare( "SELECT * FROM {$table} ORDER BY post_id ASC LIMIT %d, %d", $offset, $limit ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || [] === $rows ) {
			return [];
		}

		$ids   = array_map( 'intval', array_column( $rows, 'post_id' ) );
		$in    = implode( ',', $ids );
		$types = $db->get_results( "SELECT ID, post_type FROM {$db->posts} WHERE ID IN ({$in})", ARRAY_A );

		$byType = [];

		foreach ( is_array( $types ) ? $types : [] as $row ) {
			$byType[ (int) ( $row['ID'] ?? 0 ) ] = (string) ( $row['post_type'] ?? '' );
		}

		$items = [];

		foreach ( $rows as $row ) {
			$id = (int) ( $row['post_id'] ?? 0 );

			$items[] = [
				'id'   => $id,
				'type' => $byType[ $id ] ?? '',
				'data' => $row,
			];
		}

		return $items;
	}

	/**
	 * Fetch one term chunk from _aioseo_ termmeta, keys unprefixed.
	 *
	 * AIOSEO never writes termmeta itself, so this is a fallback path
	 * that only fires on foreign or hand written rows. Keys arrive
	 * stripped of the _aioseo_ prefix so the row mapper sees the bare
	 * column shape it already accepts.
	 *
	 * @param int $offset Chunk offset.
	 * @param int $limit Chunk size.
	 * @return array<int, array{id: int, taxonomy: string, data: array<string, mixed>}> The items.
	 */
	public function fetchTerms( int $offset, int $limit ): array {
		$db   = $this->db();
		$like = $this->likeEscape( '_aioseo_' ) . '%';
		$ids  = $db->get_col(
			$db->prepare(
				"SELECT DISTINCT term_id FROM {$db->termmeta} WHERE meta_key LIKE %s ORDER BY term_id ASC LIMIT %d, %d",
				$like,
				$offset,
				$limit
			)
		);

		if ( ! is_array( $ids ) || [] === $ids ) {
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

		$items = [];

		foreach ( $ids as $id ) {
			$items[ $id ] = [
				'id'       => $id,
				'taxonomy' => '',
				'data'     => [],
			];
		}

		foreach ( is_array( $metas ) ? $metas : [] as $row ) {
			$id  = (int) ( $row['term_id'] ?? 0 );
			$key = (string) ( $row['meta_key'] ?? '' );

			if ( isset( $items[ $id ] ) && str_starts_with( $key, '_aioseo_' ) ) {
				$items[ $id ]['data'][ substr( $key, strlen( '_aioseo_' ) ) ] = $row['meta_value'] ?? '';
			}
		}

		return array_values( $items );
	}

	/**
	 * Decode a possibly double encoded AIOSEO option section.
	 *
	 * @param mixed $raw Raw option value.
	 * @return array<string, mixed> The decoded section.
	 */
	private function decodeSection( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );

		if ( is_string( $decoded ) ) {
			$decoded = json_decode( $decoded, true );
		}

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Fetch the raw appearance sections the sitewide mapper accepts.
	 *
	 * @return array<string, mixed> The raw blob.
	 */
	public function fetchSitewideRaw(): array {
		$options = $this->decodeSection( get_option( 'aioseo_options', [] ) );
		$dynamic = $this->decodeSection( get_option( 'aioseo_options_dynamic', [] ) );
		$search  = $dynamic['searchAppearance'] ?? [];

		return [
			'appearance' => is_array( $options['searchAppearance'] ?? null ) ? $options['searchAppearance'] : [],
			'postTypes'  => is_array( $search['postTypes'] ?? null ) ? $search['postTypes'] : [],
			'taxonomies' => is_array( $search['taxonomies'] ?? null ) ? $search['taxonomies'] : [],
		];
	}

	/**
	 * Fetch raw taxonomy templates for the sitewide mapper.
	 *
	 * @return array<string, mixed> Taxonomy to template row.
	 */
	public function fetchTaxonomies(): array {
		$raw = $this->fetchSitewideRaw();

		return is_array( $raw['taxonomies'] ?? null ) ? $raw['taxonomies'] : [];
	}

	/**
	 * Fetch raw redirect rows: always none, Pro-only in v1.
	 *
	 * @return array<int, array<string, mixed>> The raw rows.
	 */
	public function fetchRedirectsRaw(): array {
		return [];
	}
}
