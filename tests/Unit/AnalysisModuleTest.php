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
use RankKernel\Modules\Analysis\AnalysisScore;
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
	 * Captured register_meta calls keyed by meta key.
	 *
	 * @var array<string, array{object_type: string, args: array<string, mixed>}>
	 */
	private array $registeredMeta = [];

	/**
	 * Captured handles passed to wp_enqueue_style.
	 *
	 * @var string[]
	 */
	private array $enqueuedStyles = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$this->options        = [];
		$this->hooks          = [];
		$this->registeredMeta = [];
		$this->enqueuedStyles = [];

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
		Functions\when( 'register_meta' )->alias(
			function ( string $objectType, string $metaKey, array $args ): bool {
				$this->registeredMeta[ $metaKey ] = [
					'object_type' => $objectType,
					'args'        => $args,
				];

				return true;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			function ( string $handle ): void {
				$this->enqueuedStyles[] = $handle;
			}
		);
		Functions\when( 'add_filter' )->alias(
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
		Functions\when( 'get_post_types' )->alias(
			static fn (): array => [
				'post'       => 'post',
				'page'       => 'page',
				'attachment' => 'attachment',
			]
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
	 * An enabled module wires the REST route and the save handler.
	 */
	public function test_enabled_module_wires_its_hooks(): void {
		$this->options['rankkernel_modules'] = [ 'analysis' ];

		$module = new AnalysisModule( new ModuleEnableMap() );
		$module->boot();

		$hooks = array_column( $this->hooks, 'hook' );

		$this->assertContains( 'rest_api_init', $hooks );
		$this->assertContains( 'wp_after_insert_post', $hooks );

		$afterInsert = array_values(
			array_filter( $this->hooks, static fn ( array $hook ): bool => 'wp_after_insert_post' === $hook['hook'] )
		);

		$this->assertSame( 20, $afterInsert[0]['priority'] );
	}

	/**
	 * A disabled module registers no meta and no hooks, so it costs nothing.
	 */
	public function test_disabled_module_registers_nothing(): void {
		$this->options['rankkernel_modules'] = [];

		$module = new AnalysisModule( new ModuleEnableMap() );

		$module->register();
		$module->boot();

		$this->assertSame( [], $this->registeredMeta );
		$this->assertSame( [], $this->hooks );
	}

	/**
	 * A disabled module costs nothing: no meta, no hook and no asset.
	 *
	 * The assertions read the recorded calls, not expectations, because a
	 * Functions\expect() on a function already stubbed in setUp is vacuous.
	 */
	public function test_disabled_module_has_zero_cost(): void {
		$this->options['rankkernel_modules'] = [];

		$module = new AnalysisModule( new ModuleEnableMap() );

		$module->register();
		$module->boot();

		$this->assertFalse( $module->isEnabled() );
		$this->assertSame( [], $this->registeredMeta );
		$this->assertSame( [], $this->hooks );
		$this->assertSame( [], $this->enqueuedStyles );
	}

	/**
	 * An enabled module registers the score meta key, not exposed to REST.
	 */
	public function test_register_registers_the_score_meta_key(): void {
		$this->options['rankkernel_modules'] = [ 'analysis' ];

		$module = new AnalysisModule( new ModuleEnableMap() );
		$module->register();

		$this->assertArrayHasKey( AnalysisScore::META_KEY, $this->registeredMeta );

		$registered = $this->registeredMeta[ AnalysisScore::META_KEY ];

		$this->assertSame( 'post', $registered['object_type'] );
		$this->assertArrayNotHasKey( 'show_in_rest', $registered['args'] );
		$this->assertSame( [ AnalysisScore::class, 'sanitize' ], $registered['args']['sanitize_callback'] );
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

	/**
	 * An enabled module wires the score column through the deferred pass.
	 */
	public function test_enabled_module_wires_the_score_column(): void {
		$this->options['rankkernel_modules'] = [ 'analysis' ];

		$module = new AnalysisModule( new ModuleEnableMap() );
		$module->boot();

		$hooks = array_column( $this->hooks, 'hook' );

		$this->assertContains( 'admin_init', $hooks );
		$this->assertContains( 'pre_get_posts', $hooks );

		$adminInit = array_values(
			array_filter( $this->hooks, static fn ( array $hook ): bool => 'admin_init' === $hook['hook'] )
		);

		$this->assertNotEmpty( $adminInit );

		$registerColumns = $adminInit[0]['callback'];
		$registerColumns();

		$after = array_column( $this->hooks, 'hook' );

		$this->assertContains( 'manage_post_posts_columns', $after );
		$this->assertContains( 'manage_edit-post_sortable_columns', $after );
	}
}
