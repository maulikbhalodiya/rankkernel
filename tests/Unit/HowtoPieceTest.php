<?php
/**
 * HowTo piece tests, schema hardening for block fed HowTo nodes.
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
use RankKernel\Modules\Schema\Pieces\HowtoPiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Howto Piece Test.
 */
final class HowtoPieceTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Post Content.
	 *
	 * @var string
	 */
	private string $postContent = '';

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->postContent = '';

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating WP core, which uses its own C implementation here.
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => trim( $v ) );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => trim( strip_tags( $v, '<p><a><br><b><i><strong><em>' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating the kses allowlist with a native tag filter.
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating WP core tag stripping with a native filter.
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
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\when( 'parse_blocks' )->justReturn( [] );
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a singular query mock.
	 *
	 * @param int $id Id.
	 * @return WP_Query The result.
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

	/**
	 * Make Context.
	 *
	 * @param WP_Query $query Query.
	 * @return Context The result.
	 */
	private function makeContext( WP_Query $query ): Context {
		return new Context( $query, new SettingsStore() );
	}

	/**
	 * Stub Post Meta.
	 *
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
	 * Stub Blocks.
	 *
	 * @param array<int, mixed> $blocks Parsed blocks to return.
	 */
	private function stubBlocks( array $blocks ): void {
		Functions\when( 'parse_blocks' )->alias(
			static function () use ( $blocks ): array {
				return $blocks;
			}
		);
	}

	/**
	 * Test pages are included on singular views.
	 */
	public function test_pages_are_included_on_singular_views(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'steps' => [
							[
								'title' => 'Page step',
								'text'  => 'Page text.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$piece = new HowtoPiece();

		$this->assertSame( 'post', $ctx->queriedType(), 'Every singular view, pages included, reports the post type' );
		$this->assertTrue( $piece->isNeeded( $ctx ) );
		$this->assertSame( 'Page step', $piece->build( $ctx )['step'][0]['name'] );
	}

	/**
	 * Test unsafe image urls never reach schema.
	 */
	public function test_unsafe_image_urls_never_reach_schema(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
						'steps' => [
							[
								'title' => 'Script',
								'text'  => 'Kept.',
								'image' => 'javascript:alert(1)',
							],
							[
								'title' => 'Data',
								'text'  => 'Kept.',
								'image' => 'data:image/png;base64,AAA',
							],
							[
								'title' => 'Spaced',
								'text'  => 'Kept.',
								'image' => '  JAVASCRIPT:alert(2)  ',
							],
							[
								'title' => 'Fine',
								'text'  => 'Kept.',
								'image' => '  https://example.com/ok.png  ',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 4, $build['step'] );
		$this->assertArrayNotHasKey( 'image', $build['step'][0] );
		$this->assertArrayNotHasKey( 'image', $build['step'][1] );
		$this->assertArrayNotHasKey( 'image', $build['step'][2] );
		$this->assertSame( 'https://example.com/ok.png', $build['step'][3]['image'] );
	}

	/**
	 * Test block image urls are validated.
	 */
	public function test_block_image_urls_are_validated(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'steps' => [
							[
								'title' => 'Tricky',
								'text'  => 'Kept.',
								'image' => 'javascript:alert(1)',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'Tricky', $build['step'][0]['name'] );
		$this->assertArrayNotHasKey( 'image', $build['step'][0] );
	}

	/**
	 * Test invalid total time is dropped.
	 */
	public function test_invalid_total_time_is_dropped(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'      => 'Guide',
						'totalTime' => 'soon',
						'steps'     => [
							[
								'title' => 'T',
								'text'  => 'S',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertArrayNotHasKey( 'totalTime', $build );
	}

	/**
	 * Test block total time fills in when meta value is invalid.
	 */
	public function test_block_total_time_fills_in_when_meta_value_is_invalid(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'      => 'Guide',
						'totalTime' => 'soon',
						'steps'     => [
							[
								'title' => 'T',
								'text'  => 'S',
								'image' => '',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'totalTime' => 'PT1H',
						'steps'     => [
							[
								'title' => 'B',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'PT1H', $build['totalTime'] );
	}

	/**
	 * Test meta total time wins over block value.
	 */
	public function test_meta_total_time_wins_over_block_value(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'      => 'Guide',
						'totalTime' => 'PT1H',
						'steps'     => [
							[
								'title' => 'T',
								'text'  => 'S',
								'image' => '',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'totalTime' => 'PT2H',
						'steps'     => [
							[
								'title' => 'B',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'PT1H', $build['totalTime'] );
	}

	/**
	 * Test block title feeds schema name when meta name is absent.
	 */
	public function test_block_title_feeds_schema_name_when_meta_name_is_absent(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'title' => 'Block guide',
						'steps' => [
							[
								'title' => 'B',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'Block guide', $build['name'] );
	}

	/**
	 * Test meta name wins over block title.
	 */
	public function test_meta_name_wins_over_block_title(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Meta guide',
						'steps' => [
							[
								'title' => 'T',
								'text'  => 'S',
								'image' => '',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'title' => 'Block guide',
						'steps' => [
							[
								'title' => 'B',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'Meta guide', $build['name'] );
	}

	/**
	 * Test merge order is payload first then blocks.
	 */
	public function test_merge_order_is_payload_first_then_blocks(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
						'steps' => [
							[
								'title' => 'First',
								'text'  => 'Meta.',
								'image' => '',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'steps' => [
							[
								'title' => 'Second',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'First', $build['step'][0]['name'] );
		$this->assertSame( 'Second', $build['step'][1]['name'] );
	}

	/**
	 * Test dedupe keeps steps differing only by image.
	 */
	public function test_dedupe_keeps_steps_differing_only_by_image(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
						'steps' => [
							[
								'title' => 'Mix',
								'text'  => 'Stir.',
								'image' => 'https://example.com/a.png',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'steps' => [
							[
								'title' => 'Mix',
								'text'  => 'Stir.',
								'image' => 'https://example.com/b.png',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 2, $build['step'] );
		$this->assertSame( 'https://example.com/a.png', $build['step'][0]['image'] );
		$this->assertSame( 'https://example.com/b.png', $build['step'][1]['image'] );
	}

	/**
	 * Test dedupe drops exact image duplicates.
	 */
	public function test_dedupe_drops_exact_image_duplicates(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
						'steps' => [
							[
								'title' => 'Mix',
								'text'  => 'Stir.',
								'image' => 'https://example.com/a.png',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'steps' => [
							[
								'title' => 'Mix',
								'text'  => 'Stir.',
								'image' => 'https://example.com/a.png',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 1, $build['step'] );
	}

	/**
	 * Test image only and whitespace steps are dropped.
	 */
	public function test_image_only_and_whitespace_steps_are_dropped(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
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
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 1, $build['step'] );
		$this->assertSame( 'Kept', $build['step'][0]['name'] );
	}

	/**
	 * Test block description tools materials emit schema fields.
	 */
	public function test_block_description_tools_materials_emit_schema_fields(): void {
		$this->stubPostMeta( [] );
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'title'       => 'Guide',
						'description' => 'Block desc.',
						'tools'       => [ 'Bowl', 'bowl', '' ],
						'materials'   => [ 'Flour' ],
						'steps'       => [
							[
								'title' => 'B',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( 'Block desc.', $build['description'] );
		$this->assertSame( [ 'Bowl' ], $build['tool'] );
		$this->assertSame( [ 'Flour' ], $build['supply'] );
	}

	/**
	 * Test full meta list starves block rows at 100.
	 */
	public function test_full_meta_list_starves_block_rows_at_100(): void {
		$rows = [];

		for ( $i = 0; $i < 100; ++$i ) {
			$rows[] = [
				'title' => 'Step ' . $i,
				'text'  => 'Do it.',
				'image' => '',
			];
		}

		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
						'steps' => $rows,
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'steps' => [
							[
								'title' => 'Block extra',
								'text'  => 'Never fits.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertCount( 100, $build['step'] );
		$this->assertSame( 'Step 99', $build['step'][99]['name'] );
	}

	/**
	 * Test cost prefers meta else block estimated cost.
	 */
	public function test_cost_prefers_meta_else_block_estimated_cost(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
						'cost'  => '5 USD',
						'steps' => [
							[
								'title' => 'T',
								'text'  => 'S',
								'image' => '',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'estimatedCost' => '9 USD',
						'steps'         => [
							[
								'title' => 'B',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( '5 USD', $build['estimatedCost'] );
	}

	/**
	 * Test block estimated cost fills in when meta cost is absent.
	 */
	public function test_block_estimated_cost_fills_in_when_meta_cost_is_absent(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'howto' => [
						'name'  => 'Guide',
						'steps' => [
							[
								'title' => 'T',
								'text'  => 'S',
								'image' => '',
							],
						],
					],
				],
			]
		);
		$this->postContent = '<!-- wp:rankkernel/howto -->';
		$this->stubBlocks(
			[
				[
					'blockName' => 'rankkernel/howto',
					'attrs'     => [
						'estimatedCost' => '9 USD',
						'steps'         => [
							[
								'title' => 'B',
								'text'  => 'Block.',
								'image' => '',
							],
						],
					],
				],
			]
		);

		$build = ( new HowtoPiece() )->build( $this->makeContext( $this->singularQuery() ) );

		$this->assertSame( '9 USD', $build['estimatedCost'] );
	}
}
