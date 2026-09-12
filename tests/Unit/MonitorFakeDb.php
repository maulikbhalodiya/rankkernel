<?php
/**
 * In memory wpdb double for 404 Monitor backend tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * Minimal behavioral fake for the 404 log table.
 *
 * Interpolates prepared placeholders, then filters an in memory row set by
 * parsing the WHERE, ORDER BY, and LIMIT clauses the repository emits. It
 * enforces the UNIQUE uri_hash constraint so dedupe tests prove real
 * behavior, and it serves bounded DELETE batches oldest first.
 */
final class MonitorFakeDb {
	/**
	 * Table prefix.
	 */
	public string $prefix = 'wp_';

	/**
	 * Whether SHOW TABLES LIKE reports the table.
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
	 */
	public int $reads = 0;

	/**
	 * Write query count.
	 */
	public int $writes = 0;

	/**
	 * Next auto increment id.
	 */
	public int $nextId = 1;

	/**
	 * Last insert id.
	 */
	public int $insert_id = 0;

	/**
	 * Full table name.
	 */
	public function table(): string {
		return $this->prefix . 'rankkernel_404_log';
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
			'id'            => $id,
			'uri_hash'      => hash( 'sha256', '/seed-' . $id ),
			'uri'           => '/seed-' . $id,
			'hits'          => 1,
			'referer'       => '',
			'user_agent'    => '',
			'created'       => '2026-01-01 00:00:00',
			'last_accessed' => '2026-01-01 00:00:00',
		];

		$this->rows[ $id ] = array_merge( $defaults, $row, [ 'id' => $id ] );

