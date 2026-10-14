<?php
/**
 * Importer module tests, identity and the zero hooks contract.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\ImporterModule;
use RankKernel\Modules\ModuleEnableMap;

/**
 * Importer Module Test.
 */
final class ImporterModuleTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build the module under test with a toggled enable map.
	 *
	 * @param bool $enabled Whether the importer id is in the enable map.
	 * @return ImporterModule Module under test.
	 */
	private function module( bool $enabled ): ImporterModule {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( $enabled ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return $enabled ? [ 'importer' ] : [];
				}

				return $fallback;
			}
		);

		return new ImporterModule( new ModuleEnableMap() );
	}

	/**
	 * Test module identity and boot contract values.
	 */
	public function test_module_identity(): void {
		$module = $this->module( true );

		$this->assertSame( 'importer', $module->getId() );
		$this->assertSame( 'Importer', $module->getName() );
		$this->assertSame( 60, $module->getPriority() );
		$this->assertSame( [], $module->dependsOn() );
	}

	/**
	 * Test a disabled module registers zero hooks.
	 */
	public function test_disabled_module_registers_zero_hooks(): void {
		$hooks = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);

		$module = $this->module( false );
		$module->register();
		$module->boot();

		$this->assertSame( [], $hooks );
	}

	/**
	 * Test an enabled module registers only its REST endpoint hook.
	 *
	 * The importer owns no front end or admin hooks by design: its screen
	 * is wired through AdminMenu like every other page, and its chunked
	 * run endpoint registers on rest_api_init from boot.
	 */
	public function test_enabled_module_registers_only_rest_endpoint(): void {
		$hooks = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);

		$module = $this->module( true );
		$module->register();
		$module->boot();

		$this->assertSame( [ 'rest_api_init' ], $hooks );
	}

	/**
	 * Test the boot callback registers both import routes.
	 */
	public function test_rest_callback_registers_preview_and_run_routes(): void {
		$routes = [];
		Functions\when( 'register_rest_route' )->alias(
			// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound -- stub mirrors the register_rest_route signature.
			static function ( string $namespace, string $route ) use ( &$routes ): bool {
				$routes[] = $namespace . $route;
				return true;
			}
		);

		$module = $this->module( true );
		$module->registerRestRoutes();

		$this->assertSame(
			[
				'rankkernel/v1/import/(?P<source>[a-z-]+)/preview',
				'rankkernel/v1/import/(?P<source>[a-z-]+)/run',
			],
			$routes
		);
	}
}
