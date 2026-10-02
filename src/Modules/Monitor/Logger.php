<?php
/**
 * 404 capture on template_redirect priority 99.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Redirects\Normalizer;

/**
 * Logs genuine frontend 404s with cheap synchronous dedupe.
 *
 * Capture runs at template_redirect priority 99, after redirect_canonical
 * and after the redirector, so only genuine 404s reach the logger. Every
 * skip category is checked before any write: admin, AJAX, REST, cron,
 * sitemap requests, internal RankKernel generated responses, 410 and 451
 * codes, static assets, probe patterns, and configured exclusions. The
 * flood budget is spent only on real content 404s that survive every skip.
 * Referer and user agent are captured only when the advanced fields setting
 * is on, truncated to 255. No IP is ever read or stored. Sensitive query
 * values are redacted wherever one can appear: on the logged URI and on the
 * referer, so a token carried in a referer is never stored beside a path
 * that was cleaned. One increment per request per URI collapses repeat writes
 * inside a single request.
 */
final class Logger {
	/**
	 * Hook priority, after canonical and after redirect dispatch.
	 */
	public const PRIORITY = 99;

	/**
	 * Maximum stored length for referer and user agent.
	 */
	public const FIELD_LENGTH = 255;

	/**
	 * Maximum stored length of the query string.
	 *
	 * The query is attacker controlled and the 404 log keeps it for up to
	 * 365 days, so it is capped to stop one request from bloating the row
	 * or retaining a long payload. 500 characters keeps the debugging
	 * shape of ordinary links without persisting a full blob.
	 */
	public const QUERY_LENGTH = 500;

	/**
	 * Placeholder written in place of a sensitive query value.
	 */
	private const REDACTED = '[redacted]';

	/**
	 * Query key fragments whose values are redacted before storage.
	 *
	 * Matched case insensitively as a substring of the parameter name, so
	 * token, access_token and reset_token are all caught. The name and the
	 * parameter order survive for debugging while the value never reaches
	 * disk, so tracking ids, emails, session ids, password reset keys and
	 * signatures are not retained as personal data for up to a year.
	 *
	 * @var string[]
	 */
	private const SENSITIVE_QUERY_FRAGMENTS = [
		'token',
		'key',
		'secret',
		'password',
		'passwd',
		'pwd',
		'auth',
		'session',
		'email',
		'mail',
		'signature',
		'sig',
		'code',
		'access',
		'reset',
		'verify',
	];

	/**
	 * Static asset extensions, never logged.
	 *
	 * @var string[]
	 */
	private const STATIC_EXTENSIONS = [
		'jpg',
		'jpeg',
		'png',
		'gif',
		'webp',
		'svg',
		'ico',
		'avif',
		'bmp',
		'tif',
		'tiff',
		'css',
		'js',
		'mjs',
		'map',
		'woff',
		'woff2',
		'ttf',
		'otf',
		'eot',
		'mp4',
		'webm',
		'mp3',
		'wav',
		'ogg',
		'pdf',
		'zip',
		'gz',
	];

	/**
	 * Scanner probe substrings, never logged.
	 *
	 * @var string[]
	 */
	private const PROBE_SUBSTRINGS = [
		'.env',
		'wp-config',
		'.git/',
		'xmlrpc.php',
		'phpmyadmin',
		'backup.sql',
		'debug.log',
		'wlwmanifest',
		'composer.json',
	];

	/**
	 * URI hashes already logged during this request.
	 *
	 * @var array<string, bool>
	 */
	private static array $logged = [];

	/**
	 * Log repository.
	 *
	 * @var MonitorRepository
	 */
	private MonitorRepository $repository;

	/**
	 * Module settings.
	 *
	 * @var MonitorSettings
	 */
	private MonitorSettings $settings;

	/**
	 * Flood guard.
	 *
	 * @var FloodGuard
	 */
	private FloodGuard $flood;

