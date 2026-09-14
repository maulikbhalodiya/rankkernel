<?php
/**
 * 404 flood guard tests, budget cap plus window reset.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\FloodGuard;
use RankKernel\Modules\Monitor\MonitorSettings;

/**
 * Monitor Flood Guard Test.
 */
final class MonitorFloodGuardTest extends TestCase {
	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $key ): bool {
				unset( $this->options[ $key ] );

				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test budget caps new uris and sets marker.
	 */
	public function test_budget_caps_new_uris_and_sets_marker(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 2 ];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertTrue( $guard->allowNew() );
		$this->assertFalse( $guard->allowNew() );
		$this->assertFalse( $guard->allowNew() );

		$this->assertArrayHasKey( FloodGuard::SUPPRESSED_OPTION, $this->options );
		$this->assertTrue( FloodGuard::isSuppressed() );
	}

	/**
	 * Test expired window resets the budget.
	 */
	public function test_expired_window_resets_the_budget(): void {
		$this->options['rankkernel_404_settings'] = [
			'flood_budget' => 1,
			'flood_window' => 300,
		];
		$this->transients['rk404_flood_1']        = [
			'start' => time() - 400,
			'count' => 1,
		];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertFalse( FloodGuard::isSuppressed() );
	}

	/**
	 * Test marker clears.
	 */
	public function test_marker_clears(): void {
		$this->options[ FloodGuard::SUPPRESSED_OPTION ] = time();

		$this->assertTrue( FloodGuard::isSuppressed() );

		FloodGuard::clearSuppressed();

		$this->assertFalse( FloodGuard::isSuppressed() );
	}

	/**
	 * Test state is keyed per site.
	 */
	public function test_state_is_keyed_per_site(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 1 ];

		$guard = new FloodGuard( new MonitorSettings() );

		$this->assertTrue( $guard->allowNew() );
		$this->assertArrayHasKey( 'rk404_flood_1', $this->transients );
	}
}
