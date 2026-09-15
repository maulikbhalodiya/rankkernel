<?php
/**
 * FAQ piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * FAQ entries as an FAQPage node.
 *
 * Needed on any singular post type, pages included, when the payload
 * holds at least one question row or the post content holds at least
 * one rankkernel/faq block with a question row. Questions and answers
 * are reduced to plain text at build time, so the schema carries no
 * HTML tags and no scripts. Frontend rendering keeps its safe HTML
 * separately, so visible formatting and structured text stay clean.
 * The Generator encodes the graph once, so pieces never pre encode
 * their own output.
 */
final class FaqPiece implements PieceInterface {
	/**
	 * Parsed block rows memoized per instance, keyed by content hash.
	 *
	 * The isNeeded and build methods run on the same instance within one request,
	 * so the post content parses once, not twice.
	 *
	 * @var array<int, array{question: string, answer: string}>|null
	 */
	private ?array $blockRows = null;

	/**
	 * Content hash for the memoized block rows.
	 *
	 * @var string
	 */
	private string $blockHash = '';

	/**
	 * Get piece id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'faq';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'post' !== $ctx->queriedType() ) {
			return false;
		}

		if ( $ctx->queriedId() <= 0 ) {
			return false;
		}

		if ( '' === $ctx->permalink() ) {
			return false;
		}

		return [] !== $this->questions( $ctx );
	}

	/**
	 * Build the FAQPage node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$questions = $this->questions( $ctx );

		if ( [] === $questions ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$entities = [];

		foreach ( $questions as $row ) {
			$entities[] = [
				'@type'          => 'Question',
				'name'           => $row['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => self::plainText( $row['answer'] ),
				],
			];
		}

		return [
			'@type'      => 'FAQPage',
			'@id'        => $permalink . '#faq',
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
		$merged = array_merge( self::payloadQuestions( $ctx ), $this->blockQuestions( $ctx ) );
		$seen   = [];
		$valid  = [];

		foreach ( $merged as $row ) {
			$key = strtolower( trim( $row['question'] ) );

			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$valid[]      = $row;

			if ( count( $valid ) >= 100 ) {
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

		if ( ! is_array( $schema ) ) {
			return [];
		}

		$faq = $schema['faq'] ?? [];

		if ( ! is_array( $faq ) ) {
			return [];
		}

		$rows = $faq['questions'] ?? [];

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$valid = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';

			if ( '' === $question ) {
				continue;
			}

			$answer = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';

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
	 * names and malformed attrs are ignored. Reusable block references
	 * (core/block entries holding a ref id) are not resolved here, so
	 * only blocks present inline in the post content feed the graph.
	 *
	 * @param Context $ctx Request context.
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function blockQuestions( Context $ctx ): array {
		if ( 'post' !== $ctx->queriedType() ) {
			return [];
		}

		$postId = $ctx->queriedId();

		if ( $postId <= 0 ) {
			return [];
		}

		if ( ! function_exists( 'get_post_field' ) || ! function_exists( 'parse_blocks' ) ) {
			return [];
		}

		$content = get_post_field( 'post_content', $postId );

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return [];
		}

		$hash = md5( $content );

		if ( null !== $this->blockRows && $hash === $this->blockHash ) {
			return $this->blockRows;
		}

		$rows = $this->parseBlockRows( $content );

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
		/**
		 * Parsed blocks from the post content.
		 *
		 * @var array<int, mixed> $blocks
		 */
		$blocks = parse_blocks( $content );

		if ( ! is_array( $blocks ) ) {
			return [];
		}

		$rows = [];

		$this->walkBlocks( $blocks, $rows );

		return $rows;
	}

	/**
	 * Collect question rows from a block list, recursing into groups.
	 *
	 * @param array<int, mixed>                                   $blocks Block list.
	 * @param array<int, array{question: string, answer: string}> $rows Collected rows.
	 */
	private function walkBlocks( array $blocks, array &$rows ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( 'rankkernel/faq' === ( $block['blockName'] ?? null ) ) {
				$this->collectRows( $block, $rows );
			}

			$inner = $block['innerBlocks'] ?? [];

			if ( is_array( $inner ) && [] !== $inner ) {
				$this->walkBlocks( $inner, $rows );
			}
		}
	}

	/**
	 * Collect valid rows from one FAQ block.
	 *
	 * The render callback drops rows with an empty question even when
	 * an answer is present, so this collector applies the same rule,
	 * which keeps visible content and FAQPage nodes in agreement.
	 * Question markup is stripped because block questions render
	 * through esc_html, so tags must not reach the Question name.
	 * Answers are trimmed, so whitespace only input counts as empty.
	 *
	 * @param array<string, mixed>                                $block FAQ block.
	 * @param array<int, array{question: string, answer: string}> $rows  Collected rows.
	 */
	private function collectRows( array $block, array &$rows ): void {
		$attrs = $block['attrs'] ?? [];

		if ( ! is_array( $attrs ) ) {
			return;
		}

		$questions = $attrs['questions'] ?? [];

		if ( ! is_array( $questions ) ) {
			return;
		}

		foreach ( $questions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$raw = isset( $row['question'] ) ? (string) $row['question'] : '';

			$question = trim( self::plainText( $raw ) );

			if ( '' === $question ) {
				continue;
			}

			$answer = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';

			$rows[] = [
				'question' => $question,
				'answer'   => $answer,
			];
		}
	}

	/**
	 * Plain text for schema fields, all markup removed.
	 *
	 * Block level tags become spaces before stripping, so words in
	 * separate paragraphs never merge. Entities are decoded and
	 * whitespace collapses to single spaces, so the schema carries
	 * clean text. Uses the native WP filter when available and falls
	 * back to strip_tags in contexts without WP loaded.
	 *
	 * @param string $text Raw value.
	 * @return string The result.
	 */
	private static function plainText( string $text ): string {
		$spaced = (string) preg_replace( '#<(?:br\s*/?|/(?:p|div|li|h[1-6]|td|tr|blockquote))\s*>#i', ' ', $text );

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$clean = wp_strip_all_tags( $spaced );
		} else {
			$clean = strip_tags( $spaced ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Fallback for contexts without WP loaded, where wp_strip_all_tags is unavailable.
		}

		$clean = html_entity_decode( (string) $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$clean = (string) preg_replace( '/\s+/u', ' ', $clean );

		return trim( $clean );
	}
}
