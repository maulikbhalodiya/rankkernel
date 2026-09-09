<?php
/**
 * SchemaModule tests, script output, boot wiring, empty graph.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\SchemaModule;
use RankKernel\Settings\SettingsStore;
use WP_Query;

final class SchemaModuleTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('esc_url_raw')->alias(static fn (string $v): string => trim($v));
        Functions\when('wp_strip_all_tags')->alias(static fn (string $s): string => strip_tags($s));
        Functions\when('is_preview')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('is_front_page')->justReturn(false);
        Functions\when('is_author')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('get_option')->alias(
            static function (string $key, mixed $default = false) {
                if ('rankkernel_settings' === $key) {
                    return [];
                }

                return $default;
            }
        );
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('get_term_meta')->justReturn([]);
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
        Functions\when('home_url')->alias(static fn (string $path = '/'): string => 'https://example.com' . $path);
        Functions\when('trailingslashit')->alias(static fn (string $v): string => rtrim($v, '/') . '/');
        Functions\when('get_bloginfo')->justReturn('My Site');
        Functions\when('get_locale')->justReturn('en_US');
        Functions\when('get_permalink')->justReturn('https://example.com/hello/');
        Functions\when('get_the_title')->justReturn('Hello Post');
        Functions\when('get_the_excerpt')->justReturn('Excerpt text');
        Functions\when('get_post_field')->justReturn('');
        Functions\when('get_post_type')->justReturn('post');
        Functions\when('get_the_date')->justReturn('2026-01-01T00:00:00+00:00');
        Functions\when('get_the_modified_date')->justReturn('2026-02-01T00:00:00+00:00');
        Functions\when('get_author_posts_url')->justReturn('');
        Functions\when('get_the_author_meta')->justReturn('');
        Functions\when('get_the_author')->justReturn('');
        Functions\when('get_the_category')->justReturn([]);
        Functions\when('date_i18n')->justReturn('Jan 1, 2026');
        Functions\when('single_term_title')->justReturn('');
        Functions\when('get_term_link')->justReturn('');
        Functions\when('wp_get_attachment_image_url')->justReturn('');
        Functions\when('wp_get_attachment_url')->justReturn('');
        Functions\when('get_post_thumbnail_id')->justReturn(0);
        Functions\when('single_post_title')->justReturn('');
        Functions\when('wp_json_encode')->alias(static fn (mixed $v, int $o = 0): string => (string) json_encode($v, $o));
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function makeSingularContext(): Context {
        $query = Mockery::mock(WP_Query::class);
        $query->shouldReceive('is_singular')->andReturn(true)->byDefault();
        $query->shouldReceive('is_search')->andReturn(false)->byDefault();
        $query->shouldReceive('is_404')->andReturn(false)->byDefault();
        $query->shouldReceive('is_feed')->andReturn(false)->byDefault();
        $query->shouldReceive('is_preview')->andReturn(false)->byDefault();
        $query->shouldReceive('is_category')->andReturn(false)->byDefault();
        $query->shouldReceive('is_tag')->andReturn(false)->byDefault();
        $query->shouldReceive('is_tax')->andReturn(false)->byDefault();
        $query->shouldReceive('is_home')->andReturn(false)->byDefault();
        $query->shouldReceive('is_front_page')->andReturn(false)->byDefault();
        $query->shouldReceive('is_archive')->andReturn(false)->byDefault();
        $query->shouldReceive('get_queried_object_id')->andReturn(1)->byDefault();
        $query->shouldReceive('get')->andReturn(0)->byDefault();

        return new Context($query, new SettingsStore());
    }

    public function test_module_identity(): void {
        $module = new SchemaModule();

        $this->assertSame('schema', $module->getId());
        $this->assertSame('Schema', $module->getName());
        $this->assertSame(30, $module->getPriority());
        $this->assertSame([ 'metadata' ], $module->dependsOn());
    }

    public function test_boot_hooks_after_tags(): void {
        Functions\expect('add_action')
            ->once()
            ->with('rankkernel/head/after_tags', \Mockery::type('array'), 10);

        (new SchemaModule())->boot();
    }

    public function test_render_emits_one_script_tag_with_valid_json(): void {
        $ctx    = $this->makeSingularContext();
        $module = new SchemaModule(null, null, null, $ctx);

        ob_start();
        $module->render($ctx);
        $out = (string) ob_get_clean();

        $this->assertSame(1, substr_count($out, '<script type="application/ld+json">'));
        $this->assertSame(1, substr_count($out, '</script>'));

        $json = preg_replace('/^.*<script type="application\/ld\+json">(.*)<\/script>.*$/s', '$1', $out);
        $this->assertIsString($json);

        $decoded = json_decode(trim($json), true);

        $this->assertIsArray($decoded);
        $this->assertSame('https://schema.org', $decoded['@context']);
        $this->assertNotEmpty($decoded['@graph']);

        $types = array_column($decoded['@graph'], '@type');

        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('WebPage', $types);
    }

    public function test_render_uses_passed_context_single_build(): void {
        $ctx    = $this->makeSingularContext();
        $module = new SchemaModule(null, null, null, $ctx);

        // No get_option call for modules and no new Context build path is
        // observable here, the passed context must simply be honored.
        ob_start();
        $module->render($ctx);
        $first = (string) ob_get_clean();

        ob_start();
        $module->render($ctx);
        $second = (string) ob_get_clean();

        $this->assertSame($first, $second);
        $this->assertStringContainsString('#organization', $first);
    }

    public function test_render_emits_nothing_for_empty_graph(): void {
        $ctx    = $this->makeSingularContext();
        $module = new SchemaModule(null, null, null, $ctx);

        Functions\when('apply_filters')->alias(
            static function (string $hook, mixed $value): mixed {
                if ('rankkernel/schema/graph' === $hook) {
                    return [];
                }

                if (str_starts_with($hook, 'rankkernel/schema/needs_')) {
                    return false;
                }

                return $value;
            }
        );

        ob_start();
        $module->render($ctx);
        $out = (string) ob_get_clean();

        $this->assertSame('', $out);
    }
}
