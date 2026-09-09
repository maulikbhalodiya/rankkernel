<?php
/**
 * SitemapCache invalidateAll tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\SitemapCache;

final class SitemapSettingsCacheTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_invalidate_all_bumps_global_validator_exactly_once(): void {
        $store = [
            SitemapCache::VALIDATOR_GLOBAL => 'old_global',
        ];

        Functions\when('get_option')->alias(
            static function (string $key, mixed $default = false) use (&$store): mixed {
                return $store[ $key ] ?? $default;
            }
        );

        $writes = 0;

        Functions\when('update_option')->alias(
            static function (string $key, mixed $value, mixed ...$rest) use (&$store, &$writes): bool {
                if (SitemapCache::VALIDATOR_GLOBAL === $key) {
                    ++$writes;
                }

                $store[ $key ] = $value;

                return true;
            }
        );

        SitemapCache::invalidateAll();

        $this->assertSame(1, $writes, 'Global validator must change exactly once');
        $this->assertNotSame('old_global', $store[ SitemapCache::VALIDATOR_GLOBAL ]);
        $this->assertIsString($store[ SitemapCache::VALIDATOR_GLOBAL ]);
    }
}
