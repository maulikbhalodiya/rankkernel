<?php
/**
 * Redirects module gating tests, disabled means zero hooks.
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
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\RedirectsModule;

/**
 * Redirects Module Test.
 */
final class RedirectsModuleTest extends TestCase {
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

		$module = new RedirectsModule();

		$this->assertSame( 'redirects', $module->getId() );
		$this->assertSame( 'Redirects', $module->getName() );
		$this->assertSame( 40, $module->getPriority() );
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
		$module  = new RedirectsModule( $map );

		$manager->register( $module );
		$manager->evaluateAll();
		$manager->bootEnabled();

		$this->assertFalse( $manager->isOn( 'redirects' ) );
		$this->assertFalse( $module->isEnabled() );
		$this->assertNull( $module->getRedirector() );
	}

	/**
	 * Test enabled module registers dispatch hook.
	 */
	public function test_enabled_module_registers_dispatch_hook(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'redirects' ];
				}

				return $fallback;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );

		$map     = new ModuleEnableMap();
		$manager = new ModuleManager( $map );
		$module  = new RedirectsModule( $map );

		$manager->register( $module );
		$manager->evaluateAll();
		$manager->bootEnabled();

		$this->assertTrue( $manager->isOn( 'redirects' ) );
		$this->assertNotNull( $module->getRedirector() );

		$dispatch = array_values(
			array_filter(
				$this->hooks,
				static fn ( array $h ): bool => 'template_redirect' === $h['hook'] && 1 === $h['priority']
			)
		);

		$this->assertCount( 1, $dispatch );
	}

	/**
	 * Test an enabled register never bumps the cache validator per request.
	 */
	public function test_enabled_register_does_not_invalidate_match_cache(): void {
		$options = [];

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$options ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return [ 'redirects' ];
				}

				return $options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$options ): bool {
				$options[ $key ] = $value;

				return true;
			}
		);

		$module = new RedirectsModule();
		$module->register();

		$this->assertTrue( $module->isEnabled() );
		$this->assertArrayNotHasKey(
			RedirectCache::VALIDATOR_OPTION,
			$options,
			'register must not invalidate the match cache on every request'
		);
	}
}
