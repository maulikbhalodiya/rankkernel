<?php
/**
 * Text extraction and statistics for the content analyser.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Pure text and HTML statistics.
 *
 * Everything here is deterministic and side effect free, so the same numbers
 * come out in PHP and in the editor. HTML is reduced to plain text once and
 * every count works from that, which keeps the checks consistent with each
 * other and keeps the analyser independent of how the content was authored.
 */
final class TextStats {
	/**
	 * Sentence terminators, kept as one character class.
	 */
	private const SENTENCE_ENDINGS = '.!?';

	/**
	 * Strip tags and normalise whitespace, for word and sentence counting.
	 *
	 * @param string $html Raw HTML or plain text.
	 * @return string Plain text.
	 */
	public static function plainText( string $html ): string {
		$text = (string) preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html );
		$text = (string) preg_replace( '/<br\s*\/?>/i', "\n", $text );
		$text = (string) preg_replace( '/<\/(p|div|h[1-6]|li|blockquote|tr)>/i', "\n", $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/[ \t\x0B\f\r]+/', ' ', $text );
		$text = (string) preg_replace( '/\n\s*\n+/', "\n", $text );

		return trim( $text );
	}

	/**
	 * Split text into words.
	 *
	 * @param string $text Plain text.
	 * @return string[] Words, punctuation removed, empties dropped.
	 */
	public static function words( string $text ): array {
		$parts = preg_split( '/[^\p{L}\p{N}\'’-]+/u', $text );

		if ( ! is_array( $parts ) ) {
			return [];
		}

		$words = [];

		foreach ( $parts as $part ) {
			$part = trim( $part, "'’-" );

			if ( '' !== $part ) {
				$words[] = $part;
			}
		}

		return $words;
	}

	/**
	 * Count words in plain text.
	 *
	 * @param string $text Plain text.
	 * @return int The result.
	 */
	public static function wordCount( string $text ): int {
		return count( self::words( $text ) );
	}

	/**
	 * Split text into sentences.
	 *
	 * Newlines also end a sentence, so a list without punctuation does not
	 * merge into one enormous sentence and skew every ratio.
	 *
	 * @param string $text Plain text.
	 * @return string[] Sentences, trimmed, empties dropped.
	 */
	public static function sentences( string $text ): array {
		$sentences = [];

		foreach ( explode( "\n", $text ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$parts = preg_split( '/(?<=[' . preg_quote( self::SENTENCE_ENDINGS, '/' ) . '])\s+/u', $line );

			if ( ! is_array( $parts ) ) {
				continue;
			}

			foreach ( $parts as $part ) {
				$part = trim( $part );

				if ( '' !== $part ) {
					$sentences[] = $part;
				}
			}
		}

		return $sentences;
	}

	/**
	 * Split text into paragraphs.
	 *
	 * @param string $text Plain text.
	 * @return string[] Paragraphs, trimmed, empties dropped.
	 */
	public static function paragraphs( string $text ): array {
		$paragraphs = [];

		foreach ( preg_split( '/\n+/', $text ) as $part ) {
			$part = trim( (string) $part );

			if ( '' !== $part ) {
				$paragraphs[] = $part;
			}
		}

		return $paragraphs;
	}

	/**
	 * Extract heading levels and text.
	 *
	 * @param string $html Raw HTML.
	 * @return array<int, array{level: int, text: string}> Headings in order.
	 */
	public static function headings( string $html ): array {
		if ( ! preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$headings = [];

		foreach ( $matches as $match ) {
			$headings[] = [
				'level' => (int) $match[1],
				'text'  => self::plainText( (string) $match[2] ),
			];
		}

		return $headings;
	}

	/**
	 * Extract image alt values.
	 *
	 * @param string $html Raw HTML.
	 * @return string[] Alt attributes, including empty ones.
	 */
	public static function imageAlts( string $html ): array {
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $html, $matches ) ) {
			return [];
		}

		$alts = [];

		foreach ( $matches[0] as $tag ) {
			$alts[] = preg_match( '/\balt\s*=\s*("([^"]*)"|\'([^\']*)\')/i', (string) $tag, $alt )
				? (string) ( '' !== ( $alt[2] ?? '' ) ? $alt[2] : ( $alt[3] ?? '' ) )
				: '';
		}

		return $alts;
	}

