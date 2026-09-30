<?php
/**
 * Uninstall handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rankkernel_settings = get_option( 'rankkernel_settings', array() );

$purge = false;
if ( is_array( $rankkernel_settings ) && isset( $rankkernel_settings['purge_on_uninstall'] ) ) {
	$purge = (bool) $rankkernel_settings['purge_on_uninstall'];
}

global $wpdb;

// An uninstall must never leave personal data behind, so the plugin's own
// custom tables and every module owned transient are always removed, whatever
// the purge_on_uninstall setting says. That setting only governs the broader
// option, post meta, term meta and user meta purge below, whose contents are
// configuration rather than captured visitor data. The 404 log table holds
// request URIs, referers and user agents, so it can never survive a default
// uninstall.

// Purge all transients and transient timeouts owned by RankKernel modules.
$transient_prefixes = array( 'rankkernel_', 'rkredir_', 'rk404_flood_' );
foreach ( $transient_prefixes as $transient_prefix ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge of plugin-owned data via $wpdb->prepare, one-shot delete needs no caching.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' . $transient_prefix ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . $transient_prefix ) . '%'
		)
	);
}

// Drop every wp_rankkernel_* table, the 404 log included, on every uninstall path.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'rankkernel_' ) . '%' ) );
if ( is_array( $tables ) ) {
	foreach ( $tables as $table ) {
		// Names only ever arrive from the wp_rankkernel_ prefix sweep, still
		// validate the identifier before it lands in a DROP statement, so an
		// unexpected character can never smuggle SQL through the table name.
		if ( ! is_string( $table ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			continue;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

		// Retire the cached existence flag after the drop, never before it. That flag
		// is held for a day when a persistent object cache is present, and a stale
		// true would make ensureTables() believe the table still exists after a
		// reinstall, so it would never recreate the one dropped here. Dropping first
		// also clears any value a concurrent request cached while the table was
		// still present.
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'table_exists_' . $table, 'rankkernel_tables' );
		}

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'rankkernel_tbl_exists_' . md5( $table ) );
		}
	}
}

if ( ! $purge ) {
	return;
}

// Full purge, only when the owner opted in. The setting keeps its meaning for
// configuration data, stored options, post meta, term meta and user meta.
// Purge all rankkernel_* options.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge of plugin-owned data via $wpdb->prepare, one-shot delete needs no caching.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'rankkernel_' ) . '%'
	)
);

// Purge all _rankkernel_* post meta (direct $wpdb for scale, meta API would be slow).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge of plugin-owned data via $wpdb->prepare, one-shot delete needs no caching.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_rankkernel_' ) . '%'
	)
);

// Purge all _rankkernel_* term meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge of plugin-owned data via $wpdb->prepare, one-shot delete needs no caching.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_rankkernel_' ) . '%'
	)
);

// Purge all _rankkernel_* user meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge of plugin-owned data via $wpdb->prepare, one-shot delete needs no caching.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_rankkernel_' ) . '%'
	)
);

// Purge the unprefixed user meta key. UserProfileField::META_KEY carries no
// leading underscore, so the LIKE sweep above never matches it and an opted-in
// purge would otherwise leave the stored X handle behind. Exact match on the
// single known key, so no wildcard can overreach into unrelated rows.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge of plugin-owned data via $wpdb->prepare, one-shot delete needs no caching.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
		'rankkernel_twitter_handle'
	)
);

// Note: Multisite purge is single-site scope in v1 (ledger ruling §O3).
// A future multisite loop would iterate over get_sites() and switch_to_blog().
