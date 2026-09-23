<?php
/**
 * Stored analysis score record and state.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the _rankkernel_analysis_score meta key.
 *
 * The record is deliberately separate from _rankkernel_meta_data, because the
 * payload key is written by the editor and a computed value must never mix
 * into a user editable payload where a later write could drop it.
 */
final class AnalysisScore {
	/**
	 * Post meta key holding the score record.
	 */
	public const META_KEY = '_rankkernel_analysis_score';

	/**
	 * Post meta key holding the score on its own, for list table sorting.
	 *
	 * The record above is a serialized array, so MySQL reads it as zero and a
	 * meta_value_num sort on it ties every row. This mirror is written with
	 * the record and holds only the numeric score, which is what the list
	 * table orders on. The record stays the source of truth for rendering,
	 * and a save that clears the record clears this key with it.
	 */
	public const SCORE_VALUE_KEY = '_rankkernel_analysis_score_value';

	/**
	 * Meta key holding the editor payload, read for the keywords and description.
	 */
	private const PAYLOAD_KEY = '_rankkernel_meta_data';

	/**
	 * Supported bands.
	 *
	 * @var string[]
	 */
	private const BANDS = [ Analyzer::BAND_GOOD, Analyzer::BAND_IMPROVE, Analyzer::BAND_PROBLEM ];

	/**
	 * State statuses.
	 */
	public const STATUS_ANALYSED      = 'analysed';
	public const STATUS_NOT_ANALYSED  = 'not_analysed';
	public const STATUS_NEEDS_RECHECK = 'needs_recheck';

	/**
	 * Analyser, injected in tests.
	 *
	 * @var Analyzer
	 */
	private Analyzer $analyzer;

	/**
	 * Constructor.
	 *
	 * @param Analyzer|null $analyzer Optional analyser, for tests.
	 */
	public function __construct( ?Analyzer $analyzer = null ) {
		$this->analyzer = $analyzer ?? new Analyzer();
	}

	/**
	 * Public post types that can carry a score, attachments excluded.
	 *
	 * @return string[] The result.
	 */
	public static function supportedPostTypes(): array {
		$types = function_exists( 'get_post_types' ) ? get_post_types( [ 'public' => true ] ) : [];

		if ( ! is_array( $types ) ) {
			return [];
		}

		$out = [];

		foreach ( array_values( $types ) as $type ) {
			if ( 'attachment' !== (string) $type ) {
				$out[] = (string) $type;
			}
		}

		return $out;
	}

	/**
	 * Whether a post type can carry a score.
	 *
	 * @param string $postType Post type slug.
	 * @return bool The result.
	 */
	public static function isSupportedType( string $postType ): bool {
		if ( '' === $postType || 'attachment' === $postType ) {
			return false;
		}

		return in_array( $postType, self::supportedPostTypes(), true );
	}

	/**
	 * Compute the record for a post without writing it.
	 *
	 * The run uses the saved row, the stored keywords and description, the
	 * featured image alt and the site URL. The uniqueness lookup is skipped
	 * on purpose: it is the only check that queries, it carries weight 0, so
	 * it cannot move the score, and skipping it saves a query per save and
	 * per batch item while producing the same number the editor reports.
	 *
	 * @param int $postId Post id.
	 * @return array<string, mixed>|null Record, or null when there are no keywords.
	 */
	public function compute( int $postId ): ?array {
		$post = function_exists( 'get_post' ) ? get_post( $postId ) : null;

		if ( ! is_object( $post ) ) {
			return null;
		}

		$payload  = $this->payload( $postId );
		$keywords = [];

		if ( isset( $payload['focus_keywords'] ) && is_array( $payload['focus_keywords'] ) ) {
			foreach ( $payload['focus_keywords'] as $keyword ) {
				$keywords[] = (string) $keyword;
			}
		}

		if ( [] === $keywords ) {
			return null;
		}

		$description = isset( $payload['description'] ) ? (string) $payload['description'] : '';

		$result = $this->analyzer->analyze(
			[
				'html'          => (string) $post->post_content,
				'title'         => (string) $post->post_title,
				'description'   => $description,
				'slug'          => (string) $post->post_name,
				'keywords'      => $keywords,
				'site_url'      => function_exists( 'home_url' ) ? (string) home_url( '/' ) : '',
				'featured_alt'  => self::featuredAlt( $postId ),
				'used_keywords' => null,
			]
		);

		$analysed = is_array( $result['keywords'] ) ? count( $result['keywords'] ) : 0;

		return [
			'score'         => (int) $result['score'],
			'band'          => (string) $result['band'],
			'keywords'      => $analysed,
			'rules_version' => Analyzer::RULES_VERSION,
			'analysed_at'   => time(),
		];
	}

