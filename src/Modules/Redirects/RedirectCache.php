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
 * cheaply, and every rule write plus the module toggle bumps it. The active
 * pattern list used by the matcher rides the same validator under its own
 * key, so one invalidation clears single matches and the pattern set
 * together.
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
	 * Store key for the cached active pattern list.
	 */
	private const PATTERNS_KEY = 'patterns_v1';

	/**
	 * In memory map for the current request.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $memory = [];

	/**
	 * In memory pattern list for the current request, null when not loaded.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $patternsMemory = null;

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
	 *
	 * Clears single matches and the pattern list together, so a write can
	 * never leave a stale pattern set behind a fresh single match.
	 */
	public function invalidate(): void {
		$this->memory         = [];
		$this->patternsMemory = null;

		self::invalidateAll();
	}

	/**
	 * Bump the global validator, synchronously visible to all requests.
	 *
	 * The validator covers single matches and the pattern list alike, so a
	 * toggle without a repository instance still retires both.
	 */
	public static function invalidateAll(): void {
		update_option( self::VALIDATOR_OPTION, (string) time() . '_' . uniqid( '', true ), false );
	}

	/**
	 * Fetch the cached active pattern list, null on any miss or staleness.
	 *
	 * The payload carries the same validator as single matches, so every
	 * write and toggle retires it through the one bump.
	 *
	 * @return array<int, array<string, mixed>>|null Pattern rows or null.
	 */
	public function getPatterns(): ?array {
		if ( null !== $this->patternsMemory ) {
			return $this->patternsMemory;
		}

		$payload = $this->readStore( self::PATTERNS_KEY );

		if ( ! is_array( $payload ) || ! isset( $payload['rules'] ) || ! is_array( $payload['rules'] ) ) {
			return null;
		}

		$current = (string) get_option( self::VALIDATOR_OPTION, '' );
		$stored  = isset( $payload['validator'] ) ? (string) $payload['validator'] : '';

		if ( $stored !== $current ) {
			return null;
		}

		$rules = [];

		foreach ( $payload['rules'] as $rule ) {
			if ( is_array( $rule ) ) {
				$rules[] = $rule;
			}
		}

		$this->patternsMemory = $rules;

		return $rules;
	}

	/**
	 * Cache the active pattern list for later cold misses.
	 *
	 * @param array<int, array<string, mixed>> $rules Bounded pattern rows.
	 */
	public function setPatterns( array $rules ): void {
		$clean = [];

		foreach ( $rules as $rule ) {
			if ( is_array( $rule ) ) {
				$clean[] = $rule;
			}
		}

		$this->patternsMemory = $clean;

		$payload = [
			'rules'     => $clean,
			'validator' => (string) get_option( self::VALIDATOR_OPTION, '' ),
		];

		$this->writeStore( self::PATTERNS_KEY, $payload );
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
