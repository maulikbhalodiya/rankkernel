<?php
/**
 * Loop and chain detection over redirect rules.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

/**
 * Save time graph analysis, administrator context only, never on the frontend.
 *
 * A cycle is a path from a rule back to itself, for example /a to /b to /a.
 * Cycles block the save and report the full path for the notice. A chain is a
 * path that ends elsewhere, for example /a to /b to /c. Chains save with an
 * advisory warning plus the recommended final destination when the traversal
 * is deterministic. Dynamic targets (regex or wildcard captures, external
 * URLs) are inconclusive and never block.
 */
final class Validator {
	/**
	 * Maximum traversal depth for loop detection.
	 */
	public const MAX_DEPTH = 10;

	/**
	 * Maximum matching edge examinations per loop analysis.
	 *
	 * Only rules whose source actually matches the current path consume the
	 * budget. Scanning past unrelated rules stays free, so a real cycle is
	 * still found in a large rule set while a genuinely wide fan out still
	 * reports inconclusive instead of a clean pass.
	 */
	public const MAX_NODES = 50;

	/**
	 * Maximum hops followed for chain warnings.
	 */
	public const MAX_CHAIN_HOPS = 5;

	/**
	 * Detect whether saving the proposed rule would create a redirect loop.
	 *
	 * Builds the proposed rule in memory, then depth first searches from its
	 * target over the given active internal rules. An edge exists when another
	 * rule matches the current target path. Returning to any visited path is
	 * a conclusive cycle and blocks. Dynamic or external branches are marked
	 * inconclusive and stop that branch without blocking. Depth beyond
	 * MAX_DEPTH and more than MAX_NODES matching edges mark the analysis
	 * inconclusive without blocking, and never count as proof of safety.
	 *
	 * @param array<string, mixed>       $proposed Proposed rule: source, target, code, match_type.
	 * @param array<int, array<string, mixed>> $rules Active candidate rules with concrete targets.
	 * @return array{has_cycle: bool, path: string[], inconclusive: bool}
	 */
	public function detect_loop( array $proposed, array $rules ): array {
		$source = Normalizer::normalizeSource(
			(string) ( $proposed['source'] ?? '' ),
			(string) ( $proposed['match_type'] ?? 'exact' )
		);
		$code   = (string) ( $proposed['code'] ?? '301' );
		$result = [
			'has_cycle'    => false,
			'path'         => [ $source ],
			'inconclusive' => false,
		];

		if ( in_array( $code, Normalizer::TERMINAL_CODES, true ) ) {
			return $result;
		}

		$targetRaw = trim( (string) ( $proposed['target'] ?? '' ) );

		if ( '' === $targetRaw ) {
			return $result;
		}

		if ( self::is_dynamic_target( $targetRaw ) ) {
			$result['inconclusive'] = true;

			return $result;
		}

		$targetPath = self::target_path( $targetRaw );

		if ( null === $targetPath ) {
			$result['inconclusive'] = true;

			return $result;
		}

		$nodes        = 0;
		$inconclusive = false;
		$cyclePath    = null;

		$visit = null;
		$visit = function ( string $current, array $trail ) use ( &$visit, &$nodes, &$inconclusive, &$cyclePath, $rules ): bool {
			if ( count( $trail ) > self::MAX_DEPTH + 1 ) {
				$inconclusive = true;

				return false;
			}

			foreach ( $rules as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}

				if ( 1 !== (int) ( $rule['is_active'] ?? 0 ) ) {
					continue;
				}

				if ( ! Matcher::rule_matches( $rule, $current ) ) {
					continue;
				}

				++$nodes;

				if ( $nodes > self::MAX_NODES ) {
					$inconclusive = true;

					return false;
				}

				$ruleCode = (string) ( $rule['code'] ?? '301' );
				$nextRaw  = trim( (string) ( $rule['target'] ?? '' ) );

				if ( in_array( $ruleCode, Normalizer::TERMINAL_CODES, true ) || '' === $nextRaw ) {
					continue;
				}

				if ( self::is_dynamic_target( $nextRaw ) ) {
					$inconclusive = true;
					continue;
				}

				if ( 'regex' === (string) ( $rule['match_type'] ?? 'exact' ) ) {
					$inconclusive = true;
					continue;
				}

				$next = self::target_path( $nextRaw );

				if ( null === $next ) {
					$inconclusive = true;
					continue;
				}

				if ( in_array( $next, $trail, true ) ) {
					$pos       = array_search( $next, $trail, true );
					$start     = is_int( $pos ) ? $pos : 0;
					$cyclePath = array_merge( array_slice( $trail, $start ), [ $next ] );

					return true;
				}

				$extended   = array_merge( $trail, [ $next ] );
				$foundCycle = $visit( $next, $extended );

				if ( $foundCycle ) {
					return true;
				}
			}

			return false;
		};

		$found = $visit( $targetPath, [ $source, $targetPath ] );

		$result['has_cycle']    = $found;
		$result['inconclusive'] = $inconclusive;

		if ( $found && null !== $cyclePath ) {
			$result['path'] = $cyclePath;
		}

