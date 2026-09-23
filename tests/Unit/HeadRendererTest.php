<?php
/**
 * HeadRenderer tests, single-pass, escaping, fallback chains, feed early-return.
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
use RankKernel\Modules\Metadata\HeadRenderer;
use RankKernel\Modules\Metadata\TagsReplacer;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Head Renderer Test.
 */
final class HeadRendererTest extends TestCase {
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
		// Escaping stubs, return value escaped for test verification (simple pass-through with htmlspecialchars).
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $v ): bool => $v instanceof \WP_Error );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'get_term_field' )->justReturn( '' );
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
	 * Make Singular Context.
	 *
	 * @param array $metaPayload       Meta Payload.
	 * @param array $settingsOverrides Settings Overrides.
	 * @return array{Context, SettingsStore, WP_Query}
	 */
	private function makeSingularContext( array $metaPayload = [], array $settingsOverrides = [] ): array {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 1 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Functions\when( 'get_the_title' )->justReturn( 'Post Title' );
		Functions\when( 'get_the_excerpt' )->justReturn( 'Excerpt text for description fallback that is trimmed' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/post/' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'get_the_date' )->justReturn( '2026-01-01' );
		Functions\when( 'get_the_author' )->justReturn( 'Author' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'date_i18n' )->justReturn( 'Jan 1, 2026' );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );

		// Settings store, stub get_option to provide overrides.
		$defaults = SettingsStore::defaults();
		$stored   = array_merge( $defaults, $settingsOverrides );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( $stored ) {
				if ( 'rankkernel_settings' === $key ) {
					return $stored;
				}
				if ( 'rankkernel_modules' === $key ) {
					return [ 'metadata' ];
				}
				return $fallback;
			}
		);

		// Meta read.
		Functions\when( 'get_post_meta' )->justReturn( $metaPayload );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ) => $v );
		Functions\when( 'do_action' )->justReturn( null );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );

		return [ $ctx, $settings, $query ];
	}

	/**
	 * Test render omits robots for clean index follow.
	 */
	public function test_render_omits_robots_for_clean_index_follow(): void {
		[ $ctx, $settings ] = $this->makeSingularContext();
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		Functions\when( 'do_action' )->justReturn( null );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringNotContainsString( 'name="robots"', $out );
	}

	/**
	 * Test render never emits its own robots tag and the directive rides wp_robots.
	 */
	public function test_render_emits_robots_with_noindex(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'robots' => [
					'index'  => false,
					'follow' => true,
				],
			]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		// Exactly one robots concept: none printed by render, core owns the tag.
		$this->assertStringNotContainsString( 'name="robots"', $out );

		$robots = $renderer->filterRobots( [] );

		$this->assertTrue( $robots['noindex'] );
	}

	/**
	 * Test filter robots keeps the tighter max snippet budget.
	 */
	public function test_render_robots_max_snippet(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'robots' => [
					'index'       => true,
					'follow'      => true,
					'max_snippet' => 120,
				],
			]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringNotContainsString( 'name="robots"', $out );

		$robots = $renderer->filterRobots( [] );

		$this->assertSame( 120, $robots['max-snippet'] );
	}

	/**
	 * Test filter robots keeps a core noindex when RankKernel says nothing.
	 */
	public function test_filter_robots_keeps_core_noindex_when_rankerkernel_says_nothing(): void {
		[ $ctx, $settings ] = $this->makeSingularContext();
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$robots = $renderer->filterRobots( [ 'noindex' => true ] );

		$this->assertTrue( $robots['noindex'] );
	}

	/**
	 * Test filter robots adds a noindex when core says nothing.
	 */
	public function test_filter_robots_adds_noindex_when_core_says_nothing(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[ 'robots' => [ 'index' => false ] ]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$robots = $renderer->filterRobots( [] );

		$this->assertTrue( $robots['noindex'] );
	}

	/**
	 * Test filter robots keeps the tighter of a no limit and a numeric snippet budget.
	 */
	public function test_filter_robots_keeps_tighter_max_snippet(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[ 'robots' => [ 'max_snippet' => 120 ] ]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$this->assertSame( 120, $renderer->filterRobots( [ 'max-snippet' => -1 ] )['max-snippet'] );
		$this->assertSame( 80, $renderer->filterRobots( [ 'max-snippet' => 80 ] )['max-snippet'] );
	}

	/**
	 * Test filter robots keeps the tighter of large and standard image preview.
	 */
	public function test_filter_robots_keeps_tighter_image_preview(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[ 'robots' => [ 'max_image_preview' => 'standard' ] ]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$this->assertSame( 'standard', $renderer->filterRobots( [ 'max-image-preview' => 'large' ] )['max-image-preview'] );

		[ $ctx2, $settings2 ] = $this->makeSingularContext(
			[ 'robots' => [ 'max_image_preview' => 'large' ] ]
		);
		$renderer2            = new HeadRenderer( $settings2, null, $ctx2 );

		$this->assertSame( 'standard', $renderer2->filterRobots( [ 'max-image-preview' => 'standard' ] )['max-image-preview'] );
	}

	/**
	 * Test render description omitted when empty.
	 */
	public function test_render_description_omitted_when_empty(): void {
		[ $ctx, $settings ] = $this->makeSingularContext( [ 'description' => '' ], [ 'description_template' => '' ] );
		// Force excerpt empty and no description template, stub AFTER context so it takes effect at render time.
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn( '' );

		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringNotContainsString( 'name="description"', $out );
	}

	/**
	 * Test render canonical escaped.
	 */
	public function test_render_canonical_escaped(): void {
		[ $ctx, $settings ] = $this->makeSingularContext( [ 'canonical' => 'https://example.com/my-page/' ] );
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		// Track esc_url called.
		$escUrlCalled = false;
		Functions\when( 'esc_url' )->alias(
			static function ( string $v ) use ( &$escUrlCalled ): string {
				$escUrlCalled = true;
				return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
			}
		);

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertTrue( $escUrlCalled );
		$this->assertStringContainsString( 'rel="canonical"', $out );
	}

	/**
	 * Test render og image fallback chain.
	 */
	public function test_render_og_image_fallback_chain(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'og' => [
					'image'    => '',
					'image_id' => 55,
				],
			]
		);
		// Override attachment stubs AFTER makeSingularContext so they are not overwritten.
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/og.jpg' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( [ 'https://example.com/og.jpg', 1200, 630 ] );

		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'og:image', $out );
		$this->assertStringContainsString( 'https://example.com/og.jpg', $out );
		$this->assertStringContainsString( 'og:image:width', $out );
	}

	/**
	 * Test title returns payload literal.
	 */
	public function test_title_returns_payload_literal(): void {
		[ $ctx, $settings ] = $this->makeSingularContext( [ 'title' => 'My Custom Title' ] );
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$result = $renderer->title( 'WP Default' );

		$this->assertSame( 'My Custom Title', $result );
	}

	/**
	 * Test title returns wp default when no payload.
	 */
	public function test_title_returns_wp_default_when_no_payload(): void {
		[ $ctx, $settings ] = $this->makeSingularContext( [], [ 'title_template' => '' ] );
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$result = $renderer->title( 'WP Default Title' );

		$this->assertSame( 'WP Default Title', $result );
	}

	/**
	 * Test title resolves tokens in payload.
	 */
	public function test_title_resolves_tokens_in_payload(): void {
		// Payload title contains tokens, should be resolved via TagsReplacer.
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $val ) {
				if ( 'rankkernel/tokens' === $hook && is_array( $val ) ) {
					// Return map as-is.
					return $val;
				}
				return $val;
			}
		);

		[ $ctx, $settings ] = $this->makeSingularContext( [ 'title' => '%%title%% %%sep%% %%sitename%%' ] );
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$result = $renderer->title( 'Fallback' );

		// Should resolve to the post title, then the separator, then the sitename.
		$this->assertStringContainsString( 'Post Title', (string) $result );
		$this->assertStringContainsString( 'My Site', (string) $result );
	}

	/**
	 * Test feed early return no tags.
	 */
	public function test_feed_early_return_no_tags(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 0 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'is_feed' )->justReturn( true );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Functions\when( 'get_the_title' )->justReturn( '' );
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'apply_filters' )->justReturn( [] );
		Functions\when( 'do_action' )->justReturn( null );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );
		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertSame( '', $out );
	}

	/**
	 * Test r2 action fired once with context.
	 */
	public function test_r2_action_fired_once_with_context(): void {
		[ $ctx, $settings ] = $this->makeSingularContext();
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		$fired  = 0;
		$argCtx = null;
		Functions\when( 'do_action' )->alias(
			static function ( string $hook, mixed $arg = null ) use ( &$fired, &$argCtx ): void {
				if ( 'rankkernel/head/after_tags' === $hook ) {
					++$fired;
					$argCtx = $arg;
				}
			}
		);

		ob_start();
		$renderer->render();
		ob_end_clean();

		$this->assertSame( 1, $fired );
		$this->assertSame( $ctx, $argCtx );
	}

	/**
	 * Test render escapes description.
	 */
	public function test_render_escapes_description(): void {
		[ $ctx, $settings ] = $this->makeSingularContext( [ 'description' => 'A "quoted" & tricky <desc>' ] );
		// Override sanitize already, payload description will be stored as provided via get_post_meta; render should esc_attr it.
		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( '&quot;quoted&quot;', $out );
		$this->assertStringContainsString( '&amp;', $out );
	}

	/**
	 * Test render webmaster tags.
	 */
	public function test_render_webmaster_tags(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[],
			[
				'webmaster_google' => 'google123',
				'webmaster_bing'   => 'bing123',
			]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'google-site-verification', $out );
		$this->assertStringContainsString( 'google123', $out );
		$this->assertStringContainsString( 'msvalidate.01', $out );
	}

	/**
	 * Test canonical omitted on search.
	 */
	public function test_canonical_omitted_on_search(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 0 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Functions\when( 'get_the_title' )->justReturn( '' );
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'apply_filters' )->justReturn( [] );
		Functions\when( 'get_term_field' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'date_i18n' )->justReturn( '' );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'do_action' )->justReturn( null );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );
		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringNotContainsString( 'rel="canonical"', $out );
		$this->assertStringNotContainsString( 'name="robots"', $out );

		$robots = $renderer->filterRobots( [] );

		$this->assertTrue( $robots['noindex'] );
		$this->assertArrayNotHasKey( 'nofollow', $robots );
	}

	/**
	 * Test og title resolves tokens no leak.
	 */
	public function test_og_title_resolves_tokens_no_leak(): void {
		// Payload title contains tokens, og.title empty → og:title must be resolved.
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'title' => '%%title%% %%sep%% %%sitename%%',
				'og'    => [ 'title' => '' ],
			]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringNotContainsString( '%%', $out, 'tokens must not leak into og:title' );
		$this->assertStringContainsString( 'og:title', $out );
		$this->assertStringContainsString( 'Post Title', $out );
		$this->assertStringContainsString( 'My Site', $out );
	}

	/**
	 * Test twitter title resolves tokens no leak.
	 */
	public function test_twitter_title_resolves_tokens_no_leak(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'title'   => '%%title%% %%sep%% %%sitename%%',
				'twitter' => [ 'title' => '' ],
				'og'      => [ 'title' => '' ],
			]
		);
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringNotContainsString( '%%title%%', $out );
		$this->assertStringContainsString( 'twitter:title', $out );
		$this->assertStringContainsString( 'Post Title', $out );
	}

	/**
	 * Test og image custom url no dims with featured.
	 */
	public function test_og_image_custom_url_no_dims_with_featured(): void {
		// Custom URL + featured thumbnail exists → should emit custom URL but NO width/height.
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'og' => [
					'image'    => 'https://example.com/custom.jpg',
					'image_id' => 0,
				],
			]
		);
		// Override stubs after context; featured exists but must be ignored for custom URL.
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/featured.jpg' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( [ 'https://example.com/featured.jpg', 800, 600 ] );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 999 );

		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'https://example.com/custom.jpg', $out );
		$this->assertStringNotContainsString( 'og:image:width', $out );
		$this->assertStringNotContainsString( 'og:image:height', $out );
	}

	/**
	 * Test og image image id dims match.
	 */
	public function test_og_image_image_id_dims_match(): void {
		// image_id only → dims must be from that attachment.
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'og' => [
					'image'    => '',
					'image_id' => 77,
				],
			]
		);
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/from-id.jpg' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( [ 'https://example.com/from-id.jpg', 1200, 630 ] );

		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'https://example.com/from-id.jpg', $out );
		$this->assertStringContainsString( 'og:image:width', $out );
		$this->assertStringContainsString( '1200', $out );
	}

	/**
	 * Test og image both set prefers custom no dims.
	 */
	public function test_og_image_both_set_prefers_custom_no_dims(): void {
		// Both image URL and image_id set → custom URL wins, dims omitted.
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'og' => [
					'image'    => 'https://example.com/custom2.jpg',
					'image_id' => 88,
				],
			]
		);
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/from-id.jpg' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( [ 'https://example.com/from-id.jpg', 1200, 630 ] );

		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'https://example.com/custom2.jpg', $out );
		$this->assertStringNotContainsString( 'og:image:width', $out );
	}

	/**
	 * Test author archive no home canonical.
	 */
	public function test_author_archive_no_home_canonical(): void {
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
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 5 )->byDefault();
		$query->shouldReceive( 'get_queried_object' )->andReturn( (object) [ 'ID' => 5 ] )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( null )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Functions\when( 'get_the_title' )->justReturn( '' );
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'get_author_posts_url' )->justReturn( 'https://example.com/author/bob/' );
		Functions\when( 'get_term_field' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'date_i18n' )->justReturn( '' );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ) => $v );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );
		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringNotContainsString( 'href="https://example.com/"', $out, 'homepage canonical must not appear on author archive' );
		$this->assertStringContainsString( 'https://example.com/author/bob/', $out );
		$this->assertStringContainsString( 'rel="canonical"', $out );
		$this->assertStringContainsString( 'og:url', $out );
	}

	/**
	 * Test render baidu webmaster tag.
	 */
	public function test_render_baidu_webmaster_tag(): void {
		[ $ctx, $settings ] = $this->makeSingularContext( [], [ 'webmaster_baidu' => 'baidu123' ] );
		$renderer           = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'baidu-site-verification', $out );
		$this->assertStringContainsString( 'baidu123', $out );
	}

	/**
	 * Test preview renders zero tags and title untouched.
	 */
	public function test_preview_renders_zero_tags_and_title_untouched(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 1 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Functions\when( 'get_the_title' )->justReturn( '' );
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'get_post_meta' )->justReturn( [ 'title' => 'Preview Title Payload' ] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'apply_filters' )->justReturn( [] );
		Functions\when( 'get_term_field' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'date_i18n' )->justReturn( '' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );

		$doActionFired = false;
		Functions\when( 'do_action' )->alias(
			static function ( string $hook ) use ( &$doActionFired ): void {
				if ( 'rankkernel/head/after_tags' === $hook ) {
					$doActionFired = true;
				}
			}
		);

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );
		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertSame( '', $out, 'preview must emit zero tags' );
		$this->assertFalse( $doActionFired, 'preview must not fire R2 action' );
		$this->assertSame( 'WP Default Preview', $renderer->title( 'WP Default Preview' ) );
		$this->assertSame( 'preview', $ctx->queriedType() );
	}

	/**
	 * Test filter robots leaves core untouched on feeds.
	 */
	public function test_filter_robots_leaves_feed_untouched(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 0 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		Functions\when( 'is_feed' )->justReturn( true );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );
		$renderer = new HeadRenderer( $settings, null, $ctx );

		$base = [ 'noindex' => true ];

		$this->assertSame( $base, $renderer->filterRobots( $base ) );
	}

	/**
	 * Test filter robots leaves core untouched on previews.
	 */
	public function test_filter_robots_leaves_preview_untouched(): void {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_preview' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		Functions\when( 'is_preview' )->justReturn( true );

		$settings = new SettingsStore();
		$ctx      = new Context( $query, $settings );
		$renderer = new HeadRenderer( $settings, null, $ctx );

		$base = [
			'index'  => false,
			'follow' => true,
		];

		$this->assertSame( $base, $renderer->filterRobots( $base ) );
	}

	/**
	 * Test a decoded JSON meta row reaches the head output.
	 */
	public function test_render_surfaces_decoded_json_meta_row(): void {
		[ $ctx, $settings ] = $this->makeSingularContext();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		Functions\when( 'get_post_meta' )->justReturn( (string) json_encode( [ 'title' => 'Decoded JSON Headline' ] ) );

		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'Decoded JSON Headline', $out );
	}

	/**
	 * Test a decoded serialized meta row reaches the head output.
	 */
	public function test_render_surfaces_decoded_serialized_meta_row(): void {
		[ $ctx, $settings ] = $this->makeSingularContext();

		Functions\when( 'get_post_meta' )->justReturn( serialize( [ 'description' => 'Serialized Description' ] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.

		$renderer = new HeadRenderer( $settings, null, $ctx );

		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'Serialized Description', $out );
	}

	/**
	 * Test boot removes core rel canonical so only one canonical is emitted.
	 */
	public function test_boot_removes_core_rel_canonical(): void {
		[ $ctx, $settings ] = $this->makeSingularContext();

		Functions\expect( 'remove_action' )->once()->with( 'wp_head', 'rel_canonical' )->andReturn( true );
		Functions\expect( 'add_action' )->andReturn( true );
		Functions\expect( 'add_filter' )->andReturn( true );

		$renderer = new HeadRenderer( $settings, null, $ctx );
		$renderer->boot();

		$this->assertTrue( true );
	}

	/**
	 * Test getResolvedTitle memoizes title resolution across title() and render() passes.
	 */
	public function test_get_resolved_title_memoization(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[
				'title'       => '%%title%% %%sep%% %%sitename%%',
				'description' => 'Fixed Description',
			],
			[
				'description_template' => '',
			]
		);

		$filterCalls = 0;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $val ) use ( &$filterCalls ) {
				if ( 'rankkernel/tokens' === $hook ) {
					++$filterCalls;
				}

				return $val;
			}
		);

		$replacer = new TagsReplacer();
		$renderer = new HeadRenderer( $settings, $replacer, $ctx );

		// 1) Call title() filter pass.
		$docTitle = $renderer->title( 'Default' );
		$this->assertStringContainsString( 'Post Title', (string) $docTitle );
		$this->assertSame( 1, $filterCalls, 'rankkernel/tokens filter should fire once during title()' );

		// Explicitly clear TagsReplacer memo to isolate HeadRenderer's $resolvedTitleMemo layer.
		$replacer->clearMemo();

		// 2) Call render() which resolves og:title and twitter:title via getResolvedTitle().
		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'og:title', $out );
		$this->assertStringContainsString( 'twitter:title', $out );
		$this->assertStringContainsString( 'Post Title', $out );

		// The filter should STILL NOT fire again during render() because HeadRenderer memoizes the title.
		$this->assertSame( 1, $filterCalls, 'rankkernel/tokens filter should not fire during render even if TagsReplacer memo is cleared' );
	}

	/**
	 * Test empty payload and empty template fallback isolation between title() and getResolvedTitle().
	 */
	public function test_empty_payload_and_template_fallback_isolation(): void {
		[ $ctx, $settings ] = $this->makeSingularContext(
			[],
			[
				'title_template'       => '',
				'description_template' => '',
			]
		);

		$renderer = new HeadRenderer( $settings, null, $ctx );

		// title() returns the WP filter default without poisoning getResolvedTitle().
		$filterDefault = $renderer->title( 'Filter WP Default' );
		$this->assertSame( 'Filter WP Default', $filterDefault );

		// render() uses Context title for og:title and twitter:title tags.
		ob_start();
		$renderer->render();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'og:title', $out );
		$this->assertStringContainsString( 'Post Title', $out );
	}
}
