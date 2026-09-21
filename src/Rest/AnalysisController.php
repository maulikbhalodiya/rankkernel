<?php
/**
 * REST content analysis controller.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Rest;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Analysis\Analyzer;
use RankKernel\Modules\Analysis\KeywordIndex;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles POST /rankkernel/v1/analysis.
 *
 * The editor posts the content it currently holds rather than a post id alone,
 * because a writer wants the analysis of what they have typed, not of the last
 * saved revision. The content is read for counting and never stored, escaped or
 * echoed, and the route is gated on the capability to edit that post.
 */
final class AnalysisController {
	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'rankkernel/v1';

	/**
	 * Constructor.
	 *
	 * @param Analyzer|null     $analyzer Optional analyser, for tests.
	 * @param KeywordIndex|null $index    Optional keyword index, for tests.
	 */
	public function __construct(
		private readonly ?Analyzer $analyzer = null,
		private readonly ?KeywordIndex $index = null
	) {
	}

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/analysis',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'analyze' ],
				'permission_callback' => [ $this, 'checkPermission' ],
				'args'                => [
					'post_id'     => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'title'       => [
						'type'    => 'string',
						'default' => '',
					],
					'description' => [
						'type'    => 'string',
						'default' => '',
					],
					'slug'        => [
						'type'    => 'string',
						'default' => '',
					],
					'content'     => [
						'type'    => 'string',
						'default' => '',
					],
					'keywords'    => [
						'type'    => 'array',
						'default' => [],
						'items'   => [ 'type' => 'string' ],
					],
				],
			]
		);
	}

	/**
	 * Check permission for the requested post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error The result.
	 */
	public function checkPermission( WP_REST_Request $request ): bool|WP_Error {
		$postId = absint( $request->get_param( 'post_id' ) );

		if ( $postId > 0 && current_user_can( 'edit_post', $postId ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'Sorry, you are not allowed to analyse this content.', 'rankkernel' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Analyse the supplied content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The result.
	 */
	public function analyze( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$postId = absint( $request->get_param( 'post_id' ) );
		$post   = get_post( $postId );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'rankkernel_unknown_post',
				esc_html__( 'That post could not be found.', 'rankkernel' ),
				[ 'status' => 404 ]
			);
		}

		$keywords = $request->get_param( 'keywords' );
		$keywords = is_array( $keywords ) ? array_map( 'strval', $keywords ) : [];

		$analyzer = $this->analyzer ?? new Analyzer();

		$result = $analyzer->analyze(
			[
				'html'          => (string) $request->get_param( 'content' ),
				'title'         => (string) $request->get_param( 'title' ),
				'description'   => (string) $request->get_param( 'description' ),
				'slug'          => (string) $request->get_param( 'slug' ),
				'keywords'      => $keywords,
				'site_url'      => home_url( '/' ),
				'featured_alt'  => $this->featuredAlt( $postId ),
				'used_keywords' => $this->usedKeywords( $keywords, $postId ),
			]
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Alt text of the featured image, when one is set.
	 *
	 * @param int $postId Post id.
	 * @return string The result.
	 */
	private function featuredAlt( int $postId ): string {
		$thumbnail = (int) get_post_thumbnail_id( $postId );

		if ( $thumbnail <= 0 ) {
			return '';
		}

		return (string) get_post_meta( $thumbnail, '_wp_attachment_image_alt', true );
	}

	/**
	 * Titles of other posts that already target the primary keyword.
	 *
	 * Null means the check does not apply and an empty list means the keyword is
	 * free. Only the primary keyword is looked up, because the warning is about
	 * the term the page is built around.
	 *
	 * @param string[] $keywords Keywords.
	 * @param int      $postId   Post being edited.
	 * @return string[]|null The result.
	 */
	private function usedKeywords( array $keywords, int $postId ): ?array {
		if ( [] === $keywords ) {
			return null;
		}

		$index = $this->index ?? new KeywordIndex();

		return $index->usedElsewhere( $keywords[0], $postId );
	}
}
