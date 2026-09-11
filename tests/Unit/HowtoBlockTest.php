<?php
/**
 * HowTo block tests, block.json values, render, block fed schema, registration.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\blocks\HowtoBlock;
use RankKernel\Modules\Schema\Pieces\HowtoPiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

final class HowtoBlockTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/** @var string */
	private string $postContent = '';

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->postContent = '';

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating WP core, which uses its own C implementation here.
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => trim( $v ) );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias(
			static function ( string $v ): string {
				$clean = filter_var( $v, FILTER_SANITIZE_URL );

				return is_string( $clean ) ? $clean : $v;
			}
		);
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => trim( strip_tags( $v, '<p><a><br><b><i><strong><em>' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating the kses allowlist with a native tag filter.
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_author' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_settings' === $key ) {
					return [];
				}

				return $fallback;
			}
		);
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $v ): string => rtrim( $v, '/' ) . '/' );
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/hello/' );
		Functions\when( 'get_the_title' )->justReturn( 'Hello Post' );
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->alias(
			function ( string $field ): string {
				if ( 'post_content' === $field ) {
					return $this->postContent;
				}

				return '';
			}
		);
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_the_date' )->justReturn( '2026-01-01T00:00:00+00:00' );
		Functions\when( 'get_the_modified_date' )->justReturn( '2026-02-01T00:00:00+00:00' );
		Functions\when( 'parse_blocks' )->justReturn( [] );
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	private function blockDir(): string {
		return dirname( __DIR__, 2 ) . '/src/Modules/Schema/blocks/howto';
	}

	public function test_block_json_has_binding_values(): void {
		$path = $this->blockDir() . '/block.json';
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local block.json fixture read in a unit test, wp_remote_get is for remote URLs only.

		$this->assertIsString( $raw );

		$json = json_decode( (string) $raw, true );

		$this->assertIsArray( $json );
		$this->assertSame( 'rankkernel/howto', $json['name'] );
		$this->assertSame( 'HowTo by RankKernel', $json['title'] );
		$this->assertSame( 'rankkernel', $json['category'] );
		$this->assertSame( [], $json['attributes']['steps']['default'] );
		$this->assertArrayNotHasKey( 'editorScript', $json );
		$this->assertArrayNotHasKey( 'editorStyle', $json );
	}

	public function test_block_json_declares_additive_fields_and_editor_supports(): void {
		$path = $this->blockDir() . '/block.json';
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local block.json fixture read in a unit test, wp_remote_get is for remote URLs only.

		$this->assertIsString( $raw );

		$json = json_decode( (string) $raw, true );

		$this->assertIsArray( $json );

		$this->assertSame( 'h3', $json['attributes']['titleWrapper']['default'] );
		$this->assertSame( [ 'h2', 'h3', 'h4' ], $json['attributes']['titleWrapper']['enum'] );
		$this->assertSame( '', $json['attributes']['stepTag']['default'] );
		$this->assertSame( '', $json['attributes']['description']['default'] );
		$this->assertSame( '', $json['attributes']['totalTime']['default'] );
		$this->assertSame( '', $json['attributes']['estimatedCost']['default'] );
		$this->assertSame( [], $json['attributes']['tools']['default'] );
		$this->assertSame( [], $json['attributes']['materials']['default'] );

		$props = $json['attributes']['steps']['items']['properties'];

		$this->assertArrayHasKey( 'title', $props );
		$this->assertArrayHasKey( 'text', $props );
		$this->assertArrayHasKey( 'image', $props );
		$this->assertSame( 'string', $props['id']['type'] );
		$this->assertSame( 'string', $props['alt']['type'] );
		$this->assertArrayHasKey( 'supports', $json );
		$this->assertArrayHasKey( 'example', $json );
	}

	public function test_register_block_registers_editor_assets_with_dependencies(): void {
		$registeredScripts = [];
		$registeredStyles  = [];
		$registeredTypes   = [];
		$translations      = [];

		Functions\when( 'wp_register_script' )->alias(
			static function ( string $handle, string $src, array $deps, mixed ...$args ) use ( &$registeredScripts ): void {
				$registeredScripts[ $handle ] = [
					'deps'    => $deps,
					'version' => $args[0] ?? null,
				];
			}
		);
		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps, mixed ...$args ) use ( &$registeredStyles ): void {
				$registeredStyles[ $handle ] = [
					'deps'    => $deps,
					'version' => $args[0] ?? null,
				];
			}
		);
		Functions\when( 'wp_set_script_translations' )->alias(
			static function ( string $handle, string $domain ) use ( &$translations ): void {
				$translations[ $handle ] = $domain;
			}
		);
		Functions\when( 'plugins_url' )->alias( static fn ( string $p ): string => 'https://example.com/wp-content/plugins/rankkernel/' . $p );
		Functions\when( 'register_block_type' )->alias(
			static function ( mixed $name, mixed $args = [] ) use ( &$registeredTypes ): mixed {
				$registeredTypes[] = [ $name, $args ];

				return true;
			}
		);

		( new HowtoBlock() )->registerBlock();

		$this->assertSame(
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
			$registeredScripts['rankkernel-howto-editor']['deps']
		);
		$this->assertArrayHasKey( 'rankkernel-howto-editor', $registeredStyles );
		$this->assertSame( 'rankkernel', $translations['rankkernel-howto-editor'] );

		$root = dirname( __DIR__, 2 );

		$this->assertSame(
			(string) filemtime( $root . '/src/Modules/Schema/blocks/howto/howto-editor.js' ),
			$registeredScripts['rankkernel-howto-editor']['version']
		);
		$this->assertSame(
			(string) filemtime( $root . '/src/Modules/Schema/blocks/howto/editor.css' ),
			$registeredStyles['rankkernel-howto-editor']['version']
		);

		$found = false;

		foreach ( $registeredTypes as $entry ) {
			if ( ! is_array( $entry[1] ) ) {
				continue;
			}

			$scriptOk = 'rankkernel-howto-editor' === ( $entry[1]['editor_script'] ?? null );
			$styleOk  = 'rankkernel-howto-editor' === ( $entry[1]['editor_style'] ?? null );

			if ( $scriptOk && $styleOk ) {
				$found = true;
			}
		}

		$this->assertTrue( $found, 'Block type must reference the explicit editor handles' );
	}

	public function test_register_never_registers_category(): void {
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'plugins_url' )->alias( static fn ( string $p ): string => 'https://example.com/wp-content/plugins/rankkernel/' . $p );
		Functions\when( 'register_block_type' )->justReturn( true );
		Functions\expect( 'add_filter' )->never();

		( new HowtoBlock() )->register();

		$this->assertFalse(
			method_exists( HowtoBlock::class, 'addCategory' ),
			'Block category registration lives centrally in SchemaModule'
		);
	}

	public function test_render_numbers_each_step(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'title'        => 'Do it',
				'titleWrapper' => 'h2',
				'steps'        => [
					[
						'title' => 'First',
						'text'  => 'One.',
						'image' => '',
					],
					[
						'title' => 'Second',
						'text'  => 'Two.',
						'image' => 'https://example.com/s.jpg',
					],
				],
			]
		);

		$this->assertStringContainsString( '<span class="rankkernel-howto-number">1. </span>First', $html );
		$this->assertStringContainsString( '<span class="rankkernel-howto-number">2. </span>Second', $html );
		$this->assertStringContainsString( '<img class="rankkernel-howto-step-image" src="https://example.com/s.jpg"', $html );
		$this->assertStringContainsString( '<ol class="rankkernel-howto-list" role="list" style="list-style-type:none;">', $html );
	}

	public function test_render_shows_details_time_cost_tools_materials(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'title'         => 'Guide',
				'description'   => 'A <b>bold</b> plan.',
				'totalTime'     => 'PT30M',
				'estimatedCost' => '5 USD',
				'tools'         => [ 'Bowl', '', '   ' ],
				'materials'     => [ 'Flour' ],
				'steps'         => [
					[
						'title' => 'Mix',
						'text'  => 'Stir.',
						'image' => '',
					],
				],
			]
		);

		$this->assertStringContainsString( '<p class="rankkernel-howto-description">A &lt;b&gt;bold&lt;/b&gt; plan.</p>', $html );
		$this->assertStringContainsString( '<p class="rankkernel-howto-total-time">Total time: PT30M</p>', $html );
		$this->assertStringContainsString( '<p class="rankkernel-howto-estimated-cost">Estimated cost: 5 USD</p>', $html );
		$this->assertStringContainsString( '<div class="rankkernel-howto-tools">', $html );
		$this->assertStringContainsString( '<li>Bowl</li>', $html );
		$this->assertStringContainsString( '<li>Flour</li>', $html );
		$this->assertSame( 2, substr_count( $html, '<li>' ) );
	}

	public function test_render_hides_invalid_total_time(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'title'     => 'Guide',
				'totalTime' => 'soon',
				'steps'     => [
					[
						'title' => 'Mix',
						'text'  => 'Stir.',
						'image' => '',
					],
				],
			]
		);

		$this->assertStringNotContainsString( 'rankkernel-howto-total-time', $html );
		$this->assertStringContainsString( 'Mix', $html );
	}

	public function test_render_step_tag_overrides_title_wrapper(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'title'        => 'Guide',
				'titleWrapper' => 'h3',
				'stepTag'      => 'h2',
				'steps'        => [
					[
						'title' => 'Mix',
						'text'  => 'Stir.',
						'image' => '',
					],
				],
			]
		);

		$this->assertStringContainsString( '<h3 class="rankkernel-howto-title">', $html );
		$this->assertStringContainsString( '<h2 class="rankkernel-howto-step-title">', $html );
	}

	public function test_render_steps_follow_title_wrapper_by_default(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'title'        => 'Guide',
				'titleWrapper' => 'h2',
				'steps'        => [
					[
						'title' => 'Mix',
						'text'  => 'Stir.',
						'image' => '',
					],
				],
			]
		);

		$this->assertStringContainsString( '<h2 class="rankkernel-howto-step-title">', $html );
	}

	public function test_render_uses_step_alt_and_rejects_unsafe_image(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'steps' => [
					[
						'id'    => 'rks-legacy-1',
						'title' => 'Whisk',
						'text'  => 'Whisk well.',
						'image' => 'https://example.com/whisk.png',
						'alt'   => 'A whisk',
					],
					[
						'title' => 'Tricky',
						'text'  => 'No image here.',
						'image' => 'javascript:alert(1)',
					],
				],
			]
		);

		$this->assertStringContainsString( 'src="https://example.com/whisk.png" alt="A whisk"', $html );
		$this->assertStringContainsString( 'No image here.', $html );
		$this->assertSame( 1, substr_count( $html, '<img' ) );
	}

	public function test_render_preserves_step_order_with_numbering(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'steps' => [
					[
						'title' => 'Charlie',
						'text'  => 'Third.',
						'image' => '',
					],
					[
						'title' => 'Bravo',
						'text'  => 'Second.',
						'image' => '',
					],
					[
						'title' => 'Alpha',
						'text'  => 'First.',
						'image' => '',
					],
				],
			]
		);

		$first  = strpos( $html, '>1. </span>Charlie' );
		$second = strpos( $html, '>2. </span>Bravo' );
		$third  = strpos( $html, '>3. </span>Alpha' );

		$this->assertIsInt( $first );
		$this->assertIsInt( $second );
		$this->assertIsInt( $third );
		$this->assertGreaterThan( $first, $second );
		$this->assertGreaterThan( $second, $third );
	}

	public function test_render_skips_image_only_and_whitespace_steps(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'steps' => [
					[
						'title' => '',
						'text'  => '',
						'image' => 'https://example.com/only.png',
					],
					[
						'title' => '',
						'text'  => '   ',
						'image' => '',
					],
					[
						'title' => 'Kept',
						'text'  => 'Here.',
						'image' => '',
					],
				],
			]
		);

		$this->assertSame( 1, substr_count( $html, '<li class="rankkernel-howto-item">' ) );
		$this->assertStringContainsString( 'Kept', $html );
	}

	public function test_render_accepts_legacy_shape_and_extra_params(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'title' => 'Old block',
				'steps' => [
					[
						'title' => 'Old step',
						'text'  => 'Old text.',
					],
				],
			],
			'',
			null
		);

		$this->assertStringContainsString( 'Old block', $html );
		$this->assertStringContainsString( 'Old step', $html );
	}

	public function test_render_escapes_markup_and_skips_empty_rows(): void {
		$html = ( new HowtoBlock() )->render(
			[
				'title'        => '<b>Hi</b>',
				'titleWrapper' => 'script',
				'steps'        => [
					[
						'title' => '',
						'text'  => '',
						'image' => '',
					],
					'junk',
					[
						'title' => '<script>alert(1)</script>',
						'text'  => 'A.',
					],
				],
			]
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;b&gt;Hi&lt;/b&gt;', $html );
		$this->assertStringContainsString( '<h3 class="rankkernel-howto-title">', $html );
		$this->assertSame( 1, substr_count( $html, '<li class="rankkernel-howto-item">' ) );
	}

	public function test_render_empty_steps_returns_empty_string(): void {
		$this->assertSame( '', ( new HowtoBlock() )->render( [ 'steps' => [] ] ) );
		$this->assertSame( '', ( new HowtoBlock() )->render( [] ) );
	}

	/**
	 * Build a singular query mock.
	 */
	private function singularQuery( int $id = 1 ): WP_Query {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( $id )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		return $query;
	}

	private function makeContext( WP_Query $query ): Context {
		return new Context( $query, new SettingsStore() );
	}

	public function test_block_only_steps_are_picked_up(): void {
		$this->postContent = 'has blocks';

		Functions\when( 'parse_blocks' )->alias(
			static function (): array {
				return [
					[
						'blockName' => 'core/paragraph',
						'attrs'     => [],
					],
					[
						'blockName' => 'rankkernel/howto',
						'attrs'     => [
							'steps' => [
								[
									'title' => 'Block step',
									'text'  => 'Block text.',
									'image' => '',
								],
							],
						],
					],
				];
			}
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$piece = new \RankKernel\Modules\Schema\Pieces\HowtoPiece();
		$build = $piece->build( $ctx );

		$this->assertSame( 'HowTo', $build['@type'] );
		$this->assertSame( 'Block step', $build['step'][0]['name'] );
	}
}
