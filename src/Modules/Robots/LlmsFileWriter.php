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
 * always refuses to overwrite an existing file, so a hand-made llms.txt is
 * never lost.
 */
final class LlmsFileWriter {
	/**
	 * Get the physical llms.txt path, filterable for tests.
	 *
	 * @return string The result.
	 */
	public function path(): string {
		$default = defined( 'ABSPATH' ) ? ABSPATH . 'llms.txt' : '';

		$path = apply_filters( 'rankkernel/llms/physical_file', $default ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		return ( is_string( $path ) && '' !== $path ) ? $path : $default;
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
	 * Write the physical llms.txt unless one exists.
	 *
	 * @param string $content Markdown content.
	 * @return array{written: bool, reason: string} Result and reason code.
	 */
	public function write( string $content ): array {
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

		$bytes = file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing the opt-in physical llms.txt at the site root, the only portable option.

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
