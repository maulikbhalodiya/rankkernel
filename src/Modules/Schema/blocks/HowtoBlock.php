<?php
/**
 * HowTo block, server rendered with explicit editor assets.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\blocks;

use function esc_attr;
use function esc_html;
use function esc_url;
use function plugins_url;

/**
 * Registers the rankkernel/howto block type and renders it.
 *
 * Steps render as a numbered list with title, text, and optional
 * image. Server rendering means zero frontend JS and zero frontend
 * CSS ship.
 */
final class HowtoBlock {
    /**
     * Allowed title wrapper tags.
     */
    private const WRAPPERS = [ 'h2', 'h3', 'h4' ];

    /**
     * Register the dynamic block type with explicit editor assets.
     *
     * The editor script and style register explicitly with full
     * dependency lists instead of relying on metadata auto loading,
     * so the editor globals they use always load first.
     */
    public function register(): void {
        add_filter('block_categories_all', [ $this, 'addCategory' ]);
        $this->registerBlock();
    }

    /**
     * Plugin relative asset URL, empty when unavailable (tests, early boot).
     */
    private static function assetUrl( string $path ): string {
        if (! function_exists('plugins_url')) {
            return '';
        }

        return (string) plugins_url($path, __FILE__);
    }

    /**
     * Register the dynamic block type from its block.json folder.
     */
    public function registerBlock(): void {
        if (! function_exists('register_block_type')) {
            return;
        }

        if (function_exists('wp_register_script') && function_exists('plugins_url')) {
            $version = \RankKernel\Plugin::VERSION;

            wp_register_script(
                'rankkernel-howto-editor',
                self::assetUrl('src/Modules/Schema/blocks/howto/howto-editor.js'),
                [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
                $version,
                true
            );
        }

        if (function_exists('wp_register_style') && function_exists('plugins_url')) {
            $version = \RankKernel\Plugin::VERSION;

            wp_register_style(
                'rankkernel-howto-editor',
                plugins_url('src/Modules/Schema/blocks/howto/editor.css', (string) RANKKERNEL_FILE),
                [],
                $version
            );
        }

        register_block_type(
            __DIR__ . '/howto',
            [
                'editor_script'   => 'rankkernel-howto-editor',
                'editor_style'    => 'rankkernel-howto-editor',
                'render_callback' => [ $this, 'render' ],
            ]
        );
    }

    /**
     * Append the RankKernel block category.
     *
     * @param mixed $categories Registered categories.
     * @return array<int, array<string, mixed>>
     */
    public function addCategory( mixed $categories ): array {
        $out = is_array($categories) ? array_values($categories) : [];

        foreach ($out as $existing) {
            if (is_array($existing) && 'rankkernel' === ( $existing['slug'] ?? null )) {
                return $out;
            }
        }

        $out[] = [
            'slug'  => 'rankkernel',
            'title' => 'RankKernel',
            'icon'  => 'editor-ol',
        ];

        return $out;
    }

    /**
     * Render the block.
     *
     * Every dynamic value is escaped, tag names come from an allowlist
     * (h2, h3, h4, default h3). Steps show their position number so the
     * order stays visible regardless of theme list styling. Rows with
     * blank title and text are skipped. Returns an empty string when no
     * valid rows remain, so nothing renders.
     *
     * @param array<string, mixed> $attributes Block attributes.
     */
    public function render( array $attributes ): string {
        $title = isset($attributes['title']) ? trim((string) $attributes['title']) : '';
        $wrapper = isset($attributes['titleWrapper']) ? strtolower(trim((string) $attributes['titleWrapper'])) : 'h3';

        if (! in_array($wrapper, self::WRAPPERS, true)) {
            $wrapper = 'h3';
        }

        $steps = $attributes['steps'] ?? [];

        if (! is_array($steps)) {
            return '';
        }

        $rows  = '';
        $index = 0;

        foreach ($steps as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stepTitle = isset($row['title']) ? trim((string) $row['title']) : '';
            $text      = isset($row['text']) ? trim((string) $row['text']) : '';
            $image     = isset($row['image']) ? trim((string) $row['image']) : '';

            if ('' === $stepTitle && '' === $text) {
                continue;
            }

            $index++;

            $rows .= '<li class="rankkernel-howto-item">';

            if ('' !== $stepTitle) {
                $rows .= '<' . $wrapper . ' class="rankkernel-howto-step-title">'
                    . '<span class="rankkernel-howto-number">' . $index . '. </span>'
                    . esc_html($stepTitle)
                    . '</' . $wrapper . '>';
            }

            if ('' !== $text) {
                $rows .= '<div class="rankkernel-howto-step-text">' . wp_kses_post($text) . '</div>';
            }

            if ('' !== $image) {
                $rows .= '<img class="rankkernel-howto-step-image" src="' . esc_url($image) . '" alt="" />';
            }

            $rows .= '</li>';
        }

        if ('' === $rows) {
            return '';
        }

        $out = '<div class="rankkernel-howto">';

        if ('' !== $title) {
            $out .= '<' . $wrapper . ' class="rankkernel-howto-title">'
                . esc_html($title)
                . '</' . $wrapper . '>';
        }

        $out .= '<ol class="rankkernel-howto-list" style="list-style-type:decimal;">' . $rows . '</ol>';
        $out .= '</div>';

        return $out;
    }
}
