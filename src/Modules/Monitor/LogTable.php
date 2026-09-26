<?php
/**
 * 404 log table definition and idempotent creation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the wp_rankkernel_404_log table.
 *
 * Tables are created through ensureTables on the module enable path, never
 * through the MigrationRunner ledger, because the ledger cannot re run for a
 * module enabled later. Every creation path is idempotent and the hot path
 * fails open when the table is missing.
 */
final class LogTable {
	/**
	 * Table suffix, prefixed with the site prefix at runtime.
	 */
	public const SUFFIX = 'rankkernel_404_log';

	/**
	 * Request-level cache of table existence, keyed by resolved table name.
	 *
	 * Keying by the resolved table name keeps a switch_to_blog() or a prefix
	 * change from reusing another site's result, while still skipping repeat
	 * queries for the same table within one request.
	 *
	 * @var array<string, bool>
	 */
	private static array $existsCache = array();

	/**
	 * Reset the static existence cache (primarily for unit tests).
	 */
	public static function resetCache(): void {
		$table = self::name();

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'table_exists_' . $table, 'rankkernel_tables' );
		}

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'rankkernel_tbl_exists_' . md5( $table ) );
		}

		self::$existsCache = array();
	}

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

		if ( array_key_exists( $table, self::$existsCache ) ) {
			return self::$existsCache[ $table ];
		}

		// Check WP Object Cache first to prevent database queries on every request when persistent object cache is enabled.
		if ( function_exists( 'wp_cache_get' ) ) {
			$foundInCache = false;
			$cached       = wp_cache_get( 'table_exists_' . $table, 'rankkernel_tables', false, $foundInCache );

			if ( $foundInCache && is_bool( $cached ) ) {
				self::$existsCache[ $table ] = $cached;

				return $cached;
			}
		}

		// Performance optimization: check transient fallback to prevent running SHOW TABLES SQL query on every request when persistent object cache is disabled.
		$transientKey = 'rankkernel_tbl_exists_' . md5( $table );
		if ( function_exists( 'get_transient' ) ) {
			$cachedTransient = get_transient( $transientKey );

			if ( '1' === $cachedTransient || '0' === $cachedTransient ) {
				$exists                      = '1' === $cachedTransient;
				self::$existsCache[ $table ] = $exists;

				if ( function_exists( 'wp_cache_set' ) ) {
					wp_cache_set( 'table_exists_' . $table, $exists, 'rankkernel_tables', DAY_IN_SECONDS );
				}

				return $exists;
			}
		}

		// Custom table existence probe, single prepared SHOW statement, fail open guard.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$exists                      = is_string( $found ) && $found === $table;
		self::$existsCache[ $table ] = $exists;

		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( 'table_exists_' . $table, $exists, 'rankkernel_tables', DAY_IN_SECONDS );
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( $transientKey, $exists ? '1' : '0', DAY_IN_SECONDS );
		}

		return $exists;
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

		self::resetCache();

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
			. 'uri_hash CHAR(64) NOT NULL,'
			. 'uri TEXT NOT NULL,'
			. 'hits BIGINT UNSIGNED NOT NULL DEFAULT 0,'
			. "referer VARCHAR(255) NOT NULL DEFAULT '',"
			. "user_agent VARCHAR(255) NOT NULL DEFAULT '',"
			. 'created DATETIME NOT NULL,'
			. 'last_accessed DATETIME NOT NULL,'
			. 'PRIMARY KEY (id),'
			. 'UNIQUE KEY uri_hash (uri_hash),'
			. 'KEY last_accessed (last_accessed)'
			/**
			 * Charset.
			 *
			 * @var `('idBIGINTUNSIGNEDNOTNULLAUTO_INCREMENT,''uri_hashCHAR(64)NOTNULL,''uriTEXTNOTNULL,''hitsBIGINTUNSIGNEDNOTNULLDEFAULT0,'"refererVARCHAR(255)NOTNULLDEFAULT'',""user_agentVARCHAR(255)NOTNULLDEFAULT'',"'createdDATETIMENOTNULL,''last_accessedDATETIMENOTNULL,''PRIMARYKEY(id),''UNIQUEKEYuri_hash(uri_hash),''KEYlast_accessed(last_accessed)'){
			 */
			/**
			 * Charset.
			 *
			 * @var `('idBIGINTUNSIGNEDNOTNULLAUTO_INCREMENT,''uri_hashCHAR(64)NOTNULL,''uriTEXTNOTNULL,''hitsBIGINTUNSIGNEDNOTNULLDEFAULT0,'"refererVARCHAR(255)NOTNULLDEFAULT'',""user_agentVARCHAR(255)NOTNULLDEFAULT'',"'createdDATETIMENOTNULL,''last_accessedDATETIMENOTNULL,''PRIMARYKEY(id),''UNIQUEKEYuri_hash(uri_hash),''KEYlast_accessed(last_accessed)'){
			 */
			. ") {$charset};";

		if ( function_exists( 'dbDelta' ) ) {
			// Module table creation via dbDelta, idempotent by design.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			dbDelta( $sql );
		} else {
			// Fallback when the upgrade library is unavailable, same idempotent statement with no user input.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql );
		}

		self::resetCache();

		return self::exists();
	}
}
