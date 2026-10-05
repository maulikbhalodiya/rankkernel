<?php
/**
 * Variation aware keyword matching.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Matches a keyword against text the way a reader would, not the way a regex would.
 *
 * Three tests run in order, from strict to tolerant. First the exact phrase. Then
 * all content words inside one sentence in any order, which is the model Yoast
 * uses and which tolerates a reordered phrase. Then the phrase compared with
 * function words dropped from both sides, so "bicycles for women" matches
 * "bicycles women".
 *
 * The point of the tolerance is that a writer is never told to repeat an exact
 * phrase to satisfy the analyser, which is the loudest complaint about both
 * competitors and the reason their density advice pushes people into stuffing.
 */
final class KeywordMatcher {
	/**
	 * Function words, dropped when comparing content words.
	 *
	 * Only used for English, because the list is English. A keyword made
	 * entirely of function words keeps all of its words, so a phrase such as
	 * "of the" still compares against something.
	 *
	 * @var string[]
	 */
	private const FUNCTION_WORDS = [
		'a',
		'about',
		'above',
		'after',
		'again',
		'against',
		'all',
		'also',
		'am',
		'an',
		'and',
		'any',
		'are',
		'as',
		'at',
		'be',
		'because',
		'been',
		'before',
		'being',
		'below',
		'between',
		'both',
		'but',
		'by',
		'can',
		'cannot',
		'could',
		'did',
		'do',
		'does',
		'doing',
		'down',
		'during',
		'each',
		'few',
		'for',
		'from',
		'further',
		'had',
		'has',
		'have',
		'having',
		'he',
		'her',
		'here',
		'hers',
		'herself',
		'him',
		'himself',
		'his',
		'how',
		'i',
		'if',
		'in',
		'into',
		'is',
		'it',
		'its',
		'itself',
		'just',
		'me',
		'more',
		'most',
		'my',
		'myself',
		'no',
		'nor',
		'not',
		'now',
		'of',
		'off',
		'on',
		'once',
		'only',
		'or',
		'other',
		'our',
		'ours',
		'ourselves',
		'out',
		'over',
		'own',
		'same',
		'she',
		'should',
		'so',
		'some',
		'such',
		'than',
		'that',
		'the',
		'their',
		'theirs',
		'them',
		'themselves',
		'then',
		'there',
		'these',
		'they',
		'this',
		'those',
		'through',
		'to',
		'too',
		'under',
		'until',
		'up',
		'very',
		'was',
		'we',
		'were',
		'what',
		'when',
		'where',
		'which',
		'while',
		'who',
		'whom',
		'why',
		'will',
		'with',
		'would',
		'you',
		'your',
		'yours',
		'yourself',
		'yourselves',
	];

	/**
	 * Map of function words keyed by word for O(1) hash lookups.
	 *
	 * @var array<string, true>|null
	 */
	private static ?array $functionWordsMap = null;

