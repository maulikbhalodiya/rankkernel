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
	 * Whether the next stubbed option write fails.
	 *
	 * @var bool
	 */
	private bool $failWrites = false;

	/**
	 * Number of stubbed option writes.
	 *
	 * @var int
	 */
	private int $writeCount = 0;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->options    = [];
		$this->failWrites = false;
		$this->writeCount = 0;

		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				++$this->writeCount;

				if ( $this->failWrites ) {
					return false;
				}

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

	/**
	 * Test a summary containing markup is sanitised.
	 */
	public function test_summary_markup_is_sanitised(): void {
		$settings = new LlmsSettings();

		$this->assertTrue(
			$settings->set(
				[
					'summary' => "Hello <b>world</b>\n<script>alert(1)</script>",
				]
			)
		);

		$stored = $this->options[ LlmsSettings::OPTION ];

		$this->assertStringContainsString( 'Hello', $stored['summary'] );
		$this->assertStringNotContainsString( '<b>', $stored['summary'] );
		$this->assertStringNotContainsString( '<script>', $stored['summary'] );
		$this->assertStringNotContainsString( '<', $stored['summary'] );
	}

	/**
	 * Test an unchanged value saves as a success without a write.
	 */
	public function test_set_unchanged_value_succeeds_without_write(): void {
		$this->options[ LlmsSettings::OPTION ] = [ 'enabled' => true ];

		$settings = new LlmsSettings();

		$this->assertTrue( $settings->set( [ 'enabled' => true ] ) );
		$this->assertSame( 0, $this->writeCount, 'an unchanged value must not be written' );
	}

	/**
	 * Test a failed write reports false and keeps the previous cache.
	 *
	 * The update_option() function returns false both for an unchanged
	 * value and for a failed write. This covers the changed-value half: the
	 * write is attempted, fails, and must surface instead of reporting
	 * success or leaving the in-memory cache ahead of storage.
	 */
	public function test_set_reports_failed_write_and_keeps_previous_cache(): void {
		$this->options[ LlmsSettings::OPTION ] = [ 'enabled' => false ];
		$this->failWrites                      = true;

		$settings = new LlmsSettings();

		$this->assertFalse( $settings->set( [ 'enabled' => true ] ) );
		$this->assertSame( 1, $this->writeCount );
		$this->assertFalse( $settings->get( 'enabled' ), 'a failed write must not update the cache' );
		$this->assertSame( [ 'enabled' => false ], $this->options[ LlmsSettings::OPTION ] );
	}
}
