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
	 * Physical robots.txt path override.
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

		$this->options      = [ 'rankkernel_modules' => [] ];
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

				if ( 'rankkernel/llms/physical_file' === $hook && '' !== $this->physicalPath ) {
					return $this->physicalPath;
				}

				return $value;
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'esc_html__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel' ] );
		Functions\when( 'add_rewrite_rule' )->justReturn( true );
		Functions\when( 'get_bloginfo' )->justReturn( 'Example Site' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
	}

	/**
	 * Test the llms route registers when the module and llms are enabled.
	 */
	public function test_llms_route_registers_when_enabled(): void {
		$this->options['rankkernel_modules']       = [ 'robots' ];
		$this->options['rankkernel_llms_settings'] = [ 'enabled' => true ];

		$module = new RobotsModule();
		$module->boot();

		$this->assertNotNull( $module->getLlmsRouter() );
		$this->assertNotNull( $module->getLlmsWriter() );
		$this->assertContains( [ 'pre_get_posts', 1 ], $this->hooks );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test a disabled module boots zero hooks.
	 */
	public function test_disabled_module_boots_zero_hooks(): void {
		$module = new RobotsModule();
		$module->boot();

		$this->assertFalse( $module->isEnabled() );
		$this->assertSame( [], $this->hooks );
	}

	/**
	 * Test an enabled module registers the robots filter.
	 */
	public function test_enabled_module_registers_filter(): void {
		$this->options['rankkernel_modules'] = [ 'robots' ];

		$module = new RobotsModule();
		$module->boot();

		$this->assertContains( [ 'robots_txt', 5 ], $this->hooks );
	}

	/**
	 * Test the filter blocks and allows the configured crawlers.
	 */
	public function test_filter_blocks_and_allows(): void {
		$this->options['rankkernel_modules']         = [ 'robots' ];
		$this->options['rankkernel_robots_settings'] = [
			'mode'     => 'default',
			'crawlers' => [
				'gptbot'        => 'block',
				'oai-searchbot' => 'allow',
			],
		];

		$module = new RobotsModule();
		$module->boot();

		$core = "User-agent: *\nDisallow: /wp-admin/\nSitemap: https://example.com/sitemap_index.xml\n";
		$out  = $module->filterRobots( $core, true );

		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $out );
		$this->assertStringContainsString( "User-agent: OAI-SearchBot\nAllow: /", $out );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $out );
		$this->assertSame( 1, substr_count( $out, 'Sitemap:' ) );
	}

	/**
	 * Test the filter leaves a private site untouched.
	 */
	public function test_filter_leaves_private_site(): void {
		$this->options['rankkernel_modules'] = [ 'robots' ];

		$module = new RobotsModule();
		$module->boot();

		$core = "User-agent: *\nDisallow: /\n";

		$this->assertSame( $core, $module->filterRobots( $core, false ) );
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

	/**
	 * Test renderPhysicalFileNotice screen scoping.
	 */
	public function test_render_physical_file_notice_screen_scoping(): void {
		$module = new RobotsModule();

		$temp = tempnam( sys_get_temp_dir(), 'rkrobots' );
		$this->assertIsString( $temp );
		file_put_contents( $temp, "User-agent: *\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.
		$this->physicalPath = $temp;

		// On a non-rankkernel screen, no notice should output.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'dashboard' ] );
		ob_start();
		$module->renderPhysicalFileNotice();
		$out = ob_get_clean();
		$this->assertSame( '', $out );

		// On a rankkernel screen, notice should output.
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel' ] );
		ob_start();
		$module->renderPhysicalFileNotice();
		$out = ob_get_clean();
		$this->assertStringContainsString( 'notice notice-warning', $out );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test renderLlmsPhysicalNotice screen scoping.
	 */
	public function test_render_llms_physical_notice_screen_scoping(): void {
		$this->options['rankkernel_modules']       = [ 'robots' ];
		$this->options['rankkernel_llms_settings'] = [ 'enabled' => true ];

		$module = new RobotsModule();
		$module->boot();

		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );
		$this->assertIsString( $temp );
		file_put_contents( $temp, "# LLMS\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.
		$this->physicalPath = $temp;

		// Non-rankkernel screen (suppressed by screen check even when file exists).
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'plugins' ] );
		ob_start();
		$module->renderLlmsPhysicalNotice();
		$out = ob_get_clean();
		$this->assertSame( '', $out );

		// RankKernel screen (outputs notice when file exists).
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel' ] );
		ob_start();
		$module->renderLlmsPhysicalNotice();
		$out = ob_get_clean();
		$this->assertStringContainsString( 'notice notice-warning', $out );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}
}
