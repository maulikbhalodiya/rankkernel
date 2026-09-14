<?php
/**
 * Review piece.
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
 * Standalone review as a Review node.
 *
 * Needed when the effective type is Review and both the reviewed
 * item name and a numeric rating resolve. The reviewed item stays a
 * plain Thing so editors never promise a richer type the page cannot
 * back up. The author ref points at the post author, the body falls
 * back to the excerpt, and the date falls back to the post date.
 */
final class ReviewPiece implements PieceInterface {
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
		return 'review';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'Review' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		$fields = SchemaHelpers::fields( $ctx );

		if ( '' === trim( $fields['itemName'] ?? '' ) ) {
			return false;
		}

		$value = trim( $fields['ratingValue'] ?? '' );

		return '' !== $value && is_numeric( $value );
	}

	/**
	 * Build the Review node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'Review' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );
		$item   = trim( $fields['itemName'] ?? '' );

		if ( '' === $item ) {
			return [];
		}

		$value = trim( $fields['ratingValue'] ?? '' );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return [];
		}

		$id = SchemaHelpers::pageId( $ctx, 'review' );

		if ( '' === $id ) {
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

		$node = [
			'@type'        => 'Review',
			'@id'          => $id,
			'itemReviewed' => [
				'@type' => 'Thing',
				'name'  => $item,
			],
			'reviewRating' => [
				'@type'       => 'Rating',
				'ratingValue' => $value,
				'bestRating'  => $best,
				'worstRating' => $worst,
			],
			'author'       => [
				'@id' => SchemaHelpers::personId( $ctx ),
			],
		];

		$body = trim( $fields['reviewBody'] ?? '' );

		if ( '' === $body ) {
			$body = SchemaHelpers::description( $ctx, $fields );
		}

		if ( '' !== $body ) {
			$node['reviewBody'] = $body;
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
}
