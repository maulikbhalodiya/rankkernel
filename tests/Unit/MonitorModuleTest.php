<?php
/**
 * 404 Monitor module gating tests, disabled means zero hooks.
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
use RankKernel\Modules\Monitor\MonitorModule;

/**
 * Monitor Module Test.
 */
final class MonitorModuleTest extends TestCase {
	/**
	 * Registered hooks.
	 *
	 * @var array<int, array{hook: string, priority: int}>
	 */
	private array $hooks = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10 ): bool {
				$this->hooks[] = [
					'hook'     => $hook,
					'priority' => $priority,
				];

				return true;
			}
		);
		Functions\when( 'add_filter' )->alias( static fn (): bool => true );
		Functions\when( 'do_action' )->justReturn( null );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test contract values.
	 */
	public function test_contract_values(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$module = new MonitorModule();

		$this->assertSame( '404', $module->getId() );
		$this->assertSame( '404 Monitor', $module->getName() );
		$this->assertSame( 50, $module->getPriority() );
		$this->assertSame( [], $module->dependsOn() );
	}

	/**
	 * Test disabled module boots zero hooks.
	 */
	public function test_disabled_module_boots_zero_hooks(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [];
				}

				return $fallback;
			}
		);
		Functions\expect( 'add_action' )->never();
		Functions\expect( 'add_filter' )->never();
		Functions\expect( 'update_option' )->never();

		$map     = new ModuleEnableMap();
		$manager = new ModuleManager( $map );
		$module  = new MonitorModule( $map );

		$manager->register( $module );
		$manager->evaluateAll();
		$manager->bootEnabled();

		$this->assertFalse( $manager->isOn( '404' ) );
		$this->assertFalse( $module->isEnabled() );
		$this->assertNull( $module->getLogger() );
	}

	/**
	 * Test enabled module registers capture hook.
	 */
	public function test_enabled_module_registers_capture_hook(): void {
		$db = new MonitorFakeDb();

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $db;

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ '404' ];
				}

				return $fallback;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );

		$map     = new ModuleEnableMap();
		$manager = new ModuleManager( $map );
		$module  = new MonitorModule( $map );

		$manager->register( $module );
		$manager->evaluateAll();
		$manager->bootEnabled();

		$this->assertTrue( $manager->isOn( '404' ) );
		$this->assertNotNull( $module->getLogger() );

		$capture = array_values(
			array_filter(
				$this->hooks,
				static fn ( array $h ): bool => 'template_redirect' === $h['hook'] && 99 === $h['priority']
			)
		);

		$this->assertCount( 1, $capture );
	}
}
