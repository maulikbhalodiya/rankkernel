<?php
/**
 * Pattern set bound tests, cap plus cache plus invalidation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\Matcher;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\RedirectRepository;

/**
 * Proves a cold miss never loads an unbounded rule set.
 *
 * The active pattern list is capped at MAX_PATTERNS rows, cached in the
 * RedirectCache group, and retired on every write and toggle. Writes that
 * would grow past the cap are refused so the admin can report the limit.
 */
final class RedirectsPatternBoundTest extends TestCase {
	/**
	 * In memory redirect table.
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db         = new RedirectsFakeDb();
		$this->options    = [];
		$this->transients = [];

		// Test installs the in memory wpdb double, restored in tearDown.
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				// Test double backing the stubbed wp_parse_url with the native parser.
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
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
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->transients[ $key ] = $value;

				return true;
			}
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Prefill active prefix rules straight into the fake table.
	 */
	private function seedPatterns( int $count, int $active = 1 ): void {
		for ( $i = 1; $i <= $count; $i++ ) {
			$this->db->rows[ $i ] = [
				'id'            => $i,
				'match_type'    => 'prefix',
				'source_hash'   => hash( 'sha256', 'prefix|/p' . (string) $i ),
				'source'        => '/p' . (string) $i,
				'target'        => '/x',
				'code'          => '301',
				'is_active'     => $active,
				'created'       => '2026-01-01 00:00:00',
				'hits'          => 0,
				'last_accessed' => null,
			];
		}

		$this->db->nextId = $count + 1;
	}

	public function test_pattern_list_is_capped_at_max(): void {
		$this->seedPatterns( RedirectRepository::MAX_PATTERNS );

		$repo = new RedirectRepository( $this->db );

		$this->assertSame( RedirectRepository::MAX_PATTERNS, $repo->count_patterns() );
		$this->assertCount( RedirectRepository::MAX_PATTERNS, $repo->all_patterns() );
	}

	public function test_insert_refuses_active_pattern_past_cap(): void {
		$this->seedPatterns( RedirectRepository::MAX_PATTERNS );

		$repo = new RedirectRepository( $this->db );

		$this->assertSame(
			0,
			$repo->insert(
				[
					'source'     => '/one-more',
					'target'     => '/x',
					'match_type' => 'prefix',
					'is_active'  => true,
				]
			),
			'An active pattern past the cap must be refused with zero'
		);

		$this->assertGreaterThan(
			0,
			$repo->insert(
				[
					'source'     => '/exact-ok',
					'target'     => '/x',
					'match_type' => 'exact',
					'is_active'  => true,
				]
			),
			'Exact rules never count toward the pattern cap'
		);

		$this->assertGreaterThan(
			0,
			$repo->insert(
				[
					'source'     => '/idle',
					'target'     => '/x',
					'match_type' => 'prefix',
					'is_active'  => false,
				]
			),
			'Inactive patterns never count toward the pattern cap'
		);
	}

	public function test_activate_and_update_respect_cap(): void {
		$this->seedPatterns( RedirectRepository::MAX_PATTERNS );

		$this->db->rows[501] = [
			'id'            => 501,
			'match_type'    => 'prefix',
			'source_hash'   => hash( 'sha256', 'prefix|/idle' ),
			'source'        => '/idle',
			'target'        => '/x',
			'code'          => '301',
			'is_active'     => 0,
			'created'       => '2026-01-01 00:00:00',
			'hits'          => 0,
			'last_accessed' => null,
		];

		$this->db->nextId = 502;

		$repo = new RedirectRepository( $this->db );

		$this->assertFalse( $repo->set_active( 501, true ), 'Activating past the cap must fail' );
		$this->assertFalse( $repo->update( 501, [ 'is_active' => true ] ), 'Updating into the set past the cap must fail' );

		$this->assertTrue( $repo->set_active( 1, false ) );
		$this->assertTrue( $repo->set_active( 501, true ), 'One freed slot allows one activation' );
	}

