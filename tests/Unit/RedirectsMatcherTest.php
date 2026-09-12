<?php
/**
 * Matcher tests, every precedence tier plus safety caps.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\Matcher;
use RankKernel\Modules\Redirects\Normalizer;

final class RedirectsMatcherTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
	}

	protected function tearDown(): void {
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

	public function test_exact_beats_every_pattern(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 10,
					'match_type' => 'regex',
					'source'     => '^/old/.*$',
				]
			),
			$this->rule(
				[
					'id'         => 9,
					'match_type' => 'suffix',
					'source'     => 'ge',
				]
			),
			$this->rule(
				[
					'id'         => 8,
					'match_type' => 'contains',
					'source'     => 'ld/pa',
				]
			),
			$this->rule(
				[
					'id'         => 7,
					'match_type' => 'wildcard',
					'source'     => '/old/*',
				]
			),
			$this->rule(
				[
					'id'         => 6,
					'match_type' => 'prefix',
					'source'     => '/old',
				]
			),
			$this->rule(
				[
					'id'         => 5,
					'match_type' => 'exact',
					'source'     => '/old/page',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 5, $winner['id'] );
	}

	public function test_prefix_beats_wildcard_contains_suffix_regex(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 10,
					'match_type' => 'regex',
					'source'     => '^/old/.*$',
				]
			),
			$this->rule(
				[
					'id'         => 9,
					'match_type' => 'suffix',
					'source'     => 'ge',
				]
			),
			$this->rule(
				[
					'id'         => 8,
					'match_type' => 'contains',
					'source'     => 'ld/pa',
				]
			),
			$this->rule(
				[
					'id'         => 7,
					'match_type' => 'wildcard',
					'source'     => '/old/*',
				]
			),
			$this->rule(
				[
					'id'         => 6,
					'match_type' => 'prefix',
					'source'     => '/old',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 6, $winner['id'] );
	}

	public function test_wildcard_beats_contains_suffix_regex(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 10,
					'match_type' => 'regex',
					'source'     => '^/old/.*$',
				]
			),
			$this->rule(
				[
					'id'         => 9,
					'match_type' => 'suffix',
					'source'     => 'ge',
				]
			),
			$this->rule(
				[
					'id'         => 8,
					'match_type' => 'contains',
					'source'     => 'ld/pa',
				]
			),
			$this->rule(
				[
					'id'         => 7,
					'match_type' => 'wildcard',
					'source'     => '/old/*',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 7, $winner['id'] );
	}

	public function test_contains_beats_suffix_regex(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 10,
					'match_type' => 'regex',
					'source'     => '^/old/.*$',
				]
			),
			$this->rule(
				[
					'id'         => 9,
					'match_type' => 'suffix',
					'source'     => 'ge',
				]
			),
			$this->rule(
				[
					'id'         => 8,
					'match_type' => 'contains',
					'source'     => 'ld/pa',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 8, $winner['id'] );
	}

	public function test_suffix_beats_regex(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 10,
					'match_type' => 'regex',
					'source'     => '^/old/.*$',
				]
			),
			$this->rule(
				[
					'id'         => 9,
					'match_type' => 'suffix',
					'source'     => 'ge',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 9, $winner['id'] );
	}

	public function test_regex_wins_when_only_regex_matches(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 10,
					'match_type' => 'regex',
					'source'     => '^/old/.*$',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 10, $winner['id'] );
	}

	public function test_longest_prefix_wins(): void {
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
					'id'         => 2,
					'match_type' => 'prefix',
					'source'     => '/old/pa',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page/x', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 2, $winner['id'] );
	}

	public function test_lowest_id_breaks_ties(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 4,
					'match_type' => 'contains',
					'source'     => 'old',
				]
			),
			$this->rule(
				[
					'id'         => 2,
					'match_type' => 'contains',
					'source'     => 'old',
				]
			),
		];

		$winner = Matcher::pick_winner( '/old/page', $rules );

		$this->assertIsArray( $winner );
		$this->assertSame( 2, $winner['id'] );
	}

	public function test_inactive_rules_never_win(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'exact',
					'source'     => '/old/page',
					'is_active'  => 0,
				]
			),
		];

		$this->assertNull( Matcher::pick_winner( '/old/page', $rules ) );
	}

	public function test_no_match_returns_null(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'exact',
					'source'     => '/elsewhere',
				]
			),
		];

		$this->assertNull( Matcher::pick_winner( '/old/page', $rules ) );
	}

	public function test_homepage_never_matches(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'prefix',
					'source'     => '/',
				]
			),
		];

		$this->assertNull( Matcher::pick_winner( '/', $rules ) );
	}

	public function test_regex_cap_ignores_rules_past_twenty(): void {
		$rules = [];

		for ( $i = 1; $i <= 20; $i++ ) {
			$rules[] = $this->rule(
				[
					'id'         => $i,
					'match_type' => 'regex',
					'source'     => '^/zzz.*$',
				]
			);
		}

		$rules[] = $this->rule(
			[
				'id'         => 21,
				'match_type' => 'regex',
				'source'     => '^/old/.*$',
			]
		);

		$this->assertNull( Matcher::pick_winner( '/old/page', $rules ) );
	}

	public function test_regex_over_length_limit_ignored(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => '^/old/' . str_repeat( 'a', 300 ) . '$',
				]
			),
		];

		$this->assertNull( Matcher::pick_winner( '/old/page', $rules ) );
	}

	public function test_invalid_regex_fails_closed(): void {
		$rules = [
			$this->rule(
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => '^/old/([',
				]
			),
		];

		$this->assertNull( Matcher::pick_winner( '/old/page', $rules ) );
	}

	public function test_rule_matches_single_rule_check(): void {
		$this->assertTrue(
			Matcher::rule_matches(
				$this->rule(
					[
						'match_type' => 'prefix',
						'source'     => '/old',
					]
				),
				'/old/page'
			)
		);
		$this->assertFalse(
			Matcher::rule_matches(
				$this->rule(
					[
						'match_type' => 'prefix',
						'source'     => '/new',
					]
				),
				'/old/page'
			)
		);
		$this->assertFalse( Matcher::rule_matches( $this->rule( [ 'is_active' => 0 ] ), '/old/page' ) );
	}

	public function test_match_uses_indexed_exact_first(): void {
		$exact = $this->rule(
			[
				'id'     => 3,
				'source' => '/old',
			]
		);

		$repo = new class( $exact ) {
			/**
			 * @var array<string, mixed>
			 */
			private array $exact;

			/**
			 * @param array<string, mixed> $exact Exact row.
			 */
			public function __construct( array $exact ) {
				$this->exact = $exact;
			}

			/**
			 * @return array<string, mixed>|null
			 */
			public function lookup( string $path ): ?array {
				return Normalizer::normalize( $path ) === $this->exact['source'] ? $this->exact : null;
			}

			/**
			 * @return array<int, array<string, mixed>>
			 */
			public function all_patterns(): array {
				return [];
			}
		};

		$matcher = new Matcher( $repo );
		$winner  = $matcher->match( '/old' );

		$this->assertIsArray( $winner );
		$this->assertSame( 3, $winner['id'] );
	}

	public function test_match_falls_back_to_patterns(): void {
		$repo = new class() {
			/**
			 * @return array<string, mixed>|null
			 */
			public function lookup( string $path ): ?array {
				return null;
			}

			/**
			 * @return array<int, array<string, mixed>>
			 */
			public function all_patterns(): array {
				return [
					[
						'id'         => 7,
						'match_type' => 'prefix',
						'source'     => '/old',
						'target'     => '/new',
						'code'       => '301',
						'hits'       => 0,
						'is_active'  => 1,
					],
				];
			}
		};

		$matcher = new Matcher( $repo );
		$winner  = $matcher->match( '/old/page' );

		$this->assertIsArray( $winner );
		$this->assertSame( 7, $winner['id'] );
	}
}
