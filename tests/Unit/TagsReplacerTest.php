<?php
/**
 * TagsReplacer tests.
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
 * Tags Replacer Test.
 */
final class TagsReplacerTest extends TestCase {
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
	 * Make Query.
	 *
	 * @param int    $id   Id.
	 * @param string $type Type.
	 * @return WP_Query The result.
	 */
	private function makeQuery( int $id = 1, string $type = 'home' ): WP_Query {
		$q = Mockery::mock( WP_Query::class );
		$q->shouldReceive( 'is_singular' )->andReturn( 'post' === $type )->byDefault();
		$q->shouldReceive( 'is_search' )->andReturn( 'search' === $type )->byDefault();
		$q->shouldReceive( 'is_404' )->andReturn( '404' === $type )->byDefault();
		$q->shouldReceive( 'is_feed' )->andReturn( 'feed' === $type )->byDefault();
		$q->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_home' )->andReturn( 'home' === $type )->byDefault();
		$q->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$q->shouldReceive( 'get_queried_object_id' )->andReturn( $id )->byDefault();
		$q->shouldReceive( 'get' )->andReturn( 0 )->byDefault();
		return $q;
	}

	/**
	 * Make Context.
	 *
	 * @param WP_Query $query Query.
	 * @return Context The result.
	 */
	private function makeContext( WP_Query $query ): Context {
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Functions\when( 'get_the_title' )->justReturn( 'My Title' );
		Functions\when( 'get_the_excerpt' )->justReturn( 'Excerpt here' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '2026-01-01' );
		Functions\when( 'get_the_author' )->justReturn( 'Author' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'date_i18n' )->justReturn( 'January 1, 2026' );
		Functions\when( 'is_feed' )->justReturn( false );

		$settings = new SettingsStore();
		return new Context( $query, $settings );
	}

