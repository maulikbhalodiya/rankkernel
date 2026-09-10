<?php
/**
 * FAQ block tests, block.json values, render, block fed schema, registration.
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
use RankKernel\Modules\Schema\blocks\FaqBlock;
use RankKernel\Modules\Schema\Pieces\FaqPiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

final class FaqBlockTest extends TestCase {
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
        Functions\when('esc_attr')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('wp_kses_post')->alias(static fn (string $v): string => trim(strip_tags($v, '<p><a><br><b><i><strong><em>')));
        Functions\when('wp_strip_all_tags')->alias(static fn (string $s): string => strip_tags($s));
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
        Functions\when('get_author_posts_url')->justReturn('https://example.com/author/bob/');
        Functions\when('get_the_author_meta')->justReturn('Bob');
        Functions\when('parse_blocks')->justReturn([]);
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function blockDir(): string {
        return dirname(__DIR__, 2) . '/src/Modules/Schema/blocks/faq';
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

    /**
     * @param array<string, mixed> $meta Raw post meta payload.
     */
    private function stubPostMeta( array $meta ): void {
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($meta): mixed {
                if ('_rankkernel_meta_data' === $key) {
                    return $meta;
                }

                return [];
            }
        );
    }

    /**
     * @param array<int, mixed> $blocks Parsed blocks to return.
     */
    private function stubBlocks( array $blocks ): void {
        Functions\when('parse_blocks')->alias(
            static function (string $content) use ($blocks): array {
                return $blocks;
            }
        );
    }

    public function test_block_json_has_binding_values(): void {
        $path = $this->blockDir() . '/block.json';
        $raw  = file_get_contents($path);

        $this->assertIsString($raw);

        $json = json_decode((string) $raw, true);

        $this->assertIsArray($json);
        $this->assertSame('rankkernel/faq', $json['name']);
        $this->assertSame('FAQ by RankKernel', $json['title']);
        $this->assertSame(
            'Add SEO friendly frequently asked questions with automatic FAQPage schema.',
            $json['description']
        );
        $this->assertSame('rankkernel', $json['category']);
        $this->assertSame('editor-ul', $json['icon']);
        $this->assertSame('rankkernel', $json['textdomain']);
        $this->assertSame(
            [ 'FAQ', 'Frequently Asked Questions', 'Schema', 'Structured Data' ],
            $json['keywords']
        );
        $this->assertSame('file:./faq-editor.js', $json['editorScript']);
        $this->assertSame(3, $json['apiVersion']);
        $this->assertSame('', $json['attributes']['title']['default']);
        $this->assertSame('h3', $json['attributes']['titleWrapper']['default']);
        $this->assertSame('ul', $json['attributes']['listStyle']['default']);
        $this->assertSame([], $json['attributes']['questions']['default']);
    }

    public function test_render_builds_list_with_title_per_wrapper(): void {
        $html = ( new FaqBlock() )->render(
            [
                'title'        => 'Common questions',
                'titleWrapper' => 'h2',
                'listStyle'    => 'ul',
                'questions'    => [
                    [ 'question' => 'What?', 'answer' => 'This.' ],
                    [ 'question' => 'Why?', 'answer' => 'Because.' ],
                ],
            ]
        );

        $this->assertStringContainsString('<div class="rankkernel-faq">', $html);
        $this->assertStringContainsString(
            '<h2 class="rankkernel-faq-title">Common questions</h2>',
            $html
        );
        $this->assertStringContainsString('<ul class="rankkernel-faq-list" style="list-style-type:disc;">', $html);
        $this->assertSame(2, substr_count($html, '<li class="rankkernel-faq-item">'));
        $this->assertStringContainsString(
            '<h2 class="rankkernel-faq-question">What?</h2>',
            $html
        );
        $this->assertStringContainsString(
            '<div class="rankkernel-faq-answer">This.</div>',
            $html
        );
    }

    public function test_render_ordered_list_and_h4_wrapper(): void {
        $html = ( new FaqBlock() )->render(
            [
                'titleWrapper' => 'h4',
                'listStyle'    => 'ol',
                'questions'    => [
                    [ 'question' => 'What?', 'answer' => 'This.' ],
                ],
            ]
        );

        $this->assertStringContainsString('<ol class="rankkernel-faq-list" style="list-style-type:decimal;">', $html);
        $this->assertStringContainsString(
            '<h4 class="rankkernel-faq-question">What?</h4>',
            $html
        );
        $this->assertStringNotContainsString('<ul', $html);
        $this->assertStringNotContainsString('rankkernel-faq-title', $html);
    }

    public function test_render_escapes_markup_and_skips_empty_rows(): void {
        $html = ( new FaqBlock() )->render(
            [
                'title'        => '<b>Hi</b>',
                'titleWrapper' => 'script',
                'listStyle'    => 'table',
                'questions'    => [
                    [ 'question' => '', 'answer' => '' ],
                    'junk',
                    [ 'question' => '<script>alert(1)</script>', 'answer' => 'A.' ],
                ],
            ]
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Hi&lt;/b&gt;', $html);
        $this->assertStringContainsString('<h3 class="rankkernel-faq-title">', $html);
        $this->assertStringContainsString('<ul class="rankkernel-faq-list" style="list-style-type:disc;">', $html);
        $this->assertSame(1, substr_count($html, '<li class="rankkernel-faq-item">'));
    }

    public function test_render_empty_questions_returns_empty_string(): void {
        $this->assertSame('', ( new FaqBlock() )->render([ 'questions' => [] ]));
        $this->assertSame('', ( new FaqBlock() )->render([]));
        $this->assertSame(
            '',
            ( new FaqBlock() )->render(
                [ 'questions' => [ [ 'question' => '', 'answer' => '' ] ] ]
            )
        );
    }

    public function test_block_only_questions_are_picked_up(): void {
        $this->stubPostMeta([]);
        $this->postContent = '<!-- wp:rankkernel/faq -->';
        $this->stubBlocks(
            [
                [
                    'blockName' => 'rankkernel/faq',
                    'attrs'     => [
                        'questions' => [
                            [ 'question' => 'Block Q?', 'answer' => 'Block A.' ],
                        ],
                    ],
                ],
            ]
        );

        $ctx   = $this->makeContext($this->singularQuery());
        $piece = new FaqPiece();

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('FAQPage', $build['@type']);
        $this->assertCount(1, $build['mainEntity']);
        $this->assertSame('Block Q?', $build['mainEntity'][0]['name']);
    }

    public function test_payload_and_block_rows_merge_with_dedupe(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'faq' => [
                        'questions' => [
                            [ 'question' => 'What?', 'answer' => 'Payload answer.' ],
                        ],
                    ],
                ],
            ]
        );
        $this->postContent = '<!-- wp:rankkernel/faq -->';
        $this->stubBlocks(
            [
                [
                    'blockName' => 'core/paragraph',
                    'attrs'     => [],
                ],
                [
                    'blockName' => 'rankkernel/faq',
                    'attrs'     => [
                        'questions' => [
                            [ 'question' => '  WHAT? ', 'answer' => 'Duplicate answer.' ],
                            [ 'question' => 'Other?', 'answer' => 'Block answer.' ],
                        ],
                    ],
                ],
            ]
        );

        $build = ( new FaqPiece() )->build($this->makeContext($this->singularQuery()));

        $this->assertCount(2, $build['mainEntity']);
        $this->assertSame('What?', $build['mainEntity'][0]['name']);
        $this->assertSame('Payload answer.', $build['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertSame('Other?', $build['mainEntity'][1]['name']);
    }

    public function test_no_blocks_and_no_payload_is_not_needed(): void {
        $this->stubPostMeta([]);
        $this->postContent = '<p>Plain content.</p>';
        $this->stubBlocks(
            [
                [
                    'blockName' => 'core/paragraph',
                    'attrs'     => [],
                ],
            ]
        );

        $this->assertFalse(
            ( new FaqPiece() )->isNeeded($this->makeContext($this->singularQuery()))
        );
    }

    public function test_malformed_block_attrs_are_ignored(): void {
        $this->stubPostMeta([]);
        $this->postContent = '<!-- wp:rankkernel/faq -->';
        $this->stubBlocks(
            [
                [
                    'blockName' => 'rankkernel/faq',
                    'attrs'     => 'junk',
                ],
                [
                    'blockName' => 'rankkernel/faq',
                    'attrs'     => [ 'questions' => 'junk' ],
                ],
                [
                    'blockName' => 'rankkernel/faq',
                    'attrs'     => [ 'questions' => [ 'junk', [ 'question' => '', 'answer' => 'x' ] ] ],
                ],
            ]
        );

        $this->assertFalse(
            ( new FaqPiece() )->isNeeded($this->makeContext($this->singularQuery()))
        );
    }

    public function test_merged_rows_cap_at_100(): void {
        $this->stubPostMeta([]);
        $this->postContent = '<!-- wp:rankkernel/faq -->';

        $rows = [];

        for ($i = 0; $i < 120; ++$i) {
            $rows[] = [ 'question' => 'Question ' . $i . '?', 'answer' => 'Answer.' ];
        }

        $this->stubBlocks(
            [
                [
                    'blockName' => 'rankkernel/faq',
                    'attrs'     => [ 'questions' => $rows ],
                ],
            ]
        );

        $build = ( new FaqPiece() )->build($this->makeContext($this->singularQuery()));

        $this->assertCount(100, $build['mainEntity']);
    }

    public function test_add_category_appends_once(): void {
        $block = new FaqBlock();

        $cats = $block->addCategory([]);

        $this->assertCount(1, $cats);
        $this->assertSame('rankkernel', $cats[0]['slug']);
        $this->assertSame('RankKernel', $cats[0]['title']);

        $again = $block->addCategory($cats);

        $this->assertCount(1, $again);
    }
}
