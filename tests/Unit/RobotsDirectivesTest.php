<?php
/**
 * Robots.txt directive validation and preset tests.
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

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $url ): string => trim( $url ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			static function ( string $key ): string {
				return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
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
	 * Test presets include the required AI crawlers.
	 */
	public function test_presets_include_required_crawlers(): void {
		$slugs = RobotsDirectives::presetSlugs();

		foreach ( [ 'gptbot', 'oai-searchbot', 'chatgpt-user', 'claudebot', 'perplexitybot', 'google-extended', 'ccbot' ] as $slug ) {
			$this->assertContains( $slug, $slugs );
		}
	}

	/**
	 * Test sanitize presets keeps known unique slugs only.
	 */
	public function test_sanitize_presets_filters_unknown_and_dedupes(): void {
		$out = RobotsDirectives::sanitizePresets( [ 'gptbot', 'not-real', 'gptbot', 'CCBot' ] );

		$this->assertSame( [ 'gptbot', 'ccbot' ], $out );
	}

	/**
	 * Test sanitize sitemap url requires an absolute http url.
	 */
	public function test_sanitize_sitemap_url_requires_absolute(): void {
		$this->assertSame( '', RobotsDirectives::sanitizeSitemapUrl( '/sitemap.xml' ) );
		$this->assertSame( '', RobotsDirectives::sanitizeSitemapUrl( '' ) );
		$this->assertSame( 'https://example.com/sitemap.xml', RobotsDirectives::sanitizeSitemapUrl( 'https://example.com/sitemap.xml' ) );
	}

	/**
	 * Test normalize strips bom and control characters.
	 */
	public function test_normalize_strips_bom_and_control_chars(): void {
		$raw = "\xEF\xBB\xBFUser-agent: *\r\nDisallow: /wp-admin/\x07\n";

		$out = RobotsDirectives::normalize( $raw );

		$this->assertStringNotContainsString( "\xEF\xBB\xBF", $out );
		$this->assertStringNotContainsString( "\x07", $out );
		$this->assertStringNotContainsString( "\r", $out );
		$this->assertStringContainsString( "User-agent: *\n", $out );
	}

	/**
	 * Test validate reports unknown directive.
	 */
	public function test_validate_reports_unknown_directive(): void {
		$result = RobotsDirectives::validate( "Foo: bar\n" );

		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( 'unknown directive', $result['errors'][0] );
	}

	/**
	 * Test validate rejects relative sitemap.
	 */
	public function test_validate_rejects_relative_sitemap(): void {
		$result = RobotsDirectives::validate( "Sitemap: /sitemap.xml\n" );

		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( 'absolute', $result['errors'][0] );
	}

	/**
	 * Test validate warns on cross host sitemap.
	 */
	public function test_validate_warns_on_cross_host_sitemap(): void {
		$result = RobotsDirectives::validate( "Sitemap: https://other.example/sitemap.xml\n" );

		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 1, $result['warnings'] );
	}

	/**
	 * Test validate warns on crawl delay.
	 */
	public function test_validate_warns_on_crawl_delay(): void {
		$result = RobotsDirectives::validate( "Crawl-delay: 10\n" );

		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 1, $result['warnings'] );
	}

	/**
	 * Test validate accepts a clean block.
	 */
	public function test_validate_accepts_clean_block(): void {
		$text = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nSitemap: https://example.com/sitemap.xml\n";

		$result = RobotsDirectives::validate( $text );

		$this->assertSame( [], $result['errors'] );
		$this->assertSame( [], $result['warnings'] );
	}
}
