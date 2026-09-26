<?php
/**
 * Instant Indexing log query layer, filtering, counting and paging in SQL.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\InstantIndexing;

defined( 'ABSPATH' ) || exit;

use RankKernel\Admin\InstantIndexingOutcomes;

/**
 * One shared read layer over the IndexNow submission log table.
 *
 * Owns every read query for the log: the server rendered admin page, the
 * future REST route and the future retry action all build from fromInput()
 * and receive the same normalized filters, so the two can never drift.
 * The database does the filtering, counting, ordering and paging; the
 * table is never loaded into PHP and rows come back one page at a time.
 *
 * Ordering uses the created index, the status filter uses the code index,
 * and the source filter uses the source index. Every query runs through
 * $wpdb->prepare() with placeholders, the table name comes from
 * LogTable::name(), and the reads fail open when the table is missing.
 */
final class LogQuery {
	/**
	 * Default rows per page.
	 */
	public const PER_PAGE = 20;

	/**
	 * Normalized filters.
	 *
	 * @var LogFilters
	 */
	private LogFilters $filters;

	/**
	 * Cached unfiltered table count.
	 *
	 * @var int|null
	 */
	private ?int $totalCount = null;

	/**
	 * Cached filtered count.
	 *
	 * @var int|null
	 */
	private ?int $filteredCount = null;

	/**
	 * Cached per category counts.
	 *
	 * @var array<string, int>|null
	 */
	private ?array $categoryCounts = null;

	/**
	 * Cached per source counts.
	 *
	 * @var array<string, int>|null
	 */
	private ?array $sourceCounts = null;

	/**
	 * Set up the query for already normalized filters.
	 *
	 * @param LogFilters $filters Normalized filters.
	 */
	public function __construct( LogFilters $filters ) {
		$this->filters = $filters;
	}

	/**
	 * Build the query from raw query arguments.
	 *
	 * Normalization lives in LogFilters, so both consumers share the same
	 * accepted values, fallbacks and page size clamp.
	 *
	 * @param array<string, mixed> $input   Raw query arguments.
	 * @param int                  $perPage Default page size.
	 * @return self The result.
	 */
	public static function fromInput( array $input, int $perPage = self::PER_PAGE ): self {
		return new self( LogFilters::fromInput( $input, $perPage ) );
	}

	/**
	 * Normalized filters behind this query.
	 *
	 * @return LogFilters The result.
	 */
	public function filters(): LogFilters {
		return $this->filters;
	}

	/**
	 * Same filters on another page.
	 *
	 * A page past the end is not clamped here: rows() then returns an
	 * empty set while the totals stay correct, and the view clamps the
	 * displayed page through pageCount().
	 *
	 * @param int $page Page number, clamped to at least one.
	 * @return self The result.
	 */
	public function withPage( int $page ): self {
		return new self( $this->filters->withPage( $page ) );
	}

	/**
	 * Rows for the requested page, newest first.
	 *
	 * Row shape matches the admin view: id, url, host, code, source, time
	 * (the stored UTC string) and message. A page past the end returns
	 * an empty set.
	 *
	 * @return array<int, array{id: int, url: string, host: string, code: int, source: string, time: string, message: string}> The result.
	 */
	public function rows(): array {
		$db = $this->connection();

		if ( null === $db || ! LogTable::exists() ) {
			return [];
		}

		[ $where, $params ] = $this->clause( true, true );

		$table    = LogTable::name();
		$perPage  = $this->filters->perPage();
		$offset   = ( $this->filters->page() - 1 ) * $perPage;
		$sql      = "SELECT id, url, host, code, source, message, created FROM `{$table}` WHERE {$where} ORDER BY created DESC, id DESC LIMIT %d OFFSET %d";
		$withPage = array_merge( [ $sql ], $params, [ $perPage, $offset ] );

		// Custom log table has no core API, paged read with placeholders through prepare.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $db->get_results( $db->prepare( ...$withPage ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$entries = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$entries[] = $this->normalizeRow( $row );
		}

