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
	 * Test activating a rule that completes a cycle is refused.
	 */
	public function test_activate_refuses_completed_cycle(): void {
		$this->repo->insert(
			[
				'source'    => '/a',
				'target'    => '/b',
				'is_active' => 0,
			]
		);
		$this->repo->insert(
			[
				'source' => '/b',
				'target' => '/c',
			]
		);
		$this->repo->insert(
			[
				'source' => '/c',
				'target' => '/a',
			]
		);

		$path = $this->repo->activationLoopPath( 1 );

		$this->assertNotSame( [], $path, 'activating /a → /b must report the cycle path' );
		$this->assertFalse( $this->repo->set_active( 1, true ) );
		$this->assertSame( 0, (int) $this->db->rows[1]['is_active'], 'a refused activation leaves the row inactive' );
	}

	/**
	 * Test activating a safe rule still succeeds.
	 */
	public function test_activate_allows_safe_rule(): void {
		$this->repo->insert(
			[
				'source' => '/b',
				'target' => '/c',
			]
		);
		$this->repo->insert(
			[
				'source'    => '/d',
				'target'    => '/e',
				'is_active' => 0,
			]
		);

		$this->assertSame( [], $this->repo->activationLoopPath( 2 ) );
		$this->assertTrue( $this->repo->set_active( 2, true ) );
		$this->assertSame( 1, (int) $this->db->rows[2]['is_active'] );
	}

	/**
	 * Test bulk activate flips safe rows and skips the cycle maker.
	 */
	public function test_bulk_activate_skips_cycle_maker(): void {
		$this->repo->insert(
			[
				'source'    => '/a',
				'target'    => '/b',
				'is_active' => 0,
			]
		);
		$this->repo->insert(
			[
				'source' => '/b',
				'target' => '/c',
			]
		);
		$this->repo->insert(
			[
				'source' => '/c',
				'target' => '/a',
			]
		);
		$this->repo->insert(
			[
				'source'    => '/d',
				'target'    => '/e',
				'is_active' => 0,
			]
		);

		$result = $this->repo->bulk( 'activate', [ 1, 4 ] );

		$this->assertSame( 1, $result['updated'], 'only the safe row flips' );
		$this->assertSame( 0, (int) $this->db->rows[1]['is_active'] );
		$this->assertSame( 1, (int) $this->db->rows[4]['is_active'] );
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

	/**
	 * Test export rows honors the row limit.
	 */
	public function test_export_rows_honors_limit(): void {
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

		$this->assertCount( 1, $this->repo->export_rows( [], 1 ) );
		$this->assertCount( 2, $this->repo->export_rows( [], 10 ) );
	}

	/**
	 * Test get row memoizes across calls and invalidates on write.
	 */
	public function test_get_row_memoizes_and_invalidates_on_write(): void {
		$id = $this->repo->insert(
			[
				'source' => '/memo-a',
				'target' => '/target-a',
			]
		);

		$readsBefore = $this->db->reads;
		$first       = $this->repo->get( $id );
		$readsFirst  = $this->db->reads;

		$this->assertGreaterThan( $readsBefore, $readsFirst );
		$this->assertIsArray( $first );

		// Second call reuses memoized row without triggering DB read.
		$secondRepo  = new RedirectRepository( $this->db );
		$second      = $secondRepo->get( $id );
		$readsSecond = $this->db->reads;

		$this->assertSame( $readsFirst, $readsSecond );
		$this->assertSame( $first, $second );

		// Write operation invalidates $rowMemo.
		$this->repo->update( $id, [ 'target' => '/target-updated' ] );

		$readsWrite = $this->db->reads;
		$third      = $this->repo->get( $id );
		$readsThird = $this->db->reads;

		$this->assertGreaterThan( $readsWrite, $readsThird );
		$this->assertSame( '/target-updated', $third['target'] ?? '' );
	}

	/**
	 * Test find_cycle_candidates memoizes across calls and invalidates on write.
	 */
	public function test_find_cycle_candidates_memoizes_and_invalidates_on_write(): void {
		$this->repo->insert(
			[
				'source' => '/cycle-a',
				'target' => '/cycle-b',
				'code'   => '301',
			]
		);

		$readsBefore = $this->db->reads;
		$first       = $this->repo->find_cycle_candidates();
		$readsFirst  = $this->db->reads;

		$this->assertGreaterThan( $readsBefore, $readsFirst );
		$this->assertCount( 1, $first );

		// Subsequent call reuses memoized candidates without triggering DB read.
		$secondRepo  = new RedirectRepository( $this->db );
		$second      = $secondRepo->find_cycle_candidates();
		$readsSecond = $this->db->reads;

		$this->assertSame( $readsFirst, $readsSecond );
		$this->assertSame( $first, $second );

		// Inserting a new active candidate invalidates cycle candidates memo.
		$this->repo->insert(
			[
				'source' => '/cycle-c',
				'target' => '/cycle-d',
				'code'   => '301',
			]
		);

		$readsWrite = $this->db->reads;
		$third      = $this->repo->find_cycle_candidates();
		$readsThird = $this->db->reads;

		$this->assertGreaterThan( $readsWrite, $readsThird );
		$this->assertCount( 2, $third );
	}

	/**
	 * Test resetCache resets all static memoized properties.
	 */
	public function test_reset_cache_resets_all_static_memos(): void {
		$id = $this->repo->insert(
			[
				'source'     => '/pattern-1*',
				'target'     => '/target-1',
				'match_type' => 'prefix',
				'code'       => '301',
			]
		);

		$this->repo->get( $id );
		$this->repo->all_patterns();
		$this->repo->find_cycle_candidates();

		$readsBefore = $this->db->reads;

		RedirectRepository::resetCache();

		$this->repo->get( $id );
		$this->repo->all_patterns();
		$this->repo->find_cycle_candidates();

		$readsAfter = $this->db->reads;

		$this->assertGreaterThan( $readsBefore, $readsAfter );
	}

	/**
	 * Test memos never leak rows across database handles.
	 */
	public function test_memos_are_scoped_to_the_database_handle(): void {
		$id = $this->repo->insert(
			[
				'source' => '/shared',
				'target' => '/target',
			]
		);

		$this->assertSame( $this->repo->get( $id )['target'], '/target' );
		$this->assertCount( 1, $this->repo->find_cycle_candidates() );

		$otherDb   = new RedirectsFakeDb();
		$otherRepo = new RedirectRepository( $otherDb );

		$this->assertNull( $otherRepo->get( $id ), 'a row memoized from one handle must not serve another' );
		$this->assertSame( [], $otherRepo->find_cycle_candidates(), 'candidates memoized from one handle must not serve another' );
	}
}
