<?php
/**
 * In memory wpdb double for the Instant Indexing log tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use RankKernel\Modules\InstantIndexing\LogTable;

/**
 * Minimal behavioral fake for the IndexNow log table.
 *
 * Interpolates prepared placeholders, then filters an in memory row set by
 * parsing the WHERE, ORDER BY, GROUP BY, LIMIT and OFFSET clauses the query
 * layer emits. The LIKE interpreter treats percent and underscore as real
 * wildcards and honours backslash escapes, so a missing esc_like() call in
 * the code under test changes the result instead of passing silently. It
 * mirrors the inserts, filtered reads, grouped counts and full clears the
 * storage methods issue, records every prepared template so tests can prove
 * values never reach the SQL, and resets the table existence cache on
 * construction so every test starts from a clean static state.
 */
final class InstantIndexingFakeDb {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Whether SHOW TABLES LIKE reports the table.
	 *
	 * @var bool
	 */
	public bool $tableExists = true;

	/**
	 * Rows keyed by id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows = [];

	/**
	 * Read query count.
	 *
	 * @var int
	 */
	public int $reads = 0;

	/**
	 * Write query count.
	 *
	 * @var int
	 */
	public int $writes = 0;

	/**
	 * Next auto increment id.
	 *
	 * @var int
	 */
	public int $nextId = 1;

	/**
	 * Last insert id.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Schema probe count, SHOW TABLES only.
	 *
	 * @var int
	 */
	public int $schemaProbes = 0;

	/**
	 * Every prepared template, placeholders still in place.
	 *
	 * @var string[]
	 */
	public array $prepared = [];

	/**
	 * Every interpolated statement after prepare, values quoted.
	 *
	 * @var string[]
	 */
	public array $interpolated = [];

	/**
	 * Reads whose statement was not the last prepared string.
	 *
	 * @var int
	 */
	public int $unpreparedReads = 0;

	/**
	 * Result of the most recent prepare call.
	 *
	 * @var string
	 */
	private string $lastPrepared = '';

	/**
	 * Reset the log table existence cache so each test starts clean.
	 */
	public function __construct() {
		LogTable::resetCache();
	}

	/**
	 * Full table name.
	 *
	 * @return string The result.
	 */
	public function table(): string {
		return $this->prefix . 'rankkernel_indexnow_log';
	}

	/**
	 * Seed one row directly, bypassing query counting.
	 *
	 * @param array<string, mixed> $row Partial row, defaults fill the rest.
	 * @return int Assigned id.
	 */
	public function seed( array $row ): int {
		$id = $this->nextId++;

		$defaults = [
			'id'      => $id,
			'url'     => 'https://example.com/seed-' . $id,
			'host'    => 'example.com',
			'code'    => 200,
			'source'  => 'manual',
			'message' => 'Accepted.',
			'created' => '2026-01-01 00:00:00',
		];

		$this->rows[ $id ] = array_merge( $defaults, $row, [ 'id' => $id ] );

		return $id;
	}

	/**
	 * Interpolate placeholders in one forward pass.
	 *
	 * The pass never rescans inserted values, so a percent or an escaped
	 * quote inside a value cannot be mistaken for the next placeholder.
	 *
	 * @param string $query   Query with placeholders.
	 * @param mixed  ...$args Values.
	 * @return string The result.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared[] = $query;

		$values = array_values( $args );
		$index  = 0;
		$length = strlen( $query );
		$result = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $query[ $i ];

			if (
				'%' === $char
				&& $i + 1 < $length
				&& $index < count( $values )
				&& ( 's' === $query[ $i + 1 ] || 'd' === $query[ $i + 1 ] )
			) {
				$value = $values[ $index ];

				$result .= 's' === $query[ $i + 1 ]
					? "'" . addslashes( (string) $value ) . "'"
					: (string) (int) $value;

				++$index;
				++$i;

				continue;
			}

			$result .= $char;
		}

		$this->lastPrepared   = $result;
		$this->interpolated[] = $result;

		return $result;
	}

	/**
	 * Single value fetch.
	 *
	 * @param string $query Query.
	 * @return mixed The result.
	 */
	public function get_var( string $query ): mixed {
		++$this->reads;
		$this->noteRead( $query );

		if ( false !== strpos( $query, 'SHOW TABLES LIKE' ) ) {
			++$this->schemaProbes;

			return $this->tableExists ? $this->table() : null;
		}

		if ( 0 === strpos( ltrim( $query ), 'SELECT COUNT(*)' ) ) {
			return count( $this->filterRows( $query ) );
		}

		return null;
	}

	// Test double mirrors the wpdb method signature, so the parameter stays.
	/**
	 * Row list fetch, filtered, grouped when grouped, paged when limited.
	 *
	 * @param string $query  Query.
	 * @param mixed  $output Unused, rows are always arrays.
	 * @return array<int, array<string, mixed>> The result.
	 */
	public function get_results( string $query, mixed $output = 'ARRAY_A' ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb method signature.
		++$this->reads;
		$this->noteRead( $query );

		$grouped = [];

		if ( 1 === preg_match( '/SELECT (\w+), COUNT\(\*\) AS total FROM/', $query, $grouped ) ) {
			return $this->groupedCounts( $grouped[1], $this->filterRows( $query ) );
		}

		$rows   = $this->filterRows( $query );
		$limit  = null;
		$offset = 0;

		if ( 1 === preg_match( '/LIMIT (\d+) OFFSET (\d+)/', $query, $clauses ) ) {
			$limit  = (int) $clauses[1];
			$offset = (int) $clauses[2];
		}

		if ( null !== $limit ) {
			$rows = array_slice( $rows, $offset, $limit );
		}

		return $rows;
	}

