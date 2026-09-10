<?php
/**
 * FAQ block, registration plus server render.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\blocks;

/**
 * Registers the rankkernel/faq block and renders it on the server.
 *
 * Server rendering means zero frontend JS and zero frontend CSS ship
 * with the block. The tradeoff is visible content with no accordion
 * toggle, which keeps the markup screen reader friendly and lets the
 * FAQPage schema match exactly what visitors see.
 */
final class FaqBlock {
    /**
     * Allowed title wrapper tags.
     *
     * @var string[]
     */
    private const WRAPPERS = [ 'h2', 'h3', 'h4' ];

    /**
     * Register the block category and the dynamic block type.
     *
     * Runs during SchemaModule::boot(), which fires on init, so the
     * register_block_type call below already happens on init and the
     * render callback runs on demand only.
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

        if (! defined('\RANKERNEL_FILE')) {
            return '';
        }

        $base = (string) constant('\RANKERNEL_FILE');

        if ('' === $base) {
            return '';
        }

        return (string) plugins_url($path, $base);
    }

    /**
     * Register the dynamic block type from its block.json folder.
     *
     * The editor script and style register explicitly with full
     * dependency lists instead of relying on metadata auto loading,
     * so the editor globals they use always load first.
     */
    public function registerBlock(): void {
        if (! function_exists('register_block_type')) {
            return;
        }

        if (function_exists('wp_register_script') && function_exists('plugins_url')) {
            $version = \RankKernel\Plugin::VERSION;

            wp_register_script(
                'rankkernel-faq-editor',
                self::assetUrl('src/Modules/Schema/blocks/faq/faq-editor.js'),
                [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
                $version,
                true
            );
        }

        if (function_exists('wp_register_style') && function_exists('plugins_url')) {
            $version = \RankKernel\Plugin::VERSION;

            wp_register_style(
                'rankkernel-faq-editor',
                self::assetUrl('src/Modules/Schema/blocks/faq/editor.css'),
                [],
                $version
            );
        }

        register_block_type(
            __DIR__ . '/faq',
            [
                'editor_script'   => 'rankkernel-faq-editor',
                'editor_style'    => 'rankkernel-faq-editor',
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
            'icon'  => 'editor-ul',
        ];

        return $out;
    }

    /**
     * Render the block.
     *
     * Every dynamic value is escaped, tag names come from an allowlist
     * (h2, h3, h4, default h3) and the list tag is ul or ol (default
     * ul). Rows with both parts blank are skipped. Returns an empty
     * string when no valid rows remain, so nothing renders.
     *
     * @param array<string, mixed> $attributes Block attributes.
     */
    public function render( array $attributes ): string {
        $title = isset($attributes['title']) ? trim((string) $attributes['title']) : '';
        $wrapper = isset($attributes['titleWrapper']) ? strtolower(trim((string) $attributes['titleWrapper'])) : 'h3';

        if (! in_array($wrapper, self::WRAPPERS, true)) {
            $wrapper = 'h3';
        }

        $list = isset($attributes['listStyle']) ? strtolower(trim((string) $attributes['listStyle'])) : 'ul';

        if (! in_array($list, [ 'ul', 'ol' ], true)) {
            $list = 'ul';
        }

        $questions = $attributes['questions'] ?? [];

        if (! is_array($questions)) {
            $questions = [];
        }

        $rows  = '';
        $index = 0;

        foreach ($questions as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = isset($row['question']) ? trim((string) $row['question']) : '';
            $answer   = isset($row['answer']) ? trim((string) $row['answer']) : '';

            if ('' === $question && '' === $answer) {
                continue;
            }

            $index++;

            $rows .= '<li class="rankkernel-faq-item">';

            if ('' !== $question) {
                $rows .= '<' . $wrapper . ' class="rankkernel-faq-question">'
                    . '<span class="rankkernel-faq-number">' . $index . '. </span>'
                    . esc_html($question)
                    . '</' . $wrapper . '>';
            }

            if ('' !== $answer) {
                $rows .= '<div class="rankkernel-faq-answer">' . wp_kses_post($answer) . '</div>';
            }

            $rows .= '</li>';
        }

        if ('' === $rows) {
            return '';
        }

        $out = '<div class="rankkernel-faq">';

        if ('' !== $title) {
            $out .= '<' . $wrapper . ' class="rankkernel-faq-title">'
                . esc_html($title)
                . '</' . $wrapper . '>';
        }

        $listStyle = 'ol' === $list ? ' style="list-style-type:decimal;"' : ' style="list-style-type:disc;"';

        $out .= '<' . $list . ' class="rankkernel-faq-list"' . $listStyle . '>' . $rows . '</' . $list . '>';
        $out .= '</div>';

        return $out;
    }
}
