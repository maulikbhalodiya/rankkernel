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
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'rankkernel_' ) . '%'
	)
);

// Purge all _rankkernel_* post meta (direct $wpdb for scale, meta API would be slow).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_rankkernel_' ) . '%'
	)
);

// Purge all _rankkernel_* term meta.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_rankkernel_' ) . '%'
	)
);

// Purge all _rankkernel_* user meta.
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}
}

// Note: Multisite purge is single-site scope in v1 (ledger ruling §O3).
// A future multisite loop would iterate over get_sites() and switch_to_blog().
