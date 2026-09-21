<?php
/**
 * Analysis save handler guard tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\AnalysisSaveHandler;
use RankKernel\Modules\Analysis\AnalysisScore;
use WP_Post;

/**
 * Analysis Save Handler Test.
 */
final class AnalysisSaveHandlerTest extends TestCase {
	/**
	 * Post returned by get post.
	 *
	 * @var WP_Post
	 */
	private WP_Post $post;

	/**
	 * Meta store.
	 *
	 * @var array<string, mixed>
	 */
	private array $meta = [];

	/**
	 * Last written record.
	 *
	 * @var mixed
	 */
	private mixed $saved = null;

	/**
	 * Delete calls, each a post id and meta key pair.
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	private array $deleted = [];

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
		$post->ID           = 11;
		$post->post_title   = 'Red apples guide';
		$post->post_name    = 'red-apples-guide';
		$post->post_content = '<p>We write about red apples and how to pick them. Red apples keep well.</p>';
		$this->post         = $post;
		$this->meta         = [ '_rankkernel_meta_data' => [ 'focus_keywords' => [ 'red apples' ] ] ];
		$this->saved        = null;
		$this->deleted      = [];

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
			function ( int $id, string $key ): mixed {
				return $this->meta[ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $id, string $key, mixed $value ): bool {
				$this->saved = $value;

				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			function ( int $id, string $key ): bool {
				$this->deleted[] = [ $id, $key ];

				return true;
			}
		);
		Functions\when( 'get_post_types' )->alias(
			static fn (): array => [
				'post'       => 'post',
				'page'       => 'page',
				'attachment' => 'attachment',
			]
		);
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A normal save stores the record.
	 */
	public function test_save_stores_the_record(): void {
		( new AnalysisSaveHandler() )->handle( 11, $this->post );

		$this->assertIsArray( $this->saved );
		$this->assertArrayHasKey( 'score', $this->saved );
		$this->assertSame( 1, $this->saved['keywords'] );
		$this->assertSame( [], $this->deleted );
	}

	/**
	 * An autosave leaves the stored value untouched.
	 */
	public function test_autosave_is_skipped(): void {
		Functions\when( 'wp_is_post_autosave' )->justReturn( true );

		( new AnalysisSaveHandler() )->handle( 11, $this->post );

		$this->assertNull( $this->saved );
		$this->assertSame( [], $this->deleted );
	}

	/**
	 * A revision leaves the stored value untouched.
	 */
	public function test_revision_is_skipped(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( true );

		( new AnalysisSaveHandler() )->handle( 11, $this->post );

		$this->assertNull( $this->saved );
		$this->assertSame( [], $this->deleted );
	}

	/**
	 * An unsupported post type is skipped.
	 */
	public function test_unsupported_post_type_is_skipped(): void {
		Functions\when( 'get_post_type' )->justReturn( 'attachment' );

		( new AnalysisSaveHandler() )->handle( 11, $this->post );

		$this->assertNull( $this->saved );
		$this->assertSame( [], $this->deleted );
	}

	/**
	 * A user without the capability is skipped without a user facing error.
	 */
	public function test_missing_capability_is_skipped(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		( new AnalysisSaveHandler() )->handle( 11, $this->post );

		$this->assertNull( $this->saved );
		$this->assertSame( [], $this->deleted );
	}

	/**
	 * A save with no keywords clears any stored score.
	 */
	public function test_save_without_keywords_clears_the_record(): void {
		$this->meta['_rankkernel_meta_data'] = [ 'focus_keywords' => [] ];

		( new AnalysisSaveHandler() )->handle( 11, $this->post );

		$this->assertSame( [ [ 11, AnalysisScore::META_KEY ] ], $this->deleted );
		$this->assertNull( $this->saved );
	}

	/**
	 * Register adds the after insert hook, which core runs after the REST meta
	 * write, and the run then sees the payload from this save, not the last one.
	 */
	public function test_register_hooks_after_the_rest_meta_write(): void {
		$captured = [];

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, mixed $callback, int $priority = 10, int $accepted = 1 ) use ( &$captured ): bool {
				$captured[] = [ $hook, $priority, $accepted ];

				return true;
			}
		);

		( new AnalysisSaveHandler() )->register();

		// save_post runs before core writes the REST meta field, which is the
		// defect this test pins, so the handler must bind the after insert hook.
		$this->assertSame( [ [ 'wp_after_insert_post', 20, 2 ] ], $captured );

		// Model the state at that hook: the REST meta write has already landed,
		// so the store holds the keywords from this save, not the last one.
		$this->meta['_rankkernel_meta_data'] = [ 'focus_keywords' => [ 'green apples', 'red apples' ] ];

		( new AnalysisSaveHandler() )->handle( 11, $this->post );

		$this->assertIsArray( $this->saved );
		$this->assertSame( 2, $this->saved['keywords'] );
		$this->assertSame( [], $this->deleted );
	}
}
