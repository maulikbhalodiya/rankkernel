<?php
/**
 * Bucketing regression tests for the redirect matcher.
 *
 * Winner selection groups active rules by match_type in a single pass and then runs only
 * the tier pickers whose bucket is populated. These tests pin the behaviour that the
 * grouping is required to preserve: tier precedence, per-tier selection, original rule
 * order, lowest-id tie breaking, inactive filtering, unknown match types and the regex
 * safety caps.
 *
 * @package RankKernel
 * @license GPL-2.0-1.0
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\Matcher;
use RankKernel\Modules\Redirects\Normalizer;

/**
 * Redirects Matcher Bucketing Test.
 */
final class RedirectsMatcherBucketingTest extends TestCase {

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );

		Normalizer::resetCache();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		Normalizer::resetCache();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a rule row.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return array<string, mixed>
	 */
	private function rule( array $overrides = [] ): array {
		return array_merge(
			[
				'id'         => 1,
				'match_type' => 'exact',
				'source'     => '/old/page',
				'target'     => '/new',
				'code'       => '301',
				'hits'       => 0,
				'is_active'  => 1,
			],
			$overrides
		);
	}

	/**
	 * Winning rule id, or null when nothing matches.
	 *
	 * @param string            $path  Request path.
	 * @param array<int, mixed> $rules Rule rows.
	 * @return int|null
	 */
	private function winnerId( string $path, array $rules ): ?int {
		$winner = Matcher::pick_winner( $path, $rules );

		return null === $winner ? null : (int) ( $winner['id'] ?? 0 );
	}

	/**
	 * Every tier contributes a candidate, and the highest tier still wins.
	 *
	 * All six buckets are populated. Only the exact rule may win, which proves the
	 * grouping did not let a lower tier jump the precedence order.
	 */
	public function test_all_tiers_populated_still_resolves_to_the_exact_tier(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 61,
					'match_type' => 'regex',
					'source'     => '.+',
				]
			),
			$this->rule(
				[
					'id'         => 62,
					'match_type' => 'suffix',
					'source'     => 'page',
				]
			),
			$this->rule(
				[
					'id'         => 63,
					'match_type' => 'contains',
					'source'     => 'old',
				]
			),
			$this->rule(
				[
					'id'         => 64,
					'match_type' => 'wildcard',
					'source'     => '/old/*',
				]
			),
			$this->rule(
				[
					'id'         => 65,
					'match_type' => 'prefix',
					'source'     => '/old',
				]
			),
			$this->rule(
				[
					'id'         => 66,
					'match_type' => 'exact',
					'source'     => '/old/page',
				]
			),
		];

		$this->assertSame( 66, $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * Removing the exact rule must fall through to the next populated bucket.
	 *
	 * @dataProvider provide_tier_fallthrough
	 *
	 * @param array<int, array<string, mixed>> $rules     Rules without the exact rule.
	 * @param int                              $expected Expected winning id.
	 */
	public function test_tier_fallthrough_resolves_each_populated_bucket( array $rules, int $expected ): void {
		$this->assertSame( $expected, $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * Rule sets that leave exactly one populated bucket above regex.
	 *
	 * @return array<string, array{0: array<int, array<string, mixed>>, 1: int}>
	 */
	public static function provide_tier_fallthrough(): array {
		return [
			'prefix wins when exact is absent'      => [
				[
					[
						'id'         => 2,
						'match_type' => 'prefix',
						'source'     => '/old',
						'target'     => '/p',
						'is_active'  => 1,
					],
				],
				2,
			],
			'wildcard wins when prefix is absent'   => [
				[
					[
						'id'         => 3,
						'match_type' => 'wildcard',
						'source'     => '/old/*',
						'target'     => '/w',
						'is_active'  => 1,
					],
				],
				3,
			],
			'contains wins when wildcard is absent' => [
				[
					[
						'id'         => 4,
						'match_type' => 'contains',
						'source'     => 'old',
						'target'     => '/c',
						'is_active'  => 1,
					],
				],
				4,
			],
			'suffix wins when contains is absent'   => [
				[
					[
						'id'         => 5,
						'match_type' => 'suffix',
						'source'     => 'page',
						'target'     => '/s',
						'is_active'  => 1,
					],
				],
				5,
			],
			'regex is the last resort'              => [
				[
					[
						'id'         => 6,
						'match_type' => 'regex',
						'source'     => '/old/pa.e',
						'target'     => '/r',
						'is_active'  => 1,
					],
				],
				6,
			],
		];
	}

	/**
	 * Lowest id wins inside a tier regardless of the order rules arrive in.
	 *
	 * The grouping appends rules in encounter order, so a bucket must not change the
	 * outcome when the same rules are supplied shuffled.
	 *
	 * @dataProvider provide_tie_break
	 *
	 * @param string $match_type Tier to populate.
	 * @param string $source     Matching source.
	 * @param string $path       Request path.
	 */
	public function test_lowest_id_wins_inside_a_tier_regardless_of_input_order( string $match_type, string $source, string $path ): void {
		$rules = [
			$this->rule(
				[
					'id'         => 9,
					'match_type' => $match_type,
					'source'     => $source,
				]
			),
			$this->rule(
				[
					'id'         => 4,
					'match_type' => $match_type,
					'source'     => $source,
				]
			),
			$this->rule(
				[
					'id'         => 7,
					'match_type' => $match_type,
					'source'     => $source,
				]
			),
		];

		$this->assertSame( 4, $this->winnerId( $path, $rules ) );

		$reversed = array_reverse( $rules );

		$this->assertSame( 4, $this->winnerId( $path, $reversed ) );
	}

	/**
	 * Tiers that all reduce to a single lowest-id winner.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function provide_tie_break(): array {
		return [
			'exact'    => [ 'exact', '/old/page', '/old/page' ],
			'prefix'   => [ 'prefix', '/old', '/old/page' ],
			'wildcard' => [ 'wildcard', '/old/*', '/old/page' ],
			'contains' => [ 'contains', 'old', '/old/page' ],
			'suffix'   => [ 'suffix', 'page', '/old/page' ],
		];
	}

	/**
	 * The prefix tier prefers the longest source, lowest id only breaks ties.
	 */
	public function test_prefix_prefers_the_longest_source_then_the_lowest_id(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'prefix',
					'source'     => '/old',
				]
			),
			$this->rule(
				[
					'id'         => 8,
					'match_type' => 'prefix',
					'source'     => '/old/page',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'prefix',
					'source'     => '/old/pa',
				]
			),
		];

		$this->assertSame( 8, $this->winnerId( '/old/page', $rules ) );

		$tie = [
			$this->rule(
				[
					'id'         => 6,
					'match_type' => 'prefix',
					'source'     => '/old/pa',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'prefix',
					'source'     => '/old/pa',
				]
			),
		];

		$this->assertSame( 2, $this->winnerId( '/old/page', $tie ) );
	}

	/**
	 * Inactive rules are dropped before bucketing and can never win.
	 */
	public function test_inactive_rules_are_filtered_before_bucketing(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'exact',
					'source'     => '/old/page',
					'is_active'  => 0,
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'prefix',
					'source'     => '/old',
					'is_active'  => 1,
				]
			),
		];

		$this->assertSame( 2, $this->winnerId( '/old/page', $rules ) );
		$this->assertNull( $this->winnerId( '/old/page', [ $rules[0] ] ) );
	}

	/**
	 * A string "1" is active, matching the loose cast the filter relies on.
	 */
	public function test_string_active_flag_is_still_treated_as_active(): void {
		$rules = [ $this->rule( [ 'is_active' => '1' ] ) ];

		$this->assertSame( 1, $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * An unknown match type lands in no bucket and is ignored.
	 *
	 * The grouping can only place a rule when its type is one of the six known tiers.
	 * A rule with an unrecognised type must be dropped, and must not stop a known rule
	 * in the same set from being bucketed and matched.
	 *
	 * @dataProvider provide_unknown_match_types
	 *
	 * @param mixed $match_type Unrecognised match type.
	 */
	public function test_unknown_match_type_is_ignored_but_does_not_block_valid_rules( mixed $match_type ): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => $match_type,
					'source'     => '/old/page',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'exact',
					'source'     => '/old/page',
				]
			),
		];

		$this->assertSame( 2, $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * Values that must never be treated as a known tier.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_unknown_match_types(): array {
		return [
			'unknown word'   => [ 'bogus' ],
			'empty string'   => [ '' ],
			'null'           => [ null ],
			'wrong case'     => [ 'EXACT' ],
			'leading space'  => [ ' exact' ],
			'trailing space' => [ 'exact ' ],
		];
	}

	/**
	 * A set made only of unknown types resolves to nothing rather than erroring.
	 */
	public function test_only_unknown_match_types_resolve_to_null(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'bogus',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => '',
				]
			),
			$this->rule(
				[
					'id'         => 3,
					'match_type' => null,
				]
			),
		];

		$this->assertNull( $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * A rule with no match_type key at all is not a candidate for any bucket.
	 */
	public function test_missing_match_type_key_resolves_to_null(): void {
		$rule = $this->rule();
		unset( $rule['match_type'] );

		$this->assertNull( $this->winnerId( '/old/page', [ $rule ] ) );
	}

	/**
	 * Empty buckets are skipped and do not prevent a populated bucket from running.
	 *
	 * Only regex rules are supplied, so the exact, prefix, wildcard, contains and suffix
	 * buckets are all empty and their pickers must not run.
	 */
	public function test_empty_buckets_are_skipped_and_regex_still_resolves(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => '/old/pa.e',
				]
			),
		];

		$this->assertSame( 1, $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * Malformed rows are skipped without disturbing the valid rules around them.
	 */
	public function test_non_array_rows_are_ignored(): void {
		$rules = [
			'not-a-rule',
			42,
			null,
			$this->rule( [ 'id' => 3 ] ),
		];

		$this->assertSame( 3, $this->winnerId( '/old/page', $rules ) );
		$this->assertNull( $this->winnerId( '/old/page', [ 'not-a-rule', 42, null ] ) );
	}

	/**
	 * An empty rule set resolves to null.
	 */
	public function test_empty_rule_set_resolves_to_null(): void {
		$this->assertNull( $this->winnerId( '/old/page', [] ) );
	}

	/**
	 * No candidate matching the path resolves to null.
	 */
	public function test_no_matching_rule_resolves_to_null(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'exact',
					'source'     => '/somewhere-else',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'prefix',
					'source'     => '/other',
				]
			),
			$this->rule(
				[
					'id'         => 3,
					'match_type' => 'suffix',
					'source'     => '.php',
				]
			),
		];

		$this->assertNull( $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * An empty source is never a candidate, in any tier.
	 */
	public function test_empty_source_is_never_a_candidate(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'exact',
					'source'     => '',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'prefix',
					'source'     => '',
				]
			),
			$this->rule(
				[
					'id'         => 3,
					'match_type' => 'wildcard',
					'source'     => '',
				]
			),
			$this->rule(
				[
					'id'         => 4,
					'match_type' => 'contains',
					'source'     => '',
				]
			),
			$this->rule(
				[
					'id'         => 5,
					'match_type' => 'suffix',
					'source'     => '',
				]
			),
			$this->rule(
				[
					'id'         => 6,
					'match_type' => 'regex',
					'source'     => '',
				]
			),
		];

		$this->assertNull( $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * The regex cap still selects the lowest ids out of an oversized regex bucket.
	 *
	 * Only the first MAX_REGEX_RULES regex rules by id may be evaluated, so a match that
	 * only exists above the cap must be ignored.
	 */
	public function test_regex_cap_still_applies_to_the_lowest_ids(): void {
		// Twenty non-matching regex rules, with the last one inside the cap matching.
		$within = [];
		for ( $id = 1; $id <= Matcher::MAX_REGEX_RULES; $id++ ) {
			$within[] = $this->rule(
				[
					'id'         => $id,
					'match_type' => 'regex',
					'source'     => '/nomatch-\d+',
				]
			);
		}
		$within[ Matcher::MAX_REGEX_RULES - 1 ]['source'] = '/old/pa.e';

		$this->assertSame(
			Matcher::MAX_REGEX_RULES,
			$this->winnerId( '/old/page', $within )
		);

		// A matching rule only above the cap must be ignored, so the set must stay
		// entirely non-matching inside the cap for this to be meaningful.
		$over = [];
		for ( $id = 1; $id <= Matcher::MAX_REGEX_RULES; $id++ ) {
			$over[] = $this->rule(
				[
					'id'         => $id,
					'match_type' => 'regex',
					'source'     => '/nomatch-\d+',
				]
			);
		}
		$over[] = $this->rule(
			[
				'id'         => Matcher::MAX_REGEX_RULES + 1,
				'match_type' => 'regex',
				'source'     => '/old/pa.e',
			]
		);

		$this->assertNull( $this->winnerId( '/old/page', $over ) );
	}

	/**
	 * A regex rule that fails to compile fails closed instead of warning or matching.
	 */
	public function test_invalid_regex_fails_closed(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => '(',
				]
			),
		];

		$this->assertNull( $this->winnerId( '/old/page', $rules ) );
	}

	/**
	 * An over-length regex is skipped, so the cap on pattern length survives bucketing.
	 */
	public function test_over_length_regex_is_skipped(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => '/' . str_repeat( 'a', Matcher::MAX_REGEX_LENGTH + 1 ),
				]
			),
		];

		$this->assertNull( $this->winnerId( '/' . str_repeat( 'a', Matcher::MAX_REGEX_LENGTH + 1 ), $rules ) );
	}

	/**
	 * Two rules that both match resolve to exactly one winner, never both.
	 */
	public function test_multiple_possible_winners_resolve_to_a_single_rule(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'exact',
					'source'     => '/old/page',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'exact',
					'source'     => '/old/page',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 1, (int) $winner['id'] );
	}

	/**
	 * Bucketing must not change which rule is returned for a mixed, out-of-order set.
	 *
	 * The same set is matched twice, once with the tiers interleaved and once grouped,
	 * and both must resolve identically. This pins the grouping against the input layout
	 * rather than only against a single hand-picked ordering.
	 */
	public function test_interleaved_and_grouped_orderings_resolve_identically(): void {
		$interleaved = [
			$this->rule(
				[
					'id'         => 11,
					'match_type' => 'regex',
					'source'     => 'pa.e',
				]
			),
			$this->rule(
				[
					'id'         => 12,
					'match_type' => 'exact',
					'source'     => '/nope',
				]
			),
			$this->rule(
				[
					'id'         => 13,
					'match_type' => 'contains',
					'source'     => 'old',
				]
			),
			$this->rule(
				[
					'id'         => 14,
					'match_type' => 'prefix',
					'source'     => '/old',
				]
			),
			$this->rule(
				[
					'id'         => 15,
					'match_type' => 'wildcard',
					'source'     => '/old/*',
				]
			),
			$this->rule(
				[
					'id'         => 16,
					'match_type' => 'suffix',
					'source'     => 'page',
				]
			),
		];

		$grouped = [
			$interleaved[1],
			$interleaved[3],
			$interleaved[4],
			$interleaved[2],
			$interleaved[5],
			$interleaved[0],
		];

		$this->assertSame(
			$this->winnerId( '/old/page', $interleaved ),
			$this->winnerId( '/old/page', $grouped )
		);

		// Contains loses to prefix, so the prefix rule is the expected winner.
		$this->assertSame( 14, $this->winnerId( '/old/page', $interleaved ) );
	}

	/**
	 * Duplicate ids across tiers still resolve by precedence, not by id.
	 */
	public function test_duplicate_ids_across_tiers_resolve_by_precedence(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => '.+',
				]
			),
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'prefix',
					'source'     => '/old',
				]
			),
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'exact',
					'source'     => '/old/page',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 'exact', (string) $winner['match_type'] );
	}

	/**
	 * The blocked root path resolves to null regardless of the rules supplied.
	 */
	public function test_blocked_root_path_resolves_to_null(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'wildcard',
					'source'     => '*',
				]
			),
		];

		$this->assertNull( $this->winnerId( '/', $rules ) );
	}
}
