<?php
/**
 * Frontend redirect dispatcher.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * Dispatches redirects on template_redirect priority 1.
 *
 * Flow per request: guard admin, AJAX, REST, cron, and sitemap requests, fail
 * open when the table is missing, normalize the request path through the
 * shared normalizer, read the match cache first, run at most one indexed
 * lookup plus one bounded pattern list read on a cold miss, warm the cache on
 * a hit, send exactly one redirect per request through the reentry guard,
 * validate the destination before sending, then exit. Terminal codes 410 and
 * 451 send a status plus a minimal body with no Location header. The hit
 * counter flushes at shutdown, never before the response.
 */
final class Redirector {
	/**
	 * Whether this request already sent a redirect.
	 */
	private static bool $sent = false;

	/**
	 * Rule repository.
	 *
	 * @var RedirectRepository
	 */
	private $repository;

	/**
	 * Match cache.
	 *
	 * @var RedirectCache
	 */
	private $cache;

	/**
	 * Hit counter.
	 *
	 * @var HitCounter
	 */
	private $hits;

	/**
	 * Module settings.
	 *
	 * @var RedirectsSettings
	 */
	private $settings;

	/**
	 * Matcher.
	 *
	 * @var Matcher
	 */
	private $matcher;

	/**
	 * Constructor, dependencies are injectable for tests.
	 *
	 * @param RedirectRepository|null $repository Rule repository.
	 * @param RedirectCache|null      $cache      Match cache.
	 * @param HitCounter|null         $hits       Hit counter.
	 * @param RedirectsSettings|null  $settings   Module settings.
	 * @param Matcher|null            $matcher    Matcher, built on the repository when null.
	 */
	public function __construct( $repository = null, $cache = null, $hits = null, $settings = null, $matcher = null ) {
		$this->repository = $repository ?? new RedirectRepository();
		$this->cache      = $cache ?? new RedirectCache();
		$this->hits       = $hits ?? new HitCounter();
		$this->settings   = $settings ?? new RedirectsSettings();
		$this->matcher    = $matcher ?? new Matcher( $this->repository );
	}

	/**
	 * Reset the reentry guard, for tests only.
	 */
	public static function resetSent(): void {
		self::$sent = false;
	}

	/**
	 * Register the dispatch hook.
	 */
	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybeRedirect' ], 1 );
	}

	/**
	 * Maybe send a redirect for the current request.
	 */
	public function maybeRedirect(): void {
		if ( self::$sent ) {
			do_action( 'rankkernel/redirect/reentry' );

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

		if ( function_exists( 'get_query_var' ) ) {
			$sitemap = get_query_var( 'rankkernel_sitemap', '' );

			if ( '' !== (string) $sitemap ) {
				return;
			}

			$xsl = get_query_var( 'rankkernel_sitemap_xsl', '' );

			if ( '' !== (string) $xsl ) {
				return;
			}
		}

		if ( ! RedirectTable::exists() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- frontend dispatch runs outside any form context, the raw URI is parsed and normalized before use and never echoed.
		$rawUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path   = Normalizer::normalize( $rawUri );

		if ( Normalizer::isBlockedSource( $path ) ) {
			return;
		}

		$rule = $this->cache->get( $path );

		if ( null === $rule ) {
			$rule = $this->matcher->match( $path );

			if ( null !== $rule ) {
				$this->cache->set( $path, $rule );
			}
		}

		if ( null === $rule || ! $this->isUsable( $rule ) ) {
			return;
		}

		$code        = (string) ( $rule['code'] ?? '301' );
		$destination = $this->buildDestination( $rule, $rawUri );

		if ( '' === $destination && ! in_array( $code, Normalizer::TERMINAL_CODES, true ) ) {
			return;
		}

		self::$sent = true;

		$this->hits->record( (int) ( $rule['id'] ?? 0 ) );

		if ( in_array( $code, Normalizer::TERMINAL_CODES, true ) ) {
			$this->sendTerminal( $code );

			return;
		}

		if ( ! in_array( $code, [ '301', '302', '307' ], true ) ) {
			return;
		}

		wp_redirect( $destination, (int) $code );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Whether a matched rule row may dispatch.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return bool True when active with a known code and matcher.
	 */
	private function isUsable( array $rule ): bool {
		if ( 1 !== (int) ( $rule['is_active'] ?? 0 ) ) {
			return false;
		}

		if ( ! Normalizer::isCode( (string) ( $rule['code'] ?? '' ) ) ) {
			return false;
		}

		return Normalizer::isMatchType( (string) ( $rule['match_type'] ?? '' ) );
	}

	/**
	 * Build the final destination: validated target plus preserved query.
	 *
	 * The incoming query string is appended when the preserve query setting
	 * is on, the destination carries no query of its own, and the rule is a
	 * redirect (terminal codes send no Location at all).
	 *
	 * @param array<string, mixed> $rule   Winning rule row.
	 * @param string               $rawUri Raw request URI.
	 * @return string Final destination or empty string when invalid.
	 */
	private function buildDestination( array $rule, string $rawUri ): string {
		$code   = (string) ( $rule['code'] ?? '301' );
		$target = (string) ( $rule['target'] ?? '' );

		$validator = new DestinationValidator();
		$checked   = $validator->validate( $target, $code, $this->allowedHosts() );

		if ( ! $checked['valid'] ) {
			return '';
		}

		$destination = $checked['destination'];

		if ( in_array( $code, Normalizer::TERMINAL_CODES, true ) ) {
			return $destination;
		}

		$preserve = $this->settings->get( 'preserve_query', true );

		if ( $preserve ) {
			$incoming = $this->incomingQuery( $rawUri );

			if ( '' !== $incoming && false === strpos( $destination, '?' ) ) {
				$destination .= '?' . $incoming;
			}
		}

		return $destination;
	}

	/**
	 * Allowlisted external hosts, filterable for future admin control.
	 *
	 * @return string[]
	 */
	private function allowedHosts(): array {
		$allowed = apply_filters( 'rankkernel/redirect/allowed_hosts', [] );

		if ( ! is_array( $allowed ) ) {
			return [];
		}

		$hosts = [];

		foreach ( $allowed as $host ) {
			$host = trim( (string) $host );

			if ( '' !== $host ) {
				$hosts[] = $host;
			}
		}

		return $hosts;
	}

	/**
	 * Query string of the incoming request, fragment excluded.
	 *
	 * @param string $rawUri Raw request URI.
	 * @return string Query part or empty string.
	 */
	private function incomingQuery( string $rawUri ): string {
		$qpos = strpos( $rawUri, '?' );

		if ( false === $qpos ) {
			return '';
		}

		$query = substr( $rawUri, (int) $qpos + 1 );
		$hash  = strpos( $query, '#' );

		if ( false !== $hash ) {
			$query = substr( $query, 0, (int) $hash );
		}

		return $query;
	}

	/**
	 * Send a terminal 410 or 451 response with a minimal body.
	 *
	 * @param string $code Terminal code.
	 */
	private function sendTerminal( string $code ): void {
		$status = '410' === $code ? 410 : 451;

		status_header( $status );
		header( 'Cache-Control: no-cache' );

		if ( 410 === $status ) {
			echo esc_html__( 'This page is gone.', 'rankkernel' );
		} else {
			echo esc_html__( 'This page is unavailable for legal reasons.', 'rankkernel' );
		}

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}
}