	// Test double mirrors the wpdb method signature, so the parameter stays.
	/**
	 * Typed insert, assigns the next auto increment id.
	 *
	 * @param string               $table  Table.
	 * @param array<string, mixed> $data   Row data.
	 * @param mixed                $format Unused.
	 * @return mixed The result.
	 */
	public function insert( string $table, array $data, mixed $format = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb method signature.
		++$this->writes;

		$id                = $this->nextId++;
		$this->rows[ $id ] = array_merge( $data, [ 'id' => $id ] );
		$this->insert_id   = $id;

		return 1;
	}

	/**
	 * Raw query, handles table creation and the full clear.
	 *
	 * @param string $query Query.
	 * @return mixed The result.
	 */
	public function query( string $query ): mixed {
		++$this->writes;

		if ( 0 === strpos( $query, 'CREATE TABLE' ) ) {
			$this->tableExists = true;

			return 1;
		}

		if ( 0 !== strpos( ltrim( $query ), 'DELETE FROM' ) ) {
			return 0;
		}

		$count      = count( $this->rows );
		$this->rows = [];

		return $count;
	}

	/**
	 * LIKE escaping.
	 *
	 * @param string $text Text.
	 * @return string The result.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Charset collation, empty for the fake.
	 *
	 * @return string The result.
	 */
	public function get_charset_collate(): string {
		return '';
	}

	/**
	 * Record a read that did not come from the last prepare call.
	 *
	 * @param string $query Query handed to a read method.
	 * @return void
	 */
	private function noteRead( string $query ): void {
		if ( $query !== $this->lastPrepared ) {
			++$this->unpreparedReads;
		}
	}

	/**
	 * Rows sorted newest first by created then id, the read order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function newestFirst(): array {
		$rows = array_values( $this->rows );

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				$cmp = strcmp( (string) $b['created'], (string) $a['created'] );

				if ( 0 !== $cmp ) {
					return $cmp;
				}

				return (int) $b['id'] <=> (int) $a['id'];
			}
		);

		return $rows;
	}

	/**
	 * Filter rows by the WHERE clauses the query layer emits.
	 *
	 * @param string $sql Sql.
	 * @return array<int, array<string, mixed>>
	 */
	private function filterRows( string $sql ): array {
		$rows = $this->newestFirst();
		$like = [];

		if ( 0 < preg_match_all( "/CONCAT\\(url, ' ', message\\) LIKE '((?:\\\\.|[^'])*)'/", $sql, $like ) ) {
			foreach ( $like[1] as $pattern ) {
				$regex = $this->likeRegex( stripslashes( $pattern ) );

				$rows = array_values(
					array_filter(
						$rows,
						static function ( array $row ) use ( $regex ): bool {
							return 1 === preg_match( $regex, (string) $row['url'] . ' ' . (string) $row['message'] );
						}
					)
				);
			}
		}

		$source = [];

		if ( 1 === preg_match( "/source = '([^']*)'/", $sql, $source ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( array $row ) use ( $source ): bool {
						return (string) $row['source'] === $source[1];
					}
				)
			);
		}

		$codes = [];

		if ( 1 === preg_match( '/code IN \(([\d, ]*)\)/', $sql, $codes ) ) {
			$wanted = [];

			foreach ( explode( ',', $codes[1] ) as $part ) {
				if ( '' !== trim( $part ) ) {
					$wanted[] = (int) $part;
				}
			}

			$rows = array_values(
				array_filter(
					$rows,
					static function ( array $row ) use ( $wanted ): bool {
						return in_array( (int) $row['code'], $wanted, true );
					}
				)
			);
		}

		return $rows;
	}

	/**
	 * Group already filtered rows by one column.
	 *
	 * @param string                           $column Column name.
	 * @param array<int, array<string, mixed>> $rows   Filtered rows.
	 * @return array<int, array<string, mixed>> The result.
	 */
	private function groupedCounts( string $column, array $rows ): array {
		$counts = [];

		foreach ( $rows as $row ) {
			$key = (string) ( $row[ $column ] ?? '' );

			$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
		}

		$result = [];

		foreach ( $counts as $value => $total ) {
			$result[] = [
				$column => $value,
				'total' => $total,
			];
		}

		return $result;
	}

	/**
	 * Turn a MySQL LIKE pattern into an anchored, case insensitive regex.
	 *
	 * Percent matches any run, underscore matches one character, and a
	 * backslash escapes the next character, so an escaped wildcard is
	 * matched literally exactly as MySQL would.
	 *
	 * @param string $pattern Like pattern.
	 * @return string The result.
	 */
	private function likeRegex( string $pattern ): string {
		$regex  = '';
		$length = strlen( $pattern );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $pattern[ $i ];

			if ( '\\' === $char && $i + 1 < $length ) {
				++$i;
				$regex .= preg_quote( $pattern[ $i ], '/' );

				continue;
			}

			if ( '%' === $char ) {
				$regex .= '.*';

				continue;
			}

			if ( '_' === $char ) {
				$regex .= '.';

				continue;
			}

			$regex .= preg_quote( $char, '/' );
		}

		return '/^' . $regex . '$/i';
	}
}
