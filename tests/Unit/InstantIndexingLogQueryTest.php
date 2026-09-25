<?php
/**
 * Instant Indexing log query layer tests, SQL filters, counts and paging.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\InstantIndexingOutcomes;
use RankKernel\Modules\InstantIndexing\LogFilters;
use RankKernel\Modules\InstantIndexing\LogQuery;
use RankKernel\Modules\InstantIndexing\LogTable;

/**
 * Instant Indexing Log Query Test.
 */
final class InstantIndexingLogQueryTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var InstantIndexingFakeDb
	 */
	private InstantIndexingFakeDb $db;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db = new InstantIndexingFakeDb();

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

		Functions\when( '__' )->alias( static fn( string $v ): string => $v );
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
	 * Build a query from raw arguments.
	 *
	 * @param array<string, mixed> $input Raw arguments.
	 * @return LogQuery The result.
	 */
	private function query( array $input = [] ): LogQuery {
		return LogQuery::fromInput( $input );
	}

	/**
	 * Seed one log row with a deterministic URL.
	 *
	 * @param int    $code    Status code.
	 * @param string $source  Source value.
	 * @param string $message Message value.
	 * @param string $created UTC timestamp.
	 * @param string $url     URL, a slug is derived when empty.
	 * @return int Assigned id.
	 */
	private function seed( int $code = 200, string $source = 'manual', string $message = 'Accepted.', string $created = '', string $url = '' ): int {
		$id = $this->db->nextId;

		return $this->db->seed(
			[
				'url'     => '' !== $url ? $url : 'https://example.com/seed-' . $id,
				'code'    => $code,
				'source'  => $source,
				'message' => $message,
				'created' => '' !== $created ? $created : gmdate( 'Y-m-d H:i:s', 1767225600 + $id ),
			]
		);
	}

	/**
	 * Extract one column from the rows for compact assertions.
	 *
	 * @param array<int, array<string, mixed>> $rows Query rows.
	 * @param string                           $key  Column key.
	 * @return array<int, mixed> The result.
	 */
	private function column( array $rows, string $key ): array {
		return array_values( array_map( static fn( array $row ): mixed => $row[ $key ] ?? null, $rows ) );
	}

	/**
	 * Test no filters returns newest first with full totals.
	 */
	public function test_no_filters_returns_newest_first_with_full_totals(): void {
		$this->seed( 200, 'manual', 'Accepted.', '2026-01-01 10:00:00' );
		$this->seed( 400, 'auto', 'Rejected.', '2026-01-01 12:00:00' );
		$this->seed( 429, 'manual', 'Limited.', '2026-01-01 11:00:00' );

		$query = $this->query();
		$rows  = $query->rows();

		$this->assertCount( 3, $rows );
		$this->assertSame( [ 400, 429, 200 ], $this->column( $rows, 'code' ), 'rows must be newest first' );
		$this->assertSame( 3, $query->total() );
		$this->assertSame( 3, $query->filteredTotal() );
		$this->assertSame( 1, $query->pageCount() );
		$this->assertFalse( $query->filters()->hasFilter() );
	}

	/**
	 * Test the row shape is stable and carries the UTC time.
	 */
	public function test_row_shape_is_stable(): void {
		$this->seed( 202, 'auto', 'Accepted, the key is pending verification.', '2026-03-04 05:06:07' );

		$row = $this->query()->rows()[0];

		$this->assertSame( [ 'url', 'host', 'code', 'source', 'time', 'message' ], array_keys( $row ) );
		$this->assertSame( 202, $row['code'] );
		$this->assertIsInt( $row['code'] );
		$this->assertSame( 'auto', $row['source'] );
		$this->assertSame( '2026-03-04 05:06:07', $row['time'], 'the stored UTC string must round trip unchanged' );
		$this->assertSame( 'example.com', $row['host'] );
	}

	/**
	 * Test the search matches URL and message, case insensitively.
	 */
	public function test_search_matches_url_and_message_case_insensitively(): void {
		$this->seed( 200, 'manual', 'Accepted.', '', 'https://example.com/hello-world' );
		$this->seed( 400, 'manual', 'Rejected permanently, retrying will not help.' );
		$this->seed( 429, 'manual', 'Temporary failure, retry later.' );

		$this->assertSame( 1, $this->query( [ 's' => 'HELLO' ] )->filteredTotal() );
		$this->assertSame( 1, $this->query( [ 's' => 'PERMANENTLY' ] )->filteredTotal() );
		$this->assertSame( 0, $this->query( [ 's' => 'no-such-needle' ] )->filteredTotal() );
	}

	/**
	 * Test the search matches across the URL and message concatenation.
	 *
	 * The old PHP filter ran stripos over url plus a space plus message,
	 * so a needle spanning the boundary must still match.
	 */
	public function test_search_matches_across_the_url_message_boundary(): void {
		$this->seed( 200, 'manual', 'BoundaryTail', '', 'https://example.com/boundary-head' );

		$this->assertSame( 1, $this->query( [ 's' => 'boundary-head BoundaryTail' ] )->filteredTotal() );
	}

	/**
	 * Test each status tab filters to exactly its codes.
	 */
	public function test_status_filter_alone_matches_each_category(): void {
		$this->seed( 200 );
		$this->seed( 202 );
		$this->seed( 400 );
		$this->seed( 403 );
		$this->seed( 405 );
		$this->seed( 422 );
		$this->seed( 0, 'manual', 'Refused.' );
		$this->seed( 429 );
		$this->seed( 500 );

		$expected = [
			InstantIndexingOutcomes::CATEGORY_ACCEPTED => [ 200 ],
			InstantIndexingOutcomes::CATEGORY_PENDING  => [ 202 ],
			InstantIndexingOutcomes::CATEGORY_REJECTED => [ 0, 422, 405, 403, 400 ],
			InstantIndexingOutcomes::CATEGORY_LIMITED  => [ 429 ],
		];

		foreach ( $expected as $status => $codes ) {
			$query = $this->query( [ 'rk_status' => $status ] );

			$count = $query->filteredTotal();

			$this->assertSame( count( $codes ), $count, "the {$status} tab must match exactly its codes" );
			$this->assertSame( $codes, $this->column( $query->rows(), 'code' ), "the {$status} tab rows must be its codes only" );
		}

		$this->assertSame( 'all', $this->query( [ 'rk_status' => 'retry' ] )->filters()->status(), 'the retry category has no tab and normalizes to all' );
	}

	/**
	 * Test the source filter alone.
	 */
	public function test_source_filter_alone(): void {
		$this->seed( 200, 'auto' );
		$this->seed( 200, 'manual' );

		$query = $this->query( [ 'rk_source' => 'auto' ] );

		$this->assertSame( 1, $query->filteredTotal() );
		$this->assertSame( [ 'auto' ], $this->column( $query->rows(), 'source' ) );
	}

	/**
	 * Test an unknown source value normalizes to all rather than failing.
	 */
	public function test_unknown_source_normalizes_to_all(): void {
		$this->seed( 200, 'auto' );
		$this->seed( 200, 'manual' );

		$query = $this->query( [ 'rk_source' => 'robot' ] );

		$this->assertSame( LogFilters::SOURCE_ALL, $query->filters()->source() );
		$this->assertSame( 2, $query->filteredTotal() );
	}

	/**
	 * Test search plus status plus source together.
	 */
	public function test_combined_search_status_and_source(): void {
		$this->seed( 200, 'manual', 'Accepted.', '', 'https://example.com/alpha' );
		$this->seed( 400, 'manual', 'Rejected permanently.', '', 'https://example.com/alpha-two' );
		$this->seed( 400, 'auto', 'Rejected permanently.', '', 'https://example.com/alpha-three' );
		$this->seed( 400, 'manual', 'Rejected permanently.', '', 'https://example.com/beta' );

		$query = $this->query(
			[
				's'         => 'alpha',
				'rk_status' => InstantIndexingOutcomes::CATEGORY_REJECTED,
				'rk_source' => 'manual',
			]
		);

		$this->assertSame( 1, $query->filteredTotal() );
		$this->assertSame( 'https://example.com/alpha-two', $query->rows()[0]['url'] );
	}

	/**
	 * Test first page, middle page, last page and a page past the end.
	 */
	public function test_pagination_first_middle_last_and_past_end(): void {
		for ( $i = 0; $i < 25; $i++ ) {
			$this->seed( 200, 'manual', 'Accepted.', gmdate( 'Y-m-d H:i:s', 1767225600 + $i ) );
		}

		$first = $this->query( [ 'rk_per_page' => 10 ] );

		$this->assertSame( 10, count( $first->rows() ) );
		$this->assertSame( 'https://example.com/seed-25', $first->rows()[0]['url'], 'the newest row leads page one' );

		$middle = $this->query(
			[
				'rk_per_page' => 10,
				'rk_paged'    => 2,
			]
		);

		$this->assertSame( 10, count( $middle->rows() ) );
		$this->assertSame( 'https://example.com/seed-15', $middle->rows()[0]['url'] );

		$last = $this->query(
			[
				'rk_per_page' => 10,
				'rk_paged'    => 3,
			]
		);

		$this->assertSame( 5, count( $last->rows() ) );
		$this->assertSame( 'https://example.com/seed-5', $last->rows()[0]['url'] );
		$this->assertSame( 25, $last->filteredTotal(), 'totals stay correct on the last page' );
		$this->assertSame( 3, $last->pageCount() );

		$past = $this->query(
			[
				'rk_per_page' => 10,
				'rk_paged'    => 99,
			]
		);

		$this->assertSame( [], $past->rows(), 'a page past the end returns an empty row set' );
		$this->assertSame( 25, $past->filteredTotal(), 'totals stay correct past the end' );
		$this->assertSame( 3, $past->pageCount() );
	}

	/**
	 * Test paging never loads the whole table.
	 */
	public function test_paging_a_large_table_loads_one_page_only(): void {
		for ( $i = 0; $i < 250; $i++ ) {
			$this->seed( 0 === $i % 2 ? 200 : 429, 'manual', 'Accepted.', gmdate( 'Y-m-d H:i:s', 1767225600 + $i ) );
		}

		$query = $this->query();

		$this->assertSame( 20, count( $query->rows() ), 'one page must come back, not the whole table' );
		$this->assertSame( 250, $query->total(), 'the total must cover the full table' );
		$this->assertSame( 250, $query->filteredTotal() );
		$this->assertSame( 13, $query->pageCount(), '250 rows at 20 per page needs 13 pages' );

		$lastPage = $query->withPage( 13 );

		$this->assertSame( 10, count( $lastPage->rows() ) );
		$this->assertSame( 250, $lastPage->filteredTotal() );
	}

	/**
	 * Test an empty result set reports zero rows and safe totals.
	 */
	public function test_empty_result_returns_no_rows_and_safe_totals(): void {
		$this->seed( 200 );
		$this->seed( 400 );

		$query = $this->query( [ 's' => 'nothing-matches-this' ] );

		$this->assertSame( [], $query->rows() );
		$this->assertSame( 0, $query->filteredTotal() );
		$this->assertSame( 1, $query->pageCount(), 'an empty result still reports one page' );
		$this->assertSame( 2, $query->total(), 'the unfiltered total ignores the search' );
		$this->assertSame( 0, $query->statusCounts()['all'], 'status counts respect the search' );
	}

	/**
	 * Test a literal percent sign is escaped and never a wildcard.
	 */
	public function test_search_percent_is_escaped(): void {
		$this->seed( 200, 'manual', 'Progress 100% done.' );
		$this->seed( 200, 'manual', 'Progress 1000 done.' );

		$query = $this->query( [ 's' => '100%' ] );

		$this->assertSame( 1, $query->filteredTotal(), 'a literal percent must not match 1000' );
		$this->assertSame( 'Progress 100% done.', $query->rows()[0]['message'] );
		$this->assertPreparedContains( '\\%' );
	}

	/**
	 * Test a literal underscore is escaped and never a single character wildcard.
	 */
	public function test_search_underscore_is_escaped(): void {
		$this->seed( 200, 'manual', 'Accepted.', '', 'https://example.com/a_b' );
		$this->seed( 200, 'manual', 'Accepted.', '', 'https://example.com/axb' );

		$query = $this->query( [ 's' => 'a_b' ] );

		$this->assertSame( 1, $query->filteredTotal(), 'a literal underscore must not match axb' );
		$this->assertSame( 'https://example.com/a_b', $query->rows()[0]['url'] );
		$this->assertPreparedContains( '\\_' );
	}

	/**
	 * Test a dangerous value stays a value, quoted by prepare.
	 */
	public function test_dangerous_search_value_is_quoted_and_matches_nothing(): void {
		$this->seed( 200 );

		$query = $this->query( [ 's' => "x' OR '1'='1" ] );

		$this->assertSame( 0, $query->filteredTotal(), 'an injected OR must not match every row' );
		$this->assertSame( [], $query->rows() );

		$image = implode( "\n", $this->db->interpolated );

		$this->assertStringNotContainsString( "'1'='1", $image, 'the raw dangerous value must never be interpolated unquoted' );
		$this->assertStringContainsString( "\\'1\\'=\\'1", $image, 'prepare must quote every embedded quote' );
		$this->assertSame( 0, $this->db->unpreparedReads, 'every read must be prepared' );
	}

	/**
	 * Test every read goes through prepare with placeholders.
	 */
	public function test_every_read_is_parameterized(): void {
		$this->seed( 200, 'manual', 'Accepted.', '', 'https://example.com/o-brien' );

		$query = $this->query(
			[
				's'         => "o'brien",
				'rk_source' => 'manual',
			]
		);

		$query->rows();
		$query->total();
		$query->filteredTotal();
		$query->statusCounts();
		$query->sourceCounts();

		$this->assertSame( 0, $this->db->unpreparedReads, 'no read may bypass prepare' );
		$this->assertGreaterThanOrEqual( 5, count( $this->db->prepared ) );

		$templates = implode( "\n", $this->db->prepared );
		$image     = implode( "\n", $this->db->interpolated );

		$this->assertStringNotContainsString( "o'brien", $templates, 'the raw search must never enter the SQL template' );
		$this->assertStringNotContainsString( "o'brien", $image, 'the raw search must never be interpolated unquoted' );
		$this->assertStringContainsString( 'LIKE %s', $templates, 'the search must travel as a placeholder' );
		$this->assertStringContainsString( 'source = %s', $templates );
	}

	/**
	 * Test clamped page and page size inputs.
	 */
	public function test_page_and_page_size_inputs_are_clamped(): void {
		$query = $this->query(
			[
				'rk_paged'    => '0',
				'rk_per_page' => '0',
			]
		);

		$this->assertSame( 1, $query->filters()->page() );
		$this->assertSame( 1, $query->filters()->perPage() );

		$negative = $this->query(
			[
				'rk_paged'    => '-7',
				'rk_per_page' => '-3',
			]
		);

		$this->assertSame( 1, $negative->filters()->page() );
		$this->assertSame( 1, $negative->filters()->perPage() );

		$oversized = $this->query( [ 'rk_per_page' => '9999' ] );

		$this->assertSame( LogFilters::MAX_PER_PAGE, $oversized->filters()->perPage() );
		$this->assertSame( 200, LogFilters::MAX_PER_PAGE );

		$garbage = $this->query(
			[
				'rk_paged'    => 'abc',
				'rk_per_page' => 'abc',
			]
		);

		$this->assertSame( 1, $garbage->filters()->page() );
		$this->assertSame( LogQuery::PER_PAGE, $garbage->filters()->perPage() );

		$this->assertSame( 1, $query->withPage( 0 )->filters()->page(), 'withPage clamps to at least one' );
	}

	/**
	 * Test filter normalization, trim, cap and unknown status fallback.
	 */
	public function test_filter_normalization(): void {
		$query = $this->query(
			[
				's'         => str_repeat( 'a', 120 ),
				'rk_status' => 'retry',
				'rk_source' => 42,
			]
		);

		$this->assertSame( 100, strlen( $query->filters()->search() ), 'the search is capped at 100 characters' );
		$this->assertSame( 'all', $query->filters()->status() );
		$this->assertSame( 'all', $query->filters()->source() );
		$this->assertTrue( $query->filters()->hasFilter(), 'a search alone is still a filter' );
	}

	/**
	 * Test the total count ignores filters.
	 */
	public function test_total_count_ignores_filters(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->seed( 200 );
		}

		$query = $this->query( [ 'rk_status' => InstantIndexingOutcomes::CATEGORY_REJECTED ] );

		$this->assertSame( 5, $query->total() );
		$this->assertSame( 0, $query->filteredTotal() );
	}

	/**
	 * Test status counts respect search and source but not status.
	 */
	public function test_status_counts_respect_search_and_source_but_not_status(): void {
		$this->seed( 200, 'manual', 'Accepted.' );
		$this->seed( 200, 'manual', 'Accepted.' );
		$this->seed( 202, 'auto', 'Key pending.' );
		$this->seed( 429, 'manual', 'Rate limited.' );
		$this->seed( 503, 'manual', 'Retry later.' );

		$rejected = $this->query(
			[
				's'         => 'seed',
				'rk_source' => 'manual',
				'rk_status' => InstantIndexingOutcomes::CATEGORY_REJECTED,
			]
		);
		$counts   = $rejected->statusCounts();

		$this->assertSame( 0, $rejected->filteredTotal(), 'the rejected tab itself has no rows here' );
		$this->assertSame(
			[
				'all'      => 4,
				'accepted' => 2,
				'pending'  => 0,
				'rejected' => 0,
				'limited'  => 1,
				'retry'    => 1,
			],
			$counts,
			'the tab counts must ignore the active status but honour search and source'
		);

		$limited = $this->query(
			[
				's'         => 'seed',
				'rk_source' => 'manual',
				'rk_status' => InstantIndexingOutcomes::CATEGORY_LIMITED,
			]
		);

		$this->assertSame( $counts, $limited->statusCounts(), 'switching tabs must not change the other tab counts' );
	}

	/**
	 * Test source counts respect search and status but not source.
	 */
	public function test_source_counts_respect_search_and_status_but_not_source(): void {
		$this->seed( 429, 'manual', 'Rate limited.' );
		$this->seed( 429, 'auto', 'Rate limited.' );
		$this->seed( 200, 'manual', 'Accepted.' );

		$query  = $this->query(
			[
				's'         => 'seed',
				'rk_source' => 'manual',
				'rk_status' => InstantIndexingOutcomes::CATEGORY_LIMITED,
			]
		);
		$counts = $query->sourceCounts();

		ksort( $counts );

		$this->assertSame(
			[
				'auto'   => 1,
				'manual' => 1,
			],
			$counts,
			'the source counts ignore the active source but honour search and status'
		);

		$this->assertSame( [], $this->query( [ 's' => 'nothing-matches' ] )->sourceCounts(), 'the search must narrow the source counts' );
	}

	/**
	 * Test the counts cover the whole table, beyond the old 200 row window.
	 */
	public function test_counts_cover_the_full_table_beyond_the_old_read_limit(): void {
		for ( $i = 0; $i < 250; $i++ ) {
			$code = 0 === $i % 5 ? 400 : 200;

			$this->seed( $code, 0 === $i % 2 ? 'auto' : 'manual', 'Row.' );
		}

		$all = LogQuery::fromInput( [] );

		$this->assertSame( 250, $all->total() );
		$this->assertSame( 250, $all->statusCounts()['all'], 'the tab counts must not stop at 200 rows' );
		$this->assertSame( 50, $all->statusCounts()['rejected'] );
		$this->assertSame( 200, $all->statusCounts()['accepted'] );

		$sources = $all->sourceCounts();

		ksort( $sources );

		$this->assertSame(
			[
				'auto'   => 125,
				'manual' => 125,
			],
			$sources
		);
		$this->assertSame( 13, $all->pageCount() );

		$stats = InstantIndexingOutcomes::statsFromCounts( $all->statusCounts() );

		$this->assertSame(
			[
				'total'    => 250,
				'accepted' => 200,
				'rejected' => 50,
				'limited'  => 0,
			],
			$stats,
			'the stats strip must describe the full table'
		);
	}

	/**
	 * Test the stats source is unfiltered while the table can be filtered.
	 */
	public function test_stats_source_is_unfiltered(): void {
		$this->seed( 200 );
		$this->seed( 400 );
		$this->seed( 429 );

		$filtered   = $this->query( [ 'rk_status' => InstantIndexingOutcomes::CATEGORY_REJECTED ] );
		$unfiltered = LogQuery::fromInput( [] );

		$this->assertSame( 1, $filtered->filteredTotal() );

		$stats = InstantIndexingOutcomes::statsFromCounts( $unfiltered->statusCounts() );

		$this->assertSame(
			[
				'total'    => 3,
				'accepted' => 1,
				'rejected' => 1,
				'limited'  => 1,
			],
			$stats,
			'the stats strip must describe every row, not the filtered view'
		);
	}

	/**
	 * Test every code agrees between the SQL map and the PHP categoriser.
	 *
	 * The query layer builds its status predicate from the same map, so
	 * this pins the two directions of the mapping together for every code
	 * that can be stored in the SMALLINT UNSIGNED column.
	 */
	public function test_status_predicates_agree_with_the_categoriser(): void {
		$map = InstantIndexingOutcomes::categoryCodes();

		for ( $code = 0; $code <= 599; $code++ ) {
			$category = InstantIndexingOutcomes::categoryFor( $code );
			$inMap    = null;

			foreach ( $map as $mappedCategory => $codes ) {
				if ( in_array( $code, $codes, true ) ) {
					$inMap = $mappedCategory;

					break;
				}
			}

			if ( InstantIndexingOutcomes::CATEGORY_RETRY === $category ) {
				$this->assertNull( $inMap, "code {$code} is retry and must not match any tab predicate" );

				continue;
			}

			$this->assertSame( $category, $inMap, "code {$code} must be covered by exactly the {$category} predicate" );
		}

		$this->assertSame( [], LogFilters::codesFor( LogFilters::STATUS_ALL ) );
		$this->assertSame( [], LogFilters::codesFor( 'retry' ) );
	}

	/**
	 * Test a missing table fails open.
	 */
	public function test_missing_table_fails_open(): void {
		$this->seed( 200 );
		$this->db->tableExists = false;
		LogTable::resetCache();

		$query = $this->query();

		$this->assertSame( [], $query->rows() );
		$this->assertSame( 0, $query->total() );
		$this->assertSame( 0, $query->filteredTotal() );
		$this->assertSame( 1, $query->pageCount() );
		$this->assertSame( [], $query->sourceCounts() );
		$this->assertSame( 0, $query->statusCounts()['all'] );
	}

	/**
	 * Test no database fails open.
	 */
	public function test_no_database_fails_open(): void {
		unset( $GLOBALS['wpdb'] );

		$query = $this->query();

		$this->assertSame( [], $query->rows() );
		$this->assertSame( 0, $query->total() );
		$this->assertSame( 0, $query->filteredTotal() );
		$this->assertSame( [], $query->sourceCounts() );
	}

	/**
	 * Assert at least one interpolated statement contains a fragment.
	 *
	 * @param string $fragment Fragment to find.
	 * @return void
	 */
	private function assertPreparedContains( string $fragment ): void {
		$found = false;

		foreach ( $this->db->interpolated as $statement ) {
			if ( str_contains( $statement, $fragment ) ) {
				$found = true;

				break;
			}
		}

		$this->assertTrue( $found, "a prepared statement must contain {$fragment}" );
	}
}
