<?php
/**
 * Redirect match cache, positive hits only.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * Object cache plus transient fallback for resolved redirect matches.
 *
 * Own group rankkernel-redirects, separate from the sitemap group, so a
 * redirect write never flushes sitemaps. Positive caching only: resolved
 * rules are cached, misses are never cached, which avoids the flood write
 * pattern of caching every unknown URL. A stored validator confirms freshness
 * cheaply, and every rule write plus the module toggle bumps it.
 */
final class RedirectCache {
	/**
	 * Object cache group, never shared with sitemaps.
	 */
	public const GROUP = 'rankkernel-redirects';

	/**
	 * Validator option name, bumped on every invalidation.
	 */
	public const VALIDATOR_OPTION = 'rankkernel_redirects_validator';

	/**
	 * Bounded time to live in seconds, twelve hours.
	 */
	public const TTL = 43200;

	/**
	 * Transient prefix for the no object cache fallback.
	 */
	private const TRANSIENT_PREFIX = 'rkredir_';

	/**
	 * In memory map for the current request.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $memory = [];

	/**
	 * Cache key for a normalized request path.
	 *
	 * @param string $normalizedPath Canonical path.
	 * @return string Stable cache key.
	 */
	public static function cacheKey( string $normalizedPath ): string {
		return 'rule_' . hash( 'sha256', $normalizedPath );
	}

	/**
	 * Fetch a cached rule for a normalized path.
	 *
	 * @param string $normalizedPath Canonical path.
	 * @return array<string, mixed>|null Rule row, or null on any miss.
	 */
	public function get( string $normalizedPath ): ?array {
		$key = self::cacheKey( $normalizedPath );

		if ( array_key_exists( $key, $this->memory ) ) {
			return $this->memory[ $key ];
		}

		$payload = $this->readStore( $key );

		if ( ! is_array( $payload ) || ! isset( $payload['rule'] ) || ! is_array( $payload['rule'] ) ) {
			return null;
		}

		$current = (string) get_option( self::VALIDATOR_OPTION, '' );
		$stored  = isset( $payload['validator'] ) ? (string) $payload['validator'] : '';

		if ( $stored !== $current ) {
			return null;
		}

		$this->memory[ $key ] = $payload['rule'];

		return $payload['rule'];
	}

	/**
	 * Cache a resolved rule for a normalized path.
	 *
	 * @param string               $normalizedPath Canonical path.
	 * @param array<string, mixed> $rule           Winning rule row.
	 */
	public function set( string $normalizedPath, array $rule ): void {
		$key = self::cacheKey( $normalizedPath );

		$this->memory[ $key ] = $rule;

		$payload = [
			'rule'      => $rule,
			'validator' => (string) get_option( self::VALIDATOR_OPTION, '' ),
		];

		$this->writeStore( $key, $payload );
	}

	/**
	 * Invalidate every cached match, called on each rule write and toggle.
	 */
	public function invalidate(): void {
		$this->memory = [];

		self::invalidateAll();
	}

	/**
	 * Bump the global validator, synchronously visible to all requests.
	 */
	public static function invalidateAll(): void {
		update_option( self::VALIDATOR_OPTION, (string) time() . '_' . uniqid( '', true ), false );
	}

	/**
	 * Read a payload, object cache first, transient fallback otherwise.
	 *
	 * @param string $key Final cache key.
	 * @return mixed Stored payload or null.
	 */
	private function readStore( string $key ): mixed {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$found = false;
			$value = wp_cache_get( $key, self::GROUP, false, $found );

			if ( $found ) {
				return $value;
			}

			return null;
		}

		$value = get_transient( self::TRANSIENT_PREFIX . $key );

		return false === $value ? null : $value;
	}

	/**
	 * Write a payload, object cache first, transient fallback otherwise.
	 *
	 * @param string $key     Final cache key.
	 * @param mixed  $payload Payload to store.
	 */
	private function writeStore( string $key, mixed $payload ): void {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $payload, self::GROUP, self::TTL );

			return;
		}

		set_transient( self::TRANSIENT_PREFIX . $key, $payload, self::TTL );
	}
}
