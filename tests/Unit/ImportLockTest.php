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
	 * Test the first run takes a free lock.
	 */
	public function test_first_run_takes_a_free_lock(): void {
		$this->assertTrue( $this->lock->claim( 'run-aaaa-1111' ) );
		$this->assertArrayHasKey( ImportLock::OPTION_NAME, $this->db->rows() );
	}

	/**
	 * Test a second run is refused while the lock is live.
	 */
	public function test_second_run_is_refused(): void {
		$this->assertTrue( $this->lock->claim( 'run-aaaa-1111' ) );
		$this->assertFalse( $this->lock->claim( 'run-bbbb-2222' ) );
	}

	/**
	 * Test the owning run can claim again to refresh the lock.
	 */
	public function test_owner_can_claim_again(): void {
		$this->assertTrue( $this->lock->claim( 'run-aaaa-1111' ) );
		$this->assertTrue( $this->lock->claim( 'run-aaaa-1111' ) );
		$this->assertFalse( $this->lock->claim( 'run-bbbb-2222' ) );
	}

	/**
	 * Test only the owning run can release the lock.
	 */
	public function test_only_the_owner_releases(): void {
		$this->lock->claim( 'run-aaaa-1111' );

		$this->assertFalse( $this->lock->release( 'run-bbbb-2222' ) );
		$this->assertArrayHasKey( ImportLock::OPTION_NAME, $this->db->rows() );

		$this->assertTrue( $this->lock->release( 'run-aaaa-1111' ) );
		$this->assertArrayNotHasKey( ImportLock::OPTION_NAME, $this->db->rows() );
	}

	/**
	 * Test releasing a lock that is not there reports no release.
	 */
	public function test_release_without_a_lock_reports_false(): void {
		$this->assertFalse( $this->lock->release( 'run-aaaa-1111' ) );
	}

	/**
	 * Test an expired lock is taken over by the next run.
	 */
	public function test_expired_lock_is_taken_over(): void {
		$this->seedExpiredLock();

		$this->assertTrue( $this->lock->claim( 'run-new-2222' ) );
		$this->assertFalse( $this->lock->claim( 'run-other-3333' ) );
	}

	/**
	 * Test an unreadable lock row is taken over instead of blocking for ever.
	 */
	public function test_unreadable_lock_is_taken_over(): void {
		$this->db->seed( ImportLock::OPTION_NAME, 'not a payload' );

		$this->assertTrue( $this->lock->claim( 'run-new-2222' ) );
	}

	/**
	 * Test a late release from an expired run cannot free a lock that a
	 * later run already took over.
	 */
	public function test_late_release_cannot_free_a_new_owner_lock(): void {
		$this->seedExpiredLock();

		$this->assertTrue( $this->lock->claim( 'run-new-2222' ) );
		$this->assertFalse( $this->lock->release( 'run-old-1111' ) );
		$this->assertArrayHasKey( ImportLock::OPTION_NAME, $this->db->rows() );
	}

	/**
	 * Test the lock row carries the owner and an expiry.
	 */
	public function test_lock_row_carries_owner_and_expiry(): void {
		$this->lock->claim( 'run-aaaa-1111' );

		$rows = $this->db->rows();
		$raw  = json_decode( (string) $rows[ ImportLock::OPTION_NAME ], true );

		$this->assertIsArray( $raw );
		$this->assertSame( 'run-aaaa-1111', $raw['owner'] );
		$this->assertGreaterThan( time(), $raw['expires'] );
	}

	/**
	 * Test the lease outlives the request execution limit.
	 *
	 * The lease has to be longer than the time a request may run, or it
	 * could expire while a chunk is still working.
	 */
	public function test_lease_outlives_the_execution_limit(): void {
		$limit = (int) ini_get( 'max_execution_time' );
		$lease = ImportLock::leaseSeconds();

		$this->assertGreaterThanOrEqual( ImportLock::MIN_LEASE, $lease );

		if ( $limit > 0 ) {
			$this->assertGreaterThan( $limit, $lease );
		} else {
			$this->assertSame( ImportLock::UNLIMITED_LEASE, $lease );
		}
	}

	/**
	 * Test the lease is at least the short floor.
	 */
	public function test_lease_has_a_floor(): void {
		$this->assertGreaterThanOrEqual( 120, ImportLock::leaseSeconds() );
	}

	/**
	 * Test the owner can renew the lease and a stranger cannot.
	 */
	public function test_only_the_owner_can_renew(): void {
		$this->lock->claim( 'run-aaaa-1111' );

		$this->assertTrue( $this->lock->renew( 'run-aaaa-1111' ) );
		$this->assertFalse( $this->lock->renew( 'run-bbbb-2222' ) );
	}

	/**
	 * Test a renewal by the owner pushes the expiry forward.
	 */
	public function test_renewal_extends_the_lease(): void {
		$this->db->seed(
			ImportLock::OPTION_NAME,
			(string) wp_json_encode(
				[
					'owner'   => 'run-aaaa-1111',
					'expires' => time() + 5,
					'nonce'   => 'old-nonce',
				]
			)
		);

		$this->assertTrue( $this->lock->renew( 'run-aaaa-1111' ) );

		$rows = $this->db->rows();
		$raw  = json_decode( (string) $rows[ ImportLock::OPTION_NAME ], true );

		$this->assertGreaterThan( time() + 5, $raw['expires'] );
	}

	/**
	 * Test a renewal fails once another run took the lock over.
	 */
	public function test_renewal_fails_after_a_takeover(): void {
		$this->seedExpiredLock();

		$this->assertTrue( $this->lock->claim( 'run-new-2222' ) );
		$this->assertFalse( $this->lock->renew( 'run-old-1111' ) );
	}

	/**
	 * Seed a lock row whose lease already expired.
	 *
	 * @return void
	 */
	private function seedExpiredLock(): void {
		$this->db->seed(
			ImportLock::OPTION_NAME,
			(string) wp_json_encode(
				[
					'owner'   => 'run-old-1111',
					'expires' => time() - 10,
					'nonce'   => 'old-nonce',
				]
			)
		);
	}
}
