<?php
/**
 * AdminMenu tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\AdminMenu;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Settings\SettingsStore;

/**
 * Admin Menu Test.
 */
final class AdminMenuTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Stubbed option store, consulted by the get_option alias.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Recorded wp_safe_redirect targets.
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

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v, string $d = '' ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		$this->options = [];

		$this->redirects = [];
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url ): void {
				$this->redirects[] = $url;
			}
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-admin/' . ltrim( $p, '/' ) );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test add action links returns settings first.
	 */
	public function test_add_action_links_returns_settings_first(): void {
		$store = new SettingsStore();
		$map   = new ModuleEnableMap();
		$menu  = new AdminMenu( $store, $map );

		$links  = [ '<a href="#">Deactivate</a>' ];
		$result = $menu->addActionLinks( $links );

		$this->assertCount( 2, $result );
		$this->assertStringContainsString( 'admin.php?page=rankkernel', $result[0] );
		$this->assertStringContainsString( 'Settings', $result[0] );
		// Escaped URL, no bare unescaped ampersand issues here (simple URL).
		$this->assertStringStartsWith( '<a href="https://example.com/wp-admin/admin.php?page=rankkernel">', $result[0] );
		// Original link stays second.
		$this->assertSame( $links[0], $result[1] );
	}

	/**
	 * Test add action links uses esc url.
	 */
	public function test_add_action_links_uses_esc_url(): void {
		$store = new SettingsStore();
		$map   = new ModuleEnableMap();
		$menu  = new AdminMenu( $store, $map );

		$called = false;
		Functions\when( 'esc_url' )->alias(
			static function ( string $v ) use ( &$called ): string {
				$called = true;
				return 'ESCAPED:' . $v;
			}
		);

		$result = $menu->addActionLinks( [] );
		$this->assertTrue( $called, 'esc_url must be used' );
		$this->assertStringContainsString( 'ESCAPED:', $result[0] );
	}

	/**
	 * Test add menu page registered with exact args.
	 */
	public function test_add_menu_page_registered_with_exact_args(): void {
		$this->options['rankkernel_modules'] = [ 'sitemaps' => true ];

		$store = new SettingsStore();
		$map   = new ModuleEnableMap();
		$menu  = new AdminMenu( $store, $map );

		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'RankKernel',
				'RankKernel',
				'manage_options',
				'rankkernel',
				\Mockery::type( 'callable' ),
				'dashicons-search',
				80
			)
			->andReturn( 'toplevel_page_rankkernel' );

		Functions\expect( 'add_action' )
			->once()
			->with( 'load-toplevel_page_rankkernel', \Mockery::type( 'callable' ) )
			->andReturn( true );

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'rankkernel',
				'Dashboard',
				'Dashboard',
				'manage_options',
				'rankkernel',
				\Mockery::type( 'callable' )
			)
			->andReturn( 'toplevel_page_rankkernel' );

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'rankkernel',
				'Sitemap Settings',
				'Sitemap',
				'manage_options',
				'rankkernel-sitemap',
				\Mockery::type( 'callable' )
			)
			->andReturn( 'rankkernel_page_rankkernel-sitemap' );

		Functions\expect( 'add_action' )
			->once()
			->with( 'load-rankkernel_page_rankkernel-sitemap', \Mockery::type( 'callable' ) )
			->andReturn( true );

		$menu->addMenuPage();
	}

	/**
	 * Test register hooks.
	 */
	public function test_register_hooks(): void {
		$store = new SettingsStore();
		$map   = new ModuleEnableMap();
		$menu  = new AdminMenu( $store, $map );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'plugin_action_links_' . plugin_basename( RANKKERNEL_FILE ), \Mockery::type( 'callable' ) )
			->andReturn( true );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', \Mockery::type( 'callable' ) )
			->andReturn( true );

		$menu->register();
	}

	/**
	 * Screen detection must accept every RankKernel admin screen.
	 *
	 * The regression this guards is real: the shared design layers loaded on
	 * one screen out of seven, because each page owned its own enqueue and
	 * most of them forgot. See issue #127.
	 *
	 * @dataProvider provide_rankkernel_screens
	 *
	 * @param string $hookSuffix Admin page hook suffix.
	 */
	public function test_is_rank_kernel_screen_matches_our_hooks( string $hookSuffix ): void {
		$this->assertTrue( AdminMenu::isRankKernelScreen( $hookSuffix ) );
	}

	/**
	 * Screen detection must reject screens that belong to WordPress.
	 *
	 * Metabox and column screens live on these hooks and enqueue their own
	 * assets, so treating them as RankKernel screens would double load.
	 *
	 * @dataProvider provide_foreign_screens
	 *
	 * @param string $hookSuffix Admin page hook suffix.
	 */
	public function test_is_rank_kernel_screen_rejects_foreign_hooks( string $hookSuffix ): void {
		$this->assertFalse( AdminMenu::isRankKernelScreen( $hookSuffix ) );
	}

	/**
	 * Our screen hooks.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function provide_rankkernel_screens(): array {
		return array(
			'top level dashboard' => array( 'toplevel_page_rankkernel' ),
			'sitemaps'            => array( 'rankkernel_page_rankkernel-sitemap' ),
			'general settings'    => array( 'rankkernel_page_rankkernel-general' ),
			'schema'              => array( 'rankkernel_page_rankkernel-schema' ),
			'redirects'           => array( 'rankkernel_page_rankkernel-redirects' ),
			'404 monitor'         => array( 'rankkernel_page_rankkernel-404' ),
			'instant indexing'    => array( 'rankkernel_page_rankkernel-instant-indexing' ),
		);
	}

	/**
	 * Screens that must not be treated as ours.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function provide_foreign_screens(): array {
		return array(
			'post editor'    => array( 'post.php' ),
			'post list'      => array( 'edit.php' ),
			'term editor'    => array( 'term.php' ),
			'dashboard'      => array( 'index.php' ),
			'plugins screen' => array( 'plugins.php' ),
			'empty hook'     => array( '' ),
			'near miss'      => array( 'rankkernel_page_other-plugin' ),
		);
	}

	/**
	 * The shared layers must reach a RankKernel screen.
	 *
	 * The test runs in a separate process because stubbing plugins_url defines
	 * a process wide Brain Monkey function, and AnalysisColumn::enqueueAssets
	 * consults function_exists() on it afterwards.
	 */
	#[RunInSeparateProcess]
	public function test_enqueue_shared_styles_loads_both_layers_on_our_screen(): void {
		$enqueued = array();

		Functions\when( 'plugins_url' )->justReturn( 'https://example.test/asset.css' );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);

		$menu = new AdminMenu( new SettingsStore(), new ModuleEnableMap() );
		$menu->enqueueSharedStyles( 'rankkernel_page_rankkernel-sitemap' );

		$this->assertContains( 'rankkernel-admin', $enqueued );
		$this->assertContains( 'rankkernel-ui', $enqueued );
	}

	/**
	 * The shared layers must stay off a screen that is not ours.
	 *
	 * Runs in its own process for the same reason as the test above.
	 */
	#[RunInSeparateProcess]
	public function test_enqueue_shared_styles_stays_quiet_on_foreign_screen(): void {
		$enqueued = array();

		Functions\when( 'plugins_url' )->justReturn( 'https://example.test/asset.css' );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);

		$menu = new AdminMenu( new SettingsStore(), new ModuleEnableMap() );
		$menu->enqueueSharedStyles( 'edit.php' );

		$this->assertSame( array(), $enqueued );
	}

	/**
	 * The user profile field hooks must exist as soon as AdminMenu is built.
	 *
	 * The field belongs to the profile screens, never to the General Settings
	 * screen, so registration cannot live in a lazily constructed page
	 * controller. Without this the X handle field silently vanishes from
	 * profile.php and user-edit.php.
	 *
	 * Runs in its own process because it asserts on the global hook registry.
	 */
	#[RunInSeparateProcess]
	public function test_user_profile_field_hooks_register_on_construction(): void {
		$menu = new AdminMenu( new SettingsStore(), new ModuleEnableMap() );

		unset( $menu );

		$this->assertNotFalse( has_action( 'show_user_profile' ), 'show_user_profile must be hooked when AdminMenu is built' );
		$this->assertNotFalse( has_action( 'edit_user_profile' ), 'edit_user_profile must be hooked when AdminMenu is built' );
		$this->assertNotFalse( has_action( 'personal_options_update' ), 'personal_options_update must be hooked when AdminMenu is built' );
		$this->assertNotFalse( has_action( 'edit_user_profile_update' ), 'edit_user_profile_update must be hooked when AdminMenu is built' );
	}

	/**
	 * Build a menu with the given module map flipped on.
	 *
	 * @param string[]|array<string, bool> $enabled Enabled module ids.
	 */
	private function menuWith( array $enabled ): AdminMenu {
		$this->options['rankkernel_modules'] = $enabled;

		return new AdminMenu( new SettingsStore(), new ModuleEnableMap() );
	}

	/**
	 * Render/save guards must bounce OFF modules to the dashboard.
	 *
	 * @param callable $renderCallback Callback under test.
	 */
	private function assertRenderBounces( callable $renderCallback ): void {
		$this->redirects = [];
		$renderCallback();

		$this->assertSame( [ 'https://example.com/wp-admin/admin.php?page=rankkernel' ], $this->redirects );
	}

	/**
	 * ON modules must not bounce; the guard passes through.
	 *
	 * @param callable $callback Callback under test.
	 */
	private function assertNoBounce( callable $callback ): void {
		$this->redirects = [];

		ob_start();

		try {
			$callback();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- render internals need unstubbed WP functions; any throw means the guard passed through.
		} finally {
			ob_end_clean();
		}

		$this->assertSame( [], $this->redirects, 'enabled module must not be bounced to the dashboard' );
	}

	/**
	 * Sitemap submenu registers only when the module is enabled.
	 */
	public function test_sitemap_page_registered_only_when_enabled(): void {
		$calls = [];
		Functions\when( 'add_menu_page' )->justReturn( 'toplevel_page_rankkernel' );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);

		$this->menuWith( [] )->addMenuPage();
		$this->assertNotContains( 'rankkernel-sitemap', $calls );

		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);
		$calls = [];
		$this->menuWith( [ 'sitemaps' ] )->addMenuPage();
		$this->assertContains( 'rankkernel-sitemap', $calls );
	}

	/**
	 * A different module being enabled never registers the sitemap submenu.
	 */
	public function test_sitemap_page_not_registered_when_other_module_enabled(): void {
		$calls = [];
		Functions\when( 'add_menu_page' )->justReturn( 'toplevel_page_rankkernel' );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);

		$this->menuWith( [ 'schema' ] )->addMenuPage();
		$this->assertNotContains( 'rankkernel-sitemap', $calls );
	}

	/**
	 * Schema page registers only when enabled.
	 */
	public function test_schema_page_registered_only_when_enabled(): void {
		$calls = [];
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);

		$this->menuWith( [] )->addSchemaPage();
		$this->assertSame( [], $calls );

		$calls = [];
		$this->menuWith( [ 'schema' ] )->addSchemaPage();
		$this->assertSame( [ 'rankkernel-schema' ], $calls );
	}

	/**
	 * Redirects page registers only when enabled.
	 */
	public function test_redirects_page_registered_only_when_enabled(): void {
		$calls = [];
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);

		$this->menuWith( [] )->addRedirectsPage();
		$this->assertSame( [], $calls );

		$calls = [];
		$this->menuWith( [ 'redirects' ] )->addRedirectsPage();
		$this->assertSame( [ 'rankkernel-redirects' ], $calls );
	}

	/**
	 * 404 Monitor registers only when enabled, including numeric-string keys.
	 */
	public function test_monitor_page_registered_only_when_enabled(): void {
		$calls = [];
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);

		$this->menuWith( [] )->addMonitorPage();
		$this->assertSame( [], $calls );

		// List form: a numeric-string VALUE keyed by int must also enable it.
		$calls = [];
		$this->menuWith( [ '404' ] )->addMonitorPage();
		$this->assertSame( [ 'rankkernel-404' ], $calls );
	}

	/**
	 * Instant Indexing page registers only when enabled.
	 */
	public function test_instant_indexing_page_registered_only_when_enabled(): void {
		$calls = [];
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);

		$this->menuWith( [] )->addInstantIndexingPage();
		$this->assertSame( [], $calls );

		$calls = [];
		$this->menuWith( [ 'instant-indexing' ] )->addInstantIndexingPage();
		$this->assertSame( [ 'rankkernel-instant-indexing' ], $calls );
	}

	/**
	 * Dashboard, General and Support stay registered with everything OFF.
	 */
	public function test_unguarded_pages_register_even_when_all_modules_off(): void {
		$calls = [];
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parentMenu, string $title, string $menu, string $cap, string $slug ) use ( &$calls ): string {
				$calls[] = $slug;

				return 'hook';
			}
		);

		$menu = $this->menuWith( [] );
		Functions\when( 'add_menu_page' )->justReturn( 'toplevel_page_rankkernel' );
		$menu->addMenuPage();
		$menu->addGeneralPage();
		$menu->addSupportPage();

		$this->assertContains( 'rankkernel', $calls );
		$this->assertContains( 'rankkernel-general', $calls );
		$this->assertContains( 'rankkernel-support', $calls );
	}

	/**
	 * Render* and handle* must redirect to the dashboard when OFF.
	 */
	public function test_render_and_handle_redirect_when_module_disabled(): void {
		$menu = $this->menuWith( [] );

		$this->assertRenderBounces( [ $menu, 'renderSitemap' ] );
		$this->assertRenderBounces( [ $menu, 'renderSchema' ] );
		$this->assertRenderBounces( [ $menu, 'renderRedirects' ] );
		$this->assertRenderBounces( [ $menu, 'renderMonitor' ] );
		$this->assertRenderBounces( [ $menu, 'renderInstantIndexing' ] );
	}

	/**
	 * Save handlers must redirect too, on a real POST.
	 */
	public function test_handle_save_redirects_when_module_disabled(): void {
		$menu = $this->menuWith( [] );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_sitemap_save'    => '1',
			'rankkernel_schema_save'     => '1',
			'rankkernel_redirect_save'   => '1',
			'rankkernel_404_clear'       => '1',
			'rankkernel_indexnow_action' => 'save',
		];

		try {
			$this->assertRenderBounces( [ $menu, 'handleSitemapSave' ] );
			$this->assertRenderBounces( [ $menu, 'handleSchemaSave' ] );
			$this->assertRenderBounces( [ $menu, 'handleRedirectsSave' ] );
			$this->assertRenderBounces( [ $menu, 'handleMonitorSave' ] );
			$this->assertRenderBounces( [ $menu, 'handleInstantIndexingSave' ] );
		} finally {
			unset( $_SERVER['REQUEST_METHOD'], $_POST );
		}
	}

	/**
	 * Enabled modules render/save without the dashboard bounce.
	 */
	public function test_render_and_handle_do_not_redirect_when_enabled(): void {
		$menu = $this->menuWith( [ 'sitemaps', 'schema', 'redirects', '404', 'instant-indexing' ] );

		$this->assertNoBounce( [ $menu, 'renderSitemap' ] );
		$this->assertNoBounce( [ $menu, 'renderSchema' ] );
		$this->assertNoBounce( [ $menu, 'renderRedirects' ] );
		$this->assertNoBounce( [ $menu, 'renderMonitor' ] );
		$this->assertNoBounce( [ $menu, 'renderInstantIndexing' ] );
	}
}
