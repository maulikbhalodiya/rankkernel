<?php
/**
 * Breadcrumbs performance tests, measured query behavior.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Breadcrumbs\blocks\BreadcrumbsBlock;
use RankKernel\Modules\Breadcrumbs\BreadcrumbsModule;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Settings\SettingsStore;
use WP_Query;

use function RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs;

/**
 * Breadcrumbs Performance Test.
 *
 * Measured behavior on representative contexts with a counting fake
 * wpdb: building a single post trail and a term archive trail issues
 * zero breadcrumb specific database calls, and rendering happens only
 * when the trail is requested through the template tag, the
 * shortcode, the block, or the schema adapter. This describes the
 * measured harness behavior with warm caches, never a promise that
 * cold object caches cannot query elsewhere in WordPress core.
 */
final class BreadcrumbsPerformanceTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Counting database double.
	 *
	 * @var BreadcrumbsCountingWpdb
	 */
	private BreadcrumbsCountingWpdb $wpdb;

	/**
	 * Queried object double.
	 *
	 * @var object|null
	 */
	private ?object $queriedObject = null;

	/**
	 * Taxonomy objects by slug.
	 *
	 * @var array<string, object>
	 */
	private array $taxonomies = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		require_once dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/functions.php';
		require_once dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/template-tags.php';

		$this->wpdb          = new BreadcrumbsCountingWpdb();
		$GLOBALS['wpdb']     = $this->wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the counting database double the suite measures.
		$this->taxonomies    = [];
		$this->queriedObject = null;

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $url ): string => $url );
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', $v ) ) );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_404' )->justReturn( false );
		Functions\when( 'is_post_type_archive' )->justReturn( false );
		Functions\when( 'is_author' )->justReturn( false );
		Functions\when( 'is_date' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'get_the_title' )->justReturn( 'Hello World' );
		Functions\when( 'get_permalink' )->alias(
			static function ( mixed $id ): string {
				if ( is_object( $id ) && isset( $id->ID ) ) {
					$id = $id->ID;
				}

				return 'https://example.com/?p=' . (int) $id;
			}
		);
		Functions\when( 'get_queried_object' )->alias(
			function (): mixed {
				return $this->queriedObject;
			}
		);
		Functions\when( 'get_post_ancestors' )->justReturn( [] );
		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'wp_get_post_parent_id' )->justReturn( 0 );
		Functions\when( 'get_ancestors' )->justReturn( [] );
		Functions\when( 'get_term' )->justReturn( null );
		Functions\when( 'get_the_terms' )->justReturn( [] );
		Functions\when( 'get_term_link' )->justReturn( 'https://example.com/go/term/' );
		Functions\when( 'get_taxonomy' )->alias(
			function ( string $taxonomy ): mixed {
				return $this->taxonomies[ $taxonomy ] ?? false;
			}
		);
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'is_taxonomy_hierarchical' )->justReturn( false );
		Functions\when( 'is_post_type_hierarchical' )->justReturn( false );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_post_type_object' )->justReturn( false );
		Functions\when( 'get_post_type_archive_link' )->justReturn( '' );
		Functions\when( 'get_search_link' )->justReturn( '' );
		Functions\when( 'get_search_query' )->justReturn( '' );
		Functions\when( 'get_author_posts_url' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'number_format_i18n' )->alias( static fn ( mixed $n ): string => (string) $n );
		Functions\when( 'shortcode_atts' )->alias(
			static function ( array $defaults, mixed $atts ): array {
				if ( ! is_array( $atts ) ) {
					$atts = [];
				}

				return array_merge( $defaults, $atts );
			}
		);
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'get_block_wrapper_attributes' )->justReturn( 'class="wp-block-rankkernel-breadcrumbs"' );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test removes the counting database double.
		unset( $GLOBALS['wp_query'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test resets the global query double.

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
			'is_singular'           => false,
			'is_search'             => false,
			'is_404'                => false,
			'is_feed'               => false,
			'is_preview'            => false,
			'is_category'           => false,
			'is_tag'                => false,
			'is_tax'                => false,
			'is_home'               => false,
			'is_front_page'         => false,
			'is_archive'            => false,
			'get_queried_object_id' => $id,
			'get'                   => 0,
		];

		foreach ( array_merge( $defaults, $flags ) as $method => $value ) {
			$q->shouldReceive( $method )->andReturn( $value )->byDefault();
		}

		$q->shouldReceive( 'get_queried_object' )->andReturnNull()->byDefault();

		return $q;
	}

	/**
	 * Point the fixture at a singular post.
	 *
	 * @param int $id Queried object id.
	 * @return WP_Query Query double.
	 */
	private function useSingularPost( int $id = 11 ): WP_Query {
		$this->queriedObject = (object) [
			'ID'         => $id,
			'post_type'  => 'post',
			'post_title' => 'Hello World',
		];

		$query = $this->makeQuery( [ 'is_singular' => true ], $id );

		$GLOBALS['wp_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test sets the global query double the production code reads.

		return $query;
	}

	/**
	 * Point the fixture at a category archive.
	 *
	 * @param int $id Term id.
	 * @return WP_Query Query double.
	 */
	private function useTermArchive( int $id = 5 ): WP_Query {
		$this->queriedObject          = (object) [
			'term_id'  => $id,
			'taxonomy' => 'category',
			'name'     => 'Tech',
			'slug'     => 'tech',
		];
		$this->taxonomies['category'] = (object) [
			'name'        => 'category',
			'public'      => true,
			'object_type' => [ 'post' ],
		];

		$query = $this->makeQuery( [ 'is_category' => true ], $id );

		$GLOBALS['wp_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test sets the global query double the production code reads.

		return $query;
	}

	/**
	 * Test a single post build issues zero breadcrumb database calls.
	 */
	public function test_single_post_build_issues_zero_breadcrumb_queries(): void {
		$query = $this->useSingularPost();
		$ctx   = new Context( $query, new SettingsStore() );

		$this->assertSame( 0, $this->wpdb->count );

		$items = ( new BreadcrumbsModule() )->buildTrail( $ctx );

		$this->assertNotEmpty( $items );
		$this->assertSame( 0, $this->wpdb->count );
	}

	/**
	 * Test a term archive build issues zero breadcrumb database calls.
	 */
	public function test_term_archive_build_issues_zero_breadcrumb_queries(): void {
		$query = $this->useTermArchive();
		$ctx   = new Context( $query, new SettingsStore() );

		$this->assertSame( 0, $this->wpdb->count );

		$items = ( new BreadcrumbsModule() )->buildTrail( $ctx );

		$this->assertNotEmpty( $items );
		$this->assertSame( 0, $this->wpdb->count );
	}

	/**
	 * Test registering the module performs no trail work.
	 */
	public function test_register_performs_no_trail_work(): void {
		$query = $this->useSingularPost();
		$ctx   = new Context( $query, new SettingsStore() );

		( new BreadcrumbsModule() )->register();

		$this->assertSame( 0, $this->wpdb->count );
		$this->assertNotEmpty( ( new BreadcrumbsModule() )->buildTrail( $ctx ) );
		$this->assertSame( 0, $this->wpdb->count );
	}

	/**
	 * Test the template tag renders only when called.
	 */
	public function test_template_tag_renders_only_when_called(): void {
		$this->useSingularPost();

		$this->assertSame( 0, $this->wpdb->count );

		$html = rankkernel_get_breadcrumbs();

		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $html );
		$this->assertSame( 0, $this->wpdb->count );
	}

	/**
	 * Test the shortcode renders only when called.
	 */
	public function test_shortcode_renders_only_when_called(): void {
		$this->useSingularPost();

		$this->assertSame( 0, $this->wpdb->count );

		$html = ( new BreadcrumbsModule() )->renderShortcode( [] );

		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $html );
		$this->assertSame( 0, $this->wpdb->count );
	}

	/**
	 * Test the block renders only when called.
	 */
	public function test_block_renders_only_when_called(): void {
		$this->useSingularPost();

		$this->assertSame( 0, $this->wpdb->count );

		$html = ( new BreadcrumbsBlock() )->render( [] );

		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $html );
		$this->assertSame( 0, $this->wpdb->count );
	}

	/**
	 * Test the schema adapter builds only when the filter runs.
	 */
	public function test_schema_adapter_builds_only_when_filter_runs(): void {
		$query  = $this->useSingularPost();
		$ctx    = new Context( $query, new SettingsStore() );
		$module = new BreadcrumbsModule();

		$this->assertSame( 0, $this->wpdb->count );

		$trail = $module->filterBreadcrumbTrail( [], $ctx );

		$this->assertNotEmpty( $trail );
		$this->assertSame( 0, $this->wpdb->count );
	}
}
