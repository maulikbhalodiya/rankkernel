<?php
/**
 * Image object piece.
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
 * Image as an ImageObject node.
 *
 * Needed when the effective type is ImageObject and an image URL
 * resolves. The manual contentUrl wins, the og image chain fills the
 * gap. The node @id is the image URL itself, so references from
 * other graphs resolve to the same entity. Width and height stay
 * integers and appear only when numeric.
 */
final class ImageObjectPiece implements PieceInterface {
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
		return 'imageobject';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'ImageObject' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return '' !== $this->imageUrl( $ctx );
	}

	/**
	 * Build the ImageObject node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'ImageObject' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$url = $this->imageUrl( $ctx );

		if ( '' === $url ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );

		$node = [
			'@type' => 'ImageObject',
			'@id'   => $url,
			'url'   => $url,
		];

		$caption = trim( $fields['caption'] ?? '' );

		if ( '' === $caption ) {
			$caption = SchemaHelpers::headline( $ctx, $fields );
		}

		if ( '' !== $caption ) {
			$node['caption'] = $caption;
		}

		foreach ( [ 'width', 'height' ] as $dim ) {
			$raw = trim( $fields[ $dim ] ?? '' );

			if ( '' !== $raw && ctype_digit( $raw ) ) {
				$node[ $dim ] = (int) $raw;
			}
		}

		return $node;
	}

	/**
	 * Image URL, manual override first, then the og image chain.
	 *
	 * @param Context $ctx Request context.
	 * @return string The result.
	 */
	private function imageUrl( Context $ctx ): string {
		$fields = SchemaHelpers::fields( $ctx );

		$manual = SchemaHelpers::httpUrl( $fields['contentUrl'] ?? '' );

		if ( '' !== $manual ) {
			return $manual;
		}

		return SchemaHelpers::httpUrl( $ctx->ogImage() );
	}
}
