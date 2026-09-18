<?php
/**
 * Crawl Signals settings store.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the rankkernel_robots_settings option.
 *
 * Holds the robots.txt mode and custom block, the enabled AI crawler
 * presets and an optional sitemap URL override. The custom block can be
 * large, so autoload is disabled.
 */
final class RobotsSettings {
	/**
	 * Option name.
	 */
	public const OPTION = 'rankkernel_robots_settings';

	/**
	 * Accepted modes.
	 *
	 * @var string[]
	 */
	private const MODES = [ 'default', 'custom' ];

	/**
	 * Cached merged settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'mode'        => 'default',
			'custom'      => '',
			'presets'     => [],
			'sitemap_url' => '',
		];
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback if not set.
	 * @return mixed The result.
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
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_option( self::OPTION, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$this->cache = array_merge( self::defaults(), $stored );

		return $this->cache;
	}

	/**
	 * Update settings with a partial array (whitelisted keys only).
	 *
	 * @param array<string, mixed> $partial Partial settings to merge.
	 * @return bool Whether anything was saved.
	 */
	public function set( array $partial ): bool {
		$allowed   = array_keys( self::defaults() );
		$sanitized = [];

		foreach ( $partial as $key => $value ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowed, true ) ) {
				continue;
			}

			$sanitized[ $key ] = $this->sanitize( $key, $value );
		}

		if ( [] === $sanitized ) {
			return false;
		}

		$merged = array_merge( $this->all(), $sanitized );

		update_option( self::OPTION, $merged, false );

		$this->cache = $merged;

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
		switch ( $key ) {
			case 'mode':
				$mode = is_string( $value ) ? strtolower( $value ) : '';

				return in_array( $mode, self::MODES, true ) ? $mode : 'default';

			case 'custom':
				return RobotsDirectives::normalize( is_string( $value ) ? $value : '' );

			case 'presets':
				return RobotsDirectives::sanitizePresets( $value );

			case 'sitemap_url':
				return RobotsDirectives::sanitizeSitemapUrl( is_string( $value ) ? $value : '' );
		}

		return null;
	}
}
