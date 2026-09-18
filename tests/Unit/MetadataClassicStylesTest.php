<?php
/**
 * Classic metadata stylesheet design-system contract tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Locks the Classic Editor stylesheet to the shared token layer.
 *
 * The Classic metabox is a separate implementation from the Gutenberg
 * sidebar but must share one design system, so it may only consume the
 * tokens defined in rankkernel-admin.css and never invent a local palette
 * or a raw colour literal.
 */
final class MetadataClassicStylesTest extends TestCase {
	/**
	 * Read a stylesheet from the assets directory.
	 *
	 * @param string $name File name.
	 * @return string The stylesheet source.
	 */
	private function css( string $name ): string {
		$path = dirname( __DIR__, 2 ) . '/assets/css/' . $name;

		$this->assertFileExists( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local plugin asset, not a remote URL.
		return (string) file_get_contents( $path );
	}

	/**
	 * The stylesheet imports the shared token layer.
	 */
	public function test_imports_the_shared_token_layer(): void {
		$this->assertStringContainsString(
			'@import url("rankkernel-admin.css")',
			$this->css( 'metadata-classic.css' )
		);
	}

	/**
	 * The stylesheet carries no raw colour literals.
	 */
	public function test_has_no_raw_colour_literals(): void {
		$css = $this->css( 'metadata-classic.css' );

		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $css, 'metadata-classic.css must use var(--rk-*) tokens, not hex colours.' );
		$this->assertDoesNotMatchRegularExpression( '/\b(?:rgb|rgba|hsl|hsla)\s*\(/i', $css, 'metadata-classic.css must use var(--rk-*) tokens, not rgb/hsl functions.' );
	}

	/**
	 * The stylesheet declares no custom properties of its own.
	 */
	public function test_invents_no_local_palette(): void {
		$this->assertDoesNotMatchRegularExpression(
			'/--rk-[a-z0-9-]+\s*:/',
			$this->css( 'metadata-classic.css' ),
			'metadata-classic.css must consume the shared tokens, never declare its own.'
		);
	}

	/**
	 * Every token the stylesheet consumes is defined in the token layer.
	 */
	public function test_only_references_defined_tokens(): void {
		$css   = $this->css( 'metadata-classic.css' );
		$admin = $this->css( 'rankkernel-admin.css' );

		preg_match_all( '/var\(\s*(--rk-[a-z0-9-]+)/', $css, $usedMatches );
		preg_match_all( '/(--rk-[a-z0-9-]+)\s*:/', $admin, $definedMatches );

		$used    = array_values( array_unique( $usedMatches[1] ) );
		$defined = array_values( array_unique( $definedMatches[1] ) );

		$this->assertNotSame( [], $used, 'metadata-classic.css must consume the shared design tokens.' );

		$missing = array_values( array_diff( $used, $defined ) );

		$this->assertSame(
			[],
			$missing,
			"metadata-classic.css references tokens the design system does not define:\n - " . implode( "\n - ", $missing )
		);
	}
}
