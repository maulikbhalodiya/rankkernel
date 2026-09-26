<?php
/**
 * Redirect rule repository, CRUD and queries over wp_rankkernel_redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * All database access for redirect rules, prepared statements only.
 *
 * Exact lookups resolve through the UNIQUE (match_type, source_hash) index in
 * one query. Pattern rules load as one bounded cached list for in memory
 * matching, never through serialized LIKE scans and never unbounded: the
 * list is capped at MAX_PATTERNS rows, cached in the RedirectCache group,
 * and retired by the shared validator on every write and toggle. Writes that
 * would grow the active pattern set past the cap are refused with a zero or
 * false return so the admin can report the limit instead of silently
 * exceeding it. Every successful write bumps the shared validator, with or
 * without an attached cache instance, so admin saves always retire
 * frontend caches.
 */
final class RedirectRepository {
	/**
	 * Maximum active non exact rules, the matcher memory and cost bound.
	 *
	 * Five hundred pattern rows cost about one hundred kilobytes and a few
	 * hundred string comparisons per cold miss, with at most twenty regex
	 * evaluations. Ten rules are trivial, one hundred stay cheap, one
	 * thousand start to cost milliseconds per cold miss, five thousand risk
	 * multi megabyte payloads in the object cache, and ten thousand would
	 * turn every cold miss into a full table scan in PHP. The admin reports
	 * this cap whenever a write would exceed it.
	 */
	public const MAX_PATTERNS = 500;

	/**
	 * Rows read per CSV export batch, keeps export memory bounded.
	 */
	public const EXPORT_BATCH = 500;
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
	 *
	 * @var RedirectCache|null
	 */
	private ?RedirectCache $cache = null;

	/**
	 * In-memory memoized pattern rows within the current request execution thread.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $patternsMemo = null;

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
	 * Regex sources hash verbatim through normalizeSource, every other
	 * matcher hashes the normalized path, matching prepareRow exactly.
	 *
	 * @param string $path      Raw or normalized path.
	 * @param string $matchType Matcher name, exact by default.
	 * @return array<string, mixed>|null Rule row or null.
	 */
	public function lookup( string $path, string $matchType = 'exact' ): ?array {
		if ( 'regex' === $matchType ) {
			$normalized = trim( $path );

			if ( '' === $normalized || Normalizer::isBlockedSource( $normalized ) ) {
				return null;
			}

			return $this->find( $matchType, Normalizer::hash( $matchType, $normalized ) );
		}

		$normalized = Normalizer::normalize( $path );

		if ( Normalizer::isBlockedSource( $normalized ) ) {
			return null;
		}

		return $this->find( $matchType, Normalizer::hash( $matchType, $normalized ) );
	}

