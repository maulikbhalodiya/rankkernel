<?php
/**
 * Video piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Settings\SettingsStore;

/**
 * Video as a VideoObject node.
 *
 * Needed when the payload type is VideoObject and the Google required
 * fields all resolve. Reads headline, description, thumbnailUrl,
 * uploadDate, duration, and contentUrl from the payload fields. The
 * thumbnail falls back to the og image chain, the upload date falls
 * back to the post date, and the duration follows the same minutes or
 * PT rule as recipes. The author ref points at the post author.
 */
final class VideoPiece implements PieceInterface {
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
		return 'videoobject';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'VideoObject' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return [] !== $this->required( $ctx );
	}

	/**
	 * Build the VideoObject node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'VideoObject' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$required = $this->required( $ctx );

		if ( [] === $required ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );

		$node = [
			'@type'        => 'VideoObject',
			'@id'          => $permalink . '#video',
			'name'         => $required['name'],
			'description'  => $required['description'],
			'thumbnailUrl' => $required['thumbnail'],
			'uploadDate'   => $required['uploadDate'],
		];

		$duration = SchemaHelpers::toDuration( $fields['duration'] ?? '' );

		if ( '' !== $duration ) {
			$node['duration'] = $duration;
		}

		$content = trim( $fields['contentUrl'] ?? '' );

		if ( '' !== $content ) {
			$node['contentUrl'] = $content;
		}

		$node['author'] = [
			'@id' => SchemaHelpers::personId( $ctx ),
		];

		return $node;
	}

	/**
	 * Required fields, empty array when any one is missing.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, string>
	 */
	private function required( Context $ctx ): array {
		$fields = SchemaHelpers::fields( $ctx );

		$name = SchemaHelpers::headline( $ctx, $fields );

		if ( '' === $name ) {
			return [];
		}

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' === $description ) {
			return [];
		}

		$thumbnail = trim( $fields['thumbnailUrl'] ?? '' );

		if ( '' === $thumbnail ) {
			$thumbnail = $ctx->ogImage();
		}

		if ( '' === $thumbnail ) {
			return [];
		}

		$upload = SchemaHelpers::normalizeDate( $fields['uploadDate'] ?? '' );

		if ( '' === $upload ) {
			$upload = SchemaHelpers::postPublished( $ctx );
		}

		if ( '' === $upload ) {
			return [];
		}

		return [
			'name'        => $name,
			'description' => $description,
			'thumbnail'   => $thumbnail,
			'uploadDate'  => $upload,
		];
	}
}
