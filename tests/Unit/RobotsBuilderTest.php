<?php
/**
 * Robots.txt output builder tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\RobotsBuilder;

/**
 * Robots Builder Test.
 */
final class RobotsBuilderTest extends TestCase {
	/**
	 * Core output fixture.
	 *
	 * @var string
	 */
	private string $core = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nSitemap: https://example.com/sitemap_index.xml\n";

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test a blocked crawler renders Disallow above the wildcard group.
	 */
	public function test_block_renders_above_wildcard(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'gptbot' => 'block' ], '', 'default' );

		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $out );
		$this->assertLessThan( strpos( $out, 'User-agent: *' ), strpos( $out, 'User-agent: GPTBot' ) );
	}

	/**
	 * Test an allowed crawler renders an explicit Allow.
	 */
	public function test_allow_renders_allow(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'oai-searchbot' => 'allow' ], '', 'default' );

		$this->assertStringContainsString( "User-agent: OAI-SearchBot\nAllow: /", $out );
	}

	/**
	 * Test a custom policy emits no group for that crawler.
	 */
	public function test_custom_policy_emits_nothing(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'gptbot' => 'custom' ], '', 'default' );

		$this->assertStringNotContainsString( 'User-agent: GPTBot', $out );
	}

	/**
	 * Test the Sitemap line survives custom mode unchanged.
	 */
	public function test_custom_mode_preserves_sitemap_line(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [], "User-agent: *\nDisallow: /private/\n", 'custom' );

		$this->assertStringContainsString( 'Disallow: /private/', $out );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $out );
		$this->assertSame( 1, substr_count( $out, 'Sitemap:' ) );
	}

	/**
	 * Test default mode appends the custom block to core output.
	 */
	public function test_default_mode_appends_custom_block(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [], "Disallow: /tmp/\n", 'default' );

		$this->assertStringContainsString( 'Disallow: /tmp/', $out );
		$this->assertStringContainsString( 'Disallow: /wp-admin/', $out );
		$this->assertSame( 1, substr_count( $out, 'Sitemap:' ) );
	}

	/**
	 * Test a private site is returned untouched.
	 */
	public function test_private_site_untouched(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, false, [ 'gptbot' => 'block' ], '', 'custom' );

		$this->assertSame( $this->core, $out );
	}
}
