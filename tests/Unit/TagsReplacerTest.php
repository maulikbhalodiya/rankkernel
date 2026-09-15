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
}
