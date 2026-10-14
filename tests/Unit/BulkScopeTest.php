<?php
/**
 * Bulk scope helpers, bounded id slices and counts for bulk rules.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\BulkScope;

/**
 * Bulk scope test.
 */
final class BulkScopeTest extends TestCase {
	/**
	 * Set up Brain Monkey.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Post counts sum the in scope statuses only.
	 */
	public function test_post_count_sums_in_scope_statuses(): void {
		Functions\when( 'wp_count_posts' )->alias(
			static function () {
				return (object) [
					'publish'    => 3,
					'draft'      => 2,
					'pending'    => 1,
					'private'    => 1,
					'future'     => 1,
					'trash'      => 9,
					'auto-draft' => 9,
					'inherit'    => 9,
				];
			}
		);

		$this->assertSame( 8, BulkScope::count( 'post', 'product' ) );
	}

	/**
	 * Empty counters count zero instead of warning on missing statuses.
	 */
	public function test_post_count_with_empty_counters_is_zero(): void {
		Functions\when( 'wp_count_posts' )->justReturn( (object) [] );

		$this->assertSame( 0, BulkScope::count( 'post', 'product' ) );
	}

	/**
	 * Term counts use the term query counter behind a taxonomy guard.
	 */
	public function test_term_count_uses_guarded_term_query(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_terms' )->justReturn( 4 );

		$this->assertSame( 4, BulkScope::count( 'term', 'category' ) );
	}

	/**
	 * Unknown taxonomies count zero without touching the query.
	 */
	public function test_term_count_for_unknown_taxonomy_is_zero(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( false );

		$this->assertSame( 0, BulkScope::count( 'term', 'nope' ) );
	}

	/**
	 * Post slices stay bounded and ordered by id.
	 */
	public function test_post_ids_slice_is_bounded(): void {
		$captured = [];

		Functions\when( 'get_posts' )->alias(
			static function ( array $args ) use ( &$captured ) {
				$captured = $args;

				return [ 11, 12 ];
			}
		);

		$this->assertSame( [ 11, 12 ], BulkScope::ids( 'post', 'product', 20, 50 ) );
		$this->assertSame( 50, $captured['posts_per_page'] );
		$this->assertSame( 20, $captured['offset'] );
		$this->assertSame( [ 'publish', 'draft', 'pending', 'private', 'future' ], $captured['post_status'] );
	}

	/**
	 * Term slices stay bounded behind the taxonomy guard.
	 */
	public function test_term_ids_slice_is_bounded(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_terms' )->alias(
			static function ( array $args ) {
				if ( isset( $args['count'] ) ) {
					return 0;
				}

				return [ 31 ];
			}
		);

		$this->assertSame( [ 31 ], BulkScope::ids( 'term', 'category', 0, 50 ) );
	}
}
