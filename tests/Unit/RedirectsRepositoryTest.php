<?php
/**
 * Repository tests over the in memory wpdb double.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\Normalizer;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\RedirectRepository;

/**
 * Redirects Repository Test.
 */
final class RedirectsRepositoryTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * Repository under test.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repo;

	/**
	 * Option store for cache doubles.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db   = new RedirectsFakeDb();
		$this->repo = new RedirectRepository( $this->db );

		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double, restored in tearDown.

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test insert normalizes and hashes.
	 */
	public function test_insert_normalizes_and_hashes(): void {
		$id = $this->repo->insert(
			[
				'source'     => '/Old//page/',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'exact',
			]
		);

		$this->assertSame( 1, $id );

		$stored = $this->db->rows[1];

		$this->assertSame( '/Old/page', $stored['source'] );
		$this->assertSame( Normalizer::hash( 'exact', '/Old/page' ), $stored['source_hash'] );
	}

	/**
	 * Test insert rejects homepage source.
	 */
	public function test_insert_rejects_homepage_source(): void {
		$this->assertSame(
			0,
			$this->repo->insert(
				[
					'source' => '/',
					'target' => '/new',
				]
			)
		);
	}

	/**
	 * Test insert rejects empty target for redirect code.
	 */
	public function test_insert_rejects_empty_target_for_redirect_code(): void {
		$this->assertSame(
			0,
			$this->repo->insert(
				[
					'source' => '/old',
					'target' => '',
				]
			)
		);
	}

	/**
	 * Test insert allows empty target for gone.
	 */
	public function test_insert_allows_empty_target_for_gone(): void {
		$id = $this->repo->insert(
			[
				'source' => '/old',
				'target' => '',
				'code'   => '410',
			]
		);

		$this->assertSame( 1, $id );
	}

	/**
	 * Test insert blocks duplicates by uniqueness.
	 */
	public function test_insert_blocks_duplicates_by_uniqueness(): void {
		$this->assertSame(
			1,
			$this->repo->insert(
				[
					'source' => '/old',
					'target' => '/a',
				]
			)
		);
		$this->assertSame(
			0,
			$this->repo->insert(
				[
					'source' => '/old',
					'target' => '/b',
				]
			)
		);
	}

	/**
	 * Test find and lookup resolve by hash.
	 */
	public function test_find_and_lookup_resolve_by_hash(): void {
		$this->repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$hash  = Normalizer::hash( 'exact', '/old' );
		$found = $this->repo->find( 'exact', $hash );

		$this->assertIsArray( $found );
		$this->assertSame( '/new', $found['target'] );

		$lookup = $this->repo->lookup( '/old?utm=9' );

		$this->assertIsArray( $lookup );
		$this->assertSame( 1, $lookup['id'] );
	}

	/**
	 * Test lookup ignores blocked source.
	 */
	public function test_lookup_ignores_blocked_source(): void {
		$this->assertNull( $this->repo->lookup( '/' ) );
	}

	/**
	 * Test update rehashes changed source.
	 */
	public function test_update_rehashes_changed_source(): void {
		$this->repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$this->assertTrue( $this->repo->update( 1, [ 'source' => '/other' ] ) );
		$this->assertNull( $this->repo->lookup( '/old' ) );

		$moved = $this->repo->lookup( '/other' );

		$this->assertIsArray( $moved );
		$this->assertSame( '/new', $moved['target'] );
	}

	/**
	 * Test update rejects unknown fields only.
	 */
	public function test_update_rejects_unknown_fields_only(): void {
		$this->repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$this->assertFalse( $this->repo->update( 1, [ 'nope' => 'x' ] ) );
	}

	/**
	 * Test delete removes row.
	 */
	public function test_delete_removes_row(): void {
		$this->repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$this->assertTrue( $this->repo->delete( 1 ) );
		$this->assertNull( $this->repo->lookup( '/old' ) );
		$this->assertFalse( $this->repo->delete( 0 ) );
	}

	/**
	 * Test set active flips flag.
	 */
	public function test_set_active_flips_flag(): void {
		$this->repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$this->assertTrue( $this->repo->set_active( 1, false ) );
		$this->assertSame( 0, (int) $this->db->rows[1]['is_active'] );
	}

	/**
	 * Test all patterns excludes exact and inactive.
	 */
	public function test_all_patterns_excludes_exact_and_inactive(): void {
		$this->repo->insert(
			[
				'source'     => '/a',
				'target'     => '/x',
				'match_type' => 'exact',
			]
		);
		$this->repo->insert(
			[
				'source'     => '/b',
				'target'     => '/x',
				'match_type' => 'prefix',
			]
		);
		$this->repo->insert(
			[
				'source'     => '/c',
				'target'     => '/x',
				'match_type' => 'prefix',
				'is_active'  => 0,
			]
		);

		$patterns = $this->repo->all_patterns();

		$this->assertCount( 1, $patterns );
		$this->assertSame( '/b', $patterns[0]['source'] );
	}

	/**
	 * Test paginate search filters sort counts.
	 */
	public function test_paginate_search_filters_sort_counts(): void {
		$this->repo->insert(
			[
				'source'     => '/alpha',
				'target'     => '/x',
				'match_type' => 'exact',
			]
		);
		$this->repo->insert(
			[
				'source'     => '/beta',
				'target'     => '/x',
				'match_type' => 'prefix',
				'code'       => '302',
			]
		);
		$this->repo->insert(
			[
				'source'     => '/gamma',
				'target'     => '/x',
				'match_type' => 'prefix',
				'is_active'  => 0,
			]
		);

		$page = $this->repo->paginate( [ 'per_page' => 10 ] );

		$this->assertSame( 3, $page['total'] );
		$this->assertSame( 2, $page['active'] );
		$this->assertSame( 1, $page['inactive'] );
		$this->assertCount( 3, $page['rows'] );

		$search = $this->repo->paginate( [ 'search' => 'alpha' ] );

		$this->assertSame( 1, $search['total'] );
		$this->assertSame( '/alpha', $search['rows'][0]['source'] );

		$filtered = $this->repo->paginate(
			[
				'match_type' => 'prefix',
				'status'     => 'active',
			]
		);

		$this->assertSame( 1, $filtered['total'] );
		$this->assertSame( '/beta', $filtered['rows'][0]['source'] );

		$sorted = $this->repo->paginate(
			[
				'orderby'  => 'source',
				'order'    => 'ASC',
				'per_page' => 10,
			]
		);

		$this->assertSame( '/alpha', $sorted['rows'][0]['source'] );
		$this->assertSame( '/beta', $sorted['rows'][1]['source'] );
	}

	/**
	 * Test bulk activate deactivate delete.
	 */
	public function test_bulk_activate_deactivate_delete(): void {
		$this->repo->insert(
			[
				'source'    => '/a',
				'target'    => '/x',
				'is_active' => 0,
			]
		);
		$this->repo->insert(
			[
				'source'    => '/b',
				'target'    => '/x',
				'is_active' => 0,
			]
		);
		$this->repo->insert(
			[
				'source' => '/c',
				'target' => '/x',
			]
		);

		$activated = $this->repo->bulk( 'activate', [ 1, 2 ] );

		$this->assertSame( 2, $activated['updated'] );

		$deactivated = $this->repo->bulk( 'deactivate', [ 3 ] );

		$this->assertSame( 1, $deactivated['updated'] );
		$this->assertSame( 0, (int) $this->db->rows[3]['is_active'] );

		$deleted = $this->repo->bulk( 'delete', [ 1, 2, 3 ] );

		$this->assertSame( 3, $deleted['deleted'] );
		$this->assertSame( 0, $this->repo->count() );

		$unknown = $this->repo->bulk( 'bogus', [ 1 ] );

		$this->assertSame( 0, $unknown['updated'] );
		$this->assertSame( 0, $unknown['deleted'] );
	}

	/**
	 * Test cacheless repository memoizes all_patterns across calls and invalidates on write.
	 */
	public function test_cacheless_all_patterns_memoizes_and_invalidates_on_write(): void {
		$this->repo->insert(
			[
				'source'     => '/prefix*',
				'target'     => '/target',
				'match_type' => 'prefix',
			]
		);

		$readsBefore = $this->db->ruleReads;
		$first       = $this->repo->all_patterns();
		$readsFirst  = $this->db->ruleReads;

		$this->assertGreaterThan( $readsBefore, $readsFirst );

		// Second call on same or separate repository instance reuses static memo (0 new DB reads).
		$secondRepo  = new RedirectRepository( $this->db );
		$second      = $secondRepo->all_patterns();
		$readsSecond = $this->db->ruleReads;

		$this->assertSame( $readsFirst, $readsSecond );
		$this->assertSame( $first, $second );

		// Insert a new pattern rule to trigger touch() and clear static memo.
		$this->repo->insert(
			[
				'source'     => '/prefix2*',
				'target'     => '/target2',
				'match_type' => 'prefix',
			]
		);

		$readsWrite = $this->db->ruleReads;
		$third      = $secondRepo->all_patterns();
		$readsThird = $this->db->ruleReads;

		$this->assertGreaterThan( $readsWrite, $readsThird );
		$this->assertCount( 2, $third );
		$this->assertNotSame( $first, $third );
	}

	/**
	 * Test count with filters.
	 */
	public function test_count_with_filters(): void {
		$this->repo->insert(
			[
				'source' => '/a',
				'target' => '/x',
				'code'   => '301',
			]
		);
		$this->repo->insert(
			[
				'source' => '/b',
				'target' => '/x',
				'code'   => '302',
			]
		);

		$this->assertSame( 2, $this->repo->count() );
		$this->assertSame( 1, $this->repo->count( [ 'code' => '302' ] ) );
		$this->assertSame( 0, $this->repo->count( [ 'status' => 'inactive' ] ) );
	}

	/**
	 * Test cycle candidates exclude terminal and inactive.
	 */
	public function test_cycle_candidates_exclude_terminal_and_inactive(): void {
		$this->repo->insert(
			[
				'source' => '/a',
				'target' => '/b',
				'code'   => '301',
			]
		);
		$this->repo->insert(
			[
				'source' => '/gone',
				'target' => '',
				'code'   => '410',
			]
		);
		$this->repo->insert(
			[
				'source'    => '/off',
				'target'    => '/b',
				'is_active' => 0,
			]
		);

		$candidates = $this->repo->find_cycle_candidates();

		$this->assertCount( 1, $candidates );
		$this->assertSame( '/a', $candidates[0]['source'] );
	}

	/**
	 * Test export rows all and selected.
	 */
	public function test_export_rows_all_and_selected(): void {
		$this->repo->insert(
			[
				'source' => '/a',
				'target' => '/x',
			]
		);
		$this->repo->insert(
			[
				'source' => '/b',
				'target' => '/y',
			]
		);

		$this->assertCount( 2, $this->repo->export_rows() );

		$selected = $this->repo->export_rows( [ 2 ] );

		$this->assertCount( 1, $selected );
		$this->assertSame( '/b', $selected[0]['source'] );
	}

	/**
	 * Test writes invalidate cache.
	 */
	public function test_writes_invalidate_cache(): void {
		$cache = new RedirectCache();
		$this->repo->setCache( $cache );

		$before = (string) get_option( RedirectCache::VALIDATOR_OPTION, '' );

		$this->repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$after = (string) ( $this->options[ RedirectCache::VALIDATOR_OPTION ] ?? '' );

		$this->assertNotSame( $before, $after );

		$this->repo->set_active( 1, false );

		$later = (string) ( $this->options[ RedirectCache::VALIDATOR_OPTION ] ?? '' );

		$this->assertNotSame( $after, $later );
	}

	/**
	 * Test rule twenty one in the active regex set is refused at write time.
	 *
	 * Before this the bound lived only in the matcher, so the rule saved,
	 * listed as active and counted toward hits, and was never evaluated.
	 */
	public function test_regex_rule_past_the_limit_is_refused_on_write(): void {
		$repo = new RedirectRepository( $this->db );

		for ( $i = 1; $i <= RedirectRepository::MAX_REGEX_RULES; $i++ ) {
			$id = $repo->insert(
				[
					'source'     => '^/rk-' . $i . '$',
					'target'     => '/new',
					'code'       => '301',
					'match_type' => 'regex',
				]
			);

			$this->assertGreaterThan( 0, $id, 'Rule ' . $i . ' must be accepted at the limit' );
		}

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $repo->count_regex_rules() );

		$overLimit = $repo->insert(
			[
				'source'     => '^/rk-dead$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
			]
		);

		$this->assertSame( 0, $overLimit, 'The rule past the regex limit must be refused' );
		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $repo->count_regex_rules() );
	}

	/**
	 * Test an inactive regex rule past the limit is still accepted.
	 *
	 * The bound is on the evaluated set, so an inactive row never grows it.
	 */
	public function test_inactive_regex_past_the_limit_is_accepted(): void {
		$repo = new RedirectRepository( $this->db );

		for ( $i = 1; $i <= RedirectRepository::MAX_REGEX_RULES; $i++ ) {
			$repo->insert(
				[
					'source'     => '^/rk-' . $i . '$',
					'target'     => '/new',
					'code'       => '301',
					'match_type' => 'regex',
				]
			);
		}

		$inactive = $repo->insert(
			[
				'source'     => '^/rk-dormant$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
				'is_active'  => false,
			]
		);

		$this->assertGreaterThan( 0, $inactive, 'An inactive rule must not be refused' );
	}

	/**
	 * Fill the active regex set to the limit and add one dormant regex rule.
	 *
	 * @return array{counted: int, dormant: int} Ids of a counted rule and the dormant one.
	 */
	private function fillRegexSetToLimit(): array {
		$counted = 0;

		for ( $i = 1; $i <= RedirectRepository::MAX_REGEX_RULES; $i++ ) {
			$id = $this->repo->insert(
				[
					'source'     => '^/rk-' . $i . '$',
					'target'     => '/new',
					'code'       => '301',
					'match_type' => 'regex',
				]
			);

			$this->assertGreaterThan( 0, $id, 'Rule ' . $i . ' must be accepted at the limit' );

			if ( 1 === $i ) {
				$counted = $id;
			}
		}

		$dormant = $this->repo->insert(
			[
				'source'     => '^/rk-dormant$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
				'is_active'  => false,
			]
		);

		$this->assertGreaterThan( 0, $dormant, 'A dormant rule must be storable at the limit' );

		return [
			'counted' => $counted,
			'dormant' => $dormant,
		];
	}

	/**
	 * Test an update cannot push the active regex set past the limit.
	 *
	 * The cap used to run only on insert, so editing a dormant rule to active
	 * grew the set anyway. The rule then saved, listed as active and counted
	 * toward hits while the matcher never evaluated it, which is the silent
	 * dead rule the cap exists to prevent.
	 */
	public function test_update_cannot_grow_the_active_regex_set_past_the_limit(): void {
		$dormant = $this->fillRegexSetToLimit()['dormant'];

		$this->assertFalse(
			$this->repo->update( $dormant, [ 'is_active' => 1 ] ),
			'An update must not activate a regex rule past the limit'
		);

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test an update cannot change an exact rule into a regex rule past the limit.
	 *
	 * The partial names neither is_active nor a stored regex type, so the cap
	 * has to resolve both from the row as it stands rather than defaulting the
	 * matcher to exact. The source comes along because a partial that renames
	 * the matcher has to restate the pattern it now applies to.
	 */
	public function test_update_cannot_flip_an_exact_rule_into_the_regex_set_past_the_limit(): void {
		$this->fillRegexSetToLimit();

		$exact = $this->repo->insert(
			[
				'source'     => '/plain-path',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'exact',
			]
		);

		$this->assertGreaterThan( 0, $exact, 'An exact rule must be storable at the regex limit' );

		$this->assertFalse(
			$this->repo->update(
				$exact,
				[
					'source'     => '^/plain/[0-9]+$',
					'match_type' => 'regex',
				]
			),
			'An update must not move an exact rule into the regex set past the limit'
		);

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test reactivation cannot grow the active regex set past the limit.
	 */
	public function test_set_active_cannot_grow_the_active_regex_set_past_the_limit(): void {
		$dormant = $this->fillRegexSetToLimit()['dormant'];

		$this->assertFalse(
			$this->repo->set_active( $dormant, true ),
			'Reactivation must not grow the regex set past the limit'
		);

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test bulk activate cannot grow the active regex set past the limit.
	 */
	public function test_bulk_activate_cannot_grow_the_active_regex_set_past_the_limit(): void {
		$dormant = $this->fillRegexSetToLimit()['dormant'];

		$result = $this->repo->bulk( 'activate', [ $dormant ] );

		$this->assertSame( 0, $result['updated'], 'Bulk activate must not grow the regex set' );
		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test bulk activate honours the remaining budget when some room is left.
	 */
	public function test_bulk_activate_uses_the_remaining_regex_budget(): void {
		for ( $i = 1; $i <= RedirectRepository::MAX_REGEX_RULES - 1; $i++ ) {
			$this->repo->insert(
				[
					'source'     => '^/rk-' . $i . '$',
					'target'     => '/new',
					'code'       => '301',
					'match_type' => 'regex',
				]
			);
		}

		$first = $this->repo->insert(
			[
				'source'     => '^/rk-spare-1$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
				'is_active'  => false,
			]
		);

		$second = $this->repo->insert(
			[
				'source'     => '^/rk-spare-2$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
				'is_active'  => false,
			]
		);

		$this->assertSame(
			1,
			$this->repo->bulk( 'activate', [ $first, $second ] )['updated'],
			'Only the one spare slot may be filled'
		);

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test an edit that keeps a rule inside the active regex set still passes at the limit.
	 *
	 * The row is already counted, so excluding it by id keeps the rule at the
	 * cap instead of treating the edit as growth and locking a live rule.
	 */
	public function test_update_of_a_counted_regex_rule_still_passes_at_the_limit(): void {
		$counted = $this->fillRegexSetToLimit()['counted'];

		$this->assertTrue(
			$this->repo->update( $counted, [ 'target' => '/moved' ] ),
			'An edit to a rule already inside the set must not be refused'
		);

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test reactivating a rule already inside the active regex set still passes at the limit.
	 */
	public function test_set_active_of_a_counted_regex_rule_still_passes_at_the_limit(): void {
		$counted = $this->fillRegexSetToLimit()['counted'];

		$this->assertTrue(
			$this->repo->set_active( $counted, true ),
			'Reactivating a counted rule must not be refused'
		);

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test an exact rule still activates at the regex limit.
	 *
	 * The bound is on the evaluated regex set, so a rule outside it is never
	 * refused by this cap.
	 */
	public function test_exact_rule_activates_at_the_regex_limit(): void {
		$this->fillRegexSetToLimit();

		$exact = $this->repo->insert(
			[
				'source'     => '/plain-path',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'exact',
			]
		);

		$this->assertTrue(
			$this->repo->set_active( $exact, true ),
			'An exact rule is not in the regex set and must not be refused'
		);

		$this->assertSame( RedirectRepository::MAX_REGEX_RULES, $this->repo->count_regex_rules() );
	}

	/**
	 * Test a regex source longer than the bound is refused on write.
	 */
	public function test_over_length_regex_is_refused_on_write(): void {
		$repo = new RedirectRepository( $this->db );

		$overLength = $repo->insert(
			[
				'source'     => '^/' . str_repeat( 'a', RedirectRepository::MAX_REGEX_LENGTH ) . '$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
			]
		);

		$this->assertSame( 0, $overLength, 'An over length regex must be refused on write' );

		$inBounds = $repo->insert(
			[
				'source'     => '^/' . str_repeat( 'a', 10 ) . '$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
			]
		);

		$this->assertGreaterThan( 0, $inBounds, 'A regex inside the length bound must be accepted' );
	}

	/**
	 * Test a catastrophic backtracking pattern is refused on write.
	 */
	public function test_catastrophic_regex_is_refused_on_write(): void {
		$repo = new RedirectRepository( $this->db );

		$unsafe = $repo->insert(
			[
				'source'     => '^(a+)+$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
			]
		);

		$this->assertSame( 0, $unsafe, 'A catastrophic pattern must be refused on write' );

		$safe = $repo->insert(
			[
				'source'     => '^/old/[0-9]+$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
			]
		);

		$this->assertGreaterThan( 0, $safe, 'An ordinary regex must still save' );
	}

	/**
	 * Test no connection fails quietly.
	 */
	public function test_no_connection_fails_quietly(): void {
		unset( $GLOBALS['wpdb'] );

		$repo = new RedirectRepository();

		$this->assertNull( $repo->lookup( '/old' ) );
		$this->assertSame( [], $repo->all_patterns() );
		$this->assertSame(
			0,
			$repo->insert(
				[
					'source' => '/old',
					'target' => '/new',
				]
			)
		);
		$this->assertFalse( $repo->delete( 1 ) );
	}
}
