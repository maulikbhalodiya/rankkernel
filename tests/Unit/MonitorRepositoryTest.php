<?php
/**
 * 404 repository tests over the in memory wpdb double.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\MonitorRepository;

/**
 * Monitor Repository Test.
 */
final class MonitorRepositoryTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var MonitorFakeDb
	 */
	private MonitorFakeDb $db;

	/**
	 * Repository under test.
	 *
	 * @var MonitorRepository
	 */
	private MonitorRepository $repo;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db   = new MonitorFakeDb();
		$this->repo = new MonitorRepository( $this->db );

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-01 12:00:00' );
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
	 * Test record inserts then increments without duplicate row.
	 */
	public function test_record_inserts_then_increments_without_duplicate_row(): void {
		$hash = hash( 'sha256', '/missing-page' );

		$this->assertSame( 'insert', $this->repo->record( $hash, '/missing-page' ) );
		$this->assertCount( 1, $this->db->rows );

		$this->assertSame( 'update', $this->repo->record( $hash, '/missing-page' ) );
		$this->assertCount( 1, $this->db->rows );

		$row = $this->repo->findByHash( $hash );

		$this->assertIsArray( $row );
		$this->assertSame( 2, (int) $row['hits'] );
		$this->assertSame( '/missing-page', $row['uri'] );
		$this->assertSame( '2026-06-01 12:00:00', $row['last_accessed'] );
	}

	/**
	 * Test record rejects empty identity.
	 */
	public function test_record_rejects_empty_identity(): void {
		$this->assertSame( '', $this->repo->record( '', '' ) );
		$this->assertCount( 0, $this->db->rows );
	}

	/**
	 * Test record race on unique hash falls back to update.
	 */
	public function test_record_race_on_unique_hash_falls_back_to_update(): void {
		$hash = hash( 'sha256', '/race' );

		$this->assertSame( 'insert', $this->repo->record( $hash, '/race' ) );

		$rows  = $this->db->rows;
		$first = reset( $rows );

		$this->assertIsArray( $first );

		$id = (int) $first['id'];

		unset( $this->db->rows[ $id ] );
		$this->db->seed(
			[
				'uri_hash' => $hash,
				'uri'      => '/race',
				'hits'     => 5,
			]
		);

		$this->assertSame( 'update', $this->repo->record( $hash, '/race' ) );

		$row = $this->repo->findByHash( $hash );

		$this->assertIsArray( $row );
		$this->assertSame( 6, (int) $row['hits'] );
	}

	/**
	 * Test count and usage.
	 */
	public function test_count_and_usage(): void {
		$this->assertSame( 0, $this->repo->count() );

		$this->repo->record( hash( 'sha256', '/a' ), '/a' );
		$this->repo->record( hash( 'sha256', '/b' ), '/b' );

		$this->assertSame( 2, $this->repo->count() );

		$usage = $this->repo->usage( 1000 );

		$this->assertSame( 2, $usage['count'] );
		$this->assertSame( 1000, $usage['max'] );
		$this->assertSame( 0.2, $usage['percent'] );
	}

	/**
	 * Test paginate search sort and filters.
	 */
	public function test_paginate_search_sort_and_filters(): void {
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/blog/old-post' ),
				'uri'           => '/blog/old-post',
				'hits'          => 3,
				'last_accessed' => '2026-05-01 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/shop/gone' ),
				'uri'           => '/shop/gone',
				'hits'          => 9,
				'last_accessed' => '2026-06-01 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/blog/other' ),
				'uri'           => '/blog/other',
				'hits'          => 1,
				'last_accessed' => '2026-04-01 00:00:00',
			]
		);

		$search = $this->repo->paginate(
			[
				'search'   => 'blog',
				'orderby'  => 'hits',
				'order'    => 'DESC',
				'page'     => 1,
				'per_page' => 10,
			]
		);

		$this->assertSame( 2, $search['total'] );
		$this->assertCount( 2, $search['rows'] );
		$this->assertSame( '/blog/old-post', $search['rows'][0]['uri'] );

		$filtered = $this->repo->paginate( [ 'min_hits' => 5 ] );

		$this->assertSame( 1, $filtered['total'] );
		$this->assertSame( '/shop/gone', $filtered['rows'][0]['uri'] );

		$paged = $this->repo->paginate(
			[
				'orderby'  => 'uri',
				'order'    => 'ASC',
				'page'     => 2,
				'per_page' => 2,
			]
		);

		$this->assertSame( 3, $paged['total'] );
		$this->assertSame( 2, $paged['pages'] );
		$this->assertCount( 1, $paged['rows'] );
	}

	/**
	 * Test find by id and delete by id.
	 */
	public function test_find_by_id_and_delete_by_id(): void {
		$id = $this->db->seed(
			[
				'uri_hash' => hash( 'sha256', '/target' ),
				'uri'      => '/target',
			]
		);

		$row = $this->repo->findById( $id );

		$this->assertIsArray( $row );
		$this->assertSame( '/target', $row['uri'] );
		$this->assertNull( $this->repo->findById( 9999 ) );
		$this->assertFalse( $this->repo->deleteById( 0 ) );

		$this->assertTrue( $this->repo->deleteById( $id ) );
		$this->assertNull( $this->repo->findById( $id ) );
	}

	/**
	 * Test delete many removes only listed ids.
	 */
	public function test_delete_many_removes_only_listed_ids(): void {
		$keep  = $this->db->seed(
			[
				'uri_hash' => hash( 'sha256', '/keep' ),
				'uri'      => '/keep',
			]
		);
		$dropA = $this->db->seed(
			[
				'uri_hash' => hash( 'sha256', '/drop-a' ),
				'uri'      => '/drop-a',
			]
		);
		$dropB = $this->db->seed(
			[
				'uri_hash' => hash( 'sha256', '/drop-b' ),
				'uri'      => '/drop-b',
			]
		);

		$this->assertSame( 0, $this->repo->deleteMany( [] ) );
		$this->assertSame( 2, $this->repo->deleteMany( [ $dropA, $dropB, $dropB, -5 ] ) );
		$this->assertSame( 1, $this->repo->count() );
		$this->assertIsArray( $this->repo->findById( $keep ) );
	}

	/**
	 * Test clear all bounded loops without truncate.
	 */
	public function test_clear_all_bounded_loops_without_truncate(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->db->seed(
				[
					'uri_hash' => hash( 'sha256', '/bulk-' . $i ),
					'uri'      => '/bulk-' . $i,
				]
			);
		}

		$total  = 0;
		$passes = 0;

		while ( $this->repo->count() > 0 && $passes < 10 ) {
			$deleted = $this->repo->clearAllBounded( 2 );

			$this->assertLessThanOrEqual( 2, $deleted, 'One pass must never exceed the batch' );

			$total += $deleted;
			++$passes;
		}

		$this->assertSame( 5, $total );
		$this->assertSame( 0, $this->repo->count() );
	}

	/**
	 * Test delete older than removes only stale rows.
	 */
	public function test_delete_older_than_removes_only_stale_rows(): void {
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/stale' ),
				'uri'           => '/stale',
				'last_accessed' => '2026-01-01 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/fresh' ),
				'uri'           => '/fresh',
				'last_accessed' => '2026-06-01 00:00:00',
			]
		);

		$this->assertSame( 1, $this->repo->deleteOlderThan( '2026-05-02 00:00:00', 500 ) );
		$this->assertSame( 1, $this->repo->count() );
		$this->assertSame( '/fresh', $this->repo->paginate()['rows'][0]['uri'] );
	}

	/**
	 * Test delete oldest over removes oldest first bounded.
	 */
	public function test_delete_oldest_over_removes_oldest_first_bounded(): void {
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/oldest' ),
				'uri'           => '/oldest',
				'last_accessed' => '2026-01-01 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/middle' ),
				'uri'           => '/middle',
				'last_accessed' => '2026-03-01 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/new-a' ),
				'uri'           => '/new-a',
				'last_accessed' => '2026-05-01 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/new-b' ),
				'uri'           => '/new-b',
				'last_accessed' => '2026-06-01 00:00:00',
			]
		);

		$this->assertSame( 0, $this->repo->deleteOldestOver( 10, 500 ), 'Within limit removes nothing' );
		$this->assertSame( 1, $this->repo->deleteOldestOver( 3, 500 ) );
		$this->assertSame( 3, $this->repo->count() );
		$this->assertNull( $this->repo->findByHash( hash( 'sha256', '/oldest' ) ) );
		$this->assertIsArray( $this->repo->findByHash( hash( 'sha256', '/new-b' ) ) );
	}
}
