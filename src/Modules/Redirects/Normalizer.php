<?php
/**
 * Shared redirect path normalizer and hasher.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * Single shared normalizer for redirect sources, lookups, cache keys, and safety checks.
 *
 * Every path that enters the redirect system (rule source at create time, request
 * path at dispatch time, loop and chain targets, CSV rows, cache keys) passes
 * through here first, so hashing and comparison stay consistent everywhere.
 * Regex rule sources are the one exception: a pattern body is stored verbatim
 * (trimmed only) because path normalization would corrupt it by cutting at
 * question marks, collapsing slashes, or forcing a leading slash.
 */
final class Normalizer {
	/**
	 * Supported match types.
	 *
	 * @var string[]
	 */
	public const MATCH_TYPES = [ 'exact', 'prefix', 'contains', 'suffix', 'wildcard', 'regex' ];

	/**
	 * Supported status codes.
	 *
	 * @var string[]
	 */
	public const CODES = [ '301', '302', '307', '410', '451' ];

	/**
	 * Terminal codes, they carry no outgoing edge.
	 *
	 * @var string[]
	 */
	public const TERMINAL_CODES = [ '410', '451' ];

	/**
	 * Normalize a raw source or request path to its canonical form.
	 *
	 * Steps in order: trim, extract the path from full URLs, drop query and
	 * fragment, collapse duplicate slashes, canonicalize percent encoding,
	 * fold Unicode to NFC, strip the subdirectory home path, enforce one
	 * leading slash, trim the trailing slash except for root, map empty to
	 * root, lowercase only when case insensitive.
	 *
	 * @param string $raw             Raw path or URL.
	 * @param bool   $caseInsensitive Whether to lowercase the result.
	 * @return string Canonical path, always starting with a slash.
	 */
	public static function normalize( string $raw, bool $caseInsensitive = false ): string {
		$path = trim( $raw );

		if ( '' === $path ) {
			return '/';
		}

		if ( '/' === substr( $path, 0, 1 ) ) {
			$cut  = strcspn( $path, '?#' );
			$path = substr( $path, 0, $cut );
		} else {
			$parts = wp_parse_url( $path );

			if ( is_array( $parts ) && isset( $parts['path'] ) && '' !== $parts['path'] ) {
				$path = $parts['path'];
			} elseif ( is_array( $parts ) && isset( $parts['host'] ) ) {
				$path = '/';
			} else {
				$cut  = strcspn( $path, '?#' );
				$path = substr( $path, 0, $cut );
			}
		}

		$collapsed = preg_replace( '#/+#', '/', $path );
		$path      = is_string( $collapsed ) ? $collapsed : $path;
		$path      = self::canonicalizeEncoding( $path );
		$path      = self::foldUnicode( $path );
		$path      = self::stripHomePath( $path );

		if ( '' === $path || '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . ltrim( $path, '/' );
		}

		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
		}

		if ( '' === $path ) {
			$path = '/';
		}

		if ( $caseInsensitive ) {
			$path = function_exists( 'mb_strtolower' ) ? mb_strtolower( $path, 'UTF-8' ) : strtolower( $path );
		}

