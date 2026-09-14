<?php
/**
 * Redirect table definition and idempotent creation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * Owns the wp_rankkernel_redirects table.
 *
 * Tables are created through ensureTables on the module enable path, never
 * through the MigrationRunner ledger, because the ledger cannot re run for a
 * module enabled later. Every creation path is idempotent and the hot path
 * fails open when the table is missing.
 */
final class RedirectTable {
	/**
	 * Table suffix, prefixed with the site prefix at runtime.
	 */
	public const SUFFIX = 'rankkernel_redirects';

	/**
	 * Full table name for the current site.
	 *
	 * @return string Prefixed table name.
	 */
	public static function name(): string {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return 'wp_' . self::SUFFIX;
		}

		return (string) $wpdb->prefix . self::SUFFIX;
	}

	/**
	 * Cheap existence check used to fail open on the hot path.
	 *
	 * @return bool True when the table exists.
	 */
	public static function exists(): bool {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}

		$table = self::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom table existence probe, single prepared SHOW statement, fail open guard.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return is_string( $found ) && $found === $table;
	}

	/**
	 * Create the table when missing, safe to call on every enable.
	 *
	 * @return bool True when the table exists after the call.
	 */
	public static function ensureTables(): bool {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}

		if ( self::exists() ) {
			return true;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';

			if ( '' !== $upgrade && file_exists( $upgrade ) ) {
				require_once $upgrade;
			}
		}

		$table   = self::name();
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? (string) $wpdb->get_charset_collate() : '';
		$sql     = "CREATE TABLE IF NOT EXISTS `{$table}` ("
			. 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
			. "match_type ENUM('exact','prefix','contains','suffix','wildcard','regex') NOT NULL DEFAULT 'exact',"
			. 'source_hash CHAR(64) NOT NULL,'
			. 'source TEXT NOT NULL,'
			. 'target TEXT NOT NULL,'
			. "code ENUM('301','302','307','410','451') NOT NULL DEFAULT '301',"
			. 'hits BIGINT UNSIGNED NOT NULL DEFAULT 0,'
			. 'is_active TINYINT(1) NOT NULL DEFAULT 1,'
			. 'created DATETIME NOT NULL,'
			. 'last_accessed DATETIME NULL DEFAULT NULL,'
			. 'PRIMARY KEY (id),'
			. 'UNIQUE KEY match_source (match_type, source_hash),'
			. 'KEY is_active (is_active)'
			. ") {$charset};";

		if ( function_exists( 'dbDelta' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- module table creation via dbDelta, idempotent by design.
			dbDelta( $sql );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- fallback when the upgrade library is unavailable, same idempotent statement with no user input.
			$wpdb->query( $sql );
		}

		return self::exists();
	}
}
