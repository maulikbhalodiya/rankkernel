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
use RankKernel\Modules\Robots\CrawlerPolicy;
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
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'gptbot' => 'block' ], '' );

		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $out );
		$this->assertLessThan( strpos( $out, 'User-agent: *' ), strpos( $out, 'User-agent: GPTBot' ) );
	}

	/**
	 * Test an allowed crawler renders an explicit Allow.
	 */
	public function test_allow_renders_allow(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'oai-searchbot' => 'allow' ], '' );

		$this->assertStringContainsString( "User-agent: OAI-SearchBot\nAllow: /", $out );
	}

	/**
	 * Test a custom policy emits no group for that crawler.
	 */
	public function test_custom_policy_emits_nothing(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'gptbot' => 'custom' ], '' );

		$this->assertStringNotContainsString( 'User-agent: GPTBot', $out );
	}

	/**
	 * Test an override replaces the whole document.
	 */
	public function test_override_replaces_the_document(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'gptbot' => 'block' ], "User-agent: *\nDisallow: /private/\n" );

		$this->assertSame( "User-agent: *\nDisallow: /private/\n", $out );
	}

	/**
	 * Test the generated document keeps the Sitemap line.
	 */
	public function test_generated_keeps_sitemap_line(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'gptbot' => 'block' ], '' );

		$this->assertStringContainsString( 'Disallow: /wp-admin/', $out );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $out );
		$this->assertSame( 1, substr_count( $out, 'Sitemap:' ) );
	}

	/**
	 * Test a private site is returned untouched.
	 */
	public function test_private_site_untouched(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, false, [ 'gptbot' => 'block' ], "User-agent: *\nDisallow: /\n" );

		$this->assertSame( $this->core, $out );
	}

	/**
	 * Test a filter provided token cannot inject a robots.txt line.
	 *
	 * The token is a well formed entry for an unknown slug, so CrawlerPolicy
	 * keeps it and the builder sink is the only line of defence. The CR and LF
	 * are stripped and the slash is removed by the safe character class, so the
	 * injected directive can never start a new line.
	 */
	public function test_filtered_token_cannot_inject_a_line(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/robots/crawlers' === $hook ) {
					$value['evil'] = [
						'label'   => 'Evil',
						'token'   => "EvilBot\r\nDisallow: /pwned",
						'purpose' => 'training',
						'default' => 'block',
						'note'    => 'Injection attempt.',
					];
				}

				return $value;
			}
		);

		$out = ( new RobotsBuilder() )->build( $this->core, true, [ 'evil' => 'block' ], '' );

		$this->assertStringNotContainsString( "\r", $out );
		$this->assertStringNotContainsString( 'Disallow: /pwned', $out );
		$this->assertStringNotContainsString( '/pwned', $out );
		$this->assertStringContainsString( 'User-agent: EvilBotDisallow pwned', $out );
	}

	/**
	 * Test the unfiltered output is byte identical to the catalogued order.
	 */
	public function test_unfiltered_output_is_byte_identical(): void {
		$out = ( new RobotsBuilder() )->build( $this->core, true, CrawlerPolicy::defaults(), '' );

		$block = implode(
			"\n\n",
			[
				"User-agent: GPTBot\nDisallow: /",
				"User-agent: ClaudeBot\nDisallow: /",
				"User-agent: Google-Extended\nDisallow: /",
				"User-agent: Applebot-Extended\nDisallow: /",
				"User-agent: meta-externalagent\nDisallow: /",
				"User-agent: Amazonbot\nDisallow: /",
				"User-agent: Bytespider\nDisallow: /",
				"User-agent: CCBot\nDisallow: /",
				"User-agent: OAI-SearchBot\nAllow: /",
				"User-agent: Claude-SearchBot\nAllow: /",
				"User-agent: PerplexityBot\nAllow: /",
				"User-agent: ChatGPT-User\nAllow: /",
				"User-agent: Claude-User\nAllow: /",
				"User-agent: Perplexity-User\nAllow: /",
			]
		);

		$expected = $block . "\n\n" . rtrim( $this->core ) . "\n";

		$this->assertSame( $expected, $out );
	}
}