	/**
	 * Pruner, scheduled on shutdown after an insert.
	 *
	 * @var Pruner
	 */
	private Pruner $pruner;

	/**
	 * Response code override, for tests only.
	 *
	 * @var int|null
	 */
	private ?int $responseCodeOverride = null;

	/**
	 * Constructor, dependencies are injectable for tests.
	 *
	 * @param MonitorRepository|null $repository Log repository.
	 * @param MonitorSettings|null   $settings   Module settings.
	 * @param FloodGuard|null        $flood      Flood guard.
	 * @param Pruner|null            $pruner     Pruner.
	 */
	public function __construct( ?MonitorRepository $repository = null, ?MonitorSettings $settings = null, ?FloodGuard $flood = null, ?Pruner $pruner = null ) {
		$this->repository = $repository ?? new MonitorRepository();
		$this->settings   = $settings ?? new MonitorSettings();
		$this->flood      = $flood ?? new FloodGuard( $this->settings );
		$this->pruner     = $pruner ?? new Pruner( $this->repository, $this->settings );
	}

	/**
	 * Reset the per request log, for tests only.
	 */
	public static function resetLogged(): void {
		self::$logged = [];
	}

	/**
	 * Set the response code override, for tests only.
	 *
	 * @param int|null $code Response code or null to read the live code.
	 */
	public function setResponseCodeOverride( ?int $code ): void {
		$this->responseCodeOverride = $code;
	}

