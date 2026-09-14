<?php
/**
 * 404 pruner tests, bounded age and count enforcement.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\MonitorRepository;
use RankKernel\Modules\Monitor\MonitorSettings;
use RankKernel\Modules\Monitor\Pruner;

/**
 * Monitor Pruner Test.
 */
final class MonitorPrunerTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var MonitorFakeDb
	 */
	private MonitorFakeDb $db;

	/**
	 * Option store.
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

		$this->db = new MonitorFakeDb();

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

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
	 * Build a pruner over the test doubles.
	 *
	 * @return Pruner The result.
	 */
	private function makePruner(): Pruner {
		return new Pruner( new MonitorRepository( $this->db ), new MonitorSettings() );
	}

	/**
	 * Test cutoff follows retention days.
	 */
	public function test_cutoff_follows_retention_days(): void {
		$this->options['rankkernel_404_settings'] = [ 'retention_days' => 30 ];

		$this->assertSame( '2026-05-02 12:00:00', $this->makePruner()->cutoff() );
	}

	/**
	 * Test prune by age removes only stale rows.
	 */
	public function test_prune_by_age_removes_only_stale_rows(): void {
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

		$this->assertSame( 1, $this->makePruner()->pruneByAge() );
		$this->assertSame( 1, count( $this->db->rows ) );
	}

	/**
	 * Test prune by count removes oldest first with margin.
	 */
	public function test_prune_by_count_removes_oldest_first_with_margin(): void {
		$this->options['rankkernel_404_settings'] = [ 'max_rows' => 3 ];

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
				'last_accessed' => '2026-05-15 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/newest' ),
				'uri'           => '/newest',
				'last_accessed' => '2026-06-01 00:00:00',
			]
		);

		$deleted = $this->makePruner()->pruneByCount();

		$this->assertSame( 2, $deleted );
		$this->assertSame( 3, count( $this->db->rows ) );
		$this->assertArrayNotHasKey( 1, $this->db->rows );
		$this->assertArrayNotHasKey( 2, $this->db->rows );
	}

	/**
	 * Test prune by count within limit removes nothing.
	 */
	public function test_prune_by_count_within_limit_removes_nothing(): void {
		$this->options['rankkernel_404_settings'] = [ 'max_rows' => 1000 ];

		$this->db->seed(
			[
				'uri_hash' => hash( 'sha256', '/only' ),
				'uri'      => '/only',
			]
		);

		$this->assertSame( 0, $this->makePruner()->pruneByCount() );
		$this->assertSame( 1, count( $this->db->rows ) );
	}

	/**
	 * Test prune runs age then count and never truncates.
	 */
	public function test_prune_runs_age_then_count_and_never_truncates(): void {
		$this->options['rankkernel_404_settings'] = [
			'retention_days' => 30,
			'max_rows'       => 100,
		];

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

		$this->assertSame( 1, $this->makePruner()->prune() );
		$this->assertSame( 1, count( $this->db->rows ) );
	}
}
