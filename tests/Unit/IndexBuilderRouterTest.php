<?php
/**
 * IndexBuilder router URL wiring tests.
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
use RankKernel\Modules\Sitemaps\Provider\AuthorsProvider;
use RankKernel\Modules\Sitemaps\Provider\PostsProvider;
use RankKernel\Modules\Sitemaps\Provider\TaxonomiesProvider;
use RankKernel\Modules\Sitemaps\Router;
use RankKernel\Modules\Sitemaps\SitemapCache;
use RankKernel\Modules\Sitemaps\XslStylesheet;

/**
 * Index Builder Router Test.
 */
final class IndexBuilderRouterTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
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
		Functions\when( 'mysql2date' )->alias( static fn ( string $format, string $date, bool $translate = true ): string => gmdate( $format, strtotime( $date ) ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress mysql2date signature.
		Functions\when( 'current_time' )->alias( static fn ( string $type, bool $gmt = false ): string => '2026-01-01 00:00:00' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress current_time signature.
		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => $v );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Make Builder.
	 *
	 * @return IndexBuilder The result.
	 */
	private function makeBuilder(): IndexBuilder {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->with( 'post' )->andReturn( 5 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getCount' )->andReturn( 0 )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getCount' )->andReturn( 0 )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		return new IndexBuilder( $posts, $tax, $auth, '9.9.9-test' );
	}

	/**
	 * Make Router.
	 *
	 * @param string $permalinkStructure Permalink Structure.
	 * @return Router The result.
	 */
	private function makeRouter( string $permalinkStructure ): Router {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( $permalinkStructure ): mixed {
				if ( 'permalink_structure' === $key ) {
					return $permalinkStructure;
				}

				return $fallback;
			}
		);

		return new Router(
			Mockery::mock( IndexBuilder::class ),
			Mockery::mock( SitemapCache::class ),
			Mockery::mock( XslStylesheet::class )
		);
	}

	/**
	 * Test plain router drives index locs and pi href.
	 */
	public function test_plain_router_drives_index_locs_and_pi_href(): void {
		$builder = $this->makeBuilder();
		$builder->setRouter( $this->makeRouter( '' ) );

		$xml = $builder->buildIndexXml();

		$this->assertStringContainsString( '<loc>https://example.com/?rankkernel_sitemap=post</loc>', $xml );
		$this->assertStringContainsString( 'rankkernel_sitemap_xsl=1', $xml );
		$this->assertStringContainsString( 'ver=9.9.9-test', $xml );
	}

	/**
	 * Test pretty router keeps classic forms.
	 */
	public function test_pretty_router_keeps_classic_forms(): void {
		$builder = $this->makeBuilder();
		$builder->setRouter( $this->makeRouter( '/%postname%/' ) );

		$xml = $builder->buildIndexXml();

		$this->assertStringContainsString( '<loc>https://example.com/post-sitemap.xml</loc>', $xml );
		$this->assertStringContainsString( 'sitemap.xsl?ver=9.9.9-test', $xml );
	}
}
