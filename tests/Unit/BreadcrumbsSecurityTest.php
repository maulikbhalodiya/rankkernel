<?php
/**
 * Breadcrumbs security tests, escaping and injection proof.
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
use RankKernel\Modules\Breadcrumbs\BreadcrumbsSettings;
use RankKernel\Modules\Breadcrumbs\Item;
use RankKernel\Modules\Breadcrumbs\Renderer;
use WP_Query;

use function RankKernel\Modules\Breadcrumbs\normalize_breadcrumb_items;
use function RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs;

/**
 * Breadcrumbs Security Test.
 *
 * Proves malicious labels, unsafe URL schemes, hostile shortcode and
 * block attributes, and hostile settings values cannot inject markup
 * or scripts into breadcrumb output.
 */
final class BreadcrumbsSecurityTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

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
	 * Extra items appended by the items filter test.
	 *
	 * @var array<int, mixed>
	 */
	private array $itemsFilterExtra = [];

	/**
	 * Titles by post id.
	 *
	 * @var array<int, string>
	 */
	private array $titles = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		require_once dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/functions.php';

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias(
			static function ( string $url ): string {
				if ( 1 === preg_match( '/^\s*javascript:/i', $url ) ) {
					return '';
				}

				if ( 1 === preg_match( '/^\s*data:/i', $url ) ) {
					return '';
				}

				if ( 1 === preg_match( '/^\s*vbscript:/i', $url ) ) {
					return '';
				}

				return $url;
			}
		);
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => trim( strip_tags( $v, '<p><a><br><b><i><strong><em>' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating the kses allowlist with a native tag filter.
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
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_breadcrumbs_settings' === $key ) {
					return $this->settingsOption ?? $fallback;
				}

				return $fallback;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'get_the_title' )->alias(
			function ( mixed $id = 0 ): string {
				return $this->titles[ (int) $id ] ?? '';
			}
		);
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
		Functions\when( 'get_taxonomy' )->justReturn( false );
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
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/breadcrumbs/items' === $hook && is_array( $value ) ) {
					foreach ( $this->itemsFilterExtra as $extra ) {
						$value[] = $extra;
					}
				}

				return $value;
			}
		);
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'get_block_wrapper_attributes' )->justReturn( 'class="wp-block-rankkernel-breadcrumbs"' );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();

		unset( $GLOBALS['wp_query'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test resets the global query double.
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
		$this->titles[ $id ] = $title;

		$GLOBALS['wp_query'] = $this->makeSingularQuery( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test sets the global query double the production code reads.
	}

	/**
	 * Test a script label from a post title is escaped in output.
	 */
	public function test_post_title_script_escaped_in_visible_output(): void {
		$this->useSingularPost( 11, '<script>alert(1)</script>' );

		$html = rankkernel_get_breadcrumbs();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * Test markup in a label is escaped in output.
	 */
	public function test_markup_label_escaped_in_visible_output(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Home', 'https://example.com/' ),
				new Item( '<img src="x" onerror="alert(1)">Tech', 'https://example.com/go/term/' ),
			]
		);

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'src="x"', $html );
		$this->assertStringContainsString( '&lt;img', $html );
	}

	/**
	 * Test a filtered label without allow html cannot inject.
	 */
	public function test_filtered_label_without_allow_html_cannot_inject(): void {
		$this->useSingularPost();

		$this->itemsFilterExtra = [ new Item( '<script>alert(1)</script>', 'https://example.com/extra/' ) ];

		$html = rankkernel_get_breadcrumbs();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * Test allow html true filters through wp kses post.
	 */
	public function test_allow_html_true_filters_through_kses(): void {
		$html = ( new Renderer() )->render(
			[ new Item( '<script>alert(1)</script><b>Bold</b>', '', true ) ]
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<b>Bold</b>', $html );
	}

	/**
	 * Test an unsafe URL scheme in an item is neutralized.
	 */
	public function test_unsafe_url_scheme_in_item_neutralized(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Bad', 'javascript:alert(1)' ),
				new Item( 'Current', 'https://example.com/here/' ),
			]
		);

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	/**
	 * Test an unsafe URL in a filtered array item is neutralized.
	 */
	public function test_unsafe_url_in_filtered_item_neutralized(): void {
		$items = normalize_breadcrumb_items(
			[
				[
					'label' => 'Bad',
					'url'   => 'javascript:alert(1)',
				],
				[
					'label' => 'Current',
					'url'   => 'https://example.com/here/',
				],
			]
		);

		$html = ( new Renderer() )->render( $items );

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	/**
	 * Test malicious shortcode attributes cannot inject.
	 */
	public function test_malicious_shortcode_attributes_cannot_inject(): void {
		$this->useSingularPost();

		$module = new BreadcrumbsModule();

		$html = $module->renderShortcode(
			[
				'separator'    => '<script>alert(1)</script>',
				'show_home'    => '1',
				'show_current' => '1"><script>alert(2)</script>',
			]
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $html );
	}

	/**
	 * Test malicious block attributes cannot inject.
	 */
	public function test_malicious_block_attributes_cannot_inject(): void {
		$this->useSingularPost();

		$html = ( new BreadcrumbsBlock() )->render(
			[
				'separator'       => '"><script>alert(1)</script>',
				'showHomeItem'    => true,
				'showCurrentItem' => true,
			]
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<nav class="rk-breadcrumbs"', $html );
	}

	/**
	 * Test a settings separator with HTML is stripped.
	 */
	public function test_settings_separator_html_stripped(): void {
		$this->useSingularPost();

		$this->settingsOption = [ 'separator' => '<b>/</b><script>alert(1)</script>' ];

		$html = rankkernel_get_breadcrumbs();

		$this->assertStringNotContainsString( '<b>/</b>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Test settings set rejects HTML in the separator and home label.
	 */
	public function test_settings_set_rejects_html_values(): void {
		$settings = new BreadcrumbsSettings();

		$settings->set(
			[
				'separator'  => '<b>/</b>',
				'home_label' => '<script>alert(1)</script>Home',
			]
		);

		$this->assertStringNotContainsString( '<', (string) $settings->get( 'separator' ) );
		$this->assertStringNotContainsString( '<', (string) $settings->get( 'home_label' ) );
	}

	/**
	 * Test a raw separator cannot break out of the style attribute.
	 */
	public function test_raw_separator_cannot_break_out_of_attribute(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Home', 'https://example.com/' ),
				new Item( 'Section', '' ),
			],
			[ 'separator' => 'x" onmouseover="alert(1)' ]
		);

		$this->assertStringNotContainsString( 'onmouseover', $html );
		$this->assertStringNotContainsString( '" onmouseover="', $html );
		$this->assertSame( 1, substr_count( $html, 'style="' ), 'Only one style attribute is emitted' );
		$this->assertStringContainsString( '<li class="rk-breadcrumbs-item">', $html );
	}

	/**
	 * Test a separator cannot inject a second CSS declaration.
	 */
	public function test_separator_cannot_inject_a_second_css_declaration(): void {
		$html = ( new Renderer() )->render(
			[
				new Item( 'Home', 'https://example.com/' ),
				new Item( 'Section', '' ),
			],
			[ 'separator' => '/;background-image:url(https://attacker.example/x)' ]
		);

		$style = '';

		if ( 1 === preg_match( '/style="([^"]*)"/', $html, $matches ) ) {
			$style = $matches[1];
		}

		$this->assertSame( 1, substr_count( $style, ';' ), 'Only the declaration terminator remains' );
		$this->assertStringNotContainsString( '(', $style );
		$this->assertStringNotContainsString( ')', $style );
		$this->assertStringNotContainsString( 'url(', $style );
		$this->assertStringStartsWith( '--rk-breadcrumb-separator:', $style );
		$this->assertSame( 1, substr_count( $html, 'style="' ), 'Only one style attribute is emitted' );
	}
}
