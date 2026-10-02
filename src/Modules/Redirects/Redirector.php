<?php
/**
 * Frontend redirect dispatcher.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatches redirects on template_redirect priority 1.
 *
 * Flow per request: guard admin, AJAX, REST, cron, and sitemap requests,
 * normalize the request path through the shared normalizer, read the match cache
 * first, fail open on a cold miss when the table is missing, run at most one
 * indexed lookup plus one bounded pattern list read on a cold miss, warm the
 * cache on a hit, send exactly one redirect per request through the reentry guard,
 * bound the redirect chain across requests with a short lived source_hash to
 * destination marker cookie that answers 410 on a repeat of the same pair,
 * validate the destination before sending, then exit. Terminal codes 410 and
 * 451 send a status plus a minimal body with no Location header. The hit
 * counter flushes at shutdown, never before the response.
 */
final class Redirector {
	/**
	 * Whether this request already sent a redirect.
	 *
	 * @var bool
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

		$this->repository->setCache( $this->cache );
	}

	/**
	 * Hop markers recorded in this process, for tests only.
	 *
	 * @var string[]
	 */
	private static array $markedHops = [];

	/**
	 * Reset the reentry guard, for tests only.
	 */
	public static function resetSent(): void {
		self::$sent       = false;
		self::$markedHops = [];
	}

	/**
	 * Hop markers recorded so far, for tests only.
	 *
	 * @return string[] Marker cookie names.
	 */
	public static function markedHops(): array {
		return self::$markedHops;
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
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- binding plan mandates the rankkernel/redirect/reentry diagnostic action, matching the rankkernel/sitemap slash namespaced hooks.
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

		// Normalize request URI early to return immediately for blocked sources without probing table existence.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- frontend dispatch runs outside any form context, the raw URI is parsed and normalized before use and never echoed.
		$rawUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path   = Normalizer::normalize( $rawUri );

		if ( Normalizer::isBlockedSource( $path ) ) {
			return;
		}

		try {
			// Check match cache first to avoid probing table existence or running SQL/transient lookups on cache hits.
			$rule = $this->cache->get( $path );

			if ( null === $rule ) {
				// Defer table existence check until a cold cache miss occurs.
				if ( ! RedirectTable::exists() ) {
					return;
				}

				$rule = $this->matcher->match( $path );

				if ( null !== $rule ) {
					$this->cache->set( $path, $rule );
				}
			}
		} catch ( \Throwable $throwable ) {
			// A redirect is never load bearing. A failed cache read or matcher
			// lookup must fail open so the page renders normally, with the
			// failure logged rather than swallowed.
			$this->logFailure( 'match', $throwable );

			return;
		}

		if ( null === $rule || ! $this->isUsable( $rule ) ) {
			return;
		}

		$code        = (string) ( $rule['code'] ?? '301' );
		$destination = $this->buildDestination( $rule, $rawUri );

		if ( '' === $destination && ! in_array( $code, Normalizer::TERMINAL_CODES, true ) ) {
			return;
		}

		$hopMarker = $this->hopMarkerName( (string) ( $rule['source_hash'] ?? '' ), $destination );

		// Request time hop guard. The self::$sent static is per process, so it
		// cannot bound a loop across requests. A capture or regex loop is never
		// provable at save time, because the validator reports every dynamic
		// target and every regex rule as inconclusive. So the guard runs here,
		// keyed on the exact source_hash to destination pair, and answers 410
		// when the same pair is asked for again inside the marker window.
		if ( '' !== $hopMarker && $this->hasHopMarker( $hopMarker ) ) {
			self::$sent = true;

			do_action( 'rankkernel/redirect/loop' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, follows the rankkernel/redirect slash namespaced diagnostics.

			$this->sendTerminal( '410' );

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

		$this->markHop( $hopMarker );

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- binding plan requires wp_redirect here, the destination passed through DestinationValidator with the scheme allowlist plus the external host allowlist before sending.
		wp_redirect( $destination, (int) $code );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Log a lookup failure without letting logging become a second failure.
	 *
	 * Follows the plugin failure convention: a diagnostic action plus a
	 * warning, both guarded so they never run when WordPress is absent.
	 *
	 * @param string     $stage     Failing stage identifier.
	 * @param \Throwable $throwable Captured failure.
	 */
	private function logFailure( string $stage, \Throwable $throwable ): void {
		if ( function_exists( 'do_action' ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
			do_action( 'rankkernel/redirect/failed', $stage, $throwable );
		}

		if ( function_exists( 'wp_trigger_error' ) ) {
			wp_trigger_error( __METHOD__, $throwable->getMessage(), E_USER_WARNING );
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
	 * @return string[] The result.
	 */
	private function allowedHosts(): array {
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- filter name follows the plugin slash namespaced convention used by the rankkernel/sitemap hooks.
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
	 * Cookie name prefix for the hop marker.
	 *
	 * @var string
	 */
	private const HOP_COOKIE = 'rankkernel_rh_';

	/**
	 * Hop marker lifetime in seconds.
	 *
	 * Short on purpose. The marker only has to survive one redirect hop, so a
	 * user who fixes the loop is never served a stale 410.
	 *
	 * @var int
	 */
	private const HOP_TTL = 60;

	/**
	 * Build the marker name for a source_hash to destination pair.
	 *
	 * An empty source_hash or destination yields an empty name, which the
	 * caller treats as "no guard available" and redirects as before.
	 *
	 * @param string $sourceHash  Rule source hash.
	 * @param string $destination Final destination.
	 * @return string Marker name or empty string.
	 */
	private function hopMarkerName( string $sourceHash, string $destination ): string {
		if ( '' === $sourceHash || '' === $destination ) {
			return '';
		}

		return self::HOP_COOKIE . substr( md5( $sourceHash . '>' . $destination ), 0, 16 );
	}

	/**
	 * Whether this pair already redirected inside the marker window.
	 *
	 * @param string $marker Marker name.
	 * @return bool True when the marker cookie is present.
	 */
	private function hasHopMarker( string $marker ): bool {
		if ( '' === $marker || ! isset( $_COOKIE[ $marker ] ) ) {
			return false;
		}

		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $marker ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- the superglobal is unslashed and sanitized on the same line, the value is only compared against a literal.

		return '1' === $value;
	}

	/**
	 * Record that this pair redirected, so a second hop can be refused.
	 *
	 * @param string $marker Marker name.
	 */
	private function markHop( string $marker ): void {
		if ( '' === $marker || headers_sent() ) {
			return;
		}

		self::$markedHops[] = $marker;

		if ( ! function_exists( 'setcookie' ) ) {
			return;
		}

		$options = [
			'expires'  => time() + self::HOP_TTL,
			'path'     => '/',
			'secure'   => function_exists( 'is_ssl' ) && is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- setcookie returns false on failure and the guard is advisory, never load bearing, so a failure is ignored rather than warned.
		@setcookie( $marker, '1', $options );
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