	/**
	 * Reduce text to comparable words.
	 *
	 * Lowercases, strips accents when WordPress is loaded, and collapses every
	 * run of non letter or digit characters to a single space. Letters and
	 * digits are unicode aware, so a non English keyword is not wiped out by
	 * the normalisation itself.
	 *
	 * @param string $text Text to normalise.
	 * @return string Normalised text.
	 */
	public static function normalize( string $text ): string {
		if ( function_exists( 'remove_accents' ) ) {
			$text = (string) remove_accents( $text );
		}

		$text = mb_strtolower( $text, 'UTF-8' );
		$text = (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Reduce text to its words.
	 *
	 * @param string $text Text to split.
	 * @return string[] Words.
	 */
	public static function words( string $text ): array {
		$normalized = self::normalize( $text );

		if ( '' === $normalized ) {
			return [];
		}

		return explode( ' ', $normalized );
	}

	/**
	 * Words that carry meaning, with function words removed.
	 *
	 * @param string $text Text to reduce.
	 * @return string[] Content words, or every word when none remain.
	 */
	public static function contentWords( string $text ): array {
		$words   = self::words( $text );
		$content = [];

		// Performance optimization: lazily construct an O(1) lookup map for function words
		// to eliminate linear array scans (in_array over the function words list for every word in large texts).
		if ( null === self::$functionWordsMap ) {
			self::$functionWordsMap = array_fill_keys( self::FUNCTION_WORDS, true );
		}

		foreach ( $words as $word ) {
			if ( ! isset( self::$functionWordsMap[ $word ] ) ) {
				$content[] = $word;
			}
		}

		return ( [] === $content ) ? $words : $content;
	}

	/**
	 * Whether a keyword appears in the text, allowing normal variation.
	 *
	 * @param string $haystack Text to search.
	 * @param string $keyword  Keyword to look for.
	 * @return bool The result.
	 */
	public static function contains( string $haystack, string $keyword ): bool {
		$needle = self::normalize( $keyword );

		if ( '' === $needle ) {
			return false;
		}

		$hay = self::normalize( $haystack );

		if ( '' === $hay ) {
			return false;
		}

		if ( self::phrasePresent( $hay, $needle ) ) {
			return true;
		}

		$content = self::contentWords( $keyword );

		if ( count( $content ) > 1 ) {
			// Sentence boundaries come from the raw haystack, because
			// normalisation collapses punctuation and newlines and would
			// otherwise leave a single sentence for the whole field.
			foreach ( TextStats::sentences( $haystack ) as $sentence ) {
				if ( self::sentenceHasAll( self::normalize( $sentence ), $content ) ) {
					return true;
				}
			}
		}

		$reduced = implode( ' ', self::contentWords( $hay ) );

		return ( '' !== $reduced ) && self::phrasePresent( $reduced, implode( ' ', $content ) );
	}

	/**
	 * Count exact phrase occurrences, on word boundaries.
	 *
	 * Only the exact phrase is counted, so a variation does not inflate the
	 * density figure. The analyser reports a variation as present rather than
	 * as a count of zero, which is the honest reading.
	 *
	 * @param string $haystack Text to search.
	 * @param string $keyword  Keyword to count.
	 * @return int The result.
	 */
	public static function occurrences( string $haystack, string $keyword ): int {
		$needle = self::normalize( $keyword );
		$hay    = self::normalize( $haystack );

		if ( '' === $needle || '' === $hay ) {
			return 0;
		}

		return substr_count( ' ' . $hay . ' ', ' ' . $needle . ' ' );
	}

	/**
	 * Keyword density as a percentage of the word count.
	 *
	 * @param string $haystack Text to measure.
	 * @param string $keyword  Keyword to measure.
	 * @return float Percentage, zero when the text is empty.
	 */
	public static function density( string $haystack, string $keyword ): float {
		$total = count( self::words( $haystack ) );

		if ( 0 === $total ) {
			return 0.0;
		}

		return ( self::occurrences( $haystack, $keyword ) / $total ) * 100;
	}

	/**
	 * Whether the exact phrase appears on word boundaries.
	 *
	 * @param string $normalizedHay Normalised text.
	 * @param string $normalizedNeedle Normalised keyword.
	 * @return bool The result.
	 */
	private static function phrasePresent( string $normalizedHay, string $normalizedNeedle ): bool {
		return str_contains( ' ' . $normalizedHay . ' ', ' ' . $normalizedNeedle . ' ' );
	}

	/**
	 * Whether one sentence holds every content word, in any order.
	 *
	 * @param string   $sentence Normalised sentence.
	 * @param string[] $content  Content words.
	 * @return bool The result.
	 */
	private static function sentenceHasAll( string $sentence, array $content ): bool {
		$padded = ' ' . $sentence . ' ';

		foreach ( $content as $word ) {
			if ( ! str_contains( $padded, ' ' . $word . ' ' ) ) {
				return false;
			}
		}

		return true;
	}
}
