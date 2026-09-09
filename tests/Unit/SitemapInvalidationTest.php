<?php
/**
 * Sitemap invalidation hook tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\SitemapCache;

final class SitemapInvalidationTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var array<string, mixed> */
    private array $options = [];

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        if (! defined('RANKKERNEL_TESTING')) {
            define('RANKKERNEL_TESTING', true);
        }

        $this->options = [];

        Functions\when('get_option')->alias(function (string $key, mixed $default = false): mixed {
            return $this->options[ $key ] ?? $default;
        });
        Functions\when('update_option')->alias(function (string $key, mixed $value, mixed $autoload = null): bool {
            $this->options[ $key ] = $value;

            return true;
        });
        Functions\when('apply_filters')->alias(static fn (string $h, mixed $v): mixed => $v);
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_covers_user_and_term_events(): void {
        $hooks = [];

        Functions\when('add_action')->alias(
            static function (string $hook, mixed $cb, int $prio = 10, int $args = 1) use (&$hooks): bool {
                $hooks[ $hook ] = $args;

                return true;
            }
        );

        (new SitemapCache())->registerHooks();

        $this->assertArrayHasKey('save_post', $hooks);
        $this->assertArrayHasKey('edited_terms', $hooks);
        $this->assertArrayHasKey('delete_term', $hooks);
        $this->assertArrayHasKey('clean_term_cache', $hooks);
        $this->assertArrayHasKey('user_register', $hooks);
        $this->assertArrayHasKey('delete_user', $hooks);
        $this->assertArrayHasKey('profile_update', $hooks);
        $this->assertSame(3, $hooks['delete_term']);
        $this->assertSame(2, $hooks['clean_term_cache']);
    }

    public function test_delete_user_queues_global_and_authors(): void {
        Functions\when('add_action')->justReturn(true);

        $cache = new SitemapCache();
        $cache->onDeleteUser(9);
        $cache->flushQueue();

        $this->assertArrayHasKey(SitemapCache::VALIDATOR_GLOBAL, $this->options);
        $this->assertArrayHasKey(SitemapCache::VALIDATOR_PREFIX . 'authors', $this->options);
    }

    public function test_profile_update_queues_global_and_authors(): void {
        Functions\when('add_action')->justReturn(true);

        $cache = new SitemapCache();
        $cache->onProfileUpdate(9);
        $cache->flushQueue();

        $this->assertArrayHasKey(SitemapCache::VALIDATOR_GLOBAL, $this->options);
        $this->assertArrayHasKey(SitemapCache::VALIDATOR_PREFIX . 'authors', $this->options);
    }

    public function test_clean_term_cache_queues_global_and_taxonomy(): void {
        Functions\when('add_action')->justReturn(true);

        $cache = new SitemapCache();
        $cache->onCleanTermCache([ 1, 2 ], 'category');
        $cache->flushQueue();

        $this->assertArrayHasKey(SitemapCache::VALIDATOR_GLOBAL, $this->options);
        $this->assertArrayHasKey(SitemapCache::VALIDATOR_PREFIX . 'category', $this->options);
    }

    public function test_deleted_term_queues_global_and_taxonomy(): void {
        Functions\when('add_action')->justReturn(true);

        $cache = new SitemapCache();
        $cache->onDeletedTerm(4, 44, 'post_tag');
        $cache->flushQueue();

        $this->assertArrayHasKey(SitemapCache::VALIDATOR_GLOBAL, $this->options);
        $this->assertArrayHasKey(SitemapCache::VALIDATOR_PREFIX . 'post_tag', $this->options);
    }
}
