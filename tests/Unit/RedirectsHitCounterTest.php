<?php
/**
 * Hit counter tests, coalescing plus shutdown flush.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\HitCounter;

/**
 * Redirects Hit Counter Test.
 */
final class RedirectsHitCounterTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * Registered hooks.
	 *
	 * @var array<int, array{hook: string, callback: mixed, priority: int}>
	 */
	private array $hooks = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db          = new RedirectsFakeDb();
		$GLOBALS['wpdb']   = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double, restored in tearDown.
		$this->db->rows[1] = [
			'id'            => 1,
			'match_type'    => 'exact',
			'source'        => '/old',
			'target'        => '/new',
			'code'          => '301',
			'hits'          => 5,
			'is_active'     => 1,
			'last_accessed' => null,
		];
		$this->db->rows[2] = [
			'id'            => 2,
			'match_type'    => 'exact',
			'source'        => '/other',
			'target'        => '/new',
			'code'          => '301',
			'hits'          => 0,
			'is_active'     => 1,
			'last_accessed' => null,
		];
		$this->db->nextId  = 3;

		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10 ): bool {
				$this->hooks[] = [
					'hook'     => $hook,
					'callback' => $callback,
					'priority' => $priority,
				];

				return true;
			}
		);
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
	 * Test records coalesce until flush.
	 */
	public function test_records_coalesce_until_flush(): void {
		$counter = new HitCounter();

		$counter->record( 1 );
		$counter->record( 1 );
		$counter->record( 1 );

		$this->assertSame( [ 1 => 3 ], $counter->pending() );
		$this->assertSame( 0, $this->db->writes, 'No write may happen before flush' );

		$counter->flush();

		$this->assertSame( 1, $this->db->writes, 'Three hits flush as one UPDATE' );
		$this->assertSame( 8, $this->db->rows[1]['hits'] );
		$this->assertSame( '2026-01-01 00:00:00', $this->db->rows[1]['last_accessed'] );
		$this->assertSame( [], $counter->pending() );
	}

	/**
	 * Test each rule gets one update.
	 */
	public function test_each_rule_gets_one_update(): void {
		$counter = new HitCounter();

		$counter->record( 1 );
		$counter->record( 2 );
		$counter->record( 2 );

		$counter->flush();

		$this->assertSame( 2, $this->db->writes );
		$this->assertSame( 6, $this->db->rows[1]['hits'] );
		$this->assertSame( 2, $this->db->rows[2]['hits'] );
	}

	/**
	 * Test empty flush writes nothing.
	 */
	public function test_empty_flush_writes_nothing(): void {
		$counter = new HitCounter();

		$counter->flush();

		$this->assertSame( 0, $this->db->writes );
	}

	/**
	 * Test shutdown hook registered once.
	 */
	public function test_shutdown_hook_registered_once(): void {
		$counter = new HitCounter();

		$counter->record( 1 );
		$counter->record( 2 );

		$shutdowns = array_values( array_filter( $this->hooks, static fn ( array $h ): bool => 'shutdown' === $h['hook'] ) );

		$this->assertCount( 1, $shutdowns );
	}

	/**
	 * Test invalid ids ignored.
	 */
	public function test_invalid_ids_ignored(): void {
		$counter = new HitCounter();

		$counter->record( 0 );
		$counter->record( -4 );

		$this->assertSame( [], $counter->pending() );
		$this->assertSame( [], $this->hooks );
	}

	/**
	 * Test flush without database clears silently.
	 */
	public function test_flush_without_database_clears_silently(): void {
		unset( $GLOBALS['wpdb'] );

		$counter = new HitCounter();

		$counter->record( 1 );
		$counter->flush();

		$this->assertSame( [], $counter->pending() );
	}
}
