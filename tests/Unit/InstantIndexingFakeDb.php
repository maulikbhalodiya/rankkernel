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
 * parsing the ORDER BY, LIMIT and OFFSET clauses the settings class emits.
 * It mirrors the inserts, newest first reads and full clears the storage
 * methods issue, and it resets the table existence cache on construction so
 * every test starts from a clean static state.
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
	 * Interpolate placeholders in order.
	 *
	 * @param string $query   Query with placeholders.
	 * @param mixed  ...$args Values.
	 * @return string The result.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		foreach ( $args as $arg ) {
			$posS = strpos( $query, '%s' );
			$posD = strpos( $query, '%d' );

			if ( false === $posS && false === $posD ) {
				break;
			}

			if ( false !== $posS && ( false === $posD || $posS < $posD ) ) {
				$query = substr_replace( $query, "'" . addslashes( (string) $arg ) . "'", $posS, 2 );
			} else {
				$query = substr_replace( $query, (string) (int) $arg, (int) $posD, 2 );
			}
		}

		return $query;
	}

	/**
	 * Single value fetch.
	 *
	 * @param string $query Query.
	 * @return mixed The result.
	 */
	public function get_var( string $query ): mixed {
		++$this->reads;

		if ( false !== strpos( $query, 'SHOW TABLES LIKE' ) ) {
			++$this->schemaProbes;

			return $this->tableExists ? $this->table() : null;
		}

		if ( 0 === strpos( ltrim( $query ), 'SELECT COUNT(*)' ) ) {
			return count( $this->rows );
		}

		return null;
	}

	// Test double mirrors the wpdb method signature, so the parameter stays.
	/**
	 * Row list fetch, newest first with LIMIT and OFFSET.
	 *
	 * @param string $query  Query.
	 * @param mixed  $output Unused, rows are always arrays.
	 * @return array<int, array<string, mixed>> The result.
	 */
	public function get_results( string $query, mixed $output = 'ARRAY_A' ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb method signature.
		++$this->reads;

		$limit  = null;
		$offset = 0;

		if ( 1 === preg_match( '/LIMIT (\d+) OFFSET (\d+)/', $query, $clauses ) ) {
			$limit  = (int) $clauses[1];
			$offset = (int) $clauses[2];
		}

		return array_slice( $this->newestFirst(), $offset, $limit );
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
}
