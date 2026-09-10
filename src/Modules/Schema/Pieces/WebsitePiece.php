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
     */
    public function getId(): string {
        return 'website';
    }

    /**
     * Whether the piece is needed (always true).
     *
     * @param Context $ctx Request context.
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
        $root = self::homeRoot();
        $name = '';

        if (function_exists('get_bloginfo')) {
            $name = trim((string) get_bloginfo('name'));
        }

        if ('' === $name) {
            return [];
        }

        $node = [
            '@type' => 'WebSite',
            '@id'   => $root . '#website',
            'name'  => $name,
            'url'   => $root,
        ];

        if ((bool) $this->settings->get('website_search_action', true)) {
            $node['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => $root . '?s={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $node;
    }

    /**
     * Home root with trailing slash.
     */
    private static function homeRoot(): string {
        $home = function_exists('home_url') ? (string) home_url('/') : '';

        if (function_exists('trailingslashit')) {
            return trailingslashit($home);
        }

        return rtrim($home, '/') . '/';
    }
}
