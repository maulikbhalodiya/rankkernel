<?php
/**
 * IndexBuilder tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\IndexBuilder;
use RankKernel\Modules\Sitemaps\Provider\AuthorsProvider;
use RankKernel\Modules\Sitemaps\Provider\PostsProvider;
use RankKernel\Modules\Sitemaps\Provider\TaxonomiesProvider;

final class IndexBuilderTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_url')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: $v);
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
        Functions\when('home_url')->alias(static fn (string $p = ''): string => 'https://example.com' . $p);
        Functions\when('add_query_arg')->alias(static fn (mixed $k = '', mixed $v = '', string $u = ''): string => $u . (str_contains($u, '?') ? '&' : '?') . (string) $k . '=' . (string) $v);
        Functions\when('mysql2date')->alias(static fn (string $format, string $date, bool $translate = true): string => gmdate($format, strtotime($date)));
        Functions\when('current_time')->alias(static fn (string $type, bool $gmt = false): string => '2026-01-01 00:00:00');
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function makeBuilderWithCounts(array $map, int $perPage = 1000): IndexBuilder {
        $posts = Mockery::mock(PostsProvider::class);
        $tax   = Mockery::mock(TaxonomiesProvider::class);
        $auth  = Mockery::mock(AuthorsProvider::class);

        // Default sets.
        $postsSets = [];
        $taxSets   = [];
        $authSets  = [];
        foreach ($map as $set => $count) {
            if (str_starts_with($set, 'tax_')) {
                $taxSets[] = substr($set, 4);
            } elseif ('authors' === $set) {
                $authSets[] = $set;
            } else {
                $postsSets[] = $set;
            }
        }

        if ([] === $postsSets && isset($map['post'])) {
            $postsSets = [ 'post' ];
        }

        $posts->shouldReceive('getSets')->andReturn($postsSets)->byDefault();
        $tax->shouldReceive('getSets')->andReturn($taxSets)->byDefault();
        $auth->shouldReceive('getSets')->andReturn($authSets)->byDefault();

        foreach ($map as $set => $count) {
            $realSet = str_starts_with($set, 'tax_') ? substr($set, 4) : $set;
            if (in_array($realSet, [ 'category', 'post_tag' ], true)) {
                $tax->shouldReceive('getCount')->with($realSet)->andReturn($count)->byDefault();
            } elseif ('authors' === $realSet) {
                $auth->shouldReceive('getCount')->with($realSet)->andReturn($count)->byDefault();
            } else {
                $posts->shouldReceive('getCount')->with($realSet)->andReturn($count)->byDefault();
            }
        }

        // Entries fallback, not used in index tests.
        $posts->shouldReceive('getEntries')->andReturn([])->byDefault();
        $tax->shouldReceive('getEntries')->andReturn([])->byDefault();
        $auth->shouldReceive('getEntries')->andReturn([])->byDefault();

        Functions\when('apply_filters')->alias(
            static function (string $hook, mixed $value) use ($perPage): mixed {
                if ('rankkernel/sitemap/entries_per_page' === $hook) {
                    return $perPage;
                }
                return $value;
            }
        );

        return new IndexBuilder($posts, $tax, $auth, '9.9.9-test');
    }

    public function test_index_xml_shape_one_sitemap_per_populated_set(): void {
        $builder = $this->makeBuilderWithCounts([
            'post'        => 2500,
            'page'        => 0,
            'tax_category' => 5,
            'authors'     => 3,
        ], 1000);

        $xml = $builder->buildIndexXml();

        $this->assertStringContainsString('<sitemapindex', $xml);
        $this->assertStringContainsString('post-sitemap.xml', $xml);
        $this->assertStringContainsString('post-sitemap2.xml', $xml);
        $this->assertStringContainsString('post-sitemap3.xml', $xml);
        $this->assertStringNotContainsString('page-sitemap', $xml, 'Empty sets must be skipped');
        $this->assertStringContainsString('category-sitemap.xml', $xml);
        $this->assertStringContainsString('authors-sitemap.xml', $xml);
    }

    public function test_stylesheet_version_injected_into_pi_href(): void {
        // The XSL version is an explicit constructor dependency, never a
        // global read, so the cache-buster always matches the caller.
        $builder = $this->makeBuilderWithCounts([ 'post' => 5 ], 1000);

        $xml = $builder->buildIndexXml();

        $this->assertStringContainsString('sitemap.xsl?ver=9.9.9-test', $xml);
    }

    public function test_index_page_count_math(): void {
        $builder = $this->makeBuilderWithCounts([ 'post' => 1000 ], 1000);
        $xml     = $builder->buildIndexXml();
        $this->assertStringContainsString('post-sitemap.xml', $xml);
        $this->assertStringNotContainsString('post-sitemap2.xml', $xml);

        $builder2 = $this->makeBuilderWithCounts([ 'post' => 1001 ], 1000);
        $xml2     = $builder2->buildIndexXml();
        $this->assertStringContainsString('post-sitemap2.xml', $xml2);
    }

    public function test_empty_set_skipped(): void {
        $builder = $this->makeBuilderWithCounts([ 'post' => 0, 'page' => 0 ], 1000);
        $xml     = $builder->buildIndexXml();

        $this->assertStringNotContainsString('<sitemap>', $xml);
        $this->assertStringContainsString('<sitemapindex', $xml);
    }

    public function test_entries_xml_with_lastmod_and_image(): void {
        $posts = Mockery::mock(PostsProvider::class);
        $posts->shouldReceive('getSets')->andReturn([ 'post' ])->byDefault();
        $posts->shouldReceive('getCount')->andReturn(1)->byDefault();
        $posts->shouldReceive('getEntries')->with('post', 1, 1000)->andReturn([
            [
                'loc'     => 'https://example.com/hello/',
                'lastmod' => '2026-01-01T00:00:00+00:00',
                'image'   => 'https://example.com/image.jpg',
            ],
        ])->byDefault();

        $tax  = Mockery::mock(TaxonomiesProvider::class);
        $tax->shouldReceive('getSets')->andReturn([])->byDefault();
        $tax->shouldReceive('getCount')->andReturn(0)->byDefault();
        $tax->shouldReceive('getEntries')->andReturn([])->byDefault();

        $auth = Mockery::mock(AuthorsProvider::class);
        $auth->shouldReceive('getSets')->andReturn([])->byDefault();
        $auth->shouldReceive('getCount')->andReturn(0)->byDefault();
        $auth->shouldReceive('getEntries')->andReturn([])->byDefault();

        Functions\when('apply_filters')->alias(static fn (string $h, mixed $v): mixed => 1000);

        $builder = new IndexBuilder($posts, $tax, $auth);
        $xml     = $builder->buildEntriesXml('post', 1);

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('<loc>https://example.com/hello/</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2026-01-01T00:00:00+00:00</lastmod>', $xml);
        $this->assertStringContainsString('<image:image>', $xml);
        $this->assertStringContainsString('<image:loc>https://example.com/image.jpg</image:loc>', $xml);
    }

    public function test_entries_xml_without_image_no_block(): void {
        $posts = Mockery::mock(PostsProvider::class);
        $posts->shouldReceive('getSets')->andReturn([ 'post' ])->byDefault();
        $posts->shouldReceive('getCount')->andReturn(1)->byDefault();
        $posts->shouldReceive('getEntries')->with('post', 1, 1000)->andReturn([
            [
                'loc'     => 'https://example.com/no-image/',
                'lastmod' => '2026-02-02T00:00:00+00:00',
                'image'   => null,
            ],
        ])->byDefault();

        $tax  = Mockery::mock(TaxonomiesProvider::class);
        $tax->shouldReceive('getSets')->andReturn([])->byDefault();
        $tax->shouldReceive('getEntries')->andReturn([])->byDefault();

        $auth = Mockery::mock(AuthorsProvider::class);
        $auth->shouldReceive('getSets')->andReturn([])->byDefault();
        $auth->shouldReceive('getEntries')->andReturn([])->byDefault();

        Functions\when('apply_filters')->alias(static fn (string $h, mixed $v): mixed => 1000);

        $builder = new IndexBuilder($posts, $tax, $auth);
        $xml     = $builder->buildEntriesXml('post', 1);

        $this->assertStringNotContainsString('<image:image>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/no-image/</loc>', $xml);
    }

    public function test_has_set_true_only_for_populated_sets(): void {
        $builder = $this->makeBuilderWithCounts([
            'post'         => 2500,
            'page'         => 0,
            'tax_category' => 5,
        ], 1000);

        $this->assertTrue($builder->hasSet('post'));
        $this->assertTrue($builder->hasSet('category'));
        $this->assertFalse($builder->hasSet('page'), 'Empty sets must not count as existing');
        $this->assertFalse($builder->hasSet('nonexistent'));
    }

    public function test_get_set_page_count_math(): void {
        $builder = $this->makeBuilderWithCounts([
            'post' => 2500,
        ], 1000);

        $this->assertSame(3, $builder->getSetPageCount('post'));
        $this->assertSame(0, $builder->getSetPageCount('page'));
        $this->assertSame(0, $builder->getSetPageCount('nonexistent'));
    }
}
