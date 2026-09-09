<?php
/**
 * Router plain permalink and sitemap.xml redirect tests.
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
use RankKernel\Modules\Sitemaps\Router;
use RankKernel\Modules\Sitemaps\SitemapCache;
use RankKernel\Modules\Sitemaps\XslStylesheet;
use WP_Query;

final class RouterPlainModeTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        if (! defined('RANKKERNEL_TESTING')) {
            define('RANKKERNEL_TESTING', true);
        }

        Functions\when('esc_url')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: $v);
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
        Functions\when('home_url')->alias(static fn (string $p = ''): string => 'https://example.com' . $p);
        Functions\when('remove_all_actions')->justReturn(true);
        Functions\when('nocache_headers')->justReturn(null);
        Functions\when('add_query_arg')->alias(static function (mixed $key = '', mixed $value = '', string $url = ''): string {
            if (is_array($key)) {
                $pairs = $key;
                $url   = is_string($value) ? $value : '';
            } else {
                $pairs = [ (string) $key => $value ];
            }

            $sep = str_contains($url, '?') ? '&' : '?';

            return $url . $sep . http_build_query($pairs);
        });
    }

    protected function tearDown(): void {
        unset($GLOBALS['wp']);
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function makeRouter(): Router {
        $builder = Mockery::mock(IndexBuilder::class);
        $cache   = Mockery::mock(SitemapCache::class);
        $xsl     = Mockery::mock(XslStylesheet::class);

        return new Router($builder, $cache, $xsl);
    }

    private function stubOptions(string $permalinkStructure): void {
        Functions\when('get_option')->alias(
            static function (string $key, mixed $default = false) use ($permalinkStructure): mixed {
                if ('permalink_structure' === $key) {
                    return $permalinkStructure;
                }

                return $default;
            }
        );
    }

    public function test_pretty_url_forms(): void {
        $this->stubOptions('/%postname%/');

        $router = $this->makeRouter();

        $this->assertSame('https://example.com/sitemap_index.xml', Router::indexUrl());
        $this->assertSame('https://example.com/post-sitemap.xml', Router::sitemapUrl('post', 1));
        $this->assertSame('https://example.com/post-sitemap2.xml', Router::sitemapUrl('post', 2));
        $this->assertSame('https://example.com/sitemap.xsl', Router::xslUrl());
    }

    public function test_plain_url_forms(): void {
        $this->stubOptions('');

        $router = $this->makeRouter();

        $this->assertSame('https://example.com/?rankkernel_sitemap=index', Router::indexUrl());
        $this->assertSame('https://example.com/?rankkernel_sitemap=post', Router::sitemapUrl('post', 1));
        $this->assertStringNotContainsString('sitemap_n', Router::sitemapUrl('post', 1));
        $this->assertSame('https://example.com/?rankkernel_sitemap=post&rankkernel_sitemap_n=2', Router::sitemapUrl('post', 2));
        $this->assertSame(
            'https://example.com/?rankkernel_sitemap_xsl=1',
            Router::xslUrl()
        );
    }

    public function test_query_vars_are_prefixed_only(): void {
        $router = $this->makeRouter();

        $vars = $router->addQueryVars([ 'p' ]);

        $this->assertContains('rankkernel_sitemap', $vars);
        $this->assertContains('rankkernel_sitemap_n', $vars);
        $this->assertContains('rankkernel_sitemap_xsl', $vars);
        $this->assertNotContains('sitemap', $vars);
        $this->assertNotContains('sitemap_n', $vars);
    }

    public function test_intercept_serves_plain_set_var(): void {
        $this->stubOptions('');

        $builder = Mockery::mock(IndexBuilder::class);
        $builder->shouldReceive('buildEntriesXml')->once()->with('blog', 1)->andReturn('<urlset>plain blog</urlset>');

        $cache = Mockery::mock(SitemapCache::class);
        $cache->shouldReceive('getMap')->once()->andReturn([ 'blog' => 1 ]);
        $cache->shouldReceive('get')->once()->andReturnUsing(
            static function (string $set, int $page, callable $cb): string {
                return (string) $cb();
            }
        );

        $xsl = Mockery::mock(XslStylesheet::class);

        $router = new Router($builder, $cache, $xsl);

        Functions\when('get_query_var')->alias(
            static function (string $key, mixed $default = ''): mixed {
                if ('rankkernel_sitemap' === $key) {
                    return 'blog';
                }

                return $default;
            }
        );

        $query = Mockery::mock(WP_Query::class);

        ob_start();
        $router->intercept($query);
        $out = (string) ob_get_clean();

        $this->assertStringContainsString('<urlset>plain blog</urlset>', $out);
    }

    public function test_intercept_serves_plain_index_var(): void {
        $this->stubOptions('');

        $builder = Mockery::mock(IndexBuilder::class);
        $builder->shouldReceive('buildIndexXml')->once()->andReturn('<sitemapindex>plain</sitemapindex>');

        $cache = Mockery::mock(SitemapCache::class);
        $cache->shouldReceive('get')->once()->andReturnUsing(
            static function (string $set, int $page, callable $cb): string {
                return (string) $cb();
            }
        );

        $xsl = Mockery::mock(XslStylesheet::class);

        $router = new Router($builder, $cache, $xsl);

        Functions\when('get_query_var')->alias(
            static function (string $key, mixed $default = ''): mixed {
                if ('rankkernel_sitemap' === $key) {
                    return 'index';
                }

                return $default;
            }
        );

        $query = Mockery::mock(WP_Query::class);

        ob_start();
        $router->intercept($query);
        $out = (string) ob_get_clean();

        $this->assertStringContainsString('<sitemapindex>plain</sitemapindex>', $out);
    }

    public function test_canonical_disabled_for_plain_vars(): void {
        $this->stubOptions('');

        $router = $this->makeRouter();

        Functions\when('get_query_var')->alias(
            static function (string $key, mixed $default = ''): mixed {
                if ('rankkernel_sitemap' === $key) {
                    return 'post';
                }

                return $default;
            }
        );

        $this->assertFalse($router->disableCanonical('https://example.com/?rankkernel_sitemap=post'));
    }

    public function test_sitemap_xml_redirects_to_index(): void {
        $this->stubOptions('/%postname%/');

        $router = $this->makeRouter();

        Functions\when('get_query_var')->alias(static fn (string $k, mixed $d = ''): mixed => $d);
        Functions\expect('wp_safe_redirect')->once()->with('https://example.com/sitemap_index.xml', 301);

        $GLOBALS['wp']          = new \stdClass();
        $GLOBALS['wp']->request = 'sitemap.xml';

        $query = Mockery::mock(WP_Query::class);

        ob_start();
        $router->intercept($query);
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function test_normal_request_untouched(): void {
        $this->stubOptions('/%postname%/');

        $builder = Mockery::mock(IndexBuilder::class);
        $builder->shouldReceive('buildIndexXml')->never();
        $builder->shouldReceive('buildEntriesXml')->never();

        $cache = Mockery::mock(SitemapCache::class);
        $cache->shouldReceive('get')->never();
        $cache->shouldReceive('getMap')->never();

        $xsl = Mockery::mock(XslStylesheet::class);
        $xsl->shouldReceive('output')->never();

        $router = new Router($builder, $cache, $xsl);

        Functions\when('get_query_var')->alias(static fn (string $k, mixed $d = ''): mixed => $d);
        Functions\expect('wp_safe_redirect')->never();

        $GLOBALS['wp']          = new \stdClass();
        $GLOBALS['wp']->request = 'hello-world';

        $query = Mockery::mock(WP_Query::class);

        ob_start();
        $router->intercept($query);
        $out = (string) ob_get_clean();

        $this->assertSame('', $out);
    }
}