	/**
	 * Test memoization same ctx field invokes filter once.
	 */
	public function test_memoization_same_ctx_field_invokes_filter_once(): void {
		$query    = $this->makeQuery( 1, 'home' );
		$ctx      = $this->makeContext( $query );
		$replacer = new TagsReplacer();

		$callCount = 0;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) use ( &$callCount ) {
				if ( 'rankkernel/tokens' === $hook ) {
					++$callCount;
				}
				return $value;
			}
		);

		$a = $replacer->replace( $ctx, '%%title%% %%sep%% %%sitename%%', 'title' );
		$b = $replacer->replace( $ctx, '%%title%% %%sep%% %%sitename%%', 'title' );

		$this->assertSame( $a, $b );
		$this->assertSame( 1, $callCount, 'apply_filters should be called once due to memoization' );
	}

	/**
	 * Test memoization different field separate.
	 */
	public function test_memoization_different_field_separate(): void {
		$query    = $this->makeQuery( 2, 'home' );
		$ctx      = $this->makeContext( $query );
		$replacer = new TagsReplacer();

		$callCount = 0;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) use ( &$callCount ) {
				if ( 'rankkernel/tokens' === $hook ) {
					++$callCount;
				}
				return $value;
			}
		);

		$replacer->replace( $ctx, '%%title%%', 'title' );
		$replacer->replace( $ctx, '%%title%%', 'description' );

		$this->assertSame( 2, $callCount );
	}

	/**
	 * Test unknown token stripped.
	 */
	public function test_unknown_token_stripped(): void {
		$query    = $this->makeQuery( 3, 'home' );
		$ctx      = $this->makeContext( $query );
		$replacer = new TagsReplacer();

		Functions\when( 'apply_filters' )->justReturn(
			[
				'title'       => 'My Title',
				'sitename'    => 'My Site',
				'sep'         => '–',
				'excerpt'     => '',
				'date'        => '',
				'author'      => '',
				'category'    => '',
				'page'        => '',
				'currentdate' => '',
			]
		);

		$result = $replacer->replace( $ctx, 'Hello %%unknown%% world', 'field' );

		$this->assertSame( 'Hello  world', $result );
	}

	/**
	 * Test custom token via filter resolves.
	 */
	public function test_custom_token_via_filter_resolves(): void {
		$query    = $this->makeQuery( 4, 'home' );
		$ctx      = $this->makeContext( $query );
		$replacer = new TagsReplacer();

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $map ) {
				if ( 'rankkernel/tokens' === $hook && is_array( $map ) ) {
					$map['custom'] = 'CustomVal';
					return $map;
				}
				return $map;
			}
		);

		$result = $replacer->replace( $ctx, 'X %%custom%% Y', 'field2' );

		$this->assertSame( 'X CustomVal Y', $result );
	}

	/**
	 * Test token caching reuses individual token values across different fields.
	 */
	public function test_token_caching_across_different_fields(): void {
		$query    = $this->makeQuery( 5, 'post' );
		$ctx      = $this->makeContext( $query );
		$replacer = new TagsReplacer();

		$categoryCallCount = 0;
		Functions\when( 'get_the_category' )->alias(
			static function () use ( &$categoryCallCount ) {
				++$categoryCallCount;
				return [ (object) [ 'name' => 'News' ] ];
			}
		);

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, $map ) => $map );

		$res1 = $replacer->replace( $ctx, 'Cat: %%category%%', 'field1' );
		$res2 = $replacer->replace( $ctx, 'Category: %%category%%', 'field2' );

		$this->assertSame( 'Cat: News', $res1 );
		$this->assertSame( 'Category: News', $res2 );
		$this->assertSame( 1, $categoryCallCount, 'get_the_category should be invoked only once due to token caching' );
	}

	/**
	 * Test cached token values stay isolated between contexts.
	 */
	public function test_cached_token_values_stay_isolated_between_contexts(): void {
		$replacer = new TagsReplacer();
		$ctxA     = $this->makeContext( $this->makeQuery( 10, 'post' ) );
		$ctxB     = $this->makeContext( $this->makeQuery( 20, 'post' ) );

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value = null ): mixed => $value );
		Functions\when( 'get_the_category' )->alias(
			static fn ( int $id ): array => [ (object) [ 'name' => 10 === $id ? 'News' : 'Sports' ] ]
		);

		$this->assertSame( 'News', $replacer->replace( $ctxA, '%%category%%', 'title' ) );
		$this->assertSame( 'Sports', $replacer->replace( $ctxB, '%%category%%', 'title' ) );
		$this->assertSame( 'News', $replacer->replace( $ctxA, '%%category%%', 'description' ) );
	}

	/**
	 * Test clear memo also drops the cached token values.
	 */
	public function test_clear_memo_drops_the_cached_token_values(): void {
		$replacer = new TagsReplacer();
		$ctx      = $this->makeContext( $this->makeQuery( 10, 'post' ) );

		$categoryCallCount = 0;

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value = null ): mixed => $value );
		Functions\when( 'get_the_category' )->alias(
			static function () use ( &$categoryCallCount ): array {
				++$categoryCallCount;

				return [ (object) [ 'name' => 'News' ] ];
			}
		);

		$replacer->replace( $ctx, '%%category%%', 'title' );
		$replacer->replace( $ctx, '%%category%%', 'description' );

		$this->assertSame( 1, $categoryCallCount, 'A second field on the same context must reuse the cached token value.' );

		$replacer->clearMemo();

		$replacer->replace( $ctx, '%%category%%', 'title' );

		$this->assertSame( 2, $categoryCallCount, 'Clearing the memo must drop the cached token values as well.' );
	}

	/**
	 * Make a term archive query.
	 *
	 * @param int    $id   Term id.
	 * @param string $name Term name.
	 * @return WP_Query The result.
	 */
	private function makeTermQuery( int $id = 9, string $name = 'News' ): WP_Query {
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
	private function makeAuthorQuery( int $id = 5 ): WP_Query {
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
	 * Test the author token on an author archive returns the author name.
	 */
	public function test_author_token_on_author_archive_returns_display_name(): void {
		$ctx = $this->makeContext( $this->makeAuthorQuery( 5 ) );

		Functions\when( 'get_the_author_meta' )->alias(
			static fn ( string $field, int $id ): string => 'display_name' === $field && 5 === $id ? 'Jane Doe' : ''
		);
		// The colliding post author must never win on an author archive.
		Functions\when( 'get_post_field' )->alias(
			static fn ( string $field, int $id ): string => 'post_author' === $field && $id > 0 ? '77' : ''
		);

		$replacer = new TagsReplacer();

		$this->assertSame( 'Jane Doe', $replacer->replace( $ctx, '%%author%%', 'title' ) );
	}

	/**
	 * Test the category token on a term archive returns the term name.
	 */
	public function test_category_token_on_term_archive_returns_term_name(): void {
		$ctx = $this->makeContext( $this->makeTermQuery( 9, 'News' ) );

		Functions\when( 'get_the_category' )->alias(
			static fn ( int $id ): array => $id > 0 ? [ (object) [ 'name' => 'Colliding Category' ] ] : []
		);

		$replacer = new TagsReplacer();

		$this->assertSame( 'News', $replacer->replace( $ctx, '%%category%%', 'title' ) );
	}

	/**
	 * Test the author token on a singular post is unchanged.
	 */
	public function test_author_token_on_singular_post_unchanged(): void {
		$ctx = $this->makeContext( $this->makeQuery( 42, 'post' ) );

		Functions\when( 'get_post_field' )->alias(
			static fn ( string $field, int $id ): string => 'post_author' === $field && 42 === $id ? '7' : ''
		);
		Functions\when( 'get_the_author_meta' )->alias(
			static fn ( string $field, int $id ): string => 'display_name' === $field && 7 === $id ? 'Post Author' : ''
		);

		$replacer = new TagsReplacer();

		$this->assertSame( 'Post Author', $replacer->replace( $ctx, '%%author%%', 'title' ) );
	}

	/**
	 * Test the category token on a singular post is unchanged.
	 */
	public function test_category_token_on_singular_post_unchanged(): void {
		$ctx = $this->makeContext( $this->makeQuery( 42, 'post' ) );

		Functions\when( 'get_the_category' )->alias(
			static fn ( int $id ): array => 42 === $id ? [ (object) [ 'name' => 'Tech' ] ] : []
		);

		$replacer = new TagsReplacer();

		$this->assertSame( 'Tech', $replacer->replace( $ctx, '%%category%%', 'title' ) );
	}

	/**
	 * Every supported token resolves a real value in its correct context.
	 *
	 * One assertion per built in token proves the backend resolves each
	 * token the editor advertises, including %%currentdate%% which the
	 * editor previously did not offer.
	 */
	public function test_every_supported_token_resolves_a_real_value(): void {
		$ctx = $this->makeContext( $this->makeQuery( 42, 'post' ) );

		Functions\when( 'get_the_title' )->justReturn( 'Real Title' );
		Functions\when( 'get_the_excerpt' )->justReturn( 'Real excerpt' );
		Functions\when( 'get_the_date' )->justReturn( '2026-01-01' );
		Functions\when( 'get_the_author' )->justReturn( 'Real Author' );
		Functions\when( 'get_the_category' )->justReturn( [ (object) [ 'name' => 'News' ] ] );
		Functions\when( 'get_query_var' )->alias(
			static function ( string $queryVar, mixed $fallback = '' ): mixed {
				if ( 'paged' === $queryVar ) {
					return 2;
				}

				if ( 'max_num_pages' === $queryVar ) {
					return 5;
				}

				return $fallback;
			}
		);
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value = null ): mixed => $value );

		$expected = [
			'title'       => 'Real Title',
			'sitename'    => 'My Site',
			'sep'         => '–',
			'excerpt'     => 'Real excerpt',
			'date'        => '2026-01-01',
			'author'      => 'Real Author',
			'category'    => 'News',
			'page'        => 'Page 2 of 5',
			'currentdate' => 'January 1, 2026',
		];

		// The canonical backend list must cover every expectation exactly.
		$this->assertSame( array_keys( $expected ), TagsReplacer::SUPPORTED_TOKENS );

		$replacer = new TagsReplacer();

		foreach ( $expected as $token => $value ) {
			$this->assertSame( $value, $replacer->replace( $ctx, '%%' . $token . '%%', 'field_' . $token ), 'token ' . $token );
		}
	}

	/**
	 * Test selective token resolution skips unused tokens like category or author.
	 */
	public function test_selective_token_resolution_skips_unused_tokens(): void {
		$ctx      = $this->makeContext( $this->makeQuery( 42, 'post' ) );
		$replacer = new TagsReplacer();

		$categoryCallCount = 0;
		Functions\when( 'get_the_category' )->alias(
			static function () use ( &$categoryCallCount ): array {
				++$categoryCallCount;

				return [ (object) [ 'name' => 'Should Not Be Called' ] ];
			}
		);

		Functions\when( 'get_the_title' )->justReturn( 'Title Only' );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value = null ): mixed => $value );

		$result = $replacer->replace( $ctx, '%%title%% %%sep%% %%sitename%%', 'title' );

		$this->assertSame( 'Title Only – My Site', $result );
		$this->assertSame( 0, $categoryCallCount, 'Unused token %category% must not be evaluated when not in template.' );
	}
}
