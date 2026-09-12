<?php
/**
 * In memory wpdb double for Redirects backend tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * Minimal behavioral fake for the redirect table.
 *
 * Interpolates prepared placeholders, then filters an in memory row set by
 * parsing the WHERE, ORDER BY, and LIMIT clauses the repository emits. It
 * counts reads and writes separately so dispatch tests can prove query
 * budgets, and it enforces the UNIQUE matcher plus hash constraint.
 */
final class RedirectsFakeDb {
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
	 * Rule table query count, schema probes excluded.
	 */
	public int $ruleReads = 0;

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
		return $this->prefix . 'rankkernel_redirects';
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
		$this->noteRuleRead( $query );

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
	 * @param mixed $output Ignored, rows are always arrays.
	 */
	public function get_row( string $query, mixed $output = 'ARRAY_A' ): ?array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors the $wpdb method signature.
		++$this->reads;
		$this->noteRuleRead( $query );

		$rows = $this->filterRows( $query );

		return $rows[0] ?? null;
	}

	/**
	 * Row list fetch.
	 *
	 * @param mixed $output Ignored, rows are always arrays.
	 */
	public function get_results( string $query, mixed $output = 'ARRAY_A' ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors the $wpdb method signature.
		++$this->reads;
		$this->noteRuleRead( $query );

		return $this->filterRows( $query );
	}

	/**
	 * Typed insert with uniqueness enforcement.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @param mixed                $format Ignored.
	 */
	public function insert( string $table, array $data, mixed $format = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors the $wpdb method signature.
		foreach ( $this->rows as $row ) {
			if ( (string) ( $data['match_type'] ?? '' ) === (string) $row['match_type']
				&& (string) ( $data['source_hash'] ?? '' ) === (string) $row['source_hash'] ) {
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
	public function update( string $table, array $data, array $where, mixed $format = null, mixed $whereFormat = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors the $wpdb method signature.
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
	public function delete( string $table, array $where, mixed $whereFormat = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors the $wpdb method signature.
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
	 * Raw query, handles counter UPDATEs, bulk writes, and table creation.
	 */
	public function query( string $query ): mixed {
		++$this->writes;
		$this->noteRuleRead( $query );

		$hit = [];

		if ( 1 === preg_match( "/SET hits = hits \\+ (\\d+), last_accessed = '([^']*)' WHERE id = (\\d+)/", $query, $hit ) ) {
			$id = (int) $hit[3];

			if ( isset( $this->rows[ $id ] ) ) {
				$this->rows[ $id ]['hits']          = (int) $this->rows[ $id ]['hits'] + (int) $hit[1];
				$this->rows[ $id ]['last_accessed'] = $hit[2];
			}

			return 1;
		}

		$del = [];

		if ( 1 === preg_match( '/DELETE FROM .* WHERE id IN \\(([\\d, ]+)\\)/', $query, $del ) ) {
			$count = 0;

			foreach ( $this->wantedIds( $del[1] ) as $id ) {
				if ( isset( $this->rows[ $id ] ) ) {
					unset( $this->rows[ $id ] );
					++$count;
				}
			}

			return $count;
		}

		$upd = [];

		if ( 1 === preg_match( '/UPDATE .* SET is_active = ([01]) WHERE id IN \\(([\\d, ]+)\\)/', $query, $upd ) ) {
			$count = 0;

			foreach ( $this->wantedIds( $upd[2] ) as $id ) {
				if ( isset( $this->rows[ $id ] ) ) {
					$this->rows[ $id ]['is_active'] = (int) $upd[1];
					++$count;
				}
			}

			return $count;
		}

		if ( 0 === strpos( $query, 'CREATE TABLE' ) ) {
			$this->tableExists = true;

			return 1;
		}

		return 0;
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
	 * Count rule table queries, schema probes excluded.
	 */
	private function noteRuleRead( string $query ): void {
		if ( 0 === strpos( ltrim( $query ), 'SHOW' ) ) {
			return;
		}

		if ( false === strpos( $query, 'rankkernel_redirects' ) ) {
			return;
		}

		++$this->ruleReads;
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
		$rows  = array_values( $this->rows );
		$match = [];

		if ( 1 === preg_match( "/match_type = '([a-z]+)'/", $sql, $match ) ) {
			$want = $match[1];
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => (string) $r['match_type'] === $want ) );
		}

		if ( false !== strpos( $sql, "match_type != 'exact'" ) ) {
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => 'exact' !== (string) $r['match_type'] ) );
		}

		$hash = [];

		if ( 1 === preg_match( "/source_hash = '([0-9a-f]+)'/", $sql, $hash ) ) {
			$want = $hash[1];
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => (string) $r['source_hash'] === $want ) );
		}

		$active = [];

		if ( 1 === preg_match( '/is_active = ([01])/', $sql, $active ) ) {
			$want = (int) $active[1];
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => (int) $r['is_active'] === $want ) );
		}

		$code = [];

		if ( 1 === preg_match( "/code = '(\\d+)'/", $sql, $code ) ) {
			$want = $code[1];
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => (string) $r['code'] === $want ) );
		}

		$inCodes = [];

		if ( 1 === preg_match( '/code IN \\(([^)]+)\\)/', $sql, $inCodes ) ) {
			$found = [];

			preg_match_all( "/'(\\d+)'/", $inCodes[1], $found );

			$allowed = $found[1];
			$rows    = array_values( array_filter( $rows, static fn ( array $r ): bool => in_array( (string) $r['code'], $allowed, true ) ) );
		}

		if ( false !== strpos( $sql, "target <> ''" ) ) {
			$rows = array_values( array_filter( $rows, static fn ( array $r ): bool => '' !== (string) $r['target'] ) );
		}

		$ids = [];

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
							if ( false !== strpos( (string) $r['source'], $needle ) || false !== strpos( (string) $r['target'], $needle ) ) {
								return true;
							}
						}

						return false;
					}
				)
			);
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
		} elseif ( false !== strpos( $sql, 'ORDER BY id ASC' ) ) {
			usort( $rows, static fn ( array $a, array $b ): int => (int) $a['id'] <=> (int) $b['id'] );
		}

		$limit = [];

		if ( 1 === preg_match( '/LIMIT (\\d+)( OFFSET (\\d+))?/', $sql, $limit ) ) {
			$offset = isset( $limit[3] ) ? (int) $limit[3] : 0;
			$rows   = array_slice( $rows, $offset, (int) $limit[1] );
		}

		return $rows;
	}
}
