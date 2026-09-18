<?php
/**
 * Context tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Metadata\TagsReplacer;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Context Test.
 */
final class ContextTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : '' );
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Make Query Singular.
	 *
	 * @param int $id Id.
	 * @return WP_Query The result.
	 */
	private function makeQuerySingular( int $id = 42 ): WP_Query {
		$q = Mockery::mock( WP_Query::class );
		$q->shouldReceive( 'is_singular' )->andReturn( true )->byDefault();
		$q->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'get_queried_object_id' )->andReturn( $id )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( 0 )->byDefault();
		return $q;
	}

	/**
	 * Test meta performs exactly one get post meta.
	 */
	public function test_meta_performs_exactly_one_get_post_meta(): void {
		$query = $this->makeQuerySingular( 7 );
		Functions\when( 'get_query_var' )->justReturn( 0 );

		$settings = new SettingsStore();
		Functions\when( 'get_option' )->justReturn( [] );

		Functions\expect( 'get_post_meta' )->once()->with( 7, '_rankkernel_meta_data', true )->andReturn( [ 'title' => 'Hello' ] );

		$ctx = new Context( $query, $settings );
		$a   = $ctx->meta();
		$b   = $ctx->meta();

		$this->assertSame( $a, $b );
		$this->assertSame( 'Hello', $a['title'] );
	}

	/**
	 * Test meta term performs one get term meta.
	 */
	public function test_meta_term_performs_one_get_term_meta(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 99 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );

		Functions\expect( 'get_term_meta' )->once()->with( 99, '_rankkernel_term_data', true )->andReturn( [ 'title' => 'Term Title' ] );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );
		$meta     = $ctx->meta();

		$this->assertSame( 'Term Title', $meta['title'] );

		// Second call should not trigger another get_term_meta.
		$meta2 = $ctx->meta();
		$this->assertSame( $meta, $meta2 );
	}

	/**
	 * Test hash stable.
	 */
	public function test_hash_stable(): void {
		$query = $this->makeQuerySingular( 1 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( $ctx->hash(), $ctx->hash() );
	}

	/**
	 * Test og image fallback chain payload image.
	 */
	public function test_og_image_fallback_chain_payload_image(): void {
		$query = $this->makeQuerySingular( 5 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'get_post_meta' )->once()->andReturn(
			[
				'og' => [
					'image'    => 'https://example.com/payload.jpg',
					'image_id' => 0,
				],
			]
		);

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'https://example.com/payload.jpg', $ctx->ogImage() );
		$this->assertSame( 0, $ctx->ogImageAttachmentId() );
	}

	/**
	 * Test og image fallback chain attachment id.
	 */
	public function test_og_image_fallback_chain_attachment_id(): void {
		$query = $this->makeQuerySingular( 6 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'get_post_meta' )->once()->andReturn(
			[
				'og' => [
					'image'    => '',
					'image_id' => 123,
				],
			]
		);
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/attach.jpg' );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'https://example.com/attach.jpg', $ctx->ogImage() );
		$this->assertSame( 123, $ctx->ogImageAttachmentId() );
	}

	/**
	 * Test og image fallback featured.
	 */
	public function test_og_image_fallback_featured(): void {
		$query = $this->makeQuerySingular( 8 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'get_post_meta' )->once()->andReturn(
			[
				'og' => [
					'image'    => '',
					'image_id' => 0,
				],
			]
		);
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 999 );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/featured.jpg' );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'https://example.com/featured.jpg', $ctx->ogImage() );
		$this->assertSame( 999, $ctx->ogImageAttachmentId() );
	}

	/**
	 * Test og image custom url with featured dims not paired.
	 */
	public function test_og_image_custom_url_with_featured_dims_not_paired(): void {
		$query = $this->makeQuerySingular( 9 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'get_post_meta' )->once()->andReturn(
			[
				'og' => [
					'image'    => 'https://example.com/custom.jpg',
					'image_id' => 123,
				],
			]
		);
		// wp_get_attachment_image_url should NOT be called for custom URL case (id ignored).
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/should-not-be-used.jpg' );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 999 );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'https://example.com/custom.jpg', $ctx->ogImage() );
		$this->assertSame( 0, $ctx->ogImageAttachmentId(), 'custom URL must have attachment id 0' );
	}

	/**
	 * Test og image both set prefers custom url.
	 */
	public function test_og_image_both_set_prefers_custom_url(): void {
		$query = $this->makeQuerySingular( 10 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'get_post_meta' )->once()->andReturn(
			[
				'og' => [
					'image'    => 'https://example.com/custom2.jpg',
					'image_id' => 55,
				],
			]
		);
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/from-id.jpg' );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		// Custom URL wins, id must be 0.
		$this->assertSame( 'https://example.com/custom2.jpg', $ctx->ogImage() );
		$this->assertSame( 0, $ctx->ogImageAttachmentId() );
	}

	/**
	 * Test permalink author archive.
	 */
	public function test_permalink_author_archive(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_author' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_date' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_post_type_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 7 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( null )->byDefault();
		$query->shouldReceive( 'get_queried_object' )->andReturn( (object) [ 'ID' => 7 ] )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_author_posts_url' )->justReturn( 'https://example.com/author/john/' );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'https://example.com/author/john/', $ctx->permalink() );
		$this->assertSame( 'archive', $ctx->queriedType() );
	}

	/**
	 * Test permalink home for home type.
	 */
	public function test_permalink_home_for_home_type(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 0 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( null )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'https://example.com/', $ctx->permalink() );
	}

	/**
	 * Test posts page permalink returns page for posts.
	 */
	public function test_posts_page_permalink_returns_page_for_posts(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 0 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( null )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'page_for_posts' === $key ) {
					return 42;
				}
				return $fallback;
			}
		);
		Functions\when( 'get_permalink' )->alias(
			static function ( int $id ): string {
				if ( 42 === $id ) {
					return 'https://example.com/blog/';
				}
				return '';
			}
		);
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'home', $ctx->queriedType() );
		$this->assertSame( 'https://example.com/blog/', $ctx->permalink() );
	}

	/**
	 * Test resolved memoized by hash field.
	 */
	public function test_resolved_memoized_by_hash_field(): void {
		$query = $this->makeQuerySingular( 1 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Functions\when( 'get_the_title' )->justReturn( 'Title' );
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '2026-01-01' );
		Functions\when( 'get_the_author' )->justReturn( 'Author' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'date_i18n' )->justReturn( 'Jan 1' );

		$callCount = 0;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $val ) use ( &$callCount ) {
				if ( 'rankkernel/tokens' === $hook ) {
					++$callCount;
				}
				return $val;
			}
		);

		$settings = new SettingsStore();
		$replacer = new TagsReplacer();
		$ctx      = new Context( $query, $settings, $replacer );

		$a = $ctx->resolved( 'title', '%%title%%' );
		$b = $ctx->resolved( 'title', '%%title%%' );

		$this->assertSame( $a, $b );
		$this->assertSame( 1, $callCount );
	}

	/**
	 * Make a term archive query.
	 *
	 * @param int    $id   Term id.
	 * @param string $name Term name.
	 * @return WP_Query The result.
	 */
	private function makeQueryTerm( int $id = 9, string $name = 'News' ): WP_Query {
		$q = Mockery::mock( WP_Query::class );
		$q->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_category' )->andReturn( true )->byDefault();
		$q->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_author' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'get_queried_object_id' )->andReturn( $id )->byDefault();
		$q->shouldReceive( 'get_queried_object' )->andReturn( (object) [ 'name' => $name ] )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		return $q;
	}

	/**
	 * Make an author archive query.
	 *
	 * @param int $id Author id.
	 * @return WP_Query The result.
	 */
	private function makeQueryAuthor( int $id = 5 ): WP_Query {
		$q = Mockery::mock( WP_Query::class );
		$q->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_archive' )->andReturn( true )->byDefault();
		$q->shouldReceive( 'is_author' )->andReturn( true )->byDefault();
		$q->shouldReceive( 'get_queried_object_id' )->andReturn( $id )->byDefault();
		$q->shouldReceive( 'get_queried_object' )->andReturn( (object) [ 'ID' => $id ] )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		return $q;
	}

	/**
	 * Test title on a term archive returns the term name, never a colliding post title.
	 */
	public function test_title_on_term_archive_returns_term_name(): void {
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		// A post with this id exists and would win the old buggy path.
		Functions\when( 'get_the_title' )->justReturn( 'Colliding Post Title' );

		$settings = new SettingsStore();
		$ctx      = new Context( $this->makeQueryTerm( 9, 'News' ), $settings );

		$this->assertSame( 'term', $ctx->queriedType() );
		$this->assertSame( 'News', $ctx->title() );
	}

	/**
	 * Test title on an author archive returns the author display name.
	 */
	public function test_title_on_author_archive_returns_display_name(): void {
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_the_title' )->justReturn( 'Colliding Post Title' );
		Functions\when( 'get_the_author_meta' )->alias(
			static fn ( string $field, int $id ): string => 'display_name' === $field && 5 === $id ? 'Jane Doe' : ''
		);

		$settings = new SettingsStore();
		$ctx      = new Context( $this->makeQueryAuthor( 5 ), $settings );

		$this->assertSame( 'Jane Doe', $ctx->authorDisplayName() );
		$this->assertSame( 'Jane Doe', $ctx->title() );
	}

	/**
	 * Test title on a singular post still resolves the post title.
	 */
	public function test_title_on_singular_post_unchanged(): void {
		$query = $this->makeQuerySingular( 42 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_the_title' )->justReturn( 'Post Title' );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'Post Title', $ctx->title() );
	}

	/**
	 * Test a JSON string meta row decodes through meta() with one read.
	 */
	public function test_meta_decodes_json_string_row(): void {
		$query = $this->makeQuerySingular( 7 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$raw = (string) json_encode( [ 'title' => 'From JSON' ] );

		Functions\expect( 'get_post_meta' )->once()->with( 7, '_rankkernel_meta_data', true )->andReturn( $raw );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'From JSON', $ctx->meta()['title'] );
		$this->assertSame( 'From JSON', $ctx->meta()['title'] );
	}

	/**
	 * Test a serialized string meta row decodes through meta().
	 */
	public function test_meta_decodes_serialized_string_row(): void {
		$query = $this->makeQuerySingular( 8 );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );

		$raw = serialize( [ 'title' => 'From Serialized' ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.

		Functions\when( 'get_post_meta' )->justReturn( $raw );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		$this->assertSame( 'From Serialized', $ctx->meta()['title'] );
	}
}
