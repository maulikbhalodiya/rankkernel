<?php
/**
 * IndexNow protocol client, builds and posts the submission payload.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\InstantIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Posts same host URL batches to the global IndexNow endpoint.
 *
 * The client is the only place the plugin talks to the network. It
 * sends exactly one header, stays blocking so the status code can be
 * read, never fetches a submitted URL, and drops any URL whose host
 * is not exactly the site host before a request is built.
 */
final class IndexNowClient {
	/**
	 * The only endpoint this client ever posts to.
	 */
	public const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Maximum URLs per request, the protocol cap.
	 */
	public const MAX_URLS = 10000;

	/**
	 * Request timeout in seconds.
	 */
	public const TIMEOUT = 10;

	/**
	 * Status codes that must never be retried.
	 */
	private const PERMANENT_CODES = [ 400, 403, 405, 422 ];

	/**
	 * Settings instance supplying the key and the log.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Transport override, null posts through the WordPress HTTP API.
	 *
	 * @var (callable(string, array<string, mixed>): (array<string, mixed>|\WP_Error))|null
	 */
	private $transport;

	/**
	 * Set up the client.
	 *
	 * @param IndexNowSettings                                                              $settings  Settings instance.
	 * @param callable(string, array<string, mixed>): (array<string, mixed>|\WP_Error)|null $transport Transport override, null uses the WordPress HTTP API.
	 */
	public function __construct( IndexNowSettings $settings, ?callable $transport = null ) {
		$this->settings  = $settings;
		$this->transport = $transport;
	}

	/**
	 * Submit URLs, chunked at the protocol cap and logged per URL.
	 *
	 * An empty key short circuits before any transport call, which is
	 * a security property rather than an optimization.
	 *
	 * @param string[] $urls   URLs to submit.
	 * @param string   $source Submitting surface, auto or manual.
	 * @return array{accepted:int, permanent:int, transient:int, results:array<int, array{url:string, code:int, accepted:bool, retryable:bool, message:string}>} Outcome summary.
	 */
	public function submit( array $urls, string $source = 'auto' ): array {
		$result = [
			'accepted'  => 0,
			'permanent' => 0,
			'transient' => 0,
			'results'   => [],
		];

		if ( '' === $this->settings->getKey() ) {
			return $result;
		}

		$urls = $this->filterUrls( $urls );

		if ( [] === $urls ) {
			return $result;
		}

		foreach ( array_chunk( $urls, self::MAX_URLS ) as $chunk ) {
			$response = $this->send( $this->buildPayload( $chunk ) );

			if ( $response instanceof \WP_Error ) {
				$code    = 0;
				$message = $this->errorMessage( $response );
			} else {
				$code    = $this->statusCode( $response );
				$message = $this->message( $code );
			}

			$accepted  = 200 === $code || 202 === $code;
			$permanent = in_array( $code, self::PERMANENT_CODES, true );
			$retryable = ! $accepted && ! $permanent;

			foreach ( $chunk as $url ) {
				$this->settings->logEntry( $url, $code, $source, $message );

				if ( $accepted ) {
					++$result['accepted'];
				} elseif ( $permanent ) {
					++$result['permanent'];
				} else {
					++$result['transient'];
				}

				$result['results'][] = [
					'url'       => $url,
					'code'      => $code,
					'accepted'  => $accepted,
					'retryable' => $retryable,
					'message'   => $message,
				];
			}
		}

		return $result;
	}

	/**
	 * Build the protocol payload for one batch.
	 *
	 * @param string[] $urls URLs to include.
	 * @return array{host:string, key:string, keyLocation:string, urlList:string[]} Payload.
	 */
	public function buildPayload( array $urls ): array {
		return [
			'host'        => $this->settings->siteHost(),
			'key'         => $this->settings->getKey(),
			'keyLocation' => $this->settings->keyLocation(),
			'urlList'     => $this->filterUrls( $urls ),
		];
	}

