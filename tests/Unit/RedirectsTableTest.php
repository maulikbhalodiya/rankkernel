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
}
