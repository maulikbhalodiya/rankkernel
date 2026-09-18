<?php
/**
 * Crawl Signals settings page tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\CrawlSettingsPage;

/**
 * Crawl Settings Page Test.
 */
final class CrawlSettingsPageTest extends TestCase {
	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Redirected URLs.
	 *
	 * @var string[]
	 */
	private array $redirects = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
			define( 'RANKKERNEL_VERSION', '0.1.0' );
		}

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', __FILE__ );
		}

		$this->options             = [];
		$this->redirects           = [];
		$_POST                     = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';

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
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url ): void {
				$this->redirects[] = $url;
			}
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/wp-admin/' . $path );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ) );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $url ): string => trim( $url ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'esc_html__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		$_POST = [];
		$_GET  = [];
		unset( $_SERVER['REQUEST_METHOD'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test save requires capability.
	 */
	public function test_save_requires_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$_SERVER['REQUEST_METHOD']      = 'POST';
		$_POST['rankkernel_crawl_save'] = '1';

		$this->expectException( \RuntimeException::class );

		( new CrawlSettingsPage() )->maybeHandleSave();
	}

	/**
	 * Test save verifies the nonce.
	 */
	public function test_save_verifies_nonce(): void {
		Functions\when( 'check_admin_referer' )->justReturn( false );

		$_SERVER['REQUEST_METHOD']      = 'POST';
		$_POST['rankkernel_crawl_save'] = '1';

		$this->expectException( \RuntimeException::class );

		( new CrawlSettingsPage() )->maybeHandleSave();
	}

	/**
	 * Test robots settings are persisted.
	 */
	public function test_robots_settings_persisted(): void {
		$_SERVER['REQUEST_METHOD']      = 'POST';
		$_GET['tab']                    = 'robots';
		$_POST['rankkernel_crawl_save'] = '1';
		$_POST['mode']                  = 'custom';
		$_POST['custom']                = "User-agent: *\nDisallow: /private/\n";
		$_POST['presets']               = [ 'gptbot' ];
		$_POST['sitemap_url']           = 'https://example.com/sitemap.xml';

		( new CrawlSettingsPage() )->maybeHandleSave();

		$stored = $this->options['rankkernel_robots_settings'] ?? null;

		$this->assertIsArray( $stored );
		$this->assertSame( 'custom', $stored['mode'] );
		$this->assertSame( [ 'gptbot' ], $stored['presets'] );
		$this->assertSame( 'https://example.com/sitemap.xml', $stored['sitemap_url'] );
		$this->assertNotEmpty( $this->redirects );
	}

	/**
	 * Test llms settings are persisted.
	 */
	public function test_llms_settings_persisted(): void {
		$_SERVER['REQUEST_METHOD']      = 'POST';
		$_GET['tab']                    = 'llms';
		$_POST['rankkernel_crawl_save'] = '1';
		$_POST['llms_enabled']          = '1';
		$_POST['llms_summary']          = 'A summary.';
		$_POST['llms_post_types']       = [ 'post' ];
		$_POST['llms_limit']            = '50';
		$_POST['llms_excerpt_length']   = '120';
		$_POST['llms_exclude_ids']      = '5, 9';

		( new CrawlSettingsPage() )->maybeHandleSave();

		$stored = $this->options['rankkernel_llms_settings'] ?? null;

		$this->assertIsArray( $stored );
		$this->assertTrue( $stored['enabled'] );
		$this->assertSame( 50, $stored['limit'] );
		$this->assertSame( 120, $stored['excerpt_length'] );
		$this->assertSame( [ 5, 9 ], $stored['exclude_ids'] );
	}

	/**
	 * Test asset enqueue is gated to the screen.
	 */
	public function test_enqueue_is_gated(): void {
		$enqueued = 0;

		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->alias(
			function () use ( &$enqueued ): void {
				++$enqueued;
			}
		);

		$page = new CrawlSettingsPage();

		$page->enqueueAssets( 'other_page' );
		$this->assertSame( 0, $enqueued );

		$page->enqueueAssets( CrawlSettingsPage::HOOK_SUFFIX );
		$this->assertSame( 1, $enqueued );
	}
}
