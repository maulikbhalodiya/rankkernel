<?php
/**
 * Import lock tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\ImportLock;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests the import run lock.
 */
final class ImportLockTest extends TestCase {
	/**
	 * Fake database handle.
	 *
	 * @var ImportLockFakeDb
	 */
	private ImportLockFakeDb $db;

	/**
	 * Lock under test.
	 *
	 * @var ImportLock
	 */
	private ImportLock $lock;

	/**
	 * Set up the fake options table.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		$this->db   = new ImportLockFakeDb();
		$this->lock = new ImportLock( $this->db );
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The stored option name for a source.
	 *
	 * @param string $source Source id.
	 * @return string The option name.
	 */
	private function key( string $source ): string {
		return ImportLock::OPTION_PREFIX . $source;
	}

	/**
	 * Test the first run takes a free lock.
	 */
	public function test_first_run_takes_a_free_lock(): void {
		$this->assertTrue( $this->lock->claim( 'yoast', 'run-aaaa-1111' ) );
		$this->assertArrayHasKey( $this->key( 'yoast' ), $this->db->rows() );
	}

	/**
	 * Test a second run is refused while the lock is live.
	 */
	public function test_second_run_is_refused(): void {
		$this->assertTrue( $this->lock->claim( 'yoast', 'run-aaaa-1111' ) );
		$this->assertFalse( $this->lock->claim( 'yoast', 'run-bbbb-2222' ) );
	}

	/**
	 * Test the owning run can claim again to refresh the lock.
	 */
	public function test_owner_can_claim_again(): void {
		$this->assertTrue( $this->lock->claim( 'yoast', 'run-aaaa-1111' ) );
		$this->assertTrue( $this->lock->claim( 'yoast', 'run-aaaa-1111' ) );
		$this->assertFalse( $this->lock->claim( 'yoast', 'run-bbbb-2222' ) );
	}

	/**
	 * Test a lock on one source does not block another source.
	 */
	public function test_lock_is_per_source(): void {
		$this->assertTrue( $this->lock->claim( 'yoast', 'run-aaaa-1111' ) );
		$this->assertTrue( $this->lock->claim( 'rank-math', 'run-bbbb-2222' ) );
	}

	/**
	 * Test only the owning run can release the lock.
	 */
	public function test_only_the_owner_releases(): void {
		$this->lock->claim( 'yoast', 'run-aaaa-1111' );

		$this->assertFalse( $this->lock->release( 'yoast', 'run-bbbb-2222' ) );
		$this->assertArrayHasKey( $this->key( 'yoast' ), $this->db->rows() );

		$this->assertTrue( $this->lock->release( 'yoast', 'run-aaaa-1111' ) );
		$this->assertArrayNotHasKey( $this->key( 'yoast' ), $this->db->rows() );
	}

	/**
	 * Test releasing a lock that is not there reports no release.
	 */
	public function test_release_without_a_lock_reports_false(): void {
		$this->assertFalse( $this->lock->release( 'yoast', 'run-aaaa-1111' ) );
	}

	/**
	 * Test an expired lock is taken over by the next run.
	 */
	public function test_expired_lock_is_taken_over(): void {
		$this->db->seed(
			$this->key( 'yoast' ),
			(string) wp_json_encode(
				[
					'owner'   => 'run-old-1111',
					'expires' => time() - 10,
					'nonce'   => 'old-nonce',
				]
			)
		);

		$this->assertTrue( $this->lock->claim( 'yoast', 'run-new-2222' ) );
		$this->assertFalse( $this->lock->claim( 'yoast', 'run-other-3333' ) );
	}

	/**
	 * Test an unreadable lock row is taken over instead of blocking for ever.
	 */
	public function test_unreadable_lock_is_taken_over(): void {
		$this->db->seed( $this->key( 'yoast' ), 'not a payload' );

		$this->assertTrue( $this->lock->claim( 'yoast', 'run-new-2222' ) );
	}

	/**
	 * Test a late release from an expired run cannot free a lock that a
	 * later run already took over.
	 */
	public function test_late_release_cannot_free_a_new_owner_lock(): void {
		$this->db->seed(
			$this->key( 'yoast' ),
			(string) wp_json_encode(
				[
					'owner'   => 'run-old-1111',
					'expires' => time() - 10,
					'nonce'   => 'old-nonce',
				]
			)
		);

		$this->assertTrue( $this->lock->claim( 'yoast', 'run-new-2222' ) );
		$this->assertFalse( $this->lock->release( 'yoast', 'run-old-1111' ) );
		$this->assertArrayHasKey( $this->key( 'yoast' ), $this->db->rows() );
	}

	/**
	 * Test the lock row carries the owner and an expiry.
	 */
	public function test_lock_row_carries_owner_and_expiry(): void {
		$this->lock->claim( 'yoast', 'run-aaaa-1111' );

		$rows = $this->db->rows();
		$raw  = json_decode( (string) $rows[ $this->key( 'yoast' ) ], true );

		$this->assertIsArray( $raw );
		$this->assertSame( 'run-aaaa-1111', $raw['owner'] );
		$this->assertGreaterThan( time(), $raw['expires'] );
	}
}
