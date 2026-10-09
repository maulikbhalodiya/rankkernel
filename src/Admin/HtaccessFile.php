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
	 * @return string The result.
	 */
	public function path(): string {
		$default = defined( 'ABSPATH' ) ? ABSPATH . '.htaccess' : '';

		$filtered = apply_filters( 'rankkernel/htaccess/path', $default ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( ! is_string( $filtered ) || '' === $filtered || $filtered === $default ) {
			return $default;
		}

		return $this->containedPath( $filtered, $default );
	}

	/**
	 * Keep a filtered path only when it resolves inside the site root.
	 *
	 * Rejects null bytes, relative paths and symlinks, then validates that both
	 * the parent directory and the file itself, when it already exists, resolve
	 * inside ABSPATH.
	 *
	 * @param string $path Filtered path.
	 * @param string $fallback Default path.
	 * @return string The result.
	 */
	private function containedPath( string $path, string $fallback ): string {
		// A null byte makes later filesystem calls raise a ValueError, and a
		// relative path would be resolved against the current working directory
		// rather than the site root, so neither can be checked for containment.
		if ( false !== strpos( $path, "\0" ) || '/' !== $path[0] ) {
			return $fallback;
		}

		// Rejecting a symlink outright is what closes the dangling symlink case.
		// A link whose target does not exist yet fails both file_exists() and
		// realpath(), so a leaf check gated on either would be skipped while a
		// later write still followed the link to a target outside the root.
		if ( is_link( $path ) ) {
			return $fallback;
		}

		if ( ! function_exists( 'wp_normalize_path' ) ) {
			return $fallback;
		}

		$root = realpath( ABSPATH );
		$dir  = realpath( dirname( $path ) );

		if ( false === $root || false === $dir ) {
			return $fallback;
		}

		$rootNorm = rtrim( wp_normalize_path( $root ), '/' ) . '/';
		$dirNorm  = rtrim( wp_normalize_path( $dir ), '/' ) . '/';

		if ( ! str_starts_with( $dirNorm, $rootNorm ) ) {
			return $fallback;
		}

		if ( file_exists( $path ) ) {
			$realPath = realpath( $path );

			if ( false === $realPath ) {
				return $fallback;
			}

			$fileNorm = wp_normalize_path( $realPath );

			if ( ! str_starts_with( $fileNorm, $rootNorm ) ) {
				return $fallback;
			}

			return $realPath;
		}

		return $path;
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
	 * How many backups to keep; older ones are pruned on every save.
	 */
	public const BACKUP_KEEP = 5;

	/**
	 * Directory holding backups, outside the site root's servable files.
	 *
	 * Backups used to sit next to the live .htaccess, where the timestamped
	 * pattern was directly web-fetchable while .htaccess itself 403s. They
	 * now live in a guarded uploads subdirectory with a silence index and a
	 * deny file, so config contents never leak over HTTP.
	 *
	 * @return string Directory path, empty when it cannot be prepared.
	 */
	public function backupDir(): string {
		$base = '';

		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir();

			// Core documents basedir as always present; a filter reshaping
			// that would break core itself, so read it directly once the
			// array shape is confirmed.
			if ( is_array( $uploads ) ) {
				$base = (string) $uploads['basedir'];
			}
		}

		if ( '' === $base && defined( 'ABSPATH' ) ) {
			$base = rtrim( (string) ABSPATH, '/' ) . '/wp-content/uploads';
		}

		if ( '' === $base ) {
			return '';
		}

		$dir = rtrim( $base, '/' ) . '/rankkernel-htaccess-backups';

		if ( ! file_exists( $dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- creating the guarded backup directory for .htaccess backups.
			if ( ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
				return '';
			}
		}

		$index = $dir . '/index.php';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- silence file guarding the backup directory.
			file_put_contents( $index, '<?php // Silence is golden.' );
		}

		$deny = $dir . '/.htaccess';

		if ( ! file_exists( $deny ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- deny file guarding the backup directory on Apache stacks.
			file_put_contents( $deny, "Require all denied\n" );
		}

		return $dir;
	}

	/**
	 * Path of the backup written before the next save.
	 *
	 * No extension: the name carries no content hint and matches nothing a
	 * static server would serve with a text type by default.
	 *
	 * @return string The result.
	 */
	public function backupPath(): string {
		$dir = $this->backupDir();

		if ( '' === $dir ) {
			return $this->path() . '.rankkernel-backup-' . gmdate( 'YmdHis' );
		}

		// uniqid() suffix: two saves inside the same second must not share
		// a name, or the later copy would silently overwrite the earlier
		// backup the restore-on-failure path may need.
		return $dir . '/htaccess-backup-' . gmdate( 'YmdHis' ) . '-' . uniqid();
	}

	/**
	 * Prune old backups, keeping the newest BACKUP_KEEP.
	 *
	 * @return int Removed backup count.
	 */
	public function pruneBackups(): int {
		$dir = $this->backupDir();

		if ( '' === $dir ) {
			return 0;
		}

		$files = glob( $dir . '/htaccess-backup-*' );

		if ( ! is_array( $files ) ) {
			return 0;
		}

		rsort( $files );

		$stale   = array_slice( $files, self::BACKUP_KEEP );
		$removed = 0;

		foreach ( $stale as $staleFile ) {
			if ( is_file( $staleFile ) && ! is_link( $staleFile ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- pruning superseded .htaccess backups the plugin itself wrote.
				if ( unlink( $staleFile ) ) {
					++$removed;
				}
			}
		}

		return $removed;
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

			if ( ! copy( $path, $backup ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- writing a guarded backup of the site .htaccess.
				return [
					'saved'  => false,
					'reason' => 'backup_failed',
					'backup' => '',
				];
			}

			$this->pruneBackups();
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
