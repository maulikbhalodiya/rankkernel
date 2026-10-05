<?php
/**
 * Design token root tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Design Token Root Test.
 *
 * The token layer is the only place in the plugin that declares the --rk-*
 * custom properties, and it declares them inside a selector list naming each
 * RankKernel root class. A surface that adopts the shared rk-ui components but
 * is missing from that list gets no tokens at all, so every var(--rk-*) in the
 * component layer becomes invalid at computed value time and silently falls
 * back: no card border, no notice accent bar, no pill, and an icon font that
 * never loads. That failure is invisible in a test suite and obvious on screen.
 *
 * These tests parse the stylesheet and every view root, so the next page to
 * adopt the design system cannot repeat the omission.
 */
final class DesignTokenRootTest extends TestCase {
	/**
	 * The minimum contrast ratio WCAG AA asks for text below 18 point.
	 */
	private const AA_TEXT_RATIO = 4.5;

	/**
	 * The token stylesheet, relative to the plugin root.
	 */
	private const TOKEN_SHEET = 'assets/css/rankkernel-admin.css';

	/**
	 * The view directory, relative to the plugin root.
	 */
	private const VIEW_DIR = 'src/Admin/Views';

	/**
	 * Read the comma separated selector list that scopes the token block.
	 *
	 * The list is the selector immediately preceding the custom properties,
	 * which always start with the sans font family declaration.
	 *
	 * @return string[] The scope class names, without the leading dot.
	 */
	private function tokenScopeClasses(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local stylesheet from disk, never a remote URL.
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . self::TOKEN_SHEET );

		$this->assertNotSame( '', $css, self::TOKEN_SHEET . ' must be readable' );

		$matched = preg_match( '/(?P<list>[^{}]+?)\s*\{\s*\/\*[^}]*?\*\/\s*font-family:\s*var\(--rk-font-sans\)/s', $css, $matches );

		$this->assertSame( 1, $matched, 'The token block must be found in ' . self::TOKEN_SHEET );

		$classes = [];
		foreach ( explode( ',', $matches['list'] ) as $selector ) {
			$selector = trim( $selector );

			if ( '' !== $selector && 1 === preg_match( '/^\.([a-z0-9-]+)$/', $selector, $found ) ) {
				$classes[] = $found[1];
			}
		}

		$this->assertNotEmpty( $classes, 'The token selector list must name at least one class' );

