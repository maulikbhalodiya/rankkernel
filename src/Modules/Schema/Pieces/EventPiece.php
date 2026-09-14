<?php
/**
 * Event piece.
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
 * Event as an Event node.
 *
 * Needed when the payload type is Event or the fields hold a startDate,
 * and both a name and a valid start date resolve. Reads headline,
 * description, startDate, endDate, locationName, streetAddress,
 * addressLocality, addressRegion, postalCode, addressCountry, performer,
 * eventStatus, price, and priceCurrency from the payload fields. The end
 * date is kept only when it falls after the start date, address fields
 * are omitted individually when empty, the performer maps to a
 * PerformingGroup, and offers carry the page URL when a price is set.
 */
final class EventPiece implements PieceInterface {
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
		return 'event';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		$fields = SchemaHelpers::fields( $ctx );
		$type   = SchemaHelpers::effectiveType( $ctx, $this->settings );

		if ( 'Event' !== $type && '' === trim( $fields['startDate'] ?? '' ) ) {
			return false;
		}

		if ( '' === SchemaHelpers::headline( $ctx, $fields ) ) {
			return false;
		}

		return '' !== SchemaHelpers::normalizeDate( $fields['startDate'] ?? '' );
	}

	/**
	 * Build the Event node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$fields = SchemaHelpers::fields( $ctx );
		$type   = SchemaHelpers::effectiveType( $ctx, $this->settings );

		if ( 'Event' !== $type && '' === trim( $fields['startDate'] ?? '' ) ) {
			return [];
		}

		$name = SchemaHelpers::headline( $ctx, $fields );

		if ( '' === $name ) {
			return [];
		}

		$start = SchemaHelpers::normalizeDate( $fields['startDate'] ?? '' );

		if ( '' === $start ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$node = [
			'@type'     => 'Event',
			'@id'       => $permalink . '#event',
			'name'      => $name,
			'startDate' => $start,
		];

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$end = SchemaHelpers::normalizeDate( $fields['endDate'] ?? '' );

		if ( '' !== $end && strtotime( $end ) > strtotime( $start ) ) {
			$node['endDate'] = $end;
		}

		$location = $this->location( $fields );

		if ( [] !== $location ) {
			$node['location'] = $location;
		}

		$performer = trim( $fields['performer'] ?? '' );

		if ( '' !== $performer ) {
			$node['performer'] = [
				'@type' => 'PerformingGroup',
				'name'  => $performer,
			];
		}

		$node['eventStatus'] = SchemaHelpers::eventStatusUrl( $fields['eventStatus'] ?? '' );

		$price = SchemaHelpers::priceString( $fields['price'] ?? '' );

		if ( '' !== $price ) {
			$offers = [
				'@type' => 'Offer',
				'price' => $price,
				'url'   => $permalink,
			];

			$currency = SchemaHelpers::currency( $fields['priceCurrency'] ?? '' );

			if ( '' !== $currency ) {
				$offers['priceCurrency'] = $currency;
			}

			$node['offers'] = $offers;
		}

		return $node;
	}

	/**
	 * Location block, omitted entirely when name and address are empty.
	 *
	 * @param array<string, string> $fields Manual overrides.
	 * @return array<string, mixed>
	 */
	private function location( array $fields ): array {
		$name    = trim( $fields['locationName'] ?? '' );
		$address = SchemaHelpers::postalAddress( $fields );

		if ( '' === $name && [] === $address ) {
			return [];
		}

		$location = [
			'@type' => 'Place',
		];

		if ( '' !== $name ) {
			$location['name'] = $name;
		}

		if ( [] !== $address ) {
			$location['address'] = $address;
		}

		return $location;
	}
}
