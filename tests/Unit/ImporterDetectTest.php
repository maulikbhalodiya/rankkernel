<?php
/**
 * Importer Detect step tests over an in memory wpdb double.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\ImporterPage;
use RankKernel\Modules\Importer\AioseoReader;
use RankKernel\Modules\Importer\RankMathReader;
use RankKernel\Modules\Importer\YoastReader;

/**
 * Importer Detect Test.
 *
 * Each source reader counts importable rows without touching the live
 * plugin: options through get_option, rows through the injected double.
 */
final class ImporterDetectTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		Functions\when( 'esc_html__' )->alias( static fn ( $text ): string => (string) $text );
		Functions\when( 'esc_html' )->alias( static fn ( $text ): string => (string) $text );
		Functions\when( 'esc_attr' )->alias( static fn ( $text ): string => (string) $text );
		Functions\when( 'esc_attr__' )->alias( static fn ( $text ): string => (string) $text );
		Functions\when( '__' )->alias( static fn ( $text ): string => (string) $text );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Seed the options used by every reader.
	 *
	 * @param array<string, mixed> $options Option name to value.
	 */
	private function seedOptions( array $options ): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- stub mirrors the WordPress get_option signature.
			static function ( $name, $default = false ) use ( $options ) {
				return array_key_exists( (string) $name, $options ) ? $options[ (string) $name ] : $default;
			}
		);
	}

	/**
	 * Yoast counts posts, settings and premium redirects.
	 */
	public function test_yoast_detect_counts_posts_settings_and_redirects(): void {
		$this->seedOptions(
			[
				'wpseo_titles'                 => [ 'title-post' => '%%title%%' ],
				'wpseo-premium-redirects-base' => [
					[
						'origin' => '/a',
						'url'    => '/b',
						'type'   => 301,
					],
					[
						'origin' => '/c',
						'url'    => '/d',
						'type'   => 302,
					],
				],
			]
		);
		$db     = new ImporterFakeDb( [ 7, 7, 9 ], [], true );
		$counts = ( new YoastReader( $db ) )->detect();

		$this->assertSame( 2, $counts['posts'] );
		$this->assertTrue( $counts['settings'] );
		$this->assertSame( 2, $counts['redirects'] );
	}

	/**
	 * An empty site detects nothing and stays quiet.
	 */
	public function test_empty_site_detects_nothing(): void {
		$this->seedOptions( [] );
		$db = new ImporterFakeDb( [], [], false );

		foreach ( [ new YoastReader( $db ), new RankMathReader( $db ), new AioseoReader( $db ) ] as $reader ) {
			$counts = $reader->detect();

			$this->assertSame( 0, $counts['posts'] );
			$this->assertFalse( $counts['settings'] );
			$this->assertSame( 0, $counts['redirects'] );
		}
	}

	/**
	 * A missing Rank Math redirections table reads as zero, not an error.
	 */
	public function test_rank_math_missing_table_detects_zero_redirects(): void {
		$this->seedOptions( [ 'rank-math-options-titles' => [ 'pt_post_title' => '%title%' ] ] );
		$db     = new ImporterFakeDb( [], [ 3 ], false );
		$counts = ( new RankMathReader( $db ) )->detect();

		$this->assertSame( 1, $counts['posts'] );
		$this->assertTrue( $counts['settings'] );
		$this->assertSame( 0, $counts['redirects'] );
	}

	/**
	 * AIOSEO reads its own posts table and options blob.
	 */
	public function test_aioseo_detect_counts_posts_and_settings(): void {
		$this->seedOptions( [ 'aioseo_options' => [ 'searchAppearance' => [] ] ] );
		$db     = new ImporterFakeDb( [], [], true, 4 );
		$counts = ( new AioseoReader( $db ) )->detect();

		$this->assertSame( 4, $counts['posts'] );
		$this->assertTrue( $counts['settings'] );
		$this->assertSame( 0, $counts['redirects'] );
	}

	/**
	 * Step one renders every reader row with counts and the empty note.
	 */
	public function test_detect_step_renders_counts_and_empty_note(): void {
		$this->seedOptions( [ 'wpseo_titles' => [ 'title-post' => '%%title%%' ] ] );
		$db      = new ImporterFakeDb( [ 7, 9 ], [], false );
		$readers = [ new YoastReader( $db ), new RankMathReader( $db ), new AioseoReader( $db ) ];

		ob_start();
		( new ImporterPage( $readers ) )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Yoast SEO', $html );
		$this->assertStringContainsString( '>2<', $html );
		$this->assertStringContainsString( 'Nothing to import', $html );
	}
}
