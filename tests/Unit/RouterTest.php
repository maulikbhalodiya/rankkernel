<?php
/**
 * Router tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\IndexBuilder;
use RankKernel\Modules\Sitemaps\Router;
use RankKernel\Modules\Sitemaps\SitemapCache;
use RankKernel\Modules\Sitemaps\XslStylesheet;
use WP_Query;

/**
 * Router Test.
 */
final class RouterTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'remove_all_actions' )->justReturn( true );
		Functions\when( '__return_false' )->alias( static fn (): bool => false );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test rewrite rules registered on init.
	 */
	public function test_rewrite_rules_registered_on_init(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$cache   = Mockery::mock( SitemapCache::class );
		$xsl     = Mockery::mock( XslStylesheet::class );

		$router = new Router( $builder, $cache, $xsl );

		$rules = [];
		Functions\when( 'add_rewrite_rule' )->alias(
			static function ( string $regex, string $query, string $position ) use ( &$rules ): bool {
				$rules[] = [ $regex, $query, $position ];
				return true;
			}
		);

		$router->addRewriteRules();

		$this->assertCount( 3, $rules );
		$this->assertSame( '^sitemap_index\.xml$', $rules[0][0] );
		$this->assertSame( 'index.php?rankkernel_sitemap=index', $rules[0][1] );
		$this->assertSame( 'top', $rules[0][2] );

		$this->assertSame( '^([^.]+)-sitemap([0-9]+)?\.xml$', $rules[1][0] );
		$this->assertStringContainsString( 'rankkernel_sitemap', $rules[1][1] );
		$this->assertStringContainsString( 'rankkernel_sitemap_n', $rules[1][1] );

		$this->assertSame( '^([a-z]+)?-?sitemap\.xsl$', $rules[2][0] );
		$this->assertSame( 'index.php?rankkernel_sitemap_xsl=1', $rules[2][1] );
	}

	/**
	 * Test query vars added.
	 */
	public function test_query_vars_added(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$cache   = Mockery::mock( SitemapCache::class );
		$xsl     = Mockery::mock( XslStylesheet::class );

		$router = new Router( $builder, $cache, $xsl );

		$vars = $router->addQueryVars( [ 'p', 'page' ] );

		$this->assertContains( 'rankkernel_sitemap', $vars );
		$this->assertContains( 'rankkernel_sitemap_n', $vars );
		$this->assertContains( 'rankkernel_sitemap_xsl', $vars );
	}

	/**
	 * Test intercept echoes xml and exits.
	 */
	public function test_intercept_echoes_xml_and_exits(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$builder->shouldReceive( 'buildIndexXml' )->once()->andReturn( '<sitemapindex>index</sitemapindex>' );

		$cache = Mockery::mock( SitemapCache::class );
		$cache->shouldReceive( 'get' )->once()->andReturnUsing(
			static function ( string $set, int $page, callable $cb ): string {
				// Ensure builder is called via cache wrapper.
				return (string) $cb();
			}
		);

		$xsl = Mockery::mock( XslStylesheet::class );
		$xsl->shouldReceive( 'output' )->never();

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias(
			static function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return 'index';
				}

				if ( 'rankkernel_sitemap_n' === $key ) {
					return '';
				}

				if ( 'rankkernel_sitemap_xsl' === $key ) {
					return '';
				}

				return $fallback;
			}
		);

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );

		ob_start();
		$router->intercept( $query );
		$out = ob_get_clean();

		$this->assertStringContainsString( '<sitemapindex>index</sitemapindex>', $out );
	}

	/**
	 * Test intercept ignores non main queries.
	 */
	public function test_intercept_ignores_non_main_queries(): void {
		// Inner queries (query loop blocks rendered during do_blocks,
		// widgets, related posts) must never trigger a render, or nested
		// builds recurse until memory runs out. Regression test.
		$builder = Mockery::mock( IndexBuilder::class );
		$builder->shouldReceive( 'buildIndexXml' )->never();
		$builder->shouldReceive( 'buildEntriesXml' )->never();

		$cache = Mockery::mock( SitemapCache::class );
		$cache->shouldReceive( 'get' )->never();
		$cache->shouldReceive( 'getMap' )->never();

		$xsl = Mockery::mock( XslStylesheet::class );
		$xsl->shouldReceive( 'output' )->never();

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias(
			static function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return 'blog';
				}

				return $fallback;
			}
		);

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( false );

		ob_start();
		$router->intercept( $query );
		$out = ob_get_clean();

		$this->assertSame( '', $out );
	}

	/**
	 * Test intercept renders known set.
	 */
	public function test_intercept_renders_known_set(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$builder->shouldReceive( 'buildEntriesXml' )->once()->with( 'blog', 1 )->andReturn( '<urlset>blog urls</urlset>' );

		$cache = Mockery::mock( SitemapCache::class );
		$cache->shouldReceive( 'getMap' )->once()->with( 'sets', Mockery::type( 'callable' ) )->andReturn(
			[
				'blog' => 2,
				'page' => 1,
			]
		);
		$cache->shouldReceive( 'get' )->once()->andReturnUsing(
			static function ( string $set, int $page, callable $cb ): string {
				return (string) $cb();
			}
		);

		$xsl = Mockery::mock( XslStylesheet::class );
		$xsl->shouldReceive( 'output' )->never();

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias(
			static function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return 'blog';
				}

				return $fallback;
			}
		);

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );

		ob_start();
		$router->intercept( $query );
		$out = ob_get_clean();

		$this->assertStringContainsString( '<urlset>blog urls</urlset>', $out );
	}

	/**
	 * Test intercept 404s unknown set.
	 */
	public function test_intercept_404s_unknown_set(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$builder->shouldReceive( 'buildEntriesXml' )->never();

		$cache = Mockery::mock( SitemapCache::class );
		$cache->shouldReceive( 'getMap' )->once()->with( 'sets', Mockery::type( 'callable' ) )->andReturn( [ 'blog' => 2 ] );
		$cache->shouldReceive( 'get' )->never();

		$xsl = Mockery::mock( XslStylesheet::class );
		$xsl->shouldReceive( 'output' )->never();

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias(
			static function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return 'post';
				}

				return $fallback;
			}
		);

		Functions\when( 'nocache_headers' )->justReturn( null );
		Functions\expect( 'status_header' )->once()->with( 404 );

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );

		ob_start();
		$router->intercept( $query );
		ob_end_clean();

		$this->assertTrue( true );
	}

	/**
	 * Test intercept 404s page out of range.
	 */
	public function test_intercept_404s_page_out_of_range(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$builder->shouldReceive( 'buildEntriesXml' )->never();

		$cache = Mockery::mock( SitemapCache::class );
		$cache->shouldReceive( 'getMap' )->once()->with( 'sets', Mockery::type( 'callable' ) )->andReturn( [ 'blog' => 2 ] );
		$cache->shouldReceive( 'get' )->never();

		$xsl = Mockery::mock( XslStylesheet::class );
		$xsl->shouldReceive( 'output' )->never();

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias(
			static function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return 'blog';
				}

				if ( 'rankkernel_sitemap_n' === $key ) {
					return '3';
				}

				return $fallback;
			}
		);

		Functions\when( 'nocache_headers' )->justReturn( null );
		Functions\expect( 'status_header' )->once()->with( 404 );

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );

		ob_start();
		$router->intercept( $query );
		ob_end_clean();

		$this->assertTrue( true );
	}

	/**
	 * Test intercept renders xsl.
	 */
	public function test_intercept_renders_xsl(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$cache   = Mockery::mock( SitemapCache::class );
		$xsl     = Mockery::mock( XslStylesheet::class );
		$xsl->shouldReceive( 'output' )->once()->andReturnNull();

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias(
			static function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap_xsl' === $key ) {
					return '1';
				}

				return $fallback;
			}
		);

		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );

		ob_start();
		$router->intercept( $query );
		ob_end_clean();

		$this->assertTrue( true );
	}

	/**
	 * Test canonical filter false on sitemap vars.
	 */
	public function test_canonical_filter_false_on_sitemap_vars(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$cache   = Mockery::mock( SitemapCache::class );
		$xsl     = Mockery::mock( XslStylesheet::class );

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias(
			static function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return 'post';
				}

				if ( 'rankkernel_sitemap_n' === $key ) {
					return '2';
				}

				if ( 'rankkernel_sitemap_xsl' === $key ) {
					return '';
				}

				return $fallback;
			}
		);

		$result = $router->disableCanonical( 'https://example.com/post/' );

		$this->assertFalse( $result );
	}

	/**
	 * Test canonical filter untouched otherwise.
	 */
	public function test_canonical_filter_untouched_otherwise(): void {
		$builder = Mockery::mock( IndexBuilder::class );
		$cache   = Mockery::mock( SitemapCache::class );
		$xsl     = Mockery::mock( XslStylesheet::class );

		$router = new Router( $builder, $cache, $xsl );

		Functions\when( 'get_query_var' )->alias( static fn ( string $k, mixed $d = '' ): mixed => $d );

		$result = $router->disableCanonical( 'https://example.com/hello/' );

		$this->assertSame( 'https://example.com/hello/', $result );
	}
}
