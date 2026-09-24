<?php
/**
 * Instant Indexing key file server tests, request matching and response body.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\InstantIndexing\KeyFileServer;

/**
 * Key File Server Test.
 */
final class KeyFileServerTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Option store backing the get_option and update_option stubs.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Status codes passed to the status_header stub.
	 *
	 * @var int[]
	 */
	private array $statusHeaders = [];

	/**
	 * Number of nocache_headers calls.
	 *
	 * @var int
	 */
	private int $nocacheCalls = 0;

	/**
	 * Settings under test.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Generated key under test.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

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

		Functions\when( 'esc_html' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'status_header' )->alias(
			function ( int $status ): void {
				$this->statusHeaders[] = $status;
			}
		);
		Functions\when( 'nocache_headers' )->alias(
			function (): void {
				++$this->nocacheCalls;
			}
		);

		$this->settings = new IndexNowSettings();
		$this->key      = $this->settings->ensureKey();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test register hooks parse_request.
	 */
	public function test_register_hooks_parse_request(): void {
		$hooked = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, mixed $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$hooked ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_action signature.
				$hooked[] = $hook;
				return true;
			}
		);

		( new KeyFileServer( $this->settings ) )->register();

		$this->assertContains( 'parse_request', $hooked );
	}

	/**
	 * Test the root key file matches when permalinks are on.
	 */
	public function test_matches_the_root_key_file_when_permalinks_are_on(): void {
		$this->settings->setPermalinkStructure( '/%postname%/' );
		$server = new KeyFileServer( $this->settings );

		$this->assertTrue( $server->matches( $this->key . '.txt', [] ) );
		$this->assertTrue( $server->matches( '/' . $this->key . '.txt', [] ) );
		$this->assertFalse( $server->matches( 'some-other-page', [] ) );
		$this->assertFalse( $server->matches( $this->key . '.txt.bak', [] ) );
		$this->assertFalse( $server->matches( 'nested/' . $this->key . '.txt', [] ) );

		// With pretty permalinks the key is advertised as a path, so the
		// query argument form is not a key file request here.
		$this->assertFalse( $server->matches( '', [ IndexNowSettings::KEY_QUERY_ARG => $this->key ] ) );
	}

	/**
	 * Test the query argument matches when permalinks are off.
	 */
	public function test_matches_the_query_argument_when_permalinks_are_off(): void {
		$this->settings->setPermalinkStructure( '' );
		$server = new KeyFileServer( $this->settings );

		$this->assertTrue( $server->matches( '', [ IndexNowSettings::KEY_QUERY_ARG => $this->key ] ) );
		$this->assertFalse( $server->matches( '', [ IndexNowSettings::KEY_QUERY_ARG => 'wrong' ] ) );
		$this->assertFalse( $server->matches( '', [] ) );

		// An array value must not be cast to a string, it never equals a key.
		$this->assertFalse( $server->matches( '', [ IndexNowSettings::KEY_QUERY_ARG => [ $this->key ] ] ) );

		// On plain permalinks the key is advertised through the query
		// argument, so the path form is not a key file request here.
		$this->assertFalse( $server->matches( $this->key . '.txt', [] ) );
	}

	/**
	 * Test a normal request is never intercepted.
	 */
	public function test_never_intercepts_a_normal_request(): void {
		$this->settings->setPermalinkStructure( '/%postname%/' );
		$server = new KeyFileServer( $this->settings );

		$this->assertFalse( $server->matches( 'hello-world', [] ) );
		$this->assertFalse( $server->matches( 'wp-admin', [] ) );
		$this->assertFalse( $server->matches( 'feed', [] ) );
		$this->assertFalse( $server->matches( 'wp-content/uploads/2026/01/photo.jpg', [] ) );
		$this->assertFalse( $server->matches( 'category/news', [] ) );
	}

	/**
	 * Test an unconfigured key never matches anything.
	 */
	public function test_an_unconfigured_key_is_inert(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_option signature.
				return $fallback;
			}
		);

		$server = new KeyFileServer( new IndexNowSettings() );

		$this->assertFalse( $server->matches( '.txt', [] ) );
		$this->assertFalse( $server->matches( '', [ IndexNowSettings::KEY_QUERY_ARG => '' ] ) );
		$this->assertFalse( $server->matches( '', [] ) );
	}

	/**
	 * Test the response body for a valid key is byte for byte the key.
	 */
	public function test_response_body_for_a_valid_key_is_byte_for_byte_the_key(): void {
		$server = new KeyFileServer( $this->settings );

		$this->assertSame( $this->key, $server->responseBody( $this->key ) );
		$this->assertSame( bin2hex( $this->key ), bin2hex( $server->responseBody( $this->key ) ) );
	}

	/**
	 * Test a key containing markup is escaped in the response body.
	 */
	public function test_response_body_escapes_markup_so_the_endpoint_never_emits_html(): void {
		$server = new KeyFileServer( $this->settings );

		$body = $server->responseBody( '<script>alert(1)</script>' );

		$this->assertSame( '&lt;script&gt;alert(1)&lt;/script&gt;', $body );
		$this->assertStringNotContainsString( '<script>', $body );
	}

	/**
	 * Test respond prints the key and marks the response plain text and uncacheable.
	 */
	public function test_respond_emits_the_key_with_a_plain_text_status(): void {
		$server = new KeyFileServer( $this->settings );

		ob_start();
		$server->respond( $this->key );
		$body = ob_get_clean();

		$this->assertSame( $this->key, $body );
		$this->assertSame( [ 200 ], $this->statusHeaders );
		$this->assertSame( 1, $this->nocacheCalls );
	}

	/**
	 * Test maybe serve answers only the key file request.
	 */
	public function test_maybe_serve_answers_only_the_key_file_request(): void {
		$this->settings->setPermalinkStructure( '/%postname%/' );
		$server = new KeyFileServer( $this->settings );

		ob_start();
		$server->maybeServe( (object) [ 'request' => 'hello-world' ] );
		$untouched = ob_get_clean();

		$this->assertSame( '', $untouched );
		$this->assertSame( [], $this->statusHeaders );

		ob_start();
		$server->maybeServe( (object) [ 'request' => $this->key . '.txt' ] );
		$body = ob_get_clean();

		$this->assertSame( $this->key, $body );
		$this->assertSame( [ 200 ], $this->statusHeaders );
	}
}