		return $result;
	}

	/**
	 * Follow the proposed rule for a chain warning, never blocking.
	 *
	 * Same traversal as loop detection, capped at MAX_CHAIN_HOPS. Chains
	 * terminate at terminal codes, external destinations, and rules with no
	 * follower. Deterministic chains report the final destination as the
	 * recommended direct target. Pattern, capture, external ambiguity, or hop
	 * cap overruns report inconclusive instead of a recommendation.
	 *
	 * @param array<string, mixed>       $proposed Proposed rule: source, target, code.
	 * @param array<int, array<string, mixed>> $rules Active candidate rules.
	 * @return array{has_chain: bool, chain: string[], final: string|null, inconclusive: bool}
	 */
	public function detect_chain( array $proposed, array $rules ): array {
		$source = Normalizer::normalizeSource(
			(string) ( $proposed['source'] ?? '' ),
			(string) ( $proposed['match_type'] ?? 'exact' )
		);
		$code   = (string) ( $proposed['code'] ?? '301' );
		$result = [
			'has_chain'    => false,
			'chain'        => [ $source ],
			'final'        => null,
			'inconclusive' => false,
		];

		if ( in_array( $code, Normalizer::TERMINAL_CODES, true ) ) {
			return $result;
		}

		$targetRaw = trim( (string) ( $proposed['target'] ?? '' ) );

		if ( '' === $targetRaw ) {
			return $result;
		}

		if ( self::is_dynamic_target( $targetRaw ) ) {
			$result['inconclusive'] = true;

			return $result;
		}

		$targetPath = self::target_path( $targetRaw );

		if ( null === $targetPath ) {
			$result['chain'] = [ $source, $targetRaw ];
			$result['final'] = $targetRaw;

			return $result;
		}

		$trail   = [ $source, $targetPath ];
		$current = $targetPath;
		$hops    = 0;

		while ( $hops < self::MAX_CHAIN_HOPS ) {
			$winner = Matcher::pick_winner( $current, $rules );

			if ( null === $winner ) {
				break;
			}

			if ( 'exact' !== (string) ( $winner['match_type'] ?? 'exact' ) ) {
				$result['chain']        = $trail;
				$result['has_chain']    = count( $trail ) >= 3;
				$result['inconclusive'] = true;

				return $result;
			}

			$winnerCode = (string) ( $winner['code'] ?? '301' );
			$nextRaw    = trim( (string) ( $winner['target'] ?? '' ) );

			if ( in_array( $winnerCode, Normalizer::TERMINAL_CODES, true ) || '' === $nextRaw ) {
				break;
			}

			if ( self::is_dynamic_target( $nextRaw ) ) {
				$result['chain']        = $trail;
				$result['has_chain']    = count( $trail ) >= 3;
				$result['final']        = null;
				$result['inconclusive'] = true;

				return $result;
			}

			$next = self::target_path( $nextRaw );

			if ( null === $next ) {
				$trail[] = $nextRaw;

				$result['chain']     = $trail;
				$result['has_chain'] = true;
				$result['final']     = $nextRaw;

				return $result;
			}

			$trail[] = $next;
			$current = $next;
			++$hops;

			if ( in_array( $current, array_slice( $trail, 0, -1 ), true ) ) {
				break;
			}
		}

		if ( $hops >= self::MAX_CHAIN_HOPS ) {
			$result['chain']        = $trail;
			$result['has_chain']    = true;
			$result['final']        = null;
			$result['inconclusive'] = true;

			return $result;
		}

		$result['chain']     = $trail;
		$result['has_chain'] = count( $trail ) >= 3;
		$result['final']     = end( $trail );

		if ( ! is_string( $result['final'] ) ) {
			$result['final'] = null;
		}

		return $result;
	}

	/**
	 * Whether a target carries dynamic content that defeats exact traversal.
	 *
	 * Capture references like $1 and backslash numbered references mark regex
	 * or wildcard capture substitution, which cannot be resolved statically.
	 *
	 * @param string $target Trimmed target.
	 * @return bool True when the target is dynamic.
	 */
	private static function is_dynamic_target( string $target ): bool {
		return 1 === preg_match( '/(\\$|\\\\[0-9])/', $target );
	}

	/**
	 * Resolve a target to an internal path, null for external or empty.
	 *
	 * Absolute URLs on the home host fold to their path. Any other host is
	 * external and returns null so the branch terminates or warns.
	 *
	 * @param string $target Trimmed target.
	 * @return string|null Internal normalized path, or null.
	 */
	private static function target_path( string $target ): ?string {
		$parts = wp_parse_url( trim( $target ) );

		if ( ! is_array( $parts ) ) {
			return null;
		}

		if ( isset( $parts['host'] ) && '' !== (string) $parts['host'] ) {
			$homeHost = self::home_host();
			$host     = strtolower( (string) $parts['host'] );

			if ( '' === $homeHost || $host !== $homeHost ) {
				return null;
			}

			$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';

			return Normalizer::normalize( '' === $path ? '/' : $path );
		}

		if ( ! isset( $parts['path'] ) || '' === (string) $parts['path'] ) {
			return null;
		}

		return Normalizer::normalize( (string) $parts['path'] );
	}

	/**
	 * Lowercase host of the home URL, empty when unavailable.
	 *
	 * @return string Home host or empty string.
	 */
	private static function home_host(): string {
		if ( ! function_exists( 'home_url' ) ) {
			return '';
		}

		$home = home_url( '/' );

		if ( ! is_string( $home ) || '' === $home ) {
			return '';
		}

		$host = wp_parse_url( $home, PHP_URL_HOST );

		if ( ! is_string( $host ) ) {
			return '';
		}

		return strtolower( $host );
	}
}
