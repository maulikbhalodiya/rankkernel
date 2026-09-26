<?php
/**
 * Instant Indexing log table tests, schema plus idempotent creation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\LogTable;

/**
 * Instant Indexing Log Table Test.
 */
final class InstantIndexingLogTableTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var InstantIndexingFakeDb
	 */
	private InstantIndexingFakeDb $db;

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
		$this->db = new InstantIndexingFakeDb();

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
		$this->assertSame( 'wp_rankkernel_indexnow_log', LogTable::name() );
		$this->assertSame( 'rankkernel_indexnow_log', LogTable::SUFFIX );
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
	 * Test the cache is scoped by resolved table name.
	 */
	public function test_cache_is_scoped_by_resolved_table_name(): void {
		$this->assertTrue( LogTable::exists() );

		// A different prefix resolves to a different table, so the cached true must not be reused.
		$this->db->prefix      = 'wp_2_';
		$this->db->tableExists = false;

		$this->assertFalse( LogTable::exists() );
		$this->assertSame( 2, $this->db->schemaProbes, 'Each distinct table name performs its own probe' );
	}

	/**
	 * Test ensure tables refreshes a cached false after creation.
	 */
	public function test_ensure_tables_refreshes_cache_after_creation(): void {
		$this->db->tableExists = false;

		$this->assertFalse( LogTable::exists() );

		$this->assertTrue( LogTable::ensureTables() );
		$this->assertTrue( LogTable::exists(), 'Cache must not keep the stale false after creation' );
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

		$this->assertStringContainsString( '`wp_rankkernel_indexnow_log`', $this->lastSql );
		$this->assertStringContainsString( 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $this->lastSql );
		$this->assertStringContainsString( 'url TEXT NOT NULL', $this->lastSql );
		$this->assertStringContainsString( "host VARCHAR(255) NOT NULL DEFAULT ''", $this->lastSql );
		$this->assertStringContainsString( 'code SMALLINT UNSIGNED NOT NULL DEFAULT 0', $this->lastSql );
		$this->assertStringContainsString( "source VARCHAR(20) NOT NULL DEFAULT ''", $this->lastSql );
		$this->assertStringContainsString( "message VARCHAR(500) NOT NULL DEFAULT ''", $this->lastSql );
		$this->assertStringContainsString( 'created DATETIME NOT NULL', $this->lastSql );
		$this->assertStringContainsString( 'PRIMARY KEY (id)', $this->lastSql );
		$this->assertStringContainsString( 'KEY created (created)', $this->lastSql );
		$this->assertStringContainsString( 'KEY code (code)', $this->lastSql );
		$this->assertStringContainsString( 'KEY source (source)', $this->lastSql );
	}

	/**
	 * Test no database fails open.
	 */
	public function test_no_database_fails_open(): void {
		unset( $GLOBALS['wpdb'] );

		$this->assertFalse( LogTable::exists() );
		$this->assertFalse( LogTable::ensureTables() );
		$this->assertSame( 'wp_rankkernel_indexnow_log', LogTable::name() );
	}

	/**
	 * Test a probe writes the persistent cache entry with the right key and ttl.
	 */
	public function test_exists_writes_the_persistent_cache_after_the_probe(): void {
		$written = [];

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

		LogTable::resetCache();
		$this->db->tableExists = true;

		$this->assertTrue( LogTable::exists() );
		$this->assertSame( 'table_exists_wp_rankkernel_indexnow_log', $written['key'] );
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

		LogTable::resetCache();
		$this->db->tableExists = false;

		$this->assertTrue( LogTable::exists() );
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

		LogTable::resetCache();
		$this->db->tableExists = true;

		$this->assertFalse( LogTable::exists() );
		$this->assertSame( 0, $this->db->schemaProbes, 'A cached false must not fall through to a probe' );
	}

	/**
	 * Test reset cache retires the persistent entry too.
	 */
	public function test_reset_cache_retires_the_persistent_entry(): void {
		$deleted = [];

		Functions\when( 'wp_cache_delete' )->alias(
			function ( string $key, string $group ) use ( &$deleted ): bool {
				$deleted[] = [ $key, $group ];

				return true;
			}
		);

		LogTable::resetCache();

		$this->assertContains(
			[ 'table_exists_wp_rankkernel_indexnow_log', 'rankkernel_tables' ],
			$deleted
		);
	}

	/**
	 * Test creation records the table as present rather than leaving a stale false.
	 */
	public function test_ensure_tables_refreshes_the_persistent_entry(): void {
		$values = [];

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->alias(
			function ( string $key, mixed $value, string $group, int $ttl ) use ( &$values ): bool {
				unset( $key, $group, $ttl );
				$values[] = $value;

				return true;
			}
		);

		LogTable::resetCache();
		$this->db->tableExists = false;

		$this->assertTrue( LogTable::ensureTables() );
		$this->assertSame( true, end( $values ), 'The last persistent write must record the table as present' );
	}
}
