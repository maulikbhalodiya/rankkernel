<?php
/**
 * Dashboard page tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\DashboardPage;
use RankKernel\Modules\ModuleRegistry;

/*
 * The dashboard view prints the plugin version from RANKKERNEL_VERSION, which
 * rankkernel.php defines as the single version source. The bootstrap does not
 * load the plugin file, so the constant is declared here the same way the
 * metadata and schema metabox tests already do it.
 */
if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
	define( 'RANKKERNEL_VERSION', '0.1.0-test' );
}

/**
 * Dashboard Page Test.
 */
final class DashboardPageTest extends TestCase {
	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Redirected URLs.
	 *
	 * @var string[]
	 */
	private array $redirects = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		/*
		 * RANKKERNEL_VERSION is declared once at file scope above, so the value
		 * is fixed for the whole run. Do not redeclare it here with a different
		 * value, because the file scope guard always wins and the two values
		 * would silently disagree.
		 */

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', __FILE__ );
		}

		$this->options             = [ 'rankkernel_modules' => [ 'metadata', 'sitemaps' ] ];
		$this->redirects           = [];
		$_POST                     = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url ): void {
				$this->redirects[] = $url;
			}
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/wp-admin/' . $path );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => trim( $value ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( 'wp_nonce_field' )->alias(
			static function ( string $action = '' ): string {
				echo 'nonce-field:' . $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stub mirrors wp_nonce_field echoing the field.
				return 'nonce-field:' . $action;
			}
		);
		Functions\when( 'wp_nonce_url' )->alias( static fn ( string $url, string $action = '' ): string => $url . '&_wpnonce=nonce:' . $action );
		Functions\when( 'add_query_arg' )->alias(
			static function ( mixed $key = [], mixed $value = '', string $url = '' ): string {
				$args = is_array( $key ) ? $key : [ (string) $key => $value ];
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'esc_html__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( '_n' )->alias(
			static function ( string $single, string $plural, int $number, string $domain = 'default' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress _n signature.
				return 1 === $number ? $single : $plural;
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		$_POST = [];
		$_GET  = [];
		unset( $_SERVER['REQUEST_METHOD'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test cards include every registry module with its state.
	 */
	public function test_cards_list_registry_modules(): void {
		$cards = ( new DashboardPage() )->cards();

		$this->assertCount( count( ModuleRegistry::all() ), $cards );

		$ids = array_column( $cards, 'id' );
		$this->assertContains( 'robots', $ids );
		$this->assertContains( 'metadata', $ids );

		$byId = [];
		foreach ( $cards as $card ) {
			$byId[ $card['id'] ] = $card;
		}

		$this->assertTrue( $byId['metadata']['enabled'] );
		$this->assertFalse( $byId['robots']['enabled'] );
		$this->assertStringContainsString( 'rankkernel-general', $byId['robots']['settingsUrl'] );
		$this->assertSame( '', $byId['analysis']['settingsUrl'] );
		$this->assertTrue( $byId['ai']['planned'] );
		$this->assertFalse( $byId['ai']['enabled'] );
		$this->assertSame( '', $byId['ai']['settingsUrl'] );
		$this->assertStringContainsString( 'Planned.', $byId['ai']['description'] );
		$this->assertFalse( $byId['metadata']['planned'] );
	}

	/**
	 * Test toggling on enables a module.
	 */
	public function test_toggle_enables_module(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'robots';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertContains( 'robots', $this->options['rankkernel_modules'] );
		$this->assertNotEmpty( $this->redirects );
	}

	/**
	 * Test toggling off disables a module.
	 */
	public function test_toggle_disables_module(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'metadata';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertNotContains( 'metadata', $this->options['rankkernel_modules'] );
		$this->assertContains( 'sitemaps', $this->options['rankkernel_modules'] );
	}

	/**
	 * Test toggling on with an assoc shaped stored option keeps siblings.
	 */
	public function test_toggle_preserves_siblings_from_assoc_shaped_option(): void {
		$this->options['rankkernel_modules'] = [
			'metadata' => true,
			'sitemaps' => true,
		];

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'robots';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertContains( 'robots', $this->options['rankkernel_modules'] );
		$this->assertContains( 'metadata', $this->options['rankkernel_modules'] );
		$this->assertContains( 'sitemaps', $this->options['rankkernel_modules'] );
	}

	/**
	 * Test toggling off with an assoc shaped stored option keeps siblings.
	 */
	public function test_toggle_off_preserves_siblings_from_assoc_shaped_option(): void {
		$this->options['rankkernel_modules'] = [
			'metadata' => true,
			'sitemaps' => true,
		];

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'metadata';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertNotContains( 'metadata', $this->options['rankkernel_modules'] );
		$this->assertContains( 'sitemaps', $this->options['rankkernel_modules'] );
	}

	/**
	 * Test toggling the sitemaps module invalidates the sitemap cache.
	 */
	public function test_toggle_sitemaps_invalidates_sitemap_cache(): void {
		$written = [];

		Functions\when( 'update_option' )->alias(
			static function ( string $key ) use ( &$written ): bool {
				$written[] = $key;

				return true;
			}
		);

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'sitemaps';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertContains( 'rankkernel_sitemap_validator_global', $written );
	}

	/**
	 * Test toggling an unrelated module leaves the sitemap cache alone.
	 */
	public function test_toggle_other_module_leaves_sitemap_cache_alone(): void {
		$written = [];

		Functions\when( 'update_option' )->alias(
			static function ( string $key ) use ( &$written ): bool {
				$written[] = $key;

				return true;
			}
		);

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'robots';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertNotContains( 'rankkernel_sitemap_validator_global', $written );
	}

	/**
	 * Test an unknown module id is ignored.
	 */
	public function test_toggle_ignores_unknown_module(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'evil-id';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertSame( [ 'metadata', 'sitemaps' ], $this->options['rankkernel_modules'] );
	}

	/**
	 * Test a planned module id is never toggled by the dashboard save.
	 */
	public function test_toggle_ignores_planned_module(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'gutenberg';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertSame( [ 'metadata', 'sitemaps' ], $this->options['rankkernel_modules'] );
	}

	/**
	 * Test the toggle requires the capability.
	 */
	public function test_toggle_requires_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'robots';

		$this->expectException( \RuntimeException::class );

		( new DashboardPage() )->maybeHandleSave();
	}

	/**
	 * Test asset enqueue is gated to the dashboard screen.
	 */
	public function test_enqueue_is_gated(): void {
		$registered   = [];
		$enqueued     = [];
		$translatedJS = [];

		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps = [], string $ver = '' ) use ( &$registered ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_register_style signature.
				$registered[ $handle ] = $deps;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_set_script_translations' )->alias(
			static function ( string $handle, string $domain = 'default', string $path = '' ) use ( &$translatedJS ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_set_script_translations signature.
				$translatedJS[ $handle ] = $domain;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);
		Functions\when( 'rest_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/wp-json/' . $path );
		Functions\when( 'wp_create_nonce' )->alias( static fn ( string $action = '' ): string => 'nonce:' . $action ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_create_nonce signature.
		$localized = [];
		Functions\when( 'wp_localize_script' )->alias(
			static function ( string $handle, string $objectName, array $data ) use ( &$localized ): void {
				$localized[ $objectName ] = $data;
			}
		);

		$page = new DashboardPage();

		$page->enqueueAssets( 'other_page' );
		$this->assertSame( [], $enqueued );

		$page->enqueueAssets( DashboardPage::HOOK_SUFFIX );
		$this->assertSame( [ 'rankkernel-admin', 'rankkernel-dashboard-admin', 'rankkernel-dashboard-admin' ], $enqueued );
		// The sheet styles rk-ui components, so the UI layer must print first.
		$this->assertSame( [ 'rankkernel-admin', 'rankkernel-ui' ], $registered['rankkernel-dashboard-admin'] );
		// The toggle script posts to the REST module endpoint with a wp_rest nonce.
		$this->assertSame( 'https://example.com/wp-json/rankkernel/v1/modules', $localized['rankkernelDashboard']['modulesUrl'] );
		$this->assertSame( 'nonce:wp_rest', $localized['rankkernelDashboard']['restNonce'] );
		// Script translations are registered with the rankkernel text domain.
		$this->assertSame( 'rankkernel', $translatedJS['rankkernel-dashboard-admin'] ?? '' );
	}

	/**
	 * Test render outputs accessible aria-labels for module action links and buttons.
	 */
	public function test_render_outputs_accessible_aria_labels(): void {
		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'aria-label="Settings for Metadata Engine"', $output );
		$this->assertStringContainsString( 'aria-label="Turn off Metadata Engine module"', $output );
		$this->assertStringContainsString( 'aria-label="Turn on Robots.txt &amp; .htaccess module"', $output );
	}

	/**
	 * Test the active module tab exposes aria-current like the sibling tab sets.
	 */
	public function test_render_marks_active_module_tab_current(): void {
		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<a class="rk-ui-tab is-current" href="#rk-modules-active" aria-current="page">', $output );
		$this->assertStringNotContainsString( 'href="#rk-modules-planned" aria-current', $output );
	}

	/**
	 * Test the export shortcut links the verified redirects download when enabled.
	 */
	public function test_quick_action_export_links_verified_download_when_redirects_enabled(): void {
		$this->options['rankkernel_modules'] = [ 'metadata', 'sitemaps', 'redirects' ];

		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'page=rankkernel-redirects', $output );
		$this->assertStringContainsString( 'rk_action=export', $output );
		$this->assertStringContainsString( '_wpnonce=nonce:rankkernel_redirect_export', $output );
	}

	/**
	 * Test the clear shortcut posts the verified marker to the 404 screen when enabled.
	 */
	public function test_quick_action_clear_posts_verified_marker_when_404_enabled(): void {
		$this->options['rankkernel_modules'] = [ 'metadata', 'sitemaps', '404' ];

		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<form class="rk-dashboard-tool-form" method="post" action="https://example.com/wp-admin/admin.php?page=rankkernel-404">', $output );
		$this->assertStringContainsString( 'nonce-field:rankkernel_404_clear', $output );
		$this->assertStringContainsString( 'name="rankkernel_404_clear"', $output );
	}

	/**
	 * Test shortcuts stay disabled while their module is switched off.
	 */
	public function test_quick_actions_stay_disabled_while_module_off(): void {
		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		// Sitemaps is enabled in the fixture, so its shortcut links out.
		$this->assertStringContainsString( 'rankkernel_sitemap=index', $output );
		$this->assertStringContainsString( 'View XML Sitemap', $output );
		// Redirects, robots and 404 are off, so no verified destination renders.
		$this->assertStringNotContainsString( 'rk_action=export', $output );
		$this->assertStringNotContainsString( 'rankkernel_404_clear', $output );
		$this->assertStringNotContainsString( '/robots.txt', $output );
		// The retired flush-cache shortcut is gone.
		$this->assertStringNotContainsString( 'Flush Sitemap Cache', $output );
	}

	/**
	 * Test the brand tile renders the Trends mark in the tile foreground
	 * color, not a hardcoded gray that dies on the blue tile.
	 */
	public function test_render_brand_tile_uses_trends_logo(): void {
		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<span class="rk-dashboard-logo"', $output );
		$this->assertStringContainsString( 'M5.5 4.75C5.5 4.33579 5.16421 4 4.75 4', $output );
		$this->assertStringContainsString( 'fill="currentColor"></path></svg></span>', $output );
		$this->assertStringNotContainsString( '605E5C', $output );
		$this->assertStringNotContainsString( 'M1.5 17.5v-3h4.5v-3h4.5V8h4v9.5z', $output );
		$this->assertStringNotContainsString( '<span class="rk-dashboard-logo" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search</span></span>', $output );
	}

	/**
	 * Test the module search renders enabled with an empty state, and the
	 * stat card carries no same-page View Modules link.
	 */
	public function test_render_module_search_is_usable_and_stat_has_no_redundant_link(): void {
		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-modules-search"', $output );
		$this->assertStringNotContainsString( 'Module search is not available', $output );
		$this->assertStringNotContainsString( 'rk-modules-search-note', $output );
		$this->assertStringContainsString( 'id="rk-modules-empty"', $output );
		$this->assertStringNotContainsString( 'rk-dashboard-stat-link', $output );
		$this->assertStringNotContainsString( 'View Modules', $output );
	}
}
