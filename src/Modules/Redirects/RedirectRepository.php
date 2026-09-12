<?php
/**
 * Redirect rule repository, CRUD and queries over wp_rankkernel_redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * All database access for redirect rules, prepared statements only.
 *
 * Exact lookups resolve through the UNIQUE (match_type, source_hash) index in
 * one query. Pattern rules load as a small active list for in memory
 * matching, never through serialized LIKE scans. Every successful write
 * invalidates the match cache through the attached RedirectCache.
 */
final class RedirectRepository {
	/**
	 * Sortable columns for paginated lists.
	 *
	 * @var string[]
	 */
	private const ORDERABLE = [
		'id',
		'source',
		'target',
		'code',
		'match_type',
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
	 * Match cache for invalidation on writes, optional.
	 */
	private ?RedirectCache $cache = null;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null         $db    Database handle, global $wpdb when null.
	 * @param RedirectCache|null $cache Cache to invalidate on writes.
	 */
	public function __construct( $db = null, ?RedirectCache $cache = null ) {
		$this->db    = $db;
		$this->cache = $cache;
	}

	/**
	 * Attach the match cache for write invalidation.
	 *
	 * @param RedirectCache $cache Match cache.
	 */
	public function setCache( RedirectCache $cache ): void {
		$this->cache = $cache;
	}

	/**
	 * Find one rule by matcher and source hash, the indexed hot path.
	 *
	 * @param string $matchType  Matcher name.
	 * @param string $sourceHash SHA256 hex from Normalizer::hash().
	 * @return array<string, mixed>|null Rule row or null.
	 */
	public function find( string $matchType, string $sourceHash ): ?array {
		$db = $this->connection();

		if ( null === $db ) {
			return null;
		}

		$table = RedirectTable::name();
		$sql   = "SELECT * FROM `{$table}` WHERE match_type = %s AND source_hash = %s LIMIT 1";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, single indexed row fetch with placeholders.
		$row = $db->get_row( $db->prepare( $sql, $matchType, $sourceHash ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Exact lookup for a raw path, normalizes and hashes before find().
	 *
	 * @param string $path      Raw or normalized path.
	 * @param string $matchType Matcher name, exact by default.
	 * @return array<string, mixed>|null Rule row or null.
	 */
	public function lookup( string $path, string $matchType = 'exact' ): ?array {
		$normalized = Normalizer::normalize( $path );

		if ( Normalizer::isBlockedSource( $normalized ) ) {
			return null;
		}

		return $this->find( $matchType, Normalizer::hash( $matchType, $normalized ) );
	}

	/**
	 * All active pattern rules for in memory matching, id ordered.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all_patterns(): array {
		$db = $this->connection();

		if ( null === $db ) {
			return [];
		}

		$table = RedirectTable::name();
		$sql   = "SELECT * FROM `{$table}` WHERE is_active = 1 AND match_type != 'exact' ORDER BY id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, bounded pattern list with no user input.
		$rows = $db->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Insert a rule, normalizing the source and hashing before storage.
	 *
	 * @param array<string, mixed> $rule Source, target, code, match_type, is_active.
	 * @return int New row id, or 0 when validation or storage fails.
	 */
	public function insert( array $rule ): int {
		$db = $this->connection();

		if ( null === $db ) {
			return 0;
		}

		$prepared = $this->prepareRow( $rule );

		if ( null === $prepared ) {
			return 0;
		}

		$table = RedirectTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom redirect tables have no core API, typed insert with format list.
		$ok = $db->insert(
			$table,
			$prepared['data'],
			$prepared['format']
		);

		if ( false === $ok ) {
			return 0;
		}

		$this->touch();

		return (int) $db->insert_id;
	}

	/**
	 * Update a rule by id, rehashing when source or matcher changes.
	 *
	 * @param int                  $id   Rule id.
	 * @param array<string, mixed> $rule Partial fields to change.
	 * @return bool True on success.
	 */
	public function update( int $id, array $rule ): bool {
		$db = $this->connection();

		if ( null === $db || $id <= 0 ) {
			return false;
		}

		$prepared = $this->prepareRow( $rule, true );

		if ( null === $prepared || [] === $prepared['data'] ) {
			return false;
		}

		$table = RedirectTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom redirect tables have no core API, typed update against the primary key.
		$ok = $db->update(
			$table,
			$prepared['data'],
			[ 'id' => $id ],
			$prepared['format'],
			[ '%d' ]
		);

		if ( false === $ok ) {
			return false;
		}

		$this->touch();

		return true;
	}

	/**
	 * Delete a rule by id.
	 *
	 * @param int $id Rule id.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		$db = $this->connection();

		if ( null === $db || $id <= 0 ) {
			return false;
		}

		$table = RedirectTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom redirect tables have no core API, delete against the primary key.
		$ok = $db->delete( $table, [ 'id' => $id ], [ '%d' ] );

		if ( false === $ok ) {
			return false;
		}

		$this->touch();

		return true;
	}

	/**
	 * Flip the active flag on one rule.
	 *
	 * @param int  $id     Rule id.
	 * @param bool $active New flag.
	 * @return bool True on success.
	 */
	public function set_active( int $id, bool $active ): bool {
		$db = $this->connection();

		if ( null === $db || $id <= 0 ) {
			return false;
		}

		$table = RedirectTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom redirect tables have no core API, flag flip against the primary key.
		$ok = $db->update( $table, [ 'is_active' => $active ? 1 : 0 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );

		if ( false === $ok ) {
			return false;
		}

		$this->touch();

		return true;
	}

	/**
	 * Bulk activate, deactivate, or delete a list of ids.
	 *
	 * @param string   $action One of activate, deactivate, delete.
	 * @param int[]    $ids    Rule ids.
	 * @return array{updated: int, deleted: int} Affected counts.
	 */
	public function bulk( string $action, array $ids ): array {
		$result = [
			'updated' => 0,
			'deleted' => 0,
		];

		$db = $this->connection();

		if ( null === $db ) {
			return $result;
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
			return $result;
		}

		$table = RedirectTable::name();
		$list  = implode( ',', $clean );

		if ( 'delete' === $action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, id list is cast to integers before interpolation.
			$affected = $db->query( "DELETE FROM `{$table}` WHERE id IN ({$list})" );

			$result['deleted'] = is_int( $affected ) ? $affected : 0;
			$this->touch();

			return $result;
		}

		if ( 'activate' === $action || 'deactivate' === $action ) {
			$flag = 'activate' === $action ? 1 : 0;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, id list is cast to integers before interpolation.
			$affected = $db->query( "UPDATE `{$table}` SET is_active = {$flag} WHERE id IN ({$list})" );

			$result['updated'] = is_int( $affected ) ? $affected : 0;
			$this->touch();

			return $result;
		}

		return $result;
	}

	/**
	 * Paginated rule list with search, filters, sorting, and status counts.
	 *
	 * @param array<string, mixed> $args Search, match_type, code, status, orderby, order, page, per_page.
	 * @return array{rows: array<int, array<string, mixed>>, total: int, pages: int, page: int, per_page: int, active: int, inactive: int}
	 */
	public function paginate( array $args = [] ): array {
		$db = $this->connection();

		$empty = [
			'rows'     => [],
			'total'    => 0,
			'pages'    => 0,
			'page'     => 1,
			'per_page' => 20,
			'active'   => 0,
			'inactive' => 0,
		];

		if ( null === $db ) {
			return $empty;
		}

		$orderby = (string) ( $args['orderby'] ?? 'id' );

		if ( ! in_array( $orderby, self::ORDERABLE, true ) ) {
			$orderby = 'id';
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
		$table    = RedirectTable::name();
		$total    = $this->countFiltered( $filtered );

		$selectArgs = array_merge( [ "SELECT * FROM `{$table}` {$filtered[0]} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d" ], $filtered[1], [ $perPage, $offset ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, order column is whitelisted, values pass through prepare unpacking.
		$rows = $db->get_results( $db->prepare( ...$selectArgs ), ARRAY_A );

		$clean = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					$clean[] = $row;
				}
			}
		}

		$activeFilters             = $args;
		$activeFilters['status']   = 'active';
		$inactiveFilters           = $args;
		$inactiveFilters['status'] = 'inactive';
		$activeCount               = $this->count( $activeFilters );
		$inactiveCount             = $this->count( $inactiveFilters );

		return [
			'rows'     => $clean,
			'total'    => $total,
			'pages'    => 0 === $total ? 0 : (int) ceil( $total / $perPage ),
			'page'     => $page,
			'per_page' => $perPage,
			'active'   => $activeCount,
			'inactive' => $inactiveCount,
		];
	}

	/**
	 * Count rules under the given filters.
	 *
	 * @param array<string, mixed> $filters Search, match_type, code, status.
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
	 * Active internal rules with a concrete target, for save time validation.
	 *
	 * Excludes terminal codes, external targets cannot be proven at the SQL
	 * layer, so callers filter or mark those branches inconclusive.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_cycle_candidates(): array {
		$db = $this->connection();

		if ( null === $db ) {
			return [];
		}

		$table = RedirectTable::name();
		$sql   = "SELECT id, match_type, source, target, code, is_active FROM `{$table}`"
			. " WHERE is_active = 1 AND code IN ('301','302','307') AND target <> '' ORDER BY id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, code list is a fixed whitelist with no user input.
		$rows = $db->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Export rows for CSV, all rows or the given ids, stable id order.
	 *
	 * Hits and last accessed ride along for the export only columns, the
	 * importer ignores them by contract.
	 *
	 * @param int[] $ids Optional row ids, all rows when empty.
	 * @return array<int, array<string, mixed>>
	 */
	public function export_rows( array $ids = [] ): array {
		$db = $this->connection();

		if ( null === $db ) {
			return [];
		}

		$table = RedirectTable::name();
		$clean = [];

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		if ( [] === $clean ) {
			$sql = "SELECT * FROM `{$table}` ORDER BY id ASC";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, unbounded admin export with no user input.
			$rows = $db->get_results( $sql, ARRAY_A );
		} else {
			$list = implode( ',', $clean );
			$sql  = "SELECT * FROM `{$table}` WHERE id IN ({$list}) ORDER BY id ASC";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, id list is cast to integers before interpolation.
			$rows = $db->get_results( $sql, ARRAY_A );
		}

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Active connection or null when the database is unavailable.
	 *
	 * @return \wpdb|null
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
	 * Invalidate the match cache after a successful write.
	 */
	private function touch(): void {
		if ( null !== $this->cache ) {
			$this->cache->invalidate();
		}
	}

	/**
	 * Build the shared WHERE clause plus params for filtered queries.
	 *
	 * @param array<string, mixed> $filters Search, match_type, code, status.
	 * @return array{0: string, 1: list<string>} Clause and params.
	 */
	private function filteredWhere( array $filters ): array {
		$where  = 'WHERE 1=1';
		$params = [];

		$search = trim( (string) ( $filters['search'] ?? '' ) );

		if ( '' !== $search ) {
			$where   .= ' AND (source LIKE %s OR target LIKE %s)';
			$like     = '%' . $this->likeEscape( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$matchType = (string) ( $filters['match_type'] ?? '' );

		if ( Normalizer::isMatchType( $matchType ) ) {
			$where   .= ' AND match_type = %s';
			$params[] = $matchType;
		}

		$code = (string) ( $filters['code'] ?? '' );

		if ( Normalizer::isCode( $code ) ) {
			$where   .= ' AND code = %s';
			$params[] = $code;
		}

		$status = (string) ( $filters['status'] ?? 'all' );

		if ( 'active' === $status ) {
			$where .= ' AND is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where .= ' AND is_active = 0';
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

		$table = RedirectTable::name();
		$args  = array_merge( [ "SELECT COUNT(*) FROM `{$table}` {$filtered[0]}" ], $filtered[1] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, count query with placeholders through prepare unpacking.
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

	/**
	 * Normalize and validate a rule array into storage data plus formats.
	 *
	 * Full rows are required for insert, partial rows are allowed for update.
	 * Returns null when the row cannot be stored (blocked source, missing
	 * destination for a redirect code, unknown fields only).
	 *
	 * @param array<string, mixed> $rule    Raw fields.
	 * @param bool                 $partial Whether missing fields are allowed.
	 * @return array{data: array<string, mixed>, format: array<int, string>}|null
	 */
	private function prepareRow( array $rule, bool $partial = false ): ?array {
		$data   = [];
		$format = [];

		$hasSource = array_key_exists( 'source', $rule );
		$hasType   = array_key_exists( 'match_type', $rule );

		if ( $hasSource || $hasType || ! $partial ) {
			$matchType = Normalizer::isMatchType( (string) ( $rule['match_type'] ?? '' ) )
				? (string) $rule['match_type']
				: 'exact';
			$source    = Normalizer::normalize( (string) ( $rule['source'] ?? '' ) );

			if ( Normalizer::isBlockedSource( $source ) ) {
				return null;
			}

			$data['match_type']  = $matchType;
			$format[]            = '%s';
			$data['source_hash'] = Normalizer::hash( $matchType, $source );
			$format[]            = '%s';
			$data['source']      = $source;
			$format[]            = '%s';
		}

		if ( array_key_exists( 'target', $rule ) || ! $partial ) {
			$target = trim( (string) ( $rule['target'] ?? '' ) );
			$code   = Normalizer::isCode( (string) ( $rule['code'] ?? '' ) )
				? (string) $rule['code']
				: '301';

			if ( '' === $target && ! in_array( $code, Normalizer::TERMINAL_CODES, true ) ) {
				return null;
			}

			$data['target'] = $target;
			$format[]       = '%s';
		}

		if ( array_key_exists( 'code', $rule ) ) {
			$data['code'] = Normalizer::isCode( (string) $rule['code'] ) ? (string) $rule['code'] : '301';
			$format[]     = '%s';
		} elseif ( ! $partial ) {
			$data['code'] = '301';
			$format[]     = '%s';
		}

		if ( array_key_exists( 'is_active', $rule ) ) {
			$data['is_active'] = (int) (bool) $rule['is_active'];
			$format[]          = '%d';
		} elseif ( ! $partial ) {
			$data['is_active'] = 1;
			$format[]          = '%d';
		}

		if ( ! $partial ) {
			$now = function_exists( 'current_time' ) ? (string) current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

			$data['created'] = $now;
			$format[]        = '%s';
			$data['hits']    = 0;
			$format[]        = '%d';
		}

		if ( [] === $data ) {
			return null;
		}

		return [
			'data'   => $data,
			'format' => $format,
		];
	}
}
