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

if ( ! $purge ) {
	return;
}

global $wpdb;

// Purge all rankkernel_* options.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge of plugin-owned data via $wpdb->prepare, one-shot delete needs no caching.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'rankkernel_' ) . '%'
	)
);

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

// Drop any wp_rankkernel_* tables if they exist (none yet in v1, but the path ships now).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'rankkernel_' ) . '%' ) );
if ( is_array( $tables ) ) {
	foreach ( $tables as $table ) {
		// Retire the cached existence flag with the table it describes. That flag
		// is held for a day when a persistent object cache is present, and a stale
		// true would make ensureTables() believe the table still exists after a
		// reinstall, so it would never recreate the one dropped here.
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'table_exists_' . $table, 'rankkernel_tables' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}
}

// Note: Multisite purge is single-site scope in v1 (ledger ruling §O3).
// A future multisite loop would iterate over get_sites() and switch_to_blog().
