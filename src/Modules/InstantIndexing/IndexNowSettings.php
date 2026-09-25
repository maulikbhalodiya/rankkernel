<?php
/**
 * Instant Indexing settings, owns the IndexNow key and the outcome log.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\InstantIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the rankkernel_instant_indexing_settings option plus the
 * rankkernel_instant_indexing_log ring buffer.
 *
 * The API key is generated server side, stored with the autoloaded
 * settings, and never leaves PHP. The log holds the most recent
 * outcomes and is stored with autoload disabled so it stays out of
 * the alloptions cache.
 */
final class IndexNowSettings {
	/**
	 * Settings option name.
	 */
	public const OPTION = 'rankkernel_instant_indexing_settings';

	/**
	 * Outcome log option name, stored without autoload.
	 */
	public const LOG_OPTION = 'rankkernel_instant_indexing_log';

	/**
	 * Maximum number of retained log entries.
	 */
	public const LOG_LIMIT = 50;

	/**
	 * Per URL debounce window in seconds.
	 */
	public const DEBOUNCE_SECONDS = 600;

	/**
	 * Query argument that serves the key on plain permalinks.
	 */
	public const KEY_QUERY_ARG = 'rankkernel_indexnow_key';

	/**
	 * Cached merged settings (defaults plus stored).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Permalink structure override, null reads the real option.
	 *
	 * @var string|null
	 */
	private ?string $permalinkStructure = null;

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'api_key'     => '',
			'auto_submit' => false,
		];
	}

	/**
	 * Whether a key satisfies the IndexNow protocol alphabet.
	 *
	 * 8 to 128 characters of A to Z, a to z, 0 to 9 and hyphen.
	 *
	 * @param string $key Candidate key.
	 * @return bool The result.
	 */
	public static function isValidKey( string $key ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9-]{8,128}$/', $key );
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

		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, [] ) : [];

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$this->cache = array_merge( self::defaults(), $stored );

		return $this->cache;
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
	 * Update settings with a partial array (whitelisted keys only).
	 *
	 * Only api_key and auto_submit are accepted. An api_key that fails
	 * isValidKey() is dropped, so a bad value can never overwrite a
	 * working key.
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

			if ( 'api_key' === $key ) {
				$candidate = (string) $value;

				if ( self::isValidKey( $candidate ) ) {
					$sanitized['api_key'] = $candidate;
				}

				continue;
			}

			if ( 'auto_submit' === $key ) {
				$normalized = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

				$sanitized['auto_submit'] = null !== $normalized ? $normalized : (bool) $value;
			}
		}

		if ( [] === $sanitized ) {
			return false;
		}

		$merged = array_merge( $this->all(), $sanitized );

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, $merged, true );
		}

		$this->cache = $merged;

		return true;
	}

	/**
	 * Get the stored key, an empty string when none is valid.
	 *
	 * @return string The result.
	 */
	public function getKey(): string {
		return (string) $this->get( 'api_key', '' );
	}

	/**
	 * Return a valid key, generating and persisting one when missing.
	 *
	 * @return string The result.
	 */
	public function ensureKey(): string {
		$current = $this->getKey();

		if ( self::isValidKey( $current ) ) {
			return $current;
		}

		return $this->resetKey();
	}

	/**
	 * Replace the stored key with a freshly generated one.
	 *
	 * @return string The result.
	 */
	public function resetKey(): string {
		$key = function_exists( 'wp_generate_password' )
			? strtolower( wp_generate_password( 32, false, false ) )
			: bin2hex( random_bytes( 16 ) );

		if ( ! self::isValidKey( $key ) ) {
			$key = bin2hex( random_bytes( 16 ) );
		}

		$this->set( [ 'api_key' => $key ] );

		return $key;
	}

	/**
	 * Whether automatic submission on publish is enabled.
	 *
	 * @return bool The result.
	 */
	public function getAutoSubmit(): bool {
		return (bool) $this->get( 'auto_submit', false );
	}

	/**
	 * Enable or disable automatic submission.
	 *
	 * @param bool $enabled Whether to enable.
	 * @return void
	 */
	public function setAutoSubmit( bool $enabled ): void {
		$this->set( [ 'auto_submit' => $enabled ] );
	}

	/**
	 * File name the key is served under.
	 *
	 * @return string The result.
	 */
	public function keyFileName(): string {
		return $this->getKey() . '.txt';
	}

	/**
	 * Public location of the key.
	 *
	 * A root level txt file when a permalink structure exists, the
	 * KEY_QUERY_ARG form when permalinks are plain, because a path
	 * based key file silently 404s in that case.
	 *
	 * @return string The result.
	 */
	public function keyLocation(): string {
		if ( '' !== $this->permalinkStructure() ) {
			$home = function_exists( 'home_url' ) ? (string) home_url() : '';

			if ( function_exists( 'trailingslashit' ) ) {
				$home = trailingslashit( $home );
			} elseif ( '' !== $home ) {
				$home = rtrim( $home, '/' ) . '/';
			}

			return $home . $this->keyFileName();
		}

		$key  = $this->getKey();
		$base = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';

		if ( function_exists( 'add_query_arg' ) ) {
			return (string) add_query_arg( self::KEY_QUERY_ARG, $key, $base );
		}

		return $base . '?' . self::KEY_QUERY_ARG . '=' . rawurlencode( $key );
	}

	/**
	 * Site host without the scheme, www is never collapsed.
	 *
	 * @return string The result.
	 */
	public function siteHost(): string {
		$home = function_exists( 'home_url' ) ? (string) home_url() : '';

		if ( function_exists( 'wp_parse_url' ) ) {
			return (string) wp_parse_url( $home, PHP_URL_HOST );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- wp_parse_url is unavailable outside WordPress, the fallback only parses the core built home URL.
		return (string) parse_url( $home, PHP_URL_HOST );
	}

	/**
	 * Prepend one outcome to the capped log.
	 *
	 * @param string $url     Submitted URL.
	 * @param int    $code    HTTP status code.
	 * @param string $source  Submitting surface, auto or manual.
	 * @param string $message Human readable outcome.
	 * @return void
	 */
	public function logEntry( string $url, int $code, string $source, string $message ): void {
		$entries = $this->logEntries();

		array_unshift(
			$entries,
			[
				'url'     => $url,
				'code'    => $code,
				'source'  => $source,
				'time'    => gmdate( 'Y-m-d H:i:s' ),
				'message' => $message,
			]
		);

		$entries = array_slice( $entries, 0, self::LOG_LIMIT );

		if ( function_exists( 'update_option' ) ) {
			update_option( self::LOG_OPTION, $entries, false );
		}
	}

	/**
	 * Get the stored log entries, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function logEntries(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::LOG_OPTION, [] ) : [];

		if ( ! is_array( $stored ) ) {
			return [];
		}

		$entries = [];

		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$entries[] = [
				'url'     => (string) ( $entry['url'] ?? '' ),
				'code'    => (int) ( $entry['code'] ?? 0 ),
				'source'  => (string) ( $entry['source'] ?? '' ),
				'time'    => (string) ( $entry['time'] ?? '' ),
				'message' => (string) ( $entry['message'] ?? '' ),
			];
		}

		return $entries;
	}

	/**
	 * Delete every log entry.
	 *
	 * @return void
	 */
	public function clearLog(): void {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LOG_OPTION, [], false );
		}
	}

	/**
	 * Permalink structure, isolated so tests can simulate plain permalinks.
	 *
	 * @param string $structure Permalink structure to report.
	 * @return void
	 */
	public function setPermalinkStructure( string $structure ): void {
		$this->permalinkStructure = $structure;
	}

	/**
	 * Current permalink structure, empty string when permalinks are plain.
	 *
	 * @return string The result.
	 */
	private function permalinkStructure(): string {
		if ( null !== $this->permalinkStructure ) {
			return $this->permalinkStructure;
		}

		return function_exists( 'get_option' ) ? (string) get_option( 'permalink_structure', '' ) : '';
	}
}
