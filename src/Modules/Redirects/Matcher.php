<?php
/**
 * Fixed precedence redirect matcher.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * Matches a normalized request path against redirect rules in fixed precedence.
 *
 * Precedence, highest first:
 * 1. exact, hash equality on the normalized path, one indexed lookup.
 * 2. prefix, longest source wins, plain string prefix comparison.
 * 3. wildcard, single glob translated to a bounded expression, never raw regex.
 * 4. contains, plain substring search.
 * 5. suffix, plain end of string comparison.
 * 6. regex, last, capped count and capped length, fail closed on error.
 * Lowest id breaks every remaining tie. There is no stored priority column,
 * specificity plus id keeps the outcome deterministic.
 *
 * Query strings are ignored during matching, path only. Inactive rules never
 * win. Regex runs last so a cheap string rule always beats an expensive one.
 */
final class Matcher {
	/**
	 * Maximum regex rules evaluated per request.
	 */
	public const MAX_REGEX_RULES = 20;

	/**
	 * Maximum regex source length in characters.
	 */
	public const MAX_REGEX_LENGTH = 200;

	/**
	 * Rule repository for database backed matching.
	 *
	 * @var RedirectRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepository $repository Rule repository.
	 */
	public function __construct( $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Match a path: exact via one indexed lookup, then patterns in memory.
	 *
	 * @param string $path Raw or normalized request path.
	 * @return array<string, mixed>|null Winning rule row, or null.
	 */
	public function match( string $path ): ?array {
		$path = Normalizer::normalize( $path );

		if ( Normalizer::isBlockedSource( $path ) ) {
			return null;
		}

		$exact = $this->repository->lookup( $path );

		if ( null !== $exact && 1 === (int) ( $exact['is_active'] ?? 0 ) ) {
			return $exact;
		}

		$patterns = $this->repository->all_patterns();

		if ( [] === $patterns ) {
			return null;
		}

		return self::pick_winner( $path, $patterns );
	}

	/**
	 * Pick the winning rule from an in memory rule list.
	 *
	 * Pure function over the given rows, used by match() and covered directly
	 * by precedence tests without a database.
	 *
	 * @param string            $path  Request path, normalized inside.
	 * @param array<int, mixed> $rules Candidate rule rows.
	 * @return array<string, mixed>|null Winning rule, or null.
	 */
	public static function pick_winner( string $path, array $rules ): ?array {
		$path = Normalizer::normalize( $path );

		if ( Normalizer::isBlockedSource( $path ) ) {
			return null;
		}

		$active = [];

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			if ( 1 !== (int) ( $rule['is_active'] ?? 0 ) ) {
				continue;
			}

			$active[] = $rule;
		}

		if ( [] === $active ) {
			return null;
		}

		$winner = self::pick_exact( $path, $active );

		if ( null !== $winner ) {
			return $winner;
		}

		$winner = self::pick_prefix( $path, $active );

		if ( null !== $winner ) {
			return $winner;
		}

		$winner = self::pick_wildcard( $path, $active );

		if ( null !== $winner ) {
			return $winner;
		}

		$winner = self::pick_contains( $path, $active );

		if ( null !== $winner ) {
			return $winner;
		}

		$winner = self::pick_suffix( $path, $active );

		if ( null !== $winner ) {
			return $winner;
		}

		return self::pick_regex( $path, $active );
	}

