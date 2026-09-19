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
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\KeywordIndex;

/**
 * Keyword Index Test.
 */
final class KeywordIndexTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		Functions\when( 'get_the_title' )->alias( static fn ( int $id ): string => 'Post ' . $id );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
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
	 * Test duplicate titles collapse to one entry.
	 */
	public function test_duplicate_titles_collapse(): void {
		$db      = new AnalysisKeywordIndexFakeDb();
		$db->ids = [ 11, 11 ];

		$this->assertSame( [ 'Post 11' ], ( new KeywordIndex( $db ) )->usedElsewhere( 'red apples', 5 ) );
	}
}
