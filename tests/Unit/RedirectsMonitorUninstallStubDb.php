<?php
/**
 * Minimal wpdb stand in for the uninstall run.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * Simulates the options table and the table list with plain arrays while
 * recording every query, so the uninstall test proves the purge removes
 * owned rows and tables and keeps everything else.
 */
final class RedirectsMonitorUninstallStubDb {
	/**
	 * Site table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Options table name.
	 *
	 * @var string
	 */
	public string $options = 'wp_options';

	/**
	 * Post meta table name.
	 *
	 * @var string
	 */
	public string $postmeta = 'wp_postmeta';

	/**
	 * Term meta table name.
	 *
	 * @var string
	 */
	public string $termmeta = 'wp_termmeta';

	/**
	 * User meta table name.
	 *
	 * @var string
	 */
	public string $usermeta = 'wp_usermeta';

	/**
	 * Option rows keyed by option name.
	 *
	 * @var array<string, mixed>
	 */
	public array $optionRows = [];

	/**
	 * Table names present on the site.
	 *
	 * @var string[]
	 */
	public array $tables = [];

	/**
	 * Recorded queries in run order.
	 *
	 * @var string[]
	 */
	public array $queries = [];

	/**
	 * Escape a value for a LIKE comparison.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Fill one placeholder per argument, string quoting included.
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Replacement values.
	 * @return string Prepared query.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		foreach ( $args as $arg ) {
			$query = (string) preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $query, 1 );
		}

		return $query;
	}

	/**
	 * Run a query against the simulated tables.
	 *
	 * @param string $query Prepared query.
	 * @return mixed Affected count.
	 */
	public function query( string $query ): mixed {
		$this->queries[] = $query;

		if ( str_contains( $query, 'DELETE FROM' ) && str_contains( $query, 'option_name LIKE' ) ) {
			foreach ( array_keys( $this->optionRows ) as $name ) {
				if (
					str_starts_with( $name, 'rankkernel_' ) ||
					str_starts_with( $name, '_transient_rankkernel_' ) ||
					str_starts_with( $name, '_transient_timeout_rankkernel_' ) ||
					str_starts_with( $name, '_transient_rk404_flood_' ) ||
					str_starts_with( $name, '_transient_timeout_rk404_flood_' ) ||
					str_starts_with( $name, '_transient_rkredir_' ) ||
					str_starts_with( $name, '_transient_timeout_rkredir_' )
				) {
					unset( $this->optionRows[ $name ] );
				}
			}

			return 1;
		}

		if ( str_starts_with( $query, 'DROP TABLE IF EXISTS' ) ) {
			if ( 1 === preg_match( '/`([^`]+)`/', $query, $matches ) ) {
				$this->tables = array_values( array_diff( $this->tables, [ $matches[1] ] ) );
			}

			return 1;
		}

		return 1;
	}

	/**
	 * List tables under the owned prefix.
	 *
	 * @param string $query Prepared SHOW TABLES query.
	 * @return string[] Owned table names.
	 */
	public function get_col( string $query ): array {
		$this->queries[] = $query;

		$out = [];

		foreach ( $this->tables as $table ) {
			if ( str_starts_with( $table, $this->prefix . 'rankkernel_' ) ) {
				$out[] = $table;
			}
		}

		return $out;
	}
}