		return $id;
	}

	/**
	 * Interpolate placeholders in order.
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Values.
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
	 */
	public function get_var( string $query ): mixed {
		++$this->reads;

		if ( false !== strpos( $query, 'SHOW TABLES LIKE' ) ) {
			return $this->tableExists ? $this->table() : null;
		}

		if ( 0 === strpos( ltrim( $query ), 'SELECT COUNT(*)' ) ) {
			return count( $this->filterRows( $query ) );
		}

		return null;
	}

	/**
	 * Single row fetch.
	 *
	 * @param mixed $output Unused, rows are always arrays.
	 */
	// Test double mirrors the wpdb method signature, so the parameter stays.
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	public function get_row( string $query, mixed $output = 'ARRAY_A' ): ?array {
		++$this->reads;

		$rows = $this->filterRows( $query );

		return $rows[0] ?? null;
	}

	/**
	 * Row list fetch.
	 *
	 * @param mixed $output Unused, rows are always arrays.
	 */
	// Test double mirrors the wpdb method signature, so the parameter stays.
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	public function get_results( string $query, mixed $output = 'ARRAY_A' ): array {
		++$this->reads;

		return $this->filterRows( $query );
	}

	/**
	 * Typed insert with uniqueness enforcement on uri_hash.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @param mixed                $format Unused.
	 */
	// Test double mirrors the wpdb method signature, so the parameter stays.
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	public function insert( string $table, array $data, mixed $format = null ): mixed {
		foreach ( $this->rows as $row ) {
			if ( (string) ( $data['uri_hash'] ?? '' ) === (string) $row['uri_hash'] ) {
				return false;
			}
		}

		++$this->writes;

		$id                = $this->nextId++;
		$this->rows[ $id ] = array_merge( $data, [ 'id' => $id ] );
		$this->insert_id   = $id;

		return 1;
	}

	/**
	 * Typed update against a where map.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @param array<string, mixed> $where Where map.
	 */
	// Test double mirrors the wpdb method signature, so the parameters stay.
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	public function update( string $table, array $data, array $where, mixed $format = null, mixed $whereFormat = null ): mixed {
		++$this->writes;

		$affected = 0;

		foreach ( $this->rows as $id => $row ) {
			if ( $this->whereMatches( $row, $where ) ) {
				$this->rows[ $id ] = array_merge( $row, $data );
				++$affected;
			}
		}

		return $affected;
	}

	/**
	 * Delete against a where map.
	 *
	 * @param array<string, mixed> $where Where map.
	 */
	// Test double mirrors the wpdb method signature, so the parameter stays.
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	public function delete( string $table, array $where, mixed $whereFormat = null ): mixed {
		++$this->writes;

		$deleted = 0;

		foreach ( $this->rows as $id => $row ) {
			if ( $this->whereMatches( $row, $where ) ) {
				unset( $this->rows[ $id ] );
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Raw query, handles bounded DELETE batches and table creation.
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

		$del = [];

		if ( 1 === preg_match( '/WHERE id IN \\(([\\d, ]+)\\)/', $query, $del ) ) {
			$count = 0;

			foreach ( $this->wantedIds( $del[1] ) as $id ) {
				if ( isset( $this->rows[ $id ] ) ) {
					unset( $this->rows[ $id ] );
					++$count;
				}
			}

			return $count;
		}

		$cutoff = [];

		if ( 1 === preg_match( "/last_accessed < '([^']*)'/", $query, $cutoff ) ) {
			$limit = $this->wantedLimit( $query );
			$rows  = $this->oldestFirst();

			$count = 0;

			foreach ( $rows as $row ) {
				if ( $count >= $limit ) {
					break;
				}

				if ( strcmp( (string) $row['last_accessed'], $cutoff[1] ) < 0 ) {
					unset( $this->rows[ (int) $row['id'] ] );
					++$count;
				}
			}

			return $count;
		}

		$limit = $this->wantedLimit( $query );
		$rows  = $this->oldestFirst();

		if ( false !== strpos( $query, 'ORDER BY id ASC' ) ) {
			$rows = array_values( $this->rows );

			usort( $rows, static fn ( array $a, array $b ): int => (int) $a['id'] <=> (int) $b['id'] );
		}

		$count = 0;

		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			unset( $this->rows[ (int) $row['id'] ] );
			++$count;
		}

		return $count;
	}

	/**
	 * LIKE escaping.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Charset collation, empty for the fake.
	 */
	public function get_charset_collate(): string {
		return '';
	}

	/**
	 * Rows sorted oldest first by last_accessed then id.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function oldestFirst(): array {
		$rows = array_values( $this->rows );

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				$cmp = strcmp( (string) $a['last_accessed'], (string) $b['last_accessed'] );

				if ( 0 !== $cmp ) {
					return $cmp;
				}

				return (int) $a['id'] <=> (int) $b['id'];
			}
		);

		return $rows;
	}

	/**
	 * LIMIT value from a DELETE statement, defaults to 500.
	 */
	private function wantedLimit( string $query ): int {
		$limit = [];

		if ( 1 === preg_match( '/LIMIT (\\d+)/', $query, $limit ) ) {
			return max( 1, (int) $limit[1] );
		}

		return 500;
	}

	/**
	 * Whether a row matches a where map.
	 *
	 * @param array<string, mixed> $row Row.
	 * @param array<string, mixed> $where Where map.
	 */
	private function whereMatches( array $row, array $where ): bool {
		foreach ( $where as $col => $val ) {
			if ( (string) ( $row[ $col ] ?? '' ) !== (string) $val ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse an id list.
	 *
	 * @return int[]
	 */
	private function wantedIds( string $idList ): array {
		$ids = [];

		foreach ( explode( ',', $idList ) as $part ) {
			$id = (int) trim( $part );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Filter rows by the clauses the repository emits.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function filterRows( string $sql ): array {
		$rows = array_values( $this->rows );
		$hash = [];

		if ( 1 === preg_match( "/uri_hash = '([0-9a-f]+)'/", $sql, $hash ) ) {
			$want = $hash[1];
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => (string) $r['uri_hash'] === $want ) );
		}

		if ( 1 === preg_match( '/WHERE id = (\d+)/', $sql, $one ) ) {
			$want = (int) $one[1];
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => (int) $r['id'] === $want ) );
		}

		if ( 1 === preg_match( '/id IN \\(([\\d, ]+)\\)/', $sql, $ids ) ) {
			$wanted = $this->wantedIds( $ids[1] );
			$rows   = array_values( array_filter( $rows, static fn ( array $r ): bool => in_array( (int) $r['id'], $wanted, true ) ) );
		}

		$likeMatches = [];

		if ( 0 < preg_match_all( "/LIKE '%([^']*)%'/", $sql, $likeMatches ) ) {
			$needles = $likeMatches[1];
			$rows    = array_values(
				array_filter(
					$rows,
					static function ( array $r ) use ( $needles ): bool {
						foreach ( $needles as $needle ) {
							if ( false !== strpos( (string) $r['uri'], $needle ) ) {
								return true;
							}
						}

						return false;
					}
				)
			);
		}

		$minHits = [];

		if ( 1 === preg_match( '/hits >= (\\d+)/', $sql, $minHits ) ) {
			$want = (int) $minHits[1];
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => (int) $r['hits'] >= $want ) );
		}

		$order = [];

		if ( 1 === preg_match( '/ORDER BY `(\\w+)` (ASC|DESC)/', $sql, $order ) ) {
			$col = $order[1];
			$dir = $order[2];

			usort(
				$rows,
				static function ( array $a, array $b ) use ( $col, $dir ): int {
					$av = $a[ $col ] ?? null;
					$bv = $b[ $col ] ?? null;

					if ( is_numeric( $av ) && is_numeric( $bv ) ) {
						$cmp = (int) $av <=> (int) $bv;
					} else {
						$cmp = strcmp( (string) $av, (string) $bv );
					}

					return 'DESC' === $dir ? -$cmp : $cmp;
				}
			);
		}

		$limit = [];

		if ( 1 === preg_match( '/LIMIT (\\d+)( OFFSET (\\d+))?/', $sql, $limit ) ) {
			$offset = isset( $limit[3] ) ? (int) $limit[3] : 0;
			$rows   = array_slice( $rows, $offset, (int) $limit[1] );
		}

		return $rows;
	}
}
