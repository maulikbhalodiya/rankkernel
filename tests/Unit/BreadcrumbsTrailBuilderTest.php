<?php
/**
 * TrailBuilder context matrix tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Breadcrumbs\BreadcrumbsModule;
use RankKernel\Modules\Breadcrumbs\Item;
use RankKernel\Modules\Breadcrumbs\TrailBuilder;
use RankKernel\Modules\Breadcrumbs\BreadcrumbsSettings;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Breadcrumbs Trail Builder Test.
 */
final class BreadcrumbsTrailBuilderTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Conditional flags.
	 *
	 * @var array<string, bool>
	 */
	private array $conds = [];

	/**
	 * Query variables.
	 *
	 * @var array<string, mixed>
	 */
	private array $queryVars = [];

	/**
	 * WordPress options.
	 *
	 * @var array<string, mixed>
	 */
	private array $wpOptions = [];

	/**
	 * Stored breadcrumbs settings option.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $settingsOption = null;

	/**
	 * Queried object double.
	 *
	 * @var object|null
	 */
	private ?object $queriedObject = null;

	/**
	 * Posts by id.
	 *
	 * @var array<int, object>
	 */
	private array $posts = [];

	/**
	 * Titles by post id.
	 *
	 * @var array<int, string>
	 */
	private array $titles = [];

	/**
	 * Post ancestors by post id.
	 *
	 * @var array<int, int[]>
	 */
	private array $postAncestors = [];

	/**
	 * Terms by post id and taxonomy.
	 *
	 * @var array<string, object[]>
	 */
	private array $termsMap = [];

	/**
	 * Term ancestors by term id.
	 *
	 * @var array<int, int[]>
	 */
	private array $termAncestors = [];

	/**
	 * Terms by id.
	 *
	 * @var array<int, object>
	 */
	private array $termsById = [];

	/**
	 * Post type objects by type.
	 *
	 * @var array<string, object>
	 */
	private array $postTypes = [];

	/**
	 * Taxonomy objects by slug.
	 *
	 * @var array<string, object>
	 */
	private array $taxonomies = [];

	/**
	 * Taxonomy map per post type, slug to public flag.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private array $objectTaxes = [];

	/**
	 * Hierarchical flag per taxonomy.
	 *
	 * @var array<string, bool>
	 */
	private array $taxHier = [];

	/**
	 * Hierarchical flag per post type.
	 *
	 * @var array<string, bool>
	 */
	private array $hierTypes = [];

	/**
	 * Term link overrides by slug.
	 *
	 * @var array<string, string>
	 */
	private array $termLinkUrls = [];

	/**
	 * Search query string.
	 *
	 * @var string
	 */
	private string $searchQuery = '';

	/**
	 * Author display name.
	 *
	 * @var string
	 */
	private string $authorName = '';

	/**
	 * Stored post meta payload.
	 *
	 * @var array<string, mixed>
	 */
	private array $postMeta = [];

	/**
	 * Post type settings filter double, null for passthrough.
	 *
	 * @var callable|null
	 */
	private mixed $postTypeFilter = null;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->wpOptions = [
			'show_on_front'  => 'posts',
			'page_on_front'  => 0,
			'page_for_posts' => 0,
		];

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_front_page' )->alias( fn (): bool => ! empty( $this->conds['front'] ) );
		Functions\when( 'is_home' )->alias( fn (): bool => ! empty( $this->conds['home'] ) );
		Functions\when( 'is_search' )->alias( fn (): bool => ! empty( $this->conds['search'] ) );
		Functions\when( 'is_404' )->alias( fn (): bool => ! empty( $this->conds['404'] ) );
		Functions\when( 'is_post_type_archive' )->alias( fn (): bool => ! empty( $this->conds['pt_archive'] ) );
		Functions\when( 'is_author' )->alias( fn (): bool => ! empty( $this->conds['author'] ) );
		Functions\when( 'is_date' )->alias( fn (): bool => ! empty( $this->conds['date'] ) );
		Functions\when( 'get_query_var' )->alias(
			function ( string $key, mixed $fallback = 0 ): mixed {
				return $this->queryVars[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_breadcrumbs_settings' === $key ) {
					return $this->settingsOption ?? $fallback;
				}

				if ( array_key_exists( $key, $this->wpOptions ) ) {
					return $this->wpOptions[ $key ];
				}

				return $fallback;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', $v ) ) );
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value, mixed ...$rest ): mixed {
				if ( 'rankkernel/breadcrumbs/post_type_settings' === $hook && null !== $this->postTypeFilter ) {
					return call_user_func( $this->postTypeFilter, $value, ...$rest );
				}

				return $value;
			}
		);
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_permalink' )->alias(
			static function ( mixed $id ): string {
				if ( is_object( $id ) && isset( $id->ID ) ) {
					$id = $id->ID;
				}

				return 'https://example.com/?p=' . (int) $id;
			}
		);
		Functions\when( 'get_the_title' )->alias(
			function ( mixed $id = 0 ): string {
				return $this->titles[ (int) $id ] ?? '';
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		// Note: single_post_title is intentionally never stubbed here. Brain Monkey
		// defines stubbed functions process wide, and later suites rely on that
		// function staying undefined, so Context title logic skips it.
		Functions\when( 'get_post_meta' )->alias(
			function (): mixed {
				return $this->postMeta;
			}
		);
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'get_queried_object' )->alias(
			function (): mixed {
				return $this->queriedObject;
			}
		);
		Functions\when( 'get_post_ancestors' )->alias(
			function ( int $id ): array {
				return $this->postAncestors[ $id ] ?? [];
			}
		);
		Functions\when( 'get_post' )->alias(
			function ( int $id ): mixed {
				return $this->posts[ $id ] ?? null;
			}
		);
		Functions\when( 'wp_get_post_parent_id' )->alias(
			function ( int $id ): int {
				$post = $this->posts[ $id ] ?? null;

				if ( is_object( $post ) && isset( $post->post_parent ) ) {
					return (int) $post->post_parent;
				}

				return 0;
			}
		);
		Functions\when( 'get_ancestors' )->alias(
			function ( int $id ): array {
				return $this->termAncestors[ $id ] ?? [];
			}
		);
		Functions\when( 'get_term' )->alias(
			function ( int $id ): mixed {
				return $this->termsById[ $id ] ?? null;
			}
		);
		Functions\when( 'get_the_terms' )->alias(
			function ( int $postId, string $taxonomy ): mixed {
				return $this->termsMap[ $postId . ':' . $taxonomy ] ?? [];
			}
		);
		Functions\when( 'get_term_link' )->alias(
			function ( mixed $term ): string {
				$slug = '';

				if ( is_object( $term ) && isset( $term->slug ) ) {
					$slug = (string) $term->slug;
				} elseif ( ! is_object( $term ) && isset( $this->termsById[ (int) $term ] ) && isset( $this->termsById[ (int) $term ]->slug ) ) {
					$slug = (string) $this->termsById[ (int) $term ]->slug;
				}

				if ( '' !== $slug && isset( $this->termLinkUrls[ $slug ] ) ) {
					return $this->termLinkUrls[ $slug ];
				}

				if ( '' !== $slug ) {
					return 'https://example.com/go/' . $slug . '/';
				}

				return 'https://example.com/go/term/';
			}
		);
		Functions\when( 'get_post_type_object' )->alias(
			function ( string $type ): mixed {
				return $this->postTypes[ $type ] ?? false;
			}
		);
		Functions\when( 'get_taxonomy' )->alias(
			function ( string $taxonomy ): mixed {
				return $this->taxonomies[ $taxonomy ] ?? false;
			}
		);
		Functions\when( 'get_object_taxonomies' )->alias(
			function ( string $postType, string $output = 'names' ): array {
				$map = $this->objectTaxes[ $postType ] ?? [];

				if ( 'objects' === $output ) {
					$out = [];

					foreach ( $map as $name => $public ) {
						$out[ $name ] = (object) [
							'name'   => $name,
							'public' => $public,
						];
					}

					return $out;
				}

				return array_keys( $map );
			}
		);
		Functions\when( 'is_taxonomy_hierarchical' )->alias(
			function ( string $taxonomy ): bool {
				return $this->taxHier[ $taxonomy ] ?? false;
			}
		);
		Functions\when( 'is_post_type_hierarchical' )->alias(
			function ( string $postType ): bool {
				return $this->hierTypes[ $postType ] ?? false;
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_post_type_archive_link' )->alias( static fn ( string $type ): string => 'https://example.com/type/' . $type . '/' );
		Functions\when( 'get_search_link' )->alias( fn (): string => 'https://example.com/?s=' . $this->searchQuery );
		Functions\when( 'get_search_query' )->alias( fn (): string => $this->searchQuery );
		Functions\when( 'get_author_posts_url' )->alias( static fn ( int $id ): string => 'https://example.com/author/' . $id . '/' );
		Functions\when( 'get_the_author_meta' )->alias( fn (): string => $this->authorName );
		Functions\when( 'get_year_link' )->alias( static fn ( int $y ): string => 'https://example.com/' . $y . '/' );
		Functions\when( 'get_month_link' )->alias( static fn ( int $y, int $m ): string => 'https://example.com/' . $y . '/' . $m . '/' );
		Functions\when( 'get_day_link' )->alias( static fn ( int $y, int $m, int $d ): string => 'https://example.com/' . $y . '/' . $m . '/' . $d . '/' );
		Functions\when( 'date_i18n' )->alias( static fn ( string $format, int $ts ): string => date( $format, $ts ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- test stub mirrors date_i18n output formatting with a fixed timestamp.
		Functions\when( 'number_format_i18n' )->alias( static fn ( mixed $n ): string => (string) $n );
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
	 * Make a query double.
	 *
	 * @param array<string, bool> $flags Conditional flags.
	 * @param int                 $id    Queried object id.
	 * @return WP_Query The result.
	 */
	private function makeQuery( array $flags = [], int $id = 0 ): WP_Query {
		$q = Mockery::mock( WP_Query::class );

		$defaults = [
			'is_singular'          => false,
			'is_search'            => false,
			'is_404'               => false,
			'is_feed'              => false,
			'is_preview'           => false,
			'is_category'          => false,
			'is_tag'               => false,
			'is_tax'               => false,
			'is_home'              => false,
			'is_front_page'        => false,
			'is_archive'           => false,
			'is_author'            => false,
			'is_date'              => false,
			'is_post_type_archive' => false,
		];

		foreach ( array_merge( $defaults, $flags ) as $method => $value ) {
			$q->shouldReceive( $method )->andReturn( $value )->byDefault();
		}

		$q->shouldReceive( 'get_queried_object_id' )->andReturn( $id )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( '' )->byDefault();
		$q->shouldReceive( 'get_queried_object' )->andReturnNull()->byDefault();

		return $q;
	}

	/**
	 * Build the trail for a query.
	 *
	 * @param WP_Query $q Query double.
	 * @return Item[] Trail.
	 */
	private function trail( WP_Query $q ): array {
		$ctx = new Context( $q, new SettingsStore() );

		return ( new TrailBuilder( $ctx, new BreadcrumbsSettings() ) )->build();
	}

	/**
	 * Labels of a trail.
	 *
	 * @param Item[] $items Trail.
	 * @return string[] Labels.
	 */
	private function labels( array $items ): array {
		return array_map( static fn ( Item $item ): string => $item->label(), $items );
	}

	/**
	 * Make a post double.
	 *
	 * @param int    $id       Post id.
	 * @param string $type     Post type.
	 * @param string $title    Post title.
	 * @param int    $parentId Parent id.
	 * @return object Post double.
	 */
	private function post( int $id, string $type, string $title, int $parentId = 0 ): object {
		return (object) [
			'ID'          => $id,
			'post_type'   => $type,
			'post_title'  => $title,
			'post_parent' => $parentId,
		];
	}

	/**
	 * Make a term double.
	 *
	 * @param int    $id       Term id.
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $name     Term name.
	 * @param string $slug     Term slug.
	 * @return object Term double.
	 */
	private function term( int $id, string $taxonomy, string $name, string $slug ): object {
		return (object) [
			'term_id'  => $id,
			'taxonomy' => $taxonomy,
			'name'     => $name,
			'slug'     => $slug,
		];
	}

	/**
	 * Seed the category taxonomy double.
	 */
	private function seedCategoryTaxonomy(): void {
		$this->taxonomies['category'] = (object) [
			'name'         => 'category',
			'public'       => true,
			'hierarchical' => true,
			'object_type'  => [ 'post' ],
			'labels'       => (object) [
				'singular_name' => 'Category',
				'name'          => 'Categories',
			],
			'label'        => 'Categories',
		];
		$this->taxHier['category']    = true;
	}

	/**
	 * Test posts front page builds no trail.
	 */
	public function test_posts_front_page_builds_no_trail(): void {
		$this->conds['front'] = true;
		$this->conds['home']  = true;

		$q = $this->makeQuery(
			[
				'is_home'       => true,
				'is_front_page' => true,
			]
		);

		$this->assertSame( [], $this->trail( $q ) );
	}

	/**
	 * Test paged posts front page appends a visible only Page N.
	 */
	public function test_paged_posts_front_page_appends_page_n(): void {
		$this->conds['front']     = true;
		$this->conds['home']      = true;
		$this->queryVars['paged'] = 2;

		$q = $this->makeQuery(
			[
				'is_home'       => true,
				'is_front_page' => true,
			]
		);

		$items = $this->trail( $q );

		$this->assertSame( [ 'Page 2' ], $this->labels( $items ) );
		$this->assertSame( '', $items[0]->url() );
		$this->assertTrue( $items[0]->schemaExcluded() );
	}

	/**
	 * Test static front page hides the trail by default.
	 */
	public function test_static_front_page_hides_trail_by_default(): void {
		$this->conds['front']             = true;
		$this->wpOptions['show_on_front'] = 'page';
		$this->wpOptions['page_on_front'] = 5;

		$q = $this->makeQuery( [ 'is_front_page' => true ] );

		$this->assertSame( [], $this->trail( $q ) );
	}

	/**
	 * Test static front page shows home when not hidden.
	 */
	public function test_static_front_page_shows_home_when_not_hidden(): void {
		$this->conds['front']             = true;
		$this->wpOptions['show_on_front'] = 'page';
		$this->wpOptions['page_on_front'] = 5;
		$this->settingsOption             = [ 'hide_on_front_page' => false ];

		$q = $this->makeQuery( [ 'is_front_page' => true ] );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/', $items[0]->url() );
	}

	/**
	 * Test blog index uses the posts page title.
	 */
	public function test_blog_index_uses_posts_page_title(): void {
		$this->conds['home']               = true;
		$this->wpOptions['page_for_posts'] = 2;
		$this->titles[2]                   = 'News';

		$q = $this->makeQuery( [ 'is_home' => true ], 2 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'News' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/?p=2', $items[1]->url() );
	}

	/**
	 * Test single post with an explicit primary taxonomy.
	 */
	public function test_single_post_with_explicit_primary_taxonomy(): void {
		$this->wpOptions['page_for_posts'] = 7;
		$this->titles[7]                   = 'News';
		$this->titles[11]                  = 'Hello World';
		$this->settingsOption              = [ 'primary_taxonomy_post' => 'category' ];
		$this->objectTaxes['post']         = [
			'category' => true,
			'post_tag' => true,
		];
		$this->queriedObject               = $this->post( 11, 'post', 'Hello World' );
		$this->termsMap['11:category']     = [ $this->term( 3, 'category', 'Tech', 'tech' ) ];
		$this->termsById[3]                = $this->term( 3, 'category', 'Tech', 'tech' );
		$this->termAncestors[3]            = [ 2 ];
		$this->termsById[2]                = $this->term( 2, 'category', 'Gadgets', 'gadgets' );

		$this->seedCategoryTaxonomy();

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'News', 'Gadgets', 'Tech', 'Hello World' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/go/gadgets/', $items[2]->url() );
		$this->assertSame( 'https://example.com/go/tech/', $items[3]->url() );
		$this->assertSame( 'https://example.com/?p=11', $items[4]->url() );
	}

	/**
	 * Test single post falls back when the mapping names nothing usable.
	 */
	public function test_single_post_falls_back_when_mapping_unusable(): void {
		$this->titles[11]              = 'Hello World';
		$this->settingsOption          = [ 'primary_taxonomy_post' => 'nope' ];
		$this->objectTaxes['post']     = [ 'category' => true ];
		$this->queriedObject           = $this->post( 11, 'post', 'Hello World' );
		$this->termsMap['11:category'] = [ $this->term( 3, 'category', 'Tech', 'tech' ) ];
		$this->termAncestors[3]        = [];

		$this->seedCategoryTaxonomy();

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Tech', 'Hello World' ], $this->labels( $items ) );
	}

	/**
	 * Test hierarchical page walks ancestors root first.
	 */
	public function test_hierarchical_page_walks_ancestors_root_first(): void {
		$this->queriedObject     = $this->post( 20, 'page', 'Child', 10 );
		$this->posts[10]         = $this->post( 10, 'page', 'Parent' );
		$this->postAncestors[20] = [ 10 ];
		$this->hierTypes['page'] = true;

		$q = $this->makeQuery( [ 'is_singular' => true ], 20 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Parent', 'Child' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/?p=10', $items[1]->url() );
		$this->assertSame( 'https://example.com/?p=20', $items[2]->url() );
	}

	/**
	 * Test hierarchical CPT includes its archive crumb.
	 */
	public function test_hierarchical_cpt_includes_archive_crumb(): void {
		$this->queriedObject     = $this->post( 30, 'book', 'Dune' );
		$this->posts[31]         = $this->post( 31, 'book', 'Series' );
		$this->postAncestors[30] = [ 31 ];
		$this->hierTypes['book'] = true;
		$this->postTypes['book'] = (object) [
			'name'        => 'book',
			'label'       => 'Books',
			'labels'      => (object) [ 'name' => 'Books' ],
			'has_archive' => true,
		];

		$q = $this->makeQuery( [ 'is_singular' => true ], 30 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Books', 'Series', 'Dune' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/type/book/', $items[1]->url() );
	}

	/**
	 * Test non hierarchical CPT uses one term branch.
	 */
	public function test_non_hierarchical_cpt_uses_one_term_branch(): void {
		$this->queriedObject        = $this->post( 40, 'movie', 'Film' );
		$this->hierTypes['movie']   = false;
		$this->postTypes['movie']   = (object) [
			'name'        => 'movie',
			'label'       => 'Movies',
			'labels'      => (object) [ 'name' => 'Movies' ],
			'has_archive' => true,
		];
		$this->objectTaxes['movie'] = [ 'genre' => true ];
		$this->taxonomies['genre']  = (object) [
			'name'        => 'genre',
			'public'      => true,
			'object_type' => [ 'movie' ],
		];
		$this->taxHier['genre']     = false;
		$this->termsMap['40:genre'] = [ $this->term( 6, 'genre', 'Action', 'action' ) ];

		$q = $this->makeQuery( [ 'is_singular' => true ], 40 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Movies', 'Action', 'Film' ], $this->labels( $items ) );
	}

	/**
	 * Test CPT archive uses the post type object label.
	 */
	public function test_cpt_archive_uses_post_type_label(): void {
		$this->conds['pt_archive']    = true;
		$this->queryVars['post_type'] = 'book';
		$this->postTypes['book']      = (object) [
			'name'        => 'book',
			'label'       => 'Books',
			'labels'      => (object) [ 'name' => 'Books' ],
			'has_archive' => true,
		];

		$q = $this->makeQuery(
			[
				'is_archive'           => true,
				'is_post_type_archive' => true,
			]
		);

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Books' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/type/book/', $items[1]->url() );
	}

	/**
	 * Test category archive includes the blog page crumb.
	 */
	public function test_category_archive_includes_blog_page_crumb(): void {
		$this->wpOptions['page_for_posts'] = 7;
		$this->titles[7]                   = 'News';
		$this->queriedObject               = $this->term( 5, 'category', 'Tech', 'tech' );
		$this->termAncestors[5]            = [];

		$this->seedCategoryTaxonomy();

		$q = $this->makeQuery( [ 'is_category' => true ], 5 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'News', 'Tech' ], $this->labels( $items ) );
	}

	/**
	 * Test tag archive builds its trail.
	 */
	public function test_tag_archive_builds_trail(): void {
		$this->wpOptions['page_for_posts'] = 7;
		$this->titles[7]                   = 'News';
		$this->queriedObject               = $this->term( 9, 'post_tag', 'WordPress', 'WordPress' );
		$this->taxonomies['post_tag']      = (object) [
			'name'        => 'post_tag',
			'public'      => true,
			'object_type' => [ 'post' ],
		];
		$this->taxHier['post_tag']         = false;

		$q = $this->makeQuery( [ 'is_tag' => true ], 9 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'News', 'WordPress' ], $this->labels( $items ) );
	}

	/**
	 * Test custom taxonomy archive adds its taxonomy name crumb.
	 */
	public function test_custom_taxonomy_archive_adds_taxonomy_name_crumb(): void {
		$this->queriedObject       = $this->term( 6, 'genre', 'Action', 'action' );
		$this->taxonomies['genre'] = (object) [
			'name'        => 'genre',
			'public'      => true,
			'object_type' => [ 'movie' ],
			'labels'      => (object) [
				'singular_name' => 'Genre',
				'name'          => 'Genres',
			],
			'label'       => 'Genres',
		];
		$this->taxHier['genre']    = false;

		$q = $this->makeQuery( [ 'is_tax' => true ], 6 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Genre', 'Action' ], $this->labels( $items ) );
		$this->assertSame( '', $items[1]->url() );
	}

	/**
	 * Test hierarchical term ancestors walk root first.
	 */
	public function test_hierarchical_term_ancestors_walk_root_first(): void {
		$this->queriedObject       = $this->term( 9, 'genre', 'Sci-Fi', 'sci-fi' );
		$this->taxonomies['genre'] = (object) [
			'name'        => 'genre',
			'public'      => true,
			'object_type' => [ 'movie' ],
			'labels'      => (object) [
				'singular_name' => 'Genre',
				'name'          => 'Genres',
			],
			'label'       => 'Genres',
		];
		$this->taxHier['genre']    = true;
		$this->termAncestors[9]    = [ 8 ];
		$this->termsById[8]        = $this->term( 8, 'genre', 'Fiction', 'fiction' );

		$q = $this->makeQuery( [ 'is_tax' => true ], 9 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Genre', 'Fiction', 'Sci-Fi' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/go/fiction/', $items[2]->url() );
	}

	/**
	 * Test hidden ancestors skip the term chain.
	 */
	public function test_hidden_ancestors_skip_term_chain(): void {
		$this->settingsOption      = [ 'show_ancestors' => false ];
		$this->queriedObject       = $this->term( 9, 'genre', 'Sci-Fi', 'sci-fi' );
		$this->taxonomies['genre'] = (object) [
			'name'        => 'genre',
			'public'      => true,
			'object_type' => [ 'movie' ],
			'labels'      => (object) [
				'singular_name' => 'Genre',
				'name'          => 'Genres',
			],
			'label'       => 'Genres',
		];
		$this->taxHier['genre']    = true;
		$this->termAncestors[9]    = [ 8 ];
		$this->termsById[8]        = $this->term( 8, 'genre', 'Fiction', 'fiction' );

		$q = $this->makeQuery( [ 'is_tax' => true ], 9 );

		$this->assertSame( [ 'Home', 'Genre', 'Sci-Fi' ], $this->labels( $this->trail( $q ) ) );
	}

	/**
	 * Test author archive uses the display name.
	 */
	public function test_author_archive_uses_display_name(): void {
		$this->conds['author'] = true;
		$this->queriedObject   = (object) [
			'ID'           => 4,
			'display_name' => 'Jane',
		];

		$q = $this->makeQuery(
			[
				'is_archive' => true,
				'is_author'  => true,
			],
			4
		);

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Jane' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/author/4/', $items[1]->url() );
	}

	/**
	 * Test year archive builds its trail.
	 */
	public function test_year_archive_builds_trail(): void {
		$this->conds['date']     = true;
		$this->queryVars['year'] = 2024;

		$q = $this->makeQuery(
			[
				'is_archive' => true,
				'is_date'    => true,
			]
		);

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', '2024' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/2024/', $items[1]->url() );
	}

	/**
	 * Test month archive chains year to month.
	 */
	public function test_month_archive_chains_year_to_month(): void {
		$this->conds['date']         = true;
		$this->queryVars['year']     = 2024;
		$this->queryVars['monthnum'] = 5;

		$q = $this->makeQuery(
			[
				'is_archive' => true,
				'is_date'    => true,
			]
		);

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', '2024', 'May 2024' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/2024/5/', $items[2]->url() );
	}

	/**
	 * Test day archive chains year to month to day.
	 */
	public function test_day_archive_chains_year_to_month_to_day(): void {
		$this->conds['date']         = true;
		$this->queryVars['year']     = 2024;
		$this->queryVars['monthnum'] = 5;
		$this->queryVars['day']      = 4;

		$q = $this->makeQuery(
			[
				'is_archive' => true,
				'is_date'    => true,
			]
		);

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', '2024', 'May 2024', 'May 4, 2024' ], $this->labels( $items ) );
	}

	/**
	 * Test search builds a linked crumb.
	 */
	public function test_search_builds_linked_crumb(): void {
		$this->conds['search'] = true;
		$this->searchQuery     = 'hello';

		$q = $this->makeQuery( [ 'is_search' => true ] );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Search results for "hello"' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/?s=hello', $items[1]->url() );
	}

	/**
	 * Test 404 builds an unlinked crumb.
	 */
	public function test_404_builds_unlinked_crumb(): void {
		$this->conds['404'] = true;

		$q = $this->makeQuery( [ 'is_404' => true ] );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Page not found' ], $this->labels( $items ) );
		$this->assertSame( '', $items[1]->url() );
	}

	/**
	 * Test attachment with a parent follows the parent trail.
	 */
	public function test_attachment_with_parent_follows_parent_trail(): void {
		$this->queriedObject     = $this->post( 50, 'attachment', 'Photo', 11 );
		$this->posts[11]         = $this->post( 11, 'post', 'Parent Post' );
		$this->postAncestors[11] = [];

		$q = $this->makeQuery( [ 'is_singular' => true ], 50 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Parent Post', 'Photo' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/?p=11', $items[1]->url() );
	}

	/**
	 * Test parentless attachment falls back to home plus title.
	 */
	public function test_parentless_attachment_falls_back_to_home_plus_title(): void {
		$this->queriedObject = $this->post( 50, 'attachment', 'Photo', 0 );

		$q = $this->makeQuery( [ 'is_singular' => true ], 50 );

		$this->assertSame( [ 'Home', 'Photo' ], $this->labels( $this->trail( $q ) ) );
	}

	/**
	 * Test paged archive appends a visible only Page N.
	 */
	public function test_paged_archive_appends_visible_only_page_n(): void {
		$this->queryVars['paged'] = 3;
		$this->queriedObject      = $this->term( 5, 'category', 'Tech', 'tech' );
		$this->termAncestors[5]   = [];

		$this->seedCategoryTaxonomy();

		$q = $this->makeQuery( [ 'is_category' => true ], 5 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Tech', 'Page 3' ], $this->labels( $items ) );
		$this->assertSame( '', $items[2]->url() );
		$this->assertTrue( $items[2]->schemaExcluded() );
	}

	/**
	 * Test paginated singular appends a visible only Page N.
	 */
	public function test_paginated_singular_appends_visible_only_page_n(): void {
		$this->queryVars['page'] = 2;
		$this->titles[11]        = 'Hello World';
		$this->queriedObject     = $this->post( 11, 'post', 'Hello World' );

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Hello World', 'Page 2' ], $this->labels( $items ) );
		$this->assertTrue( $items[2]->schemaExcluded() );
	}

	/**
	 * Test comment pagination appends its label.
	 */
	public function test_comment_pagination_appends_its_label(): void {
		$this->queryVars['cpage'] = 2;
		$this->titles[11]         = 'Hello World';
		$this->queriedObject      = $this->post( 11, 'post', 'Hello World' );

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Hello World', 'Comments Page 2' ], $this->labels( $items ) );
		$this->assertTrue( $items[2]->schemaExcluded() );
	}

	/**
	 * Test untitled objects fall back to a translatable label.
	 */
	public function test_untitled_objects_fall_back(): void {
		$this->queriedObject = $this->post( 60, 'post', '' );

		$q = $this->makeQuery( [ 'is_singular' => true ], 60 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', '(no title)' ], $this->labels( $items ) );
	}

	/**
	 * Test missing ancestors are skipped.
	 */
	public function test_missing_ancestors_are_skipped(): void {
		$this->queriedObject     = $this->post( 20, 'page', 'Child', 10 );
		$this->postAncestors[20] = [ 999 ];
		$this->hierTypes['page'] = true;

		$q = $this->makeQuery( [ 'is_singular' => true ], 20 );

		$this->assertSame( [ 'Home', 'Child' ], $this->labels( $this->trail( $q ) ) );
	}

	/**
	 * Test duplicate consecutive items collapse.
	 */
	public function test_duplicate_consecutive_items_collapse(): void {
		$this->wpOptions['page_for_posts'] = 7;
		$this->titles[7]                   = 'Tech';
		$this->queriedObject               = $this->term( 5, 'category', 'Tech', 'tech' );
		$this->termsById[5]                = $this->term( 5, 'category', 'Tech', 'tech' );
		$this->termAncestors[5]            = [];
		$this->termLinkUrls['tech']        = 'https://example.com/?p=7';

		$this->seedCategoryTaxonomy();

		$q = $this->makeQuery( [ 'is_category' => true ], 5 );

		$this->assertSame( [ 'Home', 'Tech' ], $this->labels( $this->trail( $q ) ) );
	}

	/**
	 * Test invalid contexts build nothing.
	 */
	public function test_invalid_contexts_build_nothing(): void {
		$q = $this->makeQuery();

		$this->assertSame( [], $this->trail( $q ) );
	}

	/**
	 * Test per object breadcrumb titles win over native titles.
	 */
	public function test_per_object_breadcrumb_titles_win(): void {
		$this->titles[11]    = 'Hello World';
		$this->postMeta      = [
			'flags' => [ 'breadcrumb_title' => 'Custom Label' ],
		];
		$this->queriedObject = $this->post( 11, 'post', 'Hello World' );

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Custom Label' ], $this->labels( $items ) );
	}

	/**
	 * Test hidden home drops the home crumb.
	 */
	public function test_hidden_home_drops_home_crumb(): void {
		$this->settingsOption    = [ 'show_home' => false ];
		$this->queriedObject     = $this->post( 20, 'page', 'Child', 10 );
		$this->posts[10]         = $this->post( 10, 'page', 'Parent' );
		$this->postAncestors[20] = [ 10 ];
		$this->hierTypes['page'] = true;

		$q = $this->makeQuery( [ 'is_singular' => true ], 20 );

		$this->assertSame( [ 'Parent', 'Child' ], $this->labels( $this->trail( $q ) ) );
	}

	/**
	 * Test hidden current drops the current crumb but keeps pagination.
	 */
	public function test_hidden_current_drops_current_but_keeps_pagination(): void {
		$this->settingsOption     = [ 'show_current' => false ];
		$this->queryVars['paged'] = 2;
		$this->queriedObject      = $this->term( 5, 'category', 'Tech', 'tech' );
		$this->termAncestors[5]   = [];

		$this->seedCategoryTaxonomy();

		$q = $this->makeQuery( [ 'is_category' => true ], 5 );

		$this->assertSame( [ 'Home', 'Page 2' ], $this->labels( $this->trail( $q ) ) );
	}

	/**
	 * Test a representative build adds no breadcrumb specific query.
	 */
	public function test_representative_build_adds_no_query(): void {
		$counter = new BreadcrumbsCountingWpdb();

		$GLOBALS['wpdb'] = $counter; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the counting wpdb double, restored in tearDown.

		try {
			$this->titles[11]              = 'Hello World';
			$this->settingsOption          = [ 'primary_taxonomy_post' => 'category' ];
			$this->objectTaxes['post']     = [ 'category' => true ];
			$this->queriedObject           = $this->post( 11, 'post', 'Hello World' );
			$this->termsMap['11:category'] = [ $this->term( 3, 'category', 'Tech', 'tech' ) ];
			$this->termAncestors[3]        = [];

			$this->seedCategoryTaxonomy();

			$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

			$items = $this->trail( $q );

			$this->assertSame( [ 'Home', 'Tech', 'Hello World' ], $this->labels( $items ) );
			$this->assertSame( 0, $counter->count );
		} finally {
			unset( $GLOBALS['wpdb'] );
		}
	}

	/**
	 * Seed a post with two usable taxonomies for filter tests.
	 */
	private function seedTwoTaxonomyPost(): void {
		$this->titles[11]              = 'Hello World';
		$this->settingsOption          = [ 'primary_taxonomy_post' => 'category' ];
		$this->objectTaxes['post']     = [
			'category' => true,
			'post_tag' => true,
		];
		$this->queriedObject           = $this->post( 11, 'post', 'Hello World' );
		$this->termsMap['11:category'] = [ $this->term( 3, 'category', 'Tech', 'tech' ) ];
		$this->termsMap['11:post_tag'] = [ $this->term( 9, 'post_tag', 'Tagged', 'tagged' ) ];
		$this->termsById[9]            = $this->term( 9, 'post_tag', 'Tagged', 'tagged' );
		$this->termAncestors[3]        = [];
		$this->taxonomies['post_tag']  = (object) [
			'name'        => 'post_tag',
			'public'      => true,
			'object_type' => [ 'post' ],
		];
		$this->taxHier['post_tag']     = false;

		$this->seedCategoryTaxonomy();
	}

	/**
	 * Test the post type settings filter can switch the term branch.
	 */
	public function test_post_type_settings_filter_switches_term_branch(): void {
		$this->seedTwoTaxonomyPost();
		$this->postTypeFilter = static fn ( array $config ): array => array_merge( $config, [ 'primary_taxonomy' => 'post_tag' ] );

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Tagged', 'Hello World' ], $this->labels( $items ) );
		$this->assertSame( 'https://example.com/go/tagged/', $items[1]->url() );
	}

	/**
	 * Test the post type settings filter with an unknown taxonomy is ignored.
	 */
	public function test_post_type_settings_filter_invalid_taxonomy_ignored(): void {
		$this->seedTwoTaxonomyPost();
		$this->postTypeFilter = static fn ( array $config ): array => array_merge( $config, [ 'primary_taxonomy' => 'nope' ] );

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Tech', 'Hello World' ], $this->labels( $items ) );
	}

	/**
	 * Test the post type settings filter cannot bypass validation with markup.
	 */
	public function test_post_type_settings_filter_malicious_value_ignored(): void {
		$this->seedTwoTaxonomyPost();
		$this->postTypeFilter = static fn ( array $config ): array => array_merge( $config, [ 'primary_taxonomy' => '<script>post_tag</script>' ] );

		$q = $this->makeQuery( [ 'is_singular' => true ], 11 );

		$items = $this->trail( $q );

		$this->assertSame( [ 'Home', 'Tech', 'Hello World' ], $this->labels( $items ) );

		foreach ( $items as $item ) {
			$this->assertStringNotContainsString( '<script>', $item->label() );
		}
	}

	/**
	 * Test visible and schema items stay identical under an active filter.
	 */
	public function test_filtered_taxonomy_keeps_visible_and_schema_identical(): void {
		$this->seedTwoTaxonomyPost();
		$this->postTypeFilter = static fn ( array $config ): array => array_merge( $config, [ 'primary_taxonomy' => 'post_tag' ] );

		$q        = $this->makeQuery( [ 'is_singular' => true ], 11 );
		$ctx      = new Context( $q, new SettingsStore() );
		$settings = new BreadcrumbsSettings();
		$items    = ( new TrailBuilder( $ctx, $settings ) )->build();
		$schema   = ( new BreadcrumbsModule( null, $settings ) )->filterBreadcrumbTrail( [], $ctx );

		$this->assertSame( [ 'Home', 'Tagged', 'Hello World' ], $this->labels( $items ) );
		$this->assertSame( $this->labels( $items ), array_map( static fn ( array $crumb ): string => $crumb['name'], $schema ) );
		$this->assertSame(
			array_map( static fn ( Item $item ): string => $item->url(), $items ),
			array_map( static fn ( array $crumb ): string => $crumb['url'], $schema )
		);
	}
}
