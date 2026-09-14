<?php
/**
 * Item list piece.
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
 * Node list as a named ItemList.
 *
 * Needed when the payload type is ItemList and the items key holds at
 * least one ready made node with a @type. Each entry becomes a
 * ListItem with a position indexed from 1, entries without a @type
 * are dropped. The list is already sanitized on save, so the reader
 * only filters and caps.
 */
final class ItemListPiece implements PieceInterface {
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
		return 'itemlist';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'ItemList' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return [] !== self::nodes( $ctx );
	}

	/**
	 * Build the ItemList node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'ItemList' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$nodes = self::nodes( $ctx );

		if ( [] === $nodes ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );

		$node = [
			'@type'           => 'ItemList',
			'@id'             => $permalink . '#itemlist',
			'itemListElement' => self::listItems( $nodes ),
		];

		$name = SchemaHelpers::headline( $ctx, $fields );

		if ( '' !== $name ) {
			$node['name'] = $name;
		}

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		return $node;
	}

	/**
	 * Valid item nodes, capped at 50 entries.
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

		$raw = $schema['items'] ?? [];

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
