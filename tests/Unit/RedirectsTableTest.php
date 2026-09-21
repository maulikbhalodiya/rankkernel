<?php
/**
 * Redirect table tests, idempotent creation plus fail open.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\RedirectTable;

/**
 * Redirects Table Test.
 */
final class RedirectsTableTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * DbDelta call count.
	 *
	 * @var int
	 */
	private int $dbDeltaCalls = 0;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		RedirectTable::resetCache();
		$this->db           = new RedirectsFakeDb();
		$GLOBALS['wpdb']    = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double, restored in tearDown.
		$this->dbDeltaCalls = 0;

		Functions\when( 'dbDelta' )->alias(
			function (): void {
				++$this->dbDeltaCalls;
				$this->db->tableExists = true;
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		RedirectTable::resetCache();
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test table name uses prefix.
	 */
	public function test_table_name_uses_prefix(): void {
		$this->assertSame( 'wp_rankkernel_redirects', RedirectTable::name() );
		$this->assertSame( 'rankkernel_redirects', RedirectTable::SUFFIX );
	}

	/**
	 * Test exists reports fake state.
	 */
	public function test_exists_reports_fake_state(): void {
		$this->assertTrue( RedirectTable::exists() );

		$this->db->tableExists = false;
		RedirectTable::resetCache();

		$this->assertFalse( RedirectTable::exists() );
	}

	/**
	 * Test repeated existence checks hit the database once.
	 */
	public function test_exists_queries_once_then_serves_from_cache(): void {
		$this->assertTrue( RedirectTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes, 'First call performs one schema probe' );

		$this->assertTrue( RedirectTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes, 'Second call must not probe the database again' );
	}

	/**
	 * Test a missing table is cached without a repeat query.
	 */
	public function test_missing_table_is_cached_without_repeat_query(): void {
		$this->db->tableExists = false;

		$this->assertFalse( RedirectTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes );

		$this->assertFalse( RedirectTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes, 'Cached false must not probe again' );
	}

	/**
	 * Test ensure tables clears a cached false after creation.
	 */
	public function test_ensure_tables_refreshes_cache_after_creation(): void {
		$this->db->tableExists = false;

		$this->assertFalse( RedirectTable::exists() );

		$this->assertTrue( RedirectTable::ensureTables() );
		$this->assertTrue( RedirectTable::exists(), 'Cache must not keep the stale false after creation' );
	}

	/**
	 * Test the cache is scoped by resolved table name.
	 */
	public function test_cache_is_scoped_by_resolved_table_name(): void {
		$this->db->tableExists = true;

		$this->assertTrue( RedirectTable::exists() );

		// A different prefix resolves to a different table, so the cached true must not be reused.
		$this->db->prefix      = 'wp_2_';
		$this->db->tableExists = false;

		$this->assertFalse( RedirectTable::exists() );
		$this->assertSame( 2, $this->db->schemaProbes, 'Each distinct table name performs its own probe' );
	}

	/**
	 * Test ensure tables creates once then idempotent.
	 */
	public function test_ensure_tables_creates_once_then_idempotent(): void {
		$this->db->tableExists = false;

		$this->assertTrue( RedirectTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls );

		$this->assertTrue( RedirectTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls, 'Second call must not rebuild' );
	}

	/**
	 * Test no database fails open.
	 */
	public function test_no_database_fails_open(): void {
		unset( $GLOBALS['wpdb'] );

		$this->assertFalse( RedirectTable::exists() );
		$this->assertFalse( RedirectTable::ensureTables() );
		$this->assertSame( 'wp_rankkernel_redirects', RedirectTable::name() );
	}

	/**
	 * Test a probe writes the persistent cache entry with the right key and ttl.
	 */
	public function test_exists_writes_the_persistent_cache_after_the_probe(): void {
		$written = array();

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->alias(
			function ( string $key, mixed $value, string $group, int $ttl ) use ( &$written ): bool {
				$written = [
					'key'   => $key,
					'value' => $value,
					'group' => $group,
					'ttl'   => $ttl,
				];

				return true;
			}
		);

		RedirectTable::resetCache();
		$this->db->tableExists = true;

		$this->assertTrue( RedirectTable::exists() );
		$this->assertSame( 'table_exists_wp_rankkernel_redirects', $written['key'] );
		$this->assertSame( 'rankkernel_tables', $written['group'] );
		$this->assertTrue( $written['value'] );
		$this->assertSame( DAY_IN_SECONDS, $written['ttl'] );
	}

	/**
	 * Test a persistent cache hit answers without touching the database.
	 */
	public function test_exists_uses_a_persistent_cache_hit_without_probing(): void {
		Functions\when( 'wp_cache_get' )->alias(
			static function ( string $key, string $group, bool $force, &$found ): mixed {
				$found = true;

				return true;
			}
		);

		RedirectTable::resetCache();
		$this->db->tableExists = false;

		$this->assertTrue( RedirectTable::exists() );
		$this->assertSame( 0, $this->db->schemaProbes, 'A cache hit must not probe the database' );
	}

	/**
	 * Test a cached false is honoured rather than treated as a miss.
	 */
	public function test_exists_honours_a_persistently_cached_false(): void {
		Functions\when( 'wp_cache_get' )->alias(
			static function ( string $key, string $group, bool $force, &$found ): mixed {
				$found = true;

				return false;
			}
		);

		RedirectTable::resetCache();
		$this->db->tableExists = true;

		$this->assertFalse( RedirectTable::exists() );
		$this->assertSame( 0, $this->db->schemaProbes, 'A cached false must not fall through to a probe' );
	}

	/**
	 * Test reset cache retires the persistent entry too.
	 */
	public function test_reset_cache_retires_the_persistent_entry(): void {
		$deleted = array();

		Functions\when( 'wp_cache_delete' )->alias(
			function ( string $key, string $group ) use ( &$deleted ): bool {
				$deleted[] = [ $key, $group ];

				return true;
			}
		);

		RedirectTable::resetCache();

		$this->assertContains(
			[ 'table_exists_wp_rankkernel_redirects', 'rankkernel_tables' ],
			$deleted
		);
	}

	/**
	 * Test creation records the table as present rather than leaving a stale false.
	 */
	public function test_ensure_tables_refreshes_the_persistent_entry(): void {
		$values = array();

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->alias(
			function ( string $key, mixed $value, string $group, int $ttl ) use ( &$values ): bool {
				unset( $key, $group, $ttl );
				$values[] = $value;

				return true;
			}
		);

		RedirectTable::resetCache();
		$this->db->tableExists = false;

		$this->assertTrue( RedirectTable::ensureTables() );
		$this->assertSame( true, end( $values ), 'The last persistent write must record the table as present' );
	}
}
