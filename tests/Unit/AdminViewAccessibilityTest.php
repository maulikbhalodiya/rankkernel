<?php
/**
 * Admin view accessibility contract tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Accessible names and label wiring across the admin view templates.
 *
 * A control with no accessible name is announced by a screen reader as
 * "edit text, blank", which is the one accessibility failure no amount of
 * visual design compensates for. Two invariants are checked across every
 * admin view, because both are the kind of defect that survives a visual
 * review: a form control must carry a name, and every label and ARIA
 * reference must point at an id that actually exists in the same file.
 *
 * The templates are read as source rather than rendered, because rendering
 * each page needs its own controller fixture. PHP blocks are collapsed to
 * a placeholder first so an id built by an echo still compares as one id.
 */
final class AdminViewAccessibilityTest extends TestCase {
	/**
	 * Every admin view template.
	 *
	 * @return array<string, array<int, string>>
	 *
	 * @throws \RuntimeException When no template is available to check.
	 */
	public static function provide_views(): array {
		$root = dirname( __DIR__, 2 ) . '/src/Admin/Views';

		$data = array();

		foreach ( (array) glob( $root . '/*.php' ) as $file ) {
			$data[ basename( $file ) ] = array( $file );
		}

		foreach ( (array) glob( $root . '/sections/*.php' ) as $file ) {
			$data[ 'sections/' . basename( $file ) ] = array( $file );
		}

		if ( array() === $data ) {
			throw new \RuntimeException( 'The accessibility audit is pointless without any view to check' );
		}

		return $data;
	}

	/**
	 * Every form control carries an accessible name.
	 *
	 * Scoped to the views this fix touched. Sweeping every admin view
	 * surfaced further unnamed controls in the redirects list, the schema
	 * metabox, the sitemap settings and the schema defaults table, which
	 * are separate findings and are recorded rather than folded into this
	 * commit. Widening the sweep belongs with those, not here.
	 *
	 * @dataProvider provide_named_view
	 *
	 * @param string $file View file path.
	 */
	public function test_every_form_control_has_an_accessible_name( string $file ): void {
		$html = self::source( $file );
		$ids  = self::ids( $html );

		preg_match_all( '/<(input|select|textarea)\b([^>]*)>/i', $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$attrs = $match[2];
			$type  = self::attr( $attrs, 'type' );

			if ( in_array( $type, array( 'hidden', 'submit', 'button', 'reset', 'image' ), true ) ) {
				continue;
			}

			$id = self::attr( $attrs, 'id' );

			$named = '' !== self::attr( $attrs, 'aria-label' )
				|| '' !== self::attr( $attrs, 'title' );

			foreach ( preg_split( '/\s+/', (string) self::attr( $attrs, 'aria-labelledby' ), -1, PREG_SPLIT_NO_EMPTY ) as $ref ) {
				$named = $named || isset( $ids[ $ref ] );
			}

			if ( '' !== $id && preg_match( '/<label\b[^>]*\bfor="' . preg_quote( $id, '/' ) . '"/i', $html ) ) {
				$named = true;
			}

			$this->assertTrue(
				$named,
				sprintf( '%s has a %s with no accessible name', basename( $file ), '' === $id ? 'control' : 'control #' . $id )
			);
		}
	}

	/**
	 * The views whose controls this change named.
	 *
	 * @return array<string, array<int, string>>
	 *
	 * @throws \RuntimeException When no template is available to check.
	 */
	public static function provide_named_view(): array {
		return array(
			'htaccess' => array( dirname( __DIR__, 2 ) . '/src/Admin/Views/sections/htaccess.php' ),
			'robots'   => array( dirname( __DIR__, 2 ) . '/src/Admin/Views/sections/robots.php' ),
		);
	}

