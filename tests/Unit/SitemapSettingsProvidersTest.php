<?php
/**
 * Sitemap settings provider wiring tests.
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
use RankKernel\Modules\Sitemaps\SitemapSettings;
use RankKernel\Tests\Unit\Support\SitemapSettingsFakeWpdb;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'DATE_W3C' ) ) {
	define( 'DATE_W3C', 'Y-m-d\\TH:i:sP' );
}

/**
 * Sitemap Settings Providers Test.
 */
final class SitemapSettingsProvidersTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Db.
	 *
	 * @var SitemapSettingsFakeWpdb
	 */
	private SitemapSettingsFakeWpdb $db;

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

		$this->db        = new SitemapSettingsFakeWpdb();
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test setup swaps the WordPress global, restored in tearDown.
		$this->options   = [];

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed ...$rest ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', $v ) )
		);
		Functions\when( 'get_post_types' )->alias(
			static fn ( array $a = [], string $o = '' ): array => [ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_types signature.
				'post' => 'post',
				'page' => 'page',
			]
		);
		Functions\when( 'get_taxonomies' )->alias(
			static fn ( array $a = [], string $o = '' ): array => [ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_taxonomies signature.
				'category' => 'category',
				'post_tag' => 'post_tag',
			]
		);
		Functions\when( 'trailingslashit' )->alias( static fn ( string $s ): string => rtrim( $s, '/' ) . '/' );
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'do_blocks' )->alias( static fn ( string $c ): string => $c );
		Functions\when( 'mysql2date' )->alias( static fn ( string $f, string $d, bool $t = true ): string => gmdate( $f, strtotime( $d ) ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress mysql2date signature.
		Functions\when( 'current_time' )->alias( static fn ( string $t, bool $g = false ): string => '2026-05-01 00:00:00' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress current_time signature.
		Functions\when( 'get_permalink' )->alias( static fn ( int $id ): string => 'https://example.com/?p=' . $id );
		Functions\when( 'get_term' )->alias( static fn ( int $id, string $t = '' ): object => (object) [ 'term_id' => $id ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term signature.
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $v ): bool => $v instanceof \WP_Error );
		Functions\when( 'get_term_link' )->alias(
			static function ( mixed $t, string $tax = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_link signature.
				$id = is_object( $t ) && isset( $t->term_id ) ? (int) $t->term_id : (int) $t;

				return 'https://example.com/t' . $id . '/';
			}
		);
		Functions\when( 'get_author_posts_url' )->alias( static fn ( int $id ): string => 'https://example.com/author/u' . $id . '/' );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Seed Posts.
	 */
	private function seedPosts(): void {
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
			],
			[
				'ID'                => 2,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-02 00:00:00',
			],
			[
				'ID'                => 3,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 8,
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
		];
	}

	/**
	 * Seed Terms.
	 */
	private function seedTerms(): void {
		$this->seedPosts();
		$this->db->termsList     = [ 10, 11 ];
		$this->db->termTaxonomy  = [
			10 => 'category',
			11 => 'category',
		];
		$this->db->relationships = [
			10 => [ 1 ],
			11 => [ 2, 3 ],
		];
	}

	/**
	 * Seed Users.
	 */
	private function seedUsers(): void {
		$this->db->usersRows = [
			7 => '2026-01-02 00:00:00',
			8 => '2026-02-02 00:00:00',
			9 => '2026-03-03 00:00:00',
		];
		$this->db->userRoles = [
			7 => [ 'administrator' ],
			8 => [ 'editor' ],
			9 => [ 'subscriber' ],
		];
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
			],
			[
				'ID'                => 2,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 8,
				'post_modified_gmt' => '2026-01-02 00:00:00',
			],
			[
				'ID'                => 3,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 9,
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
		];
	}

	/**
	 * Test post type toggle off removes set and zeroes count.
	 */
	public function test_post_type_toggle_off_removes_set_and_zeroes_count(): void {
		$this->seedPosts();
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$settings = new SitemapSettings();
		$settings->set( [ 'pt_post_sitemap' => false ] );

		$provider = new PostsProvider( $settings );

		$this->assertNotContains( 'post', $provider->getSets() );
		$this->assertContains( 'page', $provider->getSets() );
		$this->assertSame( 0, $provider->getCount( 'post' ) );
	}

	/**
	 * Test exclude posts absent from entries and counts.
	 */
	public function test_exclude_posts_absent_from_entries_and_counts(): void {
		$this->seedPosts();
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$settings = new SitemapSettings();
		$settings->set( [ 'exclude_posts' => [ 2 ] ] );

		$provider = new PostsProvider( $settings );

		$this->assertSame( 2, $provider->getCount( 'post' ) );

		$entries = $provider->getEntries( 'post', 1, 10 );
		$locs    = array_column( $entries, 'loc' );

		$this->assertNotContains( 'https://example.com/?p=2', $locs );
		$this->assertContains( 'https://example.com/?p=1', $locs );
		$this->assertContains( 'https://example.com/?p=3', $locs );

		$found = false;

		foreach ( $this->db->queries as $sql ) {
			if ( str_contains( $sql, 'NOT IN' ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Entries query must carry the exclusion clause' );
	}

	/**
	 * Test exclude posts chunked beyond 500.
	 */
	public function test_exclude_posts_chunked_beyond_500(): void {
		$this->db->postsRows = [
			[
				'ID'                => 5,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
		];
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$settings = new SitemapSettings();
		$settings->set( [ 'exclude_posts' => range( 1, 600 ) ] );

		$provider = new PostsProvider( $settings );

		$this->assertSame( 0, $provider->getCount( 'post' ) );
		$this->assertSame( [], $provider->getEntries( 'post', 1, 10 ) );
		$this->assertSame( 2, substr_count( $this->db->lastSql, 'NOT IN' ) );
	}

	/**
	 * Test include images false yields empty without lookups.
	 */
	public function test_include_images_false_yields_empty_without_lookups(): void {
		$this->seedPosts();

		Functions\expect( 'get_post_thumbnail_id' )->never();
		Functions\expect( 'wp_get_attachment_image_url' )->never();

		$settings = new SitemapSettings();
		$settings->set( [ 'include_images' => false ] );

		$provider = new PostsProvider( $settings );
		$entries  = $provider->getEntries( 'post', 1, 10 );

		$this->assertCount( 3, $entries );
		$this->assertSame( [ [], [], [] ], array_column( $entries, 'images' ) );
	}

	/**
	 * Test include featured image false keeps content images.
	 */
	public function test_include_featured_image_false_keeps_content_images(): void {
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
				'post_content'      => '<p><img src="https://example.com/inline.jpg"></p>',
			],
		];

		Functions\expect( 'get_post_thumbnail_id' )->never();
		Functions\expect( 'wp_get_attachment_image_url' )->never();

		$settings = new SitemapSettings();
		$settings->set( [ 'include_featured_image' => false ] );

		$provider = new PostsProvider( $settings );
		$entries  = $provider->getEntries( 'post', 1, 10 );

		$this->assertCount( 1, $entries );
		$this->assertSame( [ 'https://example.com/inline.jpg' ], $entries[0]['images'] );
	}

	/**
	 * Test content images parsed featured first deduped.
	 */
	public function test_content_images_parsed_featured_first_deduped(): void {
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
				'post_content'      => '<p>Hi <img src="https://example.com/a.jpg"> and <img src="/b.jpg"> and <img src="https://example.com/a.jpg"></p>',
			],
		];

		Functions\when( 'get_post_thumbnail_id' )->alias( static fn ( int $id ): int => 9 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_thumbnail_id signature.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_get_attachment_image_url signature.
		Functions\when( 'wp_get_attachment_image_url' )->alias( static fn ( int $id, string $s = 'full' ): string|false => 'https://example.com/featured.jpg' );
		Functions\when( 'wp_get_attachment_url' )->alias( static fn ( int $id ): string => 'https://example.com/g' . $id . '.jpg' );

		$provider = new PostsProvider( new SitemapSettings() );
		$entries  = $provider->getEntries( 'post', 1, 10 );

		$this->assertCount( 1, $entries );
		$this->assertSame(
			[ 'https://example.com/featured.jpg', 'https://example.com/a.jpg', 'https://example.com/b.jpg' ],
			$entries[0]['images']
		);
	}

	/**
	 * Test external and data images skipped.
	 */
	public function test_external_and_data_images_skipped(): void {
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
				'post_content'      => '<p><img src="https://cdn.other.test/x.jpg"><img src="data:image/png;base64,AAA"><img src="https://example.com/ok.jpg"></p>',
			],
		];

		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_url' )->alias( static fn ( int $id ): string => '' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress wp_get_attachment_url signature.

		$provider = new PostsProvider( new SitemapSettings() );
		$entries  = $provider->getEntries( 'post', 1, 10 );

		$this->assertSame( [ 'https://example.com/ok.jpg' ], $entries[0]['images'] );
	}

	/**
	 * Test dynamic blocks render before image parse.
	 */
	public function test_dynamic_blocks_render_before_image_parse(): void {
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
				'post_content'      => '<!-- wp:shortcode -->[logo_carousel]<!-- /wp:shortcode -->',
			],
		];

		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_url' )->alias( static fn ( int $id ): string => '' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress wp_get_attachment_url signature.
		Functions\when( 'do_blocks' )->alias(
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress do_blocks signature.
			static fn ( string $c ): string => '<div class="swiper-slide"><img src="https://example.com/logo-a.webp"><img src="https://example.com/logo-b.webp"></div>'
		);

		$provider = new PostsProvider( new SitemapSettings() );
		$entries  = $provider->getEntries( 'post', 1, 10 );

		$this->assertSame( [ 'https://example.com/logo-a.webp', 'https://example.com/logo-b.webp' ], $entries[0]['images'] );
	}

	/**
	 * Test gallery shortcode ids resolve.
	 */
	public function test_gallery_shortcode_ids_resolve(): void {
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
				'post_content'      => '<p>Text</p>[gallery ids="5,6,0,-2"]',
			],
		];

		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_url' )->alias( static fn ( int $id ): string => 5 === $id || 6 === $id ? 'https://example.com/g' . $id . '.jpg' : '' );

		$provider = new PostsProvider( new SitemapSettings() );
		$entries  = $provider->getEntries( 'post', 1, 10 );

		$this->assertSame( [ 'https://example.com/g5.jpg', 'https://example.com/g6.jpg' ], $entries[0]['images'] );
	}

	/**
	 * Test taxonomy toggle off removes set and zeroes count.
	 */
	public function test_taxonomy_toggle_off_removes_set_and_zeroes_count(): void {
		$this->seedTerms();

		$settings = new SitemapSettings();
		$settings->set( [ 'tax_category_sitemap' => false ] );

		$provider = new TaxonomiesProvider( $settings );

		$this->assertNotContains( 'category', $provider->getSets() );
		$this->assertContains( 'post_tag', $provider->getSets() );
		$this->assertSame( 0, $provider->getCount( 'category' ) );
	}

	/**
	 * Test exclude terms absent from entries and counts.
	 */
	public function test_exclude_terms_absent_from_entries_and_counts(): void {
		$this->seedTerms();

		$settings = new SitemapSettings();
		$settings->set( [ 'exclude_terms' => [ 10 ] ] );

		$provider = new TaxonomiesProvider( $settings );

		$this->assertSame( 1, $provider->getCount( 'category' ) );

		$entries = $provider->getEntries( 'category', 1, 10 );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'https://example.com/t11/', $entries[0]['loc'] );
	}

	/**
	 * Test include empty terms true lists zero post term.
	 */
	public function test_include_empty_terms_true_lists_zero_post_term(): void {
		$this->seedTerms();
		$this->db->termsList         = [ 10, 12 ];
		$this->db->termTaxonomy[12]  = 'category';
		$this->db->relationships[12] = [];

		$settings = new SitemapSettings();
		$provider = new TaxonomiesProvider( $settings );

		$this->assertSame( 1, $provider->getCount( 'category' ) );
		$this->assertCount( 1, $provider->getEntries( 'category', 1, 10 ) );

		$settings->set( [ 'include_empty_terms' => true ] );

		$this->assertSame( 2, $provider->getCount( 'category' ) );

		$entries = $provider->getEntries( 'category', 1, 10 );

		$this->assertCount( 2, $entries );

		$lastmods = array_column( $entries, 'lastmod' );
		$expected = gmdate( DATE_W3C, strtotime( '2026-05-01 00:00:00' ) );

		$this->assertContains( $expected, $lastmods );
	}

	/**
	 * Test authors sitemap false empties authors.
	 */
	public function test_authors_sitemap_false_empties_authors(): void {
		$this->seedUsers();

		$settings = new SitemapSettings();
		$settings->set( [ 'authors_sitemap' => false ] );

		$provider = new AuthorsProvider( $settings );

		$this->assertSame( [], $provider->getSets() );
		$this->assertSame( 0, $provider->getCount( 'authors' ) );
		$this->assertSame( [], $provider->getEntries( 'authors', 1, 10 ) );
	}

	/**
	 * Test authors include empty true lists post less user.
	 */
	public function test_authors_include_empty_true_lists_post_less_user(): void {
		$this->db->usersRows = [
			7 => '2026-01-02 00:00:00',
			9 => '2026-03-03 00:00:00',
		];
		$this->db->postsRows = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_author'       => 7,
				'post_modified_gmt' => '2026-01-03 00:00:00',
			],
		];

		$settings = new SitemapSettings();
		$provider = new AuthorsProvider( $settings );

		$this->assertSame( 1, $provider->getCount( 'authors' ) );

		$settings->set( [ 'authors_include_empty' => true ] );

		$this->assertSame( 2, $provider->getCount( 'authors' ) );

		$entries = $provider->getEntries( 'authors', 1, 10 );
		$locs    = array_column( $entries, 'loc' );

		$this->assertContains( 'https://example.com/author/u7/', $locs );
		$this->assertContains( 'https://example.com/author/u9/', $locs );

		$expected = gmdate( DATE_W3C, strtotime( '2026-03-03 00:00:00' ) );

		$found = false;

		foreach ( $entries as $entry ) {
			if ( 'https://example.com/author/u9/' === $entry['loc'] && $expected === $entry['lastmod'] ) {
				$found = true;
			}
		}

		$this->assertTrue( $found, 'Post less author uses registration date as lastmod' );
	}

	/**
	 * Test authors excluded roles and users absent.
	 */
	public function test_authors_excluded_roles_and_users_absent(): void {
		$this->seedUsers();

		$settings = new SitemapSettings();
		$settings->set(
			[
				'authors_exclude_roles' => [ 'administrator' ],
				'authors_exclude_users' => [ 8 ],
			]
		);

		$provider = new AuthorsProvider( $settings );

		$this->assertSame( 1, $provider->getCount( 'authors' ) );

		$entries = $provider->getEntries( 'authors', 1, 10 );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'https://example.com/author/u9/', $entries[0]['loc'] );

		$settings->set( [ 'authors_include_empty' => true ] );

		$this->assertSame( 1, $provider->getCount( 'authors' ) );
	}

	/**
	 * Test index builder per page honors settings then filter.
	 */
	public function test_index_builder_per_page_honors_settings_then_filter(): void {
		$this->options[ SitemapSettings::OPTION ] = [ 'items_per_page' => 250 ];

		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => $v );

		$settings = new SitemapSettings();
		$builder  = new IndexBuilder( null, null, null, '', $settings );

		$this->assertSame( 250, $builder->getPerPage() );

		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => 7 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress apply_filters signature.

		$this->assertSame( 7, $builder->getPerPage(), 'Filter wins over settings' );
	}

	/**
	 * Test index page math uses settings per page.
	 */
	public function test_index_page_math_uses_settings_per_page(): void {
		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => $v );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'add_query_arg' )->alias(
			static function ( mixed $k = '', mixed $v = '', string $u = '' ): string {
				return $u . ( str_contains( $u, '?' ) ? '&' : '?' ) . (string) $k . '=' . (string) $v;
			}
		);

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

		$settings = new SitemapSettings();
		$settings->set( [ 'items_per_page' => 2 ] );

		$builder = new IndexBuilder( $posts, $tax, $auth, '9.9.9-test', $settings );
		$xml     = $builder->buildIndexXml();

		$this->assertStringContainsString( '<sitemapindex', $xml );
		$this->assertStringContainsString( 'post-sitemap3.xml', $xml );
	}
}
