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
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'esc_url' )->alias( static fn ( string $url ): string => $url );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test presets render above the wildcard group.
	 */
	public function test_presets_render_above_wildcard(): void {
		$builder = new RobotsBuilder();

		$out = $builder->build( "User-agent: *\nDisallow: /wp-admin/\n", true, [ 'gptbot', 'ccbot' ], '' );

		$gpt  = strpos( $out, 'User-agent: GPTBot' );
		$cc   = strpos( $out, 'User-agent: CCBot' );
		$wild = strpos( $out, 'User-agent: *' );

		$this->assertNotFalse( $gpt );
		$this->assertNotFalse( $cc );
		$this->assertNotFalse( $wild );
		$this->assertLessThan( $wild, $gpt );
		$this->assertLessThan( $wild, $cc );
	}

	/**
	 * Test each enabled preset gets a disallow group.
	 */
	public function test_each_preset_gets_a_disallow_group(): void {
		$builder = new RobotsBuilder();

		$out = $builder->build( "User-agent: *\n", true, [ 'gptbot' ], '' );

		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $out );
	}

	/**
	 * Test a private site is returned untouched.
	 */
	public function test_private_site_untouched(): void {
		$builder = new RobotsBuilder();

		$base = "User-agent: *\nDisallow: /\n";

		$this->assertSame( $base, $builder->build( $base, false, [ 'gptbot' ], 'https://example.com/sitemap.xml' ) );
	}

	/**
	 * Test a sitemap line is appended at most once.
	 */
	public function test_sitemap_line_appended_once(): void {
		$builder = new RobotsBuilder();

		$out = $builder->build( "User-agent: *\nSitemap: https://example.com/old.xml\n", true, [], 'https://example.com/sitemap.xml' );

		$this->assertSame( 1, substr_count( $out, 'Sitemap:' ) );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap.xml', $out );
		$this->assertStringNotContainsString( 'old.xml', $out );
	}

	/**
	 * Test empty base with presets still yields output.
	 */
	public function test_empty_base_with_presets_yields_output(): void {
		$builder = new RobotsBuilder();

		$out = $builder->build( '', true, [ 'gptbot' ], '' );

		$this->assertStringContainsString( 'User-agent: GPTBot', $out );
		$this->assertStringEndsWith( "\n", $out );
	}
}
