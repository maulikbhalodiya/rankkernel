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
	 * Test density is a percentage of the word count.
	 */
	public function test_density_is_a_percentage_of_word_count(): void {
		$text = 'red apples and green pears and red apples';

		$this->assertSame( 0.0, KeywordMatcher::density( '', 'red apples' ) );
		$this->assertEqualsWithDelta( 25.0, KeywordMatcher::density( $text, 'red apples' ), 0.01 );
	}
}
