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
 * wp_rankkernel_indexnow_log table.
 *
 * The API key is generated server side, stored with the autoloaded
 * settings, and never leaves PHP. The log records every outcome in its
 * own table until an admin clears it, so the row count is a record
 * rather than a rolling window, and no log row ever carries the key.
 */
final class IndexNowSettings {
	/**
	 * Settings option name.
	 */
	public const OPTION = 'rankkernel_instant_indexing_settings';

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
	 * Append one outcome to the log.
	 *
	 * A single INSERT, never a read then rewrite: the table is the
	 * record and keeps every row until an admin clears it. The time is
	 * UTC, matching the option rows this replaces, and long values are
	 * clamped to the column widths so strict SQL mode never loses a
	 * diagnostic row to its length.
	 *
	 * @param string $url     Submitted URL.
	 * @param int    $code    HTTP status code.
	 * @param string $source  Submitting surface, auto or manual.
	 * @param string $message Human readable outcome.
	 * @return void
	 */
	public function logEntry( string $url, int $code, string $source, string $message ): void {
		$db = $this->connection();

		if ( null === $db || ! LogTable::exists() ) {
			return;
		}

		// Custom log table has no core API, typed insert with a format list.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$db->insert(
			LogTable::name(),
			[
				'url'     => $this->clamp( $url, 65535 ),
				'host'    => $this->clamp( $this->urlHost( $url ), 255 ),
				'code'    => max( 0, min( 65535, $code ) ),
				'source'  => $this->clamp( $source, 20 ),
				'message' => $this->clamp( $message, 500 ),
				'created' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Delete every log entry.
	 *
	 * @return void
	 */
	public function clearLog(): void {
		$db = $this->connection();

		if ( null === $db || ! LogTable::exists() ) {
			return;
		}

		$table = LogTable::name();

		// Custom log table has no core API, full clear with a constant predicate so the statement stays prepared.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$db->query( $db->prepare( "DELETE FROM `{$table}` WHERE 1 = %d", 1 ) );
	}

	/**
	 * Host of a submitted URL, empty when it cannot be parsed.
	 *
	 * Stored so the host filter and display never reparse the URL.
	 *
	 * @param string $url Submitted URL.
	 * @return string The result.
	 */
	private function urlHost( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		if ( function_exists( 'wp_parse_url' ) ) {
			return (string) wp_parse_url( $url, PHP_URL_HOST );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- wp_parse_url is unavailable outside WordPress, the fallback only reads the host.
		return (string) parse_url( $url, PHP_URL_HOST );
	}

	/**
	 * Clamp a value to a column width.
	 *
	 * @param string $value Value to clamp.
	 * @param int    $limit Maximum length in characters.
	 * @return string The result.
	 */
	private function clamp( string $value, int $limit ): string {
		if ( strlen( $value ) <= $limit ) {
			return $value;
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	/**
	 * Active database handle, null outside WordPress.
	 *
	 * @return \wpdb|null The result.
	 */
	private function connection() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}

		return $wpdb;
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
