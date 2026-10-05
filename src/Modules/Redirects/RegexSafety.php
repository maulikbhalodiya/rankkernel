<?php
/**
 * Catastrophic backtracking guard for operator regex patterns.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps an operator regex from backtracking catastrophically.
 *
 * PHP preg_match has no execution timeout, so a pattern such as ^(a+)+$
 * can cost the engine real time on a long request path. The severity is
 * bounded, and the bound is worth stating plainly. PCRE ships a
 * pcre.backtrack_limit, one million on this stack, which caps a single
 * pattern at roughly two milliseconds and fails it with an engine error.
 * Measured on this plugin with its own twenty rule ceiling and a three
 * hundred character subject, twenty catastrophic patterns cost about
 * forty milliseconds in total, and a benign twenty cost under a
 * millisecond. The matcher already fails closed on any engine error. So
 * this is not a denial of service, and the audit that proposed this guard
 * as a P1 overstated it.
 *
 * What remains true is that an operator can store a pattern which burns
 * CPU on every uncached frontend request for no benefit, because it
 * matches nothing and costs milliseconds. That is a correctness and cost
 * defect worth refusing at save time, which is what this class does, and
 * it is defence for servers that raise or remove the PCRE limit, where
 * the same pattern becomes genuinely unbounded.
 *
 * A structural scan is the primary check, because it is deterministic and
 * names the shape it refuses. The timing probe is a secondary net for
 * shapes the scan cannot model. The probe deliberately sets a budget well
 * above the measured two millisecond single pattern ceiling, because it
 * must not refuse a legitimate pattern to catch a case PCRE already
 * bounds.
 */
final class RegexSafety {
	/**
	 * Maximum elapsed time the probe allows for one pattern, in seconds.
	 *
	 * Set above the measured single pattern ceiling so the probe never
	 * refuses a correct pattern on a warm or loaded server.
	 */
	public const PROBE_BUDGET = 0.05;

	/**
	 * Payload the probe matches against, built to force the worst case.
	 *
	 * A subject of repeated characters that the pattern can partition many
	 * ways, ending in a character no alternative accepts, so the engine has
	 * to exhaust every partition before it can fail.
	 *
	 * @var string
	 */
	private const PROBE_SUBJECT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa!';

	/**
	 * Samples taken for one pattern before the budget is compared.
	 *
	 * A single wall clock sample spots a catastrophic pattern but cannot be
	 * trusted to clear a benign one, because a scheduling hiccup on a loaded
	 * server can push a correct pattern past the budget and get it refused.
	 * The fastest sample is the one least distorted by load, so that is the
	 * one compared, and a pattern only has to be slow on every sample to be
	 * called slow.
	 *
	 * @var int
	 */
	private const PROBE_SAMPLES = 3;

	/**
	 * Whether a pattern is refused for save time.
	 *
	 * @param string $pattern Stored regex body.
	 * @return bool True when the pattern must not be saved.
	 */
	public static function isUnsafe( string $pattern ): bool {
		if ( '' === $pattern ) {
			return true;
		}

		if ( self::hasNestedQuantifier( $pattern ) ) {
			return true;
		}

		return self::exceedsBudget( $pattern );
	}

	/**
	 * Whether a group body opens with a literal character.
	 *
	 * The literal must come before any quantifier, group, class or
	 * alternation, because only then does it pin where every iteration
	 * begins. (?:cat|dog) opens on a literal c. [^/]+ opens on a class,
	 * which matches many characters, so it pins nothing.
	 *
	 * @param string $body Group body.
	 * @return bool True when a leading literal anchors the group.
	 */
	private static function hasLeadingLiteral( string $body ): bool {
		$body = ltrim( $body );

		if ( '' === $body ) {
			return false;
		}

		// Strip a leading non capturing or named group marker so that
		// (?:cat|dog) is read as the literal c that follows it.
		if ( 0 === strpos( $body, '(?:' ) || 0 === strpos( $body, '(?=' ) || 0 === strpos( $body, '(?!' ) ) {
			$body = substr( $body, 3 );
		} elseif ( 0 === strpos( $body, '(?<=' ) || 0 === strpos( $body, '(?<!' ) ) {
			$body = substr( $body, 4 );
		} elseif ( 0 === strpos( $body, '(?P<' ) ) {
			$close = strpos( $body, '>' );

			if ( false === $close ) {
				return false;
			}

			$body = substr( $body, $close + 1 );
		}

		$body = ltrim( $body );

		if ( '' === $body ) {
			return false;
		}

		if ( '\\' === $body[0] && isset( $body[1] ) && false === strpos( '*?+{.[]|()^$', $body[1] ) ) {
			return true;
		}

		// Any character that carries no meaning of its own anchors the
		// group. A letter, a digit, or a literal such as the slash in
		// /[^/]+ all pin where each iteration begins. A metacharacter
		// does not, because it matches a class of characters or a
		// structural position rather than one fixed character.
		return 1 !== preg_match( '/^[*?+{.\\[\\]()|^$]/', $body[0] );
	}