	public function test_bulk_activate_stops_at_cap_and_reports_real_count(): void {
		$this->seedPatterns( 499 );

		$this->db->rows[500] = [
			'id'            => 500,
			'match_type'    => 'prefix',
			'source_hash'   => hash( 'sha256', 'prefix|/idle-a' ),
			'source'        => '/idle-a',
			'target'        => '/x',
			'code'          => '301',
			'is_active'     => 0,
			'created'       => '2026-01-01 00:00:00',
			'hits'          => 0,
			'last_accessed' => null,
		];
		$this->db->rows[501] = [
			'id'            => 501,
			'match_type'    => 'prefix',
			'source_hash'   => hash( 'sha256', 'prefix|/idle-b' ),
			'source'        => '/idle-b',
			'target'        => '/x',
			'code'          => '301',
			'is_active'     => 0,
			'created'       => '2026-01-01 00:00:00',
			'hits'          => 0,
			'last_accessed' => null,
		];

		$this->db->nextId = 502;

		$repo   = new RedirectRepository( $this->db );
		$result = $repo->bulk( 'activate', [ 500, 501 ] );

		$this->assertSame( 1, $result['updated'], 'Only the budgeted row may flip' );
		$this->assertSame( 500, $repo->count_patterns() );
		$this->assertSame( 1, (int) $this->db->rows[500]['is_active'] );
		$this->assertSame( 0, (int) $this->db->rows[501]['is_active'] );
	}

	public function test_cold_miss_reads_once_then_serves_from_cache(): void {
		$this->seedPatterns( 10 );

		$cache = new RedirectCache();
		$repo  = new RedirectRepository( $this->db, $cache );

		$readsBefore = $this->db->ruleReads;
		$first       = $repo->all_patterns();

		$this->assertCount( 10, $first );
		$this->assertSame( 1, $this->db->ruleReads - $readsBefore, 'First read loads the bounded list once' );

		$second = $repo->all_patterns();

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->db->ruleReads - $readsBefore, 'Second read must not touch the database' );

		$freshCache = new RedirectCache();
		$freshRepo  = new RedirectRepository( $this->db, $freshCache );
		$third      = $freshRepo->all_patterns();

		$this->assertSame( $first, $third );
		$this->assertSame( 1, $this->db->ruleReads - $readsBefore, 'A new instance shares the stored list' );
	}

	public function test_write_invalidates_the_pattern_list(): void {
		$this->seedPatterns( 3 );

		$cache = new RedirectCache();
		$repo  = new RedirectRepository( $this->db, $cache );

		$this->assertCount( 3, $repo->all_patterns() );

		$readsBefore = $this->db->ruleReads;

		$repo->insert(
			[
				'source'     => '/p-new',
				'target'     => '/x',
				'match_type' => 'prefix',
				'is_active'  => true,
			]
		);

		$after = $repo->all_patterns();

		$this->assertCount( 4, $after, 'A write must retire the cached list' );

		$warmBefore = $this->db->ruleReads;
		$warm       = $repo->all_patterns();

		$this->assertSame( $after, $warm );
		$this->assertSame( $warmBefore, $this->db->ruleReads, 'The reloaded list caches again' );
	}

	public function test_matcher_cold_miss_costs_two_indexed_reads_then_one(): void {
		$this->seedPatterns( 5 );

		$cache   = new RedirectCache();
		$repo    = new RedirectRepository( $this->db, $cache );
		$matcher = new Matcher( $repo );

		$readsBefore = $this->db->ruleReads;
		$winner      = $matcher->match( '/p3/deep' );

		$this->assertNotNull( $winner );
		$this->assertSame( '/p3', $winner['source'] );
		$this->assertSame( 2, $this->db->ruleReads - $readsBefore, 'Cold miss costs one exact lookup plus one bounded list read' );

		$again = $matcher->match( '/p4/deep' );

		$this->assertNotNull( $again );
		$this->assertSame( 3, $this->db->ruleReads - $readsBefore, 'Warm pattern set costs only the exact lookup' );
	}
}
