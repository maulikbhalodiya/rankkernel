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
	 * The plugin entry point is loaded here to exercise the real function. It
	 * defines the RANKKERNEL_* constants, which are process global and cannot be
	 * undefined, so this test runs in its own process. That keeps the shared
	 * process constants, and the bare define diagnostics in rankkernel.php,
	 * exactly as they are for every other test file.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_conflict_notice_is_scoped_to_rankkernel_screens(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'register_deactivation_hook' )->justReturn( true );

		if ( ! function_exists( 'rankkernel_conflict_notice' ) ) {
			require_once dirname( __DIR__, 2 ) . '/rankkernel.php';
		}

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_conflict_notice' === $key ) {
					return [ '<script>alert(1)</script>' ];
				}
				return $fallback;
			}
		);

		// Case 1: get_current_screen missing entirely. This is the frontend,
		// cron, AJAX and REST shape, so the notice must fail closed. This case
		// runs before any stub defines get_current_screen.
		$this->assertFalse( function_exists( 'get_current_screen' ) );
		$this->assertSame( '', $this->render_conflict_notice() );

		// Case 2: a null screen, WordPress returns null when no screen is set.
		Functions\when( 'get_current_screen' )->justReturn( null );
		$this->assertSame( '', $this->render_conflict_notice() );

		// Case 3: a non object screen fails closed.
		Functions\when( 'get_current_screen' )->justReturn( 'dashboard' );
		$this->assertSame( '', $this->render_conflict_notice() );

		// Case 4: an object screen without an id property fails closed.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'base' => 'dashboard' ] );
		$this->assertSame( '', $this->render_conflict_notice() );

		// Case 5: a non RankKernel screen renders nothing.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'dashboard' ] );
		$this->assertSame( '', $this->render_conflict_notice() );

		// Case 6: a RankKernel screen without manage_options renders nothing.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel' ] );
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->assertSame( '', $this->render_conflict_notice() );

		// Case 7: a RankKernel top level screen with manage_options renders the
		// notice, and the interpolated plugin name is escaped.
		Functions\when( 'current_user_can' )->justReturn( true );
		$output = $this->render_conflict_notice();
		$this->assertStringContainsString( 'RankKernel detected another SEO plugin active', $output );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $output );
		$this->assertStringNotContainsString( '<script>', $output );

		// Case 8: a RankKernel submenu screen renders the notice too.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'rankkernel_page_rankkernel-redirects' ] );
		$this->assertStringContainsString( 'RankKernel detected another SEO plugin active', $this->render_conflict_notice() );

		// Case 9: no stored conflicts renders nothing.
		Functions\when( 'get_option' )->justReturn( [] );
		$this->assertSame( '', $this->render_conflict_notice() );

		// Case 10: a non array stored value renders nothing.
		Functions\when( 'get_option' )->justReturn( 'Yoast SEO' );
		$this->assertSame( '', $this->render_conflict_notice() );
	}

	/**
	 * Render the conflict notice and return the captured output.
	 *
	 * @return string The captured output.
	 */
	private function render_conflict_notice(): string {
		ob_start();
		\rankkernel_conflict_notice();
		return (string) ob_get_clean();
	}
}
