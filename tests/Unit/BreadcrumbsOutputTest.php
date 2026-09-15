<?php
/**
 * Breadcrumbs output layer tests: renderer, API, shortcode, block, settings.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\SettingsPage;
use RankKernel\Modules\Breadcrumbs\blocks\BreadcrumbsBlock;
use RankKernel\Modules\Breadcrumbs\BreadcrumbsModule;
use RankKernel\Modules\Breadcrumbs\BreadcrumbsSettings;
use RankKernel\Modules\Breadcrumbs\Item;
use RankKernel\Modules\Breadcrumbs\Renderer;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Breadcrumbs Output Test.
 */
final class BreadcrumbsOutputTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Applied filter hooks in order.
	 *
	 * @var string[]
	 */
	private array $appliedFilters = [];

	/**
	 * Registered shortcodes.
	 *
	 * @var array<string, mixed>
	 */
	private array $shortcodes = [];

	/**
	 * Registered block types.
	 *
	 * @var array<int, array{path: string, args: mixed}>
	 */
	private array $registeredBlocks = [];

	/**
	 * Enqueued style handles.
	 *
	 * @var string[]
	 */
	private array $enqueuedStyles = [];

	/**
	 * Enqueued script handles.
	 *
	 * @var string[]
	 */
	private array $enqueuedScripts = [];

	/**
	 * Post type fixtures as slug to label.
	 *
	 * @var array<string, string>
	 */
	private array $postTypesMap = [];

	/**
	 * Taxonomy fixtures per post type as slug to label.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $objectTaxesMap = [];

	/**
	 * Override taxonomy for the post type settings filter test.
	 *
	 * @var string|null
	 */
	private ?string $postTypeSettingsOverride = null;

	/**
	 * Arguments seen by the post type settings filter.
	 *
	 * @var array<int, array{config: mixed, post_type: mixed}>
	 */
	private array $seenPostTypeFilterArgs = [];

	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Queried object double.
	 *
	 * @var object|null
	 */
	private ?object $queriedObject = null;

	/**
	 * Whether the request is the front page.
	 *
	 * @var bool
	 */
	private bool $frontPage = false;

	/**
	 * Query variables.
	 *
	 * @var array<string, mixed>
	 */
	private array $queryVars = [];

	/**
	 * Replacement separator for the args filter test.
	 *
	 * @var string|null
	 */
	private ?string $argsFilterSeparator = null;

	/**
	 * Extra items appended by the items filter test.
	 *
	 * @var array<int, mixed>
	 */
	private array $itemsFilterExtra = [];

	/**
	 * Suffix appended by the HTML filter test.
	 *
	 * @var string
	 */
	private string $htmlFilterSuffix = '';

	/**
	 * Whether the test constants were defined for this process.
	 *
	 * @var bool
	 */
	private static bool $constantsDefined = false;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		if ( ! self::$constantsDefined && ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		self::$constantsDefined = true;

		require_once dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/functions.php';
		require_once dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/template-tags.php';

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias(
			static function ( string $url ): string {
				if ( 1 === preg_match( '/^\s*javascript:/i', $url ) ) {
					return '';
				}

				return $url;
			}
		);
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => trim( strip_tags( $v, '<p><a><br><b><i><strong><em>' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating the kses allowlist with a native tag filter.
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', $v ) ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $v ): mixed => is_string( $v ) ? stripslashes( $v ) : $v );
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_front_page' )->alias( fn (): bool => $this->frontPage );
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
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'get_the_title' )->justReturn( '' );
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
				foreach ( $this->objectTaxesMap as $taxes ) {
					if ( array_key_exists( $taxonomy, $taxes ) ) {
						return (object) [
							'name'   => $taxonomy,
							'public' => true,
						];
					}
				}

				return false;
			}
		);
		Functions\when( 'get_object_taxonomies' )->alias(
			function ( string $postType, string $output = 'names' ): array {
				$taxes = $this->objectTaxesMap[ $postType ] ?? [];

				if ( 'objects' === $output ) {
					$out = [];

					foreach ( $taxes as $slug => $label ) {
						$out[ $slug ] = (object) [
							'name'   => $slug,
							'label'  => $label,
							'public' => true,
						];
					}

					return $out;
				}

				return array_keys( $taxes );
			}
		);
		Functions\when( 'is_taxonomy_hierarchical' )->justReturn( false );
		Functions\when( 'is_post_type_hierarchical' )->justReturn( false );
		Functions\when( 'get_post_types' )->alias(
			function (): array {
				$out = [];

				foreach ( $this->postTypesMap as $slug => $label ) {
					$out[ $slug ] = (object) [
						'name'  => $slug,
						'label' => $label,
					];
				}

				return $out;
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_post_type_object' )->justReturn( false );
		Functions\when( 'get_post_type_archive_link' )->justReturn( '' );
		Functions\when( 'get_search_link' )->justReturn( '' );
		Functions\when( 'get_search_query' )->justReturn( '' );
		Functions\when( 'get_author_posts_url' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'number_format_i18n' )->alias( static fn ( mixed $n ): string => (string) $n );
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value, mixed ...$rest ): mixed {
				$this->appliedFilters[] = $hook;

				if ( 'rankkernel/breadcrumbs/post_type_settings' === $hook && is_array( $value ) && null !== $this->postTypeSettingsOverride ) {
					$this->seenPostTypeFilterArgs[] = [
						'config'    => $value,
						'post_type' => $rest[0] ?? null,
					];
					$value['primary_taxonomy']      = $this->postTypeSettingsOverride;
				}

				if ( 'rankkernel/breadcrumbs/args' === $hook && is_array( $value ) && null !== $this->argsFilterSeparator ) {
					$value['separator'] = $this->argsFilterSeparator;
				}

				if ( 'rankkernel/breadcrumbs/items' === $hook && is_array( $value ) ) {
					foreach ( $this->itemsFilterExtra as $extra ) {
						$value[] = $extra;
					}
				}

				if ( 'rankkernel/breadcrumbs' === $hook && is_string( $value ) ) {
					$value .= $this->htmlFilterSuffix;
				}

				return $value;
			}
		);
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'add_shortcode' )->alias(
			function ( string $tag, mixed $callback ): bool {
				$this->shortcodes[ $tag ] = $callback;

				return true;
			}
		);
		Functions\when( 'shortcode_atts' )->alias(
			static function ( array $defaults, mixed $atts ): array {
				if ( ! is_array( $atts ) ) {
					$atts = [];
				}

				return array_merge( $defaults, $atts );
			}
		);
		Functions\when( 'register_block_type' )->alias(
			function ( string $path, mixed $args ): bool {
				$this->registeredBlocks[] = [
					'path' => $path,
					'args' => $args,
				];

				return true;
			}
		);
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->alias(
			function ( string $handle ): bool {
				$this->enqueuedScripts[] = $handle;

				return true;
			}
		);
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->alias(
			function ( string $handle ): bool {
				$this->enqueuedStyles[] = $handle;

				return true;
			}
		);
		Functions\when( 'wp_style_is' )->justReturn( false );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path ): string => 'https://example.com/wp-content/plugins/rankkernel/' . ltrim( $path, '/' ) );
		Functions\when( 'get_block_wrapper_attributes' )->justReturn( 'class="wp-block-rankkernel-breadcrumbs"' );
		Functions\when( 'checked' )->alias(
			static fn ( mixed $a, mixed $b, bool $display = true ): string => ( (string) $a === (string) $b && '' !== (string) $a ) || ( true === $a && true === $b ) ? 'checked="checked"' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress checked signature.
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-admin/' . ltrim( $p, '/' ) );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'submit_button' )->justReturn( '' );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();

		unset( $GLOBALS['wp_query'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test resets the global query double.

		$_POST                     = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
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
	 * Point the global query at a singular post double.
	 *
	 * @param int    $id    Queried object id.
	 * @param string $title Post title.
	 */
	private function useSingularPost( int $id = 11, string $title = 'Hello World' ): void {
		$this->queriedObject = (object) [
			'ID'         => $id,
			'post_type'  => 'post',
			'post_title' => $title,
		];

		$GLOBALS['wp_query'] = $this->makeSingularQuery( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test sets the global query double the production code reads.
	}

	/**
	 * Test renderer emits nav and ordered list semantics.
	 */
	public function test_renderer_emits_nav_and_ordered_list(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Home', 'https://example.com/' ),
				new Item( 'Hello World', 'https://example.com/?p=11' ),
			]
		);

		$this->assertStringContainsString( '<nav class="rk-breadcrumbs" aria-label="Breadcrumbs"', $html );
		$this->assertStringContainsString( '<ol class="rk-breadcrumbs-list">', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="rk-breadcrumbs-item' ) );
		$this->assertStringContainsString( '<a href="https://example.com/">Home</a>', $html );
	}

	/**
	 * Test renderer marks the current item with aria-current.
	 */
	public function test_renderer_marks_current_item(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Home', 'https://example.com/' ),
				new Item( 'Hello World', 'https://example.com/?p=11' ),
			]
		);

		$this->assertStringContainsString( '<span aria-current="page">Hello World</span>', $html );
		$this->assertStringNotContainsString( '<a href="https://example.com/?p=11">', $html );
	}

	/**
	 * Test renderer keeps the separator decorative.
	 */
	public function test_renderer_keeps_separator_decorative(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Home', 'https://example.com/' ),
				new Item( 'Hello World', '' ),
			],
			[ 'separator' => '>' ]
		);

		$this->assertStringContainsString( '--rk-breadcrumb-separator:&quot;&gt;&quot;', $html );
		$this->assertStringNotContainsString( 'aria-hidden', $html );
		$this->assertSame( false, (bool) preg_match( '/<\/li>\s*>\s*<li/', $html ) );
	}

	/**
	 * Test renderer escapes labels and urls.
	 */
	public function test_renderer_escapes_labels_and_urls(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( '<b>Home</b>', 'https://example.com/' ),
				new Item( 'A & B', 'javascript:alert(1)' ),
			]
		);

		$this->assertStringNotContainsString( '<b>Home</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;Home&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'A &amp; B', $html );
		$this->assertStringNotContainsString( 'javascript:alert(1)', $html );
	}

	/**
	 * Test renderer escapes by default and allows html only on opt in.
	 */
	public function test_renderer_allow_html_contract(): void {
		$plain = ( new Renderer() )->render( [ new Item( '<b>Bold</b>', '' ) ] );
		$rich  = ( new Renderer() )->render( [ new Item( '<b>Bold</b>', '', true ) ] );

		$this->assertStringContainsString( '&lt;b&gt;Bold&lt;/b&gt;', $plain );
		$this->assertStringContainsString( '<b>Bold</b>', $rich );
	}

	/**
	 * Test renderer neutralizes a malicious label and url.
	 */
	public function test_renderer_neutralizes_malicious_input(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Home', 'https://example.com/' ),
				new Item( '<script>alert(1)</script>', 'javascript:alert(2)' ),
			]
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * Test renderer never outputs raw settings html.
	 */
	public function test_renderer_never_outputs_raw_separator_html(): void {
		$html = ( new Renderer() )->render(
			[ new Item( 'Home', '' ) ],
			[ 'separator' => '<b>/</b>' ]
		);

		$this->assertStringNotContainsString( '<b>/</b>', $html );
	}

	/**
	 * Test renderer honors show home and show current args.
	 */
	public function test_renderer_honors_show_home_and_show_current(): void {
		$items = [
			new Item( 'Home', 'https://example.com/' ),
			new Item( 'Hello World', 'https://example.com/?p=11' ),
		];

		$noHome    = ( new Renderer() )->render( $items, [ 'show_home' => false ] );
		$noCurrent = ( new Renderer() )->render( $items, [ 'show_current' => false ] );

		$this->assertStringNotContainsString( 'Home', $noHome );
		$this->assertStringNotContainsString( 'Hello World', $noCurrent );
	}

	/**
	 * Test renderer returns empty without items.
	 */
	public function test_renderer_returns_empty_without_items(): void {
		$this->assertSame( '', ( new Renderer() )->render( [] ) );
		$this->assertSame( '', ( new Renderer() )->render( [ new Item( 'Only', '' ) ], [ 'show_home' => false ] ) );
	}

	/**
	 * Test template tag echoes and getter returns the same html.
	 */
	public function test_template_tag_echoes_and_getter_returns(): void {
		$this->useSingularPost();

		$html = \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs();

		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $html );
		$this->assertStringContainsString( 'Hello World', $html );
		$this->assertContains( 'rankkernel/breadcrumbs/items', $this->appliedFilters );
		$this->assertContains( 'rankkernel/breadcrumbs', $this->appliedFilters );

		ob_start();

		\rankkernel_breadcrumbs();

		$echoed = (string) ob_get_clean();

		$this->assertSame( $html, $echoed );
	}

	/**
	 * Test getter args override the stored settings.
	 */
	public function test_getter_args_override_settings(): void {
		$this->useSingularPost();

		$html = \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs(
			[
				'separator' => '>',
				'show_home' => false,
			]
		);

		$this->assertStringContainsString( '--rk-breadcrumb-separator:&quot;&gt;&quot;', $html );
		$this->assertStringNotContainsString( 'https://example.com/">Home', $html );
	}

	/**
	 * Test getter sanitizes caller args.
	 */
	public function test_getter_sanitizes_caller_args(): void {
		$this->useSingularPost();

		$html = \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs( [ 'separator' => '<script>alert(1)</script>' ] );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Test getter applies the args filter.
	 */
	public function test_getter_applies_args_filter(): void {
		$this->useSingularPost();

		$this->argsFilterSeparator = '>';

		$html = \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs();

		$this->assertContains( 'rankkernel/breadcrumbs/args', $this->appliedFilters );
		$this->assertStringContainsString( '--rk-breadcrumb-separator:&quot;&gt;&quot;', $html );
	}

	/**
	 * Test getter applies the items filter with allow html semantics.
	 */
	public function test_getter_applies_items_filter(): void {
		$this->useSingularPost();

		$this->itemsFilterExtra = [ new Item( '<b>Extra</b>', '', true ) ];

		$html = \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs();

		$this->assertStringContainsString( '<b>Extra</b>', $html );
	}

	/**
	 * Test getter applies the final html filter.
	 */
	public function test_getter_applies_html_filter(): void {
		$this->useSingularPost();

		$this->htmlFilterSuffix = '<!-- rk -->';

		$html = \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs();

		$this->assertStringEndsWith( '<!-- rk -->', $html );
	}

	/**
	 * Test getter returns empty without a query.
	 */
	public function test_getter_returns_empty_without_query(): void {
		$this->assertSame( '', \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs() );
	}

	/**
	 * Test boot registers the shortcode.
	 */
	public function test_boot_registers_shortcode(): void {
		$this->options['rankkernel_modules'] = [ 'metadata', 'schema', 'breadcrumbs' ];

		$module = new BreadcrumbsModule( new ModuleEnableMap() );
		$module->boot();

		$this->assertArrayHasKey( 'rankkernel_breadcrumbs', $this->shortcodes );
		$this->assertTrue( function_exists( 'rankkernel_breadcrumbs' ) );
		$this->assertTrue( function_exists( 'rankkernel_get_breadcrumbs' ) );
	}

	/**
	 * Test shortcode returns output and never echoes.
	 */
	public function test_shortcode_returns_without_echoing(): void {
		$this->useSingularPost();

		$this->options['rankkernel_modules'] = [ 'metadata', 'schema', 'breadcrumbs' ];

		$module = new BreadcrumbsModule( new ModuleEnableMap() );
		$module->boot();

		$callback = $this->shortcodes['rankkernel_breadcrumbs'];

		$this->assertIsCallable( $callback );

		ob_start();

		$result = call_user_func( $callback, [] );
		$echoed = (string) ob_get_clean();

		$this->assertSame( '', $echoed );
		$this->assertIsString( $result );
		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $result );
	}

	/**
	 * Test shortcode attributes sanitize.
	 */
	public function test_shortcode_attributes_sanitize(): void {
		$this->useSingularPost();

		$module = new BreadcrumbsModule();

		$result = $module->renderShortcode(
			[
				'separator'    => '<script>alert(1)</script>',
				'show_home'    => '0',
				'show_current' => '1',
			]
		);

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( 'https://example.com/">Home', $result );
	}

	/**
	 * Test block registration has no view script.
	 */
	public function test_block_registration_has_no_view_script(): void {
		( new BreadcrumbsBlock() )->register();

		$this->assertNotEmpty( $this->registeredBlocks );

		$entry = end( $this->registeredBlocks );

		$this->assertStringEndsWith( 'breadcrumbs', (string) $entry['path'] );
		$this->assertIsArray( $entry['args'] );
		$this->assertArrayHasKey( 'render_callback', $entry['args'] );
		$this->assertArrayHasKey( 'editor_script', $entry['args'] );
		$this->assertArrayNotHasKey( 'view_script', $entry['args'] );
		$this->assertArrayNotHasKey( 'viewScript', $entry['args'] );
	}

	/**
	 * Test block json declares server rendering only.
	 */
	public function test_block_json_declares_server_rendering(): void {
		$path = dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/blocks/breadcrumbs/block.json';

		$this->assertFileExists( $path );

		$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a fixture file.

		$this->assertSame( 'rankkernel/breadcrumbs', $json['name'] );
		$this->assertSame( 'rankkernel', $json['category'] );
		$this->assertArrayNotHasKey( 'viewScript', $json );
		$this->assertArrayHasKey( 'showHomeItem', $json['attributes'] );
		$this->assertArrayHasKey( 'showCurrentItem', $json['attributes'] );
		$this->assertArrayHasKey( 'showOnHomePage', $json['attributes'] );
		$this->assertArrayHasKey( 'separator', $json['attributes'] );

		$editor = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/blocks/breadcrumbs/breadcrumbs-editor.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a fixture file.

		$this->assertStringContainsString( 'return null', $editor );
	}

	/**
	 * Test block render uses wrapper attributes.
	 */
	public function test_block_render_uses_wrapper_attributes(): void {
		$this->useSingularPost();

		$html = ( new BreadcrumbsBlock() )->render( [] );

		$this->assertStringContainsString( '<div class="wp-block-rankkernel-breadcrumbs">', $html );
		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $html );
		$this->assertContains( 'rankkernel-breadcrumbs', $this->enqueuedStyles );
	}

	/**
	 * Test block render honors attributes.
	 */
	public function test_block_render_honors_attributes(): void {
		$this->useSingularPost();

		$html = ( new BreadcrumbsBlock() )->render(
			[
				'showHomeItem'    => false,
				'showCurrentItem' => true,
				'separator'       => '>',
			]
		);

		$this->assertStringNotContainsString( 'https://example.com/">Home', $html );
		$this->assertStringContainsString( '--rk-breadcrumb-separator:&quot;&gt;&quot;', $html );
	}

	/**
	 * Test block hides on the front page by default.
	 */
	public function test_block_hides_on_front_page_by_default(): void {
		$this->useSingularPost();

		$this->frontPage = true;

		$this->assertSame( '', ( new BreadcrumbsBlock() )->render( [] ) );

		$this->queryVars['paged'] = 3;

		$this->assertStringContainsString(
			'<nav class="rk-breadcrumbs"',
			( new BreadcrumbsBlock() )->render( [ 'showOnHomePage' => true ] )
		);
	}

	/**
	 * Test block render sanitizes a hostile separator.
	 */
	public function test_block_render_sanitizes_separator(): void {
		$this->useSingularPost();

		$html = ( new BreadcrumbsBlock() )->render( [ 'separator' => '<script>alert(1)</script>' ] );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Test the renderer does not trim a trail whose visibility was already applied.
	 *
	 * The canonical paths (template tag and block) decide visibility in the
	 * builder, before pagination, so the renderer must not drop the first or
	 * last item again or it would remove a real crumb or a Page N crumb.
	 */
	public function test_renderer_skips_visibility_when_already_applied(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Blog', 'https://example.com/blog/' ),
				new Item( 'Page 2', '' ),
			],
			[
				'show_home'        => false,
				'show_current'     => false,
				'apply_visibility' => false,
			]
		);

		$this->assertStringContainsString( 'Blog', $html, 'First item must survive' );
		$this->assertStringContainsString( 'Page 2', $html, 'Last item must survive' );
	}

	/**
	 * Test settings section renders the grouped breadcrumbs fields.
	 */
	public function test_settings_section_renders_fields(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Breadcrumbs', $output );
		$this->assertStringContainsString( 'Appearance', $output );
		$this->assertStringContainsString( 'Trail behavior', $output );
		$this->assertStringContainsString( 'Taxonomy preferences', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_separator_choice', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_separator_custom', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_home_label', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_show_home', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_show_current', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_hide_on_front_page', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_show_blog_page', $output );
		$this->assertStringContainsString( 'rk_breadcrumbs_show_ancestors', $output );
	}

	/**
	 * Test settings section renders native radio inputs for every preset.
	 */
	public function test_settings_section_renders_separator_preset_radios(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertSame( 7, substr_count( $output, 'name="rk_breadcrumbs_separator_choice"' ) );
		$this->assertStringContainsString( '<fieldset>', $output );
		$this->assertStringContainsString( 'value="custom"', $output );
		$this->assertStringContainsString( 'id="rk-breadcrumbs-separator-custom-wrap"', $output );
		$this->assertStringContainsString( '<label for="rk-breadcrumbs-separator-custom">', $output );
	}

	/**
	 * Test settings section preselects Custom for a stored non preset value.
	 */
	public function test_settings_section_preselects_custom_for_stored_non_preset(): void {
		$this->options[ BreadcrumbsSettings::OPTION ] = [ 'separator' => '→' ];

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'value="custom" checked="checked"', $output );
		$this->assertStringContainsString( 'value="→"', $output );
	}

	/**
	 * Test taxonomy selects render only for types with several taxonomies.
	 */
	public function test_settings_section_renders_taxonomy_selects_only_for_multi_taxonomy_types(): void {
		$this->postTypesMap   = [
			'post' => 'Posts',
			'page' => 'Pages',
			'book' => 'Books',
		];
		$this->objectTaxesMap = [
			'post' => [
				'category' => 'Categories',
				'post_tag' => 'Tags',
			],
			'page' => [ 'genre' => 'Genres' ],
			'book' => [],
		];

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="rk_breadcrumbs_primary_taxonomy_post"', $output );
		$this->assertStringNotContainsString( 'name="rk_breadcrumbs_primary_taxonomy_page"', $output );
		$this->assertStringNotContainsString( 'name="rk_breadcrumbs_primary_taxonomy_book"', $output );
		$this->assertSame( 1, substr_count( $output, '<select' ) );
		$this->assertStringContainsString( 'the only public taxonomy available', $output );
	}

	/**
	 * Test settings save stores the chosen separator preset.
	 */
	public function test_settings_save_with_preset_separator_choice(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'                   => '1',
			'_wpnonce'                          => 'valid',
			'rk_breadcrumbs_separator_choice'   => '›',
			'rk_breadcrumbs_home_label'         => 'Start',
			'rk_breadcrumbs_show_home'          => '1',
			'rk_breadcrumbs_show_current'       => '1',
			'rk_breadcrumbs_hide_on_front_page' => '1',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ BreadcrumbsSettings::OPTION ] ?? [];

		$this->assertSame( '›', $stored['separator'] );
		$this->assertSame( 'Start', $stored['home_label'] );
	}

	/**
	 * Test settings save stores a custom separator.
	 */
	public function test_settings_save_with_custom_separator(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'                 => '1',
			'_wpnonce'                        => 'valid',
			'rk_breadcrumbs_separator_choice' => 'custom',
			'rk_breadcrumbs_separator_custom' => '→',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ BreadcrumbsSettings::OPTION ] ?? [];

		$this->assertSame( '→', $stored['separator'] );
	}

	/**
	 * Test settings save strips markup from a hostile custom separator.
	 */
	public function test_settings_save_strips_hostile_separator(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'                 => '1',
			'_wpnonce'                        => 'valid',
			'rk_breadcrumbs_separator_choice' => 'custom',
			'rk_breadcrumbs_separator_custom' => '<script>alert(1)</script>',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ BreadcrumbsSettings::OPTION ] ?? [];

		$this->assertSame( 'alert(1)', $stored['separator'] );
	}

	/**
	 * Test settings save falls back to a slash for an empty custom separator.
	 */
	public function test_settings_save_empty_custom_separator_falls_back_to_slash(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'                 => '1',
			'_wpnonce'                        => 'valid',
			'rk_breadcrumbs_separator_choice' => 'custom',
			'rk_breadcrumbs_separator_custom' => '',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ BreadcrumbsSettings::OPTION ] ?? [];

		$this->assertSame( '/', $stored['separator'] );
	}

	/**
	 * Test settings save preserves taxonomy values for hidden controls.
	 */
	public function test_settings_save_preserves_hidden_taxonomy_values(): void {
		$this->postTypesMap                           = [
			'post' => 'Posts',
			'page' => 'Pages',
		];
		$this->objectTaxesMap                         = [
			'post' => [
				'category' => 'Categories',
				'post_tag' => 'Tags',
			],
			'page' => [ 'genre' => 'Genres' ],
		];
		$this->options[ BreadcrumbsSettings::OPTION ] = [
			'primary_taxonomy_post' => 'category',
			'primary_taxonomy_page' => 'genre',
		];

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'                      => '1',
			'_wpnonce'                             => 'valid',
			'rk_breadcrumbs_separator_choice'      => '/',
			'rk_breadcrumbs_primary_taxonomy_post' => 'post_tag',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ BreadcrumbsSettings::OPTION ] ?? [];

		$this->assertSame( '/', $stored['separator'] );
		$this->assertSame( 'post_tag', $stored['primary_taxonomy_post'] );
		$this->assertSame( 'genre', $stored['primary_taxonomy_page'] );
	}

	/**
	 * Test the assets enqueue only on the RankKernel settings screen.
	 */
	public function test_enqueue_assets_only_on_settings_screen(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$page->enqueueAssets( 'toplevel_page_rankkernel' );

		$this->assertContains( 'rankkernel-breadcrumbs-admin', $this->enqueuedScripts );

		$this->enqueuedScripts = [];

		$page->enqueueAssets( 'rankkernel_page_rankkernel-schema' );

		$this->assertSame( [], $this->enqueuedScripts );
	}

	/**
	 * Test settings save persists through the admin pattern.
	 */
	public function test_settings_save_persists_breadcrumbs(): void {
		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'                   => '1',
			'_wpnonce'                          => 'valid',
			'title_template'                    => 'My Title',
			'rk_breadcrumbs_separator'          => '>',
			'rk_breadcrumbs_home_label'         => 'Start',
			'rk_breadcrumbs_show_home'          => '1',
			'rk_breadcrumbs_show_current'       => '1',
			'rk_breadcrumbs_hide_on_front_page' => '1',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ BreadcrumbsSettings::OPTION ] ?? [];

		$this->assertSame( '>', $stored['separator'] );
		$this->assertSame( 'Start', $stored['home_label'] );
		$this->assertTrue( $stored['show_home'] );
		$this->assertTrue( $stored['hide_on_front_page'] );
		$this->assertFalse( $stored['show_blog_page'] );
	}
}
