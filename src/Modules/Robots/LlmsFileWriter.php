<?php
/**
 * Optional physical llms.txt writer.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Writes a physical llms.txt only when one does not already exist.
 *
 * The virtual route is the default. Writing a physical file is opt-in and
 * always refuses to overwrite an existing file, so a hand made llms.txt is
 * never lost.
 */
final class LlmsFileWriter {
	/**
	 * Get the physical llms.txt path, filterable for tests.
	 *
	 * The filter is a public hook, so the value is only used when it is a non
	 * empty string that resolves inside the site root. Anything else, including
	 * a path that climbs out of the tree, falls back to the default so the
	 * writer can never be pointed at an arbitrary file.
	 *
	 * @return string The result.
	 */
	public function path(): string {
		$default = defined( 'ABSPATH' ) ? ABSPATH . 'llms.txt' : '';

		$filtered = apply_filters( 'rankkernel/llms/physical_file', $default ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( ! is_string( $filtered ) || '' === $filtered ) {
			return $default;
		}

		if ( $filtered === $default ) {
			return $default;
		}

		if ( ! defined( 'ABSPATH' ) ) {
			return $filtered;
		}

		return $this->containedPath( $filtered, $default );
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
		if ( false !== strpos( $path, "\0" ) || '/' !== $path[0] ) {
			return $fallback;
		}

		if ( is_link( $path ) ) {
			return $fallback;
		}

		if ( ! function_exists( 'realpath' ) || ! function_exists( 'wp_normalize_path' ) ) {
			return $fallback;
		}

		$root = realpath( ABSPATH );
		$dir  = realpath( dirname( $path ) );

		if ( false === $root || false === $dir ) {
			return $fallback;
		}

		$rootNorm = rtrim( wp_normalize_path( $root ), '/' ) . '/';
		$dirNorm  = rtrim( wp_normalize_path( $dir ), '/' ) . '/';

		if ( ! $this->pathStartsWith( $dirNorm, $rootNorm ) ) {
			return $fallback;
		}

		if ( file_exists( $path ) ) {
			$realPath = realpath( $path );

			if ( false === $realPath ) {
				return $fallback;
			}

			$fileNorm = wp_normalize_path( $realPath );

			if ( ! $this->pathStartsWith( $fileNorm, $rootNorm ) ) {
				return $fallback;
			}

			return $realPath;
		}

		return $path;
	}

	/**
	 * Whether a path starts with a root prefix.
	 *
	 * The str_starts_with helper is used when available and strpos as the
	 * fallback. When neither exists the check fails closed, so the caller keeps
	 * the default rather than trusting the filter.
	 *
	 * @param string $path Path to test.
	 * @param string $root Root prefix, with a trailing slash.
	 * @return bool The result.
	 */
	private function pathStartsWith( string $path, string $root ): bool {
		if ( function_exists( 'str_starts_with' ) ) {
			return str_starts_with( $path, $root );
		}

		if ( function_exists( 'strpos' ) ) {
			return 0 === strpos( $path, $root );
		}

		return false;
	}

	/**
	 * Whether a physical llms.txt already exists.
	 *
	 * @return bool The result.
	 */
	public function exists(): bool {
		$path = $this->path();

		return '' !== $path && file_exists( $path );
	}

	/**
	 * Write the physical llms.txt unless one exists or file edits are disabled.
	 *
	 * Refuses to write when DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS is active,
	 * or when a file already exists at the target path.
	 *
	 * @param string $content Markdown content.
	 * @return array{written: bool, reason: string} Result and reason code.
	 */
	public function write( string $content ): array {
		if (
			( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
			|| ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		) {
			return [
				'written' => false,
				'reason'  => 'not_writable',
			];
		}

		$path = $this->path();

		if ( '' === $path ) {
			return [
				'written' => false,
				'reason'  => 'no_path',
			];
		}

		if ( file_exists( $path ) ) {
			return [
				'written' => false,
				'reason'  => 'exists',
			];
		}

		// The file may not exist yet, so writability is checked on its directory.
		// Without this guard file_put_contents() emits a warning and returns
		// false, which the caller would only see as a generic write failure.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- a directory writability probe on the opt-in write path, not a file write.
		if ( function_exists( 'is_writable' ) && ! is_writable( dirname( $path ) ) ) {
			return [
				'written' => false,
				'reason'  => 'write_failed',
			];
		}

		// LOCK_EX keeps a concurrent request from interleaving a partial write.
		$bytes = file_put_contents( $path, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing the opt-in physical llms.txt at the site root, the only portable option.

		if ( false === $bytes ) {
			return [
				'written' => false,
				'reason'  => 'write_failed',
			];
		}

		return [
			'written' => true,
			'reason'  => '',
		];
	}
}
