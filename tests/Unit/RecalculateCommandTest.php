<?php
/**
 * Analysis recalculation command tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\AnalysisScore;
use RankKernel\Modules\Analysis\RecalculateCommand;
use WP_Post;

/**
 * Recalculate Command Test.
 */
final class RecalculateCommandTest extends TestCase {
	/**
	 * Posts keyed by id.
	 *
	 * @var array<int, WP_Post>
	 */
	private array $posts = [];

	/**
	 * Meta store per post id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $meta = [];

	/**
	 * Captured get posts arguments.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $queried = [];

	/**
	 * Whether update post meta was called.
	 *
	 * @var bool
	 */
	private bool $updated = false;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$this->posts   = [];
		$this->meta    = [];
		$this->queried = [];
		$this->updated = false;

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( (string) preg_replace( '/[^a-z0-9_-]/i', '', $value ) ) );
		Functions\when( 'number_format_i18n' )->alias( static fn ( float $number, int $decimals = 0 ): string => number_format( $number, $decimals ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				unset( $url, $component );

				return '';
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'get_post_types' )->alias(
			static function ( array $args = [] ): array {
				unset( $args );

				return [
					'post' => 'post',
					'page' => 'page',
				];
			}
		);
		Functions\when( 'get_post' )->alias(
			function ( int $id ): mixed {
				return $this->posts[ $id ] ?? null;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			function ( int $id, string $key, bool $single ): mixed {
				unset( $single );

				return $this->meta[ $id ][ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $id, string $key, mixed $value ): bool {
				unset( $id, $key, $value );

				$this->updated = true;

				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'get_posts' )->justReturn( [] );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Add a post with keywords or without.
	 *
	 * @param int      $id       Post id.
	 * @param string[] $keywords Keywords.
	 * @return void
	 */
	private function addPost( int $id, array $keywords ): void {
		$post               = new WP_Post();
		$post->ID           = $id;
		$post->post_title   = 'Red apples guide';
		$post->post_name    = 'red-apples-guide';
		$post->post_content = '<p>We write about red apples and how to pick them. Red apples keep well.</p>';

		$this->posts[ $id ] = $post;
		$this->meta[ $id ]  = [ '_rankkernel_meta_data' => [ 'focus_keywords' => $keywords ] ];
	}

	/**
	 * Queue the get posts pages.
	 *
	 * @param array<int, array<int, int>> $pages Pages of ids.
	 * @return void
	 */
	private function queuePages( array $pages ): void {
		Functions\when( 'get_posts' )->alias(
			function ( array $args ) use ( &$pages ): array {
				$this->queried[] = $args;

				return array_shift( $pages ) ?? [];
			}
		);
	}

	/**
	 * Options are parsed from the WP-CLI associative arguments.
	 */
	public function test_options_are_parsed(): void {
		$options = ( new RecalculateCommand() )->options(
			[
				'post-type'   => 'Page',
				'post-status' => 'Draft',
				'dry-run'     => null,
				'batch'       => '10',
			]
		);

		$this->assertSame( [ 'page' ], $options['post_type'] );
		$this->assertSame( 'draft', $options['post_status'] );
		$this->assertTrue( $options['dry_run'] );
		$this->assertSame( 10, $options['batch'] );
	}

	/**
	 * The negated WP-CLI flag is not a dry run.
	 */
	public function test_negated_dry_run_flag_is_not_a_dry_run(): void {
		$options = ( new RecalculateCommand() )->options( [ 'dry-run' => false ] );

		$this->assertFalse( $options['dry_run'] );
	}

	/**
	 * A batch stores a record for every post with keywords.
	 */
	public function test_batch_stores_scores(): void {
		$this->addPost( 1, [ 'red apples' ] );
		$this->addPost( 2, [ 'red apples' ] );
		$this->addPost( 3, [ 'red apples' ] );
		$this->queuePages( [ [ 1, 2 ], [ 3 ], [] ] );

		$result = ( new RecalculateCommand() )->batch( [ 'batch' => 2 ] );

		$this->assertSame( 3, $result['scanned'] );
		$this->assertSame( 3, $result['stored'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertFalse( $result['dry_run'] );
		$this->assertTrue( $this->updated );
	}

	/**
	 * A dry run reports the same counts without writing.
	 */
	public function test_dry_run_does_not_write(): void {
		$this->addPost( 1, [ 'red apples' ] );
		$this->queuePages( [ [ 1 ], [] ] );

		$result = ( new RecalculateCommand() )->batch( [ 'dry_run' => true ] );

		$this->assertSame( 1, $result['scanned'] );
		$this->assertSame( 1, $result['stored'] );
		$this->assertTrue( $result['dry_run'] );
		$this->assertFalse( $this->updated );
	}

	/**
	 * A post without keywords is counted as skipped.
	 */
	public function test_batch_counts_a_post_without_keywords_as_skipped(): void {
		$this->addPost( 1, [] );
		$this->queuePages( [ [ 1 ], [] ] );

		$result = ( new RecalculateCommand() )->batch( [] );

		$this->assertSame( 1, $result['scanned'] );
		$this->assertSame( 0, $result['stored'] );
		$this->assertSame( 1, $result['skipped'] );
	}

	/**
	 * The batch honours the post type and post status filters.
	 */
	public function test_batch_honours_the_filters(): void {
		$this->addPost( 1, [ 'red apples' ] );
		$this->queuePages( [ [ 1 ], [] ] );

		( new RecalculateCommand() )->batch(
			[
				'post_type'   => [ 'page' ],
				'post_status' => 'draft',
			]
		);

		$this->assertSame( [ 'page' ], $this->queried[0]['post_type'] );
		$this->assertSame( 'draft', $this->queried[0]['post_status'] );
	}

	/**
	 * Recalculate is a no-op without the WP-CLI runtime, never a fatal.
	 */
	public function test_recalculate_is_safe_without_wp_cli(): void {
		$command = new RecalculateCommand();

		$command->recalculate( [], [] );

		$this->assertFalse( $this->updated );
	}
}
