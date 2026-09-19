<?php
/**
 * Content analyser tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\Analyzer;

/**
 * Analyzer Test.
 */
final class AnalyzerTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress signature.
		Functions\when( 'esc_html__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress signature.
		Functions\when( 'number_format_i18n' )->alias( static fn ( float $number, int $decimals = 0 ): string => number_format( $number, $decimals ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress signature.
		// Host extraction stub, sufficient for the fixtures and free of the native parser.
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				if ( PHP_URL_HOST !== $component ) {
					return false;
				}

				return preg_match( '~^[a-z][a-z0-9+.-]*://([^/?#]+)~i', $url, $matches )
					? strtolower( $matches[1] )
					: '';
			}
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
	 * Build a body of the requested word count with the keyword placed sensibly.
	 *
	 * @param string $keyword Keyword to weave in.
	 * @param int    $words   Approximate word count.
	 * @return string Body HTML.
	 */
	private function body( string $keyword, int $words = 700 ): string {
		$sentence = 'This paragraph talks about ' . $keyword . ' and why it matters to a reader today.';
		$filler   = 'However, the details still need care and a clear example, because a reader wants context.';
		$html     = '<h1>' . $keyword . '</h1>';
		$html    .= '<p>' . $sentence . ' ' . $filler . '</p>';
		$html    .= '<h2>More about ' . $keyword . '</h2>';
		$html    .= '<p><img src="a.jpg" alt="' . $keyword . '"> ' . $filler . ' ' . $filler . '</p>';
		$html    .= '<p>See <a href="/inner-page">our guide</a> and <a href="https://example.org/source" rel="follow">the source</a>.</p>';

		$count = str_word_count( trim( (string) preg_replace( '/<[^>]*>/', ' ', $html ) ) );

		while ( $count < $words ) {
			$html .= '<p>' . $filler . ' ' . $filler . '</p>';
			$html .= '<h2>Another section</h2><p>Therefore ' . $filler . '</p>';
			$count = str_word_count( trim( (string) preg_replace( '/<[^>]*>/', ' ', $html ) ) );
		}

		return $html . '<!-- wp:rankkernel/toc /--><img src="b.jpg" alt=""><img src="c.jpg" alt="">';
	}

	/**
	 * Build an analyse input.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed> Input.
	 */
	private function input( array $overrides = [] ): array {
		return array_merge(
			[
				'html'        => $this->body( 'red apples' ),
				'title'       => 'Red apples: a complete guide',
				'description' => 'A guide to red apples and how to pick the best ones.',
				'slug'        => 'red-apples-guide',
				'keywords'    => [ 'red apples' ],
				'site_url'    => 'https://example.com',
			],
			$overrides
		);
	}

	/**
	 * Find one check in a result.
	 *
	 * @param array<string, mixed> $result Result.
	 * @param string               $id     Check id.
	 * @return array<string, mixed>|null The result.
	 */
	private function check( array $result, string $id ): ?array {
		foreach ( $result['checks'] as $check ) {
			if ( $check['id'] === $id ) {
				return $check;
			}
		}

		return null;
	}

	/**
	 * Test no keyword yields an empty result rather than a zero score.
	 */
	public function test_no_keyword_returns_empty_result(): void {
		$result = ( new Analyzer() )->analyze( $this->input( [ 'keywords' => [] ] ) );

		$this->assertSame( 0, $result['score'] );
		$this->assertSame( [], $result['checks'] );
		$this->assertSame( [], $result['keywords'] );
	}

	/**
	 * Test a well optimised post scores in the good band.
	 */
	public function test_well_optimised_post_scores_good(): void {
		$result = ( new Analyzer() )->analyze( $this->input() );

		$this->assertGreaterThanOrEqual( 81, $result['score'] );
		$this->assertSame( Analyzer::BAND_GOOD, $result['band'] );
	}

	/**
	 * Test the keyword checks pass on a well optimised post.
	 */
	public function test_keyword_placement_checks_pass(): void {
		$result = ( new Analyzer() )->analyze( $this->input() );

		foreach ( [ 'keyword_in_title', 'keyword_in_description', 'keyword_in_slug', 'keyword_in_opening', 'keyword_in_content', 'keyword_in_subheading', 'keyword_in_image_alt' ] as $id ) {
			$check = $this->check( $result, $id );

			$this->assertNotNull( $check, $id );
			$this->assertSame( Analyzer::PASS, $check['status'], $id );
		}
	}

	/**
	 * Test a missing slug keyword is reported as a problem.
	 */
	public function test_missing_keyword_in_slug_is_a_problem(): void {
		$result = ( new Analyzer() )->analyze( $this->input( [ 'slug' => 'a-random-url' ] ) );

		$this->assertSame( Analyzer::PROBLEM, $this->check( $result, 'keyword_in_slug' )['status'] );
	}

	/**
	 * Test a reordered phrase in the content still counts.
	 */
	public function test_reordered_phrase_in_content_still_counts(): void {
		$result = ( new Analyzer() )->analyze(
			$this->input(
				[
					'keywords' => [ 'apples red' ],
					'html'     => '<p>We pick red apples every autumn and store them well.</p>',
				]
			)
		);

		$this->assertSame( Analyzer::PASS, $this->check( $result, 'keyword_in_content' )['status'] );
	}

	/**
	 * Test a variation with a function word still counts.
	 */
	public function test_function_word_variation_still_counts(): void {
		$result = ( new Analyzer() )->analyze(
			$this->input(
				[
					'keywords' => [ 'bicycles for women' ],
					'html'     => '<p>Bicycles women ride are built differently.</p>',
				]
			)
		);

		$this->assertSame( Analyzer::PASS, $this->check( $result, 'keyword_in_content' )['status'] );
	}

	/**
	 * Test a variation is reported as natural usage rather than a zero density.
	 */
	public function test_variation_reports_natural_usage_not_zero(): void {
		$result = ( new Analyzer() )->analyze(
			$this->input(
				[
					'keywords' => [ 'red apples' ],
					'html'     => '<p>Apples that are red taste best in autumn.</p>',
				]
			)
		);

		$check = $this->check( $result, 'keyword_density' );

		$this->assertSame( Analyzer::PROBLEM, $check['status'] );
		$this->assertStringContainsString( 'natural variation', $check['message'] );
	}

	/**
	 * Test the density message reports the real count and never asks for more.
	 */
	public function test_density_message_reports_the_count(): void {
		$result = ( new Analyzer() )->analyze( $this->input() );

		$check = $this->check( $result, 'keyword_density' );

		$this->assertStringContainsString( 'time(s)', $check['message'] );
		$this->assertStringNotContainsString( 'add', strtolower( $check['message'] ) );
	}

	/**
	 * Test an absent title makes the title checks not applicable.
	 */
	public function test_absent_title_excludes_the_check_from_the_denominator(): void {
		$result = ( new Analyzer() )->analyze( $this->input( [ 'title' => '' ] ) );

		$this->assertSame( Analyzer::NA, $this->check( $result, 'keyword_in_title' )['status'] );
		$this->assertSame( 0, $this->check( $result, 'keyword_in_title' )['earned'] );

		foreach ( $result['checks'] as $check ) {
			if ( Analyzer::NA === $check['status'] ) {
				continue;
			}

			$this->assertGreaterThan( 0, $check['weight'], $check['id'] );
		}
	}

	/**
	 * Test supporting keywords each get their own score.
	 */
	public function test_supporting_keywords_get_their_own_score(): void {
		$result = ( new Analyzer() )->analyze(
			$this->input( [ 'keywords' => [ 'red apples', 'autumn harvest' ] ] )
		);

		$this->assertCount( 2, $result['keywords'] );
		$this->assertTrue( $result['keywords'][0]['primary'] );
		$this->assertFalse( $result['keywords'][1]['primary'] );
		$this->assertSame( 'autumn harvest', $result['keywords'][1]['keyword'] );
		$this->assertArrayHasKey( 'score', $result['keywords'][1] );
	}

	/**
	 * Test a duplicate keyword is only analysed once.
	 */
	public function test_duplicate_keywords_are_ignored(): void {
		$result = ( new Analyzer() )->analyze( $this->input( [ 'keywords' => [ 'Red Apples', 'red apples', 'RED APPLES' ] ] ) );

		$this->assertCount( 1, $result['keywords'] );
	}

	/**
	 * Test a long paragraph is flagged.
	 */
	public function test_long_paragraph_is_flagged(): void {
		$long = '<p>' . implode( ' ', array_fill( 0, 130, 'word' ) ) . '</p>';

		$result = ( new Analyzer() )->analyze( $this->input( [ 'html' => $long ] ) );

		$this->assertSame( Analyzer::IMPROVE, $this->check( $result, 'short_paragraphs' )['status'] );
	}

	/**
	 * Test internal and external links are classified against the site URL.
	 */
	public function test_links_are_classified_against_the_site_url(): void {
		$html = '<p>Text here.</p><a href="https://example.com/inside">In</a><a href="https://other.test/out" rel="nofollow">Out</a>';

		$result = ( new Analyzer() )->analyze( $this->input( [ 'html' => $html ] ) );

		$this->assertSame( Analyzer::PASS, $this->check( $result, 'internal_links' )['status'] );
		$this->assertSame( Analyzer::PASS, $this->check( $result, 'external_links' )['status'] );
		$this->assertSame( Analyzer::IMPROVE, $this->check( $result, 'followed_external' )['status'] );
	}

	/**
	 * Test keyword uniqueness stays advisory and not applicable without data.
	 */
	public function test_uniqueness_is_not_applicable_without_data(): void {
		$result = ( new Analyzer() )->analyze( $this->input() );

		$check = $this->check( $result, 'keyword_uniqueness' );

		$this->assertSame( Analyzer::NA, $check['status'] );
		$this->assertSame( 0, $check['weight'] );
	}

	/**
	 * Test keyword uniqueness flags a keyword already used elsewhere.
	 */
	public function test_uniqueness_flags_a_used_keyword(): void {
		$result = ( new Analyzer() )->analyze( $this->input( [ 'used_keywords' => [ 'red apples' ] ] ) );

		$this->assertSame( Analyzer::IMPROVE, $this->check( $result, 'keyword_uniqueness' )['status'] );
	}

	/**
	 * Test the band matches the documented thresholds.
	 */
	public function test_band_follows_the_documented_thresholds(): void {
		$analyzer = new Analyzer();

		$weak = $analyzer->analyze(
			[
				'html'     => '<p>Short.</p>',
				'title'    => 'Nothing',
				'slug'     => 'nothing',
				'keywords' => [ 'red apples' ],
			]
		);

		$this->assertLessThanOrEqual( 50, $weak['score'] );
		$this->assertSame( Analyzer::BAND_PROBLEM, $weak['band'] );
	}
}
