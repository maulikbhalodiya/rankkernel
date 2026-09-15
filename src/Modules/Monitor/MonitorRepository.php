<?php
/**
 * 404 log repository, dedupe writes and bounded reads over wp_rankkernel_404_log.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

defined( 'ABSPATH' ) || exit;

/**
 * All database access for the 404 log, prepared statements only.
 *
 * Repeat hits for one URI update a single row through the UNIQUE uri_hash
 * index, so a flood of one URL costs one row. Deletes are always bounded:
 * every prune and clear path carries an explicit LIMIT, and TRUNCATE is
 * never used anywhere in this class.
 */
final class MonitorRepository {
	/**
	 * Sortable columns for paginated lists.
	 *
	 * @var string[]
	 */
	private const ORDERABLE = [
		'id',
		'uri',
		'hits',
		'created',
		'last_accessed',
	];

	/**
	 * Database handle, global $wpdb unless a double is injected.
	 *
	 * @var \wpdb|null
	 */
	private $db = null;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Database handle, global $wpdb when null.
	 */
	public function __construct( $db = null ) {
		$this->db = $db;
	}

	/**
	 * Record one 404 hit, insert when new, increment when seen before.
	 *
	 * The UNIQUE uri_hash constraint is the dedupe guard: a concurrent
	 * insert that loses the race falls back to the increment path, so one
	 * URI can never produce two rows. Advanced fields ride along on the
	 * insert and refresh to the latest values on repeat hits; empty values
	 * leave the stored fields untouched.
	 *
	 * @param string $uriHash   SHA256 hex of the normalized URI.
	 * @param string $uri       Normalized URI.
	 * @param string $referer   Referer, truncated to 255 by the caller.
	 * @param string $userAgent User agent, truncated to 255 by the caller.
	 * @return string One of insert, update, or empty string on failure.
	 */
	public function record( string $uriHash, string $uri, string $referer = '', string $userAgent = '' ): string {
		$db = $this->connection();

		if ( null === $db || '' === $uriHash || '' === $uri ) {
			return '';
		}

		$now   = $this->now();
		$table = LogTable::name();

		$existing = $this->findByHash( $uriHash );

		if ( null !== $existing ) {
			$data = [
				'hits'          => (int) ( $existing['hits'] ?? 0 ) + 1,
				'last_accessed' => $now,
			];

			$format = [ '%d', '%s' ];

			if ( '' !== $referer ) {
				$data['referer'] = $referer;
				$format[]        = '%s';
			}

			if ( '' !== $userAgent ) {
				$data['user_agent'] = $userAgent;
				$format[]           = '%s';
			}

			// Custom log tables have no core API, counter increment against the unique hash.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $db->update( $table, $data, [ 'uri_hash' => $uriHash ], $format, [ '%s' ] );

			return false === $ok ? '' : 'update';
		}

		// Custom log tables have no core API, typed insert with format list.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $db->insert(
			$table,
			[
				'uri_hash'      => $uriHash,
				'uri'           => $uri,
				'hits'          => 1,
				'referer'       => $referer,
				'user_agent'    => $userAgent,
				'created'       => $now,
				'last_accessed' => $now,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $ok ) {
			$retry = $this->findByHash( $uriHash );

			if ( null === $retry ) {
				return '';
			}

			// Custom log tables have no core API, race loser takes the increment path.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$fixed = $db->update(
				$table,
				[
					'hits'          => (int) ( $retry['hits'] ?? 0 ) + 1,
					'last_accessed' => $now,
				],
				[ 'uri_hash' => $uriHash ],
				[ '%d', '%s' ],
				[ '%s' ]
			);

			return false === $fixed ? '' : 'update';
		}

