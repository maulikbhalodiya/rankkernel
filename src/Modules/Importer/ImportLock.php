<?php
/**
 * One import run at a time.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Import Lock.
 *
 * Two import runs at the same time would write the same records at the
 * same time, and two runs on two different sources would still fight
 * over the same destinations, because a redirect or a meta row belongs
 * to the site and not to the source plugin it came from. The lock is
 * therefore global: one import run at a time, whatever the source.
 *
 * The lock row lives in the options table and is written with plain
 * SQL on purpose. The options API is not atomic across requests, and
 * only this class ever reads the row, so no object cache can go stale
 * behind it. The first write is a unique key insert, and every later
 * change is a compare and swap on the stored value, so only one owner
 * can hold the lock and only the owner can release it.
 *
 * The lease is derived from the PHP execution limit rather than from a
 * fixed number of seconds, so it cannot expire while a chunk is still
 * running normally. A run that is working renews the lease before every
 * write slice, so a competing run keeps being refused. A run that lost
 * the lease, for example because it was paused long enough for the lease
 * to expire and another run took over, fails its next renewal and stops
 * writing before it can touch a destination another run now owns.
 *
 * A lock that is never released expires on its own, so an abandoned
 * run cannot block the next one for ever.
 */
final class ImportLock {
	/**
	 * Option name that holds the lock.
	 */
	public const OPTION_NAME = 'rankkernel_import_lock';

	/**
	 * Shortest lease, in seconds.
	 */
	public const MIN_LEASE = 120;

	/**
	 * Extra seconds on top of the execution limit.
	 */
	public const GRACE = 30;

	/**
	 * Lease used when the execution limit is unlimited.
	 */
	public const UNLIMITED_LEASE = 900;

	/**
	 * Database handle, global $wpdb unless a double is injected.
	 *
	 * @var mixed
	 */
	private $db = null;

	/**
	 * Constructor.
	 *
	 * @param mixed $db Database handle, global $wpdb when null.
	 */
	public function __construct( $db = null ) {
		$this->db = $db;
	}

	/**
	 * Resolve the database handle.
	 *
	 * @return mixed The handle.
	 */
	private function db() {
		if ( null !== $this->db ) {
			return $this->db;
		}

		global $wpdb;

		return $wpdb;
	}

	/**
	 * The lease length for one claim, in seconds.
	 *
	 * The execution limit is the time a request may run, so a lease that
	 * outlives it can never expire under a request that is still alive.
	 * On Linux the limit counts CPU time rather than wall clock time,
	 * so a request blocked on the database can still outlive its lease.
	 * The renewal before every write slice is what closes that window.
	 *
	 * @return int The lease length in seconds.
	 */
	public static function leaseSeconds(): int {
		$limit = 0;

		if ( function_exists( 'ini_get' ) ) {
			$limit = (int) ini_get( 'max_execution_time' );
		}

		if ( $limit <= 0 ) {
			return self::UNLIMITED_LEASE;
		}

		return max( self::MIN_LEASE, $limit + self::GRACE );
	}

	/**
	 * Claim the lock for a run, or refresh a lock the run already owns.
	 *
	 * @param string $owner Run id that owns the lock.
	 * @return bool True when the run owns the lock.
	 */
	public function claim( string $owner ): bool {
		if ( $this->insert( self::OPTION_NAME, $this->payload( $owner ) ) ) {
			return true;
		}

		$current = $this->read( self::OPTION_NAME );

		if ( null === $current ) {
			return $this->insert( self::OPTION_NAME, $this->payload( $owner ) );
		}

		$data = self::decode( $current );

		if ( null !== $data && $data['owner'] === $owner ) {
			return $this->swap( self::OPTION_NAME, $current, $this->payload( $owner ) );
		}

		if ( null !== $data && $data['expires'] > time() ) {
			return false;
		}

		if ( ! $this->remove( self::OPTION_NAME, $current ) ) {
			return false;
		}

		return $this->insert( self::OPTION_NAME, $this->payload( $owner ) );
	}

