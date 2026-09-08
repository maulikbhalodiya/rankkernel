<?php
/**
 * ModuleManager tests — hard-gate verification.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\ModuleInterface;
use RankKernel\Modules\ModuleManager;

final class ModuleManagerTest extends TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Helper to create a mock module.
     *
     * @param array<string,mixed> $overrides
     */
    private function makeModule( array $overrides = [] ): ModuleInterface {
        $defaults = [
            'getId'      => 'test-module',
            'getName'    => 'Test Module',
            'isEnabled'  => true,
            'getPriority' => 10,
            'dependsOn'  => [],
        ];

        $config = array_merge($defaults, $overrides);

        $mock = Mockery::mock(ModuleInterface::class);
        $mock->shouldReceive('getId')->andReturn($config['getId']);
        $mock->shouldReceive('getName')->andReturn($config['getName']);
        $mock->shouldReceive('isEnabled')->andReturn($config['isEnabled']);
        $mock->shouldReceive('getPriority')->andReturn($config['getPriority']);
        $mock->shouldReceive('dependsOn')->andReturn($config['dependsOn']);

        // register and boot may be stubbed per test.
        $mock->shouldReceive('register')->andReturnNull()->byDefault();
        $mock->shouldReceive('boot')->andReturnNull()->byDefault();

        return $mock;
    }

    public function test_module_off_boot_never_called_zero_hooks(): void {
        // get_option returns empty (module disabled).
        Functions\when('get_option')->justReturn([]);
        Functions\when('do_action')->justReturn(null);

        // Ensure add_action is never called by the module's boot.
        // The module is OFF, so register() and boot() should never be invoked.
        $module = Mockery::mock(ModuleInterface::class);
        $module->shouldReceive('getId')->andReturn('metadata');
        $module->shouldReceive('getName')->andReturn('Metadata');
        $module->shouldReceive('isEnabled')->once()->andReturn(false);
        $module->shouldReceive('getPriority')->andReturn(10);
        $module->shouldReceive('dependsOn')->andReturn([]);
        $module->shouldReceive('register')->never();
        $module->shouldReceive('boot')->never();

        // add_action should never be called (zero hooks).
        Functions\expect('add_action')->never();
        Functions\expect('add_filter')->never();

        $manager = new ModuleManager();
        $manager->register($module);
        $manager->evaluateAll();
        $manager->bootEnabled();

        // If we reach here without Mockery expectation failure, zero hooks holds.
        $this->assertFalse($manager->isOn('metadata'));
    }

    public function test_module_on_boot_called_exactly_once_after_register(): void {
        Functions\when('get_option')->justReturn([]);
        Functions\when('do_action')->justReturn(null);

        $order = [];

        $module = Mockery::mock(ModuleInterface::class);
        $module->shouldReceive('getId')->andReturn('metadata');
        $module->shouldReceive('getName')->andReturn('Metadata');
        $module->shouldReceive('isEnabled')->once()->andReturn(true);
        $module->shouldReceive('getPriority')->andReturn(10);
        $module->shouldReceive('dependsOn')->andReturn([]);
        $module->shouldReceive('register')->once()->ordered()->andReturnUsing(
            static function () use ( &$order ): void {
                $order[] = 'register';
            }
        );
        $module->shouldReceive('boot')->once()->ordered()->andReturnUsing(
            static function () use ( &$order ): void {
                $order[] = 'boot';
            }
        );

        $manager = new ModuleManager();
        $manager->register($module);
        $manager->evaluateAll();
        $manager->bootEnabled();

        $this->assertSame([ 'register', 'boot' ], $order);
        $this->assertTrue($manager->isOn('metadata'));
    }

    public function test_dependsOn_enforcement_forced_off_and_action_fired(): void {
        Functions\when('get_option')->justReturn([]);

        $actionFired = false;
        $firedArgs   = [];

        Functions\when('do_action')->alias(
            static function ( string $hook, ...$args ) use ( &$actionFired, &$firedArgs ): void {
                if ('rankkernel/module/force_disabled' === $hook) {
                    $actionFired = true;
                    $firedArgs   = $args;
                }
            }
        );

        // Dependency module OFF.
        $depModule = Mockery::mock(ModuleInterface::class);
        $depModule->shouldReceive('getId')->andReturn('metadata');
        $depModule->shouldReceive('getName')->andReturn('Metadata');
        $depModule->shouldReceive('isEnabled')->once()->andReturn(false);
        $depModule->shouldReceive('getPriority')->andReturn(10);
        $depModule->shouldReceive('dependsOn')->andReturn([]);
        $depModule->shouldReceive('register')->never();
        $depModule->shouldReceive('boot')->never();

        // Dependent module ON but depends on metadata.
        $dependent = Mockery::mock(ModuleInterface::class);
        $dependent->shouldReceive('getId')->andReturn('schema');
        $dependent->shouldReceive('getName')->andReturn('Schema');
        $dependent->shouldReceive('isEnabled')->once()->andReturn(true);
        $dependent->shouldReceive('getPriority')->andReturn(20);
        $dependent->shouldReceive('dependsOn')->andReturn([ 'metadata' ]);
        $dependent->shouldReceive('register')->never();
        $dependent->shouldReceive('boot')->never();

        $manager = new ModuleManager();
        $manager->register($depModule);
        $manager->register($dependent);
        $manager->evaluateAll();
        $manager->bootEnabled();

        $this->assertTrue($actionFired, 'force_disabled action should have fired');
        $this->assertSame('schema', $firedArgs[0] ?? null);
        $this->assertSame('metadata', $firedArgs[1] ?? null);
        $this->assertFalse($manager->isOn('schema'), 'Dependent should be forced OFF');
    }

    public function test_isOn_reads_cached_map_get_option_called_once(): void {
        // Single read happens at ModuleEnableMap construction.
        Functions\expect('get_option')->once()->with('rankkernel_modules', [])->andReturn([ 'metadata' ]);
        Functions\when('do_action')->justReturn(null);

        $map     = new \RankKernel\Modules\ModuleEnableMap();
        $manager = new \RankKernel\Modules\ModuleManager($map);

        // Register a real MetadataModule delegating to map — isEnabled via map.
        $settings = new \RankKernel\Settings\SettingsStore();
        $module   = new \RankKernel\Modules\Metadata\MetadataModule($settings, $map);

        $manager->register($module);
        $manager->evaluateAll();

        // Multiple isOn calls should not trigger additional get_option.
        $this->assertTrue($manager->isOn('metadata'));
        $this->assertTrue($manager->isOn('metadata'));
        $this->assertTrue($manager->isOn('metadata'));
    }

    public function test_single_option_read_across_evaluate_and_boot_with_two_modules(): void {
        Functions\expect('get_option')->once()->with('rankkernel_modules', [])->andReturn([ 'metadata', 'sitemaps' ]);
        Functions\when('do_action')->justReturn(null);

        $map     = new \RankKernel\Modules\ModuleEnableMap();
        $manager = new \RankKernel\Modules\ModuleManager($map);

        $first = Mockery::mock(ModuleInterface::class);
        $first->shouldReceive('getId')->andReturn('metadata');
        $first->shouldReceive('getName')->andReturn('Metadata');
        $first->shouldReceive('getPriority')->andReturn(10);
        $first->shouldReceive('dependsOn')->andReturn([]);
        $first->shouldReceive('register')->once()->andReturnNull();
        $first->shouldReceive('boot')->once()->andReturnNull();

        $second = Mockery::mock(ModuleInterface::class);
        $second->shouldReceive('getId')->andReturn('sitemaps');
        $second->shouldReceive('getName')->andReturn('Sitemaps');
        $second->shouldReceive('getPriority')->andReturn(20);
        $second->shouldReceive('dependsOn')->andReturn([]);
        $second->shouldReceive('register')->once()->andReturnNull();
        $second->shouldReceive('boot')->once()->andReturnNull();

        $manager->register($first);
        $manager->register($second);
        $manager->evaluateAll();
        $manager->bootEnabled();

        $this->assertTrue($manager->isOn('metadata'));
        $this->assertTrue($manager->isOn('sitemaps'));
    }

    public function test_boot_order_respects_priority(): void {
        Functions\when('get_option')->justReturn([]);
        Functions\when('do_action')->justReturn(null);

        $bootOrder = [];

        $low = Mockery::mock(ModuleInterface::class);
        $low->shouldReceive('getId')->andReturn('low');
        $low->shouldReceive('getName')->andReturn('Low');
        $low->shouldReceive('isEnabled')->once()->andReturn(true);
        $low->shouldReceive('getPriority')->andReturn(30);
        $low->shouldReceive('dependsOn')->andReturn([]);
        $low->shouldReceive('register')->once()->andReturnNull();
        $low->shouldReceive('boot')->once()->andReturnUsing(
            static function () use ( &$bootOrder ): void {
                $bootOrder[] = 'low';
            }
        );

        $high = Mockery::mock(ModuleInterface::class);
        $high->shouldReceive('getId')->andReturn('high');
        $high->shouldReceive('getName')->andReturn('High');
        $high->shouldReceive('isEnabled')->once()->andReturn(true);
        $high->shouldReceive('getPriority')->andReturn(5);
        $high->shouldReceive('dependsOn')->andReturn([]);
        $high->shouldReceive('register')->once()->andReturnNull();
        $high->shouldReceive('boot')->once()->andReturnUsing(
            static function () use ( &$bootOrder ): void {
                $bootOrder[] = 'high';
            }
        );

        $mid = Mockery::mock(ModuleInterface::class);
        $mid->shouldReceive('getId')->andReturn('mid');
        $mid->shouldReceive('getName')->andReturn('Mid');
        $mid->shouldReceive('isEnabled')->once()->andReturn(true);
        $mid->shouldReceive('getPriority')->andReturn(15);
        $mid->shouldReceive('dependsOn')->andReturn([]);
        $mid->shouldReceive('register')->once()->andReturnNull();
        $mid->shouldReceive('boot')->once()->andReturnUsing(
            static function () use ( &$bootOrder ): void {
                $bootOrder[] = 'mid';
            }
        );

        $manager = new ModuleManager();
        // Register in non-priority order.
        $manager->register($low);
        $manager->register($mid);
        $manager->register($high);
        $manager->evaluateAll();
        $manager->bootEnabled();

        $this->assertSame([ 'high', 'mid', 'low' ], $bootOrder);
    }

    public function test_late_registered_module_boots_when_enabled(): void {
        Functions\when('get_option')->justReturn([]);
        Functions\when('do_action')->justReturn(null);

        $manager = new ModuleManager();
        // Initial evaluate with empty registry — as Plugin does at plugins_loaded.
        $manager->evaluateAll();

        // Late-registered module that is enabled must boot.
        $late = Mockery::mock(ModuleInterface::class);
        $late->shouldReceive('getId')->andReturn('late-module');
        $late->shouldReceive('getName')->andReturn('Late Module');
        $late->shouldReceive('isEnabled')->once()->andReturn(true);
        $late->shouldReceive('getPriority')->andReturn(10);
        $late->shouldReceive('dependsOn')->andReturn([]);
        $late->shouldReceive('register')->once()->andReturnNull();
        $late->shouldReceive('boot')->once()->andReturnNull();

        $manager->register($late);
        $manager->bootEnabled();

        $this->assertTrue($manager->isOn('late-module'));
    }

    public function test_late_registered_module_does_not_boot_when_disabled(): void {
        Functions\when('get_option')->justReturn([]);
        Functions\when('do_action')->justReturn(null);
        Functions\expect('add_action')->never();
        Functions\expect('add_filter')->never();

        $manager = new ModuleManager();
        $manager->evaluateAll();

        $late = Mockery::mock(ModuleInterface::class);
        $late->shouldReceive('getId')->andReturn('late-module');
        $late->shouldReceive('getName')->andReturn('Late Module');
        $late->shouldReceive('isEnabled')->once()->andReturn(false);
        $late->shouldReceive('getPriority')->andReturn(10);
        $late->shouldReceive('dependsOn')->andReturn([]);
        $late->shouldReceive('register')->never();
        $late->shouldReceive('boot')->never();

        $manager->register($late);
        $manager->bootEnabled();

        $this->assertFalse($manager->isOn('late-module'));
    }

    public function test_enabled_modules_returns_only_enabled_in_priority_order(): void {
        Functions\when('get_option')->justReturn([]);
        Functions\when('do_action')->justReturn(null);

        $manager = new ModuleManager();

        $off = $this->makeModule([ 'getId' => 'off-mod', 'isEnabled' => false, 'getPriority' => 5 ]);
        $onMid = $this->makeModule([ 'getId' => 'mid', 'isEnabled' => true, 'getPriority' => 15 ]);
        $onHigh = $this->makeModule([ 'getId' => 'high', 'isEnabled' => true, 'getPriority' => 10 ]);

        $manager->register($off);
        $manager->register($onMid);
        $manager->register($onHigh);
        $manager->evaluateAll();
        $manager->bootEnabled();

        $enabled = $manager->enabledModules();

        $this->assertArrayNotHasKey('off-mod', $enabled);
        $this->assertArrayHasKey('mid', $enabled);
        $this->assertArrayHasKey('high', $enabled);
        // Priority order: high (10) before mid (15).
        $this->assertSame([ 'high', 'mid' ], array_keys($enabled));
    }
}
