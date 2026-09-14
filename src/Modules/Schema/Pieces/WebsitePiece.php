<?php
/**
 * WebSite piece.
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
 * Site identity as a WebSite node, always needed.
 */
final class WebsitePiece implements PieceInterface {
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
		return 'website';
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
	 * Build the WebSite node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$root = SchemaHelpers::homeRoot();
		$name = '';

		if ( function_exists( 'get_bloginfo' ) ) {
			$name = trim( (string) get_bloginfo( 'name' ) );
		}

		if ( '' === $name ) {
			return [];
		}

		$node = [
			'@type'     => 'WebSite',
			'@id'       => $root . '#website',
			'name'      => $name,
			'url'       => $root,
			'publisher' => [
				'@id' => SchemaHelpers::publisherId( $this->settings ),
			],
		];

		if ( (bool) $this->settings->get( 'website_search_action', true ) ) {
			$node['potentialAction'] = [
				'@type'       => 'SearchAction',
				'target'      => $root . '?s={search_term_string}',
				'query-input' => 'required name=search_term_string',
			];
		}

		return $node;
	}
}
