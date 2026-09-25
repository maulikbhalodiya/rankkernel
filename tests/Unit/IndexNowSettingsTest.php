<?php
/**
 * Instant Indexing settings tests, key storage, key location and log.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;

/**
 * IndexNow Settings Test.
 */
final class IndexNowSettingsTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Option store backing the get_option and update_option stubs.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		// The suite models a site with pretty permalinks, which is the
		// state keyLocation() serves the root txt file from. The plain
		// permalink case opts out through the public seam.
		$this->stored['permalink_structure'] = '/%postname%/';

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->stored[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				$this->stored[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'trailingslashit' )->alias( static fn( string $url ): string => rtrim( $url, '/' ) . '/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( string $key, string $value, string $url ): string {
				$separator = str_contains( $url, '?' ) ? '&' : '?';

				return $url . $separator . $key . '=' . rawurlencode( $value );
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): string|int|false|null {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double mirrors wp_parse_url with the native parser.
				return parse_url( $url, $component );
			}
		);

		$generated = 0;
		Functions\when( 'wp_generate_password' )->alias(
			static function ( int $length = 12 ) use ( &$generated ): string {
				++$generated;

				return str_pad( 'testkey' . $generated, $length, 'x' );
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
	 * Test defaults are off and keyless.
	 */
	public function test_defaults_are_off_and_keyless(): void {
		$this->assertSame(
			[
				'api_key'     => '',
				'auto_submit' => false,
			],
			IndexNowSettings::defaults()
		);
	}

	/**
	 * Test generated key satisfies the protocol alphabet.
	 */
	public function test_generated_key_satisfies_the_protocol_alphabet(): void {
		$settings = new IndexNowSettings();
		$key      = $settings->ensureKey();

		$this->assertTrue( IndexNowSettings::isValidKey( $key ) );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9-]{8,128}$/', $key );
	}

	/**
	 * Test is valid key rejects bad length and alphabet.
	 */
	public function test_is_valid_key_rejects_bad_length_and_alphabet(): void {
		$this->assertFalse( IndexNowSettings::isValidKey( 'short' ) );
		$this->assertFalse( IndexNowSettings::isValidKey( str_repeat( 'a', 129 ) ) );
		$this->assertFalse( IndexNowSettings::isValidKey( 'has_underscore' ) );
		$this->assertFalse( IndexNowSettings::isValidKey( 'has space here' ) );
		$this->assertTrue( IndexNowSettings::isValidKey( str_repeat( 'a', 128 ) ) );
	}

	/**
	 * Test reset key replaces the stored key.
	 */
	public function test_reset_key_replaces_the_stored_key(): void {
		$settings = new IndexNowSettings();
		$first    = $settings->ensureKey();
		$second   = $settings->resetKey();

		$this->assertNotSame( $first, $second );
		$this->assertSame( $second, $settings->getKey() );
	}

	/**
	 * Test auto submit defaults to false.
	 */
	public function test_auto_submit_defaults_to_false(): void {
		$this->assertFalse( ( new IndexNowSettings() )->getAutoSubmit() );
	}

	/**
	 * Test site host has no scheme and no www collapsing.
	 */
	public function test_site_host_has_no_scheme_and_no_www_collapsing(): void {
		$this->assertSame( 'example.com', ( new IndexNowSettings() )->siteHost() );
	}

	/**
	 * Test key location is root txt when permalinks are on.
	 */
	public function test_key_location_is_root_txt_when_permalinks_are_on(): void {
		$settings = new IndexNowSettings();
		$key      = $settings->ensureKey();

		$this->assertSame( 'https://example.com/' . $key . '.txt', $settings->keyLocation() );
	}

	/**
	 * Test key location falls back to a query argument on plain permalinks.
	 */
	public function test_key_location_falls_back_to_a_query_argument_on_plain_permalinks(): void {
		$settings = new IndexNowSettings();
		$key      = $settings->ensureKey();

		$settings->setPermalinkStructure( '' );

		$this->assertStringContainsString( IndexNowSettings::KEY_QUERY_ARG . '=' . $key, $settings->keyLocation() );
	}

	/**
	 * Test log is capped newest first and clears.
	 */
	public function test_log_is_capped_newest_first_and_clears(): void {
		$settings = new IndexNowSettings();

		for ( $i = 0; $i < 60; $i++ ) {
			$settings->logEntry( 'https://example.com/p' . $i, 200, 'auto', 'ok' );
		}

		$entries = $settings->logEntries();

		$this->assertCount( IndexNowSettings::LOG_LIMIT, $entries );
		$this->assertSame( 'https://example.com/p59', $entries[0]['url'] );

		$settings->clearLog();
		$this->assertSame( [], $settings->logEntries() );
	}
}
