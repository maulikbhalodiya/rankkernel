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

final class MonitorTableTest extends TestCase {
	/**
	 * Fake database.
	 */
	private MonitorFakeDb $db;

	/**
	 * dbDelta call count.
	 */
	private int $dbDeltaCalls = 0;

	/**
	 * Last schema statement passed to dbDelta.
	 */
	private string $lastSql = '';

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

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

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_table_name_uses_prefix(): void {
		$this->assertSame( 'wp_rankkernel_404_log', LogTable::name() );
		$this->assertSame( 'rankkernel_404_log', LogTable::SUFFIX );
	}

	public function test_exists_reports_fake_state(): void {
		$this->assertTrue( LogTable::exists() );

		$this->db->tableExists = false;

		$this->assertFalse( LogTable::exists() );
	}

	public function test_ensure_tables_creates_once_then_idempotent(): void {
		$this->db->tableExists = false;

		$this->assertTrue( LogTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls );

		$this->assertTrue( LogTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls, 'Second call must not rebuild' );
	}

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

	public function test_no_database_fails_open(): void {
		unset( $GLOBALS['wpdb'] );

		$this->assertFalse( LogTable::exists() );
		$this->assertFalse( LogTable::ensureTables() );
		$this->assertSame( 'wp_rankkernel_404_log', LogTable::name() );
	}
}
