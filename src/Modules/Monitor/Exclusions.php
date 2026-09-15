<?php
/**
 * 404 URI exclusion matching, plain string comparators only.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

defined( 'ABSPATH' ) || exit;

/**
 * Matches a normalized 404 URI against configured exclusion rules.
 *
 * Supported comparators are exact, prefix, contains, suffix, and wildcard.
 * Wildcard supports the star character only, matching any sequence including
 * an empty one. There is no regex comparator by design, so rule input can
 * never become a pattern injection. Matching is case sensitive: the URI is
 * compared byte for byte as normalized, with no case folding.
 */
final class Exclusions {
	/**
	 * Supported comparators.
	 *
	 * @var string[]
	 */
	public const COMPARATORS = [ 'exact', 'prefix', 'contains', 'suffix', 'wildcard' ];

	/**
	 * Whether a string names a supported comparator.
	 *
	 * @param string $comparator Candidate comparator name.
	 * @return bool True for a known comparator.
	 */
	public static function isComparator( string $comparator ): bool {
		return in_array( $comparator, self::COMPARATORS, true );
	}

	/**
	 * Whether any rule excludes the URI.
	 *
	 * Malformed rules are skipped, never fatal. An empty rule set excludes
	 * nothing.
	 *
	 * @param string            $uri   Normalized 404 URI.
	 * @param array<int, mixed> $rules Exclusion rules, comparator plus value pairs.
	 * @return bool True when a rule matches.
	 */
	public static function matches( string $uri, array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$comparator = isset( $rule['comparator'] ) ? (string) $rule['comparator'] : '';
			$value      = isset( $rule['value'] ) ? (string) $rule['value'] : '';

			if ( '' === $value || ! self::isComparator( $comparator ) ) {
				continue;
			}

			if ( self::matchOne( $uri, $comparator, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match one rule against the URI.
	 *
	 * @param string $uri        Normalized 404 URI.
	 * @param string $comparator Valid comparator name.
	 * @param string $value      Rule value.
	 * @return bool True on match.
	 */
	private static function matchOne( string $uri, string $comparator, string $value ): bool {
		if ( 'exact' === $comparator ) {
			return $uri === $value;
		}

		if ( 'prefix' === $comparator ) {
			return str_starts_with( $uri, $value );
		}

		if ( 'contains' === $comparator ) {
			return false !== strpos( $uri, $value );
		}

		if ( 'suffix' === $comparator ) {
			return str_ends_with( $uri, $value );
		}

		return self::matchWildcard( $uri, $value );
	}

	/**
	 * Glob match where the star matches any sequence, all else is literal.
	 *
	 * The value is split on stars, each literal part is quoted, and the parts
	 * are rejoined with a dot star equivalent. No user input ever reaches the
	 * pattern unquoted, so this cannot behave as a regex.
	 *
	 * @param string $uri     Normalized 404 URI.
	 * @param string $pattern Rule value with optional stars.
	 * @return bool True on match.
	 */
	private static function matchWildcard( string $uri, string $pattern ): bool {
		$parts  = explode( '*', $pattern );
		$quoted = [];

		foreach ( $parts as $part ) {
			$escaped = preg_quote( $part, '#' );

			if ( is_string( $escaped ) ) {
				$quoted[] = $escaped;
			}
		}

		$regex = '#^' . implode( '.*', $quoted ) . '$#';

		return 1 === preg_match( $regex, $uri );
	}
}
