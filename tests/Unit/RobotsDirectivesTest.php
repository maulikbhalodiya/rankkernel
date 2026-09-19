<?php
/**
 * Robots.txt validation tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\RobotsDirectives;

/**
 * Robots Directives Test.
 */
final class RobotsDirectivesTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test normalise strips a BOM and control characters.
	 */
	public function test_normalize_strips_bom_and_control_chars(): void {
		$out = RobotsDirectives::normalize( "\xEF\xBB\xBFUser-agent: *\r\nDisallow: /wp-admin/\x07\n" );

		$this->assertStringNotContainsString( "\xEF\xBB\xBF", $out );
		$this->assertStringNotContainsString( "\x07", $out );
		$this->assertStringNotContainsString( "\r", $out );
		$this->assertStringContainsString( "User-agent: *\n", $out );
	}

	/**
	 * Test an unknown directive is an error.
	 */
	public function test_unknown_directive_is_error(): void {
		$result = RobotsDirectives::validate( "Foo: bar\n" );

		$this->assertCount( 1, $result['errors'] );
	}

	/**
	 * Test a relative sitemap is an error.
	 */
	public function test_relative_sitemap_is_error(): void {
		$result = RobotsDirectives::validate( "Sitemap: /sitemap.xml\n" );

		$this->assertCount( 1, $result['errors'] );
	}

	/**
	 * Test a cross host sitemap warns.
	 */
	public function test_cross_host_sitemap_warns(): void {
		$result = RobotsDirectives::validate( "Sitemap: https://other.example/sitemap.xml\n" );

		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 1, $result['warnings'] );
	}

	/**
	 * Test a clean block passes.
	 */
	public function test_clean_block_passes(): void {
		$text = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nSitemap: https://example.com/sitemap.xml\n";

		$result = RobotsDirectives::validate( $text );

		$this->assertSame( [], $result['errors'] );
		$this->assertSame( [], $result['warnings'] );
	}
}