		return $entries;
	}

	/**
	 * One normalized row by primary key, or null when it is absent.
	 *
	 * The retry action addresses a single row, which the paged list cannot
	 * offer, so this is the by id read it builds on. It returns null for a
	 * missing connection, a missing table or a non-positive id, and the id
	 * always travels as a prepared placeholder rather than in the SQL text.
	 *
	 * @param int $id Row id.
	 * @return array{id: int, url: string, host: string, code: int, source: string, time: string, message: string}|null The result.
	 */
	public function getRow( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		$db = $this->connection();

		if ( null === $db || ! LogTable::exists() ) {
			return null;
		}

		$table = LogTable::name();
		$sql   = "SELECT id, url, host, code, source, message, created FROM `{$table}` WHERE id = %d LIMIT 1";
		$with  = array_merge( [ $sql ], [ $id ] );

		// Custom log table has no core API, single row read by id with placeholders through prepare.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $db->get_results( $db->prepare( ...$with ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return null;
		}

		$row = $rows[0] ?? null;

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->normalizeRow( $row );
	}

	/**
	 * Total rows in the table, unfiltered.
	 *
	 * @return int The result.
	 */
	public function total(): int {
		if ( null === $this->totalCount ) {
			$this->totalCount = $this->count( '1 = %d', [ 1 ] );
		}

		return $this->totalCount;
	}

	/**
	 * Rows matching the active search, source and status filters.
	 *
	 * @return int The result.
	 */
	public function filteredTotal(): int {
		if ( null === $this->filteredCount ) {
			[ $where, $params ] = $this->clause( true, true );

			$this->filteredCount = $this->count( $where, $params );
		}

		return $this->filteredCount;
	}

	/**
	 * Number of pages for the filtered rows, at least one.
	 *
	 * @return int The result.
	 */
	public function pageCount(): int {
		return max( 1, (int) ceil( $this->filteredTotal() / $this->filters->perPage() ) );
	}

	/**
	 * Counts per status category under the search and source filters.
	 *
	 * The status filter itself is deliberately ignored, so a tab shows
	 * how many rows it would display if it were selected. The all key is
	 * the total under those filters, and the retry category has a key of
	 * its own but no tab.
	 *
	 * @return array{all: int, accepted: int, pending: int, rejected: int, limited: int, retry: int} The result.
	 */
	public function statusCounts(): array {
		if ( null !== $this->categoryCounts ) {
			return $this->categoryCounts;
		}

		$counts = [
			LogFilters::STATUS_ALL                     => 0,
			InstantIndexingOutcomes::CATEGORY_ACCEPTED => 0,
			InstantIndexingOutcomes::CATEGORY_PENDING  => 0,
			InstantIndexingOutcomes::CATEGORY_REJECTED => 0,
			InstantIndexingOutcomes::CATEGORY_LIMITED  => 0,
			InstantIndexingOutcomes::CATEGORY_RETRY    => 0,
		];

		$groups = $this->groupedCounts( 'code', $this->clause( true, false ) );

		foreach ( $groups as $code => $total ) {
			$counts[ LogFilters::STATUS_ALL ] += $total;

			$category = InstantIndexingOutcomes::categoryFor( (int) $code );

			if ( isset( $counts[ $category ] ) ) {
				$counts[ $category ] += $total;
			}
		}

		$this->categoryCounts = $counts;

		return $counts;
	}

	/**
	 * Counts per source under the search and status filters.
	 *
	 * The source filter itself is ignored here too, matching the tab
	 * behaviour for status, so a source control can show what switching
	 * would display. The page does not use this today; the future REST
	 * route and retry surfaces share it.
	 *
	 * @return array<string, int> The result.
	 */
	public function sourceCounts(): array {
		if ( null !== $this->sourceCounts ) {
			return $this->sourceCounts;
		}

		$this->sourceCounts = $this->groupedCounts( 'source', $this->clause( false, true ) );

		return $this->sourceCounts;
	}

	/**
	 * Count rows for a prepared WHERE clause.
	 *
	 * @param string                 $where  WHERE clause with placeholders.
	 * @param array<int, int|string> $params Placeholder values.
	 * @return int The result.
	 */
	private function count( string $where, array $params ): int {
		$db = $this->connection();

		if ( null === $db || ! LogTable::exists() ) {
			return 0;
		}

		$table = LogTable::name();
		$sql   = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
		$with  = array_merge( [ $sql ], $params );

		// Custom log table has no core API, count query with placeholders through prepare.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$count = $db->get_var( $db->prepare( ...$with ) );

		return (int) $count;
	}

	/**
	 * Grouped counts for one column under a prepared WHERE clause.
	 *
	 * @param string                                      $column Column to group by, a fixed literal.
	 * @param array{0: string, 1: array<int, int|string>} $clause WHERE clause plus values.
	 * @return array<string, int> The result.
	 */
	private function groupedCounts( string $column, array $clause ): array {
		$db = $this->connection();

		if ( null === $db || ! LogTable::exists() ) {
			return [];
		}

		$table = LogTable::name();
		$sql   = "SELECT {$column}, COUNT(*) AS total FROM `{$table}` WHERE {$clause[0]} GROUP BY {$column}";
		$with  = array_merge( [ $sql ], $clause[1] );

		// Custom log table has no core API, grouped count with placeholders through prepare.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $db->get_results( $db->prepare( ...$with ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$counts = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$counts[ (string) ( $row[ $column ] ?? '' ) ] = (int) ( $row['total'] ?? 0 );
		}

		return $counts;
	}

	/**
	 * WHERE clause plus placeholder values for the active filters.
	 *
	 * The baseline 1 = %d keeps every statement prepared even with no
	 * filters. The search matches the concatenation of url and message,
	 * the old PHP behaviour, with the LIKE wildcards escaped so a literal
	 * percent or underscore is matched as a character.
	 *
	 * @param bool $includeSource Whether the source filter applies.
	 * @param bool $includeStatus Whether the status filter applies.
	 * @return array{0: string, 1: array<int, int|string>} The result.
	 */
	private function clause( bool $includeSource, bool $includeStatus ): array {
		$where  = '1 = %d';
		$params = [ 1 ];

		if ( '' !== $this->filters->search() ) {
			$where   .= " AND CONCAT(url, ' ', message) LIKE %s";
			$params[] = '%' . $this->escLike( $this->filters->search() ) . '%';
		}

		if ( $includeSource && LogFilters::SOURCE_ALL !== $this->filters->source() ) {
			$where   .= ' AND source = %s';
			$params[] = $this->filters->source();
		}

		if ( $includeStatus && LogFilters::STATUS_ALL !== $this->filters->status() ) {
			$codes = LogFilters::codesFor( $this->filters->status() );

			if ( [] !== $codes ) {
				$placeholders = implode( ', ', array_fill( 0, count( $codes ), '%d' ) );
				$where       .= " AND code IN ({$placeholders})";

				foreach ( $codes as $code ) {
					$params[] = $code;
				}
			}
		}

		return [ $where, $params ];
	}

	/**
	 * Escape LIKE wildcards in a search needle.
	 *
	 * $wpdb->esc_like() escapes percent, underscore and backslash, so a
	 * search for a literal percent sign stays a literal and never turns
	 * into a wildcard; prepare then quotes the whole LIKE argument.
	 *
	 * @param string $text Raw needle.
	 * @return string The result.
	 */
	private function escLike( string $text ): string {
		$db = $this->connection();

		if ( null !== $db && method_exists( $db, 'esc_like' ) ) {
			return (string) $db->esc_like( $text );
		}

		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Normalize one raw database row to the shared read shape.
	 *
	 * The id is an int so a retry form can carry it, every text column is
	 * a string, and created is exposed as time to match the admin view.
	 *
	 * @param array<string, mixed> $row Raw row from the database.
	 * @return array{id: int, url: string, host: string, code: int, source: string, time: string, message: string} The result.
	 */
	private function normalizeRow( array $row ): array {
		return [
			'id'      => (int) ( $row['id'] ?? 0 ),
			'url'     => (string) ( $row['url'] ?? '' ),
			'host'    => (string) ( $row['host'] ?? '' ),
			'code'    => (int) ( $row['code'] ?? 0 ),
			'source'  => (string) ( $row['source'] ?? '' ),
			'time'    => (string) ( $row['created'] ?? '' ),
			'message' => (string) ( $row['message'] ?? '' ),
		];
	}

	/**
	 * Active database handle, null outside WordPress.
	 *
	 * @return \wpdb|null The result.
	 */
	private function connection() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}

		return $wpdb;
	}
}
