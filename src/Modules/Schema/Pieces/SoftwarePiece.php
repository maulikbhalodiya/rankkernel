<?php
/**
 * Software application piece.
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
 * Application as a SoftwareApplication node.
 *
 * Needed when the payload type is SoftwareApplication and a name
 * resolves. Reads headline, description, appCategory, operatingSystem,
 * price, priceCurrency, ratingValue, and reviewCount from the payload
 * fields. Offers appear only with a numeric price, the aggregate rating
 * appears only when both the value and the count are valid.
 */
final class SoftwarePiece implements PieceInterface {
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
		return 'softwareapplication';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'SoftwareApplication' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return '' !== SchemaHelpers::headline( $ctx, SchemaHelpers::fields( $ctx ) );
	}

	/**
	 * Build the SoftwareApplication node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'SoftwareApplication' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );
		$name   = SchemaHelpers::headline( $ctx, $fields );

		if ( '' === $name ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$node = [
			'@type' => 'SoftwareApplication',
			'@id'   => $permalink . '#software',
			'name'  => $name,
		];

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$category = trim( $fields['appCategory'] ?? '' );

		if ( '' !== $category ) {
			$node['applicationCategory'] = $category;
		}

		$os = trim( $fields['operatingSystem'] ?? '' );

		if ( '' !== $os ) {
			$node['operatingSystem'] = $os;
		}

		$price = SchemaHelpers::priceString( $fields['price'] ?? '' );

		if ( '' !== $price ) {
			$offers = [
				'@type' => 'Offer',
				'price' => $price,
			];

			$currency = SchemaHelpers::currency( $fields['priceCurrency'] ?? '' );

			if ( '' !== $currency ) {
				$offers['priceCurrency'] = $currency;
			}

			$node['offers'] = $offers;
		}

		$rating = SchemaHelpers::aggregateRating( $fields );

		if ( [] !== $rating ) {
			$node['aggregateRating'] = $rating;
		}

		return $node;
	}
}
