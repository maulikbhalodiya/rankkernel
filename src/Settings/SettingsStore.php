<?php
/**
 * Settings store.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Settings;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\MetaPayload;
use RankKernel\Modules\Schema\SchemaTypes;

/**
 * Manages the rankkernel_settings option.
 */
final class SettingsStore {
	/**
	 * Option name.
	 */
	public const OPTION = 'rankkernel_settings';

	/**
	 * Maximum profile URLs kept for the organization sameAs field.
	 *
	 * This option is autoloaded, so every entry here is read on every
	 * frontend request. The bound keeps a repeated save from growing a
	 * hot option without limit.
	 */
	public const ORG_SAMEAS_MAX = 20;

	/**
	 * Maximum schema_default_ dynamic keys stored at once.
	 *
	 * Each key is a per post type default type, so the useful number is
	 * bounded by how many post types a site has. The cap stops an
	 * unbounded key namespace accumulating in the same hot option.
	 */
	public const SCHEMA_DEFAULT_MAX = 50;

	/**
	 * Whitelisted setting keys.
	 *
	 * @var string[]
	 */
	private const ALLOWED_KEYS = [
		'title_template',
		'description_template',
		'separator',
		'social_facebook',
		'social_twitter',
		'social_instagram',
		'social_linkedin',
		'social_youtube',
		'social_pinterest',
		'social_default_image',
		'social_default_image_id',
		'twitter_site',
		'webmaster_google',
		'webmaster_bing',
		'webmaster_yandex',
		'webmaster_baidu',
		'webmaster_pinterest',
		'site_represents',
		'org_name',
		'org_logo',
		'org_sameas',
		'website_search_action',
		'schema_breadcrumbs',
		'schema_author',
		'purge_on_uninstall',
	];

	/**
	 * Cached merged settings (defaults + stored).
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
			'title_template'          => '%%title%% %%sep%% %%sitename%%',
			'description_template'    => '%%excerpt%%',
			'separator'               => '–',
			'social_facebook'         => '',
			'social_twitter'          => '',
			'social_instagram'        => '',
			'social_linkedin'         => '',
			'social_youtube'          => '',
			'social_pinterest'        => '',
			'social_default_image'    => '',
			'social_default_image_id' => 0,
			'twitter_site'            => '',
			'webmaster_google'        => '',
			'webmaster_bing'          => '',
			'webmaster_yandex'        => '',
			'webmaster_baidu'         => '',
			'webmaster_pinterest'     => '',
			'site_represents'         => 'organization',
			'org_name'                => '',
			'org_logo'                => '',
			'org_sameas'              => [],
			'website_search_action'   => true,
			'schema_breadcrumbs'      => true,
			'schema_author'           => true,
			'purge_on_uninstall'      => null,
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
	 * @return bool Whether the update succeeded.
	 */
	public function set( array $partial ): bool {
		$sanitized   = [];
		$dynamicKeys = 0;

		foreach ( $partial as $key => $value ) {
			if ( self::isDefaultTypeKey( $key ) ) {
				// The suffix names a post type, so it is validated against a
				// real registered post type rather than only against the key
				// shape. BreadcrumbsSettings does the same for its own dynamic
				// keys, so the two stores now agree on what a valid suffix is.
				if ( ! self::isRegisteredPostType( $key ) ) {
					continue;
				}

				++$dynamicKeys;

				if ( $dynamicKeys > self::SCHEMA_DEFAULT_MAX ) {
					continue;
				}

				$clean = trim( (string) $value );

				if ( '' === $clean ) {
					$sanitized[ $key ] = '';
					continue;
				}

				if ( ! in_array( $clean, SchemaTypes::SUPPORTED, true ) ) {
					continue;
				}

				$sanitized[ $key ] = $clean;
				continue;
			}

			if ( ! in_array( $key, self::ALLOWED_KEYS, true ) ) {
				continue;
			}

			$sanitized[ $key ] = $this->sanitize( $key, $value );
		}

		if ( [] === $sanitized ) {
			return false;
		}

		$current = $this->all();
		$merged  = array_merge( $current, $sanitized );

		// update_option() returns false both when the value is unchanged and
		// when the write fails, so the write is skipped when nothing changed.
		// An unchanged save still counts as a success; a changed write that
		// returns false reports the failure instead of updating the cache.
		if ( $merged === $current ) {
			return true;
		}

		if ( ! update_option( self::OPTION, $merged ) ) {
			return false;
		}

		$this->cache = $merged;

		return true;
	}

