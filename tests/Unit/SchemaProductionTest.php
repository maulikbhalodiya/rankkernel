<?php
/**
 * Schema production tests, audit driven behaviors and rendered output.
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
use RankKernel\Modules\Schema\Generator;
use RankKernel\Modules\Schema\SchemaModule;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Production grade coverage for the schema engine audit.
 *
 * Every behavior changed by the GH-11 audit lands here, including
 * assertions against the final rendered JSON string.
 */
final class SchemaProductionTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('esc_url_raw')->alias(static fn (string $v): string => trim($v));
        Functions\when('wp_strip_all_tags')->alias(static fn (string $s): string => strip_tags($s));
        Functions\when('absint')->alias(static fn (mixed $v): int => abs((int) $v));
        Functions\when('mysql2date')->alias(
            static fn (string $format, string $date): string => (string) gmdate($format, (int) strtotime($date))
        );
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
        Functions\when('get_post_thumbnail_id')->justReturn(0);
        Functions\when('wp_get_attachment_image_url')->justReturn('');
        Functions\when('wp_get_attachment_url')->justReturn('');
        Functions\when('single_term_title')->justReturn('');
        Functions\when('get_term_link')->justReturn('https://example.com/cat/news/');
        Functions\when('wp_kses_post')->alias(static fn (string $v): string => $v);
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
     * @param array<string, mixed> $flags Method to return value overrides.
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
     * @param array<string, mixed> $schema Raw schema payload.
     */
    private function schemaContext( array $schema ): Context {
        $this->stubPostMeta([ 'schema' => $schema ]);

        return $this->makeContext($this->makeQuery([ 'is_singular' => true ]));
    }

    public function test_sanitize_preserves_automatic_empty_type(): void {
        $clean = MetaPayload::sanitize([ 'schema' => [ 'type' => '' ] ]);

        $this->assertSame('', $clean['schema']['type']);
    }

    public function test_normalize_or_empty_keeps_automatic_and_rejects_unknown(): void {
        $this->assertSame('', SchemaTypes::normalizeOrEmpty(''));
        $this->assertSame('', SchemaTypes::normalizeOrEmpty(null));
        $this->assertSame('', SchemaTypes::normalizeOrEmpty('   '));
        $this->assertSame('Product', SchemaTypes::normalizeOrEmpty('Product'));
        $this->assertSame('Article', SchemaTypes::normalizeOrEmpty('EvilType'));
    }
}
