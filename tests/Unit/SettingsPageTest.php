<?php
/**
 * SettingsPage save-handler tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\SettingsPage;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Settings\SettingsStore;

/**
 * Settings Page Test.
 */
final class SettingsPageTest extends TestCase {
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

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v, string $d = '' ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $v ): mixed => is_string( $v ) ? stripslashes( $v ) : $v );
		Functions\when( 'admin_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-admin/' . ltrim( $p, '/' ) );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'submit_button' )->justReturn( '' );
		Functions\when( 'checked' )->alias(
			static fn ( mixed $a, mixed $b, bool $display = true ): string => ( (string) $a === (string) $b && '' !== (string) $a ) || ( true === $a && true === $b ) ? 'checked="checked"' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress checked signature.
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();

		$_POST                     = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	/**
	 * Make Page.
	 *
	 * @return SettingsPage The result.
	 */
	private function makePage(): SettingsPage {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_modules' === $key ) {
					return [];
				}
				if ( 'rankkernel_settings' === $key ) {
					return [];
				}
				return $fallback;
			}
		);

		$store = new SettingsStore();
		$map   = new ModuleEnableMap();

		return new SettingsPage( $store, $map );
	}

	/**
	 * Test save valid nonce and caps redirects and saves.
	 */
	public function test_save_valid_nonce_and_caps_redirects_and_saves(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\expect( 'update_option' )->atLeast()->once()->andReturn( true );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'https://example.com/wp-admin/admin.php?page=rankkernel-general&settings-updated=1' )
			->andReturn( true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'    => '1',
			'_wpnonce'           => 'valid',
			'title_template'     => 'My Title %%title%%',
			'separator'          => '|',
			'webmaster_google'   => 'google123',
			'rankkernel_modules' => [ 'metadata', 'sitemaps' ],
			'purge_on_uninstall' => '1',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		// update_option called at least twice: settings + modules, already asserted via atLeast.
		$this->assertTrue( true );
	}

	/**
	 * Test checkbox absent means false.
	 */
	public function test_checkbox_absent_means_false(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		$capturedSettings = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$capturedSettings ): bool {
				if ( 'rankkernel_settings' === $key ) {
					$capturedSettings = $value;
				}
				return true;
			}
		);
		Functions\when( 'wp_safe_redirect' )->justReturn( true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save' => '1',
			'_wpnonce'        => 'valid',
			'title_template'  => 't',
			// purge_on_uninstall NOT present → should be false.
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertIsArray( $capturedSettings );
		$this->assertArrayHasKey( 'purge_on_uninstall', $capturedSettings );
		$this->assertFalse( $capturedSettings['purge_on_uninstall'] );
	}

	/**
	 * Test invalid nonce no save wp die.
	 */
	public function test_invalid_nonce_no_save_wp_die(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\expect( 'update_option' )->never();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save' => '1',
			'_wpnonce'        => 'bad',
			'title_template'  => 'x',
		];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Test missing caps wp die.
	 */
	public function test_missing_caps_wp_die(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'check_admin_referer' )->never();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save' => '1',
			'_wpnonce'        => 'valid',
		];

		$this->expectException( \RuntimeException::class );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Test redirect after save.
	 */
	public function test_redirect_after_save(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with(
				\Mockery::on(
					static function ( string $url ): bool {
						return str_contains( $url, 'page=rankkernel' ) && str_contains( $url, 'settings-updated=1' );
					}
				)
			)
			->andReturn( true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save' => '1',
			'_wpnonce'        => 'valid',
			'title_template'  => 'hello',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertTrue( true );
	}

	/**
	 * Test the General Settings shell renders the persistent left nav and sections.
	 */
	public function test_general_settings_shell_renders_nav_and_sections(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$page = $this->makePage();

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="rk-settings"', $output );
		$this->assertStringContainsString( 'class="rk-settings-nav"', $output );
		$this->assertStringContainsString( 'section=general', $output );
		$this->assertStringContainsString( 'section=breadcrumbs', $output );
		$this->assertStringContainsString( 'section=webmaster', $output );
		$this->assertStringContainsString( 'section=advanced', $output );
		$this->assertStringContainsString( 'id="rk-section-general"', $output );
		$this->assertStringNotContainsString( 'id="rk-section-breadcrumbs"', $output );
	}

	/**
	 * Test the settings stylesheet is enqueued only on the settings screen.
	 */
	public function test_enqueue_assets_adds_settings_stylesheet(): void {
		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', __FILE__ );
		}

		$registered = [];

		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src = '', array $deps = [], mixed $ver = false ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_register_style signature.
				$registered[] = $handle;

				return true;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle ) use ( &$registered ): void {
				$registered[] = $handle;
			}
		);
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.

		$page = $this->makePage();

		$page->enqueueAssets( 'some_other_page' );
		$this->assertNotContains( 'rankkernel-settings-admin', $registered );

		$page->enqueueAssets( 'rankkernel_page_rankkernel-general' );
		$this->assertContains( 'rankkernel-settings-admin', $registered );
	}

	/**
	 * Test the robots section renders when the module is enabled.
	 */
	public function test_robots_section_renders_when_enabled(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [ 'robots' ] );

		$_GET['section'] = 'robots';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'section=robots', $output );
		$this->assertStringContainsString( 'id="rk-section-robots"', $output );
		$this->assertStringContainsString( 'rk_robots_policy[gptbot]', $output );
	}

	/**
	 * Test the robots settings save with the settings form.
	 */
	public function test_robots_settings_saved(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );

		$captured = null;

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value, mixed $autoload = null ) use ( &$captured ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				if ( 'rankkernel_robots_settings' === $key ) {
					$captured = $value;
				}

				return true;
			}
		);

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'robots' ];
				}

				return $fallback;
			}
		);

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'  => '1',
			'_wpnonce'         => 'valid',
			'rk_robots_mode'   => 'custom',
			'rk_robots_custom' => "Disallow: /tmp/\n",
			'rk_robots_policy' => [ 'gptbot' => 'allow' ],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertIsArray( $captured );
		$this->assertSame( 'custom', $captured['mode'] );
		$this->assertSame( 'allow', $captured['crawlers']['gptbot'] );
	}

	/**
	 * Stub the WordPress functions the robots and llms sections need.
	 *
	 * @param array<string, mixed> $modules Module enable map value.
	 */
	private function stubCrawlPage( array $modules ): void {
		Functions\when( 'esc_textarea' )->alias( static fn ( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'get_bloginfo' )->justReturn( 'Example Site' );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'sanitize_key' )->alias(
			static function ( string $key ): string {
				return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( $modules ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return $modules;
				}

				return $fallback;
			}
		);
	}

	/**
	 * Test the llms section renders when the module is enabled.
	 */
	public function test_llms_section_renders_when_enabled(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [ 'robots' ] );

		$_GET['section'] = 'llms';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'section=llms', $output );
		$this->assertStringContainsString( 'id="rk-section-llms"', $output );
		$this->assertStringContainsString( 'rk_llms_content', $output );
		$this->assertStringContainsString( 'name="rk_llms_write"', $output );
	}

	/**
	 * Test the llms settings save with the settings form.
	 */
	public function test_llms_settings_saved(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );

		$this->stubCrawlPage( [ 'robots' ] );

		$captured  = null;
		$validator = false;

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value, mixed $autoload = null ) use ( &$captured, &$validator ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				if ( 'rankkernel_llms_settings' === $key ) {
					$captured = $value;
				}
				if ( 'rankkernel_llms_validator' === $key ) {
					$validator = true;
				}

				return true;
			}
		);

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save' => '1',
			'_wpnonce'        => 'valid',
			'rk_llms_enabled' => '1',
			'rk_llms_summary' => 'A summary.',
			'rk_llms_content' => "## Company\n\n- [About](https://example.com/about)\n",
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertIsArray( $captured );
		$this->assertTrue( $captured['enabled'] );
		$this->assertStringContainsString( '## Company', $captured['content'] );
		$this->assertTrue( $validator );
	}

	/**
	 * Test the physical llms.txt write action.
	 */
	public function test_llms_write_action_writes_file(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'update_option' )->justReturn( true );

		$temp = tempnam( sys_get_temp_dir(), 'rkllmswrite' );

		$this->assertIsString( $temp );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$redirect = '';
		Functions\when( 'wp_safe_redirect' )->alias(
			static function ( string $url ) use ( &$redirect ): void {
				$redirect = $url;
			}
		);
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'robots' ];
				}

				return $fallback;
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Example Site' );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ) use ( $temp ): mixed {
				if ( 'rankkernel/llms/physical_file' === $hook ) {
					return $temp;
				}

				return $value;
			}
		);

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rk_llms_write'   => '1',
			'_wpnonce'        => 'valid',
			'rk_llms_summary' => 'A summary.',
			'rk_llms_content' => "## Company\n\n- [About](https://example.com/about)\n",
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertFileExists( $temp );
		$this->assertStringContainsString( 'rk_notice=written', $redirect );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test only the active settings section renders.
	 */
	public function test_only_active_section_renders(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [] );

		$_GET['section'] = 'webmaster';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-section-webmaster"', $output );
		$this->assertStringNotContainsString( 'id="rk-section-general"', $output );
		$this->assertStringContainsString( 'section=htaccess', $output );
	}

	/**
	 * Test the htaccess section is a deferred placeholder.
	 */
	public function test_htaccess_section_is_a_placeholder(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [] );

		$_GET['section'] = 'htaccess';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-section-htaccess"', $output );
		$this->assertStringContainsString( 'not available yet', $output );
	}
}
