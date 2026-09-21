<?php
/**
 * Analysis score list table column.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

use RankKernel\Plugin;
use WP_Query;

/**
 * Renders the stored score on the list table for each supported post type.
 *
 * The column reads one value per row and the meta cache is primed for the
 * queried posts, so a large list is not one query per row. The module owns
 * this class, so a disabled module registers no hook and adds no asset.
 */
final class AnalysisColumn {
	/**
	 * Column key.
	 */
	private const COLUMN = 'rankkernel_analysis';

	/**
	 * Style handle.
	 */
	private const STYLE_HANDLE = 'rankkernel-analysis-column';

	/**
	 * Score service.
	 *
	 * @var AnalysisScore
	 */
	private AnalysisScore $score;

	/**
	 * Constructor.
	 *
	 * @param AnalysisScore|null $score Optional score service, for tests.
	 */
	public function __construct( ?AnalysisScore $score = null ) {
		$this->score = $score ?? new AnalysisScore();
	}

	/**
	 * Register the column, its sort, the cache priming and the asset gate.
	 */
	public function register(): void {
		foreach ( AnalysisScore::supportedPostTypes() as $postType ) {
			add_filter( 'manage_' . $postType . '_posts_columns', [ $this, 'addColumn' ] );
			add_action( 'manage_' . $postType . '_posts_custom_column', [ $this, 'renderColumn' ], 10, 2 );
			add_filter( 'manage_edit-' . $postType . '_sortable_columns', [ $this, 'addSortableColumn' ] );
		}

		add_action( 'pre_get_posts', [ $this, 'orderBy' ] );
		add_filter( 'the_posts', [ $this, 'primeMetaCache' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
	}

	/**
	 * Insert the score column directly after the title.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string> The result.
	 */
	public function addColumn( array $columns ): array {
		$out = [];

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out[ self::COLUMN ] = __( 'Score', 'rankkernel' );
			}
		}

		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'Score', 'rankkernel' );
		}

		return $out;
	}

	/**
	 * Render one cell in the three documented states.
	 *
	 * @param string $column Column key.
	 * @param int    $postId Post id.
	 */
	public function renderColumn( string $column, int $postId ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$state = $this->score->state( $postId );

		if ( AnalysisScore::STATUS_ANALYSED === $state['status'] ) {
			$band    = (string) $state['band'];
			$classes = 'rk-analysis-col ' . $this->bandClass( $band );
			$label   = sprintf(
				/* translators: 1: score, 2: band label. */
				__( 'Score %1$d out of 100, %2$s.', 'rankkernel' ),
				(int) $state['score'],
				AnalysisScore::bandLabel( $band )
			);

			echo '<span class="' . esc_attr( $classes ) . '"><span class="screen-reader-text">' . esc_html( $label ) . '</span>' . esc_html( (string) $state['score'] ) . '</span>';

			return;
		}

		if ( AnalysisScore::STATUS_NEEDS_RECHECK === $state['status'] ) {
			echo '<span class="rk-analysis-col rk-badge-none" title="' . esc_attr( (string) $state['reason'] ) . '">' . esc_html__( 'Needs recheck', 'rankkernel' ) . '</span>';

			return;
		}

		echo '<span class="rk-analysis-col rk-badge-none">' . esc_html__( 'Not analysed', 'rankkernel' ) . '</span>';
	}

	/**
	 * Register our column as sortable.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string> The result.
	 */
	public function addSortableColumn( array $columns ): array {
		$columns[ self::COLUMN ] = self::COLUMN;

		return $columns;
	}

	/**
	 * Apply a numeric meta sort for our column only.
	 *
	 * @param WP_Query $query Query.
	 */
	public function orderBy( WP_Query $query ): void {
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		if ( self::COLUMN !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', AnalysisScore::META_KEY );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Prime the meta cache for the queried posts on the admin list screens.
	 *
	 * WordPress already primes the cache for the queried posts on the frontend,
	 * so running there as well would only duplicate a query the query itself
	 * makes. The handler returns the posts untouched outside the admin.
	 *
	 * @param array<int, mixed> $posts Posts.
	 * @param mixed             $query Query.
	 * @return array<int, mixed> The result.
	 */
	public function primeMetaCache( array $posts, mixed $query = null ): array {
		// The hook supplies the query object, but only the posts matter here.
		unset( $query );

		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return $posts;
		}

		$ids = [];

		foreach ( $posts as $post ) {
			if ( is_object( $post ) && isset( $post->ID ) ) {
				$ids[] = (int) $post->ID;
			}
		}

		if ( [] !== $ids && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $ids );
		}

		return $posts;
	}

	/**
	 * Enqueue the column stylesheet on the list screens that render it.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( 'edit.php' !== $hookSuffix ) {
			return;
		}

		$postType = '';

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( is_object( $screen ) ) {
				$postType = (string) $screen->post_type;
			}
		}

		if ( '' !== $postType && ! AnalysisScore::isSupportedType( $postType ) ) {
			return;
		}

		$pluginFile = defined( 'RANKKERNEL_FILE' ) ? (string) RANKKERNEL_FILE : '';
		$src        = function_exists( 'plugins_url' ) ? plugins_url( 'assets/css/analysis-column.css', $pluginFile ) : '';

		wp_register_style( self::STYLE_HANDLE, $src, [], Plugin::version() );
		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/**
	 * Map a band to the shared badge class.
	 *
	 * @param string $band Band.
	 * @return string The result.
	 */
	private function bandClass( string $band ): string {
		return match ( $band ) {
			Analyzer::BAND_GOOD    => 'rk-badge-ok',
			Analyzer::BAND_IMPROVE => 'rk-badge-warn',
			Analyzer::BAND_PROBLEM => 'rk-badge-bad',
			default                => 'rk-badge-none',
		};
	}
}