		return $path;
	}

	/**
	 * Normalize a rule source according to its matcher.
	 *
	 * String matchers share normalize(). Regex bodies are kept verbatim apart
	 * from trimming, so characters with pattern meaning survive storage and
	 * the matcher compiles exactly what the administrator entered.
	 *
	 * @param string $raw       Raw source as entered.
	 * @param string $matchType Matcher name.
	 * @return string Storage form of the source.
	 */
	public static function normalizeSource( string $raw, string $matchType = 'exact' ): string {
		if ( 'regex' === $matchType ) {
			return trim( $raw );
		}

		return self::normalize( $raw );
	}

	/**
	 * Hash a normalized path for indexed lookup.
	 *
	 * The hash covers match type plus the casefolded path, path only, so the
	 * query string never affects identity and identical paths under different
	 * matchers stay distinct rows.
	 *
	 * @param string $matchType       Matcher name.
	 * @param string $normalizedPath  Canonical path from normalize().
	 * @param bool   $caseInsensitive Whether the rule folds case.
	 * @return string SHA256 hex digest.
	 */
	public static function hash( string $matchType, string $normalizedPath, bool $caseInsensitive = false ): string {
		$folded = $caseInsensitive ? strtolower( $normalizedPath ) : $normalizedPath;

		return hash( 'sha256', $matchType . '|' . $folded );
	}

	/**
	 * Whether a normalized path is blocked as a redirect source.
	 *
	 * The homepage and the bare domain both normalize to root, and root as a
	 * source would lock visitors out, so it is always rejected at create time
	 * and always skipped at dispatch time.
	 *
	 * @param string $normalizedPath Canonical path.
	 * @return bool True when the path must never be a source.
	 */
	public static function isBlockedSource( string $normalizedPath ): bool {
		return '/' === $normalizedPath;
	}

	/**
	 * Whether a string is a supported match type.
	 *
	 * @param string $matchType Candidate matcher name.
	 * @return bool True for a known matcher.
	 */
	public static function isMatchType( string $matchType ): bool {
		return in_array( $matchType, self::MATCH_TYPES, true );
	}

	/**
	 * Whether a string is a supported status code.
	 *
	 * @param string $code Candidate code.
	 * @return bool True for a known code.
	 */
	public static function isCode( string $code ): bool {
		return in_array( $code, self::CODES, true );
	}

	/**
	 * Subdirectory home path, derived from home_url parsing.
	 *
	 * Returns an empty string for root installs and for unparseable values.
	 *
	 * @return string Home path like /blog, or empty when none applies.
	 */
	public static function homePath(): string {
		if ( ! function_exists( 'home_url' ) ) {
			return '';
		}

		$home = home_url( '/' );

		if ( ! is_string( $home ) || '' === $home ) {
			return '';
		}

		$parts = wp_parse_url( $home );

		if ( ! is_array( $parts ) || ! isset( $parts['path'] ) ) {
			return '';
		}

		$base = '/' . trim( $parts['path'], '/' );

		return '/' === $base ? '' : $base;
	}

	/**
	 * Canonicalize percent encoding: uppercase hex, decode unreserved bytes.
	 *
	 * Unreserved characters (letters, digits, hyphen, underscore, dot, tilde)
	 * are stored decoded. Reserved bytes stay encoded with uppercase hex so
	 * equivalent spellings hash identically.
	 *
	 * @param string $path Path with raw percent sequences.
	 * @return string Path with canonical encoding.
	 */
	private static function canonicalizeEncoding( string $path ): string {
		$decoded = preg_replace_callback(
			'/%([0-9a-fA-F]{2})/',
			static function ( array $matches ): string {
				$byte = chr( (int) hexdec( $matches[1] ) );

				if ( 1 === preg_match( '/[A-Za-z0-9\-_.~]/', $byte ) ) {
					return $byte;
				}

				return '%' . strtoupper( $matches[1] );
			},
			$path
		);

		return is_string( $decoded ) ? $decoded : $path;
	}

	/**
	 * Fold Unicode to NFC so equivalent spellings hash over the same bytes.
	 *
	 * Uses the intl normalizer when available, otherwise keeps the input bytes
	 * untouched. Hashing always runs over the resulting raw bytes.
	 *
	 * @param string $path Raw path bytes.
	 * @return string NFC folded path, or the input when intl is absent.
	 */
	private static function foldUnicode( string $path ): string {
		if ( class_exists( \Normalizer::class ) && method_exists( \Normalizer::class, 'normalize' ) ) {
			$folded = \Normalizer::normalize( $path, \Normalizer::FORM_C );

			if ( is_string( $folded ) ) {
				return $folded;
			}
		}

		return $path;
	}

	/**
	 * Strip the subdirectory home path prefix from a normalized path.
	 *
	 * @param string $path Path starting with a slash.
	 * @return string Path relative to the home directory.
	 */
	private static function stripHomePath( string $path ): string {
		$home = self::homePath();

		if ( '' === $home ) {
			return $path;
		}

		if ( $path === $home || str_starts_with( $path, $home . '/' ) ) {
			$stripped = substr( $path, strlen( $home ) );

			return '' === $stripped ? '/' : $stripped;
		}

		return $path;
	}
}
