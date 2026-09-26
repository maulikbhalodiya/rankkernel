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
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
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
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );

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
	 * Test rankkernel_conflict_notice is scoped to RankKernel admin screens.
	 *
	 * Runs in a separate process because it loads rankkernel.php and defines
	 * the plugin constants. In the shared process earlier test files already
	 * define some of them, so the bare defines would raise PHP warnings that
	 * the failOnWarning setting turns into a failure.
	 */
	#[RunInSeparateProcess]
	public function test_conflict_notice_is_scoped_to_rankkernel_screens(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_conflict_notice' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_conflict_notice' === $key ) {
					return [ 'Yoast SEO' ];
				}
				return $fallback;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );

		// Case 1: get_current_screen unavailable, as on a frontend request -> Should output nothing.
		ob_start();
		\rankkernel_conflict_notice();
		$output_no_screen_function = ob_get_clean();
		$this->assertEmpty( $output_no_screen_function, 'Notice must not render when get_current_screen is unavailable' );

		// Case 2: Non RankKernel screen -> Should output nothing.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'dashboard' ] );
		ob_start();
		\rankkernel_conflict_notice();
		$output_non_rk = ob_get_clean();
		$this->assertEmpty( $output_non_rk, 'Notice must not render on a screen outside RankKernel' );

		// Case 3: Null screen -> Should output nothing.
		Functions\when( 'get_current_screen' )->justReturn( null );
		ob_start();
		\rankkernel_conflict_notice();
		$output_null_screen = ob_get_clean();
		$this->assertEmpty( $output_null_screen, 'Notice must not render when get_current_screen returns null' );

		// Case 4: Screen object without an id property -> Should output nothing.
		Functions\when( 'get_current_screen' )->justReturn( new \stdClass() );
		ob_start();
		\rankkernel_conflict_notice();
		$output_no_id = ob_get_clean();
		$this->assertEmpty( $output_no_id, 'Notice must not render when the screen has no id property' );

		// Case 5: Integer screen id -> Should output nothing.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 123 ] );
		ob_start();
		\rankkernel_conflict_notice();
		$output_int_id = ob_get_clean();
		$this->assertEmpty( $output_int_id, 'Notice must not render when the screen id is an integer' );

		// Case 6: Object screen id that would coerce to a RankKernel match -> Should output nothing.
		$stringable_id = new class() {
			/**
			 * String value for the screen id double.
			 *
			 * @return string
			 */
			public function __toString(): string {
				return 'rankkernel';
			}
		};
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => $stringable_id ] );
		ob_start();
		\rankkernel_conflict_notice();
		$output_object_id = ob_get_clean();
		$this->assertEmpty( $output_object_id, 'Notice must not render when the screen id is not a string' );

		// Case 7: Screen id that only CONTAINS rankkernel but is not a real screen -> Should output nothing.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'my-rankkernel-clone' ] );
		ob_start();
		\rankkernel_conflict_notice();
		$output_lookalike = ob_get_clean();
		$this->assertEmpty( $output_lookalike, 'Notice must not render on a screen that merely contains the rankkernel substring' );

		// Case 8: User lacking manage_options -> Should output nothing.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel' ] );
		Functions\when( 'current_user_can' )->justReturn( false );
		ob_start();
		\rankkernel_conflict_notice();
		$output_no_cap = ob_get_clean();
		$this->assertEmpty( $output_no_cap, 'Notice must not render without the manage_options capability' );

		// Case 9: RankKernel top level screen with manage_options capability -> Should output conflict notice.
		Functions\when( 'current_user_can' )->justReturn( true );
		ob_start();
		\rankkernel_conflict_notice();
		$output_rk = ob_get_clean();
		$this->assertStringContainsString( 'RankKernel detected another SEO plugin active', $output_rk );

		// Case 10: RankKernel submenu screen -> Should output conflict notice.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'rankkernel_page_rankkernel-redirects' ] );
		ob_start();
		\rankkernel_conflict_notice();
		$output_rk_sub = ob_get_clean();
		$this->assertStringContainsString( 'RankKernel detected another SEO plugin active', $output_rk_sub );

		// Case 11: Literal rankkernel screen id -> Should output conflict notice.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'rankkernel' ] );
		ob_start();
		\rankkernel_conflict_notice();
		$output_rk_literal = ob_get_clean();
		$this->assertStringContainsString( 'RankKernel detected another SEO plugin active', $output_rk_literal );

		// Case 12: Empty conflict list -> Should output nothing.
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_conflict_notice' === $key ) {
					return [];
				}
				return $fallback;
			}
		);
		ob_start();
		\rankkernel_conflict_notice();
		$output_no_conflicts = ob_get_clean();
		$this->assertEmpty( $output_no_conflicts, 'Notice must not render without conflicts' );

		// Case 13: Conflict names are escaped -> Should output no raw script tag.
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_conflict_notice' === $key ) {
					return [ '<script>alert("xss")</script>' ];
				}
				return $fallback;
			}
		);
		ob_start();
		\rankkernel_conflict_notice();
		$output_escaped = ob_get_clean();
		$this->assertStringNotContainsString( '<script>', $output_escaped, 'Conflict names must not be emitted as raw HTML' );
		$this->assertStringContainsString( '&lt;script&gt;', $output_escaped, 'Conflict names must pass through esc_html' );
	}
}
