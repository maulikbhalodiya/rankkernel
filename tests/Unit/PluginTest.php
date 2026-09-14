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
}
