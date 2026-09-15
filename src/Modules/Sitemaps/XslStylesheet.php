<?php
/**
 * XSL stylesheet handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the sitemap XSL stylesheet.
 */
class XslStylesheet {
	/**
	 * Get path to bundled XSL file.
	 *
	 * @return string The result.
	 */
	public function getPath(): string {
		return __DIR__ . '/sitemap.xsl';
	}

	/**
	 * Output the stylesheet with headers and exit.
	 */
	public function output(): void {
		$path = $this->getPath();

		if ( ! file_exists( $path ) ) {
			status_header( 404 );
			echo esc_html__( 'Stylesheet not found.', 'rankkernel' );

			if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
				exit;
			}

			return;
		}

		header( 'Content-Type: text/xsl; charset=UTF-8' );
		header( 'Cache-Control: public, max-age=31536000' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 31536000 ) . ' GMT' );
		header( 'X-Content-Type-Options: nosniff' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- readfile streams the generated XSL stylesheet to output, WP_Filesystem is for file management, not output.
		readfile( $path );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}
}
