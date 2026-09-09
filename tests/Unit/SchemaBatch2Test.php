<?php
/**
 * Schema batch 2 tests, payload contract plus FAQPage and HowToPage pieces.
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
use RankKernel\Modules\Metadata\MetaPayload;
use RankKernel\Modules\Schema\Pieces\FaqPiece;
use RankKernel\Modules\Schema\Pieces\HowtoPiece;
use RankKernel\Modules\Schema\SchemaModule;
use RankKernel\Settings\SettingsStore;
use WP_Query;

final class SchemaBatch2Test extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('esc_url_raw')->alias(static fn (string $v): string => trim($v));
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
        Functions\when('get_the_excerpt')->justReturn('Excerpt text');
        Functions\when('get_post_field')->justReturn('');
        Functions\when('get_post_type')->justReturn('post');
        Functions\when('get_the_date')->justReturn('2026-01-01T00:00:00+00:00');
        Functions\when('get_the_modified_date')->justReturn('2026-02-01T00:00:00+00:00');
        Functions\when('get_author_posts_url')->justReturn('https://example.com/author/bob/');
        Functions\when('get_the_author_meta')->justReturn('Bob');
        Functions\when('get_the_author')->justReturn('Bob');
        Functions\when('get_the_category')->justReturn([]);
        Functions\when('date_i18n')->justReturn('Jan 1, 2026');
        Functions\when('single_term_title')->justReturn('');
        Functions\when('get_term_link')->justReturn('https://example.com/cat/news/');
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

    /**
     * Build a query mock for a scenario.
     *
     * @param array<string, mixed> $flags Method => return value overrides.
     */
    private function makeQuery( array $flags = [], int $id = 1 ): WP_Query {
        $defaults = [
            'is_singular'           => false,
            'is_search'             => false,
            'is_404'                => false,
            'is_feed'               => false,
            'is_preview'            => false,
            'is_category'           => false,
            'is_tag'                => false,
            'is_tax'                => false,
            'is_home'               => false,
            'is_front_page'         => false,
            'is_archive'            => false,
            'is_author'             => false,
            'is_date'               => false,
            'is_post_type_archive'  => false,
            'get_queried_object_id' => $id,
            'get'                   => 0,
        ];

        $query = Mockery::mock(WP_Query::class);

        foreach (array_merge($defaults, $flags) as $method => $value) {
            $query->shouldReceive($method)->andReturn($value)->byDefault();
        }

        return $query;
    }

    private function makeContext( WP_Query $query ): Context {
        return new Context($query, new SettingsStore());
    }

    private function singularQuery( int $id = 1 ): WP_Query {
        return $this->makeQuery([ 'is_singular' => true ], $id);
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

    public function test_sanitize_schema_empty_stays_empty_list(): void {
        $this->assertSame([], MetaPayload::sanitize([])['schema']);
        $this->assertSame([], MetaPayload::sanitize([ 'schema' => [] ])['schema']);
        $this->assertSame([], MetaPayload::sanitize([ 'schema' => null ])['schema']);
        $this->assertSame([], MetaPayload::sanitize([ 'schema' => 'junk' ])['schema']);
    }

    public function test_sanitize_schema_unknown_type_falls_back_and_never_emits_raw(): void {
        $clean = MetaPayload::sanitize([ 'schema' => [ 'type' => 'EvilType' ] ]);

        $this->assertSame('Article', $clean['schema']['type']);
        $this->assertStringNotContainsString('EvilType', (string) json_encode($clean['schema']));
    }

    public function test_sanitize_schema_known_type_passes_through(): void {
        $clean = MetaPayload::sanitize([ 'schema' => [ 'type' => 'FAQPage' ] ]);

        $this->assertSame('FAQPage', $clean['schema']['type']);
    }

    public function test_sanitize_schema_fields_caps_and_casts(): void {
        $fields = [
            'long'  => str_repeat('a', 2500),
            'array' => [ 'nested' ],
            'int'   => 42,
        ];

        for ($i = 0; $i < 60; ++$i) {
            $fields[ 'k' . $i ] = 'v' . $i;
        }

        $clean = MetaPayload::sanitize([ 'schema' => [ 'fields' => $fields ] ]);
        $kept  = $clean['schema']['fields'];

        $this->assertCount(50, $kept);
        $this->assertArrayNotHasKey('array', $kept);
        $this->assertSame('42', $kept['int']);
        $this->assertSame(2000, strlen($kept['long']));
    }

    public function test_sanitize_schema_faq_drops_empty_questions_and_caps_rows(): void {
        $rows = [
            [ 'question' => '', 'answer' => 'dropped' ],
            [ 'question' => '   ', 'answer' => 'dropped' ],
            [ 'question' => 'Kept?', 'answer' => 'Yes.' ],
        ];

        for ($i = 0; $i < 105; ++$i) {
            $rows[] = [ 'question' => 'Q' . $i, 'answer' => 'A' . $i ];
        }

        $clean     = MetaPayload::sanitize([ 'schema' => [ 'faq' => [ 'questions' => $rows ] ] ]);
        $questions = $clean['schema']['faq']['questions'];

        $this->assertCount(100, $questions);
        $this->assertSame('Kept?', $questions[0]['question']);
        $this->assertSame('Yes.', $questions[0]['answer']);
    }

    public function test_sanitize_schema_howto_drops_empty_rows_and_caps_steps(): void {
        $steps = [
            [ 'title' => '', 'text' => '', 'image' => 'https://example.com/empty.png' ],
            [ 'title' => 'Title only', 'text' => '', 'image' => '' ],
            [ 'title' => '', 'text' => 'Text only', 'image' => '' ],
        ];

        for ($i = 0; $i < 105; ++$i) {
            $steps[] = [ 'title' => 'T' . $i, 'text' => 'S' . $i, 'image' => '' ];
        }

        $clean = MetaPayload::sanitize(
            [
                'schema' => [
                    'howto' => [
                        'name'      => 'Guide',
                        'steps'     => $steps,
                        'totalTime' => 'PT30M',
                        'cost'      => 'Free',
                    ],
                ],
            ]
        );
        $howto = $clean['schema']['howto'];

        $this->assertSame('Guide', $howto['name']);
        $this->assertCount(100, $howto['steps']);
        $this->assertSame('Title only', $howto['steps'][0]['title']);
        $this->assertSame('Text only', $howto['steps'][1]['text']);
        $this->assertSame('PT30M', $howto['totalTime']);
        $this->assertSame('Free', $howto['cost']);
    }

    public function test_sanitize_schema_custom_objects_become_empty_array(): void {
        $clean = MetaPayload::sanitize([ 'schema' => [ 'custom' => new \stdClass() ] ]);

        $this->assertSame([], $clean['schema']['custom']);
    }

    public function test_sanitize_schema_custom_drops_resources_and_caps_depth(): void {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        $deep = [ 'x' => 'leaf' ];
        for ($i = 0; $i < 7; ++$i) {
            $deep = [ 'level' . $i => $deep ];
        }

        $clean = MetaPayload::sanitize(
            [
                'schema' => [
                    'custom' => [
                        'ok'       => 'yes',
                        'number'   => 7,
                        'flag'     => true,
                        'nothing'  => null,
                        'resource' => $resource,
                        'object'   => new \stdClass(),
                        'deep'     => $deep,
                    ],
                ],
            ]
        );
        $custom = $clean['schema']['custom'];

        fclose($resource);

        $this->assertSame('yes', $custom['ok']);
        $this->assertSame(7, $custom['number']);
        $this->assertTrue($custom['flag']);
        $this->assertNull($custom['nothing']);
        $this->assertArrayNotHasKey('resource', $custom);
        $this->assertArrayNotHasKey('object', $custom);
        $this->assertSame([], $custom['deep']['level6']['level5']['level4']['level3']);
    }

    public function test_sanitize_schema_custom_caps_total_keys(): void {
        $wide = [];
        for ($i = 0; $i < 250; ++$i) {
            $wide[ 'k' . $i ] = 'v';
        }

        $clean = MetaPayload::sanitize([ 'schema' => [ 'custom' => $wide ] ]);

        $this->assertCount(200, $clean['schema']['custom']);
    }

    public function test_rest_schema_describes_object_shape(): void {
        $schema = MetaPayload::restSchema()['properties']['schema'];

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('type', $schema['properties']);
        $this->assertArrayHasKey('fields', $schema['properties']);
        $this->assertArrayHasKey('faq', $schema['properties']);
        $this->assertArrayHasKey('howto', $schema['properties']);
        $this->assertArrayHasKey('custom', $schema['properties']);
        $this->assertArrayHasKey('questions', $schema['properties']['faq']['properties']);
        $this->assertArrayHasKey('steps', $schema['properties']['howto']['properties']);
    }

    public function test_faq_not_needed_without_questions_or_off_singular(): void {
        $piece = new FaqPiece();

        $emptyCtx = $this->makeContext($this->singularQuery());
        $this->assertFalse($piece->isNeeded($emptyCtx));

        $this->stubPostMeta([ 'schema' => [ 'faq' => [ 'questions' => [] ] ] ]);
        $noQuestionsCtx = $this->makeContext($this->singularQuery());
        $this->assertFalse($piece->isNeeded($noQuestionsCtx));

        $this->stubPostMeta(
            [ 'schema' => [ 'faq' => [ 'questions' => [ [ 'question' => 'Q?', 'answer' => 'A.' ] ] ] ] ]
        );
        $homeCtx = $this->makeContext($this->makeQuery([ 'is_home' => true ]));
        $this->assertFalse($piece->isNeeded($homeCtx));
    }

    public function test_faq_builds_exact_page_with_two_questions(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'faq' => [
                        'questions' => [
                            [ 'question' => 'What is RankKernel?', 'answer' => 'An SEO engine.' ],
                            [ 'question' => 'Is it free?', 'answer' => 'Yes.' ],
                        ],
                    ],
                ],
            ]
        );

        $ctx   = $this->makeContext($this->singularQuery());
        $piece = new FaqPiece();

        $this->assertTrue($piece->isNeeded($ctx));
        $this->assertSame(
            [
                '@type'      => 'FAQPage',
                '@id'        => 'https://example.com/hello/#faq',
                'mainEntity' => [
                    [
                        '@type'          => 'Question',
                        'name'           => 'What is RankKernel?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'An SEO engine.' ],
                    ],
                    [
                        '@type'          => 'Question',
                        'name'           => 'Is it free?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Yes.' ],
                    ],
                ],
            ],
            $piece->build($ctx)
        );
    }

    public function test_faq_drops_empty_question_rows(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'faq' => [
                        'questions' => [
                            [ 'question' => '', 'answer' => 'dropped' ],
                            [ 'question' => 'Kept?', 'answer' => 'Yes.' ],
                        ],
                    ],
                ],
            ]
        );

        $build = (new FaqPiece())->build($this->makeContext($this->singularQuery()));

        $this->assertCount(1, $build['mainEntity']);
        $this->assertSame('Kept?', $build['mainEntity'][0]['name']);
    }

    public function test_howto_not_needed_without_steps_or_off_singular(): void {
        $piece = new HowtoPiece();

        $emptyCtx = $this->makeContext($this->singularQuery());
        $this->assertFalse($piece->isNeeded($emptyCtx));

        $this->stubPostMeta([ 'schema' => [ 'howto' => [ 'steps' => [] ] ] ]);
        $noStepsCtx = $this->makeContext($this->singularQuery());
        $this->assertFalse($piece->isNeeded($noStepsCtx));

        $this->stubPostMeta(
            [ 'schema' => [ 'howto' => [ 'steps' => [ [ 'title' => 'T', 'text' => 'S', 'image' => '' ] ] ] ] ]
        );
        $homeCtx = $this->makeContext($this->makeQuery([ 'is_home' => true ]));
        $this->assertFalse($piece->isNeeded($homeCtx));
    }

    public function test_howto_builds_steps_with_image_only_when_set(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'howto' => [
                        'name'      => 'Bake bread',
                        'steps'     => [
                            [ 'title' => 'Mix', 'text' => 'Mix flour.', 'image' => 'https://example.com/mix.png' ],
                            [ 'title' => 'Bake', 'text' => 'Bake hot.', 'image' => '' ],
                        ],
                        'totalTime' => 'PT1H',
                        'cost'      => '5 USD',
                    ],
                ],
            ]
        );

        $ctx   = $this->makeContext($this->singularQuery());
        $piece = new HowtoPiece();

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('HowTo', $build['@type']);
        $this->assertSame('https://example.com/hello/#howto', $build['@id']);
        $this->assertSame('Bake bread', $build['name']);
        $this->assertCount(2, $build['step']);
        $this->assertSame('HowToStep', $build['step'][0]['@type']);
        $this->assertSame('Mix', $build['step'][0]['name']);
        $this->assertSame('Mix flour.', $build['step'][0]['text']);
        $this->assertSame('https://example.com/mix.png', $build['step'][0]['image']);
        $this->assertArrayNotHasKey('image', $build['step'][1]);
        $this->assertSame('PT1H', $build['totalTime']);
        $this->assertSame('5 USD', $build['estimatedCost']);
    }

    public function test_howto_omits_empty_totaltime_and_cost(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'howto' => [
                        'name'  => 'Guide',
                        'steps' => [ [ 'title' => 'T', 'text' => 'S', 'image' => '' ] ],
                    ],
                ],
            ]
        );

        $build = (new HowtoPiece())->build($this->makeContext($this->singularQuery()));

        $this->assertArrayNotHasKey('totalTime', $build);
        $this->assertArrayNotHasKey('estimatedCost', $build);
    }

    public function test_howto_name_falls_back_to_post_title(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'howto' => [
                        'steps' => [ [ 'title' => 'T', 'text' => 'S', 'image' => '' ] ],
                    ],
                ],
            ]
        );

        $build = (new HowtoPiece())->build($this->makeContext($this->singularQuery()));

        $this->assertSame('Hello Post', $build['name']);
    }

    public function test_answer_and_step_text_neutralize_script_tags(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'faq'   => [
                        'questions' => [
                            [ 'question' => 'Safe?', 'answer' => '<script>alert(1)</script>Hi' ],
                        ],
                    ],
                    'howto' => [
                        'name'  => 'Guide',
                        'steps' => [
                            [ 'title' => 'T', 'text' => '<script>alert(2)</script>Do this.', 'image' => '' ],
                        ],
                    ],
                ],
            ]
        );

        $ctx       = $this->makeContext($this->singularQuery());
        $faqBuild  = (new FaqPiece())->build($ctx);
        $howtoBuild = (new HowtoPiece())->build($ctx);

        $this->assertStringNotContainsString('<script>', $faqBuild['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertStringContainsString('Hi', $faqBuild['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertStringNotContainsString('<script>', $howtoBuild['step'][0]['text']);
        $this->assertStringContainsString('Do this.', $howtoBuild['step'][0]['text']);
    }

    public function test_generator_wiring_includes_faq_and_howto_pieces(): void {
        $this->stubPostMeta(
            [
                'schema' => [
                    'faq'   => [
                        'questions' => [ [ 'question' => 'Q?', 'answer' => 'A.' ] ],
                    ],
                    'howto' => [
                        'name'  => 'Guide',
                        'steps' => [ [ 'title' => 'T', 'text' => 'S', 'image' => '' ] ],
                    ],
                ],
            ]
        );

        $ctx    = $this->makeContext($this->singularQuery());
        $module = new SchemaModule(null, null, null, $ctx);
        $graph  = $module->getGenerator()->generate($ctx)['@graph'];
        $types  = array_column($graph, '@type');

        $this->assertContains('FAQPage', $types);
        $this->assertContains('HowTo', $types);
    }
}
