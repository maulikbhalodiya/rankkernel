<?php
/**
 * Redirects module settings, own option with autoload disabled.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the rankkernel_redirects_settings option.
 *
 * Follows the SettingsStore pattern (whitelisted keys, sanitized writes) but
 * keeps a separate option with autoload disabled, so redirect settings never
 * bloat the autoloaded options row.
 */
final class RedirectsSettings {
	/**
	 * Option name.
	 */
	public const OPTION = 'rankkernel_redirects_settings';

	/**
	 * Whitelisted setting keys.
	 *
	 * @var string[]
	 */
	private const ALLOWED_KEYS = [
		'preserve_query',
		'auto_slug_redirect',
		'rules_per_page',
		'schema_ok',
	];

	/**
	 * Cached merged settings, defaults plus stored values.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Request-level static cache for merged redirect settings across instances, keyed by blog ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $cachedSettings = [];

	/**
	 * Reset the static request-level settings cache (primarily for unit tests or blog switches).
	 *
	 * @return void
	 */
	public static function resetCache(): void {
		self::$cachedSettings = [];
	}

	/**
	 * Get the current blog ID for multisite-safe static caching.
	 *
	 * @return int Blog ID or 0 when unavailable.
	 */
	private static function currentBlogId(): int {
		if ( function_exists( 'get_current_blog_id' ) ) {
			return (int) get_current_blog_id();
		}

		return 1;
	}

	/**
	 * Default settings.
	 *
	 * Query preservation defaults to on, the slug watcher defaults to on, the
	 * admin page size defaults to 20 rows, the schema flag starts off until
	 * the module enable path confirms the table.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'preserve_query'     => true,
			'auto_slug_redirect' => true,
			'rules_per_page'     => 20,
			'schema_ok'          => false,
		];
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback when the key is missing.
	 * @return mixed Setting value or the fallback.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $fallback;
	}

	/**
	 * Get all merged settings.
	 *
	 * Performance optimization: memoizes merged settings in static request-level memory
	 * across all RedirectsSettings instances to eliminate redundant get_option() calls for
	 * non-autoloaded redirect settings during a request.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$blogId = self::currentBlogId();

		if ( isset( self::$cachedSettings[ $blogId ] ) ) {
			$this->cache = self::$cachedSettings[ $blogId ];

			return $this->cache;
		}

		$stored = get_option( self::OPTION, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$merged                          = array_merge( self::defaults(), $stored );
		$this->cache                     = $merged;
		self::$cachedSettings[ $blogId ] = $merged;

		return $this->cache;
	}

	/**
	 * Seed the option with defaults when it is missing.
	 */
	public function ensureSchema(): void {
		$blogId   = self::currentBlogId();
		$stored   = get_option( self::OPTION, null );
		$defaults = self::defaults();

		if ( ! is_array( $stored ) ) {
			update_option( self::OPTION, $defaults, false );
			$this->cache                     = $defaults;
			self::$cachedSettings[ $blogId ] = $defaults;

			return;
		}

		$merged                          = array_merge( $defaults, $stored );
		$this->cache                     = $merged;
		self::$cachedSettings[ $blogId ] = $merged;
	}

	/**
	 * Update settings with a partial array, whitelisted keys only.
	 *
	 * @param array<string, mixed> $partial Partial settings to merge.
	 * @return bool Whether anything was written.
	 */
	public function set( array $partial ): bool {
		$sanitized = [];

		foreach ( $partial as $key => $value ) {
			if ( ! in_array( $key, self::ALLOWED_KEYS, true ) ) {
				continue;
			}

			$sanitized[ $key ] = $this->sanitize( $key, $value );
		}

		if ( [] === $sanitized ) {
			return false;
		}

		$blogId      = self::currentBlogId();
		$this->cache = self::$cachedSettings[ $blogId ] ?? null;
		$merged      = array_merge( $this->all(), $sanitized );

		update_option( self::OPTION, $merged, false );

		$this->cache                     = $merged;
		self::$cachedSettings[ $blogId ] = $merged;

		return true;
	}

	/**
	 * Sanitize a single value by key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize( string $key, mixed $value ): mixed {
		if ( 'rules_per_page' === $key ) {
			$perPage = (int) $value;

			if ( $perPage < 1 ) {
				return 1;
			}

			if ( $perPage > 100 ) {
				return 100;
			}

			return $perPage;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		$normalized = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		if ( null !== $normalized ) {
			return $normalized;
		}

		return (bool) $value;
	}
}
