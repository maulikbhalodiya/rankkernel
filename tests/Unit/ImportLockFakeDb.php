<?php
/**
 * Fake database handle for the import lock tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * Import Lock Fake Db.
 *
 * Holds one options table in memory and answers the four statements the
 * lock uses: an insert that ignores an existing row, a select, a
 * compare and swap update and a compare and delete.
 */
final class ImportLockFakeDb {
	/**
	 * Options table name.
	 *
	 * @var string
	 */
	public string $options = 'wp_options';

	/**
	 * Stored rows, option name to option value.
	 *
	 * @var array<string, string>
	 */
	private array $rows = [];

	/**
	 * Prepare a statement.
	 *
	 * @param string $query Query.
	 * @param mixed  ...$args Values.
	 * @return string The prepared query.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		if ( [] === $args ) {
			return $query;
		}

		$out = $query;

		foreach ( $args as $arg ) {
			$pos = strpos( $out, '%s' );

			if ( false === $pos ) {
				break;
			}

			$value = is_scalar( $arg ) ? (string) $arg : '';

			$out = substr_replace( $out, "'" . str_replace( "'", "\\'", $value ) . "'", $pos, 2 );
		}

		return $out;
	}

	/**
	 * Run a statement.
	 *
	 * @param string $query Query.
	 * @return int|false Affected rows.
	 */
	public function query( string $query ): int|false {
		$sql = stripslashes( $query );

		if ( preg_match( "/^INSERT IGNORE INTO \w+ \(option_name, option_value, autoload\) VALUES \('([^']*)', '([^']*)', 'no'\)$/", $sql, $m ) ) {
			if ( isset( $this->rows[ $m[1] ] ) ) {
				return 0;
			}

			$this->rows[ $m[1] ] = $m[2];

			return 1;
		}

		if ( preg_match( "/^UPDATE \w+ SET option_value = '([^']*)' WHERE option_name = '([^']*)' AND option_value = '([^']*)'$/", $sql, $m ) ) {
			if ( ! isset( $this->rows[ $m[2] ] ) || $this->rows[ $m[2] ] !== $m[3] ) {
				return 0;
			}

			$this->rows[ $m[2] ] = $m[1];

			return 1;
		}

		if ( preg_match( "/^DELETE FROM \w+ WHERE option_name = '([^']*)' AND option_value = '([^']*)'$/", $sql, $m ) ) {
			if ( ! isset( $this->rows[ $m[1] ] ) || $this->rows[ $m[1] ] !== $m[2] ) {
				return 0;
			}

			unset( $this->rows[ $m[1] ] );

			return 1;
		}

		return false;
	}

	/**
	 * Read one value.
	 *
	 * @param string|null $query Query.
	 * @return mixed The value or null.
	 */
	public function get_var( ?string $query = null ): mixed {
		$sql = stripslashes( (string) $query );

		if ( preg_match( "/^SELECT option_value FROM \w+ WHERE option_name = '([^']*)' LIMIT 1$/", $sql, $m ) ) {
			return $this->rows[ $m[1] ] ?? null;
		}

		return null;
	}

	/**
	 * The stored rows, for assertions.
	 *
	 * @return array<string, string> The rows.
	 */
	public function rows(): array {
		return $this->rows;
	}

	/**
	 * Store a raw row, for expiry tests.
	 *
	 * @param string $name  Option name.
	 * @param string $value Option value.
	 * @return void
	 */
	public function seed( string $name, string $value ): void {
		$this->rows[ $name ] = $value;
	}
}
