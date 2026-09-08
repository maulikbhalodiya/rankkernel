<?php
/**
 * MetaPayload tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\MetaPayload;

final class MetaPayloadTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Realistic sanitizers.
        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('esc_url_raw')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: '');
        Functions\when('absint')->alias(static fn (mixed $v): int => abs((int) $v));
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_sanitize_strips_script_from_title(): void {
        $payload = [ 'title' => '<script>alert(1)</script>Hello' ];
        $clean   = MetaPayload::sanitize($payload);

        $this->assertStringNotContainsString('<script>', $clean['title']);
        $this->assertSame('alert(1)Hello', $clean['title']);
    }

    public function test_sanitize_canonical_esc_url_raw(): void {
        $payload = [ 'canonical' => 'https://example.com/page?q=1' ];
        $clean   = MetaPayload::sanitize($payload);

        $this->assertSame('https://example.com/page?q=1', $clean['canonical']);
    }

    public function test_sanitize_image_id_absint(): void {
        $payload = [ 'og' => [ 'image_id' => '-42' ] ];
        $clean   = MetaPayload::sanitize($payload);

        $this->assertSame(42, $clean['og']['image_id']);
    }

    public function test_sanitize_drops_unknown_top_level_key(): void {
        $payload = [ 'evil' => 'x', 'title' => 'Hello' ];
        $clean   = MetaPayload::sanitize($payload);

        $this->assertArrayNotHasKey('evil', $clean);
        $this->assertSame('Hello', $clean['title']);
    }

    public function test_sanitize_fills_missing_keys_from_defaults(): void {
        $clean = MetaPayload::sanitize([]);

        $this->assertSame(MetaPayload::defaults(), $clean);
    }

    public function test_sanitize_robots_max_snippet_null_or_int(): void {
        $cleanNull = MetaPayload::sanitize([ 'robots' => [ 'max_snippet' => null ] ]);
        $this->assertNull($cleanNull['robots']['max_snippet']);

        $cleanInt = MetaPayload::sanitize([ 'robots' => [ 'max_snippet' => '120' ] ]);
        $this->assertSame(120, $cleanInt['robots']['max_snippet']);

        $cleanEmpty = MetaPayload::sanitize([ 'robots' => [ 'max_snippet' => '' ] ]);
        $this->assertNull($cleanEmpty['robots']['max_snippet']);
    }

    public function test_sanitize_schema_json_safe(): void {
        $payload = [
            'schema' => [
                [ 'type' => 'Article' ],
                'not-an-array',
                [ 'type' => 'Breadcrumb' ],
                123,
            ],
        ];
        $clean = MetaPayload::sanitize($payload);

        $this->assertCount(2, $clean['schema']);
        $this->assertSame([ 'type' => 'Article' ], $clean['schema'][0]);
        $this->assertSame([ 'type' => 'Breadcrumb' ], $clean['schema'][1]);
    }

    public function test_rest_schema_shape_sanity(): void {
        $schema = MetaPayload::restSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertArrayHasKey('robots', $schema['properties']);
        $this->assertArrayHasKey('og', $schema['properties']);
        $this->assertArrayHasKey('twitter', $schema['properties']);
        $this->assertArrayHasKey('focus_keywords', $schema['properties']);
        $this->assertArrayHasKey('schema', $schema['properties']);
        $this->assertArrayHasKey('flags', $schema['properties']);
        $this->assertFalse($schema['properties']['robots']['additionalProperties']);
    }

    public function test_defaults_shape_matches_spec(): void {
        $defaults = MetaPayload::defaults();

        $this->assertSame('', $defaults['title']);
        $this->assertSame('', $defaults['description']);
        $this->assertSame('', $defaults['canonical']);
        $this->assertTrue($defaults['robots']['index']);
        $this->assertTrue($defaults['robots']['follow']);
        $this->assertFalse($defaults['robots']['noarchive']);
        $this->assertNull($defaults['robots']['max_snippet']);
        $this->assertSame('', $defaults['og']['image']);
        $this->assertSame(0, $defaults['og']['image_id']);
        $this->assertSame('summary_large_image', $defaults['twitter']['card']);
        $this->assertSame([], $defaults['focus_keywords']);
        $this->assertSame([], $defaults['schema']);
        $this->assertFalse($defaults['flags']['pillar']);
    }

    public function test_user_prefs_schema_is_permissive(): void {
        $schema = MetaPayload::userPrefsSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertIsArray($schema['additionalProperties']);
        // Must allow scalars and array-of-scalars; not strict false.
        $this->assertNotFalse($schema['additionalProperties']);
        // Ensure oneOf includes scalar types.
        $oneOf = $schema['additionalProperties']['oneOf'] ?? [];
        $types = array_column($oneOf, 'type');
        $this->assertContains('string', $types);
        $this->assertContains('boolean', $types);
        // Must be different from restSchema's strict false.
        $strict = MetaPayload::restSchema();
        $this->assertFalse($strict['additionalProperties']);
    }
}