	/**
	 * Compute and store the record, clearing it when there are no keywords.
	 *
	 * @param int $postId Post id.
	 * @return array<string, mixed> Stored record, or an empty array when cleared.
	 */
	public function store( int $postId ): array {
		$record = $this->compute( $postId );

		if ( null === $record ) {
			if ( function_exists( 'delete_post_meta' ) ) {
				delete_post_meta( $postId, self::META_KEY );
				delete_post_meta( $postId, self::SCORE_VALUE_KEY );
			}

			return [];
		}

		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $postId, self::META_KEY, $record );
			update_post_meta( $postId, self::SCORE_VALUE_KEY, (int) $record['score'] );
		}

		return $record;
	}

	/**
	 * Read and normalize the stored record.
	 *
	 * @param int $postId Post id.
	 * @return array<string, mixed> Record, or an empty array.
	 */
	public function read( int $postId ): array {
		$raw = function_exists( 'get_post_meta' ) ? get_post_meta( $postId, self::META_KEY, true ) : [];

		return self::sanitize( $raw );
	}

	/**
	 * The rendered state for a post.
	 *
	 * @param int $postId Post id.
	 * @return array{status: string, score: int, band: string, reason: string} The result.
	 */
	public function state( int $postId ): array {
		$stored = $this->read( $postId );

		if ( [] === $stored ) {
			return [
				'status' => self::STATUS_NOT_ANALYSED,
				'score'  => 0,
				'band'   => '',
				'reason' => '',
			];
		}

		if ( (int) $stored['rules_version'] < Analyzer::RULES_VERSION ) {
			return [
				'status' => self::STATUS_NEEDS_RECHECK,
				'score'  => (int) $stored['score'],
				'band'   => (string) $stored['band'],
				'reason' => sprintf(
					/* translators: 1: stored rules version, 2: current rules version. */
					__( 'Scored with rules version %1$d. The current version is %2$d.', 'rankkernel' ),
					(int) $stored['rules_version'],
					Analyzer::RULES_VERSION
				),
			];
		}

		return [
			'status' => self::STATUS_ANALYSED,
			'score'  => (int) $stored['score'],
			'band'   => (string) $stored['band'],
			'reason' => '',
		];
	}

	/**
	 * Validate and normalize a stored value.
	 *
	 * This callback accepts mixed input on purpose. Declaring array here
	 * would reproduce the defect class already present in this codebase,
	 * where a sanitize callback fatals when WordPress hands it a string.
	 *
	 * @param mixed $value Raw stored value.
	 * @return array<string, mixed> Normalized record, or an empty array.
	 */
	public static function sanitize( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		foreach ( [ 'score', 'band', 'keywords', 'rules_version', 'analysed_at' ] as $key ) {
			if ( ! array_key_exists( $key, $value ) ) {
				return [];
			}
		}

		$band = is_string( $value['band'] ) ? $value['band'] : '';

		if ( ! in_array( $band, self::BANDS, true ) ) {
			return [];
		}

		foreach ( [ 'score', 'keywords', 'rules_version', 'analysed_at' ] as $key ) {
			if ( ! is_numeric( $value[ $key ] ) ) {
				return [];
			}
		}

		return [
			'score'         => max( 0, min( 100, (int) $value['score'] ) ),
			'band'          => $band,
			'keywords'      => max( 0, (int) $value['keywords'] ),
			'rules_version' => max( 0, (int) $value['rules_version'] ),
			'analysed_at'   => max( 0, (int) $value['analysed_at'] ),
		];
	}

	/**
	 * The band as words, for an accessible label.
	 *
	 * @param string $band Band.
	 * @return string The result.
	 */
	public static function bandLabel( string $band ): string {
		switch ( $band ) {
			case Analyzer::BAND_GOOD:
				return __( 'Good', 'rankkernel' );

			case Analyzer::BAND_IMPROVE:
				return __( 'Needs improvement', 'rankkernel' );

			case Analyzer::BAND_PROBLEM:
				return __( 'Poor', 'rankkernel' );
		}

		return __( 'Not analysed', 'rankkernel' );
	}

	/**
	 * Read the editor payload.
	 *
	 * @param int $postId Post id.
	 * @return array<string, mixed> The result.
	 */
	private function payload( int $postId ): array {
		$raw = function_exists( 'get_post_meta' ) ? get_post_meta( $postId, self::PAYLOAD_KEY, true ) : [];

		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Featured image alt text, when one is set.
	 *
	 * The single lookup behind the browser panel, the REST route and the
	 * stored score, so the three can never read a different value.
	 *
	 * @param int $postId Post id.
	 * @return string The result.
	 */
	public static function featuredAlt( int $postId ): string {
		if ( $postId <= 0 || ! function_exists( 'get_post_thumbnail_id' ) || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}

		$thumbnail = (int) get_post_thumbnail_id( $postId );

		if ( $thumbnail <= 0 ) {
			return '';
		}

		return (string) get_post_meta( $thumbnail, '_wp_attachment_image_alt', true );
	}
}
