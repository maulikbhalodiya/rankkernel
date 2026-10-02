<?php
/**
 * Local business piece.
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
 * Local business as a LocalBusiness node.
 *
 * Needed when the effective type is LocalBusiness and a name
 * resolves. Reads headline, description, telephone, priceRange,
 * openingHours, and the postal address fields from the payload
 * fields. Opening hours stay plain text lines per the schema.org
 * shape. The address block is omitted entirely when empty.
 */
final class LocalBusinessPiece implements PieceInterface {
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
		return 'localbusiness';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'LocalBusiness' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
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
		return SchemaTypes::isComplete( 'LocalBusiness', SchemaHelpers::resolvedFields( $ctx ) );
	}

	/**
	 * Build the LocalBusiness node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'LocalBusiness' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
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

		$id = SchemaHelpers::pageId( $ctx, 'localbusiness' );

		if ( '' === $id ) {
			return [];
		}

		$node = [
			'@type' => 'LocalBusiness',
			'@id'   => $id,
			'name'  => $name,
		];

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$image = $ctx->ogImage();

		if ( '' !== $image ) {
			$node['image'] = $image;
		}

		$address = SchemaHelpers::postalAddress( $fields );

		if ( [] !== $address ) {
			$node['address'] = $address;
		}

		$phone = trim( $fields['telephone'] ?? '' );

		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}

		$range = trim( $fields['priceRange'] ?? '' );

		if ( '' !== $range ) {
			$node['priceRange'] = $range;
		}

		$hours = SchemaHelpers::splitLines( $fields['openingHours'] ?? '' );

		if ( [] !== $hours ) {
			$node['openingHours'] = $hours;
		}

		return $node;
	}
}
