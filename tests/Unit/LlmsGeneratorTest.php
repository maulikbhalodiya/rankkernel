<?php
/**
 * Llms.txt rendering and validation tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsGenerator;

/**
 * Llms Generator Test.
 */
final class LlmsGeneratorTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
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
	 * Test the document renders the H1, summary and curated sections.
	 */
	public function test_renders_heading_summary_and_content(): void {
		$content = "## Company\n\n- [About us](https://example.com/about): Who we are\n- [Pricing](https://example.com/pricing)\n";

		$out = ( new LlmsGenerator() )->render( 'Example Ltd', 'A short summary.', $content );

		$this->assertStringContainsString( "# Example Ltd\n", $out );
		$this->assertStringContainsString( '> A short summary.', $out );
		$this->assertStringContainsString( '## Company', $out );
		$this->assertStringContainsString( '- [About us](https://example.com/about): Who we are', $out );
		$this->assertStringEndsWith( "\n", $out );
	}

	/**
	 * Test a non-absolute link is rejected.
	 */
	public function test_relative_link_is_an_error(): void {
		$result = ( new LlmsGenerator() )->validate( "- [Relative](/about)\n" );

		$this->assertCount( 1, $result['errors'] );
	}

	/**
	 * Test a duplicate URL warns.
	 */
	public function test_duplicate_url_warns(): void {
		$content = "- [One](https://example.com/a)\n- [Two](https://example.com/a)\n";

		$result = ( new LlmsGenerator() )->validate( $content );

		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 1, $result['warnings'] );
	}

	/**
	 * Test a clean content block passes.
	 */
	public function test_clean_content_passes(): void {
		$content = "## Company\n\n- [About](https://example.com/about): Who we are\n- [Contact](https://example.com/contact)\n";

		$result = ( new LlmsGenerator() )->validate( $content );

		$this->assertSame( [], $result['errors'] );
		$this->assertSame( [], $result['warnings'] );
	}
}
