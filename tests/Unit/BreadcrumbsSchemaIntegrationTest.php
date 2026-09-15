<?php
/**
 * Breadcrumbs schema integration tests, one BreadcrumbList proof.
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
use RankKernel\Modules\Breadcrumbs\Renderer;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\Generator;
use RankKernel\Modules\Schema\Pieces\BreadcrumbPiece;
use RankKernel\Modules\Schema\Pieces\WebpagePiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Breadcrumbs Schema Integration Test.
 *
 * Builds a real Context plus the module, then proves the generated
 * graph holds exactly one BreadcrumbList with an unchanged @id, a
 * resolving WebPage reference, shared canonical items, pagination
 * excluded, 404 omitted, front page behavior following settings,
 * and first wins dedupe when a duplicate node could appear.
 */
final class BreadcrumbsSchemaIntegrationTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Global conditional flags.
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
	 * Titles by post id.
	 *
	 * @var array<int, string>
	 */
	private array $titles = [];

	/**
	 * Terms by id.
	 *
	 * @var array<int, object>
	 */
	private array $termsById = [];

	/**
	 * Taxonomy objects by slug.
	 *
	 * @var array<string, object>
	 */
	private array $taxonomies = [];

	/**
	 * Module wired into the schema trail filter, null for fallback.
	 *
	 * @var BreadcrumbsModule|null
	 */
	private ?BreadcrumbsModule $schemaModule = null;

	/**
	 * Extra graph nodes injected through the graph filter.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $graphExtra = [];

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
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $url ): string => $url );
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_front_page' )->alias( fn (): bool => ! empty( $this->conds['front'] ) );
		Functions\when( 'is_home' )->alias( fn (): bool => ! empty( $this->conds['home'] ) );
		Functions\when( 'is_search' )->alias( fn (): bool => ! empty( $this->conds['search'] ) );
		Functions\when( 'is_404' )->alias( fn (): bool => ! empty( $this->conds['404'] ) );
		Functions\when( 'is_post_type_archive' )->justReturn( false );
		Functions\when( 'is_author' )->justReturn( false );
		Functions\when( 'is_date' )->justReturn( false );
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
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $v ): string => rtrim( $v, '/' ) . '/' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
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
		Functions\when( 'get_the_date' )->justReturn( '2026-01-01T00:00:00+00:00' );
		Functions\when( 'get_the_modified_date' )->justReturn( '2026-02-01T00:00:00+00:00' );
		Functions\when( 'get_queried_object' )->alias(
			function (): mixed {
				return $this->queriedObject;
			}
		);
		Functions\when( 'get_post_ancestors' )->justReturn( [] );
		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'wp_get_post_parent_id' )->justReturn( 0 );
		Functions\when( 'get_ancestors' )->justReturn( [] );
		Functions\when( 'get_term' )->alias(
			function ( int $id ): mixed {
				return $this->termsById[ $id ] ?? null;
			}
		);
		Functions\when( 'get_the_terms' )->justReturn( [] );
		Functions\when( 'get_term_link' )->alias(
			function ( mixed $term ): string {
				if ( is_object( $term ) && isset( $term->slug ) ) {
					return 'https://example.com/go/' . (string) $term->slug . '/';
				}

				if ( is_int( $term ) && isset( $this->termsById[ $term ] ) && isset( $this->termsById[ $term ]->slug ) ) {
					return 'https://example.com/go/' . (string) $this->termsById[ $term ]->slug . '/';
				}

				return 'https://example.com/go/term/';
			}
		);
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
		Functions\when( 'single_term_title' )->justReturn( '' );
		Functions\when( 'number_format_i18n' )->alias( static fn ( mixed $n ): string => (string) $n );
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value, mixed ...$rest ): mixed {
				if ( 'rankkernel/schema/breadcrumb_trail' === $hook && null !== $this->schemaModule && isset( $rest[0] ) && $rest[0] instanceof Context ) {
					return $this->schemaModule->filterBreadcrumbTrail( $value, $rest[0] );
				}

				if ( 'rankkernel/schema/graph' === $hook && is_array( $value ) ) {
					foreach ( $this->graphExtra as $node ) {
						$value[] = $node;
					}
				}

				return $value;
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
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
	 * Make a context for a query.
	 *
	 * @param WP_Query $query Query double.
	 * @return Context The result.
	 */
	private function makeContext( WP_Query $query ): Context {
		return new Context( $query, new SettingsStore() );
	}

	/**
	 * Point the fixture at a singular post.
	 *
	 * @param int    $id    Queried object id.
	 * @param string $title Post title.
	 * @return WP_Query Query double.
	 */
	private function useSingularPost( int $id = 11, string $title = 'Hello World' ): WP_Query {
		$this->queriedObject = (object) [
			'ID'         => $id,
			'post_type'  => 'post',
			'post_title' => $title,
		];
		$this->titles[ $id ] = $title;

		return $this->makeQuery( [ 'is_singular' => true ], $id );
	}

	/**
	 * Point the fixture at a category archive.
	 *
	 * @param int    $id   Term id.
	 * @param string $name Term name.
	 * @return WP_Query Query double.
	 */
	private function useTermArchive( int $id = 5, string $name = 'Tech' ): WP_Query {
		$this->queriedObject          = (object) [
			'term_id'  => $id,
			'taxonomy' => 'category',
			'name'     => $name,
			'slug'     => 'tech',
		];
		$this->termsById[ $id ]       = $this->queriedObject;
		$this->taxonomies['category'] = (object) [
			'name'        => 'category',
			'public'      => true,
			'object_type' => [ 'post' ],
		];

		return $this->makeQuery( [ 'is_category' => true ], $id );
	}

	/**
	 * Generate a graph with the breadcrumb and webpage pieces.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed> Generated document.
	 */
	private function generateGraph( Context $ctx ): array {
		$generator = new Generator();
		$generator->register( new BreadcrumbPiece( new SettingsStore() ) );
		$generator->register( new WebpagePiece( new SettingsStore() ) );

		return $generator->generate( $ctx );
	}

	/**
	 * Collect BreadcrumbList nodes from a graph.
	 *
	 * @param array<int, array<string, mixed>> $graph Graph nodes.
	 * @return array<int, array<string, mixed>> BreadcrumbList nodes.
	 */
	private function breadcrumbNodes( array $graph ): array {
		return array_values(
			array_filter(
				$graph,
				static fn ( mixed $node ): bool => is_array( $node ) && ( $node['@type'] ?? '' ) === 'BreadcrumbList'
			)
		);
	}

	/**
	 * Test the graph holds exactly one BreadcrumbList with the module wired.
	 */
	public function test_graph_holds_exactly_one_breadcrumb_list(): void {
		$this->schemaModule = new BreadcrumbsModule();

		$ctx = $this->makeContext( $this->useSingularPost() );
		$doc = $this->generateGraph( $ctx );

		$this->assertCount( 1, $this->breadcrumbNodes( $doc['@graph'] ) );
	}

	/**
	 * Test the @id is unchanged when the module supplies the trail.
	 */
	public function test_breadcrumb_id_unchanged_with_module_trail(): void {
		$query = $this->useSingularPost();
		$ctx   = $this->makeContext( $query );

		$fallback = ( new BreadcrumbPiece( new SettingsStore() ) )->build( $ctx );

		$this->schemaModule = new BreadcrumbsModule();

		$doc   = $this->generateGraph( $ctx );
		$nodes = $this->breadcrumbNodes( $doc['@graph'] );

		$this->assertCount( 1, $nodes );
		$this->assertSame( $fallback['@id'], $nodes[0]['@id'] );
		$this->assertSame( $ctx->permalink() . '#breadcrumb', $nodes[0]['@id'] );
	}

	/**
	 * Test the WebPage breadcrumb reference is unchanged and resolves.
	 */
	public function test_webpage_breadcrumb_reference_resolves(): void {
		$this->schemaModule = new BreadcrumbsModule();

		$ctx = $this->makeContext( $this->useSingularPost() );
		$doc = $this->generateGraph( $ctx );

		$webpages = array_values(
			array_filter(
				$doc['@graph'],
				static fn ( mixed $node ): bool => is_array( $node ) && in_array( $node['@type'] ?? '', [ 'WebPage', 'CollectionPage' ], true )
			)
		);

		$this->assertCount( 1, $webpages );
		$this->assertArrayHasKey( 'breadcrumb', $webpages[0] );

		$ref = is_array( $webpages[0]['breadcrumb'] ) ? (string) ( $webpages[0]['breadcrumb']['@id'] ?? '' ) : '';

		$nodes = $this->breadcrumbNodes( $doc['@graph'] );

		$this->assertCount( 1, $nodes );
		$this->assertSame( $nodes[0]['@id'], $ref );
	}

	/**
	 * Test visible and schema trails share the canonical items.
	 */
	public function test_visible_and_schema_trails_share_canonical_items(): void {
		$module             = new BreadcrumbsModule();
		$this->schemaModule = $module;

		$ctx   = $this->makeContext( $this->useSingularPost() );
		$items = $module->buildTrail( $ctx );
		$trail = $module->filterBreadcrumbTrail( [], $ctx );

		$this->assertNotEmpty( $items );
		$this->assertCount( count( $items ), $trail );

		foreach ( $items as $index => $item ) {
			$this->assertSame( $item->label(), $trail[ $index ]['name'] );
			$this->assertSame( $item->url(), $trail[ $index ]['url'] );
		}

		$doc   = $this->generateGraph( $ctx );
		$nodes = $this->breadcrumbNodes( $doc['@graph'] );

		$this->assertCount( 1, $nodes );

		$elements = $nodes[0]['itemListElement'];

		$this->assertCount( count( $trail ), $elements );

		foreach ( $trail as $index => $crumb ) {
			$this->assertSame( $crumb['name'], $elements[ $index ]['name'] );
		}
	}

	/**
	 * Test a pagination item stays visible only, absent from schema.
	 */
	public function test_pagination_item_visible_only(): void {
		$this->queryVars['paged'] = 3;

		$module             = new BreadcrumbsModule();
		$this->schemaModule = $module;

		$ctx   = $this->makeContext( $this->useTermArchive() );
		$items = $module->buildTrail( $ctx );

		$this->assertNotEmpty( $items );

		$last = end( $items );

		$this->assertTrue( $last->schemaExcluded() );

		$trail = $module->filterBreadcrumbTrail( [], $ctx );

		$this->assertCount( count( $items ) - 1, $trail );

		$doc   = $this->generateGraph( $ctx );
		$nodes = $this->breadcrumbNodes( $doc['@graph'] );

		$this->assertCount( 1, $nodes );
		$this->assertCount( count( $trail ), $nodes[0]['itemListElement'] );

		foreach ( $nodes[0]['itemListElement'] as $element ) {
			$this->assertStringNotContainsString( 'Page', (string) $element['name'] );
		}

		$html = ( new Renderer() )->render( $items );

		$this->assertStringContainsString( 'Page', $html );
	}

	/**
	 * Test a 404 produces no BreadcrumbList node.
	 */
	public function test_404_produces_no_breadcrumb_list(): void {
		$this->conds['404'] = true;

		$module             = new BreadcrumbsModule();
		$this->schemaModule = $module;

		$ctx = $this->makeContext( $this->makeQuery( [ 'is_404' => true ] ) );
		$doc = $this->generateGraph( $ctx );

		$this->assertSame( [], $this->breadcrumbNodes( $doc['@graph'] ) );
		$this->assertFalse( ( new BreadcrumbPiece( new SettingsStore() ) )->isNeeded( $ctx ) );
	}

	/**
	 * Test front page output follows the hide on front page setting.
	 */
	public function test_front_page_follows_hide_setting(): void {
		$this->conds['front']             = true;
		$this->wpOptions['show_on_front'] = 'page';

		$hidden             = new BreadcrumbsModule();
		$this->schemaModule = $hidden;

		$ctx = $this->makeContext( $this->makeQuery( [ 'is_front_page' => true ] ) );

		$this->assertSame( [], $hidden->buildTrail( $ctx ) );

		$doc = $this->generateGraph( $ctx );

		$this->assertSame( [], $this->breadcrumbNodes( $doc['@graph'] ) );

		$this->settingsOption = [ 'hide_on_front_page' => false ];

		$shown = new BreadcrumbsModule();

		$this->assertNotEmpty( $shown->buildTrail( $ctx ) );
	}

	/**
	 * Test a duplicate BreadcrumbList keeps the first node only.
	 */
	public function test_duplicate_breadcrumb_node_keeps_first(): void {
		$this->schemaModule = new BreadcrumbsModule();

		$ctx = $this->makeContext( $this->useSingularPost() );
		$doc = $this->generateGraph( $ctx );

		$nodes = $this->breadcrumbNodes( $doc['@graph'] );

		$this->assertCount( 1, $nodes );

		$this->graphExtra = [
			[
				'@type'           => 'BreadcrumbList',
				'@id'             => $nodes[0]['@id'],
				'itemListElement' => [
					[
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => 'Injected',
					],
				],
			],
		];

		$again = $this->generateGraph( $ctx );
		$kept  = $this->breadcrumbNodes( $again['@graph'] );

		$this->assertCount( 1, $kept );
		$this->assertSame( $nodes[0], $kept[0] );
	}
}