	/**
	 * Renew the lease, but only when the given run still owns the lock.
	 *
	 * The renewal is a compare and swap, so it also fails when another
	 * run took the lock over in the meantime. A run that cannot renew
	 * has lost the lock and must not write anything else.
	 *
	 * @param string $owner Run id that owns the lock.
	 * @return bool True when the run still owns the lock.
	 */
	public function renew( string $owner ): bool {
		$current = $this->read( self::OPTION_NAME );

		if ( null === $current ) {
			return false;
		}

		$data = self::decode( $current );

		if ( null === $data || $data['owner'] !== $owner ) {
			return false;
		}

		return $this->swap( self::OPTION_NAME, $current, $this->payload( $owner ) );
	}

	/**
	 * Release the lock, but only when the given run owns it.
	 *
	 * @param string $owner Run id that owns the lock.
	 * @return bool True when this run released the lock.
	 */
	public function release( string $owner ): bool {
		$current = $this->read( self::OPTION_NAME );

		if ( null === $current ) {
			return false;
		}

		$data = self::decode( $current );

		if ( null === $data || $data['owner'] !== $owner ) {
			return false;
		}

		return $this->remove( self::OPTION_NAME, $current );
	}

	/**
	 * Build the stored payload for a run.
	 *
	 * The nonce makes every write a different value, so a compare and
	 * swap can always tell a fresh write from the one it read.
	 *
	 * @param string $owner Run id that owns the lock.
	 * @return string The payload.
	 */
	private function payload( string $owner ): string {
		return (string) wp_json_encode(
			[
				'owner'   => $owner,
				'expires' => time() + self::leaseSeconds(),
				'nonce'   => self::nonce(),
			]
		);
	}

	/**
	 * A unique value for one write.
	 *
	 * @return string The nonce.
	 */
	private static function nonce(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return (string) wp_generate_uuid4();
		}

		return uniqid( '', true );
	}

	/**
	 * Read the stored payload.
	 *
	 * @param string $raw Stored value.
	 * @return array{owner: string, expires: int}|null The payload, null when unreadable.
	 */
	private static function decode( string $raw ): ?array {
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || ! isset( $data['owner'] ) || ! isset( $data['expires'] ) ) {
			return null;
		}

		if ( ! is_string( $data['owner'] ) || ! is_int( $data['expires'] ) ) {
			return null;
		}

		return [
			'owner'   => $data['owner'],
			'expires' => $data['expires'],
		];
	}

	/**
	 * Insert the lock row when it does not exist yet.
	 *
	 * @param string $key     Option name.
	 * @param string $payload Stored value.
	 * @return bool True when this call inserted the row.
	 */
	private function insert( string $key, string $payload ): bool {
		$db = $this->db();

		if ( null === $db ) {
			return false;
		}

		$inserted = $db->query(
			$db->prepare(
				"INSERT IGNORE INTO {$db->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$key,
				$payload
			)
		);

		return 1 === (int) $inserted;
	}

	/**
	 * Read the stored value.
	 *
	 * @param string $key Option name.
	 * @return string|null The value, null when the row is missing.
	 */
	private function read( string $key ): ?string {
		$db = $this->db();

		if ( null === $db ) {
			return null;
		}

		$value = $db->get_var(
			$db->prepare( "SELECT option_value FROM {$db->options} WHERE option_name = %s LIMIT 1", $key )
		);

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Replace the stored value only when it still holds the expected one.
	 *
	 * @param string $key      Option name.
	 * @param string $expected Value the row must still hold.
	 * @param string $payload  New value.
	 * @return bool True when the row was replaced.
	 */
	private function swap( string $key, string $expected, string $payload ): bool {
		$db = $this->db();

		if ( null === $db ) {
			return false;
		}

		$updated = $db->query(
			$db->prepare(
				"UPDATE {$db->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$payload,
				$key,
				$expected
			)
		);

		return 1 === (int) $updated;
	}

	/**
	 * Delete the stored value only when it still holds the expected one.
	 *
	 * @param string $key      Option name.
	 * @param string $expected Value the row must still hold.
	 * @return bool True when the row was deleted.
	 */
	private function remove( string $key, string $expected ): bool {
		$db = $this->db();

		if ( null === $db ) {
			return false;
		}

		$deleted = $db->query(
			$db->prepare(
				"DELETE FROM {$db->options} WHERE option_name = %s AND option_value = %s",
				$key,
				$expected
			)
		);

		return 1 === (int) $deleted;
	}
}
