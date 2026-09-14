<?php
/**
 * BreadcrumbList piece.
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
 * Trail as a BreadcrumbList node.
 *
 * Builds a minimal home to current trail. The full trail belongs to the
 * future breadcrumbs module, which can supply it through the
 * rankkernel/schema/breadcrumb_trail filter. Each trail entry is an array
 * with name and optional url keys. The schema_breadcrumbs setting turns
 * the node off entirely.
 */
final class BreadcrumbPiece implements PieceInterface {
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
		return 'breadcrumb';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * Needed when the context can produce a trail, singular and archives.
	 * Pure home has no trail beyond itself, so it is skipped.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( ! (bool) $this->settings->get( 'schema_breadcrumbs', true ) ) {
			return false;
		}

		return in_array( $ctx->queriedType(), [ 'post', 'term', 'archive' ], true );
	}

	/**
	 * Build the BreadcrumbList node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';

		$trail = [
			[
				'name' => $ctx->siteName(),
				'url'  => $home,
			],
		];

		$currentName = trim( $ctx->title() );

		if ( '' === $currentName && function_exists( 'single_term_title' ) ) {
			$termTitle = single_term_title( '', false );

			if ( is_string( $termTitle ) ) {
				$currentName = trim( $termTitle );
			}
		}

		$permalink = $ctx->permalink();

		if ( '' !== $currentName || '' !== $permalink ) {
			$trail[] = [
				'name' => $currentName,
				'url'  => $permalink,
			];
		}

		/**
		 * Seam for the future breadcrumbs module to supply the full trail.
		 *
		 * @param array<int, mixed> $trail Trail entries.
		 * @param Context           $ctx   Current request context.
		 */
		$trail = apply_filters( 'rankkernel/schema/breadcrumb_trail', $trail, $ctx ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( ! is_array( $trail ) || [] === $trail ) {
			return [];
		}

		$base = '' !== $permalink ? $permalink : $home;

		if ( '' === $base ) {
			return [];
		}

		$items    = [];
		$position = 1;

		foreach ( $trail as $crumb ) {
			if ( ! is_array( $crumb ) ) {
				continue;
			}

			$name = isset( $crumb['name'] ) ? trim( (string) $crumb['name'] ) : '';
			$url  = isset( $crumb['url'] ) ? trim( (string) $crumb['url'] ) : '';

			if ( '' === $name && '' === $url ) {
				continue;
			}

			$item = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $name,
			];

			if ( '' !== $url ) {
				$item['item'] = $url;
			}

			$items[] = $item;
			++$position;
		}

		if ( [] === $items ) {
			return [];
		}

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => $base . '#breadcrumb',
			'itemListElement' => $items,
		];
	}
}
