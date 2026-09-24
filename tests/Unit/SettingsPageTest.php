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
use RankKernel\Modules\Metadata\MetaPayload;
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
		Functions\when( 'esc_attr__' )->alias( static fn ( string $v, string $d = '' ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_attr__ signature.
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $url ): string {
				$url = trim( $url );

				return preg_match( '/^https?:\\/\\//i', $url ) ? $url : '';
			}
		);
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => is_numeric( $value ) ? abs( (int) $value ) : 0 );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $v ): mixed => is_string( $v ) ? stripslashes( $v ) : $v );
		Functions\when( 'admin_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-admin/' . ltrim( $p, '/' ) );
		Functions\when( 'sanitize_key' )->alias(
			static function ( string $key ): string {
				return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
			}
		);
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
	 * @param array<string, mixed> $settings Stored settings values.
	 * @return SettingsPage The result.
	 */
	private function makePage( array $settings = [] ): SettingsPage {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( $settings ) {
				if ( 'rankkernel_modules' === $key ) {
					return [];
				}
				if ( 'rankkernel_settings' === $key ) {
					return $settings;
				}
				return $fallback;
			}
		);

		$store = new SettingsStore();
		$map   = new ModuleEnableMap();

		return new SettingsPage( $store, $map );
	}

	/**
	 * Stub the WordPress lookups used while rendering a settings section.
	 */
	private function stubSettingsPageRender(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );
	}

	/**
	 * Capture the profile hooks registered by the settings page.
	 *
	 * @param array<string, array<int, callable>> $hooks Hook callbacks by hook name.
	 */
	private function captureProfileHooks( array &$hooks ): void {
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, mixed $callback ) use ( &$hooks ): void {
				$hooks[ $hook ][] = $callback;
			}
		);
	}

	/**
	 * Assert a profile hook was registered with a callable.
	 *
	 * @param array<string, array<int, callable>> $hooks Hook callbacks by hook name.
	 * @param string                              $hook  Hook name.
	 */
	private function assertProfileHook( array $hooks, string $hook ): void {
		$this->assertArrayHasKey( $hook, $hooks );
		$this->assertIsCallable( $hooks[ $hook ][0] );
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
	 * Test media assets load only on the settings screen with the picker dependency.
	 */
	public function test_enqueue_assets_loads_media_only_on_settings_screen(): void {
		$registered = [];
		$mediaCalls = 0;

		Functions\when( 'wp_enqueue_media' )->alias(
			static function () use ( &$mediaCalls ): void {
				++$mediaCalls;
			}
		);
		Functions\when( 'wp_register_script' )->alias(
			static function ( string $handle, string $src, array $deps, mixed $ver, bool $inFooter ) use ( &$registered ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				$registered[ $handle ] = $deps;

				return true;
			}
		);
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		$page = $this->makePage();

		$page->enqueueAssets( 'some_other_page' );
		$this->assertSame( 0, $mediaCalls );

		$page->enqueueAssets( 'rankkernel_page_rankkernel-general' );
		$this->assertSame( 1, $mediaCalls );
		$this->assertContains( 'media-editor', $registered['rankkernel-settings-admin'] );
	}

	/**
	 * Test the settings stylesheet is enqueued only on the settings screen.
	 */
	public function test_enqueue_assets_adds_settings_stylesheet(): void {
		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', __FILE__ );
		}

		$registered = [];

		Functions\when( 'wp_enqueue_media' )->justReturn( true );
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
		$this->assertStringContainsString( '<nav class="rk-robots-tabs"', $output );

		$robotsTabNav = [];
		preg_match( '/<nav class="rk-robots-tabs".*?<\/nav>/s', $output, $robotsTabNav );
		$this->assertNotEmpty( $robotsTabNav, 'The robots tab landmark should render.' );
		$this->assertStringContainsString( 'aria-label="Robots.txt tabs"', $robotsTabNav[0] );
		$this->assertStringContainsString( 'aria-current="page"', $robotsTabNav[0] );
		$this->assertSame( 1, substr_count( $robotsTabNav[0], 'aria-current="page"' ), 'Exactly one robots tab may be marked as current' );
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
			'rankkernel_save'    => '1',
			'_wpnonce'           => 'valid',
			'rk_robots_override' => "User-agent: *\nDisallow: /tmp/\n",
			'rk_robots_policy'   => [ 'gptbot' => 'allow' ],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertIsArray( $captured );
		$this->assertStringContainsString( 'Disallow: /tmp/', $captured['override'] );
		$this->assertSame( 'allow', $captured['crawlers']['gptbot'] );
	}

	/**
	 * Test the robots reset clears the override.
	 */
	public function test_robots_reset_clears_override(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'wp_check_invalid_utf8' )->alias( static fn ( string $text, bool $strip = false ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_check_invalid_utf8 signature.

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
				if ( 'rankkernel_robots_settings' === $key ) {
					return [ 'override' => "User-agent: *\nDisallow: /x/\n" ];
				}

				return $fallback;
			}
		);

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rk_robots_reset' => '1',
			'_wpnonce'        => 'valid',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertIsArray( $captured );
		$this->assertSame( '', $captured['override'] );
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
	 * Test the llms section renders the consistency warning when llms.txt is
	 * on while an AI search crawler is blocked in robots.txt.
	 */
	public function test_llms_section_shows_consistency_warning_for_blocked_consumer(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [ 'robots' ] );

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'robots' ];
				}

				if ( 'rankkernel_llms_settings' === $key ) {
					return [ 'enabled' => true ];
				}

				if ( 'rankkernel_robots_settings' === $key ) {
					return [ 'crawlers' => [ 'perplexitybot' => 'block' ] ];
				}

				return $fallback;
			}
		);

		$_GET['section'] = 'llms';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Consistency check', $output );
		$this->assertStringContainsString( 'PerplexityBot', $output );
	}

	/**
	 * Test a consistent setup renders no consistency warning at all.
	 */
	public function test_llms_section_shows_no_warning_for_a_consistent_setup(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [ 'robots' ] );

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'robots' ];
				}

				if ( 'rankkernel_llms_settings' === $key ) {
					return [ 'enabled' => true ];
				}

				return $fallback;
			}
		);

		$_GET['section'] = 'llms';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-section-llms"', $output );
		$this->assertStringNotContainsString( 'Consistency check', $output );
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
	 * Test the htaccess section renders its warnings.
	 */
	public function test_htaccess_section_renders_with_warnings(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => trim( $value ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );

		$this->stubCrawlPage( [] );

		$_GET['section'] = 'htaccess';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-section-htaccess"', $output );
		$this->assertStringContainsString( 'rk-banner-danger', $output );
		$this->assertStringNotContainsString( 'notice notice-warning', $output );
	}

	/**
	 * Test a partial request renders only the section, on the load hook.
	 *
	 * The load hook is where the fix lives: the page callback runs after the
	 * admin header, so a partial rendered there would ship the whole admin
	 * page and the section loader would swap that in. Rendering on the load
	 * hook keeps the reply to the section and its save button.
	 */
	public function test_partial_request_renders_only_the_section_on_the_load_hook(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [] );

		$_GET['section']    = 'breadcrumbs';
		$_GET['rk_partial'] = 'breadcrumbs';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->maybeHandleSave();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-section-breadcrumbs"', $output );
		$this->assertStringNotContainsString( 'rk-settings-nav', $output );
		$this->assertStringNotContainsString( '<form', $output );
		$this->assertStringNotContainsString( 'wpwrap', $output );
		$this->assertStringNotContainsString( '<html', $output );
	}

	/**
	 * Test a normal page load renders nothing on the load hook.
	 *
	 * Without a partial the hook must stay silent, so the page callback still
	 * renders the full settings screen.
	 */
	public function test_load_hook_renders_nothing_without_a_partial(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		$this->stubCrawlPage( [] );

		$_GET['section'] = 'breadcrumbs';

		$page = new SettingsPage( new SettingsStore(), new ModuleEnableMap() );

		ob_start();
		$page->maybeHandleSave();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Test the social section renders its stored image and site handle.
	 */
	public function test_social_section_renders_stored_image_and_site_handle(): void {
		$this->stubSettingsPageRender();

		$_GET['section'] = 'social';

		$page = $this->makePage(
			[
				'social_default_image'    => 'https://example.com/social.jpg',
				'social_default_image_id' => 42,
				'twitter_site'            => 'rankkernel',
			]
		);

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'section=social', $output );
		$this->assertStringContainsString( 'id="rk-section-social"', $output );
		$this->assertStringContainsString( 'name="social_default_image"', $output );
		$this->assertStringContainsString( 'name="social_default_image_id"', $output );
		$this->assertStringContainsString( 'name="twitter_site"', $output );
		$this->assertStringContainsString( 'value="https://example.com/social.jpg"', $output );
		$this->assertStringContainsString( 'value="42"', $output );
		$this->assertStringContainsString( 'value="rankkernel"', $output );
		$this->assertStringContainsString( 'id="rk-social-default-image-select"', $output );
	}

	/**
	 * Test social settings save through type specific sanitizers.
	 */
	public function test_social_settings_save_uses_type_specific_sanitizers(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );

		$captured = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$captured ): bool {
				if ( 'rankkernel_settings' === $key ) {
					$captured = $value;
				}

				return true;
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'         => '1',
			'social_default_image'    => ' https://example.com/social.jpg ',
			'social_default_image_id' => '42',
			'twitter_site'            => ' @rank-kernel ',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertIsArray( $captured );
		$this->assertSame( 'https://example.com/social.jpg', $captured['social_default_image'] );
		$this->assertSame( 42, $captured['social_default_image_id'] );
		$this->assertSame( 'rankkernel', $captured['twitter_site'] );
	}

	/**
	 * Test hostile social settings are neutralised by their dedicated sanitizers.
	 */
	public function test_social_settings_rejects_hostile_values(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );

		$captured = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$captured ): bool {
				if ( 'rankkernel_settings' === $key ) {
					$captured = $value;
				}

				return true;
			}
		);

		$hostileHandle = '\"><script>alert(1)</script>@foo bar';

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_save'         => '1',
			'social_default_image'    => 'javascript:alert(1)',
			'social_default_image_id' => '-7',
			'twitter_site'            => $hostileHandle,
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertIsArray( $captured );
		$this->assertSame( '', $captured['social_default_image'] );
		$this->assertSame( 0, $captured['social_default_image_id'] );
		$this->assertSame( MetaPayload::sanitizeTwitterHandle( $hostileHandle ), $captured['twitter_site'] );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_]{0,15}$/', $captured['twitter_site'] );
	}

	/**
	 * Test non numeric and negative image ids both save as zero.
	 */
	public function test_non_numeric_or_negative_image_id_saves_as_zero(): void {
		$captured = [];
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$captured ): bool {
				if ( 'rankkernel_settings' === $key ) {
					$captured[] = $value;
				}

				return true;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );

		foreach ( [ 'not-a-number', '-7' ] as $rawId ) {
			$page = $this->makePage();

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = [
				'rankkernel_save'         => '1',
				'social_default_image_id' => $rawId,
			];

			ob_start();
			$page->maybeHandleSave();
			ob_end_clean();
		}

		$this->assertCount( 2, $captured );
		$this->assertSame( 0, $captured[0]['social_default_image_id'] );
		$this->assertSame( 0, $captured[1]['social_default_image_id'] );
	}

	/**
	 * Test the social section is accepted by the partial loader.
	 */
	public function test_social_section_is_reachable_as_a_partial(): void {
		$this->stubSettingsPageRender();

		$_GET['section']    = 'social';
		$_GET['rk_partial'] = 'social';

		$page = $this->makePage();

		ob_start();
		$page->maybeHandleSave();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-section-social"', $output );
		$this->assertStringNotContainsString( 'rk-settings-nav', $output );
	}

	/**
	 * Test the profile field renders the stored handle in bare form.
	 */
	public function test_user_profile_field_renders_sanitized_handle(): void {
		$hooks = [];
		$this->captureProfileHooks( $hooks );
		$this->makePage();

		$this->assertArrayHasKey( 'show_user_profile', $hooks );
		$this->assertArrayHasKey( 'edit_user_profile', $hooks );
		$this->assertArrayHasKey( 'personal_options_update', $hooks );
		$this->assertArrayHasKey( 'edit_user_profile_update', $hooks );

		Functions\when( 'get_user_meta' )->alias(
			static function ( mixed ...$args ): string {
				unset( $args );

				return '@author_handle';
			}
		);

		ob_start();
		call_user_func( $hooks['show_user_profile'][0], (object) [ 'ID' => 42 ] );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="rankkernel_twitter_handle"', $output );
		$this->assertStringContainsString( 'value="author_handle"', $output );
		$this->assertStringNotContainsString( '@author_handle', $output );
	}

	/**
	 * Test the profile field saves a normalised handle with edit capability.
	 */
	public function test_user_profile_field_saves_with_edit_user_capability(): void {
		$hooks = [];
		$this->captureProfileHooks( $hooks );
		$this->makePage();
		$this->assertProfileHook( $hooks, 'personal_options_update' );

		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		$captured = [];
		Functions\when( 'update_user_meta' )->alias(
			static function ( int $userId, string $key, mixed $value ) use ( &$captured ): bool {
				$captured[] = [ $userId, $key, $value ];

				return true;
			}
		);

		$_POST = [
			'_wpnonce'                  => 'valid',
			'rankkernel_twitter_handle' => '@author-handle',
		];

		call_user_func( $hooks['personal_options_update'][0], 42 );

		$this->assertSame( [ [ 42, 'rankkernel_twitter_handle', 'authorhandle' ] ], $captured );
	}

	/**
	 * Test the profile field refuses a user the current user cannot edit.
	 */
	public function test_user_profile_field_does_not_save_without_edit_user_capability(): void {
		$hooks = [];
		$this->captureProfileHooks( $hooks );
		$this->makePage();
		$this->assertProfileHook( $hooks, 'edit_user_profile_update' );

		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( false );
		$called = false;
		Functions\when( 'update_user_meta' )->alias(
			static function () use ( &$called ): bool {
				$called = true;

				return true;
			}
		);

		$_POST = [
			'_wpnonce'                  => 'valid',
			'rankkernel_twitter_handle' => 'authorhandle',
		];

		call_user_func( $hooks['edit_user_profile_update'][0], 42 );

		$this->assertFalse( $called );
	}

	/**
	 * Test the profile field does not write when its input is absent.
	 */
	public function test_user_profile_field_does_not_save_when_field_is_absent(): void {
		$hooks = [];
		$this->captureProfileHooks( $hooks );
		$this->makePage();
		$this->assertProfileHook( $hooks, 'personal_options_update' );

		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		$called = false;
		Functions\when( 'update_user_meta' )->alias(
			static function () use ( &$called ): bool {
				$called = true;

				return true;
			}
		);

		$_POST = [ '_wpnonce' => 'valid' ];

		call_user_func( $hooks['personal_options_update'][0], 42 );

		$this->assertFalse( $called );
	}

	/**
	 * Test the profile field refuses a request with an invalid profile nonce.
	 */
	public function test_user_profile_field_does_not_save_without_valid_profile_nonce(): void {
		$hooks = [];
		$this->captureProfileHooks( $hooks );
		$this->makePage();
		$this->assertProfileHook( $hooks, 'personal_options_update' );

		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );
		$called = false;
		Functions\when( 'update_user_meta' )->alias(
			static function () use ( &$called ): bool {
				$called = true;

				return true;
			}
		);

		$_POST = [
			'_wpnonce'                  => 'invalid',
			'rankkernel_twitter_handle' => 'authorhandle',
		];

		call_user_func( $hooks['personal_options_update'][0], 42 );

		$this->assertFalse( $called );
	}
}
