<?php
/**
 * Instant Indexing client tests, payload building and submission.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowClient;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use WP_Error;

/**
 * IndexNow Client Test.
 */
final class IndexNowClientTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Settings under test, keyed during setUp.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Recorded transport calls.
	 *
	 * @var array<int, array{url:string, args:array<string, mixed>}>
	 */
	private array $calls = [];

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
		// state keyLocation() serves the root txt file from.
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
		Functions\when( 'wp_json_encode' )->alias( static fn( mixed $data ): string => (string) json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		Functions\when( 'esc_url_raw' )->alias( static fn( string $url ): string => $url );

		$generated = 0;
		Functions\when( 'wp_generate_password' )->alias(
			static function ( int $length = 12 ) use ( &$generated ): string {
				++$generated;

				return str_pad( 'testkey' . $generated, $length, 'x' );
			}
		);

		$this->settings = new IndexNowSettings();
		$this->settings->ensureKey();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a recording transport double.
	 *
	 * @param int  $code    Status code to report.
	 * @param bool $isError Whether to report a WP_Error.
	 * @return callable(string, array<string, mixed>): (array<string, mixed>|WP_Error)
	 */
	private function transport( int $code = 200, bool $isError = false ): callable {
		return function ( string $url, array $args ) use ( $code, $isError ) {
			$this->calls[] = [
				'url'  => $url,
				'args' => $args,
			];

			if ( $isError ) {
				return new WP_Error( 'http_request_failed', 'boom' );
			}

			return [
				'response' => [ 'code' => $code ],
				'body'     => '',
			];
		};
	}

	/**
	 * Build a client wired to a recording transport double.
	 *
	 * @param int  $code    Status code to report.
	 * @param bool $isError Whether to report a WP_Error.
	 * @return IndexNowClient Client under test.
	 */
	private function client( int $code = 200, bool $isError = false ): IndexNowClient {
		return new IndexNowClient( $this->settings, $this->transport( $code, $isError ) );
	}

	/**
	 * Test the payload uses the exact spec field casing.
	 */
	public function test_payload_uses_exact_spec_field_casing(): void {
		$payload = $this->client()->buildPayload( [ 'https://example.com/a' ] );

		$this->assertSame( [ 'host', 'key', 'keyLocation', 'urlList' ], array_keys( $payload ) );
		$this->assertSame( 'example.com', $payload['host'] );
		$this->assertStringEndsWith( '.txt', $payload['keyLocation'] );
		$this->assertSame( [ 'https://example.com/a' ], $payload['urlList'] );
	}

	/**
	 * Test the request posts JSON to the global endpoint.
	 */
	public function test_request_posts_json_to_the_global_endpoint(): void {
		$this->client()->submit( [ 'https://example.com/a' ] );

		$this->assertCount( 1, $this->calls );
		$this->assertSame( IndexNowClient::ENDPOINT, $this->calls[0]['url'] );
		$this->assertSame( 'application/json; charset=utf-8', $this->calls[0]['args']['headers']['Content-Type'] );
		$this->assertArrayNotHasKey( 'X-Source-Info', $this->calls[0]['args']['headers'] );
		$this->assertArrayNotHasKey( 'User-Agent', $this->calls[0]['args']['headers'] );
		$this->assertTrue( $this->calls[0]['args']['blocking'] );
	}

	/**
	 * Test a 202 is accepted, not failed.
	 */
	public function test_202_is_treated_as_accepted_not_failed(): void {
		$result = $this->client( 202 )->submit( [ 'https://example.com/a' ] );

		$this->assertSame( 1, $result['accepted'] );
		$this->assertSame( 0, $result['permanent'] );
		$this->assertTrue( $result['results'][0]['accepted'] );
	}

	/**
	 * Test 403 is permanent and 429 is retryable.
	 */
	public function test_403_is_permanent_and_429_is_retryable(): void {
		$this->assertSame( 1, $this->client( 403 )->submit( [ 'https://example.com/a' ] )['permanent'] );
		$this->assertSame( 1, $this->client( 429 )->submit( [ 'https://example.com/a' ] )['transient'] );
	}

	/**
	 * Test a transport error is retryable.
	 */
	public function test_wp_error_is_retryable(): void {
		$result = $this->client( 0, true )->submit( [ 'https://example.com/a' ] );

		$this->assertSame( 1, $result['transient'] );
	}

	/**
	 * Test URLs on another host are dropped without any request.
	 */
	public function test_urls_on_another_host_are_dropped_without_any_request(): void {
		$result = $this->client()->submit( [ 'https://evil.test/a', 'https://example.com/b' ] );

		$this->assertCount( 1, $this->calls );
		$this->assertSame( [ 'https://example.com/b' ], json_decode( (string) $this->calls[0]['args']['body'], true )['urlList'] );
		$this->assertSame( 1, $result['accepted'] );
	}

	/**
	 * Test the batch is chunked at ten thousand URLs.
	 */
	public function test_batch_is_chunked_at_ten_thousand(): void {
		$urls = [];
		for ( $i = 0; $i < 10001; $i++ ) {
			$urls[] = 'https://example.com/p' . $i;
		}

		$this->client()->submit( $urls );

		$this->assertCount( 2, $this->calls );
		$this->assertCount( 10000, json_decode( (string) $this->calls[0]['args']['body'], true )['urlList'] );
		$this->assertCount( 1, json_decode( (string) $this->calls[1]['args']['body'], true )['urlList'] );
	}

	/**
	 * Test an empty key means no request at all.
	 */
	public function test_no_key_means_no_request_at_all(): void {
		// Clear the keyed store so the fresh settings instance reads defaults.
		$this->stored = [];

		$empty = new IndexNowSettings();

		$result = ( new IndexNowClient( $empty, $this->transport() ) )->submit( [ 'https://example.com/a' ] );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( 0, $result['accepted'] );
	}
}
