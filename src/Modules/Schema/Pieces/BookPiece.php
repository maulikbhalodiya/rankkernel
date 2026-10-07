<?php
/**
 * Book piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;

/**
 * Book as a Book node.
 *
 * Needed when the payload type is Book and a name resolves. Reads
 * headline, description, and isbn from the payload fields. The author
 * ref points at the post author, the publisher ref points at the site
 * organization, and the published date comes from the post date. The
 * ISBN is kept only when it holds digits and X after cleanup.
 */
final class BookPiece implements PieceInterface {
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
		return 'book';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'Book' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return $this->isComplete( $ctx );
	}

	/**
	 * Whether the type can produce a complete node.
	 *
	 * A node missing the properties Google requires for its rich result is
	 * published to every consumer and fails validation in Search Console, so
	 * an incomplete node is never emitted. The metabox warns about the same
	 * field set through SchemaTypes::requiredFields, so an author is told
	 * rather than silently losing the entity.
	 *
	 * @param Context $ctx Request context.
	 * @return bool True when every required field is present.
	 */
	private function isComplete( Context $ctx ): bool {
		return SchemaTypes::isComplete( 'Book', SchemaHelpers::resolvedFields( $ctx ) );
	}

	/**
	 * Build the Book node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'Book' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );
		$name   = SchemaHelpers::headline( $ctx, $fields );

		if ( '' === $name ) {
			return [];
		}

		if ( ! $this->isComplete( $ctx ) ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$node = [
			'@type'  => 'Book',
			'@id'    => $permalink . '#book',
			'name'   => $name,
			'author' => [
				'@id' => SchemaHelpers::personId( $ctx ),
			],
		];

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$isbn = SchemaHelpers::cleanIsbn( $fields['isbn'] ?? '' );

		if ( '' !== $isbn ) {
			$node['isbn'] = $isbn;
		}

		$node['publisher'] = [
			'@id' => SchemaHelpers::publisherId( $this->settings ),
		];

		$published = SchemaHelpers::postPublished( $ctx );

		if ( '' !== $published ) {
			$node['datePublished'] = $published;
		}

		return $node;
	}
}
