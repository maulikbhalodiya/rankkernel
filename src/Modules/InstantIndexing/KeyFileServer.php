<?php
/**
 * Virtual IndexNow verification key file endpoint.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\InstantIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the IndexNow verification key file from a virtual route.
 *
 * IndexNow fetches the key file to prove the key belongs to the host. The
 * file is never written to disk: this class answers the key request from
 * the request path, or from the KEY_QUERY_ARG fallback on plain permalinks,
 * and leaves every other request untouched. A request only matches when the
 * path or query argument equals the configured key byte for byte, compared
 * with hash_equals() so a wrong key cannot be discovered by timing.
 */
final class KeyFileServer {
	/**
	 * Settings owning the key.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param IndexNowSettings $settings Settings owning the key.
	 */
	public function __construct( IndexNowSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hook the key file server into the request lifecycle.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'parse_request', [ $this, 'maybeServe' ] );
		}
	}

	/**
	 * Serve the key file when the request asks for it.
	 *
	 * Runs on parse_request, so every request is matched against the key
	 * and anything that does not match is left alone.
	 *
	 * @param mixed $wp WordPress environment passed by the parse_request action, null when called directly.
	 * @return void
	 */
	public function maybeServe( mixed $wp = null ): void {
		$key = $this->settings->getKey();

		if ( '' === $key ) {
			return;
		}

		$vars        = is_object( $wp ) ? get_object_vars( $wp ) : [];
		$requestPath = isset( $vars['request'] ) && is_string( $vars['request'] ) ? $vars['request'] : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only matching, the value is compared with hash_equals() and never stored, logged or echoed raw. A valid key alphabet cannot contain slashes.
		$query = $_GET;

		if ( ! $this->matches( $requestPath, $query ) ) {
			return;
		}

		$this->respond( $key );
	}

	/**
	 * Whether a request path and query identify the key file.
	 *
	 * With a permalink structure the key is served from the root txt file,
	 * so only the path form matches. On plain permalinks the key is served
	 * through KEY_QUERY_ARG, so only the query form matches. An empty key
	 * never matches anything, keeping the class inert before a key exists.
	 *
	 * @param string               $requestPath Request path relative to the home URL, no leading slash.
	 * @param array<string, mixed> $query       Request query arguments.
	 * @return bool The result.
	 */
	public function matches( string $requestPath, array $query ): bool {
		$key = $this->settings->getKey();

		if ( '' === $key ) {
			return false;
		}

		if ( $this->servesQueryLocation() ) {
			$candidate = $query[ IndexNowSettings::KEY_QUERY_ARG ] ?? null;

			if ( ! is_string( $candidate ) ) {
				return false;
			}

			return hash_equals( $key, $candidate );
		}

		return hash_equals( $this->settings->keyFileName(), trim( $requestPath, '/' ) );
	}

	/**
	 * Emit the key file response and stop the request.
	 *
	 * The body is exactly the key and nothing else, served as plain text
	 * with indexing disabled, then the request is terminated so no theme
	 * or template output can follow. The exit is skipped under tests.
	 *
	 * @param string $key Key to serve.
	 * @return void
	 */
	public function respond( string $key ): void {
		if ( ! headers_sent() ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( 200 );
			}

			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Robots-Tag: noindex, nofollow' );

			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
		}

		echo $this->responseBody( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in responseBody().

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Response body for the key file, separated so tests never hit exit.
	 *
	 * The body must be the key byte for byte, so escaping is a no-op for
	 * every protocol valid key and a hard stop if a key ever carried markup.
	 *
	 * @param string $key Key to render.
	 * @return string The result.
	 */
	public function responseBody( string $key ): string {
		if ( function_exists( 'esc_html' ) ) {
			return (string) esc_html( $key );
		}

		return htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Whether the key is advertised through the query argument.
	 *
	 * The advertised location decides the only form answered here: the
	 * root txt path when a permalink structure exists, the query argument
	 * on plain permalinks. The settings class exposes the structure only
	 * through this location, and the key alphabet cannot contain the
	 * query argument name, so the check is exact.
	 *
	 * @return bool The result.
	 */
	private function servesQueryLocation(): bool {
		return str_contains( $this->settings->keyLocation(), IndexNowSettings::KEY_QUERY_ARG );
	}
}
