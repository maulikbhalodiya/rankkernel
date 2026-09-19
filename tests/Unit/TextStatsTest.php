<?php
/**
 * Text statistics tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\TextStats;

/**
 * Text Stats Test.
 */
final class TextStatsTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		// Tag stripping stub, sufficient for the simple fixtures below.
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $text ): string => trim( (string) preg_replace( '/<[^>]*>/', '', $text ) )
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test plain text drops markup, scripts and entities.
	 */
	public function test_plain_text_drops_markup_and_scripts(): void {
		$html = '<p>Hello &amp; welcome</p><script>var a = 1;</script><style>.a{}</style><p>Second line</p>';

		$text = TextStats::plainText( $html );

		$this->assertStringContainsString( 'Hello & welcome', $text );
		$this->assertStringContainsString( 'Second line', $text );
		$this->assertStringNotContainsString( 'var a', $text );
		$this->assertStringNotContainsString( '.a{}', $text );
	}

	/**
	 * Test word counting ignores punctuation, including unicode letters.
	 */
	public function test_word_count_ignores_punctuation(): void {
		$this->assertSame( 4, TextStats::wordCount( 'One, two. Three! Four?' ) );
		$this->assertSame( 2, TextStats::wordCount( 'Привет мир' ) );
		$this->assertSame( 0, TextStats::wordCount( '   ' ) );
	}

	/**
	 * Test sentences split on punctuation and on newlines.
	 */
	public function test_sentences_split_on_punctuation_and_newlines(): void {
		$sentences = TextStats::sentences( "First one. Second one!\nThird line" );

		$this->assertCount( 3, $sentences );
		$this->assertSame( 'First one.', $sentences[0] );
		$this->assertSame( 'Third line', $sentences[2] );
	}

	/**
	 * Test paragraphs split on blank lines.
	 */
	public function test_paragraphs_split_on_newlines(): void {
		$paragraphs = TextStats::paragraphs( "One\n\nTwo\nThree" );

		$this->assertSame( [ 'One', 'Two', 'Three' ], $paragraphs );
	}

	/**
	 * Test headings are read with their level.
	 */
	public function test_headings_read_with_level(): void {
		$headings = TextStats::headings( '<h1>Top</h1><h2>Sub <em>here</em></h2><p>body</p>' );

		$this->assertCount( 2, $headings );
		$this->assertSame( 1, $headings[0]['level'] );
		$this->assertSame( 'Sub here', $headings[1]['text'] );
	}

	/**
	 * Test image alts are read, including an empty one.
	 */
	public function test_image_alts_read_including_empty(): void {
		$html = '<img src="a.jpg" alt="Red apple"><img src="b.jpg"><img src="c.jpg" alt="">';

		$this->assertSame( [ 'Red apple', '', '' ], TextStats::imageAlts( $html ) );
	}

	/**
	 * Test links expose href, rel and text for the caller to classify.
	 */
	public function test_links_expose_href_rel_and_text(): void {
		$html = '<a href="/inner">Inner</a><a href="https://other.test" rel="nofollow">Outer</a>';

		$links = TextStats::links( $html );

		$this->assertCount( 2, $links );
		$this->assertSame( '/inner', $links[0]['href'] );
		$this->assertSame( '', $links[0]['rel'] );
		$this->assertSame( 'nofollow', $links[1]['rel'] );
		$this->assertSame( 'Outer', $links[1]['text'] );
	}

	/**
	 * Test media counts images, galleries and video.
	 */
	public function test_media_counts_images_galleries_and_video(): void {
		$html = '<img src="a.jpg"><img src="b.jpg">[gallery ids="1,2"]<iframe src="https://www.youtube.com/embed/x"></iframe>';

		$media = TextStats::media( $html );

		$this->assertSame( 3, $media['images'] );
		$this->assertSame( 1, $media['videos'] );
	}

	/**
	 * Test a table of contents is detected.
	 */
	public function test_toc_detection(): void {
		$this->assertTrue( TextStats::hasToc( '<!-- wp:rankkernel/toc /-->' ) );
		$this->assertFalse( TextStats::hasToc( '<p>plain</p>' ) );
	}

	/**
	 * Test the long sentence ratio.
	 */
	public function test_long_sentence_ratio(): void {
		$sentences = [
			'one two three',
			'one two three four five six',
			'one',
			'one two',
		];

		$this->assertSame( 0.25, TextStats::longSentenceRatio( $sentences, 5 ) );
		$this->assertSame( 0.0, TextStats::longSentenceRatio( [], 5 ) );
	}

	/**
	 * Test the longest paragraph in words.
	 */
	public function test_longest_paragraph(): void {
		$this->assertSame( 3, TextStats::longestParagraph( [ 'one two', 'one two three' ] ) );
		$this->assertSame( 0, TextStats::longestParagraph( [] ) );
	}

	/**
	 * Test the longest run of sentences opening with the same word.
	 */
	public function test_longest_repeated_opening(): void {
		$sentences = [
			'We build things.',
			'We ship things.',
			'We test things.',
			'Then we rest.',
		];

		$this->assertSame( 3, TextStats::longestRepeatedOpening( $sentences ) );
		$this->assertSame( 1, TextStats::longestRepeatedOpening( [ 'One.', 'Two.' ] ) );
	}

	/**
	 * Test reading time rounds up and never reports zero.
	 */
	public function test_reading_time(): void {
		$this->assertSame( 1, TextStats::readingTime( 0 ) );
		$this->assertSame( 1, TextStats::readingTime( 200 ) );
		$this->assertSame( 2, TextStats::readingTime( 201 ) );
	}
}
