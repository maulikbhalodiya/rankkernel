<?php
/**
 * Analysis list table column tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\AnalysisColumn;
use RankKernel\Modules\Analysis\AnalysisScore;
use RankKernel\Modules\Analysis\Analyzer;
use WP_Post;
use WP_Query;

/**
 * Analysis Column Test.
 */
final class AnalysisColumnTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Meta store.
	 *
	 * @var array<string, mixed>
	 */
	private array $meta = [];

	/**
	 * Captured hooks.
	 *
	 * @var array<int, array{hook: string, priority: int, accepted: int}>
	 */
	private array $hooks = [];

	/**
	 * Captured meta cache primes, each a meta type and a post id list.
	 *
	 * @var array<int, array{0: string, 1: array<int, int>}>
	 */
	private array $metaCaches = [];

	/**
	 * Captured style handles passed to wp_enqueue_style.
	 *
	 * @var string[]
	 */
	private array $enqueuedStyles = [];

	/**
	 * Whether the request under test runs in the admin.
	 *
	 * @var bool
	 */
	private bool $isAdmin = true;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		$this->meta           = [];
		$this->hooks          = [];
		$this->metaCaches     = [];
		$this->enqueuedStyles = [];
		$this->isAdmin        = true;

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html' )->alias( static fn ( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'get_post_types' )->alias(
			static fn (): array => [
				'post'       => 'post',
				'page'       => 'page',
				'attachment' => 'attachment',
			]
		);
		Functions\when( 'get_post_meta' )->alias(
			function ( int $id, string $key, bool $single ): mixed {
				unset( $id, $single );

				return $this->meta[ $key ] ?? '';
			}
		);
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'number_format_i18n' )->alias( static fn ( float $number, int $decimals = 0 ): string => number_format( $number, $decimals ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				unset( $url, $component );

				return '';
			}
		);
		Functions\when( 'is_admin' )->alias(
			function (): bool {
				return $this->isAdmin;
			}
		);
		Functions\when( 'get_current_screen' )->justReturn( null );
		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10, int $accepted = 1 ): bool {
				unset( $callback );

				$this->hooks[] = [
					'hook'     => $hook,
					'priority' => $priority,
					'accepted' => $accepted,
				];

				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10, int $accepted = 1 ): bool {
				unset( $callback );

				$this->hooks[] = [
					'hook'     => $hook,
					'priority' => $priority,
					'accepted' => $accepted,
				];

				return true;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			function ( string $handle ): void {
				$this->enqueuedStyles[] = $handle;
			}
		);
		Functions\when( 'update_meta_cache' )->alias(
			function ( string $type, array $ids ): bool {
				$this->metaCaches[] = [ $type, array_values( $ids ) ];

				return true;
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
	 * Render the column for a post id.
	 *
	 * @param int $postId Post id.
	 * @return string The markup.
	 */
	private function render( int $postId = 7 ): string {
		ob_start();
		( new AnalysisColumn() )->renderColumn( 'rankkernel_analysis', $postId );

		return (string) ob_get_clean();
	}

	/**
	 * Register wires the deferred type pass and the generic hooks.
	 */
	public function test_register_wires_the_generic_hooks(): void {
		( new AnalysisColumn() )->register();

		$hooks = array_column( $this->hooks, 'hook' );

		$this->assertContains( 'admin_init', $hooks );
		$this->assertContains( 'pre_get_posts', $hooks );
		$this->assertContains( 'the_posts', $hooks );
		$this->assertContains( 'admin_enqueue_scripts', $hooks );
	}

	/**
	 * The deferred pass wires a column, a sortable header and a sort per type.
	 */
	public function test_register_columns_wires_the_column(): void {
		( new AnalysisColumn() )->registerColumns();

		$hooks = array_column( $this->hooks, 'hook' );

		$this->assertContains( 'manage_post_posts_columns', $hooks );
		$this->assertContains( 'manage_post_posts_custom_column', $hooks );
		$this->assertContains( 'manage_edit-post_sortable_columns', $hooks );
		$this->assertContains( 'manage_page_posts_columns', $hooks );
	}

	/**
	 * The supported type rule drives registration, so attachments get nothing.
	 */
	public function test_register_skips_attachments(): void {
		( new AnalysisColumn() )->registerColumns();

		$hooks = array_column( $this->hooks, 'hook' );

		$this->assertNotContains( 'manage_attachment_posts_columns', $hooks );
		$this->assertNotContains( 'manage_edit-attachment_sortable_columns', $hooks );
	}

	/**
	 * A public CPT registered after the module boots still gets a column,
	 * because the supported type list is resolved when admin_init fires.
	 */
	public function test_late_registered_post_type_gets_a_column(): void {
		Functions\when( 'get_post_types' )->alias(
			static fn (): array => [
				'post'       => 'post',
				'attachment' => 'attachment',
			]
		);

		$column = new AnalysisColumn();
		$column->register();

		// The CPT registers after the analysis module booted.
		Functions\when( 'get_post_types' )->alias(
			static fn (): array => [
				'post'       => 'post',
				'product'    => 'product',
				'attachment' => 'attachment',
			]
		);

		$column->registerColumns();

		$hooks = array_column( $this->hooks, 'hook' );

		$this->assertContains( 'manage_product_posts_columns', $hooks );
		$this->assertContains( 'manage_edit-product_sortable_columns', $hooks );
	}

	/**
	 * The column is inserted directly after the title column.
	 */
	public function test_column_is_inserted_after_title(): void {
		$columns = [
			'cb'    => 'Checkbox',
			'title' => 'Title',
			'date'  => 'Date',
		];

		$out = ( new AnalysisColumn() )->addColumn( $columns );

		$this->assertSame( [ 'cb', 'title', 'rankkernel_analysis', 'date' ], array_keys( $out ) );
		$this->assertSame( 'Score', $out['rankkernel_analysis'] );
	}

	/**
	 * An analysed score renders the number, the band class and a spoken label.
	 */
	public function test_render_analysed_state(): void {
		$this->meta[ AnalysisScore::META_KEY ] = [
			'score'         => 87,
			'band'          => Analyzer::BAND_GOOD,
			'keywords'      => 1,
			'rules_version' => Analyzer::RULES_VERSION,
			'analysed_at'   => 123,
		];

		$out = $this->render();

		$this->assertStringContainsString( 'rk-badge-ok', $out );
		$this->assertStringContainsString( '87', $out );
		$this->assertStringContainsString( 'Good', $out );
	}

	/**
	 * A post with no record renders the neutral not analysed state.
	 */
	public function test_render_not_analysed_state(): void {
		$out = $this->render();

		$this->assertStringContainsString( 'rk-badge-none', $out );
		$this->assertStringContainsString( 'Not analysed', $out );
	}

	/**
	 * A record with an older rules version renders needs recheck with the reason.
	 */
	public function test_render_needs_recheck_state(): void {
		$this->meta[ AnalysisScore::META_KEY ] = [
			'score'         => 87,
			'band'          => Analyzer::BAND_GOOD,
			'keywords'      => 1,
			'rules_version' => Analyzer::RULES_VERSION - 1,
			'analysed_at'   => 123,
		];

		$out = $this->render();

		$this->assertStringContainsString( 'rk-badge-none', $out );
		$this->assertStringContainsString( 'Needs recheck', $out );
		$this->assertStringContainsString( 'rules version', $out );
	}

	/**
	 * The sortable filter registers our own column only.
	 */
	public function test_sortable_column_registration(): void {
		$out = ( new AnalysisColumn() )->addSortableColumn( [ 'title' => 'title' ] );

		$this->assertSame( 'rankkernel_analysis', $out['rankkernel_analysis'] );
	}

	/**
	 * The orderby guard ignores another column.
	 */
	public function test_orderby_ignores_another_column(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );
		$query->shouldReceive( 'get' )->with( 'orderby' )->andReturn( 'date' );
		$query->shouldReceive( 'set' )->never();

		( new AnalysisColumn() )->orderBy( $query );
	}

	/**
	 * The orderby guard ignores a query that is not the main query.
	 */
	public function test_orderby_ignores_a_non_main_query(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( false );
		$query->shouldReceive( 'get' )->never();
		$query->shouldReceive( 'set' )->never();

		( new AnalysisColumn() )->orderBy( $query );
	}

	/**
	 * Score rows for the sort tests, one row per post.
	 *
	 * @return array<int, array{id: int, score: int|null}> Fixture rows.
	 */
	private function scoreRows(): array {
		return [
			[
				'id'    => 11,
				'score' => 87,
			],
			[
				'id'    => 22,
				'score' => null,
			],
			[
				'id'    => 33,
				'score' => 43,
			],
		];
	}

	/**
	 * Run orderBy against a query double and return the vars it set.
	 *
	 * @param string $order Order value as the list table sends it.
	 * @return array<string, mixed> The query vars our code set.
	 */
	private function sortedVars( string $order ): array {
		$vars  = [];
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_main_query' )->andReturn( true );
		$query->shouldReceive( 'get' )->with( 'orderby' )->andReturn( 'rankkernel_analysis' );
		$query->shouldReceive( 'get' )->with( 'order' )->andReturn( $order );
		$query->shouldReceive( 'set' )->andReturnUsing(
			static function ( string $key, mixed $value ) use ( &$vars ): void {
				$vars[ $key ] = $value;
			}
		);

		( new AnalysisColumn() )->orderBy( $query );

		return $vars;
	}

	/**
	 * Apply the sort the way WordPress and MySQL apply it.
	 *
	 * A meta query with a NOT EXISTS clause becomes a left join, so a post
	 * without the meta stays in the result, and a named NUMERIC clause orders
	 * by the cast meta value. MySQL orders a NULL first ascending and last
	 * descending, which is where a post without a score lands.
	 *
	 * @param array<string, mixed>                        $vars Query vars set by orderBy.
	 * @param array<int, array{id: int, score: int|null}> $rows Fixture rows.
	 * @return int[] The surviving post ids in sort order.
	 */
	private function sortedIds( array $vars, array $rows ): array {
		$this->assertSame( 'OR', $vars['meta_query']['relation'] );

		$keepsMissing = false;
		$sortsNumeric = false;

		foreach ( $vars['meta_query'] as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
				continue;
			}

			$this->assertSame( AnalysisScore::SCORE_VALUE_KEY, $clause['key'] );

			if ( 'NOT EXISTS' === $clause['compare'] ) {
				$keepsMissing = true;
			}

			if ( 'NUMERIC' === ( $clause['type'] ?? '' ) ) {
				$sortsNumeric = true;
			}
		}

		$this->assertTrue( $keepsMissing, 'the sort must keep posts without a score' );
		$this->assertTrue( $sortsNumeric, 'the sort must read the score as a number' );

		$this->assertIsArray( $vars['orderby'] );
		$order = strtoupper( (string) reset( $vars['orderby'] ) );

		usort(
			$rows,
			static function ( array $a, array $b ) use ( $order ): int {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}

				if ( null === $a['score'] ) {
					return 'ASC' === $order ? -1 : 1;
				}

				if ( null === $b['score'] ) {
					return 'ASC' === $order ? 1 : -1;
				}

				return 'ASC' === $order ? $a['score'] <=> $b['score'] : $b['score'] <=> $a['score'];
			}
		);

		return array_map( static fn ( array $row ): int => $row['id'], $rows );
	}

	/**
	 * Descending score order puts the best first and the unscored post last.
	 */
	public function test_orderby_descending_scores_keeps_the_unscored_post(): void {
		$this->assertSame( [ 11, 33, 22 ], $this->sortedIds( $this->sortedVars( 'DESC' ), $this->scoreRows() ) );
	}

	/**
	 * Ascending score order reverses the scores and keeps the unscored post.
	 */
	public function test_orderby_ascending_scores_keeps_the_unscored_post(): void {
		$this->assertSame( [ 22, 33, 11 ], $this->sortedIds( $this->sortedVars( 'ASC' ), $this->scoreRows() ) );
	}

	/**
	 * A missing order value falls back to descending.
	 */
	public function test_orderby_defaults_to_descending(): void {
		$this->assertSame( [ 11, 33, 22 ], $this->sortedIds( $this->sortedVars( '' ), $this->scoreRows() ) );
	}

	/**
	 * The meta cache is primed once for the queried posts.
	 */
	public function test_meta_cache_is_primed_for_the_queried_posts(): void {
		$postOne     = new WP_Post();
		$postOne->ID = 4;
		$postTwo     = new WP_Post();
		$postTwo->ID = 9;

		$out = ( new AnalysisColumn() )->primeMetaCache( [ $postOne, $postTwo ] );

		$this->assertSame( [ $postOne, $postTwo ], $out );
		$this->assertSame( [ [ 'post', [ 4, 9 ] ] ], $this->metaCaches );
	}

	/**
	 * The meta cache prime is admin only, because the frontend query primes it.
	 */
	public function test_meta_cache_is_primed_in_admin_only(): void {
		$post     = new WP_Post();
		$post->ID = 4;

		$this->isAdmin = false;

		$out = ( new AnalysisColumn() )->primeMetaCache( [ $post ] );

		$this->assertSame( [ $post ], $out );
		$this->assertSame( [], $this->metaCaches );

		$this->isAdmin = true;

		$out = ( new AnalysisColumn() )->primeMetaCache( [ $post ] );

		$this->assertSame( [ $post ], $out );
		$this->assertSame( [ [ 'post', [ 4 ] ] ], $this->metaCaches );
	}

	/**
	 * Assets are enqueued on the list screen only.
	 */
	public function test_assets_are_enqueued_on_the_list_screen(): void {
		( new AnalysisColumn() )->enqueueAssets( 'edit.php' );

		$this->assertSame( [ 'rankkernel-analysis-column' ], $this->enqueuedStyles );
	}

	/**
	 * Assets are not enqueued on another screen.
	 */
	public function test_assets_are_not_enqueued_elsewhere(): void {
		( new AnalysisColumn() )->enqueueAssets( 'options-general.php' );

		$this->assertSame( [], $this->enqueuedStyles );
	}
}
