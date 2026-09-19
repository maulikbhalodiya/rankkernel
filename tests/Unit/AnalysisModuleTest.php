<?php
/**
 * Content Analysis module gating tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Analysis\AnalysisModule;
use RankKernel\Modules\ModuleEnableMap;

/**
 * Analysis Module Test.
 */
final class AnalysisModuleTest extends TestCase {
	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Captured hooks.
	 *
	 * @var array<int, array{hook: string, priority: int, callback: mixed, accepted: int}>
	 */
	private array $hooks = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$this->options = [];
		$this->hooks   = [];

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10, int $accepted = 1 ): bool {
				$this->hooks[] = [
					'hook'     => $hook,
					'priority' => $priority,
					'callback' => $callback,
					'accepted' => $accepted,
				];

				return true;
			}
		);
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test the module declares the reserved id, label and priority.
	 */
	public function test_declares_identity(): void {
		$module = new AnalysisModule( new ModuleEnableMap() );

		$this->assertSame( 'analysis', $module->getId() );
		$this->assertSame( 'Content Analysis', $module->getName() );
		$this->assertSame( [], $module->dependsOn() );
		$this->assertGreaterThan( 0, $module->getPriority() );
	}

	/**
	 * Test a disabled module boots zero hooks.
	 */
	public function test_disabled_module_boots_zero_hooks(): void {
		$this->options['rankkernel_modules'] = [];

		$module = new AnalysisModule( new ModuleEnableMap() );

		$this->assertFalse( $module->isEnabled() );

		$module->boot();

		$this->assertSame( [], $this->hooks );
	}

	/**
	 * Test an enabled module registers the REST route on the right hook.
	 */
	public function test_enabled_module_registers_the_rest_route(): void {
		$this->options['rankkernel_modules'] = [ 'analysis' ];

		$module = new AnalysisModule( new ModuleEnableMap() );

		$this->assertTrue( $module->isEnabled() );

		$module->boot();

		$this->assertCount( 1, $this->hooks );
		$this->assertSame( 'rest_api_init', $this->hooks[0]['hook'] );
	}

	/**
	 * Test register builds nothing, so a disabled module stays free.
	 */
	public function test_register_wires_nothing(): void {
		$module = new AnalysisModule( new ModuleEnableMap() );

		$module->register();

		$this->assertSame( [], $this->hooks );
	}

	/**
	 * Test the enabled state is read once and cached.
	 */
	public function test_enabled_state_is_cached(): void {
		$this->options['rankkernel_modules'] = [ 'analysis' ];

		$module = new AnalysisModule( new ModuleEnableMap() );

		$this->assertTrue( $module->isEnabled() );

		$this->options['rankkernel_modules'] = [];

		$this->assertTrue( $module->isEnabled() );
	}
}
