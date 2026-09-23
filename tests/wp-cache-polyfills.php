<?php
/**
 * Object cache polyfills for the test suite.
 *
 * Kept in a file of its own because Patchwork can only intercept functions
 * defined in a file it was able to instrument, and the bootstrap starts running
 * before Patchwork loads. A real function here also means a Brain Monkey stub
 * has something to restore when the test ends, which is what stops a later test
 * from calling an inactive mock through the function_exists guard the table
 * classes use.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! isset( $GLOBALS['rankkernel_test_object_cache'] ) ) {
	$GLOBALS['rankkernel_test_object_cache'] = array();
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	/**
	 * Read a value from the in-memory test store.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @param bool   $force Accepted for signature parity, a test store never discards.
	 * @param bool   $found Set to whether the key was present.
	 * @return mixed Stored value, or false on a miss.
	 */
	function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
		unset( $force );

		$store = $GLOBALS['rankkernel_test_object_cache'];
		$found = ( '' !== (string) $group && isset( $store[ $group ] ) && array_key_exists( $key, $store[ $group ] ) );

		return $found ? $store[ $group ][ $key ] : false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	/**
	 * Store a value in the in-memory test store.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param string $group Cache group.
	 * @param int    $ttl   Accepted for signature parity, a test store never expires.
	 * @return bool True.
	 */
	function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
		unset( $ttl );

		$GLOBALS['rankkernel_test_object_cache'][ $group ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	/**
	 * Remove a value from the in-memory test store.
	 *
	 * Mirrors core, which returns false when the group holds no such key.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True when a value was removed, false when there was nothing to remove.
	 */
	function wp_cache_delete( $key, $group = '' ) {
		if ( ! isset( $GLOBALS['rankkernel_test_object_cache'][ $group ] ) || ! array_key_exists( $key, $GLOBALS['rankkernel_test_object_cache'][ $group ] ) ) {
			return false;
		}

		unset( $GLOBALS['rankkernel_test_object_cache'][ $group ][ $key ] );

		return true;
	}
}

if ( ! function_exists( '_prime_post_caches' ) ) {
	/**
	 * Accept a post cache priming request.
	 *
	 * WordPress owns this function in a real install. The providers guard
	 * the call with function_exists, so a real definition here keeps the
	 * call reachable and gives a Brain Monkey stub something to restore at
	 * tear down instead of leaving an inactive mock behind.
	 *
	 * @param int[] $ids               Post ids.
	 * @param bool  $update_term_cache Whether to update the term cache.
	 * @param bool  $update_meta_cache Whether to update the meta cache.
	 * @return void
	 */
	function _prime_post_caches( $ids, $update_term_cache = true, $update_meta_cache = true ) {
		unset( $ids, $update_term_cache, $update_meta_cache );
	}
}

if ( ! function_exists( '_prime_term_caches' ) ) {
	/**
	 * Accept a term cache priming request.
	 *
	 * WordPress owns this function in a real install. The providers guard
	 * the call with function_exists, so a real definition here keeps the
	 * call reachable and gives a Brain Monkey stub something to restore at
	 * tear down instead of leaving an inactive mock behind.
	 *
	 * @param int[] $term_ids          Term ids.
	 * @param bool  $update_meta_cache Whether to update the term meta cache.
	 * @return void
	 */
	function _prime_term_caches( $term_ids, $update_meta_cache = true ) {
		unset( $term_ids, $update_meta_cache );
	}
}
