<?php
/**
 * Custom JSON-LD piece.
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
 * Stored custom JSON merged into the graph.
 *
 * Needed when the payload custom key holds a non empty value. The
 * stored value is already sanitized on save (scalars and arrays
 * only, depth capped, key count capped), the reader still validates
 * every level because REST and import paths can deliver stored data
 * too. A single typed object becomes one node, an @graph wrapper or
 * a plain list becomes one node per entry, capped at 10 nodes. A
 * typeless object is wrapped as a generic Thing so valid data is
 * never silently dropped. Nested @context keys are stripped, the
 * graph carries exactly one @context.
 */
final class CustomJsonPiece implements PieceInterface {
	/**
	 * Max custom nodes merged into the graph.
	 */
	private const MAX_NODES = 10;

	/**
	 * Get piece id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'custom';
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
	 * Build the custom nodes.
	 *
	 * Returns a single node directly when exactly one resolves, else
	 * a numeric list of nodes, which the Generator appends item by
	 * item.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string|int, mixed>
	 */
	public function build( Context $ctx ): array {
		$nodes = self::nodes( $ctx );

		if ( [] === $nodes ) {
			return [];
		}

		if ( 1 === count( $nodes ) ) {
			return $nodes[0];
		}

		return array_values( $nodes );
	}

	/**
	 * Valid custom nodes, capped.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string|int, mixed>
	 */
	private static function nodes( Context $ctx ): array {
		$meta = $ctx->meta();

		$schema = $meta['schema'] ?? [];

		if ( ! is_array( $schema ) ) {
			return [];
		}

		$custom = $schema['custom'] ?? [];

		if ( ! is_array( $custom ) || [] === $custom ) {
			return [];
		}

		if ( array_is_list( $custom ) ) {
			$raw = $custom;
		} elseif ( isset( $custom['@graph'] ) && is_array( $custom['@graph'] ) ) {
			$raw = $custom['@graph'];
		} else {
			$raw = [ $custom ];
		}

		$out = [];

		foreach ( $raw as $item ) {
			if ( count( $out ) >= self::MAX_NODES ) {
				break;
			}

			$node = self::cleanNode( $item );

			if ( [] !== $node ) {
				$out[] = $node;
			}
		}

		return array_values( $out );
	}

	/**
	 * Clean one custom value into a graph node.
	 *
	 * Non arrays and empty values are dropped. Nested @context keys
	 * are stripped at every level. Typeless objects are wrapped as a
	 * generic Thing, typeless scalars never become nodes.
	 *
	 * @param mixed $item Raw custom value.
	 * @return array<string, mixed>
	 */
	private static function cleanNode( mixed $item ): array {
		if ( ! is_array( $item ) || [] === $item ) {
			return [];
		}

		if ( array_is_list( $item ) ) {
			return [];
		}

		$node = self::stripContext( $item );

		if ( [] === $node ) {
			return [];
		}

		$type = $node['@type'] ?? '';

		if ( is_array( $type ) ) {
			$type = implode( ',', array_map( static fn ( mixed $t ): string => (string) $t, $type ) );
		}

		if ( ! is_string( $type ) || '' === trim( $type ) ) {
			$node['@type'] = 'Thing';
		}

		return $node;
	}

	/**
	 * Strip @context keys from a custom level.
	 *
	 * @param array<string|int, mixed> $level Raw level.
	 * @return array<string|int, mixed>
	 */
	private static function stripContext( array $level ): array {
		$out = [];

		foreach ( $level as $key => $value ) {
			if ( '@context' === $key ) {
				continue;
			}

			if ( is_array( $value ) && ! array_is_list( $value ) ) {
				$value = self::stripContext( $value );
			}

			$out[ $key ] = $value;
		}

		return $out;
	}
}
