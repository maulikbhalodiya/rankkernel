<?php
/**
 * One import run at a time, per source.
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
 * Two runs on the same source would write the same records at the same
 * time, so a run holds the lock for the whole run and a second run on
 * that source is refused with a conflict.
 *
 * The lock row lives in the options table and is written with plain
 * SQL on purpose. The options API is not atomic across requests, and
 * only this class ever reads the row, so no object cache can go stale
 * behind it. The first write is a unique key insert, and every later
 * change is a compare and swap on the stored value, so only one owner
 * can hold the lock and only the owner can release it.
 *
 * A lock that is never released expires on its own, so an abandoned
 * run cannot block the next one for ever.
 */
final class ImportLock {
	/**
	 * Option name prefix.
	 */
	public const OPTION_PREFIX = 'rankkernel_import_lock_';

	/**
	 * Seconds a lock stays valid without a refresh.
	 */
	public const TTL = 120;

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
	 * Claim the lock for a run, or refresh a lock the run already owns.
	 *
	 * @param string $source Source id.
	 * @param string $owner  Run id that owns the lock.
	 * @return bool True when the run owns the lock.
	 */
	public function claim( string $source, string $owner ): bool {
		$key = $this->key( $source );

		if ( $this->insert( $key, $this->payload( $owner ) ) ) {
			return true;
		}

		$current = $this->read( $key );

		if ( null === $current ) {
			return $this->insert( $key, $this->payload( $owner ) );
		}

		$data = self::decode( $current );

		if ( null !== $data && $data['owner'] === $owner ) {
			return $this->swap( $key, $current, $this->payload( $owner ) );
		}

		if ( null !== $data && $data['expires'] > time() ) {
			return false;
		}

		if ( ! $this->remove( $key, $current ) ) {
			return false;
		}

		return $this->insert( $key, $this->payload( $owner ) );
	}

	/**
	 * Release the lock, but only when the given run owns it.
	 *
	 * @param string $source Source id.
	 * @param string $owner  Run id that owns the lock.
	 * @return bool True when this run released the lock.
	 */
	public function release( string $source, string $owner ): bool {
		$key     = $this->key( $source );
		$current = $this->read( $key );

		if ( null === $current ) {
			return false;
		}

		$data = self::decode( $current );

		if ( null === $data || $data['owner'] !== $owner ) {
			return false;
		}

		return $this->remove( $key, $current );
	}

	/**
	 * The option name that holds the lock for a source.
	 *
	 * @param string $source Source id.
	 * @return string The option name.
	 */
	private function key( string $source ): string {
		$safe = (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $source ) );

		return self::OPTION_PREFIX . $safe;
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
				'expires' => time() + self::TTL,
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