	/**
	 * Whether a single rule matches a normalized path.
	 *
	 * Shared edge test for loop and chain traversal, so admin analysis uses
	 * exactly the same semantics as frontend dispatch.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @param string               $path Normalized path.
	 * @return bool True when the rule matches.
	 */
	public static function rule_matches( array $rule, string $path ): bool {
		if ( 1 !== (int) ( $rule['is_active'] ?? 0 ) ) {
			return false;
		}

		$type   = (string) ( $rule['match_type'] ?? 'exact' );
		$source = (string) ( $rule['source'] ?? '' );

		if ( '' === $source ) {
			return false;
		}

		switch ( $type ) {
			case 'exact':
				return $path === $source;
			case 'prefix':
				return str_starts_with( $path, $source );
			case 'wildcard':
				return 1 === preg_match( self::wildcard_to_regex( $source ), $path );
			case 'contains':
				return false !== strpos( $path, $source );
			case 'suffix':
				return str_ends_with( $path, $source );
			case 'regex':
				return self::regex_matches( $source, $path );
			default:
				return false;
		}
	}

	/**
	 * Exact tier, lowest id wins.
	 *
	 * @param string            $path  Normalized path.
	 * @param array<int, mixed> $rules Active rule rows.
	 * @return array<string, mixed>|null Winner or null.
	 */
	private static function pick_exact( string $path, array $rules ): ?array {
		$winner = null;
		$bestId = PHP_INT_MAX;

		foreach ( $rules as $rule ) {
			if ( 'exact' !== (string) ( $rule['match_type'] ?? '' ) ) {
				continue;
			}

			if ( (string) ( $rule['source'] ?? '' ) !== $path ) {
				continue;
			}

			$id = (int) ( $rule['id'] ?? PHP_INT_MAX );

			if ( $id < $bestId ) {
				$bestId = $id;
				$winner = $rule;
			}
		}

		return $winner;
	}

	/**
	 * Prefix tier, longest source wins, lowest id breaks ties.
	 *
	 * @param string            $path  Normalized path.
	 * @param array<int, mixed> $rules Active rule rows.
	 * @return array<string, mixed>|null Winner or null.
	 */
	private static function pick_prefix( string $path, array $rules ): ?array {
		$winner  = null;
		$bestLen = -1;
		$bestId  = PHP_INT_MAX;

		foreach ( $rules as $rule ) {
			if ( 'prefix' !== (string) ( $rule['match_type'] ?? '' ) ) {
				continue;
			}

			$source = (string) ( $rule['source'] ?? '' );

			if ( '' === $source || ! str_starts_with( $path, $source ) ) {
				continue;
			}

			$id  = (int) ( $rule['id'] ?? PHP_INT_MAX );
			$len = strlen( $source );

			if ( $len > $bestLen || ( $len === $bestLen && $id < $bestId ) ) {
				$bestLen = $len;
				$bestId  = $id;
				$winner  = $rule;
			}
		}

		return $winner;
	}

	/**
	 * Wildcard tier, lowest id wins.
	 *
	 * @param string            $path  Normalized path.
	 * @param array<int, mixed> $rules Active rule rows.
	 * @return array<string, mixed>|null Winner or null.
	 */
	private static function pick_wildcard( string $path, array $rules ): ?array {
		$winner = null;
		$bestId = PHP_INT_MAX;

		foreach ( $rules as $rule ) {
			if ( 'wildcard' !== (string) ( $rule['match_type'] ?? '' ) ) {
				continue;
			}

			$source = (string) ( $rule['source'] ?? '' );

			if ( '' === $source ) {
				continue;
			}

			if ( 1 !== preg_match( self::wildcard_to_regex( $source ), $path ) ) {
				continue;
			}

			$id = (int) ( $rule['id'] ?? PHP_INT_MAX );

			if ( $id < $bestId ) {
				$bestId = $id;
				$winner = $rule;
			}
		}

		return $winner;
	}

	/**
	 * Contains tier, lowest id wins.
	 *
	 * @param string            $path  Normalized path.
	 * @param array<int, mixed> $rules Active rule rows.
	 * @return array<string, mixed>|null Winner or null.
	 */
	private static function pick_contains( string $path, array $rules ): ?array {
		$winner = null;
		$bestId = PHP_INT_MAX;

		foreach ( $rules as $rule ) {
			if ( 'contains' !== (string) ( $rule['match_type'] ?? '' ) ) {
				continue;
			}

			$source = (string) ( $rule['source'] ?? '' );

			if ( '' === $source || false === strpos( $path, $source ) ) {
				continue;
			}

			$id = (int) ( $rule['id'] ?? PHP_INT_MAX );

			if ( $id < $bestId ) {
				$bestId = $id;
				$winner = $rule;
			}
		}

		return $winner;
	}

