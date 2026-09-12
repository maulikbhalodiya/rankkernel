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

final class RedirectsTableTest extends TestCase {
	/**
	 * Fake database.
	 */
	private RedirectsFakeDb $db;

	/**
	 * dbDelta call count.
	 */
	private int $dbDeltaCalls = 0;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->db            = new RedirectsFakeDb();
		$GLOBALS['wpdb']     = $this->db;
		$this->dbDeltaCalls  = 0;

		Functions\when( 'dbDelta' )->alias(
			function ( string $sql ): void {
				++$this->dbDeltaCalls;
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
		$this->assertSame( 'wp_rankkernel_redirects', RedirectTable::name() );
		$this->assertSame( 'rankkernel_redirects', RedirectTable::SUFFIX );
	}

	public function test_exists_reports_fake_state(): void {
		$this->assertTrue( RedirectTable::exists() );

		$this->db->tableExists = false;

		$this->assertFalse( RedirectTable::exists() );
	}

	public function test_ensure_tables_creates_once_then_idempotent(): void {
		$this->db->tableExists = false;

		$this->assertTrue( RedirectTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls );

		$this->assertTrue( RedirectTable::ensureTables() );
		$this->assertSame( 1, $this->dbDeltaCalls, 'Second call must not rebuild' );
	}

	public function test_no_database_fails_open(): void {
		unset( $GLOBALS['wpdb'] );

		$this->assertFalse( RedirectTable::exists() );
		$this->assertFalse( RedirectTable::ensureTables() );
		$this->assertSame( 'wp_rankkernel_redirects', RedirectTable::name() );
	}
}
