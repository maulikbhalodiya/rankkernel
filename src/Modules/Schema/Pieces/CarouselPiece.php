<?php
/**
 * Carousel piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * Node list as an ItemList carousel.
 *
 * Needed when the payload carousel key holds at least one ready made
 * node with a @type. Each entry becomes a ListItem with a position
 * indexed from 1, entries without a @type are dropped. The list is
 * already sanitized on save, so the reader only filters and caps.
 */
final class CarouselPiece implements PieceInterface {
	/**
	 * Get piece id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'carousel';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		return [] !== self::nodes( $ctx );
	}

	/**
	 * Build the ItemList carousel node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$nodes = self::nodes( $ctx );

		if ( [] === $nodes ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		return [
			'@type'           => 'ItemList',
			'@id'             => $permalink . '#carousel',
			'itemListElement' => self::listItems( $nodes ),
		];
	}

	/**
	 * Valid carousel nodes, capped at 50 entries.
	 *
	 * @param Context $ctx Request context.
	 * @return array<int, array<string, mixed>>
	 */
	private static function nodes( Context $ctx ): array {
		$meta = $ctx->meta();

		$schema = $meta['schema'] ?? [];

		if ( ! is_array( $schema ) ) {
			return [];
		}

		$raw = $schema['carousel'] ?? [];

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $item ) {
			if ( count( $out ) >= 50 ) {
				break;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$type = $item['@type'] ?? '';

			if ( ! is_string( $type ) || '' === trim( $type ) ) {
				continue;
			}

			$out[] = $item;
		}

		return array_values( $out );
	}

	/**
	 * Wrap nodes in positioned ListItem entries.
	 *
	 * @param array<int, array<string, mixed>> $nodes Valid nodes.
	 * @return array<int, array<string, mixed>>
	 */
	private static function listItems( array $nodes ): array {
		$out = [];

		foreach ( array_values( $nodes ) as $index => $item ) {
			$out[] = [
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'item'     => $item,
			];
		}

		return $out;
	}
}
