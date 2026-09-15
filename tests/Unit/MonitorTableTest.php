<?php
/**
 * 404 log table tests, schema plus idempotent creation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\LogTable;

/**
 * Monitor Table Test.
 */
final class MonitorTableTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var MonitorFakeDb
	 */
	private MonitorFakeDb $db;

	/**
	 * DbDelta call count.
	 *
	 * @var int
	 */
	private int $dbDeltaCalls = 0;

	/**
	 * Last schema statement passed to dbDelta.
	 *
	 * @var string
	 */
	private string $lastSql = '';

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		LogTable::resetCache();
		$this->db = new MonitorFakeDb();

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

		$this->dbDeltaCalls = 0;
		$this->lastSql      = '';

		Functions\when( 'dbDelta' )->alias(
			function ( string $sql ): void {
				++$this->dbDeltaCalls;
				$this->lastSql         = $sql;
				$this->db->tableExists = true;
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		LogTable::resetCache();
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test table name uses prefix.
	 */
	public function test_table_name_uses_prefix(): void {
		$this->assertSame( 'wp_rankkernel_404_log', LogTable::name() );
		$this->assertSame( 'rankkernel_404_log', LogTable::SUFFIX );
	}

	/**
	 * Test exists reports fake state.
	 */
	public function test_exists_reports_fake_state(): void {
		$this->assertTrue( LogTable::exists() );

		$this->db->tableExists = false;
		LogTable::resetCache();

		$this->assertFalse( LogTable::exists() );
	}

	/**
	 * Test repeated existence checks hit the database once.
	 */
	public function test_exists_queries_once_then_serves_from_cache(): void {
		$this->assertTrue( LogTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes, 'First call performs one schema probe' );

		$this->assertTrue( LogTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes, 'Second call must not probe the database again' );
	}

	/**
	 * Test a missing table is cached without a repeat query.
	 */
	public function test_missing_table_is_cached_without_repeat_query(): void {
		$this->db->tableExists = false;

		$this->assertFalse( LogTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes );

		$this->assertFalse( LogTable::exists() );
		$this->assertSame( 1, $this->db->schemaProbes, 'Cached false must not probe again' );
	}

	/**
	 * Test ensure tables clears a cached false after creation.
	 */
	public function test_ensure_tables_refreshes_cache_after_creation(): void {
		$this->db->tableExists = false;

		$this->assertFalse( LogTable::exists() );

		$this->assertTrue( LogTable::ensureTables() );
		$this->assertTrue( LogTable::exists(), 'Cache must not keep the stale false after creation' );
	}

	/**
	 * Test the cache is scoped by resolved table name.
	 */
	public function test_cache_is_scoped_by_resolved_table_name(): void {
		$this->db->tableExists = true;

		$this->assertTrue( LogTable::exists() );

		// A different prefix resolves to a different table, so the cached true must not be reused.
		$this->db->prefix      = 'wp_2_';
		$this->db->tableExists = false;

		$this->assertFalse( LogTable::exists() );
		$this->assertSame( 2, $this->db->schemaProbes, 'Each distinct table name performs its own probe' );
	}

	/**
	 * Test ensure tables creates once then idempotent.
	 */
	public function test_ensure_tables_creates_once_then_idempotent(): void {
		$this->db->tableExists = false;

		$this->assertTrue( LogTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls );

		$this->assertTrue( LogTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls, 'Second call must not rebuild' );
	}

	/**
	 * Test schema matches binding contract.
	 */
	public function test_schema_matches_binding_contract(): void {
		$this->db->tableExists = false;

		LogTable::ensureTables();

		$this->assertStringContainsString( '`wp_rankkernel_404_log`', $this->lastSql );
		$this->assertStringContainsString( 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $this->lastSql );
		$this->assertStringContainsString( 'uri_hash CHAR(64) NOT NULL', $this->lastSql );
		$this->assertStringContainsString( 'uri TEXT NOT NULL', $this->lastSql );
		$this->assertStringContainsString( 'hits BIGINT UNSIGNED NOT NULL DEFAULT 0', $this->lastSql );
		$this->assertStringContainsString( "referer VARCHAR(255) NOT NULL DEFAULT ''", $this->lastSql );
		$this->assertStringContainsString( "user_agent VARCHAR(255) NOT NULL DEFAULT ''", $this->lastSql );
		$this->assertStringContainsString( 'created DATETIME NOT NULL', $this->lastSql );
		$this->assertStringContainsString( 'last_accessed DATETIME NOT NULL', $this->lastSql );
		$this->assertStringContainsString( 'PRIMARY KEY (id)', $this->lastSql );
		$this->assertStringContainsString( 'UNIQUE KEY uri_hash (uri_hash)', $this->lastSql );
		$this->assertStringContainsString( 'KEY last_accessed (last_accessed)', $this->lastSql );
	}

	/**
	 * Test no database fails open.
	 */
	public function test_no_database_fails_open(): void {
		unset( $GLOBALS['wpdb'] );

		$this->assertFalse( LogTable::exists() );
		$this->assertFalse( LogTable::ensureTables() );
		$this->assertSame( 'wp_rankkernel_404_log', LogTable::name() );
	}
}
