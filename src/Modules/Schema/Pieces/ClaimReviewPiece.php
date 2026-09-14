<?php
/**
 * Claim review piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Settings\SettingsStore;

/**
 * Fact check as a ClaimReview node.
 *
 * Needed when the payload type is ClaimReview and a reviewed claim
 * resolves. Reads claimReviewed, ratingValue, bestRating, worstRating,
 * and datePublished from the payload fields. The review rating keeps
 * a numeric value with best defaulting to 5 and worst defaulting to
 * 1, the author ref points at the site organization, the date falls
 * back to the post date, and the url is the page permalink.
 */
final class ClaimReviewPiece implements PieceInterface {
	/**
	 * Settings store.
	 *
	 * @var SettingsStore
	 */
	private readonly SettingsStore $settings;

	/**
	 * Constructor.
	 *
	 * @param SettingsStore|null $settings Optional settings store.
	 */
	public function __construct( ?SettingsStore $settings = null ) {
		$this->settings = $settings ?? new SettingsStore();
	}

	/**
	 * Get piece id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'claimreview';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'ClaimReview' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return '' !== $this->claim( $ctx );
	}

	/**
	 * Build the ClaimReview node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'ClaimReview' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$claim = $this->claim( $ctx );

		if ( '' === $claim ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );

		$node = [
			'@type'         => 'ClaimReview',
			'@id'           => $permalink . '#claimreview',
			'url'           => $permalink,
			'claimReviewed' => $claim,
			'author'        => [
				'@id' => SchemaHelpers::publisherId( $this->settings ),
			],
		];

		$rating = $this->reviewRating( $fields );

		if ( [] !== $rating ) {
			$node['reviewRating'] = $rating;
		}

		$published = SchemaHelpers::normalizeDate( $fields['datePublished'] ?? '' );

		if ( '' === $published ) {
			$published = SchemaHelpers::postPublished( $ctx );
		}

		if ( '' !== $published ) {
			$node['datePublished'] = $published;
		}

		return $node;
	}

	/**
	 * Reviewed claim text, manual override first.
	 *
	 * @param Context $ctx Request context.
	 * @return string The result.
	 */
	private function claim( Context $ctx ): string {
		$fields = SchemaHelpers::fields( $ctx );

		return trim( $fields['claimReviewed'] ?? '' );
	}

	/**
	 * Review rating block, empty when the value is not numeric.
	 *
	 * @param array<string, string> $fields Manual overrides.
	 * @return array<string, mixed>
	 */
	private function reviewRating( array $fields ): array {
		$value = trim( $fields['ratingValue'] ?? '' );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return [];
		}

		$best = trim( $fields['bestRating'] ?? '' );

		if ( '' === $best || ! is_numeric( $best ) ) {
			$best = '5';
		}

		$worst = trim( $fields['worstRating'] ?? '' );

		if ( '' === $worst || ! is_numeric( $worst ) ) {
			$worst = '1';
		}

		return [
			'@type'       => 'Rating',
			'ratingValue' => $value,
			'bestRating'  => $best,
			'worstRating' => $worst,
		];
	}
}
