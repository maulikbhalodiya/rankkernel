<?php
/**
 * ModuleRegistry tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\ModuleRegistry;

final class ModuleRegistryTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_all_returns_map(): void {
        $all = ModuleRegistry::all();

        $this->assertSame(ModuleRegistry::MODULES, $all);
        $this->assertCount(13, $all);
        $this->assertArrayHasKey('metadata', $all);
        $this->assertSame('Metadata Engine', $all['metadata']);
    }

    public function test_ids_returns_keys(): void {
        $ids = ModuleRegistry::ids();

        $expected = array_map('strval', array_keys(ModuleRegistry::MODULES));
        $this->assertSame($expected, $ids);
        $this->assertContains('sitemaps', $ids);
        $this->assertContains('404', $ids);
    }

    public function test_has_returns_true_for_known(): void {
        $this->assertTrue(ModuleRegistry::has('metadata'));
        $this->assertTrue(ModuleRegistry::has('ai'));
    }

    public function test_has_returns_false_for_unknown(): void {
        $this->assertFalse(ModuleRegistry::has('unknown'));
        $this->assertFalse(ModuleRegistry::has(''));
    }

    public function test_label_returns_label_for_known(): void {
        $this->assertSame('Metadata Engine', ModuleRegistry::label('metadata'));
        $this->assertSame('Headless', ModuleRegistry::label('headless'));
    }

    public function test_label_throws_for_unknown(): void {
        $this->expectException(\InvalidArgumentException::class);
        ModuleRegistry::label('not-real');
    }
}
