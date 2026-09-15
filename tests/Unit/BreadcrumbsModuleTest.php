<?php
/**
 * Breadcrumbs module gating and schema adapter tests.
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
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Metadata\MetadataModule;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleManager;
use RankKernel\Modules\Schema\Pieces\BreadcrumbPiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Breadcrumbs Module Test.
 */
final class BreadcrumbsModuleTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Registered filter hooks.
	 *
	 * @var array<int, array{hook: string, callback: mixed}>
	 */
	private array $filters = [];

	/**
	 * Registered action hooks.
	 *
	 * @var array<int, string>
	 */
	private array $actions = [];

	/**
	 * Queried object double.
	 *
	 * @var object|null
	 */
	private ?object $queriedObject = null;

	/**
	 * Enabled module ids for the enable map stub.
	 *
	 * @var string[]
	 */
	private array $enabledModules = [];

	/**
	 * Query variables.
	 *
	 * @var array<string, mixed>
	 */
	private array $queryVars = [];

	/**
	 * Taxonomy objects by slug.
	 *
	 * @var array<string, object>
	 */
	private array $taxonomies = [];

	/**
	 * Update option call count.
	 *
	 * @var int
	 */
	private int $updateCount = 0;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_404' )->justReturn( false );
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
				if ( 'rankkernel_modules' === $key ) {
					return $this->enabledModules;
				}

				if ( 'rankkernel_breadcrumbs_settings' === $key ) {
					return [];
				}

				return $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function (): bool {
				++$this->updateCount;

				return true;
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', $v ) ) );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'get_the_title' )->justReturn( '' );
		// Note: single_post_title is intentionally never stubbed here, see the
		// TrailBuilder suite note on process wide Brain Monkey definitions.
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
		Functions\when( 'get_term_link' )->alias(
			static function ( mixed $term ): string {
				if ( is_object( $term ) && isset( $term->slug ) ) {
					return 'https://example.com/go/' . $term->slug . '/';
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
		Functions\when( 'number_format_i18n' )->alias( static fn ( mixed $n ): string => (string) $n );
		Functions\when( 'add_action' )->alias(
			function ( string $hook ): bool {
				$this->actions[] = $hook;

				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			function ( string $hook, mixed $callback ): bool {
				$this->filters[] = [
					'hook'     => $hook,
					'callback' => $callback,
				];

				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				return $value;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'register_meta' )->justReturn( true );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Boot the module through a manager with the given enabled ids.
	 *
	 * @param string[] $enabled Enabled module ids.
	 * @return BreadcrumbsModule Module instance.
	 */
	private function bootWith( array $enabled ): BreadcrumbsModule {
		$this->enabledModules = $enabled;

		$map      = new ModuleEnableMap();
		$manager  = new ModuleManager( $map );
		$metadata = new MetadataModule( new SettingsStore(), $map );
		$module   = new BreadcrumbsModule( $map );

		$manager->register( $metadata );
		$manager->register( $module );
		$manager->evaluateAll();
		$manager->bootEnabled();

		return $module;
	}

	/**
	 * Make a singular query double.
	 *
	 * @param int $id Queried object id.
	 * @return WP_Query The result.
	 */
	private function makeSingularQuery( int $id ): WP_Query {
		$q = Mockery::mock( WP_Query::class );
		$q->shouldReceive( 'is_singular' )->andReturn( true )->byDefault();
		$q->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'get_queried_object_id' )->andReturn( $id )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( '' )->byDefault();
		$q->shouldReceive( 'get_queried_object' )->andReturnNull()->byDefault();

		return $q;
	}

	/**
	 * Make a context for a query.
	 *
	 * @param WP_Query $q Query double.
	 * @return Context The result.
	 */
	private function makeContext( WP_Query $q ): Context {
		return new Context( $q, new SettingsStore() );
	}

	/**
	 * Test module identity.
	 */
	public function test_module_identity(): void {
		$module = new BreadcrumbsModule();

		$this->assertSame( 'breadcrumbs', $module->getId() );
		$this->assertSame( 'Breadcrumbs', $module->getName() );
		$this->assertSame( [ 'metadata' ], $module->dependsOn() );
		$this->assertGreaterThan( 30, $module->getPriority() );
	}

	/**
	 * Test disabled module registers zero hooks.
	 */
	public function test_disabled_module_registers_zero_hooks(): void {
		$module = $this->bootWith( [] );

		$this->assertFalse( $module->isEnabled() );
		$this->assertSame( [], $this->actions );
		$this->assertSame( [], $this->filters );
		$this->assertSame( 0, $this->updateCount );
	}

	/**
	 * Test disabled module keeps the fallback piece behavior.
	 */
	public function test_disabled_module_keeps_fallback_piece_behavior(): void {
		$this->bootWith( [] );

		$this->queriedObject = (object) [
			'ID'         => 11,
			'post_type'  => 'post',
			'post_title' => 'Hello World',
		];

		$ctx   = $this->makeContext( $this->makeSingularQuery( 11 ) );
		$piece = new BreadcrumbPiece( new SettingsStore() );

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$node = $piece->build( $ctx );

		$this->assertSame( 'BreadcrumbList', $node['@type'] );
		$this->assertCount( 2, $node['itemListElement'] );
		$this->assertSame( 'Test Site', $node['itemListElement'][0]['name'] );
	}

	/**
	 * Test enabled module registers the schema trail filter only.
	 */
	public function test_enabled_module_registers_schema_trail_filter_only(): void {
		$module = $this->bootWith( [ 'metadata', 'schema', 'breadcrumbs' ] );

		$this->assertTrue( $module->isEnabled() );
		$this->assertSame( [ 'wp_head' ], $this->actions );

		$trailFilters = array_values(
			array_filter(
				$this->filters,
				static fn ( array $entry ): bool => 'rankkernel/schema/breadcrumb_trail' === $entry['hook']
			)
		);

		$this->assertCount( 1, $trailFilters );
		$this->assertSame( [ $module, 'filterBreadcrumbTrail' ], $trailFilters[0]['callback'] );
	}

	/**
	 * Test adapter maps items to name and url entries.
	 */
	public function test_adapter_maps_items_to_name_and_url(): void {
		$module = $this->bootWith( [ 'metadata', 'schema', 'breadcrumbs' ] );

		$this->queriedObject = (object) [
			'ID'         => 11,
			'post_type'  => 'post',
			'post_title' => 'Hello World',
		];

		$ctx    = $this->makeContext( $this->makeSingularQuery( 11 ) );
		$result = $module->filterBreadcrumbTrail( [], $ctx );

		$this->assertSame(
			[
				[
					'name' => 'Home',
					'url'  => 'https://example.com/',
				],
				[
					'name' => 'Hello World',
					'url'  => 'https://example.com/?p=11',
				],
			],
			$result
		);
	}

	/**
	 * Test adapter excludes pagination items.
	 */
	public function test_adapter_excludes_pagination_items(): void {
		$module = $this->bootWith( [ 'metadata', 'schema', 'breadcrumbs' ] );

		$this->queryVars['paged']     = 3;
		$this->queriedObject          = (object) [
			'term_id'  => 5,
			'taxonomy' => 'category',
			'name'     => 'Tech',
			'slug'     => 'tech',
		];
		$this->taxonomies['category'] = (object) [
			'name'        => 'category',
			'public'      => true,
			'object_type' => [ 'post' ],
		];

		$q = Mockery::mock( WP_Query::class );
		$q->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_category' )->andReturn( true )->byDefault();
		$q->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'get_queried_object_id' )->andReturn( 5 )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( '' )->byDefault();
		$q->shouldReceive( 'get_queried_object' )->andReturnNull()->byDefault();

		$ctx    = $this->makeContext( $q );
		$result = $module->filterBreadcrumbTrail( [], $ctx );

		$this->assertSame(
			[
				[
					'name' => 'Home',
					'url'  => 'https://example.com/',
				],
				[
					'name' => 'Tech',
					'url'  => 'https://example.com/go/term/',
				],
			],
			$result
		);
	}

	/**
	 * Test adapter never emits JSON-LD.
	 */
	public function test_adapter_never_emits_json_ld(): void {
		$module = $this->bootWith( [ 'metadata', 'schema', 'breadcrumbs' ] );

		$this->queriedObject = (object) [
			'ID'         => 11,
			'post_type'  => 'post',
			'post_title' => 'Hello World',
		];

		$ctx = $this->makeContext( $this->makeSingularQuery( 11 ) );

		ob_start();

		try {
			$result = $module->filterBreadcrumbTrail( [], $ctx );
		} finally {
			$output = (string) ob_get_clean();
		}

		$this->assertSame( '', $output );

		$encoded = (string) json_encode( $result ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- native encoder keeps wp_json_encode undefined, see the single_post_title note above.

		$this->assertStringNotContainsString( 'BreadcrumbList', $encoded );
		$this->assertStringNotContainsString( 'application/ld+json', $encoded );
	}

	/**
	 * Test adapter keeps the incoming trail for empty builder output.
	 */
	public function test_adapter_keeps_incoming_trail_for_empty_output(): void {
		$module = $this->bootWith( [ 'metadata', 'schema', 'breadcrumbs' ] );

		$incoming = [
			[
				'name' => 'Home',
				'url'  => 'https://example.com/',
			],
		];

		$q      = $this->makeQueryNoContext();
		$ctx    = $this->makeContext( $q );
		$result = $module->filterBreadcrumbTrail( $incoming, $ctx );

		$this->assertSame( $incoming, $result );
	}

	/**
	 * Test adapter passes non context calls through safely.
	 */
	public function test_adapter_passes_non_context_calls_through(): void {
		$module = new BreadcrumbsModule();

		$this->assertSame( [], $module->filterBreadcrumbTrail( [], null ) );
		$this->assertSame(
			[
				[
					'name' => 'x',
					'url'  => '',
				],
			],
			$module->filterBreadcrumbTrail( [ [ 'name' => 'x' ] ], 'nope' )
		);
	}

	/**
	 * Make a query double with no recognizable context.
	 *
	 * @return WP_Query The result.
	 */
	private function makeQueryNoContext(): WP_Query {
		$q = Mockery::mock( WP_Query::class );
		$q->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'get_queried_object_id' )->andReturn( 0 )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( '' )->byDefault();
		$q->shouldReceive( 'get_queried_object' )->andReturnNull()->byDefault();

		return $q;
	}
}
