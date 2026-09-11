<?php
/**
 * HowTo piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * HowTo steps as a HowTo node.
 *
 * Needed on any singular post type, pages included, when the payload
 * holds at least one valid step. The name falls back to the post
 * title when the payload name is empty. Step text passes through
 * wp_kses_post at build time, so stored HTML keeps its safe
 * formatting without breaking the JSON or injecting scripts. The
 * Generator encodes the graph once, so pieces never pre encode
 * their own output.
 */
final class HowtoPiece implements PieceInterface {
    /**
     * Parsed block steps memoized per instance, keyed by content hash.
     *
     * isNeeded and build run on the same instance within one request,
     * so the post content parses once, not twice.
     *
     * @var array<int, array{title: string, text: string, image: string}>|null
     */
    private ?array $blockRows = null;

    /**
     * Content hash for the memoized block steps.
     */
    private string $blockHash = '';

    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'howto';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('post' !== $ctx->queriedType()) {
            return false;
        }

        return [] !== $this->steps($ctx);
    }

    /**
     * Build the HowTo node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        $howto = $this->howto($ctx);

        if ([] === $howto['steps']) {
            return [];
        }

        $permalink = $ctx->permalink();

        if ('' === $permalink) {
            return [];
        }

        $name = trim($howto['name']);

        if ('' === $name) {
            $name = trim($ctx->title());
        }

        $steps = [];

        foreach ($howto['steps'] as $row) {
            $step = [
                '@type' => 'HowToStep',
                'name'  => $row['title'],
                'text'  => self::kses($row['text']),
            ];

            if ('' !== $row['image']) {
                $step['image'] = $row['image'];
            }

            $steps[] = $step;
        }

        $node = [
            '@type' => 'HowTo',
            '@id'   => $permalink . '#howto',
            'name'  => $name,
            'step'  => $steps,
        ];

        if ('' !== trim($howto['totalTime'])) {
            $node['totalTime'] = $howto['totalTime'];
        }

        if ('' !== trim($howto['cost'])) {
            $node['estimatedCost'] = $howto['cost'];
        }

        return $node;
    }

    /**
     * HowTo block from the payload, defensive on both shapes.
     *
     * Fresh rows hold an empty schema list, saved rows hold the object
     * shape. Steps with both an empty title and empty text are dropped
     * here as well, so unsanitized input can never reach the graph.
     *
     * @param Context $ctx Request context.
     * @return array{name: string, steps: array<int, mixed>, totalTime: string, cost: string}
     */
    private function howto( Context $ctx ): array {
        $out = [
            'name'      => '',
            'steps'     => [],
            'totalTime' => '',
            'cost'      => '',
        ];

        $meta = $ctx->meta();

        $schema = $meta['schema'] ?? [];

        if (! is_array($schema)) {
            return $out;
        }

        $howto = $schema['howto'] ?? [];

        if (! is_array($howto)) {
            return $out;
        }

        if (isset($howto['name'])) {
            $out['name'] = trim((string) $howto['name']);
        }

        if (isset($howto['totalTime'])) {
            $out['totalTime'] = trim((string) $howto['totalTime']);
        }

        if (isset($howto['cost'])) {
            $out['cost'] = trim((string) $howto['cost']);
        }

        $rows = $howto['steps'] ?? [];

        if (! is_array($rows)) {
            return $out;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            $text  = isset($row['text']) ? (string) $row['text'] : '';

            if ('' === $title && '' === trim($text)) {
                continue;
            }

            $image = isset($row['image']) ? trim((string) $row['image']) : '';

            $out['steps'][] = [
                'title' => $title,
                'text'  => $text,
                'image' => $image,
            ];
        }

        foreach ($this->blockSteps($ctx) as $blockStep) {
            $seen = false;

            foreach ($out['steps'] as $existing) {
                $sameTitle = strtolower(trim($existing['title'])) === strtolower(trim($blockStep['title']));
                $existingText = strtolower(trim(strip_tags($existing['text'])));
                $blockText    = strtolower(trim(strip_tags($blockStep['text'])));

                if ($sameTitle && $existingText === $blockText) {
                    $seen = true;
                    break;
                }
            }

            if (! $seen) {
                $out['steps'][] = $blockStep;
            }

            if (count($out['steps']) >= 100) {
                break;
            }
        }

        return $out;
    }

    /**
     * Step rows from rankkernel/howto blocks in the post content.
     *
     * Only on singular contexts with a post id. Block attrs use the
     * same row shape as payload rows. Unknown block names and
     * malformed attrs are ignored.
     *
     * @param Context $ctx Request context.
     * @return array<int, array{title: string, text: string, image: string}>
     */
    private function blockSteps( Context $ctx ): array {
        if ('post' !== $ctx->queriedType()) {
            return [];
        }

        $postId = $ctx->queriedId();

        if ($postId <= 0) {
            return [];
        }

        if (! function_exists('get_post_field') || ! function_exists('parse_blocks')) {
            return [];
        }

        $content = get_post_field('post_content', $postId);

        if (! is_string($content) || '' === trim($content)) {
            return [];
        }

        $hash = md5($content);

        if (null !== $this->blockRows && $hash === $this->blockHash) {
            return $this->blockRows;
        }

        /** @var array<int, mixed> $blocks */
        $blocks = parse_blocks($content);

        if (! is_array($blocks)) {
            return [];
        }

        $rows = [];

        $this->walkBlocks($blocks, $rows);

        $this->blockRows = $rows;
        $this->blockHash = $hash;

        return $rows;
    }

    /**
     * Collect step rows from a block list, recursing into groups.
     *
     * @param array<int, mixed> $blocks Block list.
     * @param array<int, array{title: string, text: string, image: string}> $rows Collected rows.
     */
    private function walkBlocks( array $blocks, array &$rows ): void {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if ('rankkernel/howto' === ( $block['blockName'] ?? null )) {
                $this->collectRows($block, $rows);
            }

            $inner = $block['innerBlocks'] ?? [];

            if (is_array($inner) && [] !== $inner) {
                $this->walkBlocks($inner, $rows);
            }
        }
    }

    /**
     * Collect valid rows from one HowTo block.
     *
     * @param array<string, mixed> $block HowTo block.
     * @param array<int, array{title: string, text: string, image: string}> $rows Collected rows.
     */
    private function collectRows( array $block, array &$rows ): void {
        $attrs = $block['attrs'] ?? [];

        if (! is_array($attrs)) {
            return;
        }

        $steps = $attrs['steps'] ?? [];

        if (! is_array($steps)) {
            return;
        }

        foreach ($steps as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            $text  = isset($row['text']) ? (string) $row['text'] : '';

            if ('' === $title && '' === trim($text)) {
                continue;
            }

            $image = isset($row['image']) ? trim((string) $row['image']) : '';

            $rows[] = [
                'title' => $title,
                'text'  => $text,
                'image' => $image,
            ];
        }
    }

    /**
     * Valid steps from the payload.
     *
     * @param Context $ctx Request context.
     * @return array<int, array{title: string, text: string, image: string}>
     */
    private function steps( Context $ctx ): array {
        return $this->howto($ctx)['steps'];
    }

    /**
     * Filter rich text through wp_kses_post.
     *
     * @param string $text Raw text.
     */
    private static function kses( string $text ): string {
        if (function_exists('wp_kses_post')) {
            $clean = wp_kses_post($text);

            if (is_string($clean)) {
                return $clean;
            }
        }

        return strip_tags($text);
    }
}
