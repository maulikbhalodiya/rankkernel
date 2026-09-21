<?php
/**
 * Compute the analysis score on save.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the score service against the saved row at priority 20.
 *
 * The guard order follows SchemaMetabox rather than MetadataBox. MetadataBox
 * returns early when its metabox fields are absent, which is exactly the case
 * for a block editor save, so its order can never be reused here. There is no
 * user facing error for a missing capability, because this is derived data on
 * a save that another handler already authorised or refused.
 */
final class AnalysisSaveHandler {
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
	 * Register the save hook.
	 */
	public function register(): void {
		add_action( 'save_post', [ $this, 'handle' ], 20, 2 );
	}

	/**
	 * Guard, then store the score for the saved row.
	 *
	 * @param int   $postId Current post id.
	 * @param mixed $post   Current post object.
	 */
	public function handle( int $postId, mixed $post = null ): void {
		// The hook supplies the saved object, but the run re-reads the row
		// through the score service, so this argument is deliberately unused.
		unset( $post );

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $postId ) ) {
			return;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $postId ) ) {
			return;
		}

		$postType = function_exists( 'get_post_type' ) ? (string) get_post_type( $postId ) : '';

		if ( ! AnalysisScore::isSupportedType( $postType ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}

		$this->score->store( $postId );
	}
}