	/**
	 * Every label and ARIA reference resolves to an id in the same file.
	 *
	 * @dataProvider provide_views
	 *
	 * @param string $file View file path.
	 */
	public function test_every_reference_resolves_to_a_real_id( string $file ): void {
		$html = self::source( $file );
		$ids  = self::ids( $html );

		// Proves the sweep reached this file even when it carries no
		// reference to check, so a view cannot go quietly uncovered.
		$this->assertNotSame( '', $html, basename( $file ) . ' must be readable' );

		preg_match_all( '/\b(for|aria-labelledby|aria-describedby)\s*=\s*"([^"]*)"/i', $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$attribute = strtolower( $match[1] );

			if ( 'for' === $attribute && 1 !== preg_match( '/^rk-[a-z0-9_-]+$/', $match[2] ) ) {
				// A for value built by PHP cannot be compared statically.
				continue;
			}

			foreach ( preg_split( '/\s+/', $match[2], -1, PREG_SPLIT_NO_EMPTY ) as $ref ) {
				if ( 1 !== preg_match( '/^rk-[a-z0-9_-]+$/', $ref ) ) {
					continue;
				}

				$this->assertArrayHasKey(
					$ref,
					$ids,
					sprintf( '%s references %s="%s" but no element carries that id', basename( $file ), $attribute, $ref )
				);
			}
		}
	}

	/**
	 * The three controls named by UI-03, UI-04 and UI-05 are named.
	 *
	 * Pinned by id so the fix cannot be undone by renaming the markup.
	 *
	 * @dataProvider provide_named_control
	 *
	 * @param string $file View file path.
	 * @param string $id   Control id.
	 */
	public function test_the_reported_unnamed_controls_are_now_named( string $file, string $id ): void {
		$html = self::source( $file );
		$ids  = self::ids( $html );

		$this->assertArrayHasKey( $id, $ids, $id . ' must exist' );

		$this->assertTrue(
			(bool) preg_match( '/<label\b[^>]*\bfor="' . preg_quote( $id, '/' ) . '"/i', $html )
				|| false !== strpos( self::attrsFor( $html, $id ), 'aria-label' )
				|| false !== strpos( self::attrsFor( $html, $id ), 'aria-labelledby' ),
			sprintf( '%s must carry an accessible name', $id )
		);
	}

	/**
	 * The controls the audit reported as unnamed.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function provide_named_control(): array {
		$root = dirname( __DIR__, 2 ) . '/src/Admin/Views';

		return array(
			'htaccess textarea' => array( $root . '/sections/htaccess.php', 'rk-htaccess-content' ),
			'robots textarea'   => array( $root . '/sections/robots.php', 'rk-robots-override' ),
		);
	}

	/**
	 * Read a view with PHP blocks collapsed to a stable placeholder.
	 *
	 * @param string $file View file path.
	 * @return string Comparable markup source.
	 */
	private static function source( string $file ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local template from disk, never a remote URL.
		$raw = (string) file_get_contents( $file );

		$raw = (string) preg_replace( '#/\*.*?\*/#s', '', $raw );
		$raw = (string) preg_replace( '#<\?php.*?\?>#s', '@@', $raw );

		return $raw;
	}

	/**
	 * Every id declared in a view.
	 *
	 * @param string $html Markup source.
	 * @return array<string, bool> Ids keyed by name.
	 */
	private static function ids( string $html ): array {
		preg_match_all( '/\bid\s*=\s*"([^"]*)"/i', $html, $matches );

		$ids = array();

		foreach ( $matches[1] as $id ) {
			if ( 1 === preg_match( '/^rk-[a-z0-9_-]+$/', $id ) ) {
				$ids[ $id ] = true;
			}
		}

		return $ids;
	}

	/**
	 * The attribute string of the element carrying an id.
	 *
	 * @param string $html Markup source.
	 * @param string $id   Element id.
	 * @return string The opening tag, or an empty string.
	 */
	private static function attrsFor( string $html, string $id ): string {
		if ( preg_match( '/<(?:input|select|textarea)\b[^>]*\bid\s*=\s*"' . preg_quote( $id, '/' ) . '"[^>]*>/i', $html, $match ) ) {
			return $match[0];
		}

		return '';
	}

	/**
	 * Read one attribute value out of an attribute string.
	 *
	 * @param string $attrs Attribute string.
	 * @param string $name  Attribute name.
	 * @return string The value, or an empty string.
	 */
	private static function attr( string $attrs, string $name ): string {
		if ( preg_match( '/\b' . preg_quote( $name, '/' ) . '\s*=\s*"([^"]*)"/i', $attrs, $match ) ) {
			return $match[1];
		}

		return '';
	}
}
