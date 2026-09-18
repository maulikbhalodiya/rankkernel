<?php
/**
 * Crawl Signals module tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\RobotsModule;

/**
 * Robots Module Test.
 */
final class RobotsModuleTest extends TestCase {
	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Captured hooks as [hook, priority] pairs.
	 *
	 * @var array<int, array{0: string, 1: int}>
	 */
	private array $hooks = [];

	/**
	 * Filter override for the physical robots.txt path.
	 *
	 * @var string
	 */
	private string $physicalPath = '';

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

		$this->options      = [
			'rankkernel_modules' => [],
		];
		$this->hooks        = [];
		$this->physicalPath = '';

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
		Functions\when( 'add_filter' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_filter signature.
				$this->hooks[] = [ $hook, $priority ];

				return true;
			}
		);
		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_action signature.
				$this->hooks[] = [ $hook, $priority ];

				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/robots/physical_file' === $hook && '' !== $this->physicalPath ) {
					return $this->physicalPath;
				}

				return $value;
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'esc_url' )->alias( static fn ( string $url ): string => $url );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $url ): string => trim( $url ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			static function ( string $key ): string {
				return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
			}
		);
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'esc_html__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'current_user_can' )->justReturn( true );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test disabled module boots zero hooks.
	 */
	public function test_disabled_module_boots_zero_hooks(): void {
		$module = new RobotsModule();
		$module->boot();

		$this->assertFalse( $module->isEnabled() );
		$this->assertSame( [], $this->hooks );
	}

	/**
	 * Test enabled module registers the robots filter.
	 */
	public function test_enabled_module_registers_robots_filter(): void {
		$this->options['rankkernel_modules'] = [ 'robots' ];

		$module = new RobotsModule();
		$module->boot();

		$this->assertTrue( $module->isEnabled() );
		$this->assertContains( [ 'robots_txt', 5 ], $this->hooks );
	}

	/**
	 * Test filter renders presets above the wildcard group.
	 */
	public function test_filter_renders_presets_above_wildcard(): void {
		$this->options['rankkernel_modules']         = [ 'robots' ];
		$this->options['rankkernel_robots_settings'] = [
			'mode'    => 'default',
			'presets' => [ 'gptbot' ],
		];

		$module = new RobotsModule();
		$module->boot();

		$out = $module->filterRobots( "User-agent: *\nDisallow: /wp-admin/\n", true );

		$this->assertStringContainsString( 'User-agent: GPTBot', $out );
		$this->assertLessThan( strpos( $out, 'User-agent: *' ), strpos( $out, 'User-agent: GPTBot' ) );
	}

	/**
	 * Test filter honours the public flag.
	 */
	public function test_filter_honours_public_flag(): void {
		$this->options['rankkernel_modules']         = [ 'robots' ];
		$this->options['rankkernel_robots_settings'] = [
			'mode'   => 'custom',
			'custom' => "User-agent: *\nDisallow: /\n",
		];

		$module = new RobotsModule();
		$module->boot();

		$core = "User-agent: *\nDisallow: /wp-admin/\n";

		$this->assertSame( $core, $module->filterRobots( $core, false ) );
	}

	/**
	 * Test custom mode replaces the base block.
	 */
	public function test_custom_mode_replaces_base(): void {
		$this->options['rankkernel_modules']         = [ 'robots' ];
		$this->options['rankkernel_robots_settings'] = [
			'mode'   => 'custom',
			'custom' => "User-agent: *\nDisallow: /private/\n",
		];

		$module = new RobotsModule();
		$module->boot();

		$out = $module->filterRobots( "User-agent: *\nDisallow: /wp-admin/\n", true );

		$this->assertStringContainsString( 'Disallow: /private/', $out );
		$this->assertStringNotContainsString( '/wp-admin/', $out );
	}

	/**
	 * Test physical file detection.
	 */
	public function test_physical_file_detection(): void {
		$module = new RobotsModule();

		$this->physicalPath = '/nonexistent/really/robots.txt';

		$this->assertFalse( $module->hasPhysicalFile() );

		$temp = tempnam( sys_get_temp_dir(), 'rkrobots' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "User-agent: *\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.
		$this->physicalPath = $temp;

		$this->assertTrue( $module->hasPhysicalFile() );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}
}
