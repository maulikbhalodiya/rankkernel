<?php
/**
 * ImportRunner tests over fake readers and stubbed storage.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\ImportRunner;
use RankKernel\Modules\Importer\SourceReader;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Settings\SettingsStore;

/**
 * Import runner test.
 */
final class ImportRunnerTest extends TestCase {
	/**
	 * Fake source reader with canned chunks.
	 *
	 * @return SourceReader The reader.
	 */
	private function reader(): SourceReader {
		return new class() implements SourceReader {
			/** Source id. */
			public function id(): string {
				return 'yoast';
			}

			/** Source label. */
			public function label(): string {
				return 'Yoast';
			}

			/** Detected counts. */
			public function detect(): array {
				return [
					'posts'     => 2,
					'terms'     => 1,
					'settings'  => true,
					'redirects' => 1,
				];
			}

			/**
			 * Canned post chunk.
			 *
			 * @param int $offset Chunk offset.
			 * @param int $limit  Chunk size.
			 */
			public function fetchPosts( int $offset, int $limit ): array {
				if ( 0 !== $offset ) {
					return [];
				}

				return [
					[
						'id'   => 11,
						'type' => 'post',
						'data' => [ '_yoast_wpseo_title' => 'Alpha' ],
					],
					[
						'id'   => 12,
						'type' => 'post',
						'data' => [ '_yoast_wpseo_title' => 'Beta' ],
					],
				];
			}

			/**
			 * Canned term chunk.
			 *
			 * @param int $offset Chunk offset.
			 * @param int $limit  Chunk size.
			 */
			public function fetchTerms( int $offset, int $limit ): array {
				if ( 0 !== $offset ) {
					return [];
				}

				return [
					[
						'id'       => 21,
						'taxonomy' => 'category',
						'data'     => [ 'wpseo_title' => 'Cats' ],
					],
				];
			}

			/** Canned sitewide blob. */
			public function fetchSitewideRaw(): array {
				return [
					'title-post'    => '%%title%% %%sep%% %%sitename%%',
					'title-page'    => '%%title%%',
					'metadesc-post' => '%%excerpt%%',
				];
			}

			/** Canned redirect rows. */
			public function fetchRedirectsRaw(): array {
				return [
					[
						'origin' => '/old',
						'url'    => '/new',
						'type'   => '301',
						'format' => 'plain',
					],
				];
			}
		};
	}

	/**
	 * Build the runner with an in memory redirect store.
	 *
	 * @return array{ImportRunner, RedirectsFakeDb} The runner and fake db.
	 */
	private function runner(): array {
		$db     = new RedirectsFakeDb();
		$runner = new ImportRunner(
			[ 'yoast' => $this->reader() ],
			new RedirectRepository( $db ),
			new SettingsStore()
		);

		return [ $runner, $db ];
	}

	/**
	 * Set up Brain Monkey and the wpdb double.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ) {
				$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double for wp_parse_url, WordPress is not loaded in unit tests.

				if ( false === $parts ) {
					return false;
				}

				if ( -1 === $component ) {
					return $parts;
				}

				return $parts[ $component ] ?? null;
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
			}
		);
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Posts chunk applies clean rows and reports done with detect total.
	 */
	public function test_posts_chunk_applies_clean_rows(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip' );

		$this->assertSame( 2, $result['applied'] );
		$this->assertSame( 0, $result['failed'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 2, $result['total'] );
	}

	/**
	 * Terms chunk writes through the term meta path.
	 */
	public function test_terms_chunk_applies_term_rows(): void {
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'update_term_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'terms', 0, 50, 'skip' );

		$this->assertSame( 1, $result['applied'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 1, $result['total'] );
	}

	/**
	 * Skip mode leaves a conflicting field alone.
	 */
	public function test_skip_mode_skips_conflicting_row(): void {
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'title'       => 'Mine',
				'description' => '',
			]
		);
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip' );

		$this->assertSame( 0, $result['applied'] );
		$this->assertSame( 2, $result['skipped'] );
	}

	/**
	 * Overwrite mode takes the conflicting field.
	 */
	public function test_overwrite_mode_takes_conflicting_row(): void {
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'title'       => 'Mine',
				'description' => '',
			]
		);
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'overwrite' );

		$this->assertSame( 2, $result['applied'] );
	}

	/**
	 * Excluded ref ids are skipped without touching storage.
	 */
	public function test_excludes_skip_rows(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip', [ 11 ] );

		$this->assertSame( 1, $result['applied'] );
		$this->assertSame( 1, $result['skipped'] );
	}

	/**
	 * Sitewide run applies the chosen candidate per slot.
	 */
	public function test_sitewide_applies_chosen_candidate(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$saved = [];

		Functions\when( 'update_option' )->alias(
			static function ( string $option, $value ) use ( &$saved ) {
				$saved[ $option ] = $value;

				return true;
			}
		);

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'sitewide', 0, 50, 'skip', [], [ 'title_template' => 'page' ] );

		$this->assertSame( 1, $result['applied'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( '%%title%%', $saved['rankkernel_settings']['title_template'] ?? null );
	}

	/**
	 * Sitewide run rejects an unknown choice instead of guessing.
	 */
	public function test_sitewide_invalid_choice_throws(): void {
		[ $runner ] = $this->runner();

		$this->expectException( \InvalidArgumentException::class );

		$runner->run( 'yoast', 'sitewide', 0, 50, 'skip', [], [ 'title_template' => 'nope' ] );
	}

	/**
	 * Redirect rows insert through the repository.
	 */
	public function test_redirects_insert_through_repository(): void {
		[ $runner, $db ] = $this->runner();

		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double.

		Functions\when( 'update_option' )->justReturn( true );

		$result = $runner->run( 'yoast', 'redirects', 0, 50, 'skip' );

		$this->assertSame( 1, $result['applied'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Empty chunk reports done with zero counts.
	 */
	public function test_empty_chunk_reports_done(): void {
		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 50, 50, 'skip' );

		$this->assertSame( 0, $result['applied'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Unknown source, kind, or mode throws instead of running.
	 */
	public function test_invalid_arguments_throw(): void {
		[ $runner ] = $this->runner();

		$this->expectException( \InvalidArgumentException::class );

		$runner->run( 'yoast', 'nope', 0, 50, 'skip' );
	}
}