	/**
	 * Register the capture hook.
	 */
	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybeLog' ], self::PRIORITY );
	}

	/**
	 * Maybe log the current request as a 404.
	 */
	public function maybeLog(): void {
		if ( ! function_exists( 'is_404' ) || ! is_404() ) {
			return;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return;
		}

		if ( $this->isSitemapRequest() ) {
			return;
		}

		$code = $this->responseCode();

		if ( 410 === $code || 451 === $code ) {
			return;
		}

		if ( ! LogTable::exists() ) {
			return;
		}

		// Frontend capture runs outside any form context, the raw URI is parsed and normalized before use and never echoed.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$rawUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$uri    = $this->normalizeUri( $rawUri );

		if ( '' === $uri || '/' === $uri ) {
			return;
		}

		if ( self::isStaticAsset( $uri ) ) {
			return;
		}

		if ( self::isProbe( $uri ) ) {
			return;
		}

		if ( Exclusions::matches( $uri, $this->settings->getExclusions() ) ) {
			return;
		}

		$hash = hash( 'sha256', $uri );

		if ( isset( self::$logged[ $hash ] ) ) {
			return;
		}

		$existing = $this->repository->findByHash( $hash );

		if ( null === $existing && ! $this->flood->allowNew() ) {
			return;
		}

		if ( $this->settings->isAdvancedFields() ) {
			$referer   = $this->serverField( 'HTTP_REFERER' );
			$userAgent = $this->serverField( 'HTTP_USER_AGENT' );
		} else {
			$referer   = '';
			$userAgent = '';
		}

		$result = $this->repository->record( $hash, $uri, $referer, $userAgent );

		if ( '' === $result ) {
			return;
		}

		self::$logged[ $hash ] = true;

		if ( 'insert' === $result ) {
			$pruner = $this->pruner;

			add_action(
				'shutdown',
				static function () use ( $pruner ): void {
					$pruner->prune();
				}
			);
		}
	}

	/**
	 * Normalize the request URI for logging identity.
	 *
	 * The path passes through the shared redirect normalizer so 404 URIs
	 * and redirect sources compare identically. The query string is kept
	 * only when the ignore_query setting is off. When kept, the query is
	 * capped at QUERY_LENGTH and the value of every sensitive key is
	 * redacted, because the row is retained for up to 365 days. A safe
	 * query is returned byte for byte, so its hash stays stable for
	 * redirect matching and 404 grouping.
	 *
	 * @param string $rawUri Raw request URI.
	 * @return string Normalized URI used for hashing and display.
	 */
	public function normalizeUri( string $rawUri ): string {
		$path  = $rawUri;
		$query = '';
		$qpos  = strpos( $rawUri, '?' );

		if ( false !== $qpos ) {
			$path  = substr( $rawUri, 0, (int) $qpos );
			$query = substr( $rawUri, (int) $qpos + 1 );
		}

		$normalized = Normalizer::normalize( $path );

		if ( $this->settings->isIgnoreQuery() ) {
			return $normalized;
		}

		$hash = strpos( $query, '#' );

		if ( false !== $hash ) {
			$query = substr( $query, 0, (int) $hash );
		}

		$query = trim( $query );
		$query = $this->redactQuery( $query );

		if ( '' === $query ) {
			return $normalized;
		}

		return $normalized . '?' . $query;
	}

	/**
	 * Redact sensitive values and cap the stored query string.
	 *
	 * Splits on ampersand and semicolon while preserving the exact
	 * separators and parameter order, so a safe query round trips with an
	 * identical hash. Each parameter whose name contains a sensitive
	 * fragment has its value replaced with a placeholder, repeated keys
	 * included. The result is then capped at QUERY_LENGTH.
	 *
	 * @param string $query Raw trimmed query string.
	 * @return string Redacted and capped query string.
	 */
	private function redactQuery( string $query ): string {
		if ( '' === $query ) {
			return $query;
		}

		$parts = preg_split( '/([&;])/', $query, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( is_array( $parts ) ) {
			$count = count( $parts );

			for ( $i = 0; $i < $count; $i += 2 ) {
				$parts[ $i ] = $this->redactParameter( (string) $parts[ $i ] );
			}

			$query = implode( '', $parts );
		}

		if ( $this->queryStringLength( $query ) > self::QUERY_LENGTH ) {
			$query = $this->clampQuery( $query );
		}

		return $query;
	}

	/**
	 * Replace one parameter's value when its name is sensitive.
	 *
	 * @param string $parameter One raw query parameter, possibly empty.
	 * @return string The parameter with its value redacted when sensitive.
	 */
	private function redactParameter( string $parameter ): string {
		if ( '' === $parameter ) {
			return $parameter;
		}

		$eq = strpos( $parameter, '=' );

		if ( false === $eq ) {
			return $parameter;
		}

		$name = substr( $parameter, 0, (int) $eq );

		if ( ! $this->isSensitiveName( $name ) ) {
			return $parameter;
		}

		return $name . '=' . self::REDACTED;
	}

	/**
	 * Whether a parameter name carries a sensitive fragment.
	 *
	 * The name is percent decoded before matching, so an encoded key
	 * cannot slip a token or email value past the redaction.
	 *
	 * @param string $name Raw parameter name.
	 * @return bool The result.
	 */
	private function isSensitiveName( string $name ): bool {
		$decoded = function_exists( 'rawurldecode' ) ? rawurldecode( $name ) : $name;
		$needle  = strtolower( $decoded );

		foreach ( self::SENSITIVE_QUERY_FRAGMENTS as $fragment ) {
			if ( str_contains( $needle, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Length of a query string in characters, multibyte aware.
	 *
	 * @param string $query Query string.
	 * @return int The result.
	 */
	private function queryStringLength( string $query ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $query ) : strlen( $query );
	}

	/**
	 * Cap the query to the stored maximum.
	 *
	 * @param string $query Query string.
	 * @return string The result.
	 */
	private function clampQuery( string $query ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $query, 0, self::QUERY_LENGTH );
		}

		return substr( $query, 0, self::QUERY_LENGTH );
	}

	/**
	 * Whether the URI targets a static asset by extension.
	 *
	 * @param string $uri Normalized URI.
	 * @return bool True when the extension is a known asset type.
	 */
	public static function isStaticAsset( string $uri ): bool {
		$path = $uri;
		$qpos = strpos( $path, '?' );

		if ( false !== $qpos ) {
			$path = substr( $path, 0, (int) $qpos );
		}

		$slash = strrpos( $path, '/' );
		$base  = false === $slash ? $path : substr( $path, (int) $slash + 1 );
		$dot   = strrpos( $base, '.' );

		if ( false === $dot ) {
			return false;
		}

		$ext = strtolower( substr( $base, (int) $dot + 1 ) );

		return in_array( $ext, self::STATIC_EXTENSIONS, true );
	}

	/**
	 * Whether the URI matches a known scanner probe pattern.
	 *
	 * @param string $uri Normalized URI.
	 * @return bool True when a probe substring is present.
	 */
	public static function isProbe( string $uri ): bool {
		foreach ( self::PROBE_SUBSTRINGS as $probe ) {
			if ( false !== stripos( $uri, $probe ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current request is a sitemap or RankKernel generated response.
	 *
	 * Any rankkernel_sitemap query var marks the request as internally
	 * generated, including the 404 responses the sitemap router sends for
	 * unknown sets, so those never enter the 404 log.
	 *
	 * @return bool The result.
	 */
	private function isSitemapRequest(): bool {
		if ( ! function_exists( 'get_query_var' ) ) {
			return false;
		}

		$sitemap = get_query_var( 'rankkernel_sitemap', '' );

		if ( '' !== (string) $sitemap ) {
			return true;
		}

		$xsl = get_query_var( 'rankkernel_sitemap_xsl', '' );

		return '' !== (string) $xsl;
	}

	/**
	 * Current response code, override wins in tests.
	 *
	 * @return int The result.
	 */
	private function responseCode(): int {
		if ( null !== $this->responseCodeOverride ) {
			return $this->responseCodeOverride;
		}

		if ( function_exists( 'http_response_code' ) ) {
			$code = http_response_code();

			if ( is_int( $code ) ) {
				return $code;
			}
		}

		return 200;
	}

	/**
	 * Read and truncate a server field, never an IP field.
	 *
	 * Only referer and user agent names are ever requested by callers.
	 *
	 * @param string $name Server field name.
	 * @return string Sanitized value, at most 255 characters.
	 */
	private function serverField( string $name ): string {
		if ( ! isset( $_SERVER[ $name ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- key allowlisted by callers, value unslashed and sanitized below.
		$raw = $_SERVER[ $name ];

		if ( ! is_string( $raw ) ) {
			return '';
		}

		$value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( wp_unslash( $raw ) ) : trim( $raw );

		if ( 'HTTP_REFERER' === $name ) {
			$value = $this->redactUrlQuery( $value );
		}

		if ( strlen( $value ) > self::FIELD_LENGTH ) {
			$value = substr( $value, 0, self::FIELD_LENGTH );
		}

		return $value;
	}

	/**
	 * Redact the query component of a stored URL, keeping the rest intact.
	 *
	 * The 404 URI runs through redactQuery() before storage, but the referer
	 * beside it did not, so a request carrying a reset token in its referer
	 * stored that token verbatim while the path beside it was cleaned. A
	 * referer is attacker and third party controlled, and it frequently
	 * carries session, token and email parameters, so it now gets the same
	 * treatment as the path. The path, host and fragment are left alone,
	 * because only a query carries a value that looks like a secret.
	 *
	 * @param string $url Stored field value.
	 * @return string The value with a redacted query component.
	 */
	private function redactUrlQuery( string $url ): string {
		if ( '' === $url ) {
			return $url;
		}

		$qpos = strpos( $url, '?' );

		if ( false === $qpos ) {
			return $url;
		}

		$base  = substr( $url, 0, (int) $qpos );
		$query = substr( $url, (int) $qpos + 1 );
		$hash  = strpos( $query, '#' );

		if ( false !== $hash ) {
			$query = substr( $query, 0, (int) $hash );
		}

		$query = $this->redactQuery( trim( $query ) );

		if ( '' === $query ) {
			return $base;
		}

		return $base . '?' . $query;
	}
}
