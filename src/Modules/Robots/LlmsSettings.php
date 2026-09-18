<?php
/**
 * Llms.txt settings store.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the rankkernel_llms_settings option.
 *
 * Holds the virtual llms.txt toggle, summary, included post types and
 * taxonomies, caps, physical write toggle and exclusions. Autoload off.
 */
final class LlmsSettings {
	/**
	 * Option name.
	 */
	public const OPTION = 'rankkernel_llms_settings';

	/**
	 * Minimum and maximum item cap per section.
	 */
	private const MIN_LIMIT = 1;

	/**
	 * Maximum item cap per section.
	 */
	private const MAX_LIMIT = 500;

	/**
	 * Minimum excerpt length.
	 */
	private const MIN_EXCERPT = 20;

	/**
	 * Maximum excerpt length.
	 */
	private const MAX_EXCERPT = 400;

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
			'enabled'        => false,
			'summary'        => '',
			'post_types'     => [ 'post', 'page' ],
			'taxonomies'     => [],
			'limit'          => 100,
			'excerpt_length' => 160,
			'physical'       => false,
			'exclude_ids'    => [],
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
			case 'enabled':
			case 'physical':
				return (bool) $value;

			case 'summary':
				return trim( is_string( $value ) ? $value : '' );

			case 'post_types':
			case 'taxonomies':
				return $this->sanitizeSlugList( $value );

			case 'limit':
				return $this->clamp( $value, self::MIN_LIMIT, self::MAX_LIMIT, 100 );

			case 'excerpt_length':
				return $this->clamp( $value, self::MIN_EXCERPT, self::MAX_EXCERPT, 160 );

			case 'exclude_ids':
				return $this->sanitizeIdList( $value );
		}

		return null;
	}

	/**
	 * Clamp an integer into a range.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Minimum.
	 * @param int   $max      Maximum.
	 * @param int   $fallback Fallback when not numeric.
	 * @return int The result.
	 */
	private function clamp( mixed $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Filter a raw list to unique non empty slugs.
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	private function sanitizeSlugList( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$slugs = [];

		foreach ( $value as $item ) {
			$slug = sanitize_key( (string) $item );

			if ( '' !== $slug && ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Filter an id list to unique positive ints.
	 *
	 * @param mixed $value Raw list.
	 * @return int[]
	 */
	private function sanitizeIdList( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$ids = [];

		foreach ( $value as $item ) {
			$id = absint( $item );

			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}
