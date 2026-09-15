<?php
/**
 * Breadcrumbs module settings, own option with autoload enabled.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs;

/**
 * Manages the rankkernel_breadcrumbs_settings option.
 *
 * Small array, read on every frontend request that builds a trail, so it
 * is registered with autoload YES, following the SitemapSettings shape
 * (defaults, get, all, set, whitelist, sanitize). Per post type primary
 * taxonomy mappings live inside the same option as dynamic
 * primary_taxonomy_{post_type} keys gated by a key pattern.
 */
final class BreadcrumbsSettings {
	/**
	 * Option name.
	 */
	public const OPTION = 'rankkernel_breadcrumbs_settings';

	/**
	 * Separator preset choices shown as native radio inputs.
	 *
	 * @var string[]
	 */
	public const SEPARATOR_PRESETS = [ '/', '›', '»', '*', '|', '•' ];

	/**
	 * Maximum stored separator length in characters.
	 */
	public const SEPARATOR_MAX_LENGTH = 10;

	/**
	 * Fixed setting keys (dynamic primary taxonomy keys match by pattern).
	 *
	 * @var string[]
	 */
	private const FIXED_KEYS = [
		'separator',
		'home_label',
		'show_home',
		'show_current',
		'hide_on_front_page',
		'show_blog_page',
		'show_ancestors',
	];

	/**
	 * Cached merged settings (defaults plus stored).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Separator preset choices for the admin chooser.
	 *
	 * @return string[]
	 */
	public static function separatorPresets(): array {
		return self::SEPARATOR_PRESETS;
	}

	/**
	 * Whether a value is one of the separator presets.
	 *
	 * @param string $value Candidate separator.
	 * @return bool The result.
	 */
	public static function isSeparatorPreset( string $value ): bool {
		return in_array( $value, self::SEPARATOR_PRESETS, true );
	}

	/**
	 * Get default settings.
	 *
	 * Dynamic primary_taxonomy_{post_type} keys default to an empty string
	 * (fall back to the first public taxonomy with terms), so they are
	 * not pre seeded here.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'separator'          => '/',
			'home_label'         => 'Home',
			'show_home'          => true,
			'show_current'       => true,
			'hide_on_front_page' => true,
			'show_blog_page'     => true,
			'show_ancestors'     => true,
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
	 * Seed the option with defaults when it is missing.
	 */
	public function ensureSchema(): void {
		$stored = get_option( self::OPTION, null );

		if ( ! is_array( $stored ) ) {
			update_option( self::OPTION, self::defaults(), true );
			$this->cache = self::defaults();

			return;
		}

		$this->cache = array_merge( self::defaults(), $stored );
	}

	/**
	 * Update settings with a partial array (whitelisted keys only).
	 *
	 * Unknown keys are dropped. The partial is merged over the stored
	 * values, so tabbed admin saves only touch their own keys.
	 *
	 * @param array<string, mixed> $partial Partial settings to merge.
	 * @return bool Whether anything was saved.
	 */
	public function set( array $partial ): bool {
		$sanitized = [];

		foreach ( $partial as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( ! $this->isAllowed( $key ) ) {
				continue;
			}

			$sanitized[ $key ] = $this->sanitize( $key, $value );
		}

		if ( [] === $sanitized ) {
			return false;
		}

		$merged = array_merge( $this->all(), $sanitized );

		update_option( self::OPTION, $merged, true );

		$this->cache = $merged;

		return true;
	}

	/**
	 * Whether a key may be stored.
	 *
	 * @param string $key Setting key.
	 * @return bool The result.
	 */
	private function isAllowed( string $key ): bool {
		if ( in_array( $key, self::FIXED_KEYS, true ) ) {
			return true;
		}

		return $this->isDynamicKey( $key );
	}

	/**
	 * Whether a key is a dynamic per post type primary taxonomy mapping.
	 *
	 * @param string $key Setting key.
	 * @return bool The result.
	 */
	private function isDynamicKey( string $key ): bool {
		return 1 === preg_match( '/^primary_taxonomy_[a-z0-9_]+$/', $key );
	}