	/**
	 * Keep only same host http and https URLs, deduplicated.
	 *
	 * The host comparison case folds both hosts first and then compares
	 * exactly, so an uppercase host is accepted while www and the apex
	 * stay different hosts and are never collapsed. The list fails
	 * closed when the WordPress URL parser is unavailable.
	 *
	 * Non-string elements are dropped before parsing, because the
	 * loose array parameter is a documentation contract rather than
	 * a runtime guarantee.
	 *
	 * @param string[] $urls Candidate URLs.
	 * @return string[] Filtered list.
	 */
	public function filterUrls( array $urls ): array {
		if ( ! function_exists( 'wp_parse_url' ) ) {
			return [];
		}

		$targetHost = strtolower( $this->settings->siteHost() );
		$kept       = [];

		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			$host   = wp_parse_url( $url, PHP_URL_HOST );

			if ( ! is_string( $scheme ) || ! is_string( $host ) ) {
				continue;
			}

			if ( 'http' !== $scheme && 'https' !== $scheme ) {
				continue;
			}

			if ( strtolower( $host ) !== $targetHost ) {
				continue;
			}

			$kept[] = $url;
		}

		return array_values( array_unique( $kept ) );
	}

	/**
	 * Send one payload to the global endpoint.
	 *
	 * @param array<string, mixed> $payload Protocol payload.
	 * @return array<string, mixed>|\WP_Error Response or failure.
	 */
	private function send( array $payload ): array|\WP_Error {
		if ( ! function_exists( 'wp_json_encode' ) ) {
			return new \WP_Error( 'rankkernel_indexnow_encode', 'The WordPress JSON encoder is unavailable.' );
		}

		$body = wp_json_encode( $payload );

		if ( ! is_string( $body ) ) {
			return new \WP_Error( 'rankkernel_indexnow_encode', 'The IndexNow payload could not be encoded.' );
		}

		$args = [
			'blocking'    => true,
			'timeout'     => self::TIMEOUT,
			// Pin redirects to zero so a 3xx is surfaced as a response,
			// never followed to another host with the key in the body.
			'redirection' => 0,
			'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ],
			'body'        => $body,
		];

		$transport = $this->transport;

		if ( null !== $transport ) {
			return $transport( self::ENDPOINT, $args );
		}

		return self::defaultTransport( self::ENDPOINT, $args );
	}

	/**
	 * Post through the WordPress HTTP API, failing closed.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Request arguments.
	 * @return array<string, mixed>|\WP_Error Response or failure.
	 */
	private static function defaultTransport( string $url, array $args ): array|\WP_Error {
		if ( function_exists( 'wp_safe_remote_post' ) ) {
			return wp_safe_remote_post( $url, $args );
		}

		if ( function_exists( 'wp_remote_post' ) ) {
			return wp_remote_post( $url, $args );
		}

		return new \WP_Error( 'rankkernel_indexnow_http', 'The WordPress HTTP API is unavailable.' );
	}

	/**
	 * Read the status code from a response array.
	 *
	 * @param array<string, mixed> $response HTTP response.
	 * @return int Status code, 0 when absent.
	 */
	private function statusCode( array $response ): int {
		$responsePart = $response['response'] ?? null;

		if ( ! is_array( $responsePart ) ) {
			return 0;
		}

		return (int) ( $responsePart['code'] ?? 0 );
	}

	/**
	 * Human readable message for a status code.
	 *
	 * @param int $code HTTP status code.
	 * @return string Message.
	 */
	private function message( int $code ): string {
		if ( 202 === $code ) {
			return 'Accepted, the key is pending verification.';
		}

		if ( 200 === $code ) {
			return 'Accepted.';
		}

		if ( in_array( $code, self::PERMANENT_CODES, true ) ) {
			return 'Rejected permanently, retrying will not help.';
		}

		return 'Temporary failure, retry later.';
	}

	/**
	 * Human readable message for a transport error.
	 *
	 * @param \WP_Error $error Transport error.
	 * @return string Message.
	 */
	private function errorMessage( \WP_Error $error ): string {
		$message = $error->get_error_message();

		return '' !== $message ? $message : 'Transport failure, retry later.';
	}
}
