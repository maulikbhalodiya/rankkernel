<?php
/**
 * 404 capture on template_redirect priority 99.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

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
 * is on, truncated to 255. No IP is ever read or stored. One increment per
 * URI per request collapses repeat writes inside a single request.
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
	 */
	private MonitorRepository $repository;

	/**
	 * Module settings.
	 */
	private MonitorSettings $settings;

	/**
	 * Flood guard.
	 */
	private FloodGuard $flood;

	/**
	 * Pruner, scheduled on shutdown after an insert.
	 */
	private Pruner $pruner;

	/**
	 * Response code override, for tests only.
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
	 * only when the ignore_query setting is off.
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

		if ( '' === $query ) {
			return $normalized;
		}

		return $normalized . '?' . $query;
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

		$raw = $_SERVER[ $name ];

		if ( ! is_string( $raw ) ) {
			return '';
		}

		$value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( wp_unslash( $raw ) ) : trim( $raw );

		if ( strlen( $value ) > self::FIELD_LENGTH ) {
			$value = substr( $value, 0, self::FIELD_LENGTH );
		}

		return $value;
	}
}