	/**
	 * Sanitize a single value by key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize( string $key, mixed $value ): mixed {
		if ( 'separator' === $key ) {
			return $this->sanitizeSeparator( $value );
		}

		if ( 'home_label' === $key ) {
			return sanitize_text_field( (string) $value );
		}

		if ( $this->isDynamicKey( $key ) ) {
			return $this->sanitizeTaxonomy( $key, $value );
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

	/**
	 * Sanitize the canonical separator as plain text.
	 *
	 * Tags are stripped, the value is capped at SEPARATOR_MAX_LENGTH
	 * characters, and an empty result falls back to a slash so the
	 * renderer never receives an unusable separator.
	 *
	 * @param mixed $value Raw value.
	 * @return string Sanitized separator.
	 */
	private function sanitizeSeparator( mixed $value ): string {
		$clean = sanitize_text_field( (string) $value );

		if ( function_exists( 'mb_substr' ) ) {
			$clean = mb_substr( $clean, 0, self::SEPARATOR_MAX_LENGTH );
		} else {
			$clean = substr( $clean, 0, self::SEPARATOR_MAX_LENGTH );
		}

		if ( '' === trim( $clean ) ) {
			return '/';
		}

		return $clean;
	}

	/**
	 * Effective per post type configuration for the trail builder.
	 *
	 * Applied after the stored settings are loaded and before
	 * TrailBuilder consumes the configuration, so developers can adjust
	 * the primary taxonomy through the
	 * rankkernel/breadcrumbs/post_type_settings filter.
	 *
	 * Filter contract: receives the resolved configuration map with
	 * post_type and primary_taxonomy keys, plus the post type slug as
	 * the second argument, and must return the same shape. The returned
	 * taxonomy is validated again against the public taxonomies
	 * registered for that post type and must exist in the registry. An
	 * invalid or malicious value is ignored and the stored value
	 * applies, falling back to an empty string (first public taxonomy
	 * with terms) when the stored value is unusable.
	 *
	 * @param string $postType Post type slug.
	 * @return array{post_type: string, primary_taxonomy: string} Resolved configuration.
	 */
	public function postTypeSettings( string $postType ): array {
		$stored = (string) $this->get( 'primary_taxonomy_' . $postType, '' );

		$resolved = [
			'post_type'        => $postType,
			'primary_taxonomy' => $stored,
		];

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rankkernel/breadcrumbs/post_type_settings', $resolved, $postType ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

			if ( is_array( $filtered ) && isset( $filtered['primary_taxonomy'] ) && is_string( $filtered['primary_taxonomy'] ) && $filtered['primary_taxonomy'] !== $stored ) {
				$candidate = sanitize_key( $filtered['primary_taxonomy'] );

				if ( '' === $candidate ) {
					$resolved['primary_taxonomy'] = '';
				} elseif ( $this->isValidTaxonomyForType( $candidate, $postType ) ) {
					$resolved['primary_taxonomy'] = $candidate;
				}
			}
		}

		return $resolved;
	}

	/**
	 * Whether a taxonomy is public and registered for a post type.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $postType Post type slug.
	 * @return bool The result.
	 */
	private function isValidTaxonomyForType( string $taxonomy, string $postType ): bool {
		$registered = function_exists( 'get_object_taxonomies' ) ? get_object_taxonomies( $postType ) : [];

		if ( ! is_array( $registered ) || ! in_array( $taxonomy, $registered, true ) ) {
			return false;
		}

		if ( function_exists( 'get_taxonomy' ) ) {
			$tax = get_taxonomy( $taxonomy );

			if ( ! is_object( $tax ) || empty( $tax->public ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize a primary taxonomy mapping against the registered taxonomies.
	 *
	 * Keeps only public taxonomies registered for the post type named by
	 * the key. Anything else (unknown taxonomy, private taxonomy,
	 * taxonomy from another post type) becomes an empty string, which
	 * the trail builder reads as fall back to the first public taxonomy
	 * with terms.
	 *
	 * @param string $key   Setting key, primary_taxonomy_{post_type}.
	 * @param mixed  $value Raw value.
	 * @return string Sanitized taxonomy slug or empty string.
	 */
	private function sanitizeTaxonomy( string $key, mixed $value ): string {
		$taxonomy = sanitize_key( (string) $value );

		if ( '' === $taxonomy ) {
			return '';
		}

		$postType = substr( $key, strlen( 'primary_taxonomy_' ) );

		if ( '' === $postType ) {
			return '';
		}

		if ( ! $this->isValidTaxonomyForType( $taxonomy, $postType ) ) {
			return '';
		}

		return $taxonomy;
	}
}
