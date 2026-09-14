<?php
/**
 * Robots.txt sitemap directive tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\SitemapsModule;

/**
 * Sitemap Robots Directive Test.
 */
final class SitemapRobotsDirectiveTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

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

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
			define( 'RANKKERNEL_VERSION', '0.1.0' );
		}

		$this->options = [
			'blog_public'         => '1',
			'permalink_structure' => '/%postname%/',
			'rankkernel_modules'  => [ 'sitemaps' ],
		];

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
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'add_query_arg' )->alias(
			static function ( mixed $key = '', mixed $value = '', string $url = '' ): string {
				if ( is_array( $key ) ) {
					$pairs = $key;
					$url   = is_string( $value ) ? $value : '';
				} else {
					$pairs = [ (string) $key => $value ];
				}

				$sep = str_contains( $url, '?' ) ? '&' : '?';

				return $url . $sep . http_build_query( $pairs );
			}
		);
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => $v );
		Functions\when( 'current_time' )->alias( static fn ( string $t, bool $g = false ): string => '2026-01-01 00:00:00' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress current_time signature.
		Functions\when( 'mysql2date' )->alias( static fn ( string $f, string $d, bool $t = true ): string => gmdate( $f, strtotime( $d ) ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress mysql2date signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Booted Module.
	 *
	 * @return SitemapsModule The result.
	 */
	private function bootedModule(): SitemapsModule {
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_rewrite_rule' )->justReturn( true );

		$module = new SitemapsModule();
		$module->boot();

		return $module;
	}

	/**
	 * Test boot registers robots txt filter.
	 */
	public function test_boot_registers_robots_txt_filter(): void {
		$filters = [];

		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $cb, int $prio = 10, int $args = 1 ) use ( &$filters ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_filter signature.
				$filters[] = [ $hook, $prio ];

				return true;
			}
		);
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_rewrite_rule' )->justReturn( true );

		$module = new SitemapsModule();
		$module->boot();

		$this->assertContains( [ 'robots_txt', 1 ], $filters );
	}

	/**
	 * Test replaces core sitemap line.
	 */
	public function test_replaces_core_sitemap_line(): void {
		$module = $this->bootedModule();

		$out = $module->sitemapDirective(
			"User-agent: *\nDisallow: /wp-admin/\nSitemap: https://example.com/wp-sitemap.xml\n"
		);

		$this->assertStringNotContainsString( 'wp-sitemap.xml', $out );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $out );
		$this->assertSame( 1, substr_count( $out, 'Sitemap:' ) );
	}

	/**
	 * Test private blog untouched.
	 */
	public function test_private_blog_untouched(): void {
		$module = $this->bootedModule();

		$this->options['blog_public'] = '0';

		$input = "User-agent: *\nDisallow: /\n";

		$this->assertSame( $input, $module->sitemapDirective( $input ) );
	}

	/**
	 * Test appends when missing and stays idempotent.
	 */
	public function test_appends_when_missing_and_stays_idempotent(): void {
		$module = $this->bootedModule();

		$once = $module->sitemapDirective( "User-agent: *\nDisallow: /wp-admin/\n" );

		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $once );

		$twice = $module->sitemapDirective( $once );

		$this->assertSame( 1, substr_count( $twice, 'Sitemap:' ) );
	}

	/**
	 * Test plain mode uses plain index url.
	 */
	public function test_plain_mode_uses_plain_index_url(): void {
		$this->options['permalink_structure'] = '';

		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_rewrite_rule' )->justReturn( true );

		$module = new SitemapsModule();
		$module->boot();

		$router = $module->getRouter();

		$this->assertNotNull( $router );
		$this->assertSame( 'https://example.com/?rankkernel_sitemap=index', $router->indexUrl() );

		$out = $module->sitemapDirective( "User-agent: *\n" );

		$this->assertStringContainsString( 'Sitemap: https://example.com/?rankkernel_sitemap=index', $out );
	}
}
