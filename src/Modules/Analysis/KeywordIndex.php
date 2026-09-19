<?php
/**
 * Keyword uniqueness lookup.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Finds other posts that already target a keyword.
 *
 * Every post keeps its metadata in one row, so a LIKE over that row finds
 * candidates in a single query and no derived index has to be built or kept
 * in step. The result is advisory: it informs the writer and never blocks a
 * save, which is why the check carries no weight in the score.
 */
final class KeywordIndex {
	/**
	 * Meta key holding the per post payload.
	 */
	private const META_KEY = '_rankkernel_meta_data';

	/**
	 * Most candidates to title resolve.
	 */
	private const LIMIT = 20;

	/**
	 * Database handle, global $wpdb unless a double is injected.
	 *
	 * @var \wpdb|null
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Database handle, global $wpdb when null.
	 */
	public function __construct( $db = null ) {
		$this->db = $db;
	}

	/**
	 * Titles of other posts that already target this keyword.
	 *
	 * @param string $keyword       Keyword to look for.
	 * @param int    $excludePostId Post being edited, left out of the result.
	 * @return string[] Post titles, capped and de-duplicated.
	 */
	public function usedElsewhere( string $keyword, int $excludePostId ): array {
		$keyword = trim( $keyword );
		$db      = $this->connection();

		if ( '' === $keyword || null === $db ) {
			return [];
		}

		$table = (string) $db->postmeta;
		$sql   = 'SELECT post_id FROM ' . $table . ' WHERE meta_key = %s AND meta_value LIKE %s AND post_id != %d LIMIT %d';

		$ids = $db->get_col(
			$db->prepare(
				$sql,
				self::META_KEY,
				'%' . $db->esc_like( $keyword ) . '%',
				$excludePostId,
				self::LIMIT
			)
		);

		if ( ! is_array( $ids ) ) {
			return [];
		}

		$titles = [];

		foreach ( $ids as $id ) {
			$title = get_the_title( (int) $id );

			if ( is_string( $title ) && '' !== $title ) {
				$titles[] = $title;
			}
		}

		return array_values( array_unique( $titles ) );
	}

	/**
	 * Active connection, or null when the database is unavailable.
	 *
	 * @return \wpdb|null The result.
	 */
	private function connection() {
		if ( null !== $this->db ) {
			return $this->db;
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}

		return $wpdb;
	}
}
