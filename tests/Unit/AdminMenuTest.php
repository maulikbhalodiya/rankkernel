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
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v, string $d = '' ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'get_option' )->justReturn( [] );
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
}
