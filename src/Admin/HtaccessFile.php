<?php
/**
 * Safe .htaccess read, backup and write.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the site .htaccess with a timestamped backup.
 *
 * A bad .htaccess can take the whole site down, so this class backs the file
 * up before every write, only reports success when the write happened, and
 * only supports Apache and LiteSpeed, where .htaccess is actually read.
 */
final class HtaccessFile {
	/**
	 * Absolute path of the site .htaccess, filterable for tests.
	 *
	 * The filter is a public hook, so the value is only used when it is a non
	 * empty string that resolves inside the site root. Anything else, including
	 * a path that climbs out of the tree, falls back to the default so the
	 * editor can never be pointed at an arbitrary file.
	 *
	 * @return string The result.
	 */
	public function path(): string {
		$default = defined( 'ABSPATH' ) ? ABSPATH . '.htaccess' : '';

		$path = apply_filters( 'rankkernel/htaccess/path', $default ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( ! is_string( $path ) || '' === $path || ! defined( 'ABSPATH' ) ) {
			return $default;
		}

		if ( $path === $default ) {
			return $default;
		}

		return $this->containedPath( $path, $default );
	}

	/**
	 * Keep a filtered path only when it resolves inside the site root.
	 *
	 * When the path resolution helpers are unavailable the filter value is not
	 * trusted, so the default is returned. The parent directory is resolved with
	 * realpath, so a sequence such as .. cannot climb out of the tree. The file
	 * itself may not exist yet, so only its directory is resolved before the
	 * prefix check.
	 *
	 * @param string $path     Filtered path.
	 * @param string $fallback Default path.
	 * @return string The result.
	 */
	private function containedPath( string $path, string $fallback ): string {
		if ( ! function_exists( 'realpath' ) || ! function_exists( 'wp_normalize_path' ) ) {
			return $fallback;
		}

		$root = realpath( ABSPATH );
		$dir  = realpath( dirname( $path ) );

		if ( false === $root || false === $dir ) {
			return $fallback;
		}

		$root = rtrim( wp_normalize_path( $root ), '/' ) . '/';
		$dir  = rtrim( wp_normalize_path( $dir ), '/' ) . '/';

		return str_starts_with( $dir, $root ) ? $path : $fallback;
	}

	/**
	 * Whether the .htaccess exists.
	 *
	 * @return bool The result.
	 */
	public function exists(): bool {
		$path = $this->path();

		return '' !== $path && file_exists( $path );
	}

	/**
	 * Read the .htaccess content.
	 *
	 * @return string The result.
	 */
	public function read(): string {
		$path = $this->path();

		if ( '' === $path || ! file_exists( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the site .htaccess, the only portable option.

		return ( is_string( $content ) ) ? $content : '';
	}

	/**
	 * Whether the .htaccess is on a server that reads it.
	 *
	 * @return bool The result.
	 */
	public function isSupported(): bool {
		$supported = (bool) ( $GLOBALS['is_apache'] ?? false );

		if ( ! $supported && isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$software  = strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_SOFTWARE'] ) ) );
			$supported = str_contains( $software, 'apache' ) || str_contains( $software, 'litespeed' );
		}

		/**
		 * Filter whether .htaccess editing is available on this server.
		 *
		 * @param bool $supported Detected support.
		 */
		return (bool) apply_filters( 'rankkernel/htaccess/supported', $supported ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
	}

	/**
	 * Whether the .htaccess can be written.
	 *
	 * @return bool The result.
	 */
	public function isWritable(): bool {
		if (
			( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
			|| ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		) {
			return false;
		}

		$path = $this->path();

		if ( '' === $path ) {
			return false;
		}

		return file_exists( $path ) ? is_writable( $path ) : is_writable( dirname( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- checking writability of the site .htaccess before offering an editor.
	}

	/**
	 * Path of the backup written before the next save.
	 *
	 * @return string The result.
	 */
	public function backupPath(): string {
		return $this->path() . '.rankkernel-backup-' . gmdate( 'YmdHis' );
	}

	/**
	 * Back up the current file, then write the new content.
	 *
	 * @param string $content New .htaccess content.
	 * @return array{saved: bool, reason: string, backup: string} Result, reason code and backup path.
	 */
	public function save( string $content ): array {
		$path = $this->path();

		if ( '' === $path ) {
			return [
				'saved'  => false,
				'reason' => 'no_path',
				'backup' => '',
			];
		}

		if ( ! $this->isSupported() ) {
			return [
				'saved'  => false,
				'reason' => 'unsupported',
				'backup' => '',
			];
		}

		if ( ! $this->isWritable() ) {
			return [
				'saved'  => false,
				'reason' => 'not_writable',
				'backup' => '',
			];
		}

		$backup = '';

		if ( file_exists( $path ) ) {
			$backup = $this->backupPath();

			if ( ! copy( $path, $backup ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- writing a sibling backup of the site .htaccess.
				return [
					'saved'  => false,
					'reason' => 'backup_failed',
					'backup' => '',
				];
			}
		}

		$bytes = file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing the site .htaccess, the only portable option.

		// A failed write returns false, but a partial write can return a byte
		// count smaller than the content without returning false. A truncated
		// .htaccess can take the whole site down, so both are a failure and
		// the pre-write backup is restored when one was taken.
		if ( false === $bytes || $bytes < strlen( $content ) ) {
			if ( '' !== $backup && file_exists( $backup ) ) {
				copy( $backup, $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- restoring the sibling backup of the site .htaccess after a failed write.
			}

			return [
				'saved'  => false,
				'reason' => 'write_failed',
				'backup' => $backup,
			];
		}

		return [
			'saved'  => true,
			'reason' => '',
			'backup' => $backup,
		];
	}
}
