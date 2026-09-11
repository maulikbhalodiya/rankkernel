<?php
/**
 * FAQ piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * FAQ entries as an FAQPage node.
 *
 * Needed on any singular post type, pages included, when the payload
 * holds at least one question row or the post content holds at least
 * one rankkernel/faq block with a question row. Answers pass through
 * wp_kses_post at build time, so stored HTML keeps its safe formatting
 * without breaking the JSON or injecting scripts. The Generator encodes
 * the graph once, so pieces never pre encode their own output.
 */
final class FaqPiece implements PieceInterface {
    /**
     * Parsed block rows memoized per instance, keyed by content hash.
     *
     * isNeeded and build run on the same instance within one request,
     * so the post content parses once, not twice.
     *
     * @var array<int, array{question: string, answer: string}>|null
     */
    private ?array $blockRows = null;

    /**
     * Content hash for the memoized block rows.
     */
    private string $blockHash = '';

    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'faq';
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

        if ($ctx->queriedId() <= 0) {
            return false;
        }

        return [] !== $this->questions($ctx);
    }

    /**
     * Build the FAQPage node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        $questions = $this->questions($ctx);

        if ([] === $questions) {
            return [];
        }

        $permalink = $ctx->permalink();

        if ('' === $permalink) {
            return [];
        }

        $entities = [];

        foreach ($questions as $row) {
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $row['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => self::kses($row['answer']),
                ],
            ];
        }

        return [
            '@type'       => 'FAQPage',
            '@id'         => $permalink . '#faq',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Valid question rows, payload rows first, then block rows.
     *
     * Fresh rows hold an empty schema list, saved rows hold the object
     * shape. Rows with an empty question are dropped here as well, so
     * unsanitized input can never reach the graph. Payload and block
     * rows merge with dedupe on the lowercased trimmed question text
     * (first row wins) and the merged list caps at 100 rows.
     *
     * @param Context $ctx Request context.
     * @return array<int, array{question: string, answer: string}>
     */
    private function questions( Context $ctx ): array {
        $merged = array_merge(self::payloadQuestions($ctx), $this->blockQuestions($ctx));
        $seen   = [];
        $valid  = [];

        foreach ($merged as $row) {
            $key = strtolower(trim($row['question']));

            if ('' === $key || isset($seen[ $key ])) {
                continue;
            }

            $seen[ $key ] = true;
            $valid[]      = $row;

            if (count($valid) >= 100) {
                break;
            }
        }

        return $valid;
    }

    /**
     * Valid question rows from the payload, defensive on both shapes.
     *
     * @param Context $ctx Request context.
     * @return array<int, array{question: string, answer: string}>
     */
    private static function payloadQuestions( Context $ctx ): array {
        $meta = $ctx->meta();

        $schema = $meta['schema'] ?? [];

        if (! is_array($schema)) {
            return [];
        }

        $faq = $schema['faq'] ?? [];

        if (! is_array($faq)) {
            return [];
        }

        $rows = $faq['questions'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        $valid = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = isset($row['question']) ? trim((string) $row['question']) : '';

            if ('' === $question) {
                continue;
            }

            $answer = isset($row['answer']) ? (string) $row['answer'] : '';

            $valid[] = [
                'question' => $question,
                'answer'   => $answer,
            ];
        }

        return $valid;
    }

    /**
     * Question rows from rankkernel/faq blocks in the post content.
     *
     * Only on singular contexts with a post id. Block attrs use the
     * same row shape and sanitization as payload rows. Unknown block
     * names and malformed attrs are ignored.
     *
     * @param Context $ctx Request context.
     * @return array<int, array{question: string, answer: string}>
     */
    private function blockQuestions( Context $ctx ): array {
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

        $rows = $this->parseBlockRows($content);

        $this->blockRows = $rows;
        $this->blockHash = $hash;

        return $rows;
    }

    /**
     * Parse question rows from post content blocks.
     *
     * Group and column blocks nest inner blocks one level down,
     * so the walk recurses into innerBlocks instead of reading only
     * the top level.
     *
     * @param string $content Raw post content.
     * @return array<int, array{question: string, answer: string}>
     */
    private function parseBlockRows( string $content ): array {
        // Parsed block data is untrusted runtime input, so read it as a
        // plain list and validate every level before use.
        /** @var array<int, mixed> $blocks */
        $blocks = parse_blocks($content);

        if (! is_array($blocks)) {
            return [];
        }

        $rows = [];

        $this->walkBlocks($blocks, $rows);

        return $rows;
    }

    /**
     * Collect question rows from a block list, recursing into groups.
     *
     * @param array<int, mixed> $blocks Block list.
     * @param array<int, array{question: string, answer: string}> $rows Collected rows.
     */
    private function walkBlocks( array $blocks, array &$rows ): void {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if ('rankkernel/faq' === ( $block['blockName'] ?? null )) {
                $this->collectRows($block, $rows);
            }

            $inner = $block['innerBlocks'] ?? [];

            if (is_array($inner) && [] !== $inner) {
                $this->walkBlocks($inner, $rows);
            }
        }
    }

    /**
     * Collect valid rows from one FAQ block.
     *
     * @param array<string, mixed> $block FAQ block.
     * @param array<int, array{question: string, answer: string}> $rows Collected rows.
     */
    private function collectRows( array $block, array &$rows ): void {
        $attrs = $block['attrs'] ?? [];

        if (! is_array($attrs)) {
            return;
        }

        $questions = $attrs['questions'] ?? [];

        if (! is_array($questions)) {
            return;
        }

        foreach ($questions as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = isset($row['question']) ? trim((string) $row['question']) : '';

            if ('' === $question) {
                continue;
            }

            $answer = isset($row['answer']) ? (string) $row['answer'] : '';

            $rows[] = [
                'question' => $question,
                'answer'   => $answer,
            ];
        }
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
