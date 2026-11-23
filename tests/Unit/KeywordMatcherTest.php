<?php
/**
 * Variation aware keyword matching tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\KeywordMatcher;

/**
 * Keyword Matcher Test.
 */
final class KeywordMatcherTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		KeywordMatcher::resetCache();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test normalising keeps letters and digits and drops punctuation.
	 */
	public function test_normalize_keeps_words_and_drops_punctuation(): void {
		$this->assertSame( 'fresh red apples', KeywordMatcher::normalize( 'Fresh, RED   apples!' ) );
		$this->assertSame( 'well known', KeywordMatcher::normalize( 'well-known' ) );
	}

	/**
	 * Test normalising keeps non latin letters.
	 */
	public function test_normalize_keeps_non_latin_letters(): void {
		$this->assertSame( 'привет мир', KeywordMatcher::normalize( 'Привет, мир!' ) );
	}

	/**
	 * Test the exact phrase is found on word boundaries.
	 */
	public function test_exact_phrase_matches_on_word_boundaries(): void {
		$this->assertTrue( KeywordMatcher::contains( 'We sell red apples here.', 'red apples' ) );
		$this->assertFalse( KeywordMatcher::contains( 'We sell redapples here.', 'red apples' ) );
	}

	/**
	 * Test a reordered phrase still counts, which is the Yoast model.
	 */
	public function test_reordered_words_in_one_sentence_still_count(): void {
		$this->assertTrue( KeywordMatcher::contains( 'Your cat may like this food.', 'cat food' ) );
	}

	/**
	 * Test a phrase split across sentences does not count as one sentence hit.
	 *
	 * The first sentence holds "cat", the second holds "food", so the sentence
	 * test must not fire. The text still fails to contain the phrase.
	 */
	public function test_words_split_across_sentences_do_not_count(): void {
		$this->assertFalse( KeywordMatcher::contains( 'The cat sat. Then some food arrived.', 'dog kennel' ) );
	}

	/**
	 * Test shared content words split across sentences do not count as one hit.
	 *
	 * Every content word of the keyword appears, but in two different
	 * sentences. The variation test is scoped to one sentence, so this must
	 * not match. Computing sentence boundaries before normalisation is what
	 * keeps the scope, because normalisation destroys the punctuation.
	 */
	public function test_shared_content_words_split_across_sentences_do_not_count(): void {
		$this->assertFalse( KeywordMatcher::contains( 'The weather is cold today. I brew coffee at home.', 'cold brew coffee' ) );
	}

	/**
	 * Test shared content words inside one sentence still count in any order.
	 */
	public function test_shared_content_words_in_one_sentence_still_count(): void {
		$this->assertTrue( KeywordMatcher::contains( 'They brew coffee cold in summer.', 'cold brew coffee' ) );
	}

	/**
	 * Test a plural is not tolerated, which is the real behaviour of the matcher.
	 *
	 * The image alt check used to claim the matcher accepted singular or
	 * plural. It does not, so this pins the actual contract.
	 */
	public function test_plural_is_not_tolerated(): void {
		$this->assertTrue( KeywordMatcher::contains( 'We sell red apple here.', 'red apple' ) );
		$this->assertFalse( KeywordMatcher::contains( 'We sell red apples here.', 'red apple' ) );
	}

	/**
	 * Test a function word difference still counts, both directions.
	 */
	public function test_function_word_difference_still_counts(): void {
		$this->assertTrue( KeywordMatcher::contains( 'Bicycles women ride daily.', 'bicycles for women' ) );
		$this->assertTrue( KeywordMatcher::contains( 'Bicycles for women are here.', 'bicycles women' ) );
	}

	/**
	 * Test an unrelated keyword is not matched.
	 */
	public function test_unrelated_keyword_does_not_match(): void {
		$this->assertFalse( KeywordMatcher::contains( 'A page about garden tools.', 'car insurance quote' ) );
	}

	/**
	 * Test an empty keyword never matches.
	 */
	public function test_empty_keyword_never_matches(): void {
		$this->assertFalse( KeywordMatcher::contains( 'Any content at all.', '' ) );
		$this->assertFalse( KeywordMatcher::contains( 'Any content at all.', '   ' ) );
	}

	/**
	 * Test position returns the first boundary-valid occurrence.
	 */
	public function test_position_returns_the_first_boundary_valid_occurrence(): void {
		$this->assertSame( 4, KeywordMatcher::position( 'the cat guide', 'cat' ) );
		$this->assertSame( 0, KeywordMatcher::position( 'red apples guide', 'red apples' ) );
		$this->assertSame( 13, KeywordMatcher::position( 'guide to the cat', 'cat' ) );
		$this->assertSame( 26, KeywordMatcher::position( 'concatenated results with cat', 'cat' ) );
		$this->assertSame( 0, KeywordMatcher::position( 'cat guide and cat tips', 'cat' ) );
		$this->assertSame( 0, KeywordMatcher::position( 'red apples and red apples', 'red apples' ) );
	}

	/**
	 * Test position rejects fragments and plural fragments.
	 */
	public function test_position_rejects_fragments_and_plural_fragments(): void {
		$this->assertFalse( KeywordMatcher::position( 'concatenated results', 'cat' ) );
		$this->assertFalse( KeywordMatcher::position( 'cats guide', 'cat' ) );
		$this->assertFalse( KeywordMatcher::position( 'red apples guide', 'red apple' ) );
		$this->assertFalse( KeywordMatcher::position( '猫concatenated', 'cat' ) );
	}

	/**
	 * Test position skips an invalid first candidate and finds a later match.
	 */
	public function test_position_skips_an_invalid_first_candidate(): void {
		$this->assertSame( 13, KeywordMatcher::position( 'concatenated cat guide', 'cat' ) );
		$this->assertSame( 7, KeywordMatcher::position( 'catxxx cat yyyyyyyyyy', 'cat' ) );
	}

	/**
	 * Test punctuation, mixed scripts and astral character offsets.
	 */
	public function test_position_handles_punctuation_mixed_scripts_and_astral_offsets(): void {
		$this->assertSame( 0, KeywordMatcher::position( 'red, apples guide', 'red apples' ) );
		$this->assertSame( 2, KeywordMatcher::position( '猫 cat 狗', 'cat' ) );
		$this->assertSame( 2, KeywordMatcher::position( "\u{20000} cat", 'cat' ) );
	}

	/**
	 * Test unspaced script adjacency stays valid.
	 */
	public function test_position_preserves_unspaced_script_matches(): void {
		$this->assertSame( 0, KeywordMatcher::position( '咖啡指南', '咖啡' ) );
		$this->assertSame( 2, KeywordMatcher::position( '我喝咖啡', '咖啡' ) );
		$this->assertSame( 0, KeywordMatcher::position( '咖啡店', '咖啡' ) );
		$this->assertSame( 0, KeywordMatcher::position( '서울은', '서울' ) );
		$this->assertSame( 0, KeywordMatcher::position( 'ภาษาไทย', 'ภาษา' ) );
	}

	/**
	 * Test empty input never returns a position.
	 */
	public function test_position_returns_false_for_empty_input(): void {
		$this->assertFalse( KeywordMatcher::position( '', 'cat' ) );
		$this->assertFalse( KeywordMatcher::position( 'cat', '' ) );
		$this->assertFalse( KeywordMatcher::position( 'cat', '   ' ) );
	}

	/**
	 * Test content words drop function words but keep an all function phrase.
	 */
	public function test_content_words_drop_function_words(): void {
		$this->assertSame( [ 'red', 'apples' ], KeywordMatcher::contentWords( 'the red apples of' ) );
		$this->assertSame( [ 'of', 'the' ], KeywordMatcher::contentWords( 'of the' ) );
	}

	/**
	 * Test occurrences counts the exact phrase only.
	 */
	public function test_occurrences_count_the_exact_phrase(): void {
		$this->assertSame( 2, KeywordMatcher::occurrences( 'red apples and more red apples', 'red apples' ) );
		$this->assertSame( 0, KeywordMatcher::occurrences( 'apples that are red', 'red apples' ) );
	}

	/**
	 * Test density is a percentage of the word count.
	 */
	public function test_density_is_a_percentage_of_word_count(): void {
		$text = 'red apples and green pears and red apples';

		$this->assertSame( 0.0, KeywordMatcher::density( '', 'red apples' ) );
		$this->assertEqualsWithDelta( 25.0, KeywordMatcher::density( $text, 'red apples' ), 0.01 );
	}

	/**
	 * Test memoized results and resetCache().
	 */
	public function test_memoization_and_reset_cache(): void {
		$text   = 'Fresh, RED apples!';
		$first  = KeywordMatcher::normalize( $text );
		$second = KeywordMatcher::normalize( $text );
		$this->assertSame( $first, $second );

		$words1 = KeywordMatcher::words( $text );
		$words2 = KeywordMatcher::words( $text );
		$this->assertSame( $words1, $words2 );

		$content1 = KeywordMatcher::contentWords( $text );
		$content2 = KeywordMatcher::contentWords( $text );
		$this->assertSame( $content1, $content2 );

		KeywordMatcher::resetCache();

		$third = KeywordMatcher::normalize( $text );
		$this->assertSame( $first, $third );
	}
}
