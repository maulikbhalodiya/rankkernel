<?php
/**
 * Organization piece.
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
 * Site identity as an Organization node.
 *
 * Always needed, every page carries the identity node so Article and
 * WebPage publisher refs resolve. The site_represents setting is stored
 * for the future publisher as Person mode, which lands in a later task.
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
     */
    public function getId(): string {
        return 'organization';
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
     * Build the Organization node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        $root = self::homeRoot();
        $name = trim((string) $this->settings->get('org_name', ''));

        if ('' === $name && function_exists('get_bloginfo')) {
            $name = trim((string) get_bloginfo('name'));
        }

        if ('' === $name) {
            return [];
        }

        $node = [
            '@type' => 'Organization',
            '@id'   => $root . '#organization',
            'name'  => $name,
            'url'   => $root,
        ];

        $logo = $this->resolveLogo();

        if ('' !== $logo) {
            $node['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logo,
            ];
        }

        $sameAs = $this->settings->get('org_sameas', []);

        if (! is_array($sameAs)) {
            $sameAs = [];
        }

        $sameAs = array_values(
            array_filter(
                array_map(static fn (mixed $url): string => trim((string) $url), $sameAs),
                static fn (string $url): bool => '' !== $url
            )
        );

        if ([] !== $sameAs) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /**
     * Resolve the logo URL from the org_logo setting.
     *
     * Accepts an attachment id or a raw URL.
     */
    private function resolveLogo(): string {
        $raw = $this->settings->get('org_logo', '');

        if (is_int($raw) || (is_string($raw) && '' !== trim($raw) && ctype_digit(trim($raw)))) {
            $id = (int) $raw;

            if ($id > 0) {
                if (function_exists('wp_get_attachment_image_url')) {
                    $url = wp_get_attachment_image_url($id, 'full');

                    if (is_string($url) && '' !== $url) {
                        return $url;
                    }
                }

                if (function_exists('wp_get_attachment_url')) {
                    $url = wp_get_attachment_url($id);

                    if (is_string($url) && '' !== $url) {
                        return $url;
                    }
                }
            }

            return '';
        }

        return trim((string) $raw);
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