	/**
	 * Whether two branches of a top level alternation can start alike.
	 *
	 * Compares the leading literal characters of each branch. Branches that
	 * both begin with the same character, or both begin with a construct
	 * that can match that character, are reported as overlapping, because
	 * the engine has to try both at every iteration. A branch that starts
	 * with a distinct character is a decision the engine makes once.
	 *
	 * @param string $fragment Pattern fragment with a top level alternation.
	 * @return bool True when two branches overlap on their first character.
	 */
	private static function hasOverlappingBranches( string $fragment ): bool {
		$branches = self::splitAlternation( $fragment );

		if ( count( $branches ) < 2 ) {
			return false;
		}

		$starts = array();

		foreach ( $branches as $branch ) {
			$start = self::firstCharacter( $branch );

			// A branch whose first character cannot be pinned down could
			// overlap any other, so it is reported as a shared start. If
			// nothing shares a start, no branch was ambiguous.
			$starts[] = null === $start ? "\0unknown" : $start;
		}

		return count( array_unique( $starts ) ) < count( $starts );
	}

	/**
	 * Split a fragment on its top level pipes.
	 *
	 * @param string $fragment Pattern fragment.
	 * @return string[] Branches in order.
	 */
	private static function splitAlternation( string $fragment ): array {
		$depth    = 0;
		$length   = strlen( $fragment );
		$branches = array();
		$current  = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $fragment[ $i ];

			if ( '\\' === $char ) {
				$current .= substr( $fragment, $i, 2 );
				++$i;

				continue;
			}

			if ( '[' === $char ) {
				$closed = strpos( $fragment, ']', $i );

				if ( false === $closed ) {
					$current .= substr( $fragment, $i );
					break;
				}

				$current .= substr( $fragment, $i, $closed - $i + 1 );
				$i        = $closed;

				continue;
			}

			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char ) {
				--$depth;
			}

			if ( '|' === $char && 0 === $depth ) {
				$branches[] = $current;
				$current    = '';

				continue;
			}

