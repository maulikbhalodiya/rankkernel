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
	 * The stylesheet no longer imports the token layer over CSS.
	 *
	 * The token layer is a registered dependency now, so the browser fetches
	 * both sheets in parallel instead of chaining a second request.
	 */
	public function test_does_not_import_the_shared_token_layer(): void {
		$this->assertStringNotContainsString(
			'@import',
			$this->css( 'metadata-classic.css' ),
			'metadata-classic.css must depend on the token handle, not import it.'
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

	/**
	 * Every rule that removes a focus outline must give one back.
	 *
	 * Removing an outline is not the defect on its own, the defect is when
	 * nothing replaces it. This walks every outline none in the editor
	 * sheet, finds the selector it belongs to, and requires that the
	 * element itself to draw an indicator in a focus state. Without
	 * this, dropping a focus ring is invisible to every other test.
	 */
	public function test_every_removed_outline_has_a_replacement_indicator(): void {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $this->css( 'metadata-editor.css' ) );

		$offset = 0;
		$found  = 0;

		while ( preg_match( '/outline\s*:\s*none/', $css, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			++$found;
			$at = $match[0][1];

			// Walk back to the start of the rule this declaration belongs to.
			$head = strrpos( substr( $css, 0, $at ), '{' );
			$open = strrpos( substr( $css, 0, (int) $head ), '}' );
			$from = false === $open ? 0 : $open + 1;

			$selector = trim( substr( $css, $from, (int) $head - $from ) );

			$target    = $this->focusTarget( $selector );
			$indicator = $this->hasIndicator( $css, $target );

			$this->assertTrue(
				$indicator,
				sprintf( '%s removes its focus outline but never draws a replacement', $selector )
			);

			$offset = $at + strlen( $match[0][0] );
		}

		$this->assertGreaterThan( 0, $found, 'the editor sheet is expected to suppress some outlines' );
	}

	/**
	 * The element a selector targets, with its scoping prefix stripped.
	 *
	 * @param string $selector Rule selector.
	 * @return string The trailing class name.
	 */
	private function focusTarget( string $selector ): string {
		$parts = (array) preg_split( '/\s*,\s*/', $selector );
		$last  = trim( (string) $parts[ count( $parts ) - 1 ] );

		// The last class is the element the rule targets. Taking the first
		// one returns the scoping prefix instead, which matches some other
		// rule in the sheet and made this check pass with the replacement
		// removed.
		if ( preg_match_all( '/\.([a-z0-9_-]+)/i', $last, $classMatch ) && $classMatch[1] ) {
			$classes = $classMatch[1];

			return (string) $classes[ count( $classes ) - 1 ];
		}

		return $last;
	}

	/**
	 * Whether the element itself draws a focus indicator.
	 *
	 * Deliberately strict. An indicator on some other element in the file
	 * says nothing about this one, and accepting that made the check pass
	 * with the replacement removed. The indicator has to belong to the
	 * target class, in a focus or focus-visible state, either drawn in
	 * normal rendering or restored in a forced-colors block where
	 * box-shadow is not rendered at all.
	 *
	 * @param string $css    Stylesheet source.
	 * @param string $target Class name.
	 * @return bool True when an indicator rule exists.
	 */
	private function hasIndicator( string $css, string $target ): bool {
		$quoted = preg_quote( $target, '/' );

		$patterns = array(
			'/\.' . $quoted . '[^{}]*:focus(?:-visible|-within)?[^{}]*\{[^}]*(?:box-shadow|outline)\s*:\s*(?!none)/s',
			'/@media\s+forced-colors[^{]*\{[^@]*?\.' . $quoted . '[^{}]*:focus[^{}]*\{[^}]*outline\s*:\s*(?!none)/s',
		);

		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $css ) ) {
				return true;
			}
		}

		return false;
	}
}
