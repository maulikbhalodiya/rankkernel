<?php
/**
 * FAQ block tests, block.json values, render, block fed schema, registration.
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
use RankKernel\Modules\Schema\blocks\FaqBlock;
use RankKernel\Modules\Schema\Pieces\FaqPiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

final class FaqBlockTest extends TestCase {
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
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => trim( strip_tags( $v, '<p><a><br><b><i><strong><em>' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating the kses allowlist with a native tag filter.
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating wp_strip_all_tags, which cannot call itself.
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
		Functions\when( 'get_author_posts_url' )->justReturn( 'https://example.com/author/bob/' );
		Functions\when( 'get_the_author_meta' )->justReturn( 'Bob' );
		Functions\when( 'parse_blocks' )->justReturn( [] );
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	private function blockDir(): string {
		return dirname( __DIR__, 2 ) . '/src/Modules/Schema/blocks/faq';
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

	/**
	 * @param array<string, mixed> $meta Raw post meta payload.
	 */
	private function stubPostMeta( array $meta ): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key ) use ( $meta ): mixed {
				if ( '_rankkernel_meta_data' === $key ) {
					return $meta;
				}

				return [];
			}
		);
	}

	/**
	 * @param array<int, mixed> $blocks Parsed blocks to return.
	 */
	private function stubBlocks( array $blocks ): void {
		Functions\when( 'parse_blocks' )->alias(
			static function () use ( $blocks ): array {
				return $blocks;
			}
		);
	}

	public function test_block_json_has_binding_values(): void {
		$path = $this->blockDir() . '/block.json';
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local block.json fixture read in a unit test, wp_remote_get is for remote URLs only.

		$this->assertIsString( $raw );

		$json = json_decode( (string) $raw, true );

		$this->assertIsArray( $json );
		$this->assertSame( 'rankkernel/faq', $json['name'] );
		$this->assertSame( 'FAQ by RankKernel', $json['title'] );
		$this->assertSame(
			'Add SEO friendly frequently asked questions with automatic FAQPage schema.',
			$json['description']
		);
		$this->assertSame( 'rankkernel', $json['category'] );
		$this->assertSame( 'editor-ul', $json['icon'] );
		$this->assertSame( 'rankkernel', $json['textdomain'] );
		$this->assertSame(
			[ 'FAQ', 'Frequently Asked Questions', 'Schema', 'Structured Data' ],
			$json['keywords']
		);
		$this->assertSame( 3, $json['apiVersion'] );
		$this->assertSame( '', $json['attributes']['title']['default'] );
		$this->assertSame( 'h3', $json['attributes']['titleWrapper']['default'] );
		$this->assertSame( [ 'h2', 'h3', 'h4' ], $json['attributes']['titleWrapper']['enum'] );
		$this->assertSame( '', $json['attributes']['questionTag']['default'] );
		$this->assertSame( 'ul', $json['attributes']['listStyle']['default'] );
		$this->assertSame( [], $json['attributes']['questions']['default'] );
		$this->assertSame( 'string', $json['attributes']['questions']['items']['properties']['id']['type'] );
		$this->assertSame( 'string', $json['attributes']['questions']['items']['properties']['question']['type'] );
		$this->assertSame( 'string', $json['attributes']['questions']['items']['properties']['answer']['type'] );
		$this->assertArrayHasKey( 'supports', $json );
		$this->assertArrayHasKey( 'example', $json );
		$this->assertArrayNotHasKey( 'editorScript', $json );
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

		( new FaqBlock() )->registerBlock();

		$this->assertSame(
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
			$registeredScripts['rankkernel-faq-editor']['deps']
		);
		$this->assertArrayHasKey( 'rankkernel-faq-editor', $registeredStyles );
		$this->assertSame( 'rankkernel', $translations['rankkernel-faq-editor'] );

		$root = dirname( __DIR__, 2 );

		$this->assertSame(
			(string) filemtime( $root . '/src/Modules/Schema/blocks/faq/faq-editor.js' ),
			$registeredScripts['rankkernel-faq-editor']['version']
		);
		$this->assertSame(
			(string) filemtime( $root . '/src/Modules/Schema/blocks/faq/editor.css' ),
			$registeredStyles['rankkernel-faq-editor']['version']
		);

		$found = false;

		foreach ( $registeredTypes as $entry ) {
			if ( ! is_array( $entry[1] ) ) {
				continue;
			}

			$scriptOk = 'rankkernel-faq-editor' === ( $entry[1]['editor_script'] ?? null );
			$styleOk  = 'rankkernel-faq-editor' === ( $entry[1]['editor_style'] ?? null );

			if ( $scriptOk && $styleOk ) {
				$found = true;
			}
		}

		$this->assertTrue( $found, 'Block type must reference the explicit editor handles' );
	}

	public function test_render_numbers_each_question(): void {
		$html = ( new FaqBlock() )->render(
			[
				'title'        => '',
				'titleWrapper' => 'h3',
				'listStyle'    => 'ol',
				'questions'    => [
					[
						'question' => 'First?',
						'answer'   => 'One.',
					],
					[
						'question' => 'Second?',
						'answer'   => 'Two.',
					],
				],
			]
		);

		$this->assertStringContainsString( '<span class="rankkernel-faq-number">1. </span>First?', $html );
		$this->assertStringContainsString( '<span class="rankkernel-faq-number">2. </span>Second?', $html );
	}

	public function test_render_builds_list_with_title_per_wrapper(): void {
		$html = ( new FaqBlock() )->render(
			[
				'title'        => 'Common questions',
				'titleWrapper' => 'h2',
				'listStyle'    => 'ul',
				'questions'    => [
					[
						'question' => 'What?',
						'answer'   => 'This.',
					],
					[
						'question' => 'Why?',
						'answer'   => 'Because.',
					],
				],
			]
		);

		$this->assertStringContainsString( '<div class="rankkernel-faq">', $html );
		$this->assertStringContainsString(
			'<h2 class="rankkernel-faq-title">Common questions</h2>',
			$html
		);
		$this->assertStringContainsString( '<ul class="rankkernel-faq-list" role="list" style="list-style-type:disc;">', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="rankkernel-faq-item">' ) );
		$this->assertStringContainsString(
			'<h2 class="rankkernel-faq-question">What?</h2>',
			$html
		);
		$this->assertStringNotContainsString( 'rankkernel-faq-number', $html );
		$this->assertStringContainsString(
			'<div class="rankkernel-faq-answer">This.</div>',
			$html
		);
	}

	public function test_render_ordered_list_and_h4_wrapper(): void {
		$html = ( new FaqBlock() )->render(
			[
				'titleWrapper' => 'h4',
				'listStyle'    => 'ol',
				'questions'    => [
					[
						'question' => 'What?',
						'answer'   => 'This.',
					],
				],
			]
		);

		$this->assertStringContainsString( '<ol class="rankkernel-faq-list" role="list" style="list-style-type:none;">', $html );
		$this->assertStringContainsString(
			'<h4 class="rankkernel-faq-question"><span class="rankkernel-faq-number">1. </span>What?</h4>',
			$html
		);
		$this->assertStringNotContainsString( '<ul', $html );
		$this->assertStringNotContainsString( 'rankkernel-faq-title', $html );
	}

	public function test_render_single_numbering_without_list_markers(): void {
		$html = ( new FaqBlock() )->render(
			[
				'titleWrapper' => 'h2',
				'listStyle'    => 'ol',
				'questions'    => [
					[
						'question' => 'First?',
						'answer'   => 'One.',
					],
					[
						'question' => '',
						'answer'   => 'Orphan.',
					],
					[
						'question' => 'Second?',
						'answer'   => 'Two.',
					],
				],
			]
		);

		$this->assertStringContainsString( '<ol class="rankkernel-faq-list" role="list" style="list-style-type:none;">', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="rankkernel-faq-item">' ) );
		$this->assertSame( 2, substr_count( $html, 'rankkernel-faq-number' ) );
		$this->assertStringContainsString(
			'<h2 class="rankkernel-faq-question"><span class="rankkernel-faq-number">1. </span>First?</h2>',
			$html
		);
		$this->assertStringContainsString(
			'<h2 class="rankkernel-faq-question"><span class="rankkernel-faq-number">2. </span>Second?</h2>',
			$html
		);
		$this->assertStringNotContainsString( 'decimal', $html );
		$this->assertStringNotContainsString( 'disc', $html );
		$this->assertStringNotContainsString( 'Orphan.', $html );
	}

	public function test_render_unordered_list_uses_bullets_without_numbers(): void {
		$html = ( new FaqBlock() )->render(
			[
				'titleWrapper' => 'h3',
				'listStyle'    => 'ul',
				'questions'    => [
					[
						'question' => 'First?',
						'answer'   => 'One.',
					],
					[
						'question' => 'Second?',
						'answer'   => 'Two.',
					],
				],
			]
		);

		$this->assertStringContainsString( '<ul class="rankkernel-faq-list" role="list" style="list-style-type:disc;">', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="rankkernel-faq-item">' ) );
		$this->assertStringNotContainsString( 'rankkernel-faq-number', $html );
		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">First?</h3>', $html );
		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">Second?</h3>', $html );
	}

	public function test_sequential_updates_keep_untouched_rows_stable(): void {
		$rows = [
			[
				'id'       => 'rkq-a',
				'question' => 'First?',
				'answer'   => 'One.',
			],
			[
				'id'       => 'rkq-b',
				'question' => 'Second?',
				'answer'   => 'Two.',
			],
			[
				'id'       => 'rkq-c',
				'question' => 'Third?',
				'answer'   => 'Three.',
			],
		];

		$before = ( new FaqBlock() )->render( [ 'questions' => $rows ] );

		// Simulate the editor positional update of row 3 only: untouched rows stay byte identical, ids included.
		$updated    = $rows;
		$updated[2] = [
			...$updated[2],
			'answer' => 'Three updated.',
		];

		$this->assertSame( $rows[0], $updated[0] );
		$this->assertSame( $rows[1], $updated[1] );
		$this->assertSame( 'rkq-a', $updated[0]['id'] );
		$this->assertSame( 'rkq-b', $updated[1]['id'] );
		$this->assertSame( 'rkq-c', $updated[2]['id'] );

		$after = ( new FaqBlock() )->render( [ 'questions' => $updated ] );

		$beforeItems = [];
		$afterItems  = [];

		preg_match_all( '@<li class="rankkernel-faq-item">.*?</li>@s', $before, $beforeItems );
		preg_match_all( '@<li class="rankkernel-faq-item">.*?</li>@s', $after, $afterItems );

		$this->assertCount( 3, $beforeItems[0] );
		$this->assertCount( 3, $afterItems[0] );
		$this->assertSame( $beforeItems[0][0], $afterItems[0][0] );
		$this->assertSame( $beforeItems[0][1], $afterItems[0][1] );
		$this->assertStringContainsString( 'Three updated.', $afterItems[0][2] );
		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">Third?</h3>', $afterItems[0][2] );
	}

	public function test_render_escapes_markup_and_skips_empty_rows(): void {
		$html = ( new FaqBlock() )->render(
			[
				'title'        => '<b>Hi</b>',
				'titleWrapper' => 'script',
				'listStyle'    => 'table',
				'questions'    => [
					[
						'question' => '',
						'answer'   => '',
					],
					'junk',
					[
						'question' => '<script>alert(1)</script>',
						'answer'   => 'A.',
					],
				],
			]
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '&lt;b&gt;Hi&lt;/b&gt;', $html );
		$this->assertStringContainsString( '<h3 class="rankkernel-faq-title">', $html );
		$this->assertStringContainsString( '<ul class="rankkernel-faq-list" role="list" style="list-style-type:disc;">', $html );
		$this->assertSame( 1, substr_count( $html, '<li class="rankkernel-faq-item">' ) );
	}

	public function test_render_empty_questions_returns_empty_string(): void {
		$this->assertSame( '', ( new FaqBlock() )->render( [ 'questions' => [] ] ) );
		$this->assertSame( '', ( new FaqBlock() )->render( [] ) );
		$this->assertSame(
			'',
			( new FaqBlock() )->render(
				[
					'questions' => [
						[
							'question' => '',
							'answer'   => '',
						],
					],
				]
			)
		);
	}

	public function test_render_drops_answer_only_rows_like_schema(): void {
		$html = ( new FaqBlock() )->render(
			[
				'titleWrapper' => 'h3',
				'listStyle'    => 'ul',
				'questions'    => [
					[
						'question' => '',
						'answer'   => 'Orphan answer.',
					],
					[
						'question' => 'Kept?',
						'answer'   => 'Yes.',
					],
				],
			]
		);

		$this->assertStringNotContainsString( 'Orphan answer.', $html );
		$this->assertSame( 1, substr_count( $html, '<li class="rankkernel-faq-item">' ) );
		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">Kept?</h3>', $html );
		$this->assertStringNotContainsString( 'rankkernel-faq-number', $html );
	}

	public function test_render_answer_only_rows_render_nothing(): void {
		$this->assertSame(
			'',
			( new FaqBlock() )->render(
				[
					'questions' => [
						[
							'question' => '   ',
							'answer'   => 'Orphan answer.',
						],
					],
				]
			)
		);
	}

	public function test_render_question_tag_follows_title_wrapper_by_default(): void {
		$html = ( new FaqBlock() )->render(
			[
				'title'        => 'Common questions',
				'titleWrapper' => 'h2',
				'questions'    => [
					[
						'question' => 'What?',
						'answer'   => 'This.',
					],
				],
			]
		);

		$this->assertStringContainsString( '<h2 class="rankkernel-faq-title">Common questions</h2>', $html );
		$this->assertStringContainsString(
			'<h2 class="rankkernel-faq-question">What?</h2>',
			$html
		);
	}

	public function test_render_question_tag_override_beats_title_wrapper(): void {
		$html = ( new FaqBlock() )->render(
			[
				'title'        => 'Common questions',
				'titleWrapper' => 'h4',
				'questionTag'  => 'h2',
				'questions'    => [
					[
						'question' => 'What?',
						'answer'   => 'This.',
					],
				],
			]
		);

		$this->assertStringContainsString( '<h4 class="rankkernel-faq-title">Common questions</h4>', $html );
		$this->assertStringContainsString(
			'<h2 class="rankkernel-faq-question">What?</h2>',
			$html
		);
	}

	public function test_render_invalid_question_tag_falls_back_to_title_wrapper(): void {
		$html = ( new FaqBlock() )->render(
			[
				'titleWrapper' => 'h4',
				'questionTag'  => 'script',
				'questions'    => [
					[
						'question' => 'What?',
						'answer'   => 'This.',
					],
				],
			]
		);

		$this->assertStringContainsString(
			'<h4 class="rankkernel-faq-question">What?</h4>',
			$html
		);
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_render_ignores_extra_callback_params(): void {
		$html = ( new FaqBlock() )->render(
			[
				'titleWrapper' => 'h3',
				'questions'    => [
					[
						'question' => 'What?',
						'answer'   => 'This.',
					],
				],
			],
			'<p>Inner content.</p>',
			[ 'blockName' => 'rankkernel/faq' ]
		);

		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">What?</h3>', $html );
		$this->assertStringNotContainsString( 'Inner content.', $html );
	}

	public function test_render_whitespace_only_answer_prints_no_answer_div(): void {
		$html = ( new FaqBlock() )->render(
			[
				'questions' => [
					[
						'question' => 'What?',
						'answer'   => "   \n\t  ",
					],
				],
			]
		);

		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">What?</h3>', $html );
		$this->assertStringNotContainsString( 'rankkernel-faq-answer', $html );
	}

	public function test_render_old_rows_without_id_still_render(): void {
		$html = ( new FaqBlock() )->render(
			[
				'questions' => [
					[
						'question' => 'First?',
						'answer'   => 'One.',
					],
					[
						'question' => 'Second?',
						'answer'   => '',
					],
				],
			]
		);

		$this->assertSame( 2, substr_count( $html, '<li class="rankkernel-faq-item">' ) );
		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">First?</h3>', $html );
		$this->assertStringContainsString( '<h3 class="rankkernel-faq-question">Second?</h3>', $html );
		$this->assertStringNotContainsString( 'rankkernel-faq-number', $html );
		$this->assertSame( 1, substr_count( $html, 'rankkernel-faq-answer' ) );
	}

	public function test_block_only_questions_are_picked_up(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [
						'questions' => [
							[
								'question' => 'Block Q?',
								'answer'   => 'Block A.',
							],
						],
					],
				],
			]
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$piece = new FaqPiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'FAQPage', $build['@type'] );
		$this->assertCount( 1, $build['mainEntity'] );
		$this->assertSame( 'Block Q?', $build['mainEntity'][0]['name'] );
	}

	public function test_payload_and_block_rows_merge_with_dedupe(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'faq' => [
						'questions' => [
							[
								'question' => 'What?',
								'answer'   => 'Payload answer.',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'core/paragraph',
					'attrs'     => [],
				],
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [
						'questions' => [
							[
								'question' => '  WHAT? ',
								'answer'   => 'Duplicate answer.',
							],
							[
								'question' => 'Other?',
								'answer'   => 'Block answer.',
							],
						],
					],
				],
			]
		);

		$build = ( new FaqPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 2, $build['mainEntity'] );
		$this->assertSame( 'What?', $build['mainEntity'][0]['name'] );
		$this->assertSame( 'Payload answer.', $build['mainEntity'][0]['acceptedAnswer']['text'] );
		$this->assertSame( 'Other?', $build['mainEntity'][1]['name'] );
	}

	public function test_no_blocks_and_no_payload_is_not_needed(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<p>Plain content.</p>';
		$this->stubBlocks(
			[
				[
					'blockName' => 'core/paragraph',
					'attrs'     => [],
				],
			]
		);

		$this->assertFalse(
			( new FaqPiece() )->isNeeded( $this->makeContext( $this->singularQuery() ) )
		);
	}

	public function test_malformed_block_attrs_are_ignored(): void {

		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => 'junk',
				],
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [ 'questions' => 'junk' ],
				],
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [
						'questions' => [
							'junk',
							[
								'question' => '',
								'answer'   => 'x',
							],
						],
					],
				],
			]
		);

		$this->assertFalse(
			( new FaqPiece() )->isNeeded( $this->makeContext( $this->singularQuery() ) )
		);
	}

	public function test_schema_strips_question_html_from_names(): void {
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( string $s ): string {
				$clean = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $s );

				return strip_tags( is_string( $clean ) ? $clean : $s ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Faithful wp_strip_all_tags emulation for this test, which cannot call the function it emulates.
			}
		);
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [
						'questions' => [
							[
								'question' => '<b>Bold?</b>',
								'answer'   => 'Yes.',
							],
							[
								'question' => '<script>alert(1)</script>Plain?',
								'answer'   => 'Yes.',
							],
						],
					],
				],
			]
		);

		$build = ( new FaqPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 2, $build['mainEntity'] );
		$this->assertSame( 'Bold?', $build['mainEntity'][0]['name'] );
		$this->assertSame( 'Plain?', $build['mainEntity'][1]['name'] );
		$this->assertStringNotContainsString( '<script>', $build['mainEntity'][1]['name'] );
	}

	public function test_schema_trims_whitespace_only_answers(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [
						'questions' => [
							[
								'question' => 'Spaced?',
								'answer'   => "   \n\t  ",
							],
						],
					],
				],
			]
		);

		$build = ( new FaqPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 1, $build['mainEntity'] );
		$this->assertSame( 'Spaced?', $build['mainEntity'][0]['name'] );
		$this->assertSame( '', $build['mainEntity'][0]['acceptedAnswer']['text'] );
	}

	public function test_answer_only_block_rows_stay_out_of_render_and_schema(): void {
		$attrs = [
			'questions' => [
				[
					'question' => '',
					'answer'   => 'Orphan answer.',
				],
			],
		];

		$this->assertSame( '', ( new FaqBlock() )->render( $attrs ) );

		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => $attrs,
				],
			]
		);

		$piece = new FaqPiece();

		$this->assertFalse( $piece->isNeeded( $this->makeContext( $this->singularQuery() ) ) );
		$this->assertSame( [], $piece->build( $this->makeContext( $this->singularQuery() ) ) );
	}

	public function test_old_block_rows_without_id_feed_schema(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [
						'questions' => [
							[
								'question' => 'Legacy?',
								'answer'   => 'Still works.',
							],
						],
					],
				],
			]
		);

		$build = ( new FaqPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 1, $build['mainEntity'] );
		$this->assertSame( 'Legacy?', $build['mainEntity'][0]['name'] );
	}

	public function test_payload_answers_are_trimmed(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'faq' => [
						'questions' => [
							[
								'question' => 'Padded?',
								'answer'   => '  Padded answer.  ',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<p>No blocks.</p>';
		$this->stubBlocks( [] );

		$build = ( new FaqPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'Padded answer.', $build['mainEntity'][0]['acceptedAnswer']['text'] );
	}

	public function test_missing_permalink_disables_schema(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [
						'questions' => [
							[
								'question' => 'Gated?',
								'answer'   => 'Yes.',
							],
						],
					],
				],
			]
		);
		Functions\when( 'get_permalink' )->justReturn( '' );

		$piece = new FaqPiece();

		$this->assertFalse( $piece->isNeeded( $this->makeContext( $this->singularQuery() ) ) );
		$this->assertSame( [], $piece->build( $this->makeContext( $this->singularQuery() ) ) );
	}

	public function test_merged_rows_cap_at_100(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/faq -->';

		$rows = [];

		for ( $i = 0; $i < 120; ++$i ) {
			$rows[] = [
				'question' => 'Question ' . $i . '?',
				'answer'   => 'Answer.',
			];
		}

		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/faq',
					'attrs'     => [ 'questions' => $rows ],
				],
			]
		);

		$build = ( new FaqPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 100, $build['mainEntity'] );
	}

	public function test_no_per_block_category_registration(): void {
		$this->assertFalse(
			method_exists( FaqBlock::class, 'addCategory' ),
			'Block category registration lives centrally in SchemaModule'
		);
	}
}
