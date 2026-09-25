<?php
/**
 * Instant Indexing log view tests, categories plus stats plus filters.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\InstantIndexingLogView;
use RankKernel\Admin\InstantIndexingOutcomes;
use RankKernel\Modules\InstantIndexing\IndexNowClient;

/**
 * Instant Indexing Log View Test.
 */
final class InstantIndexingLogViewTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( '__' )->alias( static fn( string $v ): string => $v );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build one normalized log row.
	 *
	 * @param int    $code    Status code.
	 * @param string $source  Source value.
	 * @param string $message Message value.
	 * @return array{url: string, code: int, source: string, time: string, message: string} The result.
	 */
	private function row( int $code, string $source = 'manual', string $message = 'Message.' ): array {
		return [
			'url'     => 'https://example.com/' . $code . $source,
			'code'    => $code,
			'source'  => $source,
			'time'    => '2026-09-24 06:58:00',
			'message' => $message,
		];
	}

	/**
	 * Test every stored code maps to the designed category.
	 */
	public function test_category_for_maps_every_code(): void {
		$this->assertSame( 'accepted', InstantIndexingOutcomes::categoryFor( 200 ) );
		$this->assertSame( 'pending', InstantIndexingOutcomes::categoryFor( 202 ) );
		$this->assertSame( 'rejected', InstantIndexingOutcomes::categoryFor( 400 ) );
		$this->assertSame( 'rejected', InstantIndexingOutcomes::categoryFor( 403 ) );
		$this->assertSame( 'rejected', InstantIndexingOutcomes::categoryFor( 405 ) );
		$this->assertSame( 'rejected', InstantIndexingOutcomes::categoryFor( 422 ) );
		$this->assertSame( 'rejected', InstantIndexingOutcomes::categoryFor( 0 ) );
		$this->assertSame( 'limited', InstantIndexingOutcomes::categoryFor( 429 ) );
		$this->assertSame( 'retry', InstantIndexingOutcomes::categoryFor( 500 ) );
		$this->assertSame( 'retry', InstantIndexingOutcomes::categoryFor( 503 ) );
	}

	/**
	 * Test the admin permanent codes never drift from the client list.
	 *
	 * The client owns the protocol list privately, so the two copies are
	 * read through reflection and compared, and any drift fails here.
	 */
	public function test_permanent_code_lists_stay_identical(): void {
		$outcomes = new \ReflectionClassConstant( InstantIndexingOutcomes::class, 'PERMANENT_CODES' );
		$client   = new \ReflectionClassConstant( IndexNowClient::class, 'PERMANENT_CODES' );

		$this->assertSame(
			$client->getValue(),
			$outcomes->getValue(),
			'the admin permanent codes must match the client permanent codes'
		);
	}

	/**
	 * Test the stats derive from the rows with the specified buckets.
	 */
	public function test_stats_derive_from_the_rows(): void {
		$rows = [
			$this->row( 200 ),
			$this->row( 202 ),
			$this->row( 400 ),
			$this->row( 0 ),
			$this->row( 429 ),
			$this->row( 503 ),
		];

		$this->assertSame(
			[
				'total'    => 6,
				'accepted' => 2,
				'rejected' => 2,
				'limited'  => 1,
			],
			InstantIndexingOutcomes::stats( $rows )
		);
	}

	/**
	 * Test unknown query values fall back to the unfiltered state.
	 */
	public function test_from_query_sanitizes_unknown_values(): void {
		$view = InstantIndexingLogView::fromQuery(
			[],
			[
				's'         => '  hello  ',
				'rk_source' => 'robot',
				'rk_status' => 'retry',
				'rk_paged'  => '0',
			],
			'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing'
		);

		$this->assertSame( 'hello', $view->search() );
		$this->assertSame( 'all', $view->source() );
		$this->assertSame( 'all', $view->status() );
		$this->assertSame( 1, $view->page() );
		$this->assertTrue( $view->hasFilter() );
	}

	/**
	 * Test the search matches the message as well as the URL.
	 */
	public function test_search_matches_url_and_message(): void {
		$rows = [
			$this->row( 200, 'manual', 'Accepted.' ),
			$this->row( 400, 'manual', 'Rejected permanently, retrying will not help.' ),
		];

		$view = InstantIndexingLogView::fromQuery(
			$rows,
			[ 's' => 'permanently' ],
			'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing'
		);

		$this->assertSame( 1, $view->totalFiltered() );
		$this->assertSame( 400, $view->pageRows()[0]['code'] );
	}

	/**
	 * Test the source plus status filters narrow the rows.
	 */
	public function test_source_and_status_filters_narrow_the_rows(): void {
		$rows = [
			$this->row( 200, 'manual' ),
			$this->row( 200, 'auto' ),
			$this->row( 202, 'manual' ),
		];

		$view = InstantIndexingLogView::fromQuery(
			$rows,
			[
				'rk_source' => 'manual',
				'rk_status' => 'accepted',
			],
			'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing'
		);

		$this->assertSame( 1, $view->totalFiltered() );
		$this->assertSame( 'manual', $view->pageRows()[0]['source'] );
	}

	/**
	 * Test the tabs carry real counts over the searched rows.
	 */
	public function test_tabs_carry_real_counts(): void {
		$rows = [
			$this->row( 200, 'manual' ),
			$this->row( 200, 'manual' ),
			$this->row( 202, 'manual' ),
			$this->row( 429, 'manual' ),
		];

		$view = InstantIndexingLogView::fromQuery(
			$rows,
			[],
			'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing'
		);

		$counts = [];

		foreach ( $view->tabs() as $tab ) {
			$counts[ $tab['key'] ] = $tab['count'];
		}

		$this->assertSame(
			[
				'all'      => 4,
				'accepted' => 2,
				'pending'  => 1,
				'rejected' => 0,
				'limited'  => 1,
			],
			$counts
		);
	}

	/**
	 * Test pagination slices the rows and labels the range.
	 */
	public function test_pagination_slices_the_rows(): void {
		$rows = [];

		for ( $i = 0; $i < 25; $i++ ) {
			$rows[] = $this->row( 200 );
		}

		$view = InstantIndexingLogView::fromQuery(
			$rows,
			[ 'rk_paged' => '2' ],
			'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing'
		);

		$this->assertSame( 2, $view->page() );
		$this->assertCount( 5, $view->pageRows() );
		$this->assertSame(
			[
				'from'  => 21,
				'to'    => 25,
				'total' => 25,
			],
			$view->showing()
		);

		$pagination = $view->pagination();

		$this->assertTrue( $pagination['show'] );
		$this->assertNotSame( '', $pagination['prevUrl'] );
		$this->assertSame( '', $pagination['nextUrl'] );
	}

	/**
	 * Test a page past the end clamps to the last page.
	 */
	public function test_page_past_the_end_clamps_to_the_last_page(): void {
		$view = InstantIndexingLogView::fromQuery(
			[ $this->row( 200 ) ],
			[ 'rk_paged' => '99' ],
			'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing'
		);

		$this->assertSame( 1, $view->page() );
		$this->assertCount( 1, $view->pageRows() );
		$this->assertFalse( $view->pagination()['show'] );
	}

	/**
	 * Test every notice code maps to its notice, unknown codes to none.
	 */
	public function test_notice_for_maps_every_code(): void {
		$this->assertSame( 'error', (string) ( InstantIndexingLogView::noticeFor( 'host' )['type'] ?? '' ) );
		$this->assertSame( 'error', (string) ( InstantIndexingLogView::noticeFor( 'mixed' )['type'] ?? '' ) );
		$this->assertSame( 'error', (string) ( InstantIndexingLogView::noticeFor( 'empty' )['type'] ?? '' ) );
		$this->assertSame( 'error', (string) ( InstantIndexingLogView::noticeFor( 'disabled' )['type'] ?? '' ) );
		$this->assertSame( 'error', (string) ( InstantIndexingLogView::noticeFor( 'unvalidated' )['type'] ?? '' ) );
		$this->assertSame( 'info', (string) ( InstantIndexingLogView::noticeFor( 'cleared' )['type'] ?? '' ) );
		$this->assertNull( InstantIndexingLogView::noticeFor( 'nope' ) );
		$this->assertNull( InstantIndexingLogView::noticeFor( '' ) );
	}
}
