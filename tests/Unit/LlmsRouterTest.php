<?php
/**
 * Llms.txt route tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsGenerator;
use RankKernel\Modules\Robots\LlmsRouter;
use RankKernel\Modules\Robots\LlmsSettings;

/**
 * Llms Router Test.
 */
final class LlmsRouterTest extends TestCase {
	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Transients.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->options    = [];
		$this->transients = [];

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value, int $ttl = 0 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress set_transient signature.
				$this->transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Example Site' );
		Functions\when( 'wp_rand' )->justReturn( 12345 );

		// The router falls back to the generated sections when no curated
		// content exists. These stubs keep this test deterministic about that
		// fallback and keep it independent of test execution order, because a
		// function defined by any earlier test would otherwise satisfy
		// function_exists and then raise for being unstubbed.
		Functions\when( 'get_post_types' )->justReturn( [] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a router.
	 *
	 * @return LlmsRouter The result.
	 */
	private function router(): LlmsRouter {
		return new LlmsRouter( new LlmsGenerator(), new LlmsSettings() );
	}

	/**
	 * Test register wires the route and hooks.
	 */
	public function test_register_wires_route_and_hooks(): void {
		$rewrites = [];
		$filters  = [];
		$actions  = [];

		Functions\when( 'add_rewrite_rule' )->alias(
			static function ( string $regex, string $query, string $after ) use ( &$rewrites ): void {
				$rewrites[] = [ $regex, $query, $after ];
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$filters ): bool {
				$filters[] = $hook;

				return true;
			}
		);
		Functions\when( 'add_action' )->alias(
			static function ( string $hook ) use ( &$actions ): bool {
				$actions[] = $hook;

				return true;
			}
		);

		$this->router()->register();

		$this->assertSame( 'index.php?rankkernel_llms=1', $rewrites[0][1] );
		$this->assertContains( 'query_vars', $filters );
		$this->assertContains( 'pre_get_posts', $actions );
	}

	/**
	 * Test add query vars appends the llms var.
	 */
	public function test_add_query_vars_appends(): void {
		$this->assertContains( LlmsRouter::QUERY_VAR, $this->router()->addQueryVars( [ 'p' ] ) );
	}

	/**
	 * Test content renders and caches by validator.
	 */
	public function test_content_renders_and_caches(): void {
		$router = $this->router();

		$first = $router->content();

		$this->assertStringContainsString( '# Example Site', $first );
		$this->assertIsArray( $this->transients['rankkernel_llms_md'] ?? null );

		$this->assertSame( $first, $router->content() );
	}

	/**
	 * Test the markdown response headers include nosniff.
	 */
	public function test_response_headers_include_nosniff(): void {
		$headers = LlmsRouter::responseHeaders();

		$this->assertContains( 'Content-Type: text/markdown; charset=UTF-8', $headers );
		$this->assertContains( 'X-Robots-Tag: noindex, follow', $headers );
		$this->assertContains( 'X-Content-Type-Options: nosniff', $headers );
	}

	/**
	 * Test invalidate bumps the validator option.
	 */
	public function test_invalidate_bumps_validator(): void {
		LlmsRouter::invalidate();

		$this->assertArrayHasKey( LlmsRouter::VALIDATOR_OPTION, $this->options );
	}

	/**
	 * With no curated content the router falls back to the generated sections.
	 *
	 * FUNC-01. The shipped document was a bare H1, 13 bytes, while the site
	 * had published content, so a site serving llms.txt told crawlers it had
	 * none. This proves the fallback is wired at the router, not just built by
	 * the generator.
	 *
	 * @return void
	 */
	public function test_router_falls_back_to_generated_sections(): void {
		$post = (object) [
			'ID'         => 7,
			'post_title' => 'A published post',
		];

		Functions\when( 'get_post_types' )->justReturn( [ 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_posts' )->justReturn( [ $post ] );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/?p=7' );
		Functions\when( 'get_the_excerpt' )->justReturn( 'What the post is about.' );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'get_post_type_object' )->alias(
			static fn (): object => (object) [ 'labels' => (object) [ 'name' => 'Posts' ] ]
		);

		$markdown = $this->router()->content();

		$this->assertStringContainsString( '## Posts', $markdown );
		$this->assertStringContainsString( '- [A published post](https://example.com/?p=7): What the post is about.', $markdown );
		$this->assertGreaterThan( 13, strlen( $markdown ), 'the document must carry more than a bare H1' );
	}

	/**
	 * A curated document is never diluted by generated sections.
	 *
	 * FUNC-01. The operator writing their own sections must keep full control,
	 * so the fallback only fires when the curated field is empty.
	 *
	 * @return void
	 */
	public function test_curated_content_wins_over_generated_sections(): void {
		$this->options[ LlmsSettings::OPTION ] = [
			'enabled' => true,
			'content' => "## Hand written\n\n- [About](https://example.com/about)\n",
		];

		Functions\when( 'get_post_types' )->justReturn( [ 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_posts' )->justReturn( [ (object) [ 'ID' => 7 ] ] );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/?p=7' );
		Functions\when( 'get_the_excerpt' )->justReturn( 'Generated excerpt.' );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'get_post_type_object' )->alias(
			static fn (): object => (object) [ 'labels' => (object) [ 'name' => 'Posts' ] ]
		);

		$markdown = $this->router()->content();

		$this->assertStringContainsString( '## Hand written', $markdown );
		$this->assertStringNotContainsString( '## Posts', $markdown, 'a curated document must not gain generated sections' );
		$this->assertStringNotContainsString( 'Generated', $markdown );
	}
}
