<?php
/**
 * Analysis score storage tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\AnalysisScore;
use RankKernel\Modules\Analysis\Analyzer;
use WP_Post;

/**
 * Analysis Score Test.
 */
final class AnalysisScoreTest extends TestCase {
	/**
	 * Post returned by get post.
	 *
	 * @var WP_Post
	 */
	private WP_Post $post;

	/**
	 * Meta store keyed by meta key.
	 *
	 * @var array<string, mixed>
	 */
	private array $meta = [];

	/**
	 * Whether delete post meta was called.
	 *
	 * @var bool
	 */
	private bool $deleted = false;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$post               = new WP_Post();
		$post->ID           = 7;
		$post->post_title   = 'Red apples guide';
		$post->post_name    = 'red-apples-guide';
		$post->post_content = '<p>We write about red apples and how to pick them. Red apples keep well.</p>';
		$this->post         = $post;
		$this->meta         = [];
		$this->deleted      = false;

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'number_format_i18n' )->alias( static fn ( float $number, int $decimals = 0 ): string => number_format( $number, $decimals ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				if ( PHP_URL_HOST !== $component ) {
					return false;
				}

				return preg_match( '~^[a-z][a-z0-9+.-]*://([^/?#]+)~i', $url, $matches )
					? strtolower( (string) preg_replace( '/[0-9]+$/', '', $matches[1] ) )
					: '';
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'get_post' )->justReturn( $this->post );
		Functions\when( 'get_post_meta' )->alias(
			function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				return $this->meta[ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $id, string $key, mixed $value ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_post_meta signature.
				$this->meta[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			function ( int $id, string $key ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress delete_post_meta signature.
				unset( $this->meta[ $key ] );
				$this->deleted = true;

				return true;
			}
		);
		Functions\when( 'get_post_types' )->alias(
			static fn ( array $args = [] ): array => [ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
				'post'       => 'post',
				'page'       => 'page',
				'attachment' => 'attachment',
			]
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
	 * Prime the stored editor payload.
	 *
	 * @param string[] $keywords Keywords.
	 * @return void
	 */
	private function withKeywords( array $keywords ): void {
		$this->meta['_rankkernel_meta_data'] = [ 'focus_keywords' => $keywords ];
	}

	/**
	 * The rules version is a positive integer.
	 */
	public function test_rules_version_is_a_positive_integer(): void {
		$this->assertIsInt( Analyzer::RULES_VERSION );
		$this->assertGreaterThan( 0, Analyzer::RULES_VERSION );
	}

	/**
	 * Compute returns the documented record shape.
	 */
	public function test_compute_returns_the_documented_shape(): void {
		$this->withKeywords( [ 'red apples' ] );

		$record = ( new AnalysisScore() )->compute( 7 );

		$this->assertIsArray( $record );
		$this->assertSame( [ 'score', 'band', 'keywords', 'rules_version', 'analysed_at' ], array_keys( $record ) );
		$this->assertIsInt( $record['score'] );
		$this->assertContains( $record['band'], [ Analyzer::BAND_GOOD, Analyzer::BAND_IMPROVE, Analyzer::BAND_PROBLEM ] );
		$this->assertSame( 1, $record['keywords'] );
		$this->assertSame( Analyzer::RULES_VERSION, $record['rules_version'] );
		$this->assertGreaterThan( 0, $record['analysed_at'] );
	}

	/**
	 * The stored score equals the score the editor reports for the same content.
	 */
	public function test_compute_matches_the_editor_engine(): void {
		$this->withKeywords( [ 'red apples' ] );

		$input = [
			'html'          => $this->post->post_content,
			'title'         => $this->post->post_title,
			'description'   => '',
			'slug'          => $this->post->post_name,
			'keywords'      => [ 'red apples' ],
			'site_url'      => 'https://example.com/',
			'featured_alt'  => '',
			'used_keywords' => null,
		];

		$expected = ( new Analyzer() )->analyze( $input );
		$record   = ( new AnalysisScore() )->compute( 7 );

		$this->assertSame( $expected['score'], $record['score'] );
		$this->assertSame( $expected['band'], $record['band'] );
	}

	/**
	 * A post with no keywords is not scored.
	 */
	public function test_compute_returns_null_without_keywords(): void {
		$this->meta['_rankkernel_meta_data'] = [ 'focus_keywords' => [] ];

		$this->assertNull( ( new AnalysisScore() )->compute( 7 ) );
	}

	/**
	 * Store writes the record, and a save without keywords clears it.
	 */
	public function test_store_writes_and_clears(): void {
		$this->withKeywords( [ 'red apples' ] );

		$score  = new AnalysisScore();
		$record = $score->store( 7 );

		$this->assertSame( $record, $this->meta[ AnalysisScore::META_KEY ] );
		$this->assertSame( $record, $score->read( 7 ) );

		$this->meta['_rankkernel_meta_data'] = [ 'focus_keywords' => [] ];

		$this->assertSame( [], $score->store( 7 ) );
		$this->assertTrue( $this->deleted );
	}

	/**
	 * Store keeps the scalar sort mirror in step with the record.
	 */
	public function test_store_writes_and_clears_the_scalar_sort_mirror(): void {
		$this->withKeywords( [ 'red apples' ] );

		$score  = new AnalysisScore();
		$record = $score->store( 7 );

		$this->assertSame( $record['score'], $this->meta[ AnalysisScore::SCORE_VALUE_KEY ] );

		$this->meta['_rankkernel_meta_data'] = [ 'focus_keywords' => [] ];
		$score->store( 7 );

		$this->assertArrayNotHasKey( AnalysisScore::SCORE_VALUE_KEY, $this->meta );
	}

	/**
	 * The shared featured alt lookup reads the attachment alt and degrades.
	 */
	public function test_featured_alt_reads_the_attachment_alt(): void {
		$this->assertSame( '', AnalysisScore::featuredAlt( 0 ) );

		Functions\when( 'get_post_thumbnail_id' )->justReturn( 12 );
		$this->meta['_wp_attachment_image_alt'] = 'A photo of red apples';

		$this->assertSame( 'A photo of red apples', AnalysisScore::featuredAlt( 7 ) );
	}

	/**
	 * The sanitize callback tolerates a string instead of fataling.
	 */
	public function test_sanitize_tolerates_a_string(): void {
		$this->assertSame( [], AnalysisScore::sanitize( 'not an array' ) );
		$this->assertSame( [], AnalysisScore::sanitize( '' ) );
	}

	/**
	 * Sanitize normalizes a valid record and rejects an unknown band.
	 */
	public function test_sanitize_normalizes_and_rejects(): void {
		$normalized = AnalysisScore::sanitize(
			[
				'score'         => '150',
				'band'          => 'good',
				'keywords'      => '2',
				'rules_version' => '1',
				'analysed_at'   => '123',
			]
		);

		$this->assertSame( 100, $normalized['score'] );
		$this->assertSame( 2, $normalized['keywords'] );

		$this->assertSame(
			[],
			AnalysisScore::sanitize(
				[
					'score'         => 50,
					'band'          => 'bogus',
					'keywords'      => 1,
					'rules_version' => 1,
					'analysed_at'   => 1,
				]
			)
		);
	}

	/**
	 * The state reports not analysed, analysed and needs recheck.
	 */
	public function test_state_reports_the_three_states(): void {
		$score = new AnalysisScore();

		$this->assertSame( 'not_analysed', $score->state( 7 )['status'] );

		$this->meta[ AnalysisScore::META_KEY ] = [
			'score'         => 87,
			'band'          => Analyzer::BAND_GOOD,
			'keywords'      => 1,
			'rules_version' => Analyzer::RULES_VERSION,
			'analysed_at'   => 123,
		];

		$this->assertSame( 'analysed', $score->state( 7 )['status'] );

		$this->meta[ AnalysisScore::META_KEY ]['rules_version'] = Analyzer::RULES_VERSION - 1;

		$stale = $score->state( 7 );

		$this->assertSame( 'needs_recheck', $stale['status'] );
		$this->assertStringContainsString( 'rules version', $stale['reason'] );
	}

	/**
	 * Band labels name the band in words.
	 */
	public function test_band_labels_are_words(): void {
		$this->assertSame( 'Good', AnalysisScore::bandLabel( Analyzer::BAND_GOOD ) );
		$this->assertSame( 'Needs improvement', AnalysisScore::bandLabel( Analyzer::BAND_IMPROVE ) );
		$this->assertSame( 'Poor', AnalysisScore::bandLabel( Analyzer::BAND_PROBLEM ) );
	}

	/**
	 * Supported types are public and never attachments.
	 */
	public function test_supported_types_exclude_attachments(): void {
		$this->assertSame( [ 'post', 'page' ], AnalysisScore::supportedPostTypes() );
		$this->assertTrue( AnalysisScore::isSupportedType( 'post' ) );
		$this->assertFalse( AnalysisScore::isSupportedType( 'attachment' ) );
		$this->assertFalse( AnalysisScore::isSupportedType( '' ) );
	}
}