	/**
	 * Whether a key is a per post type default type key.
	 *
	 * Dynamic keys look like schema_default_{post_type}. They are not
	 * listed in ALLOWED_KEYS, the pattern gates them instead, and values
	 * must match the central type list (empty means Automatic).
	 *
	 * @param mixed $key Raw key.
	 * @return bool The result.
	 */
	private static function isDefaultTypeKey( mixed $key ): bool {
		if ( ! is_string( $key ) ) {
			return false;
		}

		if ( ! str_starts_with( $key, 'schema_default_' ) ) {
			return false;
		}

		return 1 === preg_match( '/^schema_default_[a-z0-9_-]+$/', $key );
	}

	/**
	 * Whether a schema_default_ key names a real post type.
	 *
	 * The key shape alone left the namespace unbounded, because
	 * SettingsController passes get_params() and unregistered body keys come
	 * back verbatim. When WordPress is absent, as in a unit test, the shape
	 * check stands alone so the store keeps working without a bootstrap.
	 *
	 * @param string $key Candidate key.
	 * @return bool True when the suffix is a registered post type.
	 */
	private static function isRegisteredPostType( string $key ): bool {
		if ( ! function_exists( 'post_type_exists' ) ) {
			return true;
		}

		$postType = substr( $key, strlen( 'schema_default_' ) );

		if ( '' === $postType ) {
			return false;
		}

		return post_type_exists( $postType );
	}

	/**
	 * Sanitize a single value by key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize( string $key, mixed $value ): mixed {
		if ( 'site_represents' === $key ) {
			$normalized = strtolower( trim( (string) $value ) );

			if ( in_array( $normalized, [ 'organization', 'person' ], true ) ) {
				return $normalized;
			}

			return 'organization';
		}

		if ( 'social_default_image' === $key ) {
			return esc_url_raw( is_string( $value ) ? trim( $value ) : '' );
		}

		if ( 'social_default_image_id' === $key ) {
			return is_numeric( $value ) && (int) $value > 0 ? absint( $value ) : 0;
		}

		if ( 'twitter_site' === $key ) {
			return MetaPayload::sanitizeTwitterHandle( $value );
		}

		if ( 'org_logo' === $key ) {
			return esc_url_raw( trim( (string) $value ) );
		}

		if ( 'org_sameas' === $key ) {
			$urls  = is_array( $value ) ? array_values( $value ) : [ $value ];
			$clean = [];

			foreach ( $urls as $url ) {
				$sanitized = esc_url_raw( trim( (string) $url ) );

				if ( '' !== $sanitized ) {
					$clean[] = $sanitized;
				}
			}

			// rankkernel_settings is autoloaded and read on every frontend
			// request, so an unbounded list here grows a value already
			// resident in wp_load_alloptions() on every request. Twenty
			// profiles is far past any real use of the field.
			return array_slice( $clean, 0, self::ORG_SAMEAS_MAX );
		}

		if ( 'website_search_action' === $key || 'schema_breadcrumbs' === $key || 'schema_author' === $key ) {
			if ( is_bool( $value ) ) {
				return $value;
			}

			$normalized = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			if ( null !== $normalized ) {
				return $normalized;
			}

			return (bool) $value;
		}

		if ( 'org_name' === $key ) {
			return sanitize_text_field( (string) $value );
		}

		if ( 'purge_on_uninstall' === $key ) {
			if ( null === $value ) {
				return null;
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

		if ( str_starts_with( $key, 'social_' ) || str_starts_with( $key, 'webmaster_' ) ) {
			return sanitize_text_field( (string) $value );
		}

		// title_template, description_template, separator.
		return sanitize_text_field( (string) $value );
	}
}
