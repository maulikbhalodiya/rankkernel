<?php
/**
 * XSL stylesheet handler tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\XslStylesheet;

/**
 * Xsl Stylesheet Test.
 */
final class XslStylesheetTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		Functions\when( 'esc_html__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'nocache_headers' )->justReturn( null );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test an unreadable stylesheet 404s without the long cache headers.
	 */
	public function test_unreadable_stylesheet_404s_without_long_cache_headers(): void {
		// Regression: file_exists passed for an unreadable file, so the
		// year long cache headers were sent before readfile failed, and a
		// visitor would cache the truncated stylesheet.
		$path = tempnam( sys_get_temp_dir(), 'rkxsl' );

		$this->assertIsString( $path );
		file_put_contents( $path, '<xsl:stylesheet>secret</xsl:stylesheet>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes its own temp file.
		chmod( $path, 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- test fixture removes read permission to exercise the guard.

		if ( is_readable( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

			$this->markTestSkipped( 'Filesystem ignores chmod for this user.' );
		}

		Functions\expect( 'status_header' )->once()->with( 404 );

		$stylesheet = new class( $path ) extends XslStylesheet {
			/**
			 * Constructor.
			 *
			 * @param string $path Stylesheet path under test.
			 */
			public function __construct( private readonly string $path ) {
			}

			/**
			 * Get path to bundled XSL file.
			 *
			 * @return string The result.
			 */
			public function getPath(): string {
				return $this->path;
			}
		};

		ob_start();
		$stylesheet->output();
		$out = ob_get_clean();

		chmod( $path, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- test fixture restores permission so it can clean up.
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$this->assertStringContainsString( 'Stylesheet not found.', $out );
		$this->assertStringNotContainsString( 'secret', $out );
	}

	/**
	 * Test a readable stylesheet streams the bundled file.
	 */
	public function test_readable_stylesheet_streams_bundled_file(): void {
		$stylesheet = new XslStylesheet();

		ob_start();
		$stylesheet->output();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'xsl:stylesheet', $out );
	}
}
