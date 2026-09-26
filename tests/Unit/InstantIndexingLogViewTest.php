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
	 * Fake database.
	 *
	 * @var InstantIndexingFakeDb
	 */
	private InstantIndexingFakeDb $db;

	/**
	 * Screen URL under test.
	 *
	 * @var string
	 */
	private string $baseUrl = 'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing';

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
	 * Seed one log row.
	 *
	 * @param int    $code    Status code.
	 * @param string $source  Source value.
	 * @param string $message Message value.
	 * @return int Assigned id.
	 */
	private function seed( int $code, string $source = 'manual', string $message = 'Message.' ): int {
		return $this->db->seed(
			[
				'url'     => 'https://example.com/seed-' . $code . '-' . $source . '-' . $this->db->nextId,
				'code'    => $code,
				'source'  => $source,
				'message' => $message,
			]
		);
	}

	/**
	 * Build the view from raw query arguments.
	 *
	 * @param array<string, mixed> $query Raw query arguments.
	 * @return InstantIndexingLogView The result.
	 */
	private function view( array $query = [] ): InstantIndexingLogView {
		return InstantIndexingLogView::fromQuery( $query, $this->baseUrl );
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
	 * Test the SQL facing code map agrees with the categoriser.
	 *
	 * Every code the tab predicate map covers must categorise as exactly
	 * that category, and every other code must not be covered, so a
	 * mapping drift fails here before it can change a displayed number.
	 */
	public function test_category_codes_agree_with_the_categoriser(): void {
		$map = InstantIndexingOutcomes::categoryCodes();

		foreach ( $map as $category => $codes ) {
			foreach ( $codes as $code ) {
				$this->assertSame( $category, InstantIndexingOutcomes::categoryFor( (int) $code ) );
			}
		}

		$this->assertSame( [ 0, 400, 403, 405, 422 ], $map[ InstantIndexingOutcomes::CATEGORY_REJECTED ] );
		$this->assertSame( [ 429 ], $map[ InstantIndexingOutcomes::CATEGORY_LIMITED ] );
	}

	/**
	 * Test the stats strip derives from the category counts.
	 */
	public function test_stats_derive_from_the_category_counts(): void {
		$this->assertSame(
			[
				'total'    => 6,
				'accepted' => 2,
				'rejected' => 2,
				'limited'  => 1,
			],
			InstantIndexingOutcomes::statsFromCounts(
				[
					'all'      => 6,
					'accepted' => 1,
					'pending'  => 1,
					'rejected' => 2,
					'limited'  => 1,
					'retry'    => 1,
				]
			)
		);
	}

	/**
	 * Test unknown query values fall back to the unfiltered state.
	 */
	public function test_from_query_sanitizes_unknown_values(): void {
		$view = $this->view(
			[
				's'         => '  hello  ',
				'rk_source' => 'robot',
				'rk_status' => 'retry',
				'rk_paged'  => '0',
			]
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
		$this->seed( 200, 'manual', 'Accepted.' );
		$this->seed( 400, 'manual', 'Rejected permanently, retrying will not help.' );

		$view = $this->view( [ 's' => 'permanently' ] );

		$this->assertSame( 1, $view->totalFiltered() );
		$this->assertSame( 400, $view->pageRows()[0]['code'] );
	}

	/**
	 * Test the source plus status filters narrow the rows.
	 */
	public function test_source_and_status_filters_narrow_the_rows(): void {
		$this->seed( 200, 'manual' );
		$this->seed( 200, 'auto' );
		$this->seed( 202, 'manual' );

		$view = $this->view(
			[
				'rk_source' => 'manual',
				'rk_status' => 'accepted',
			]
		);

		$this->assertSame( 1, $view->totalFiltered() );
		$this->assertSame( 'manual', $view->pageRows()[0]['source'] );
	}

	/**
	 * Test the tabs carry real counts over the searched rows.
	 */
	public function test_tabs_carry_real_counts(): void {
		$this->seed( 200, 'manual' );
		$this->seed( 200, 'manual' );
		$this->seed( 202, 'manual' );
		$this->seed( 429, 'manual' );

		$view   = $this->view();
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
		for ( $i = 0; $i < 25; $i++ ) {
			$this->seed( 200 );
		}

		$view = $this->view( [ 'rk_paged' => '2' ] );

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
		$this->seed( 200 );

		$view = $this->view( [ 'rk_paged' => '99' ] );

		$this->assertSame( 1, $view->page() );
		$this->assertCount( 1, $view->pageRows() );
		$this->assertFalse( $view->pagination()['show'] );
	}

	/**
	 * Test pagination and tab links preserve every filter.
	 *
	 * This is the no JavaScript path: the URL query parameters stay the
	 * source of truth, so paging must carry search, source and status.
	 */
	public function test_pagination_and_tab_links_preserve_every_filter(): void {
		for ( $i = 0; $i < 25; $i++ ) {
			$this->seed( 429, 'auto' );
		}

		$view = $this->view(
			[
				's'         => 'seed',
				'rk_source' => 'auto',
				'rk_status' => 'limited',
			]
		);

		$links = [ $view->pagination()['nextUrl'] ];
		$tabs  = $view->tabs();

		foreach ( $tabs as $tab ) {
			$links[] = $tab['url'];
		}

		foreach ( $links as $link ) {
			$this->assertStringContainsString( 's=seed', $link );
			$this->assertStringContainsString( 'rk_source=auto', $link );
		}

		$this->assertStringContainsString( 'rk_status=limited', $view->pagination()['nextUrl'] );
		$this->assertStringContainsString( 'rk_status=accepted', $tabs[1]['url'] );
	}

	/**
	 * Test pageRows exposes the row id as an integer.
	 *
	 * The retry form posts the id alone, so the view must surface it on
	 * every rendered row without dropping any existing key.
	 */
	public function test_page_rows_expose_the_id_as_an_integer(): void {
		$id = $this->seed( 200, 'manual', 'Accepted.' );

		$row = $this->view()->pageRows()[0];

		$this->assertSame( $id, $row['id'], 'the rendered row must carry its stored id' );
		$this->assertIsInt( $row['id'] );
		$this->assertSame(
			[
				'id',
				'url',
				'code',
				'source',
				'time',
				'message',
				'category',
				'statusLabel',
				'statusPill',
				'sourceLabel',
				'sourcePill',
			],
			array_keys( $row ),
			'the id must arrive alongside every existing display key'
		);
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
