<?php
/**
 * Plugin modules property test (F5).
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleManager;
use RankKernel\Modules\Metadata\MetadataModule;
use RankKernel\Plugin;
use RankKernel\Rest\LogController;
use RankKernel\Settings\SettingsStore;

/**
 * Plugin Test.
 */
final class PluginTest extends TestCase {
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
		Functions\when( 'register_meta' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
		// Reset singleton between tests.
		$ref  = new \ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Test boot populates enabled modules only.
	 */
	public function test_boot_populates_enabled_modules_only(): void {
		// Only metadata enabled.
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'metadata' ];
				}
				if ( 'rankkernel_settings' === $key ) {
					return [];
				}
				return $fallback;
			}
		);
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'do_action' )->justReturn( null );
		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		$plugin = Plugin::getInstance();
		$plugin->registerCoreServices();
		$plugin->bootModules();

		$mods = $plugin->modules();
		$this->assertArrayHasKey( 'metadata', $mods );
		$this->assertCount( 1, $mods );

		// Also via manager.
		$manager = $plugin->get( 'module_manager' );
		$this->assertInstanceOf( ModuleManager::class, $manager );
		$enabled = $manager->enabledModules();
		$this->assertArrayHasKey( 'metadata', $enabled );
	}

	/**
	 * Test an unknown service throws a raw, unescaped message.
	 *
	 * The message is exception text, not HTML output, so esc_html must not
	 * touch it. Escaping here would double escape if a display site ever
	 * renders the message.
	 */
	public function test_unknown_service_throws_a_raw_message(): void {
		$plugin = Plugin::getInstance();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'RankKernel service not found: alpha & beta' );

		$plugin->get( 'alpha & beta' );
	}

	/**
	 * Test the Instant Indexing log route is wired on rest_api_init.
	 */
	public function test_instant_indexing_log_route_is_wired_on_rest_api_init(): void {
		$routes    = [];
		$callbacks = [];

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $path ) use ( &$routes ): bool {
				$routes[] = [ $route_namespace, $path ];

				return true;
			}
		);

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$callbacks ): bool {
				if ( 'rest_api_init' === $hook ) {
					$callbacks[] = $callback;
				}

				return true;
			}
		);

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_modules' === $key || 'rankkernel_settings' === $key ) {
					return [];
				}

				return $fallback;
			}
		);
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'do_action' )->justReturn( null );

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		$plugin = Plugin::getInstance();
		$plugin->registerCoreServices();

		$this->assertInstanceOf( LogController::class, $plugin->get( 'log_controller' ) );
		$this->assertNotEmpty( $callbacks, 'the plugin must register a rest_api_init callback' );

		foreach ( $callbacks as $callback ) {
			$callback();
		}

		$this->assertContains(
			[ 'rankkernel/v1', '/instant-indexing/log' ],
			$routes,
			'the plugin must register the Instant Indexing log route on rest_api_init'
		);
	}

	/**
	 * Test plugin header version matches the version constant.
	 */
	public function test_plugin_header_version_matches_the_version_constant(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/rankkernel.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file, never a remote URL.

		$this->assertSame(
			1,
			preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $source, $header ),
			'The plugin header must declare a Version value'
		);
		$this->assertSame(
			1,
			preg_match( "/define\(\s*'RANKKERNEL_VERSION'\s*,\s*'([^']+)'\s*\)/", $source, $define ),
			'rankkernel.php must define RANKKERNEL_VERSION as the single version source'
		);
		$this->assertSame(
			$define[1],
			$header[1],
			'The header Version and RANKKERNEL_VERSION must match, assets read only the constant'
		);
	}

	/**
	 * Test PHP requirement is consistent across configurations.
	 */
	public function test_php_requirement_is_consistent_across_configurations(): void {
		$root = dirname( __DIR__, 2 );

		$php_source = (string) file_get_contents( $root . '/rankkernel.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file.
		$this->assertSame( 1, preg_match( '/^\s*\*\s*Requires PHP:\s*(\S+)/m', $php_source, $php_header ) );
		$this->assertSame( '8.2', $php_header[1], 'rankkernel.php header must require PHP 8.2' );

		$readme_source = (string) file_get_contents( $root . '/readme.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file.
		$this->assertSame( 1, preg_match( '/^Requires PHP:\s*(\S+)/m', $readme_source, $readme_header ) );
		$this->assertSame( '8.2', $readme_header[1], 'readme.txt must require PHP 8.2' );

		$composer_json = (string) file_get_contents( $root . '/composer.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file.
		$composer_data = json_decode( $composer_json, true );
		$this->assertIsArray( $composer_data );
		$this->assertSame( '>=8.2', $composer_data['require']['php'] ?? null, 'composer.json must require php >=8.2' );

		$phpcs_source = (string) file_get_contents( $root . '/phpcs.xml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file.
		$this->assertSame( 1, preg_match( '/<config name="testVersion" value="([^"]+)"\s*\/>/', $phpcs_source, $phpcs_match ) );
		$this->assertSame( '8.2-', $phpcs_match[1], 'phpcs.xml must test against PHP 8.2-' );

		$guard_pattern = "/version_compare\(\s*PHP_VERSION,\s*'8\.2\.0',\s*'<'\s*\)/";
		$this->assertSame(
			2,
			preg_match_all( $guard_pattern, $php_source, $php_guards, PREG_OFFSET_CAPTURE ),
			'rankkernel.php must guard the boot path and the activation path at PHP 8.2.0'
		);

		$autoload_offset = strpos( $php_source, 'vendor/autoload.php' );
		$this->assertNotFalse( $autoload_offset, 'rankkernel.php must load the vendor autoloader' );
		$this->assertLessThan(
			$autoload_offset,
			$php_guards[0][0][1],
			'The boot guard must run before the autoloader loads'
		);
		$this->assertGreaterThan(
			$autoload_offset,
			$php_guards[0][1][1],
			'The activation guard must run after the autoloader, inside the activation callback'
		);

		$readme_md_source = (string) file_get_contents( $root . '/README.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a local plugin file.
		$this->assertSame( 1, preg_match( '/^- PHP (\S+)$/m', $readme_md_source, $readme_md_requirement ) );
		$this->assertSame( '8.2+', $readme_md_requirement[1], 'README.md must require PHP 8.2+' );
	}
	/**
	 * Stub get_option against a mutable option store shared with the test.
	 *
	 * @param array<string, mixed> $store Options store.
	 */
	private function stub_option_store( array &$store ): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$store ) {
				return array_key_exists( $key, $store ) ? $store[ $key ] : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$store ) {
				$store[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'add_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$store ) {
				if ( ! array_key_exists( $key, $store ) ) {
					$store[ $key ] = $value;
				}
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( string $key ) use ( &$store ) {
				unset( $store[ $key ] );
				return true;
			}
		);
	}

	/**
	 * Activation seeds the conflict option with every known SEO plugin slug.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_activation_seeds_conflict_notice_with_all_known_slugs(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_activate' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		$store = array(
			'rankkernel_modules'      => array( 'metadata' ),
			'rankkernel_settings'     => array(),
			'rankkernel_db_version'   => '0.0.0',
			'active_plugins'          => array(
				'wordpress-seo/wordpress-seo.php',
				'seo-by-rank-math/rank-math.php',
				'wp-seopress/seopress.php',
				'all-in-one-seo-pack/all_in_one_seo_pack.php',
				'autodescription/autodescription.php',
			),
			'active_sitewide_plugins' => array(
				'slim-seo/slim-seo.php' => time(),
			),
		);
		$this->stub_option_store( $store );

		\rankkernel_activate();

		$this->assertSame(
			array( 'Yoast SEO', 'Rank Math', 'SEOPress', 'All in One SEO', 'The SEO Framework', 'Slim SEO' ),
			$store['rankkernel_conflict_notice'] ?? null,
			'Activation must seed the conflict notice with every active SEO plugin, network-activated included'
		);
	}

	/**
	 * Activation schedules the daily 404 prune exactly once.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_activation_schedules_daily_prune_once(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_activate' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		$scheduled = [];
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( string $hook ) use ( &$scheduled ): mixed {
				return in_array( $hook, $scheduled, true ) ? 123456 : false;
			}
		);
		Functions\when( 'wp_schedule_event' )->alias(
			static function ( int $timestamp, string $recurrence, string $hook ) use ( &$scheduled ): bool {
				$scheduled[] = $hook;

				return true;
			}
		);

		$store = array();
		$this->stub_option_store( $store );

		\rankkernel_activate();
		\rankkernel_activate();

		$this->assertSame( [ 'rankkernel_daily_prune' ], $scheduled, 'Double activation must still schedule exactly one prune event.' );
	}

	/**
	 * Deactivation flushes rewrite rules while leaving all data intact.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_deactivation_flushes_rewrite_rules_and_keeps_data(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_deactivate' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		$calls   = 0;
		$flushed = null;
		Functions\when( 'flush_rewrite_rules' )->alias(
			static function ( bool $hard = true ) use ( &$calls, &$flushed ): void {
				++$calls;
				$flushed = $hard;
			}
		);

		$store = array( 'rankkernel_settings' => array( 'separator' => 'x' ) );
		$this->stub_option_store( $store );

		\rankkernel_deactivate();

		$this->assertSame( 1, $calls, 'Deactivation must flush rewrite rules exactly once.' );
		$this->assertFalse( $flushed, 'Deactivation must flush softly, never hard.' );
		$this->assertSame( array( 'separator' => 'x' ), $store['rankkernel_settings'], 'Deactivation must leave all data intact.' );
	}

	/**
	 * Deactivation clears the daily 404 prune event.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_deactivation_clears_daily_prune_event(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_deactivate' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		Functions\when( 'flush_rewrite_rules' )->justReturn( true );

		$cleared = [];
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			static function ( string $hook ) use ( &$cleared ): void {
				$cleared[] = $hook;
			}
		);

		\rankkernel_deactivate();

		$this->assertSame( [ 'rankkernel_daily_prune' ], $cleared );
	}

	/**
	 * Conflict notice renders on the plugins screen with a live re-check.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_conflict_notice_renders_on_plugins_screen_with_live_recheck(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_conflict_notice' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		$store = array(
			'active_plugins'             => array( 'wp-seopress/seopress.php' ),
			'active_sitewide_plugins'    => array( 'wordpress-seo/wordpress-seo.php' => time() ),
			'rankkernel_conflict_notice' => array( 'Stale Plugin' ),
		);
		$this->stub_option_store( $store );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'plugins' ) );

		ob_start();
		\rankkernel_conflict_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'RankKernel detected another SEO plugin active', $output );
		$this->assertStringContainsString( 'SEOPress', $output );
		$this->assertStringContainsString( 'Yoast SEO', $output );
		$this->assertSame( array( 'Yoast SEO', 'SEOPress' ), $store['rankkernel_conflict_notice'], 'Live list must replace the stale cached option' );
	}

	/**
	 * Stale conflict option is cleared and nothing renders when no conflict is active.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_conflict_notice_clears_stale_option_when_no_conflict(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_conflict_notice' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		$store = array(
			'active_plugins'             => array(),
			'active_sitewide_plugins'    => array(),
			'rankkernel_conflict_notice' => array( 'Yoast SEO' ),
		);
		$this->stub_option_store( $store );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'plugins.php' ) );

		ob_start();
		\rankkernel_conflict_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Notice must not render when nothing conflicts' );
		$this->assertArrayNotHasKey( 'rankkernel_conflict_notice', $store, 'Stale conflict option must be deleted' );
	}

	/**
	 * Notice stays silent off the plugins screen and outside RankKernel screens.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_conflict_notice_silent_on_unrelated_screens(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_conflict_notice' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		$store = array(
			'active_plugins'             => array( 'slim-seo/slim-seo.php' ),
			'active_sitewide_plugins'    => array(),
			'rankkernel_conflict_notice' => array( 'Slim SEO' ),
		);
		$this->stub_option_store( $store );
		Functions\when( 'current_user_can' )->justReturn( true );

		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'dashboard' ) );
		ob_start();
		\rankkernel_conflict_notice();
		$this->assertEmpty( ob_get_clean(), 'Notice must not render on the dashboard' );

		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'my-rankkernel-clone' ) );
		ob_start();
		\rankkernel_conflict_notice();
		$this->assertEmpty( ob_get_clean(), 'Notice must not render on a lookalike screen id' );

		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'toplevel_page_rankkernel' ) );
		ob_start();
		\rankkernel_conflict_notice();
		$this->assertStringContainsString( 'RankKernel detected another SEO plugin active', ob_get_clean() );
	}
}
