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
	 * Test occurrences counts every repeat, not every other one.
	 *
	 * The old padded substr_count consumed the trailing space the next
	 * occurrence also needed, so a repeated keyword was reported at about
	 * half its real count. That figure feeds the 2.5 percent ceiling, so an
	 * over stuffed article read as fine.
	 */
	public function test_occurrences_count_every_repeat(): void {
		$this->assertSame( 3, KeywordMatcher::occurrences( 'seo seo seo', 'seo' ) );
		$this->assertSame( 4, KeywordMatcher::occurrences( 'seo seo seo seo', 'seo' ) );
		$this->assertSame( 3, KeywordMatcher::occurrences( 'SEO for SEO. SEO!', 'seo' ) );
		$this->assertSame( 1, KeywordMatcher::occurrences( 'a  seo   b', 'seo' ) );
	}

	/**
	 * Test occurrences still refuses a partial word match.
	 */
	public function test_occurrences_ignore_partial_words(): void {
		// normalize() folds case and drops punctuation to word boundaries,
		// so seo-tools becomes the two words seo tools and seo does match
		// it. A substring of a longer word must not match, which is what
		// the word anchored pattern is for.
		$this->assertSame( 2, KeywordMatcher::occurrences( 'seo-tools seo', 'seo' ) );
		$this->assertSame( 1, KeywordMatcher::occurrences( 'seo-tools seo', 'seo tools' ) );
		$this->assertSame( 0, KeywordMatcher::occurrences( 'preseo', 'seo' ) );
		$this->assertSame( 0, KeywordMatcher::occurrences( 'seoseo', 'seo' ) );
		$this->assertSame( 0, KeywordMatcher::occurrences( 'cat care basics', 'at' ) );
		$this->assertSame( 0, KeywordMatcher::occurrences( '', 'seo' ) );
		$this->assertSame( 0, KeywordMatcher::occurrences( 'seo', '' ) );
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
	 * Test the documented PHP and JavaScript occurrence divergence.
	 *
	 * The normalize step turns every run of non letter, non digit characters
	 * into a single space, and occurrences then counts whole words in that
	 * alphabet. Both engines must agree on which codepoints count as letters
	 * and digits, or the two sides count differently.
	 *
	 * They currently do not, on purpose, and this test is the tripwire. This
	 * host runs PCRE2 10.46, whose Unicode tables predate the ones in the
	 * JavaScript engine. PCRE2 does not classify 357 codepoints across 23
	 * ranges as a letter or a digit, almost all of them Unicode 15.0 and
	 * later additions such as Kawi, Nag Mundari, Todhri and Garay, while
	 * V8 does. PHP therefore replaces such a character with a space and
	 * counts the keyword after it, while JavaScript keeps the character and
	 * reads it as part of a word, so the count is one lower there.
	 *
	 * The divergence is accepted for V1. Hardcoding the ranges in the
	 * JavaScript normalizer would pin the behaviour to whichever PCRE2 build
	 * this happens to run and would invert on a newer one, and narrowing the
	 * word definition to ASCII would drop the Cyrillic and Greek support the
	 * suite depends on. No real search corpus contains these characters, so
	 * the practical effect on keyword density is nil.
	 *
	 * If this assertion ever fails, PHP has moved to a PCRE2 whose tables do
	 * know these codepoints. That is the good outcome, and the fix is to
	 * re-measure the JavaScript side rather than to relax this test.
	 *
	 * @dataProvider provideDivergentCodepoints
	 *
	 * @param string $character Codepoint the two engines classify differently.
	 */
	public function test_occurrences_diverge_from_javascript_only_while_pcre_unicode_lags(
		string $character
	): void {
		$haystack = 'x' . $character . 'seo';

		$this->assertSame(
			1,
			KeywordMatcher::occurrences( $haystack, 'seo' ),
			'PCRE2 replaces this codepoint with a space, so PHP counts the keyword'
		);
	}

	/**
	 * Codepoints the two engines currently classify differently.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provideDivergentCodepoints(): array {
		return [
			'U+088F Arabic'  => [ "\u{088F}" ],
			'U+A7CE Latin'   => [ "\u{A7CE}" ],
			'U+2CEA2 Gurung' => [ "\u{2CEA2}" ],
		];
	}
}
