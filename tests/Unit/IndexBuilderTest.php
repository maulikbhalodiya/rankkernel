<?php
/**
 * IndexBuilder tests.
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

/**
 * Index Builder Test.
 */
final class IndexBuilderTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'add_query_arg' )->alias( static fn ( mixed $k = '', mixed $v = '', string $u = '' ): string => $u . ( str_contains( $u, '?' ) ? '&' : '?' ) . (string) $k . '=' . (string) $v );
		Functions\when( 'mysql2date' )->alias( static fn ( string $format, string $date, bool $translate = true ): string => gmdate( $format, strtotime( $date ) ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress mysql2date signature.
		Functions\when( 'current_time' )->alias( static fn ( string $type, bool $gmt = false ): string => '2026-01-01 00:00:00' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress current_time signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Make Builder With Counts.
	 *
	 * @param array $map     Map.
	 * @param int   $perPage Per Page.
	 * @return IndexBuilder The result.
	 */
	private function makeBuilderWithCounts( array $map, int $perPage = 1000 ): IndexBuilder {
		$posts = Mockery::mock( PostsProvider::class );
		$tax   = Mockery::mock( TaxonomiesProvider::class );
		$auth  = Mockery::mock( AuthorsProvider::class );

		// Default sets.
		$postsSets = [];
		$taxSets   = [];
		$authSets  = [];
		foreach ( $map as $set => $count ) {
			if ( str_starts_with( $set, 'tax_' ) ) {
				$taxSets[] = substr( $set, 4 );
			} elseif ( 'authors' === $set ) {
				$authSets[] = $set;
			} else {
				$postsSets[] = $set;
			}
		}

		if ( [] === $postsSets && isset( $map['post'] ) ) {
			$postsSets = [ 'post' ];
		}

		$posts->shouldReceive( 'getSets' )->andReturn( $postsSets )->byDefault();
		$tax->shouldReceive( 'getSets' )->andReturn( $taxSets )->byDefault();
		$auth->shouldReceive( 'getSets' )->andReturn( $authSets )->byDefault();

		foreach ( $map as $set => $count ) {
			$realSet = str_starts_with( $set, 'tax_' ) ? substr( $set, 4 ) : $set;
			if ( in_array( $realSet, [ 'category', 'post_tag' ], true ) ) {
				$tax->shouldReceive( 'getCount' )->with( $realSet )->andReturn( $count )->byDefault();
			} elseif ( 'authors' === $realSet ) {
				$auth->shouldReceive( 'getCount' )->with( $realSet )->andReturn( $count )->byDefault();
			} else {
				$posts->shouldReceive( 'getCount' )->with( $realSet )->andReturn( $count )->byDefault();
			}
		}

		// Entries fallback, not used in index tests.
		$posts->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		// Max modified fallback, overridden per test that asserts index dates.
		$posts->shouldReceive( 'getMaxLastmod' )->andReturn( '' )->byDefault();
		$tax->shouldReceive( 'getMaxLastmod' )->andReturn( '' )->byDefault();
		$auth->shouldReceive( 'getMaxLastmod' )->andReturn( '' )->byDefault();

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ) use ( $perPage ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return $perPage;
				}
				return $value;
			}
		);

		return new IndexBuilder( $posts, $tax, $auth, '9.9.9-test' );
	}

	/**
	 * Test index xml shape one sitemap per populated set.
	 */
	public function test_index_xml_shape_one_sitemap_per_populated_set(): void {
		$builder = $this->makeBuilderWithCounts(
			[
				'post'         => 2500,
				'page'         => 0,
				'tax_category' => 5,
				'authors'      => 3,
			],
			1000
		);

		$xml = $builder->buildIndexXml();

		$this->assertStringContainsString( '<sitemapindex', $xml );
		$this->assertStringContainsString( 'post-sitemap.xml', $xml );
		$this->assertStringContainsString( 'post-sitemap2.xml', $xml );
		$this->assertStringContainsString( 'post-sitemap3.xml', $xml );
		$this->assertStringNotContainsString( 'page-sitemap', $xml, 'Empty sets must be skipped' );
		$this->assertStringContainsString( 'category-sitemap.xml', $xml );
		$this->assertStringContainsString( 'authors-sitemap.xml', $xml );
	}

	/**
	 * Test index lastmod carries each set's newest date, never now.
	 */
	public function test_index_lastmod_carries_set_newest_date(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$tax   = Mockery::mock( TaxonomiesProvider::class );
		$auth  = Mockery::mock( AuthorsProvider::class );

		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->with( 'post' )->andReturn( 5 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
		$posts->shouldReceive( 'getMaxLastmod' )->with( 'post' )->andReturn( '2026-08-24 10:00:00' )->byDefault();
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getMaxLastmod' )->andReturn( '' )->byDefault();
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getMaxLastmod' )->andReturn( '' )->byDefault();

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				return $value;
			}
		);

		$builder = new IndexBuilder( $posts, $tax, $auth, '9.9.9-test' );
		$xml     = $builder->buildIndexXml();

		$this->assertStringContainsString( '2026-08-24', $xml, 'index lastmod must be the set newest date' );
		$this->assertStringNotContainsString( '2026-01-01', $xml, 'index lastmod must never be now' );
	}

	/**
	 * Test stylesheet version injected into pi href.
	 */
	public function test_stylesheet_version_injected_into_pi_href(): void {
		// The XSL version is an explicit constructor dependency, never a
		// global read, so the cache-buster always matches the caller.
		$builder = $this->makeBuilderWithCounts( [ 'post' => 5 ], 1000 );

		$xml = $builder->buildIndexXml();

		$this->assertStringContainsString( 'sitemap.xsl?ver=9.9.9-test', $xml );
	}

	/**
	 * Test index page count math.
	 */
	public function test_index_page_count_math(): void {
		$builder = $this->makeBuilderWithCounts( [ 'post' => 1000 ], 1000 );
		$xml     = $builder->buildIndexXml();
		$this->assertStringContainsString( 'post-sitemap.xml', $xml );
		$this->assertStringNotContainsString( 'post-sitemap2.xml', $xml );

		$builder2 = $this->makeBuilderWithCounts( [ 'post' => 1001 ], 1000 );
		$xml2     = $builder2->buildIndexXml();
		$this->assertStringContainsString( 'post-sitemap2.xml', $xml2 );
	}

	/**
	 * Test empty set skipped.
	 */
	public function test_empty_set_skipped(): void {
		$builder = $this->makeBuilderWithCounts(
			[
				'post' => 0,
				'page' => 0,
			],
			1000
		);
		$xml     = $builder->buildIndexXml();

		$this->assertStringNotContainsString( '<sitemap>', $xml );
		$this->assertStringContainsString( '<sitemapindex', $xml );
	}

	/**
	 * Test entries xml with lastmod and image.
	 */
	public function test_entries_xml_with_lastmod_and_image(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->with( 'post', 1, 1000 )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '2026-01-01T00:00:00+00:00',
					'images'  => [ 'https://example.com/image.jpg', 'https://example.com/second.jpg' ],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getCount' )->andReturn( 0 )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getCount' )->andReturn( 0 )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => 1000 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress apply_filters signature.

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );

		$this->assertStringContainsString( '<urlset', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/hello/</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2026-01-01T00:00:00+00:00</lastmod>', $xml );
		$this->assertStringContainsString( '<image:image>', $xml );
		$this->assertStringContainsString( '<image:loc>https://example.com/image.jpg</image:loc>', $xml );
	}

	/**
	 * Test entries xml without image no block.
	 */
	public function test_entries_xml_without_image_no_block(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->with( 'post', 1, 1000 )->andReturn(
			[
				[
					'loc'     => 'https://example.com/no-image/',
					'lastmod' => '2026-02-02T00:00:00+00:00',
					'images'  => [],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => 1000 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress apply_filters signature.

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );

		$this->assertStringNotContainsString( '<image:image>', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/no-image/</loc>', $xml );
	}

	/**
	 * Test has set true only for populated sets.
	 */
	public function test_has_set_true_only_for_populated_sets(): void {
		$builder = $this->makeBuilderWithCounts(
			[
				'post'         => 2500,
				'page'         => 0,
				'tax_category' => 5,
			],
			1000
		);

		$this->assertTrue( $builder->hasSet( 'post' ) );
		$this->assertTrue( $builder->hasSet( 'category' ) );
		$this->assertFalse( $builder->hasSet( 'page' ), 'Empty sets must not count as existing' );
		$this->assertFalse( $builder->hasSet( 'nonexistent' ) );
	}

	/**
	 * Test get set page count math.
	 */
	public function test_get_set_page_count_math(): void {
		$builder = $this->makeBuilderWithCounts(
			[
				'post' => 2500,
			],
			1000
		);

		$this->assertSame( 3, $builder->getSetPageCount( 'post' ) );
		$this->assertSame( 0, $builder->getSetPageCount( 'page' ) );
		$this->assertSame( 0, $builder->getSetPageCount( 'nonexistent' ) );
	}

	/**
	 * Test getSetsWithPageCounts memoizes result and resetCache clears it.
	 */
	public function test_get_sets_with_page_counts_memoization_and_reset_cache(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->twice()->andReturn( [ 'post' ] );
		$posts->shouldReceive( 'getCount' )->twice()->with( 'post' )->andReturn( 10, 2000 );

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->twice()->andReturn( [] );

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->twice()->andReturn( [] );

		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => 1000 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress apply_filters signature.

		$builder = new IndexBuilder( $posts, $tax, $auth );

		// First call invokes provider methods.
		$first = $builder->getSetsWithPageCounts();
		$this->assertSame( [ 'post' => 1 ], $first );

		// Second call uses memoized result without calling provider methods again.
		$second = $builder->getSetsWithPageCounts();
		$this->assertSame( $first, $second );

		// resetCache clears memoization on the SAME builder instance so next call queries providers again.
		$builder->resetCache();

		$third = $builder->getSetsWithPageCounts();
		$this->assertSame( [ 'post' => 2 ], $third );
	}

	/**
	 * Test getSetsWithPageCounts recomputes when per_page changes.
	 */
	public function test_get_sets_with_page_counts_recomputes_when_per_page_changes(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->twice()->andReturn( [ 'post' ] );
		$posts->shouldReceive( 'getCount' )->twice()->with( 'post' )->andReturn( 1500 );

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->twice()->andReturn( [] );

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->twice()->andReturn( [] );

		$currentPerPage = 1000;

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ) use ( &$currentPerPage ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return $currentPerPage;
				}
				return $value;
			}
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );

		// Initial query with per_page = 1000: 1500 items = 2 pages.
		$first = $builder->getSetsWithPageCounts();
		$this->assertSame( [ 'post' => 2 ], $first );

		// Change per_page filter to 500 without calling resetCache().
		$currentPerPage = 500;

		// getSetsWithPageCounts recomputes because perPage changed: 1500 items / 500 = 3 pages.
		$second = $builder->getSetsWithPageCounts();
		$this->assertSame( [ 'post' => 3 ], $second );
	}

	/**
	 * Test default providers are memoized per builder and cleared by resetCache.
	 *
	 * The provider classes hold no per instance state, every read goes through
	 * the current settings, so storing the lazily created instance is safe and
	 * removes the repeated allocations during index and entry building. The
	 * reset must drop the stored instances so a settings change starts from
	 * fresh providers instead of a stale one.
	 */
	public function test_default_providers_are_memoized_and_reset(): void {
		$builder = new IndexBuilder();

		foreach ( [ 'postsProvider', 'taxonomiesProvider', 'authorsProvider' ] as $method ) {
			$reflection = new \ReflectionMethod( IndexBuilder::class, $method );
			$reflection->setAccessible( true );

			$first  = $reflection->invoke( $builder );
			$second = $reflection->invoke( $builder );

			$this->assertSame( $first, $second, $method . ' must reuse the stored instance' );

			$builder->resetCache();

			$this->assertNotSame( $first, $reflection->invoke( $builder ), $method . ' must create a fresh instance after resetCache' );
		}
	}

	/**
	 * Test entries xml is byte-identical when no loc filter is registered.
	 */
	public function test_entries_xml_loc_filter_not_registered_byte_identical(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '2026-01-01T00:00:00+00:00',
					'images'  => [ 'https://example.com/image.jpg' ],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				return $value;
			}
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );

		$expected = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<?xml-stylesheet type="text/xsl" href="https://example.com/sitemap.xsl?ver=0.1.0-test"?>' . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n"
			. '  <url>' . "\n"
			. '    <loc>https://example.com/hello/</loc>' . "\n"
			. '    <lastmod>2026-01-01T00:00:00+00:00</lastmod>' . "\n"
			. '    <image:image>' . "\n"
			. '      <image:loc>https://example.com/image.jpg</image:loc>' . "\n"
			. '    </image:image>' . "\n"
			. '  </url>' . "\n"
			. '</urlset>';

		$this->assertSame( $expected, $xml );
	}

	/**
	 * Test loc filter callback replaces loc and receives set and page.
	 */
	public function test_entries_xml_loc_filter_replaces_loc(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '',
					'images'  => [],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$registered = [];
		$received   = null;

		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors WordPress signature.
				$registered[ $hook ][] = $callback;
				return true;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				if ( isset( $registered[ $hook ] ) ) {
					foreach ( $registered[ $hook ] as $callback ) {
						$value = $callback( $value, ...$args );
					}
				}
				return $value;
			}
		);

		add_filter(
			'rankkernel/sitemap/loc',
			static function ( mixed $loc, mixed $set, mixed $page ) use ( &$received ): string {
				$received = [ $set, $page ];
				return 'https://example.com/overridden/';
			},
			10,
			3
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 2 );

		$this->assertStringContainsString( '<loc>https://example.com/overridden/</loc>', $xml );
		$this->assertSame( [ 'post', 2 ], $received );
	}

	/**
	 * Test non-string loc filter returns keep original URL.
	 */
	public function test_entries_xml_loc_filter_non_string_keeps_original(): void {
		foreach ( [ 123, null, [ 'x' ] ] as $retval ) {
			$posts = Mockery::mock( PostsProvider::class );
			$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
			$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
			$posts->shouldReceive( 'getEntries' )->andReturn(
				[
					[
						'loc'     => 'https://example.com/hello/',
						'lastmod' => '',
						'images'  => [],
					],
				]
			)->byDefault();

			$tax = Mockery::mock( TaxonomiesProvider::class );
			$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
			$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

			$auth = Mockery::mock( AuthorsProvider::class );
			$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
			$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

			$registered = [];
			Functions\when( 'add_filter' )->alias(
				static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors WordPress signature.
					$registered[ $hook ][] = $callback;
					return true;
				}
			);
			Functions\when( 'apply_filters' )->alias(
				static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered, $retval ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors WordPress signature.
					if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
						return 1000;
					}
					if ( 'rankkernel/sitemap/loc' === $hook && isset( $registered[ $hook ] ) ) {
						return $retval;
					}
					return $value;
				}
			);

			add_filter(
				'rankkernel/sitemap/loc',
				static function ( mixed $loc ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors WordPress signature.
					return null;
				},
				10,
				3
			);

			// Override apply_filters return to the desired non-string via closure scope impossible; instead rely on callback return value.
			// Re-register a callback that returns the specific non-string.
			$registered = [];
			add_filter(
				'rankkernel/sitemap/loc',
				static function ( mixed $loc ) use ( $retval ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors WordPress signature.
					return $retval;
				},
				10,
				3
			);

			$builder = new IndexBuilder( $posts, $tax, $auth );
			$xml     = $builder->buildEntriesXml( 'post', 1 );

			$this->assertStringContainsString( '<loc>https://example.com/hello/</loc>', $xml, 'non-string return must keep original' );
		}
	}

	/**
	 * Test empty-string loc filter return skips entry.
	 */
	public function test_entries_xml_loc_filter_empty_skips_entry(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '',
					'images'  => [],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$registered = [];
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors WordPress signature.
				$registered[ $hook ][] = $callback;
				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				if ( isset( $registered[ $hook ] ) ) {
					foreach ( $registered[ $hook ] as $callback ) {
						$value = $callback( $value, ...$args );
					}
				}
				return $value;
			}
		);

		add_filter(
			'rankkernel/sitemap/loc',
			static function ( mixed $loc ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors WordPress signature.
				return '';
			},
			10,
			3
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );

		$this->assertStringNotContainsString( '<url>', $xml );
		$this->assertStringNotContainsString( 'https://example.com/hello/', $xml );
	}


	/**
	 * Non-string loc filter returns keep original URL (extended matrix).
	 */
	public function test_entries_xml_loc_filter_non_string_matrix_keeps_original(): void {
		foreach ( [ null, false, 0, [ 'x' ], new \stdClass() ] as $retval ) {
			$posts = Mockery::mock( PostsProvider::class );
			$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
			$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
			$posts->shouldReceive( 'getEntries' )->andReturn(
				[
					[
						'loc'     => 'https://example.com/hello/',
						'lastmod' => '',
						'images'  => [],
					],
				]
			)->byDefault();

			$tax = Mockery::mock( TaxonomiesProvider::class );
			$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
			$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

			$auth = Mockery::mock( AuthorsProvider::class );
			$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
			$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

			$registered = [];
			Functions\when( 'add_filter' )->alias(
				static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					$registered[ $hook ][] = $callback;
					return true;
				}
			);
			Functions\when( 'apply_filters' )->alias(
				static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered, $retval ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
						return 1000;
					}
					if ( 'rankkernel/sitemap/loc' === $hook && isset( $registered[ $hook ] ) ) {
						return $retval;
					}
					return $value;
				}
			);

			$registered = [];
			add_filter(
				'rankkernel/sitemap/loc',
				static function ( mixed $loc ) use ( $retval ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					return $retval;
				},
				10,
				3
			);

			$builder = new IndexBuilder( $posts, $tax, $auth );
			$xml     = $builder->buildEntriesXml( 'post', 1 );

			$this->assertStringContainsString( '<loc>https://example.com/hello/</loc>', $xml, 'non-string return must keep original' );
		}
	}

	/**
	 * All entries skipped via empty string.
	 */
	public function test_entries_xml_loc_filter_all_skipped(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 2 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/a/',
					'lastmod' => '',
					'images'  => [],
				],
				[
					'loc'     => 'https://example.com/b/',
					'lastmod' => '',
					'images'  => [],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$registered = [];
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$registered[ $hook ][] = $callback;
				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				if ( isset( $registered[ $hook ] ) ) {
					foreach ( $registered[ $hook ] as $callback ) {
						$value = $callback( $value, ...$args );
					}
				}
				return $value;
			}
		);

		add_filter(
			'rankkernel/sitemap/loc',
			static function ( mixed $loc ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return '';
			},
			10,
			3
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );

		$this->assertStringContainsString( '<urlset', $xml );
		$this->assertStringNotContainsString( '<url>', $xml );
	}

	/**
	 * Invalid replacements keep original loc.
	 */
	public function test_entries_xml_loc_filter_invalid_keeps_original(): void {
		foreach ( [ '/relative', 'not-a-url', 'http://', 'ftp://example.com/x' ] as $bad ) {
			$posts = Mockery::mock( PostsProvider::class );
			$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
			$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
			$posts->shouldReceive( 'getEntries' )->andReturn(
				[
					[
						'loc'     => 'https://example.com/hello/',
						'lastmod' => '',
						'images'  => [],
					],
				]
			)->byDefault();

			$tax = Mockery::mock( TaxonomiesProvider::class );
			$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
			$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

			$auth = Mockery::mock( AuthorsProvider::class );
			$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
			$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

			$registered = [];
			Functions\when( 'add_filter' )->alias(
				static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					$registered[ $hook ][] = $callback;
					return true;
				}
			);
			Functions\when( 'apply_filters' )->alias(
				static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
					if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
						return 1000;
					}
					if ( isset( $registered[ $hook ] ) ) {
						foreach ( $registered[ $hook ] as $callback ) {
							$value = $callback( $value, ...$args );
						}
					}
					return $value;
				}
			);

			add_filter(
				'rankkernel/sitemap/loc',
				static function ( mixed $loc ) use ( $bad ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					return $bad;
				},
				10,
				3
			);

			$builder = new IndexBuilder( $posts, $tax, $auth );
			$xml     = $builder->buildEntriesXml( 'post', 1 );

			$this->assertStringContainsString( '<loc>https://example.com/hello/</loc>', $xml, 'invalid must keep original' );
		}

		// Valid control.
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '',
					'images'  => [],
				],
			]
		)->byDefault();
		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$registered = [];
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$registered[ $hook ][] = $callback;
				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				if ( isset( $registered[ $hook ] ) ) {
					foreach ( $registered[ $hook ] as $callback ) {
						$value = $callback( $value, ...$args );
					}
				}
				return $value;
			}
		);
		add_filter(
			'rankkernel/sitemap/loc',
			static function ( mixed $loc ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return 'https://cdn.example.com/new';
			},
			10,
			3
		);
		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );
		$this->assertStringContainsString( '<loc>https://cdn.example.com/new</loc>', $xml );
	}

	/**
	 * Escaping after filter stays single-escaped.
	 */
	public function test_entries_xml_loc_filter_escaping_single(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '',
					'images'  => [],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$registered = [];
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$registered[ $hook ][] = $callback;
				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				if ( isset( $registered[ $hook ] ) ) {
					foreach ( $registered[ $hook ] as $callback ) {
						$value = $callback( $value, ...$args );
					}
				}
				return $value;
			}
		);
		add_filter(
			'rankkernel/sitemap/loc',
			static function ( mixed $loc ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return 'https://example.com/a?x=1&y=2<>"\'"';
			},
			10,
			3
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );

		$this->assertStringNotContainsString( '&amp;amp;', $xml );
		$parsed = simplexml_load_string( $xml );
		$this->assertNotFalse( $parsed, 'XML must stay valid' );
	}

	/**
	 * Only loc changes; lastmod and image:loc unchanged.
	 */
	public function test_entries_xml_loc_filter_only_loc_changes(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '2026-01-01T00:00:00+00:00',
					'images'  => [ 'https://example.com/image.jpg' ],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$registered = [];
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$registered[ $hook ][] = $callback;
				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				if ( isset( $registered[ $hook ] ) ) {
					foreach ( $registered[ $hook ] as $callback ) {
						$value = $callback( $value, ...$args );
					}
				}
				return $value;
			}
		);
		add_filter(
			'rankkernel/sitemap/loc',
			static function ( mixed $loc ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return 'https://example.com/overridden/';
			},
			10,
			3
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$xml     = $builder->buildEntriesXml( 'post', 1 );

		$this->assertStringContainsString( '<loc>https://example.com/overridden/</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2026-01-01T00:00:00+00:00</lastmod>', $xml );
		$this->assertStringContainsString( '<image:loc>https://example.com/image.jpg</image:loc>', $xml );
	}

	/**
	 * Hook identity for taxonomy and authors sets.
	 */
	public function test_entries_xml_loc_filter_hook_identity_tax_and_authors(): void {
		foreach ( [
			'category' => 'tax',
			'authors'  => 'authors',
		] as $set => $label ) {
			$posts = Mockery::mock( PostsProvider::class );
			$posts->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
			$posts->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

			$tax = Mockery::mock( TaxonomiesProvider::class );
			if ( 'category' === $set ) {
				$tax->shouldReceive( 'getSets' )->andReturn( [ 'category' ] )->byDefault();
				$tax->shouldReceive( 'getEntries' )->with( 'category', 1, 1000 )->andReturn(
					[
						[
							'loc'     => 'https://example.com/t/',
							'lastmod' => '',
							'images'  => [],
						],
					]
				)->byDefault();
			} else {
				$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
				$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
			}

			$auth = Mockery::mock( AuthorsProvider::class );
			if ( 'authors' === $set ) {
				$auth->shouldReceive( 'getSets' )->andReturn( [ 'authors' ] )->byDefault();
				$auth->shouldReceive( 'getEntries' )->with( 'authors', 1, 1000 )->andReturn(
					[
						[
							'loc'     => 'https://example.com/a/',
							'lastmod' => '',
							'images'  => [],
						],
					]
				)->byDefault();
			} else {
				$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
				$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
			}

			$posts->shouldReceive( 'getCount' )->andReturn( 0 )->byDefault();

			$received   = null;
			$registered = [];
			Functions\when( 'add_filter' )->alias(
				static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					$registered[ $hook ][] = $callback;
					return true;
				}
			);
			Functions\when( 'apply_filters' )->alias(
				static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
					if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
						return 1000;
					}
					if ( isset( $registered[ $hook ] ) ) {
						foreach ( $registered[ $hook ] as $callback ) {
							$value = $callback( $value, ...$args );
						}
					}
					return $value;
				}
			);

			add_filter(
				'rankkernel/sitemap/loc',
				static function ( mixed $loc, mixed $s, mixed $p ) use ( &$received ): string {
					$received = [ $loc, $s, $p ];
					return 'https://example.com/overridden/';
				},
				10,
				3
			);

			$builder = new IndexBuilder( $posts, $tax, $auth );
			$builder->buildEntriesXml( $set, 1 );

			$this->assertNotNull( $received );
			$this->assertSame( $set, $received[1], 'set arg mismatch' );
			$this->assertSame( 1, $received[2], 'page arg mismatch' );
		}
	}

	/**
	 * Exception from callback propagates.
	 */
	public function test_entries_xml_loc_filter_exception_rethrows(): void {
		$posts = Mockery::mock( PostsProvider::class );
		$posts->shouldReceive( 'getSets' )->andReturn( [ 'post' ] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn(
			[
				[
					'loc'     => 'https://example.com/hello/',
					'lastmod' => '',
					'images'  => [],
				],
			]
		)->byDefault();

		$tax = Mockery::mock( TaxonomiesProvider::class );
		$tax->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$auth = Mockery::mock( AuthorsProvider::class );
		$auth->shouldReceive( 'getSets' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();

		$registered = [];
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, mixed ...$rest ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$registered[ $hook ][] = $callback;
				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value, mixed ...$args ) use ( &$registered ): mixed {
				if ( 'rankkernel/sitemap/entries_per_page' === $hook ) {
					return 1000;
				}
				if ( isset( $registered[ $hook ] ) ) {
					foreach ( $registered[ $hook ] as $callback ) {
						$value = $callback( $value, ...$args );
					}
				}
				return $value;
			}
		);

		add_filter(
			'rankkernel/sitemap/loc',
			static function ( mixed $loc ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				throw new \RuntimeException( 'boom-filter' );
			},
			10,
			3
		);

		$builder = new IndexBuilder( $posts, $tax, $auth );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'boom-filter' );
		$builder->buildEntriesXml( 'post', 1 );
	}
}
