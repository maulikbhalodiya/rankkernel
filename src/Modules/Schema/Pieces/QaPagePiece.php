<?php
/**
 * Question and answer page piece.
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
 * Q and A as a QAPage node.
 *
 * Needed when the payload type is QAPage and a question resolves. The
 * question comes from the question field with the first FAQ row as
 * fallback, the answer mirrors that order. The accepted answer names
 * its author through the post author ref when the answerAuthor field
 * is set, otherwise it stays a plain Answer. Answer text passes
 * through wp_kses_post at build time.
 */
final class QaPagePiece implements PieceInterface {
    /**
     * Settings store.
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
     */
    public function getId(): string {
        return 'qapage';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('QAPage' !== SchemaHelpers::effectiveType($ctx, $this->settings)) {
            return false;
        }

        $qa = self::questionAnswer($ctx);

        return '' !== $qa['question'] && '' !== $qa['answer'];
    }

    /**
     * Build the QAPage node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('QAPage' !== SchemaHelpers::effectiveType($ctx, $this->settings)) {
            return [];
        }

        $qa = self::questionAnswer($ctx);

        if ('' === $qa['question'] || '' === $qa['answer']) {
            return [];
        }

        $permalink = $ctx->permalink();

        if ('' === $permalink) {
            return [];
        }

        $fields = SchemaHelpers::fields($ctx);
        $name   = SchemaHelpers::headline($ctx, $fields);

        $node = [
            '@type'      => 'QAPage',
            '@id'        => $permalink . '#qapage',
            'mainEntity' => [
                '@type'          => 'Question',
                'name'           => $qa['question'],
                'acceptedAnswer' => self::answer($ctx, $fields, $qa['answer']),
            ],
        ];

        if ('' !== $name) {
            $node['name'] = $name;
        }

        return $node;
    }

    /**
     * Question and answer pair, fields first, FAQ fallback second.
     *
     * @param Context $ctx Request context.
     * @return array{question: string, answer: string}
     */
    private static function questionAnswer( Context $ctx ): array {
        $fields = SchemaHelpers::fields($ctx);

        $question = trim($fields['question'] ?? '');
        $answer   = trim($fields['answer'] ?? '');

        if ('' !== $question && '' !== $answer) {
            return [
                'question' => $question,
                'answer'   => $answer,
            ];
        }

        $fallback = self::firstFaqRow($ctx);

        if ('' === $question) {
            $question = $fallback['question'];
        }

        if ('' === $answer) {
            $answer = $fallback['answer'];
        }

        return [
            'question' => $question,
            'answer'   => $answer,
        ];
    }

    /**
     * First FAQ row, empty pair when the payload holds none.
     *
     * @param Context $ctx Request context.
     * @return array{question: string, answer: string}
     */
    private static function firstFaqRow( Context $ctx ): array {
        $meta = $ctx->meta();

        $schema = $meta['schema'] ?? [];

        if (! is_array($schema)) {
            return [ 'question' => '', 'answer' => '' ];
        }

        $faq = $schema['faq'] ?? [];

        if (! is_array($faq)) {
            return [ 'question' => '', 'answer' => '' ];
        }

        $rows = $faq['questions'] ?? [];

        if (! is_array($rows)) {
            return [ 'question' => '', 'answer' => '' ];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = isset($row['question']) ? trim((string) $row['question']) : '';

            if ('' === $question) {
                continue;
            }

            $answer = isset($row['answer']) ? trim((string) $row['answer']) : '';

            return [
                'question' => $question,
                'answer'   => $answer,
            ];
        }

        return [ 'question' => '', 'answer' => '' ];
    }

    /**
     * Accepted answer, author ref included when answerAuthor is set.
     *
     * @param Context               $ctx    Request context.
     * @param array<string, string> $fields Manual overrides.
     * @param string                $answer Answer text.
     * @return array<string, mixed>
     */
    private static function answer( Context $ctx, array $fields, string $answer ): array {
        $out = [
            '@type' => 'Answer',
            'text'  => self::kses($answer),
        ];

        if ('' !== trim($fields['answerAuthor'] ?? '')) {
            $out['author'] = [
                '@id' => SchemaHelpers::personId($ctx),
            ];
        }

        return $out;
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