	/**
	 * Suffix tier, lowest id wins.
	 *
	 * @param string            $path  Normalized path.
	 * @param array<int, mixed> $rules Active rule rows.
	 * @return array<string, mixed>|null Winner or null.
	 */
	private static function pick_suffix( string $path, array $rules ): ?array {
		$winner = null;
		$bestId = PHP_INT_MAX;

		foreach ( $rules as $rule ) {
			if ( 'suffix' !== (string) ( $rule['match_type'] ?? '' ) ) {
				continue;
			}

			$source = (string) ( $rule['source'] ?? '' );

			if ( '' === $source || ! str_ends_with( $path, $source ) ) {
				continue;
			}

			$id = (int) ( $rule['id'] ?? PHP_INT_MAX );

			if ( $id < $bestId ) {
				$bestId = $id;
				$winner = $rule;
			}
		}

		return $winner;
	}

	/**
	 * Regex tier, last resort, lowest id wins within the cap.
	 *
	 * Only the first MAX_REGEX_RULES regex rules by id are evaluated, longer
	 * patterns are skipped, compile failures and engine errors fail closed.
	 *
	 * @param string            $path  Normalized path.
	 * @param array<int, mixed> $rules Active rule rows.
	 * @return array<string, mixed>|null Winner or null.
	 */
	private static function pick_regex( string $path, array $rules ): ?array {
		$candidates = [];

		foreach ( $rules as $rule ) {
			if ( 'regex' !== (string) ( $rule['match_type'] ?? '' ) ) {
				continue;
			}

			$candidates[] = $rule;
		}

		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				return (int) ( $a['id'] ?? PHP_INT_MAX ) <=> (int) ( $b['id'] ?? PHP_INT_MAX );
			}
		);

		$candidates = array_slice( $candidates, 0, self::MAX_REGEX_RULES );

		foreach ( $candidates as $rule ) {
			$source = (string) ( $rule['source'] ?? '' );

			if ( self::regex_matches( $source, $path ) ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Translate a single glob to a bounded anchored expression.
	 *
	 * Star matches any run, question mark matches one character, everything
	 * else is quoted, so user input can never inject raw regex.
	 *
	 * @param string $glob Glob source like /old/*.
	 * @return string Anchored expression.
	 */
	private static function wildcard_to_regex( string $glob ): string {
		$quoted = preg_quote( $glob, '#' );

		if ( ! is_string( $quoted ) ) {
			$quoted = '';
		}

		$body = str_replace( [ '\\*', '\\?' ], [ '.*', '.' ], $quoted );

		return '#\A(?:' . $body . ')\z#u';
	}

	/**
	 * Test a stored regex body against a path, fail closed on any problem.
	 *
	 * @param string $pattern Stored regex body.
	 * @param string $path    Normalized path.
	 * @return bool True on a clean match, false otherwise.
	 */
	private static function regex_matches( string $pattern, string $path ): bool {
		if ( '' === $pattern || strlen( $pattern ) > self::MAX_REGEX_LENGTH ) {
			return false;
		}

		$wrapped = '#' . str_replace( '#', '\\#', $pattern ) . '#u';

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- bounded regex compile probe for admin entered patterns, the handler swallows only the compile warning and is always restored in finally.
		set_error_handler( static fn (): bool => true );

		try {
			$result = preg_match( $wrapped, $path );
		} finally {
			restore_error_handler();
		}

		if ( false === $result || PREG_NO_ERROR !== preg_last_error() ) {
			return false;
		}

		return 1 === $result;
	}
}
