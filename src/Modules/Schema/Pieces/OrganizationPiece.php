<?php
/**
 * Organization piece.
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
 * Site identity as an Organization or Person node.
 *
 * Always needed, every page carries the identity node so publisher
 * refs resolve. The site_represents setting picks the shape: a
 * personal site emits a Person at the publisher @id, every other
 * site emits the Organization. Refs built through
 * SchemaHelpers::publisherId always match the node built here.
 */
final class OrganizationPiece implements PieceInterface {
	/**
	 * Constructor.
	 *
	 * @param SettingsStore $settings Settings store.
	 */
	public function __construct( private readonly SettingsStore $settings ) {
	}

	/**
	 * Get piece id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'organization';
	}

	/**
	 * Whether the piece is needed (always true).
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		return true;
	}

	/**
	 * Build the Organization node.
	 *
	 * @param Context $ctx Request context.
	 * @return string[] The result.
	 */
	public function build( Context $ctx ): array {
		$root = SchemaHelpers::homeRoot();
		$name = trim( (string) $this->settings->get( 'org_name', '' ) );

		if ( '' === $name && function_exists( 'get_bloginfo' ) ) {
			$name = trim( (string) get_bloginfo( 'name' ) );
		}

		if ( '' === $name ) {
			return [];
		}

		if ( SchemaHelpers::representsPerson( $this->settings ) ) {
			return $this->personNode( $root, $name );
		}

		$node = [
			'@type' => 'Organization',
			'@id'   => $root . '#organization',
			'name'  => $name,
			'url'   => $root,
		];

		$logo = $this->resolveLogo();

		if ( '' !== $logo ) {
			$node['logo'] = [
				'@type' => 'ImageObject',
				'url'   => $logo,
			];
		}

		$sameAs = $this->sameAsList();

		if ( [] !== $sameAs ) {
			$node['sameAs'] = $sameAs;
		}

		return $node;
	}

	/**
	 * Publisher identity as a Person node for personal sites.
	 *
	 * The @id differs from the organization shape on purpose, so a
	 * mode switch never merges two identities into one entity.
	 *
	 * @param string $root Site root with trailing slash.
	 * @param string $name Publisher name.
	 * @return array<string, mixed>
	 */
	private function personNode( string $root, string $name ): array {
		$node = [
			'@type' => 'Person',
			'@id'   => $root . '#publisher',
			'name'  => $name,
			'url'   => $root,
		];

		$logo = $this->resolveLogo();

		if ( '' !== $logo ) {
			$node['image'] = [
				'@type' => 'ImageObject',
				'url'   => $logo,
			];
		}

		$sameAs = $this->sameAsList();

		if ( [] !== $sameAs ) {
			$node['sameAs'] = $sameAs;
		}

		return $node;
	}

	/**
	 * Clean sameAs URL list from settings.
	 *
	 * @return string[] The result.
	 */
	private function sameAsList(): array {
		$sameAs = $this->settings->get( 'org_sameas', [] );

		if ( ! is_array( $sameAs ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map( static fn ( mixed $url ): string => trim( (string) $url ), $sameAs ),
				static fn ( string $url ): bool => '' !== $url
			)
		);
	}

	/**
	 * Resolve the logo URL from the org_logo setting.
	 *
	 * Accepts an attachment id or a raw URL.
	 *
	 * @return string The result.
	 */
	private function resolveLogo(): string {
		$raw = $this->settings->get( 'org_logo', '' );

		if ( is_int( $raw ) || ( is_string( $raw ) && '' !== trim( $raw ) && ctype_digit( trim( $raw ) ) ) ) {
			$id = (int) $raw;

			if ( $id > 0 ) {
				if ( function_exists( 'wp_get_attachment_image_url' ) ) {
					$url = wp_get_attachment_image_url( $id, 'full' );

					if ( is_string( $url ) && '' !== $url ) {
						return $url;
					}
				}

				if ( function_exists( 'wp_get_attachment_url' ) ) {
					$url = wp_get_attachment_url( $id );

					if ( is_string( $url ) && '' !== $url ) {
						return $url;
					}
				}
			}

			return '';
		}

		return trim( (string) $raw );
	}
}
