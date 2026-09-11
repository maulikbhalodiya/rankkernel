<?php
/**
 * HowTo block tests, block.json values, render, block fed schema, registration.
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
use RankKernel\Modules\Schema\blocks\HowtoBlock;
use RankKernel\Modules\Schema\Pieces\HowtoPiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

final class HowtoBlockTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var string */
    private string $postContent = '';

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->postContent = '';

        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('esc_url_raw')->alias(static fn (string $v): string => trim($v));
        Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_html__')->alias(static fn (string $v, string $d = ''): string => $v);
        Functions\when('esc_attr')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_url')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: $v);
        Functions\when('wp_kses_post')->alias(static fn (string $v): string => trim(strip_tags($v, '<p><a><br><b><i><strong><em>')));
        Functions\when('absint')->alias(static fn (mixed $v): int => abs((int) $v));
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
        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_post_field')->alias(
            function (string $field, int $id): string {
                if ('post_content' === $field) {
                    return $this->postContent;
                }

                return '';
            }
        );
        Functions\when('get_post_type')->justReturn('post');
        Functions\when('get_the_date')->justReturn('2026-01-01T00:00:00+00:00');
        Functions\when('get_the_modified_date')->justReturn('2026-02-01T00:00:00+00:00');
        Functions\when('parse_blocks')->justReturn([]);
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function blockDir(): string {
        return dirname(__DIR__, 2) . '/src/Modules/Schema/blocks/howto';
    }

    public function test_block_json_has_binding_values(): void {
        $path = $this->blockDir() . '/block.json';
        $raw  = file_get_contents($path);

        $this->assertIsString($raw);

        $json = json_decode((string) $raw, true);

        $this->assertIsArray($json);
        $this->assertSame('rankkernel/howto', $json['name']);
        $this->assertSame('HowTo by RankKernel', $json['title']);
        $this->assertSame('rankkernel', $json['category']);
        $this->assertSame([], $json['attributes']['steps']['default']);
        $this->assertArrayNotHasKey('editorScript', $json);
        $this->assertArrayNotHasKey('editorStyle', $json);
    }

    public function test_register_block_registers_editor_assets_with_dependencies(): void {
        $registeredScripts = [];
        $registeredStyles  = [];

        Functions\when('wp_register_script')->alias(
            static function (string $handle, string $src, array $deps, mixed $ver, bool $footer) use (&$registeredScripts): void {
                $registeredScripts[ $handle ] = $deps;
            }
        );
        Functions\when('wp_register_style')->alias(
            static function (string $handle, string $src, array $deps, mixed $ver) use (&$registeredStyles): void {
                $registeredStyles[ $handle ] = $deps;
            }
        );
        Functions\when('plugins_url')->alias(static fn (string $p, string $f = ''): string => 'https://example.com/wp-content/plugins/rankkernel/' . $p);
        Functions\when('register_block_type')->alias(
            static function (mixed $name, mixed $args = []): mixed {
                return true;
            }
        );

        ( new HowtoBlock() )->registerBlock();

        $this->assertSame(
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
            $registeredScripts['rankkernel-howto-editor']
        );
        $this->assertArrayHasKey('rankkernel-howto-editor', $registeredStyles);
    }

    public function test_render_numbers_each_step(): void {
        $html = ( new HowtoBlock() )->render(
            [
                'title'        => 'Do it',
                'titleWrapper' => 'h2',
                'steps'        => [
                    [ 'title' => 'First', 'text' => 'One.', 'image' => '' ],
                    [ 'title' => 'Second', 'text' => 'Two.', 'image' => 'https://example.com/s.jpg' ],
                ],
            ]
        );

        $this->assertStringContainsString('<span class="rankkernel-howto-number">1. </span>First', $html);
        $this->assertStringContainsString('<span class="rankkernel-howto-number">2. </span>Second', $html);
        $this->assertStringContainsString('<img class="rankkernel-howto-step-image" src="https://example.com/s.jpg"', $html);
        $this->assertStringContainsString('<ol class="rankkernel-howto-list" style="list-style-type:decimal;">', $html);
    }

    public function test_render_escapes_markup_and_skips_empty_rows(): void {
        $html = ( new HowtoBlock() )->render(
            [
                'title'        => '<b>Hi</b>',
                'titleWrapper' => 'script',
                'steps'        => [
                    [ 'title' => '', 'text' => '', 'image' => '' ],
                    'junk',
                    [ 'title' => '<script>alert(1)</script>', 'text' => 'A.' ],
                ],
            ]
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;b&gt;Hi&lt;/b&gt;', $html);
        $this->assertStringContainsString('<h3 class="rankkernel-howto-title">', $html);
        $this->assertSame(1, substr_count($html, '<li class="rankkernel-howto-item">'));
    }

    public function test_render_empty_steps_returns_empty_string(): void {
        $this->assertSame('', ( new HowtoBlock() )->render([ 'steps' => [] ]));
        $this->assertSame('', ( new HowtoBlock() )->render([]));
    }

    /**
     * Build a singular query mock.
     */
    private function singularQuery( int $id = 1 ): WP_Query {
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
        $query->shouldReceive('get_queried_object_id')->andReturn($id)->byDefault();
        $query->shouldReceive('get')->andReturn(0)->byDefault();

        return $query;
    }

    private function makeContext( WP_Query $query ): Context {
        return new Context($query, new SettingsStore());
    }

    public function test_block_only_steps_are_picked_up(): void {
        $this->postContent = 'has blocks';

        Functions\when('parse_blocks')->alias(
            static function (string $content): array {
                return [
                    [ 'blockName' => 'core/paragraph', 'attrs' => [] ],
                    [
                        'blockName' => 'rankkernel/howto',
                        'attrs'     => [
                            'steps' => [
                                [ 'title' => 'Block step', 'text' => 'Block text.', 'image' => '' ],
                            ],
                        ],
                    ],
                ];
            }
        );

        $ctx   = $this->makeContext($this->singularQuery());
        $piece = new \RankKernel\Modules\Schema\Pieces\HowtoPiece();
        $build = $piece->build($ctx);

        $this->assertSame('HowTo', $build['@type']);
        $this->assertSame('Block step', $build['step'][0]['name']);
    }
}