	/**
	 * Get one rule by id, for the admin edit screen.
	 *
	 * @param int $id Rule id.
	 * @return array<string, mixed>|null Rule row or null.
	 */
	public function get( int $id ): ?array {
		$db = $this->connection();

		if ( null === $db || $id <= 0 ) {
			return null;
		}

		$table = RedirectTable::name();
		$sql   = "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1";

		// Custom redirect tables have no core API, primary key fetch with a placeholder.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $db->get_row( $db->prepare( $sql, $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * All active pattern rules for in memory matching, id ordered.
	 *
	 * Bounded and cached: at most MAX_PATTERNS rows, served from the
	 * RedirectCache pattern slot when fresh, otherwise read with an explicit
	 * LIMIT and stored. A cold miss therefore costs one small indexed read,
	 * never a full table load.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all_patterns(): array {
		if ( null !== $this->patternsMemo ) {
			return $this->patternsMemo;
		}

		if ( null !== $this->cache ) {
			$cached = $this->cache->getPatterns();

			if ( null !== $cached ) {
				$this->patternsMemo = $cached;

				return $cached;
			}
		}

		$db = $this->connection();

		if ( null === $db ) {
			return [];
		}

		$table = RedirectTable::name();
		$sql   = "SELECT * FROM `{$table}` WHERE is_active = 1 AND match_type != 'exact' ORDER BY id ASC LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, bounded pattern list with an integer limit and no user input.
		$rows = $db->get_results( $db->prepare( $sql, self::MAX_PATTERNS ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		if ( null !== $this->cache ) {
			$this->cache->setPatterns( $out );
		}

		// Performance optimization: memoize pattern rows in memory for the duration of the request execution thread.
		$this->patternsMemo = $out;

		return $out;
	}

	/**
	 * Count the active non exact rules against the pattern cap.
	 *
	 * @return int Active pattern rule count.
	 */
	public function count_patterns(): int {
		$db = $this->connection();

		if ( null === $db ) {
			return 0;
		}

		$table = RedirectTable::name();
		$sql   = "SELECT COUNT(*) FROM `{$table}` WHERE is_active = 1 AND match_type != 'exact'";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, single bounded count with no user input.
		$count = $db->get_var( $sql );

		return (int) $count;
	}

	/**
	 * Insert a rule, normalizing the source and hashing before storage.
	 *
	 * An active non exact rule that would grow the pattern set past
	 * MAX_PATTERNS is refused with a zero return, so the caller can report
	 * the cap instead of silently exceeding it.
	 *
	 * @param array<string, mixed> $rule Source, target, code, match_type, is_active.
	 * @return int New row id, or 0 when validation, the pattern cap, or storage fails.
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

		if ( $this->wouldExceedCap( $prepared['data'], null ) ) {
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
	 * A change that would flip a rule into the active pattern set past
	 * MAX_PATTERNS is refused with false, so the caller can report the cap.
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

		if ( $this->wouldExceedCap( $prepared['data'], $id ) ) {
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
	 * Activating a non exact rule past MAX_PATTERNS is refused with false.
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

		if ( $active && $this->wouldExceedCap( [ 'is_active' => 1 ], $id ) ) {
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
	 * Bulk activation respects MAX_PATTERNS: exact rules always flip, non
	 * exact rules flip in id order only while budget remains, and the
	 * returned count reports exactly how many rows changed, so the admin
	 * notice can never claim more than happened.
	 *
	 * @param string $action One of activate, deactivate, delete.
	 * @param int[]  $ids    Rule ids.
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
			if ( 'deactivate' === $action ) {
				$flag = 0;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, id list is cast to integers before interpolation.
				$affected = $db->query( "UPDATE `{$table}` SET is_active = {$flag} WHERE id IN ({$list})" );

				$result['updated'] = is_int( $affected ) ? $affected : 0;
				$this->touch();

				return $result;
			}

			$allowed = $this->capBudgetForBulk( $clean );
			$flag    = 1;

			if ( [] === $allowed ) {
				return $result;
			}

			$allowedList = implode( ',', $allowed );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, id list is cast to integers before interpolation.
			$affected = $db->query( "UPDATE `{$table}` SET is_active = {$flag} WHERE id IN ({$allowedList})" );

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
	 * importer ignores them by contract. Prefer export_count plus
	 * export_batch for large tables, this helper loads the full set and
	 * suits small selections only.
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
	 * Count rows in the export scope, all rows or the given ids.
	 *
	 * @param int[] $ids Optional row ids, all rows when empty.
	 * @return int Row count in scope.
	 */
	public function export_count( array $ids = [] ): int {
		$db = $this->connection();

		if ( null === $db ) {
			return 0;
		}

		$table = RedirectTable::name();
		$clean = $this->cleanIds( $ids );

		if ( [] === $clean ) {
			$sql = "SELECT COUNT(*) FROM `{$table}`";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, single count with no user input.
			$count = $db->get_var( $sql );

			return (int) $count;
		}

		$list = implode( ',', $clean );
		$sql  = "SELECT COUNT(*) FROM `{$table}` WHERE id IN ({$list})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, id list is cast to integers before interpolation.
		$count = $db->get_var( $sql );

		return (int) $count;
	}

	/**
	 * Read one bounded export batch in stable id order.
	 *
	 * @param int[] $ids    Optional row ids, all rows when empty.
	 * @param int   $limit  Rows per batch, capped at EXPORT_BATCH.
	 * @param int   $offset Zero based offset into the id ordered set.
	 * @return array<int, array<string, mixed>>
	 */
	public function export_batch( array $ids = [], int $limit = self::EXPORT_BATCH, int $offset = 0 ): array {
		$db = $this->connection();

		if ( null === $db ) {
			return [];
		}

		$batch = max( 1, min( self::EXPORT_BATCH, $limit ) );
		$skip  = max( 0, $offset );
		$table = RedirectTable::name();
		$clean = $this->cleanIds( $ids );

		if ( [] === $clean ) {
			$sql = "SELECT * FROM `{$table}` ORDER BY id ASC LIMIT %d OFFSET %d";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, bounded batch with integer limit and offset.
			$rows = $db->get_results( $db->prepare( $sql, $batch, $skip ), ARRAY_A );
		} else {
			$list = implode( ',', $clean );
			$sql  = "SELECT * FROM `{$table}` WHERE id IN ({$list}) ORDER BY id ASC LIMIT %d OFFSET %d";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom redirect tables have no core API, id list is cast to integers before interpolation.
			$rows = $db->get_results( $db->prepare( $sql, $batch, $skip ), ARRAY_A );
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
	 * Cast an id list to unique positive integers.
	 *
	 * @param int[] $ids Raw ids.
	 * @return int[] Clean ids.
	 */
	private function cleanIds( array $ids ): array {
		$clean = [];

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}

		return array_values( array_unique( $clean ) );
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
	 * Whether storing the given fields would grow past the pattern cap.
	 *
	 * The check only fires when the resulting row lands inside the active
	 * non exact set. An existing row that already counts toward the cap is
	 * excluded by id, so edits that keep a rule inside the set always pass.
	 *
	 * @param array<string, mixed> $data      Storage fields for the write.
	 * @param int|null             $excludeId Row id already in the set, null on insert.
	 * @return bool True when the write must be refused.
	 */
	private function wouldExceedCap( array $data, ?int $excludeId ): bool {
		$matchType = isset( $data['match_type'] ) ? (string) $data['match_type'] : null;
		$isActive  = isset( $data['is_active'] ) ? 1 === (int) $data['is_active'] : null;

		if ( null === $excludeId ) {
			return 'exact' !== $matchType && true === $isActive && $this->count_patterns() >= self::MAX_PATTERNS;
		}

		$current = $this->get( $excludeId );

		if ( null === $current ) {
			return false;
		}

		$nowCounts = 1 === (int) ( $current['is_active'] ?? 0 ) && 'exact' !== (string) ( $current['match_type'] ?? 'exact' );
		$newType   = null === $matchType ? (string) ( $current['match_type'] ?? 'exact' ) : $matchType;
		$newActive = null === $isActive ? 1 === (int) ( $current['is_active'] ?? 0 ) : $isActive;
		$newCounts = $newActive && 'exact' !== $newType;

		if ( ! $newCounts || $nowCounts ) {
			return false;
		}

		return $this->count_patterns() >= self::MAX_PATTERNS;
	}

	/**
	 * Ids a bulk activate may flip without exceeding the pattern cap.
	 *
	 * Exact rows and rows already active always pass. Inactive non exact
	 * rows pass in id order while budget remains.
	 *
	 * @param int[] $ids Requested ids in any order.
	 * @return int[] Ids allowed to activate.
	 */
	private function capBudgetForBulk( array $ids ): array {
		sort( $ids );

		$budget  = self::MAX_PATTERNS - $this->count_patterns();
		$allowed = [];

		foreach ( $ids as $id ) {
			$row = $this->get( $id );

			if ( null === $row ) {
				continue;
			}

			if ( 1 === (int) ( $row['is_active'] ?? 0 ) || 'exact' === (string) ( $row['match_type'] ?? 'exact' ) ) {
				$allowed[] = $id;

				continue;
			}

			if ( $budget > 0 ) {
				$allowed[] = $id;
				--$budget;
			}
		}

		return $allowed;
	}

	/**
	 * Invalidate the match cache after a successful write.
	 *
	 * Always bumps the shared validator, so writes through a repository
	 * without an attached cache instance still retire every cached match
	 * and pattern list. The attached instance additionally clears its in
	 * memory maps.
	 */
	private function touch(): void {
		$this->patternsMemo = null;

		if ( null !== $this->cache ) {
			$this->cache->invalidate();

			return;
		}

		RedirectCache::invalidateAll();
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
			$source    = Normalizer::normalizeSource( (string) ( $rule['source'] ?? '' ), $matchType );

			if ( '' === $source || Normalizer::isBlockedSource( $source ) ) {
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