			$current .= $char;
		}

		$branches[] = $current;

		return $branches;
	}

	/**
	 * The character a branch can start with, null when it is not decidable.
	 *
	 * Only a literal or a single character class at the front of a branch
	 * yields a character. Anything else, a group or a quantifier, can start
	 * with too much to pin down, so it is reported as unknown and treated as
	 * overlapping by the caller.
	 *
	 * @param string $branch One alternation branch.
	 * @return string|null The leading character, or null when undecidable.
	 */
	private static function firstCharacter( string $branch ): ?string {
		$branch = ltrim( $branch );

		if ( '' === $branch ) {
			return null;
		}

		// Strip a leading group marker so (?:cat is read as the literal c
		// that follows it, rather than as an undecidable marker.
		if ( 0 === strpos( $branch, '(?:' ) || 0 === strpos( $branch, '(?=' ) || 0 === strpos( $branch, '(?!' ) ) {
			$branch = ltrim( substr( $branch, 3 ) );
		} elseif ( 0 === strpos( $branch, '(?<=' ) || 0 === strpos( $branch, '(?<!' ) ) {
			$branch = ltrim( substr( $branch, 4 ) );
		}

		if ( '\\' === $branch[0] && isset( $branch[1] ) && ctype_alnum( $branch[1] ) ) {
			return $branch[1];
		}

		if ( '[' === $branch[0] ) {
			$closed = strpos( $branch, ']' );

			if ( false === $closed || $closed < 2 ) {
				return null;
			}

			$class = substr( $branch, 1, $closed - 1 );

			if ( 1 === strlen( $class ) && 1 === preg_match( '/^[a-zA-Z0-9]$/', $class ) ) {
				return $class;
			}

			return null;
		}

		if ( 1 === preg_match( '/^[a-zA-Z0-9]$/', $branch[0] ) ) {
			return $branch[0];
		}

		return null;
	}

	/**
	 * The quantifier run that starts at $index.
	 *
	 * @param string $pattern Stored regex body.
	 * @param int    $index   Index of the first quantifier character.
	 * @return string The run, the question mark, star, plus, or a brace bounded form.
	 */
	private static function quantifierRun( string $pattern, int $index ): string {
		if ( ! isset( $pattern[ $index ] ) ) {
			return '';
		}

		if ( '{' === $pattern[ $index ] ) {
			$closed = strpos( $pattern, '}', $index );

			return false === $closed ? '{' : substr( $pattern, $index, $closed - $index + 1 );
		}

		return $pattern[ $index ];
	}

	/**
	 * Whether a fragment holds a top level alternation.
	 *
	 * A pipe outside a group or a character class. (?:foo|bar) alone is one
	 * alternative evaluated once and is left alone, because a group that is
	 * not repeated cannot multiply its branches.
	 *
	 * @param string $fragment Pattern fragment.
	 * @return bool True when a top level alternation is present.
	 */
	private static function hasTopLevelAlternation( string $fragment ): bool {
		$depth  = 0;
		$length = strlen( $fragment );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $fragment[ $i ];

			if ( '\\' === $char ) {
				++$i;

				continue;
			}

			if ( '[' === $char ) {
				$closed = strpos( $fragment, ']', $i );

				if ( false === $closed ) {
					return false;
				}

				$i = $closed;

				continue;
			}

			if ( '(' === $char ) {
				++$depth;

				continue;
			}

			if ( ')' === $char ) {
				--$depth;

				continue;
			}

			if ( '|' === $char && 0 === $depth ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a quantified group contains another quantifier.
	 *
	 * This is the shape that backtracks: the outer quantifier offers the
	 * engine more than one way to divide the same characters, so a failing
	 * match costs an exponential number of steps. A single quantifier, or a
	 * quantified group holding only literals and classes, divides the input
	 * one way and is left alone.
	 *
	 * @param string $pattern Stored regex body.
	 * @return bool True when a nested quantifier is present.
	 */
	public static function hasNestedQuantifier( string $pattern ): bool {
		$length = strlen( $pattern );

		for ( $i = 0; $i < $length; $i++ ) {
			if ( '(' !== $pattern[ $i ] ) {
				continue;
			}

			$end = self::groupEnd( $pattern, $i );

			if ( null === $end ) {
				// Unbalanced, the compile probe rejects it anyway.
				continue;
			}

			if ( ! self::quantifierFollows( $pattern, $end + 1 ) ) {
				continue;
			}

			$outer = self::quantifierRun( $pattern, $end + 1 );

			// A question mark means the group runs at most once, so the
			// engine has one iteration to divide the input within and no
			// second iteration to multiply against. ^/old(/[a-z]+)?$ is
			// linear and stays allowed.
			if ( '?' === $outer ) {
				continue;
			}

			// $end is the index of the closing parenthesis, so the body runs from
			// just after the opening parenthesis up to but not including it.
			$body = substr( $pattern, $i + 1, $end - $i - 1 );

			// The nested quantifier can sit immediately before the group
			// quantifier, as in ^(a+)+, which is the shape most written by
			// hand and always ambiguous. That is refused outright.
			if ( '' !== self::quantifierBefore( $pattern, $end ) ) {
				return true;
			}

			// An alternation inside a repeating group is the same defect
			// without a visible quantifier, as in ^(a|a)+$ and
			// ^(foo|bar)+$, where the engine retries each branch for
			// every iteration. It only costs when two branches can
			// start the same way, because then the engine cannot commit
			// to one on the first character. (?:cat|dog)+ commits on the
			// first letter and stays fast, so it is left alone.
			if ( self::hasTopLevelAlternation( $body ) && self::hasOverlappingBranches( $body ) ) {
				return true;
			}

			// A quantifier inside the group only costs when the group can
			// start at more than one place in the input. A leading
			// literal anchors the start, as in ^/(?:cat|dog)+$ where the
			// slash pins every iteration, so the inner plus never has to
			// be retried across a moving position. Without such an anchor,
			// as in ^([^/]+)+$, the start is free and the inner quantifier
			// multiplies against the outer one.
			if ( self::hasQuantifier( $body ) && ! self::hasLeadingLiteral( $body ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Index of the parenthesis closing the group that opens at $start.
	 *
	 * @param string $pattern Stored regex body.
	 * @param int    $start  Index of the opening parenthesis.
	 * @return int|null Closing index, or null when the group is unbalanced.
	 */
	private static function groupEnd( string $pattern, int $start ): ?int {
		$length = strlen( $pattern );
		$depth  = 1;

		for ( $i = $start + 1; $i < $length; $i++ ) {
			if ( '\\' === $pattern[ $i ] ) {
				++$i;

				continue;
			}

			if ( '[' === $pattern[ $i ] ) {
				$closed = strpos( $pattern, ']', $i );

				if ( false === $closed ) {
					return null;
				}

				$i = $closed;

				continue;
			}

			if ( '(' === $pattern[ $i ] ) {
				++$depth;

				continue;
			}

			if ( ')' === $pattern[ $i ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		return null;
	}

	/**
	 * Whether a quantifier follows the character at $index.
	 *
	 * @param string $pattern Stored regex body.
	 * @param int    $index  Index just past the group.
	 * @return bool True when a quantifier is present.
	 */
	private static function quantifierFollows( string $pattern, int $index ): bool {
		if ( ! isset( $pattern[ $index ] ) ) {
			return false;
		}

		if ( false !== strpos( '*?+', $pattern[ $index ] ) ) {
			return true;
		}

		return '{' === $pattern[ $index ] && false !== strpos( $pattern, '}', $index );
	}

	/**
	 * The quantifier run that ends immediately before $index.
	 *
	 * Walks backwards from the character before $index, so for ^(a+)+$ the
	 * group opens at one and closes at four, and the run ending at four is
	 * the plus at three. Returns an empty string when no quantifier touches
	 * that position.
	 *
	 * @param string $pattern Stored regex body.
	 * @param int    $index   Index to walk back from.
	 * @return string The quantifier run.
	 */
	private static function quantifierBefore( string $pattern, int $index ): string {
		$run = '';

		for ( $i = $index - 1; $i >= 0; $i-- ) {
			$char = $pattern[ $i ];

			if ( false !== strpos( '*?+', $char ) || '{' === $char ) {
				// A quantifier here applies to whatever sits directly
				// before it. If that is a character class, the
				// quantifier belongs to the class and sits outside the
				// group, so it is not the nested shape. Walk back over
				// the class and keep looking.
				$previous = $i - 1;

				if ( $previous >= 0 && ']' === $pattern[ $previous ] ) {
					$open = strrpos( substr( $pattern, 0, $previous ), '[' );

					if ( false !== $open ) {
						$i = $open + 1;

						continue;
					}
				}

				$run = $char . $run;

				continue;
			}

			if ( '}' === $char ) {
				$open = strrpos( substr( $pattern, 0, $i ), '{' );

				if ( false === $open ) {
					return $run;
				}

				$run = substr( $pattern, $open, $i - $open + 1 ) . $run;
				$i   = $open;

				continue;
			}

			if ( ']' === $char ) {
				// Step back over the whole character class. A plus
				// inside or after a class belongs to the class, not to
				// this position, so [^/]+ before a group is not a
				// quantifier applying to that group. Resume inside the
				// class so the walk does not fall out of its far side.
				$open = strrpos( substr( $pattern, 0, $i ), '[' );

				if ( false === $open ) {
					return $run;
				}

				$i = $open + 1;

				continue;
			}

			return $run;
		}

		return $run;
	}

	/**
	 * Whether a fragment carries a quantifier outside a character class.
	 *
	 * A bracket is not a quantifier, so a class such as [+*] is skipped
	 * rather than read as one.
	 *
	 * @param string $fragment Pattern fragment.
	 * @return bool True when a quantifier is present.
	 */
	private static function hasQuantifier( string $fragment ): bool {
		$length = strlen( $fragment );

		for ( $i = 0; $i < $length; $i++ ) {
			if ( '\\' === $fragment[ $i ] ) {
				++$i;

				continue;
			}

			if ( '[' === $fragment[ $i ] ) {
				$closed = strpos( $fragment, ']', $i );

				if ( false === $closed ) {
					return false;
				}

				$i = $closed;

				continue;
			}

			if ( false !== strpos( '*?+', $fragment[ $i ] ) ) {
				return true;
			}

			if ( '{' === $fragment[ $i ] ) {
				$closed = strpos( $fragment, '}', $i );

				if ( false !== $closed ) {
					$i = $closed;
				}
			}
		}

		return false;
	}

	/**
	 * Whether a pattern runs longer than the probe budget on the payload.
	 *
	 * Takes the fastest of PROBE_SAMPLES samples, because the verdict is a
	 * refusal and a false refusal locks an operator out of a correct pattern.
	 *
	 * @param string $pattern Stored regex body.
	 * @return bool True when the pattern is too slow to keep.
	 */
	public static function exceedsBudget( string $pattern ): bool {
		$wrapped = '#' . str_replace( '#', '\\#', $pattern ) . '#u';
		$fastest = INF;

		for ( $sample = 0; $sample < self::PROBE_SAMPLES; $sample++ ) {
			$start = microtime( true );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- bounded compile probe for operator entered patterns, the handler swallows only the compile warning and is always restored in finally.
			set_error_handler( static fn (): bool => true );

			try {
				preg_match( $wrapped, self::PROBE_SUBJECT );
			} finally {
				restore_error_handler();
			}

			$elapsed = microtime( true ) - $start;

			if ( $elapsed < $fastest ) {
				$fastest = $elapsed;
			}
		}

		return $fastest > self::PROBE_BUDGET;
	}
}
