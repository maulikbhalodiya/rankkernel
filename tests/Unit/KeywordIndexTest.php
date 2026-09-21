<?php
/**
 * Keyword uniqueness lookup tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use RankKernel\Modules\Analysis\KeywordIndex;
use RankKernel\Rest\AnalysisController;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Keyword Index Test.
 *
 * Extends the fully qualified TestCase so the coding standards recognise this
 * as a test class and allow the fixture to restore the $wpdb global.
 */
final class KeywordIndexTest extends \PHPUnit\Framework\TestCase {
	/**
	 * Whether the global $wpdb existed before the test.
	 *
	 * @var bool
	 */
	private bool $wpdbWasSet = false;

	/**
	 * The global $wpdb value before the test.
	 *
	 * @var mixed
	 */
	private mixed $wpdb = null;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$this->wpdbWasSet = array_key_exists( 'wpdb', $GLOBALS );
		$this->wpdb       = $GLOBALS['wpdb'] ?? null;

		Functions\when( 'get_the_title' )->alias( static fn ( int $id ): string => 'Post ' . $id );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_post' )->justReturn( new WP_Post() );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'number_format_i18n' )->alias( static fn ( float $number, int $decimals = 0 ): string => number_format( $number, $decimals ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				if ( PHP_URL_HOST !== $component ) {
					return false;
				}

				return preg_match( '~^[a-z][a-z0-9+.-]*://([^/?#]+)~i', $url, $matches )
					? strtolower( $matches[1] )
					: '';
			}
		);
	}

	/**
	 * Tear down the test fixture and restore the $wpdb global.
	 */
	protected function tearDown(): void {
		if ( $this->wpdbWasSet ) {
			$GLOBALS['wpdb'] = $this->wpdb;
		} else {
			unset( $GLOBALS['wpdb'] );
		}

		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test titles come back for the ids the query returned.
	 */
	public function test_returns_titles_for_found_rows(): void {
		$db      = new AnalysisKeywordIndexFakeDb();
		$db->ids = [ 11, 12 ];

		$titles = ( new KeywordIndex( $db ) )->usedElsewhere( 'red apples', 5 );

		$this->assertSame( [ 'Post 11', 'Post 12' ], $titles );
	}

	/**
	 * Test the query excludes the post being edited and escapes the keyword.
	 */
	public function test_query_excludes_current_post_and_escapes_the_keyword(): void {
		$db      = new AnalysisKeywordIndexFakeDb();
		$db->ids = [];

		( new KeywordIndex( $db ) )->usedElsewhere( 'red_apples', 5 );

		$this->assertCount( 1, $db->queries );

		$query = $db->queries[0];

		$this->assertStringContainsString( '_rankkernel_meta_data', $query );
		$this->assertStringContainsString( 'red\\_apples', $query );
		$this->assertStringContainsString( '!= 5', $query );
	}

	/**
	 * Test an empty keyword reports nothing rather than querying.
	 */
	public function test_empty_keyword_queries_nothing(): void {
		$db      = new AnalysisKeywordIndexFakeDb();
		$db->ids = [ 11 ];

		$this->assertSame( [], ( new KeywordIndex( $db ) )->usedElsewhere( '   ', 5 ) );
		$this->assertSame( [], $db->queries );
	}

	/**
	 * Test a missing database fails quietly.
	 */
	public function test_missing_database_fails_quietly(): void {
		unset( $GLOBALS['wpdb'] );

		$this->assertSame( [], ( new KeywordIndex() )->usedElsewhere( 'red apples', 5 ) );
	}

	/**
	 * Test the query scopes candidates to published posts or the current user's own.
	 */
	public function test_query_scopes_candidates_by_status_and_author(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );

		$db      = new AnalysisKeywordIndexFakeDb();
		$db->ids = [];

		( new KeywordIndex( $db ) )->usedElsewhere( 'red apples', 5 );

		$this->assertCount( 1, $db->queries );

		$query = $db->queries[0];

		$this->assertStringContainsString( 'post_status', $query );
		$this->assertStringContainsString( "'trash'", $query );
		$this->assertStringContainsString( "'auto-draft'", $query );
		$this->assertStringContainsString( "'inherit'", $query );
		$this->assertStringContainsString( "'publish'", $query );
		$this->assertStringContainsString( 'post_author = 7', $query );
	}

	/**
	 * Test the LIKE filter drops candidate rows that do not hold the keyword.
	 */
	public function test_keyword_filters_candidate_rows(): void {
		$db       = new AnalysisKeywordIndexFakeDb();
		$db->rows = [
			11 => 'red apples and pears',
			12 => 'blue cars',
		];

		$titles = ( new KeywordIndex( $db ) )->usedElsewhere( 'red apples', 5 );

		$this->assertSame( [ 'Post 11' ], $titles );
	}

	/**
	 * Test duplicate titles collapse to one entry.
	 */
	public function test_duplicate_titles_collapse(): void {
		$db      = new AnalysisKeywordIndexFakeDb();
		$db->ids = [ 11, 11 ];

		$this->assertSame( [ 'Post 11' ], ( new KeywordIndex( $db ) )->usedElsewhere( 'red apples', 5 ) );
	}

	/**
	 * Test the controller looks up the primary keyword for the edited post.
	 *
	 * The lookup has to be reachable from analyze(), carrying the first keyword
	 * and the post being edited. Only the primary keyword is looked up, so a
	 * supporting keyword never produces a second query.
	 */
	public function test_controller_looks_up_the_primary_keyword_for_the_post(): void {
		$db      = new AnalysisKeywordIndexFakeDb();
		$index   = new KeywordIndex( $db );
		$request = new WP_REST_Request();

		$request->set_param( 'post_id', 5 );
		$request->set_param( 'content', '<p>Red apples are best in autumn. Red apples keep well.</p>' );
		$request->set_param( 'keywords', [ 'red apples', 'autumn harvest' ] );

		$result = ( new AnalysisController( null, $index ) )->analyze( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertCount( 1, $db->queries );
		$this->assertStringContainsString( "meta_value LIKE '%red apples%'", $db->queries[0] );
		$this->assertStringContainsString( 'pm.post_id != 5', $db->queries[0] );
		$this->assertStringNotContainsString( 'autumn harvest', $db->queries[0] );
	}
}
