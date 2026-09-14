<?php
/**
 * Combined Redirects plus 404 Monitor gating tests, disabled means zero hooks.
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
use RankKernel\Modules\Redirects\RedirectsModule;

/**
 * Redirects Monitor Gating Test.
 */
final class RedirectsMonitorGatingTest extends TestCase {
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
	 * Boot both modules through one manager with the given enabled ids.
	 *
	 * @param string[] $enabled Enabled module ids.
	 * @return array{0: ModuleManager, 1: RedirectsModule, 2: MonitorModule} Manager plus both modules.
	 */
	private function bootWith( array $enabled ): array {
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( $enabled ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return $enabled;
				}

				return $fallback;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );

		$map       = new ModuleEnableMap();
		$manager   = new ModuleManager( $map );
		$redirects = new RedirectsModule( $map );
		$monitor   = new MonitorModule( $map );

		$manager->register( $redirects );
		$manager->register( $monitor );
		$manager->evaluateAll();
		$manager->bootEnabled();

		return [ $manager, $redirects, $monitor ];
	}

	/**
	 * Whether a hook plus priority pair was registered.
	 *
	 * @param string $hook     Hook name.
	 * @param int    $priority Hook priority.
	 * @return bool True when the pair was registered.
	 */
	private function hasHook( string $hook, int $priority ): bool {
		foreach ( $this->hooks as $entry ) {
			if ( $hook === $entry['hook'] && $priority === $entry['priority'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test both disabled boot zero hooks.
	 */
	public function test_both_disabled_boot_zero_hooks(): void {
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

		$map       = new ModuleEnableMap();
		$manager   = new ModuleManager( $map );
		$redirects = new RedirectsModule( $map );
		$monitor   = new MonitorModule( $map );

		$manager->register( $redirects );
		$manager->register( $monitor );
		$manager->evaluateAll();
		$manager->bootEnabled();

		$this->assertFalse( $manager->isOn( 'redirects' ) );
		$this->assertFalse( $manager->isOn( '404' ) );
		$this->assertFalse( $redirects->isEnabled() );
		$this->assertFalse( $monitor->isEnabled() );
		$this->assertNull( $redirects->getRedirector() );
		$this->assertNull( $redirects->getSlugWatcher() );
		$this->assertNull( $monitor->getLogger() );
	}

	/**
	 * Test only redirects enabled registers dispatch only.
	 */
	public function test_only_redirects_enabled_registers_dispatch_only(): void {
		[ $manager, $redirects, $monitor ] = $this->bootWith( [ 'redirects' ] );

		$this->assertTrue( $manager->isOn( 'redirects' ) );
		$this->assertFalse( $manager->isOn( '404' ) );
		$this->assertNotNull( $redirects->getRedirector() );
		$this->assertNotNull( $redirects->getSlugWatcher() );
		$this->assertNull( $monitor->getLogger() );
		$this->assertTrue( $this->hasHook( 'template_redirect', 1 ) );
		$this->assertFalse( $this->hasHook( 'template_redirect', 99 ) );
	}

	/**
	 * Test only monitor enabled registers capture only.
	 */
	public function test_only_monitor_enabled_registers_capture_only(): void {
		[ $manager, $redirects, $monitor ] = $this->bootWith( [ '404' ] );

		$this->assertFalse( $manager->isOn( 'redirects' ) );
		$this->assertTrue( $manager->isOn( '404' ) );
		$this->assertNull( $redirects->getRedirector() );
		$this->assertNull( $redirects->getSlugWatcher() );
		$this->assertNotNull( $monitor->getLogger() );
		$this->assertFalse( $this->hasHook( 'template_redirect', 1 ) );
		$this->assertTrue( $this->hasHook( 'template_redirect', 99 ) );
	}

	/**
	 * Test both enabled register both hooks.
	 */
	public function test_both_enabled_register_both_hooks(): void {
		[ $manager, $redirects, $monitor ] = $this->bootWith( [ 'redirects', '404' ] );

		$this->assertTrue( $manager->isOn( 'redirects' ) );
		$this->assertTrue( $manager->isOn( '404' ) );
		$this->assertNotNull( $redirects->getRedirector() );
		$this->assertNotNull( $monitor->getLogger() );

		$dispatch = array_values(
			array_filter(
				$this->hooks,
				static fn ( array $h ): bool => 'template_redirect' === $h['hook'] && 1 === $h['priority']
			)
		);
		$capture  = array_values(
			array_filter(
				$this->hooks,
				static fn ( array $h ): bool => 'template_redirect' === $h['hook'] && 99 === $h['priority']
			)
		);

		$this->assertCount( 1, $dispatch );
		$this->assertCount( 1, $capture );
	}
}
