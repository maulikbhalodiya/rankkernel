<?php
/**
 * Llms.txt settings tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsSettings;

/**
 * Llms Settings Test.
 */
final class LlmsSettingsTest extends TestCase {
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
	 * Test defaults are off and empty.
	 */
	public function test_defaults(): void {
		$settings = new LlmsSettings();

		$this->assertFalse( $settings->get( 'enabled' ) );
		$this->assertSame( '', $settings->get( 'summary' ) );
		$this->assertSame( '', $settings->get( 'content' ) );
		$this->assertFalse( $settings->get( 'physical' ) );
	}

	/**
	 * Test set whitelists keys and normalises values.
	 */
	public function test_set_whitelists_and_normalises(): void {
		$settings = new LlmsSettings();

		$this->assertTrue(
			$settings->set(
				[
					'enabled' => 1,
					'summary' => '  Hello  ',
					'content' => "## A\r\n- [X](https://example.com/x)\r\n",
					'evil'    => 'x',
				]
			)
		);

		$stored = $this->options[ LlmsSettings::OPTION ];
		$this->assertTrue( $stored['enabled'] );
		$this->assertSame( 'Hello', $stored['summary'] );
		$this->assertStringNotContainsString( "\r", $stored['content'] );
		$this->assertArrayNotHasKey( 'evil', $stored );
	}
}
