<?php
/**
 * Robots.txt settings tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\CrawlerPolicy;
use RankKernel\Modules\Robots\RobotsSettings;

/**
 * Robots Settings Test.
 */
final class RobotsSettingsTest extends TestCase {
	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->options = [];

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				$this->options[ $key ] = $value;

				return true;
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test defaults seed the crawler policy map and an empty override.
	 */
	public function test_defaults_seed_crawler_map(): void {
		$settings = new RobotsSettings();

		$this->assertSame( '', $settings->get( 'override' ) );
		$this->assertSame( CrawlerPolicy::BLOCK, $settings->get( 'crawlers' )['gptbot'] );
		$this->assertSame( CrawlerPolicy::ALLOW, $settings->get( 'crawlers' )['oai-searchbot'] );
	}

	/**
	 * Test set whitelists keys and normalises values.
	 */
	public function test_set_whitelists_and_normalises(): void {
		$settings = new RobotsSettings();

		$this->assertTrue(
			$settings->set(
				[
					'override' => "Disallow: /tmp/\r\n",
					'crawlers' => [ 'gptbot' => 'allow' ],
					'evil'     => 'x',
				]
			)
		);

		$stored = $this->options[ RobotsSettings::OPTION ];
		$this->assertStringNotContainsString( "\r", $stored['override'] );
		$this->assertSame( 'allow', $stored['crawlers']['gptbot'] );
		$this->assertArrayNotHasKey( 'evil', $stored );
	}

	/**
	 * Test an unknown key alone saves nothing.
	 */
	public function test_unknown_key_saves_nothing(): void {
		$settings = new RobotsSettings();

		$this->assertFalse( $settings->set( [ 'evil' => 'x' ] ) );
	}
}
