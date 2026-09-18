<?php
/**
 * Llms.txt selection tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsCollector;
use RankKernel\Modules\Robots\LlmsSettings;

/**
 * Llms Collector Test.
 */
final class LlmsCollectorTest extends TestCase {
	/**
	 * Posts returned by the get_posts stub.
	 *
	 * @var array<int, object>
	 */
	private array $posts = [];

	/**
	 * Post meta payloads keyed by id.
	 *
	 * @var array<int, mixed>
	 */
	private array $meta = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->posts = [];
		$this->meta  = [];

		Functions\when( 'get_posts' )->alias( fn (): array => $this->posts );
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $id, string $key, bool $single = false ): mixed => $this->meta[ $id ] ?? '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
		);
		Functions\when( 'get_permalink' )->alias( static fn ( object $post ): string => 'https://example.com/p/' . (string) $post->ID );
		Functions\when( 'get_post_type_object' )->alias(
			static function ( string $type ): object {
				$labels       = new \stdClass();
				$labels->name = ucfirst( $type );

				$object         = new \stdClass();
				$object->labels = $labels;

				return $object;
			}
		);
		Functions\when( 'get_terms' )->justReturn( [] );
		Functions\when( 'get_term_link' )->alias( static fn ( object $term ): string => 'https://example.com/t/' . (string) $term->term_id );
		Functions\when( 'get_taxonomy' )->alias(
			static function ( string $tax ): object {
				$labels       = new \stdClass();
				$labels->name = ucfirst( $tax );

				$object         = new \stdClass();
				$object->labels = $labels;

				return $object;
			}
		);
		Functions\when( 'strip_shortcodes' )->alias( static fn ( string $text ): string => (string) preg_replace( '/\[[^\]]*\]/', '', $text ) );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $text ): string => strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a settings object with partial overrides.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return LlmsSettings The result.
	 */
	private function settings( array $overrides = [] ): LlmsSettings {
		$settings = new LlmsSettings();

		if ( [] !== $overrides ) {
			Functions\when( 'get_option' )->justReturn( $overrides );
		}

		return $settings;
	}

	/**
	 * Test excludes noindex password and attachment posts.
	 */
	public function test_excludes_noindex_password_and_attachment(): void {
		$keep                = new \stdClass();
		$keep->ID            = 1;
		$keep->post_type     = 'post';
		$keep->post_password = '';
		$keep->post_title    = 'Keep me';
		$keep->post_excerpt  = '';
		$keep->post_content  = 'Body text';

		$noindex                = new \stdClass();
		$noindex->ID            = 2;
		$noindex->post_type     = 'post';
		$noindex->post_password = '';
		$noindex->post_title    = 'Noindex me';
		$noindex->post_excerpt  = '';
		$noindex->post_content  = 'Body text';

		$password                = new \stdClass();
		$password->ID            = 3;
		$password->post_type     = 'post';
		$password->post_password = 'secret';
		$password->post_title    = 'Locked';
		$password->post_excerpt  = '';
		$password->post_content  = 'Body text';

		$this->posts = [ $keep, $noindex, $password ];
		$this->meta  = [
			2 => [ 'robots' => [ 'index' => false ] ],
			3 => [],
		];

		$sections = ( new LlmsCollector() )->collect(
			$this->settings(
				[
					'post_types' => [ 'post' ],
					'taxonomies' => [],
				]
			)
		);

		$this->assertCount( 1, $sections );
		$this->assertCount( 1, $sections[0]['items'] );
		$this->assertSame( 'Keep me', $sections[0]['items'][0]['title'] );
	}

	/**
	 * Test trims excerpt and strips markup.
	 */
	public function test_trims_excerpt_and_strips_markup(): void {
		$post                = new \stdClass();
		$post->ID            = 5;
		$post->post_type     = 'post';
		$post->post_password = '';
		$post->post_title    = 'Title';
		$post->post_excerpt  = '';
		$post->post_content  = '<p>Hello [shortcode] world this is a long body that should be trimmed at a word boundary.</p>';

		$this->posts = [ $post ];

		$sections = ( new LlmsCollector() )->collect(
			$this->settings(
				[
					'post_types'     => [ 'post' ],
					'taxonomies'     => [],
					'excerpt_length' => 20,
				]
			)
		);

		$excerpt = $sections[0]['items'][0]['excerpt'];

		$this->assertStringNotContainsString( '<p>', $excerpt );
		$this->assertStringNotContainsString( '[shortcode]', $excerpt );
		$this->assertStringEndsWith( '...', $excerpt );
		$this->assertLessThanOrEqual( 24, strlen( $excerpt ) );
	}

	/**
	 * Test taxonomy section is built from terms.
	 */
	public function test_taxonomy_section_from_terms(): void {
		$term              = new \stdClass();
		$term->term_id     = 10;
		$term->name        = 'News';
		$term->description = 'Latest news';

		Functions\when( 'get_terms' )->justReturn( [ $term ] );

		$sections = ( new LlmsCollector() )->collect(
			$this->settings(
				[
					'post_types' => [],
					'taxonomies' => [ 'category' ],
				]
			)
		);

		$this->assertCount( 1, $sections );
		$this->assertSame( 'Category', $sections[0]['title'] );
		$this->assertSame( 'News', $sections[0]['items'][0]['title'] );
	}
}