	/**
	 * Extract anchors with their href, rel and anchor text.
	 *
	 * Classification into internal and external is left to the caller, which
	 * knows the site URL.
	 *
	 * @param string $html Raw HTML.
	 * @return array<int, array{href: string, rel: string, text: string}> Anchors in order.
	 */
	public static function links( string $html ): array {
		if ( ! preg_match_all( '/<a\b([^>]*)>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$links = [];

		foreach ( $matches as $match ) {
			$attributes = (string) $match[1];
			$href       = preg_match( '/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attributes, $found )
				? (string) ( '' !== ( $found[2] ?? '' ) ? $found[2] : ( $found[3] ?? '' ) )
				: '';
			$rel        = preg_match( '/\brel\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attributes, $relFound )
				? (string) ( '' !== ( $relFound[2] ?? '' ) ? $relFound[2] : ( $relFound[3] ?? '' ) )
				: '';

			$links[] = [
				'href' => html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'rel'  => strtolower( $rel ),
				'text' => self::plainText( (string) $match[2] ),
			];
		}

		return $links;
	}

	/**
	 * Count the media a reader can see.
	 *
	 * @param string $html Raw HTML.
	 * @return array{images: int, videos: int} Media counts.
	 */
	public static function media( string $html ): array {
		$images = preg_match_all( '/<img\b[^>]*>/i', $html );
		$images = ( false === $images ) ? 0 : $images;

		$galleries = preg_match_all( '/\[gallery\b[^]]*\]/i', $html );
		$images   += ( false === $galleries ) ? 0 : $galleries;

		$videos = preg_match_all( '/<video\b[^>]*>|<iframe\b[^>]*(youtube|vimeo)[^>]*>|\[video\b[^]]*\]/i', $html );
		$videos = ( false === $videos ) ? 0 : $videos;

		return [
			'images' => (int) $images,
			'videos' => (int) $videos,
		];
	}

	/**
	 * Whether a table of contents block or shortcode is present.
	 *
	 * @param string $html Raw HTML.
	 * @return bool The result.
	 */
	public static function hasToc( string $html ): bool {
		return 1 === preg_match( '/wp:rankkernel\/toc|\[rankkernel_toc|wp-block-rank-math-toc-block|\[toc\b/i', $html );
	}

	/**
	 * Fraction of sentences longer than a word limit.
	 *
	 * @param string[] $sentences Sentences.
	 * @param int      $limit     Word limit.
	 * @return float Ratio between 0 and 1.
	 */
	public static function longSentenceRatio( array $sentences, int $limit ): float {
		if ( [] === $sentences ) {
			return 0.0;
		}

		$long = 0;

		foreach ( $sentences as $sentence ) {
			if ( self::wordCount( $sentence ) > $limit ) {
				++$long;
			}
		}

		return $long / count( $sentences );
	}

	/**
	 * Longest paragraph, in words.
	 *
	 * @param string[] $paragraphs Paragraphs.
	 * @return int The result.
	 */
	public static function longestParagraph( array $paragraphs ): int {
		$longest = 0;

		foreach ( $paragraphs as $paragraph ) {
			$longest = max( $longest, self::wordCount( $paragraph ) );
		}

		return $longest;
	}

	/**
	 * Largest run of consecutive sentences that start with the same word.
	 *
	 * @param string[] $sentences Sentences.
	 * @return int The result, 1 when there is no repeat.
	 */
	public static function longestRepeatedOpening( array $sentences ): int {
		$longest  = 0;
		$current  = 0;
		$previous = null;

		foreach ( $sentences as $sentence ) {
			$words = self::words( $sentence );
			$first = mb_strtolower( (string) ( $words[0] ?? '' ), 'UTF-8' );

			if ( null !== $previous && '' !== $first && $first === $previous ) {
				++$current;
			} else {
				$current = 1;
			}

			$previous = $first;
			$longest  = max( $longest, $current );
		}

		return $longest;
	}

	/**
	 * Estimated reading time in minutes, at 200 words per minute.
	 *
	 * @param int $wordCount Word count.
	 * @return int Minutes, at least 1.
	 */
	public static function readingTime( int $wordCount ): int {
		return max( 1, (int) ceil( $wordCount / 200 ) );
	}
}
