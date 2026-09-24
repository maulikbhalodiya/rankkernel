<?php
/**
 * SitemapsModule tests, takeover and ping.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\SitemapCache;
use RankKernel\Modules\Sitemaps\SitemapsModule;
use WP_Post;

/**
 * Sitemaps Module Test.
 */
final class SitemapsModuleTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
			define( 'RANKKERNEL_VERSION', '0.1.0' );
		}

		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'add_rewrite_rule' )->justReturn( true );
		Functions\when( 'remove_all_actions' )->justReturn( true );
		Functions\when( 'wp_cache_get' )->justReturn( null );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'mysql2date' )->alias( static fn ( string $fmt, string $date, bool $t = true ): string => gmdate( $fmt, strtotime( $date ) ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress mysql2date signature.
		Functions\when( 'current_time' )->alias( static fn ( string $type, bool $gmt = false ): string => '2026-01-01 00:00:00' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress current_time signature.
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [ 'category' => 'category' ] );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias( static fn ( string $h, mixed $v ): mixed => $v );
		Functions\when( '__return_false' )->alias( static fn (): bool => false );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		// The memo is a process-wide static, so it outlives this test unless cleared.
		SitemapCache::resetValidatorCache();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test boot registers takeover filter and notice.
	 */
	public function test_boot_registers_takeover_filter_and_notice(): void {
		$addedFilters = [];
		$addedActions = [];

		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, mixed $callback, int $prio = 10, int $args = 1 ) use ( &$addedFilters ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_filter signature.
				$addedFilters[] = [ $hook, $callback ];
				return true;
			}
		);

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, mixed $callback, int $prio = 10, int $args = 1 ) use ( &$addedActions ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_action signature.
				$addedActions[] = [ $hook, $callback ];
				return true;
			}
		);

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'sitemaps' ];
				}
				return $fallback;
			}
		);

		$module = new SitemapsModule();
		$module->register();
		$module->boot();

		$found = false;
		foreach ( $addedFilters as $entry ) {
			if ( 'wp_sitemaps_enabled' === $entry[0] && '__return_false' === $entry[1] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'wp_sitemaps_enabled filter must be registered with __return_false' );

		$noticeFound = false;
		foreach ( $addedActions as $entry ) {
			if ( 'admin_notices' === $entry[0] ) {
				$noticeFound = true;
				break;
			}
		}

		$this->assertTrue( $noticeFound, 'Admin notice for takeover must be queued' );

		// Verify notice output contains expected text on a RankKernel screen.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel' ] );

		ob_start();
		$module->renderTakeoverNotice();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'Core WordPress sitemaps are disabled in favor of RankKernel sitemaps.', $out );
	}

	/**
	 * Test takeover notice is suppressed on non-RankKernel screens.
	 *
	 * The near miss ids contain the "rankkernel" substring but are not owned
	 * by RankKernel, so an exact comparison must reject them. A null or
	 * non-object screen also fails closed.
	 *
	 * @return void
	 */
	public function test_render_takeover_notice_suppressed_on_other_screens(): void {
		$module = new SitemapsModule();

		$otherScreens = [
			'dashboard',
			'toplevel_page_rankkernel-clone',
			'toplevel_page_my-rankkernel-tool',
			'rankkernel_page_rankkernel-evil',
		];

		foreach ( $otherScreens as $screenId ) {
			Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => $screenId ] );

			ob_start();
			$module->renderTakeoverNotice();
			$out = ob_get_clean();

			$this->assertSame( '', $out, sprintf( 'Notice must be suppressed on the "%s" screen', $screenId ) );
		}

		Functions\when( 'get_current_screen' )->justReturn( null );
		ob_start();
		$module->renderTakeoverNotice();
		$outNull = ob_get_clean();

		$this->assertSame( '', $outNull, 'A null screen must fail closed' );

		Functions\when( 'get_current_screen' )->justReturn( 'not-a-screen' );
		ob_start();
		$module->renderTakeoverNotice();
		$outNonObject = ob_get_clean();

		$this->assertSame( '', $outNonObject, 'A non-object screen must fail closed' );
	}

	/**
	 * Test takeover notice renders on every RankKernel screen.
	 *
	 * @return void
	 */
	public function test_render_takeover_notice_renders_on_rankkernel_screens(): void {
		$module = new SitemapsModule();

		$rankKernelScreens = [
			'toplevel_page_rankkernel',
			'rankkernel_page_rankkernel-sitemap',
			'rankkernel_page_rankkernel-general',
			'rankkernel_page_rankkernel-schema',
			'rankkernel_page_rankkernel-redirects',
			'rankkernel_page_rankkernel-404',
		];

		foreach ( $rankKernelScreens as $screenId ) {
			Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => $screenId ] );

			ob_start();
			$module->renderTakeoverNotice();
			$out = ob_get_clean();

			$this->assertStringContainsString( 'Core WordPress sitemaps are disabled in favor of RankKernel sitemaps.', $out, sprintf( 'Notice must render on the "%s" screen', $screenId ) );
		}
	}

	/**
	 * Test takeover notice fails closed when the screen API is absent.
	 *
	 * A separate process is required because stubbing get_current_screen
	 * anywhere else in the suite defines it for the whole process, so only a
	 * fresh process can prove the function_exists guard.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_render_takeover_notice_fails_closed_without_screen_api(): void {
		$this->assertFalse( function_exists( 'get_current_screen' ), 'Precondition: the screen API must be absent' );

		$module = new SitemapsModule();
		ob_start();
		$module->renderTakeoverNotice();
		$out = ob_get_clean();

		$this->assertSame( '', $out, 'A missing screen API must fail closed' );
	}

	/**
	 * Test boot flushes rewrite rules once per version.
	 */
	public function test_boot_flushes_rewrite_rules_once_per_version(): void {
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_rewrite_rule' )->justReturn( true );

		$flushes = 0;
		$stored  = '';

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$stored ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'sitemaps' ];
				}
				if ( 'rankkernel_rewrite_rules_version' === $key ) {
					return '' !== $stored ? $stored : $fallback;
				}
				return $fallback;
			}
		);

		Functions\when( 'flush_rewrite_rules' )->alias(
			static function ( bool $soft = true ) use ( &$flushes ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress flush_rewrite_rules signature.
				$flushes++;
			}
		);

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value, mixed $autoload = null ) use ( &$stored ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				if ( 'rankkernel_rewrite_rules_version' === $key ) {
					$stored = (string) $value;
				}
				return true;
			}
		);

		$module = new SitemapsModule();
		$module->boot();

		$this->assertSame( 1, $flushes, 'First boot without a stored version must flush once' );
		$this->assertSame( RANKKERNEL_VERSION, $stored );

		$second = new SitemapsModule();
		$second->boot();

		$this->assertSame( 1, $flushes, 'Second boot with a matching version must not flush again' );
	}

	/**
	 * Test xsl uses namespaced xpaths.
	 */
	public function test_xsl_uses_namespaced_xpaths(): void {
		// The generated XML declares the sitemap namespace as its default
		// namespace, so unprefixed XPaths match nothing and browsers render
		// an empty table. This regression guards the prefix pairing.
		$xslPath = dirname( __DIR__, 2 ) . '/src/Modules/Sitemaps/sitemap.xsl';
		$xsl     = (string) file_get_contents( $xslPath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file, never a remote URL.

		$this->assertStringContainsString( 'xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9"', $xsl );
		$this->assertStringContainsString( 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $xsl );
		$this->assertStringContainsString( 'sm:sitemapindex/sm:sitemap', $xsl );
		$this->assertStringContainsString( 'sm:urlset/sm:url', $xsl );
		$this->assertStringNotContainsString( 'select="sitemapindex/sitemap"', $xsl );
		$this->assertStringNotContainsString( 'select="urlset/url"', $xsl );
	}

	/**
	 * Test xsl shows counts backlink and image counts.
	 */
	public function test_xsl_shows_counts_backlink_and_image_counts(): void {
		// Count lines, the back link to the index, and per URL image counts
		// (never raw image URLs) keep the human view readable like the
		// leading SEO plugins do.
		$xslPath = dirname( __DIR__, 2 ) . '/src/Modules/Sitemaps/sitemap.xsl';
		$xsl     = (string) file_get_contents( $xslPath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file, never a remote URL.

		$this->assertStringContainsString( 'This XML Sitemap Index file contains', $xsl );
		$this->assertStringContainsString( 'This XML Sitemap contains', $xsl );
		$this->assertStringContainsString( 'Sitemap Index</a>', $xsl );
		$this->assertStringContainsString( 'class="desc-block"', $xsl );
		$this->assertStringContainsString( 'class="table-block"', $xsl );
		$this->assertStringContainsString( 'count(image:image)', $xsl );
		$this->assertStringContainsString( 'count(sm:urlset/sm:url)', $xsl );
		$this->assertStringContainsString( 'This XML Sitemap Index is generated by RankKernel.', $xsl );
		$this->assertStringContainsString( 'This XML Sitemap is generated by RankKernel.', $xsl );
	}

	/**
	 * Test boot bumps sitemap validators on code upgrade.
	 */
	public function test_boot_bumps_sitemap_validators_on_code_upgrade(): void {
		// Cached XML from older plugin code must not survive an upgrade.
		$stored = [];

		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_rewrite_rule' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$stored ): mixed {
				return $stored[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$stored ): bool {
				$stored[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_rand' )->justReturn( 12345 );

		$module = new SitemapsModule();
		$module->register();
		$module->boot();

		$this->assertArrayHasKey( SitemapCache::VALIDATOR_GLOBAL, $stored );
		$this->assertArrayHasKey( 'rankkernel_sitemap_code_version', $stored );
		$this->assertSame( RANKKERNEL_VERSION, $stored['rankkernel_sitemap_code_version'] );

		// Second boot on the same version bumps nothing.
		$storedBefore = $stored;
		$second       = new SitemapsModule();
		$second->boot();

		$this->assertSame( $storedBefore, $stored );
	}

	/**
	 * Test the version bump retires a validator the request already memoized.
	 *
	 * The bump writes the validator option directly, bypassing SitemapCache, so
	 * without a reset the memo keeps answering with the pre-bump value for the
	 * rest of the request and cached XML from the old code survives the upgrade.
	 */
	public function test_boot_version_bump_retires_the_memoized_validator(): void {
		SitemapCache::resetValidatorCache();

		$stored      = [];
		$globalReads = 0;

		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$stored, &$globalReads ): mixed {
				if ( SitemapCache::VALIDATOR_GLOBAL === $key ) {
					++$globalReads;
				}

				return $stored[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$stored ): bool {
				$stored[ $key ] = $value;

				return true;
			}
		);

		$cache = new SitemapCache();

		$cache->store( 'post', 1, '<xml>1</xml>' );
		$afterFirstRead = $globalReads;
		$this->assertGreaterThan( 0, $afterFirstRead, 'The first read must reach storage' );

		$cache->store( 'post', 2, '<xml>2</xml>' );
		$this->assertSame( $afterFirstRead, $globalReads, 'A repeated read must be served by the memo' );

		$module = new SitemapsModule();
		$module->register();
		$module->boot();

		$this->assertArrayHasKey( SitemapCache::VALIDATOR_GLOBAL, $stored, 'The upgrade must bump the validator' );

		$cache->store( 'post', 3, '<xml>3</xml>' );
		$this->assertGreaterThan( $afterFirstRead, $globalReads, 'The bump must retire the memo so the next read is fresh' );
	}

	/**
	 * Test ping fires only on publish.
	 */
	public function test_ping_fires_only_on_publish(): void {
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );

		$module = new SitemapsModule();
		$module->boot();

		$fired = [];
		Functions\when( 'do_action' )->alias(
			static function ( string $hook, mixed ...$args ) use ( &$fired ): void {
				if ( 'rankkernel/sitemap/ping' === $hook ) {
					$fired[] = $args[0] ?? null;
				}
			}
		);

		$post     = Mockery::mock( WP_Post::class );
		$post->ID = 42;

		$module->onTransitionPostStatus( 'publish', 'draft', $post );

		$this->assertCount( 1, $fired );
		$this->assertSame( 42, $fired[0] );

		$fired = [];
		$module->onTransitionPostStatus( 'publish', 'publish', $post );
		$this->assertCount( 0, $fired, 'Publish to publish must not fire' );

		$module->onTransitionPostStatus( 'draft', 'publish', $post );
		$this->assertCount( 0, $fired, 'Publish to draft must not fire' );

		$module->onTransitionPostStatus( 'draft', 'draft', $post );
		$this->assertCount( 0, $fired );
	}

	/**
	 * Test module id and priority.
	 */
	public function test_module_id_and_priority(): void {
		$module = new SitemapsModule();

		$this->assertSame( 'sitemaps', $module->getId() );
		$this->assertSame( 20, $module->getPriority() );
		$this->assertSame( [], $module->dependsOn() );
		$this->assertSame( 'XML Sitemaps', $module->getName() );
	}
}
