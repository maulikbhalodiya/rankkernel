<?php
/**
 * Sitemap author rules and attachment policy tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\Provider\AuthorsProvider;
use RankKernel\Modules\Sitemaps\Provider\PostsProvider;
use RankKernel\Tests\Unit\Support\SitemapAuthorFakeWpdb;

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

final class SitemapAuthorsAttachmentsTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private SitemapAuthorFakeWpdb $db;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        if (! defined('RANKKERNEL_TESTING')) {
            define('RANKKERNEL_TESTING', true);
        }

        $this->db        = new SitemapAuthorFakeWpdb();
        $GLOBALS['wpdb'] = $this->db;

        Functions\when('mysql2date')->alias(static fn (string $f, string $d, bool $t = true): string => gmdate($f, strtotime($d)));
        Functions\when('get_author_posts_url')->alias(static fn (int $id): string => 'https://example.com/author/u' . $id . '/');
    }

    protected function tearDown(): void {
        unset($GLOBALS['wpdb']);
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_author_with_only_non_public_posts_is_absent(): void {
        Functions\when('get_post_types')->alias(static fn (array $a = [], string $o = ''): array => [ 'post' => 'post', 'page' => 'page' ]);

        $this->db->postsRows = [
            [ 'ID' => 1, 'post_type' => 'flamingo', 'post_status' => 'publish', 'post_author' => 5, 'post_modified_gmt' => '2026-01-01 00:00:00' ],
            [ 'ID' => 2, 'post_type' => 'form', 'post_status' => 'publish', 'post_author' => 5, 'post_modified_gmt' => '2026-01-01 00:00:00' ],
            [ 'ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 7, 'post_modified_gmt' => '2026-01-02 00:00:00' ],
        ];

        $provider = new AuthorsProvider();

        $this->assertSame(1, $provider->getCount('authors'));

        $entries = $provider->getEntries('authors', 1, 10);

        $this->assertCount(1, $entries);
        $this->assertSame('https://example.com/author/u7/', $entries[0]['loc']);
    }

    public function test_attachment_sets_filtered_and_short_circuited(): void {
        Functions\when('get_post_types')->alias(static fn (array $a = [], string $o = ''): array => [ 'post' => 'post', 'attachment' => 'attachment' ]);

        $provider = new PostsProvider();

        $this->assertNotContains('attachment', $provider->getSets());
        $this->assertContains('post', $provider->getSets());
        $this->assertSame([], $provider->getEntries('attachment', 1, 10));
        $this->assertSame(0, $provider->getCount('attachment'));
    }
}