		return $classes;
	}

	/**
	 * Every view root that opts into rk-ui must receive the tokens.
	 *
	 * @dataProvider provide_adopting_views
	 *
	 * @param string $view        View file name.
	 * @param string $rootClass   The page scope class on the root element.
	 */
	public function test_adopting_view_root_is_a_token_root( string $view, string $rootClass ): void {
		$this->assertContains(
			$rootClass,
			$this->tokenScopeClasses(),
			sprintf(
				'%s renders rk-ui components, so its root class %s must appear in the token selector list in %s. Without it every var(--rk-*) in the component layer is invalid on that page.',
				$view,
				$rootClass,
				self::TOKEN_SHEET
			)
		);
	}

	/**
	 * Views that opt into the shared component layer.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function provide_adopting_views(): array {
		return array(
			'dashboard'     => array( 'dashboard.php', 'rk-dashboard' ),
			'sitemap'       => array( 'sitemap-settings.php', 'rk-sitemap-settings' ),
			'schema'        => array( 'schema-settings.php', 'rk-schema-settings' ),
			'404 monitor'   => array( 'not-found.php', 'rk-monitor' ),
			'instant index' => array( 'instant-indexing.php', 'rk-instant-indexing' ),
		);
	}

	/**
	 * No view may opt into the components while missing from the token list.
	 *
	 * This is the guard that would have caught the omission before it shipped.
	 * It reads the views rather than trusting the provider above, so a new page
	 * is covered the moment it adopts rk-ui.
	 */
	public function test_no_view_adopts_the_components_without_tokens(): void {
		$scopes   = $this->tokenScopeClasses();
		$adopting = [];

		foreach ( (array) glob( dirname( __DIR__, 2 ) . '/' . self::VIEW_DIR . '/*.php' ) as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local view file from disk, never a remote URL.
			$html = (string) file_get_contents( $file );

			if ( 1 !== preg_match( '/class="([^"]*\brk-ui\b[^"]*)"/', $html, $match ) ) {
				continue;
			}

			foreach ( explode( ' ', $match[1] ) as $class ) {
				$class = trim( $class );

				/*
				 * Only page scope classes matter. wrap is not a scope, and
				 * neither is anything in the rk-ui component namespace, because
				 * those classes live in the shared component layer and can
				 * never be a token root. A partial such as the Redirects list
				 * section inherits the rk-ui root of the view that includes
				 * it, so its first rk-ui class is a component, not a scope.
				 */
				if ( 1 !== preg_match( '/^rk-[a-z0-9-]+$/', $class ) || 0 === strpos( $class, 'rk-ui' ) ) {
					continue;
				}

				$adopting[ basename( $file ) ][] = $class;
			}
		}

		$missing = array();

		foreach ( $adopting as $view => $classes ) {
			foreach ( array_unique( $classes ) as $class ) {
				if ( ! in_array( $class, $scopes, true ) ) {
					$missing[] = sprintf( '%s uses %s but it is not a token root', $view, $class );
				}
			}
		}

		$this->assertSame( array(), $missing, "Views missing from the token scope list:\n" . implode( "\n", $missing ) );
	}

	/**
	 * Raw hex must not leak outside the token layer.
	 *
	 * The token file header states that raw hex belongs there alone, so any
	 * other sheet carrying a literal colour is a second palette creeping back.
	 *
	 * @dataProvider provide_css_sheets
	 *
	 * @param string $sheet Stylesheet file name.
	 */
	public function test_sheets_other_than_the_token_layer_carry_no_raw_hex( string $sheet ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local stylesheet from disk, never a remote URL.
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/' . $sheet );

		// Strip comments so a hex inside a docblock is not a finding.
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		preg_match_all( '/#[0-9a-fA-F]{3,8}\b/', $css, $matches );

		$this->assertSame(
			array(),
			array_values( array_unique( $matches[0] ) ),
			sprintf( '%s must resolve colour from the --rk-* tokens, found: %s', $sheet, implode( ', ', array_unique( $matches[0] ) ) )
		);
	}

	/**
	 * Every shipped stylesheet other than the token layer.
	 *
	 * @return array<string, array<int, string>>
	 *
	 * @throws \RuntimeException When no stylesheet is available to check.
	 */
	public static function provide_css_sheets(): array {
		$data = array();

		foreach ( (array) glob( dirname( __DIR__, 2 ) . '/assets/css/*.css' ) as $file ) {
			$name = basename( $file );

			if ( 'rankkernel-admin.css' === $name ) {
				continue;
			}

			$data[ $name ] = array( $name );
		}

		if ( array() === $data ) {
			throw new \RuntimeException( 'The hex audit is pointless without any sheet to check' );
		}

		return $data;
	}

	/**
	 * Every token foreground clears 4.5:1 against the background it sits on.
	 *
	 * WCAG AA asks 4.5:1 for body text and anything below 18 point bold. These
	 * nine combinations were measured as failing, the worst being the hint
	 * text at 2.56 on white and 2.29 on the canvas. A palette that fails here
	 * is invisible to a unit test that only checks the token exists, so the
	 * ratio is computed from the shipped values and locked.
	 *
	 * @dataProvider provide_contrast_pairs
	 *
	 * @param string $token Foreground token name.
	 * @param string $bg    Background token name it is drawn on.
	 */
	public function test_token_foreground_clears_wcag_aa( string $token, string $bg ): void {
		$tokens = self::tokenValues();

		$this->assertArrayHasKey( $token, $tokens, $token . ' must be declared' );
		$this->assertArrayHasKey( $bg, $tokens, $bg . ' must be declared' );

		$ratio = self::contrastRatio( $tokens[ $token ], $tokens[ $bg ] );

		$this->assertGreaterThanOrEqual(
			self::AA_TEXT_RATIO,
			$ratio,
			sprintf( '%s on %s is %.2f:1, below the 4.5:1 AA minimum for text', $token, $bg, $ratio )
		);
	}

	/**
	 * The foreground and background token pairs that carry text.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function provide_contrast_pairs(): array {
		return array(
			'hint on surface' => array( '--rk-color-text-hint', '--rk-color-surface' ),
			'hint on canvas'  => array( '--rk-color-text-hint', '--rk-color-canvas' ),
			'muted on canvas' => array( '--rk-color-text-muted', '--rk-color-canvas' ),
			'410 badge'       => array( '--rk-badge-410-fg', '--rk-badge-410-bg' ),
			'regex badge'     => array( '--rk-badge-regex-fg', '--rk-badge-regex-bg' ),
			'inactive badge'  => array( '--rk-badge-inactive-fg', '--rk-badge-inactive-bg' ),
			'good score'      => array( '--rk-score-good', '--rk-score-good-soft' ),
			'problem score'   => array( '--rk-score-problem', '--rk-score-problem-soft' ),
			'neutral score'   => array( '--rk-score-neutral', '--rk-score-neutral-soft' ),
		);
	}

	/**
	 * Read the declared token values out of the token layer.
	 *
	 * @return array<string, string> Token name to six digit hex.
	 */
	private static function tokenValues(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local stylesheet from disk, never a remote URL.
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . self::TOKEN_SHEET );

		$values = array();

		if ( preg_match_all( '/(--rk-[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/', $css, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$values[ $match[1] ] = $match[2];
			}
		}

		return $values;
	}

	/**
	 * WCAG relative luminance contrast ratio between two hex colours.
	 *
	 * The formula from the WCAG 2.1 definition, computed here so the lock is
	 * a measurement rather than a copied number.
	 *
	 * @param string $foreground Foreground hex.
	 * @param string $background Background hex.
	 * @return float Contrast ratio, 1.0 to 21.0.
	 */
	private static function contrastRatio( string $foreground, string $background ): float {
		$first  = self::relativeLuminance( $foreground );
		$second = self::relativeLuminance( $background );

		$lighter = max( $first, $second );
		$darker  = min( $first, $second );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Relative luminance of one hex colour.
	 *
	 * @param string $hex Six digit hex.
	 * @return float Luminance between 0 and 1.
	 */
	private static function relativeLuminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$channels = array();

		foreach ( array( 0, 2, 4 ) as $offset ) {
			$value      = hexdec( substr( $hex, $offset, 2 ) ) / 255.0;
			$channels[] = $value <= 0.04045 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
		}

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