		return 'insert';
	}

	/**
	 * Find one row by URI hash, the dedupe lookup.
	 *
	 * @param string $uriHash SHA256 hex of the normalized URI.
	 * @return array<string, mixed>|null Row or null.
	 */
	public function findByHash( string $uriHash ): ?array {
		$db = $this->connection();

		if ( null === $db || '' === $uriHash ) {
			return null;
		}

		$table = LogTable::name();
		$sql   = "SELECT * FROM `{$table}` WHERE uri_hash = %s LIMIT 1";

		// Custom log tables have no core API, single indexed row fetch with a placeholder.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $db->get_row( $db->prepare( $sql, $uriHash ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get one row by id, for the admin list view.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>|null Row or null.
	 */
	public function findById( int $id ): ?array {
		$db = $this->connection();

		if ( null === $db || $id <= 0 ) {
			return null;
		}

		$table = LogTable::name();
		$sql   = "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1";

		// Custom log tables have no core API, primary key fetch with a placeholder.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $db->get_row( $db->prepare( $sql, $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Count rows under the given filters.
	 *
	 * @param array<string, mixed> $filters Search needle.
	 * @return int Matching row count.
	 */
	public function count( array $filters = [] ): int {
		$db = $this->connection();

		if ( null === $db ) {
			return 0;
		}

		return $this->countFiltered( $this->filteredWhere( $filters ) );
	}

	/**
	 * Current usage against the configured maximum.
	 *
	 * @param int $maxRows Configured row limit.
	 * @return array{count: int, max: int, percent: float} Usage snapshot.
	 */
	public function usage( int $maxRows ): array {
		$count = $this->count();
		$max   = max( 1, $maxRows );

		return [
			'count'   => $count,
			'max'     => $maxRows,
			'percent' => round( ( $count / $max ) * 100, 1 ),
		];
	}

	/**
	 * Paginated log list with search and sorting, for the admin list view.
	 *
	 * @param array<string, mixed> $args Search, orderby, order, page, per_page, min_hits.
	 * @return array{rows: array<int, array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
	 */
	public function paginate( array $args = [] ): array {
		$db = $this->connection();

		$empty = [
			'rows'     => [],
			'total'    => 0,
			'pages'    => 0,
			'page'     => 1,
			'per_page' => 20,
		];

		if ( null === $db ) {
			return $empty;
		}

		$orderby = (string) ( $args['orderby'] ?? 'last_accessed' );

		if ( ! in_array( $orderby, self::ORDERABLE, true ) ) {
			$orderby = 'last_accessed';
		}

		$order = strtoupper( (string) ( $args['order'] ?? 'DESC' ) );

		if ( 'ASC' !== $order && 'DESC' !== $order ) {
			$order = 'DESC';
		}

		$page    = max( 1, (int) ( $args['page'] ?? 1 ) );
		$perPage = (int) ( $args['per_page'] ?? 20 );
		$perPage = max( 1, min( 100, $perPage ) );
		$offset  = ( $page - 1 ) * $perPage;

		$filtered = $this->filteredWhere( $args );
		$table    = LogTable::name();
		$total    = $this->countFiltered( $filtered );

		$selectArgs = array_merge( [ "SELECT * FROM `{$table}` {$filtered[0]} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d" ], $filtered[1], [ $perPage, $offset ] );

		// Custom log tables have no core API, order column is whitelisted, values pass through prepare unpacking.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $db->get_results( $db->prepare( ...$selectArgs ), ARRAY_A );

		$clean = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					$clean[] = $row;
				}
			}
		}

		return [
			'rows'     => $clean,
			'total'    => $total,
			'pages'    => 0 === $total ? 0 : (int) ceil( $total / $perPage ),
			'page'     => $page,
			'per_page' => $perPage,
		];
	}

	/**
	 * Delete one row by id.
	 *
	 * @param int $id Row id.
	 * @return bool True on success.
	 */
	public function deleteById( int $id ): bool {
		$db = $this->connection();

		if ( null === $db || $id <= 0 ) {
			return false;
		}

		$table = LogTable::name();

		// Custom log tables have no core API, delete against the primary key.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $db->delete( $table, [ 'id' => $id ], [ '%d' ] );

		return false !== $ok;
	}

	/**
	 * Delete a list of ids, for admin bulk actions.
	 *
	 * @param int[] $ids Row ids.
	 * @return int Deleted row count.
	 */
	public function deleteMany( array $ids ): int {
		$db = $this->connection();

		if ( null === $db ) {
			return 0;
		}

		$clean = [];

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		if ( [] === $clean ) {
			return 0;
		}

		$table = LogTable::name();
		$list  = implode( ',', $clean );

		// Custom log tables have no core API, id list is cast to integers before interpolation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$affected = $db->query( "DELETE FROM `{$table}` WHERE id IN ({$list})" );

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * Delete one bounded batch of the oldest rows, for manual clear loops.
	 *
	 * The admin clear path calls this in a loop until the table is empty or
	 * a pass cap is hit. Never TRUNCATE, always one LIMIT batch per call.
	 *
	 * @param int $limit Rows per batch, capped at 500.
	 * @return int Deleted row count.
	 */
	public function clearAllBounded( int $limit = 500 ): int {
		$db = $this->connection();

		if ( null === $db ) {
			return 0;
		}

		$batch = max( 1, min( 500, $limit ) );
		$table = LogTable::name();

		// Custom log tables have no core API, bounded oldest first batch with an integer limit.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$affected = $db->query( $db->prepare( "DELETE FROM `{$table}` ORDER BY id ASC LIMIT %d", $batch ) );

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * Delete rows last seen before the cutoff, oldest first, bounded.
	 *
	 * @param string $cutoff MySQL datetime, rows older than this are removed.
	 * @param int    $limit  Rows per pass, capped at 500.
	 * @return int Deleted row count.
	 */
	public function deleteOlderThan( string $cutoff, int $limit = 500 ): int {
		$db = $this->connection();

		if ( null === $db || '' === $cutoff ) {
			return 0;
		}

		$batch = max( 1, min( 500, $limit ) );
		$table = LogTable::name();

		// Custom log tables have no core API, bounded age prune with placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$affected = $db->query( $db->prepare( "DELETE FROM `{$table}` WHERE last_accessed < %s ORDER BY last_accessed ASC, id ASC LIMIT %d", $cutoff, $batch ) );

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * Delete the oldest rows down toward the maximum, bounded.
	 *
	 * Deletes at most the smaller of the excess and the limit, oldest first
	 * by last_accessed then id. Returns zero when the table is within limit.
	 *
	 * @param int $maxRows Configured row limit.
	 * @param int $limit   Rows per pass, capped at 500.
	 * @return int Deleted row count.
	 */
	public function deleteOldestOver( int $maxRows, int $limit ): int {
		$db = $this->connection();

		if ( null === $db || $maxRows < 1 ) {
			return 0;
		}

		$total = $this->count();

		if ( $total <= $maxRows ) {
			return 0;
		}

		$batch = max( 1, min( 500, $limit ) );
		$batch = min( $batch, $total - $maxRows );
		$table = LogTable::name();

		// Custom log tables have no core API, bounded count prune with an integer limit.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$affected = $db->query( $db->prepare( "DELETE FROM `{$table}` ORDER BY last_accessed ASC, id ASC LIMIT %d", $batch ) );

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * Active connection or null when the database is unavailable.
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

	/**
	 * Current MySQL time.
	 *
	 * @return string The result.
	 */
	private function now(): string {
		if ( function_exists( 'current_time' ) ) {
			return (string) current_time( 'mysql' );
		}

		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Build the shared WHERE clause plus params for filtered queries.
	 *
	 * @param array<string, mixed> $filters Search, min_hits.
	 * @return array{0: string, 1: list<string>} Clause and params.
	 */
	private function filteredWhere( array $filters ): array {
		$where  = 'WHERE 1=1';
		$params = [];

		$search = trim( (string) ( $filters['search'] ?? '' ) );

		if ( '' !== $search ) {
			$where   .= ' AND uri LIKE %s';
			$params[] = '%' . $this->likeEscape( $search ) . '%';
		}

		$minHits = (int) ( $filters['min_hits'] ?? 0 );

		if ( $minHits > 0 ) {
			$where   .= ' AND hits >= %d';
			$params[] = (string) $minHits;
		}

		return [ $where, $params ];
	}

	/**
	 * Count rows for a prebuilt WHERE clause plus params.
	 *
	 * @param array{0: string, 1: list<string>} $filtered Clause and params.
	 * @return int Row count.
	 */
	private function countFiltered( array $filtered ): int {
		$db = $this->connection();

		if ( null === $db ) {
			return 0;
		}

		$table = LogTable::name();
		$args  = array_merge( [ "SELECT COUNT(*) FROM `{$table}` {$filtered[0]}" ], $filtered[1] );

		// Custom log tables have no core API, count query with placeholders through prepare unpacking.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$count = $db->get_var( $db->prepare( ...$args ) );

		return (int) $count;
	}

	/**
	 * Escape a search needle for a LIKE comparison.
	 *
	 * @param string $search Raw needle.
	 * @return string Escaped needle.
	 */
	private function likeEscape( string $search ): string {
		$db = $this->connection();

		if ( null !== $db && method_exists( $db, 'esc_like' ) ) {
			return (string) $db->esc_like( $search );
		}

		return addcslashes( $search, '_%\\' );
	}
}
