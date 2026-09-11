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
use RankKernel\Modules\Schema\GraphNormalizer;
use RankKernel\Modules\Schema\Pieces\ArticlePiece;
use RankKernel\Modules\Schema\Pieces\CustomJsonPiece;
use RankKernel\Modules\Schema\Pieces\FaqPiece;
use RankKernel\Modules\Schema\Pieces\ImageObjectPiece;
use RankKernel\Modules\Schema\Pieces\LocalBusinessPiece;
use RankKernel\Modules\Schema\Pieces\OrganizationPiece;
use RankKernel\Modules\Schema\Pieces\PersonPiece;
use RankKernel\Modules\Schema\Pieces\ProductPiece;
use RankKernel\Modules\Schema\Pieces\ReviewPiece;
use RankKernel\Modules\Schema\Pieces\SchemaHelpers;
use RankKernel\Modules\Schema\Pieces\WebpagePiece;
use RankKernel\Modules\Schema\Pieces\WebsitePiece;
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
        Functions\when('get_the_author')->justReturn('Bob');
        Functions\when('get_the_category')->justReturn([]);
        Functions\when('date_i18n')->justReturn('Jan 1, 2026');
        Functions\when('single_post_title')->justReturn('');
        Functions\when('get_post_thumbnail_id')->justReturn(0);
        Functions\when('wp_get_attachment_image_url')->justReturn('');
        Functions\when('wp_get_attachment_url')->justReturn('');
        Functions\when('single_term_title')->justReturn('');
        Functions\when('get_term_link')->justReturn('https://example.com/cat/news/');
        Functions\when('wp_kses_post')->alias(static fn (string $v): string => $v);
        Functions\when('wp_json_encode')->alias(static fn (mixed $v, int $o = 0): string => (string) json_encode($v, $o));
        Functions\when('update_option')->justReturn(true);
        Functions\when('parse_blocks')->justReturn([]);
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

    public function test_registry_maps_every_supported_type_to_a_piece(): void {
        foreach (SchemaTypes::SUPPORTED as $type) {
            $this->assertNotSame('', SchemaTypes::pieceFor($type), $type);
            $this->assertNotSame('', SchemaTypes::label($type), $type);
        }

        $this->assertSame('', SchemaTypes::pieceFor('EvilType'));
        $this->assertSame('article', SchemaTypes::pieceFor('BlogPosting'));
        $this->assertSame('article', SchemaTypes::pieceFor('NewsArticle'));
        $this->assertSame('localbusiness', SchemaTypes::pieceFor('LocalBusiness'));
        $this->assertSame('review', SchemaTypes::pieceFor('Review'));
        $this->assertSame('imageobject', SchemaTypes::pieceFor('ImageObject'));
    }

    public function test_registry_required_fields_match_admin_warnings(): void {
        $this->assertSame([ 'headline', 'startDate', 'locationName' ], SchemaTypes::requiredFields('Event'));
        $this->assertSame([ 'headline' ], SchemaTypes::requiredFields('Product'));
        $this->assertSame([ 'headline' ], SchemaTypes::requiredFields('Service'));
        $this->assertSame([ 'headline' ], SchemaTypes::requiredFields('Review'));
        $this->assertSame([ 'headline' ], SchemaTypes::requiredFields('LocalBusiness'));
        $this->assertSame([], SchemaTypes::requiredFields('ItemList'));
        $this->assertSame([], SchemaTypes::requiredFields('QAPage'));
        $this->assertSame([], SchemaTypes::requiredFields('EvilType'));

        $this->assertSame(
            'Product name (headline) is required for Product.',
            SchemaTypes::requiredMessage('Product', 'headline')
        );
        $this->assertSame(
            'Name (headline) is required for Event.',
            SchemaTypes::requiredMessage('Event', 'headline')
        );
        $this->assertSame('Start date is required for Event.', SchemaTypes::requiredMessage('Event', 'startDate'));
        $this->assertSame(
            'Headline is required for Service.',
            SchemaTypes::requiredMessage('Service', 'headline')
        );
    }

    public function test_generator_registers_every_registry_piece(): void {
        $generator = ( new SchemaModule(new SettingsStore()) )->getGenerator();

        $prop = new \ReflectionProperty(Generator::class, 'pieces');
        $prop->setAccessible(true);

        /** @var array<string, object> $pieces */
        $pieces = $prop->getValue($generator);

        foreach (array_unique(array_values(SchemaTypes::PIECES)) as $pieceId) {
            $this->assertArrayHasKey($pieceId, $pieces, $pieceId);
        }
    }

    /**
     * @param array<string, mixed> $settings Stored settings overrides.
     */
    private function storeWith( array $settings ): SettingsStore {
        $store = new SettingsStore();
        $store->set($settings);

        return $store;
    }

    /**
     * Assert no empty string, null, or empty array survives in decoded output.
     */
    private function assertNoEmptyValues( mixed $value, string $path = '' ): void {
        if (is_string($value)) {
            $this->assertNotSame('', trim($value), 'empty string at ' . $path);

            return;
        }

        if (! is_array($value)) {
            return;
        }

        $this->assertNotSame([], $value, 'empty array at ' . $path);

        foreach ($value as $key => $item) {
            $this->assertNoEmptyValues($item, $path . '/' . (string) $key);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fullGraph( Context $ctx ): array {
        $module = new SchemaModule(new SettingsStore());

        return $module->getGenerator()->generate($ctx)['@graph'];
    }

    /**
     * @param array<int, array<string, mixed>> $graph
     * @return array<int, string>
     */
    private function graphTypes( array $graph ): array {
        $out = [];

        foreach ($graph as $node) {
            if (isset($node['@type']) && is_string($node['@type'])) {
                $out[] = $node['@type'];
            }
        }

        return $out;
    }

    public function test_localbusiness_builds_full_node(): void {
        $ctx = $this->schemaContext([
            'type'   => 'LocalBusiness',
            'fields' => [
                'telephone'       => '+1-555-0100',
                'priceRange'      => '$$',
                'openingHours'    => "Mo-Fr 09:00-17:00",
                'streetAddress'   => '1 Main St',
                'addressLocality' => 'Springfield',
                'addressCountry'  => 'US',
            ],
        ]);

        $piece = new LocalBusinessPiece(new SettingsStore());

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('LocalBusiness', $build['@type']);
        $this->assertSame('https://example.com/hello/#localbusiness', $build['@id']);
        $this->assertSame('Hello Post', $build['name']);
        $this->assertSame('+1-555-0100', $build['telephone']);
        $this->assertSame('$$', $build['priceRange']);
        $this->assertSame([ 'Mo-Fr 09:00-17:00' ], $build['openingHours']);
        $this->assertSame('PostalAddress', $build['address']['@type']);
        $this->assertSame('1 Main St', $build['address']['streetAddress']);
        $this->assertArrayNotHasKey('addressRegion', $build['address']);
    }

    public function test_localbusiness_not_needed_without_name_or_wrong_type(): void {
        $piece = new LocalBusinessPiece(new SettingsStore());

        $this->assertFalse($piece->isNeeded($this->schemaContext([ 'type' => 'Product' ])));
        $this->assertFalse($piece->isNeeded($this->schemaContext([])));
    }

    public function test_review_builds_standalone_node(): void {
        Functions\when('get_post_field')->alias(
            static fn (string $field, int $id): string => 'post_author' === $field ? '7' : ''
        );

        $ctx = $this->schemaContext([
            'type'   => 'Review',
            'fields' => [
                'itemName'    => 'Great Widget',
                'ratingValue' => '4.5',
                'reviewBody'  => 'Works well.',
            ],
        ]);

        $piece = new ReviewPiece(new SettingsStore());

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('Review', $build['@type']);
        $this->assertSame('https://example.com/hello/#review', $build['@id']);
        $this->assertSame('Great Widget', $build['itemReviewed']['name']);
        $this->assertSame('Thing', $build['itemReviewed']['@type']);
        $this->assertSame('4.5', $build['reviewRating']['ratingValue']);
        $this->assertSame('5', $build['reviewRating']['bestRating']);
        $this->assertSame('Works well.', $build['reviewBody']);
        $this->assertSame('https://example.com/author/bob/#author', $build['author']['@id']);
    }

    public function test_review_rejects_non_numeric_rating(): void {
        $piece = new ReviewPiece(new SettingsStore());

        $this->assertFalse(
            $piece->isNeeded($this->schemaContext([ 'type' => 'Review', 'fields' => [ 'itemName' => 'X' ] ]))
        );
        $this->assertFalse(
            $piece->isNeeded(
                $this->schemaContext([
                    'type'   => 'Review',
                    'fields' => [ 'itemName' => 'X', 'ratingValue' => 'great' ],
                ])
            )
        );
    }

    public function test_imageobject_uses_url_as_id_with_dimensions(): void {
        $ctx = $this->schemaContext([
            'type'   => 'ImageObject',
            'fields' => [
                'contentUrl' => 'https://example.com/img/photo.jpg',
                'caption'    => 'A photo',
                'width'      => '1200',
                'height'     => 'bogus',
            ],
        ]);

        $piece = new ImageObjectPiece(new SettingsStore());

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('ImageObject', $build['@type']);
        $this->assertSame('https://example.com/img/photo.jpg', $build['@id']);
        $this->assertSame('A photo', $build['caption']);
        $this->assertSame(1200, $build['width']);
        $this->assertArrayNotHasKey('height', $build);
    }

    public function test_custom_json_single_typed_node_merges(): void {
        $ctx = $this->schemaContext([
            'custom' => [
                '@type' => 'SpecialAnnouncement',
                'name'  => 'Heads up',
            ],
        ]);

        $piece = new CustomJsonPiece();

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('SpecialAnnouncement', $build['@type']);
        $this->assertSame('Heads up', $build['name']);
    }

    public function test_custom_json_strips_context_and_wraps_typeless(): void {
        $ctx = $this->schemaContext([
            'custom' => [
                '@context' => 'https://schema.org',
                'foo'      => 'bar',
            ],
        ]);

        $build = ( new CustomJsonPiece() )->build($ctx);

        $this->assertSame('Thing', $build['@type']);
        $this->assertSame('bar', $build['foo']);
        $this->assertArrayNotHasKey('@context', $build);
    }

    public function test_custom_json_graph_wrapper_and_caps(): void {
        $nodes = [];

        for ($i = 0; $i < 12; $i++) {
            $nodes[] = [ '@type' => 'Thing', 'name' => 'n' . $i ];
        }

        $ctx = $this->schemaContext([ 'custom' => [ '@graph' => $nodes ] ]);

        $piece = new CustomJsonPiece();

        $this->assertTrue($piece->isNeeded($ctx));

        $graph = $this->fullGraph($ctx);
        $names = [];

        foreach ($graph as $node) {
            if (isset($node['name']) && is_string($node['name']) && str_starts_with($node['name'], 'n')) {
                $names[] = $node['name'];
            }
        }

        $this->assertCount(10, $names);
    }

    public function test_custom_json_malformed_shapes_are_ignored(): void {
        $piece = new CustomJsonPiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));
        $this->assertFalse($piece->isNeeded($this->schemaContext([ 'custom' => 'nope' ])));
        $this->assertSame([], $piece->build($this->schemaContext([ 'custom' => 'nope' ])));
    }

    public function test_normalizer_rules(): void {
        $graph = GraphNormalizer::normalize([
            [ '@type' => 'WebSite', '@id' => 'https://example.com/#website', 'name' => 'S', 'empty' => '', 'nil' => null, 'list' => [] ],
            [ '@type' => 'WebSite', '@id' => 'https://example.com/#website', 'name' => 'Dupe' ],
            [ 'name' => 'typeless' ],
            'garbage',
            [ '@type' => '', 'name' => 'empty type' ],
            [
                '@type'     => 'Article',
                '@id'       => 'https://example.com/hello/#article',
                'publisher' => [ '@id' => 'https://example.com/#missing' ],
                'author'    => [ '@id' => 'https://external.example/other#author' ],
                '@context'  => 'https://schema.org',
            ],
            [ '@type' => 'Thing', 'count' => 0, 'flag' => false, 'zero' => '0' ],
        ]);

        $this->assertCount(3, $graph);
        $this->assertSame('S', $graph[0]['name']);
        $this->assertArrayNotHasKey('empty', $graph[0]);
        $this->assertArrayNotHasKey('publisher', $graph[1]);
        $this->assertSame('https://external.example/other#author', $graph[1]['author']['@id']);
        $this->assertSame(0, $graph[2]['count']);
        $this->assertFalse($graph[2]['flag']);
        $this->assertSame('0', $graph[2]['zero']);
    }

    public function test_disabled_flag_suppresses_everything_including_globals(): void {
        $graph = $this->fullGraph($this->schemaContext([ 'disabled' => true ]));

        $this->assertSame([], $graph);
    }

    public function test_disabled_filter_kills_graph(): void {
        Functions\when('apply_filters')->alias(
            static function (string $hook, mixed $value): mixed {
                if ('rankkernel/schema/disabled' === $hook) {
                    return true;
                }

                return $value;
            }
        );

        $graph = $this->fullGraph($this->schemaContext([]));

        $this->assertSame([], $graph);
    }

    public function test_single_primary_for_explicit_product(): void {
        Functions\when('get_post_field')->alias(
            static fn (string $field, int $id): string => 'post_author' === $field ? '7' : ''
        );

        $ctx = $this->schemaContext([
            'type'   => 'Product',
            'fields' => [ 'price' => '19.99', 'priceCurrency' => 'USD' ],
        ]);

        $graph = $this->fullGraph($ctx);
        $types = $this->graphTypes($graph);

        $this->assertContains('Product', $types);
        $this->assertNotContains('Article', $types);
        $this->assertNotContains('BlogPosting', $types);
        $this->assertCount(1, array_keys(array_filter($graph, static fn ($n): bool => 'Product' === ($n['@type'] ?? ''))));

        foreach ($graph as $node) {
            if ('Product' === ($node['@type'] ?? '')) {
                $this->assertSame('19.99', $node['offers']['price']);
            }
        }
    }

    public function test_post_type_default_routes_to_owning_piece(): void {
        Functions\when('get_post_type')->justReturn('product');

        $store = $this->storeWith([ 'schema_default_product' => 'Product' ]);

        $this->stubPostMeta([]);
        $ctx = $this->makeContext($this->makeQuery([ 'is_singular' => true ]));

        $this->assertSame('Product', SchemaHelpers::effectiveType($ctx, $store));
        $this->assertTrue(( new ProductPiece($store) )->isNeeded($ctx));
        $this->assertFalse(( new ArticlePiece($store) )->isNeeded($ctx));
    }

    public function test_effective_type_hierarchy(): void {
        Functions\when('get_post_type')->justReturn('post');

        $store = $this->storeWith([ 'schema_default_post' => 'NewsArticle' ]);

        $this->stubPostMeta([ 'schema' => [ 'type' => 'Article' ] ]);
        $ctx = $this->makeContext($this->makeQuery([ 'is_singular' => true ]));
        $this->assertSame('Article', SchemaHelpers::effectiveType($ctx, $store));

        $this->stubPostMeta([]);
        $ctx = $this->makeContext($this->makeQuery([ 'is_singular' => true ]));
        $this->assertSame('NewsArticle', SchemaHelpers::effectiveType($ctx, $store));

        $plain = new SettingsStore();
        $this->stubPostMeta([]);
        $ctx = $this->makeContext($this->makeQuery([ 'is_singular' => true ]));
        $this->assertSame('BlogPosting', SchemaHelpers::effectiveType($ctx, $plain));
    }

    public function test_person_publisher_mode_matches_refs(): void {
        Functions\when('get_post_field')->alias(
            static fn (string $field, int $id): string => 'post_author' === $field ? '7' : ''
        );

        $store = $this->storeWith([ 'site_represents' => 'person' ]);
        $ctx   = $this->makeContext($this->makeQuery([ 'is_singular' => true ]));

        $identity = ( new OrganizationPiece($store) )->build($ctx);

        $this->assertSame('Person', $identity['@type']);
        $this->assertSame('https://example.com/#publisher', $identity['@id']);

        $website = ( new WebsitePiece($store) )->build($ctx);

        $this->assertSame('https://example.com/#publisher', $website['publisher']['@id']);

        $article = ( new ArticlePiece($store) )->build($ctx);

        $this->assertSame('https://example.com/#publisher', $article['publisher']['@id']);
    }

    public function test_organization_mode_publisher_refs(): void {
        $store = new SettingsStore();
        $ctx   = $this->makeContext($this->makeQuery([ 'is_singular' => true ]));

        $identity = ( new OrganizationPiece($store) )->build($ctx);

        $this->assertSame('Organization', $identity['@type']);
        $this->assertSame('https://example.com/#organization', $identity['@id']);
        $this->assertSame('https://example.com/#organization', SchemaHelpers::publisherId($store));
    }

    public function test_webpage_is_webpage_on_front_with_ispartof(): void {
        Functions\when('is_front_page')->justReturn(true);

        $ctx   = $this->makeContext($this->makeQuery([ 'is_home' => true ]));
        $build = ( new WebpagePiece(new SettingsStore()) )->build($ctx);

        $this->assertSame('WebPage', $build['@type']);
        $this->assertSame('https://example.com/#website', $build['isPartOf']['@id']);
        $this->assertArrayNotHasKey('breadcrumb', $build);
    }

    public function test_webpage_has_breadcrumb_ref_on_singular(): void {
        $ctx   = $this->schemaContext([]);
        $build = ( new WebpagePiece(new SettingsStore()) )->build($ctx);

        $this->assertSame('https://example.com/hello/#breadcrumb', $build['breadcrumb']['@id']);
    }

    public function test_person_has_no_empty_sameas_and_honors_override(): void {
        Functions\when('get_post_field')->alias(
            static fn (string $field, int $id): string => 'post_author' === $field ? '7' : ''
        );

        $ctx   = $this->schemaContext([]);
        $build = ( new PersonPiece(new SettingsStore()) )->build($ctx);

        $this->assertArrayNotHasKey('sameAs', $build);

        $ctx   = $this->schemaContext([ 'fields' => [ 'author' => 'Pen Name' ] ]);
        $build = ( new PersonPiece(new SettingsStore()) )->build($ctx);

        $this->assertSame('Pen Name', $build['name']);
    }

    public function test_singular_renders_valid_single_script_graph(): void {
        Functions\when('get_post_field')->alias(
            static fn (string $field, int $id): string => 'post_author' === $field ? '7' : ''
        );

        $module = new SchemaModule(new SettingsStore());
        $ctx    = $this->schemaContext([]);

        ob_start();
        $module->render($ctx);
        $out = (string) ob_get_clean();

        $this->assertSame(1, substr_count($out, '<script type="application/ld+json">'));
        $this->assertSame(1, substr_count($out, '</script>'));

        $json = preg_replace('/^.*<script type="application\/ld\+json">(.*)<\/script>.*$/s', '$1', $out);
        $this->assertIsString($json);

        $doc = json_decode($json, true);
        $this->assertIsArray($doc);
        $this->assertSame('https://schema.org', $doc['@context']);
        $this->assertNotSame([], $doc['@graph']);

        $ids = [];

        foreach ($doc['@graph'] as $node) {
            $this->assertIsArray($node);
            $this->assertArrayHasKey('@type', $node);

            if (isset($node['@id'])) {
                $this->assertNotContains($node['@id'], $ids, 'duplicate @id ' . $node['@id']);
                $ids[] = $node['@id'];
            }
        }

        $this->assertNoEmptyValues($doc['@graph']);
    }

    public function test_render_escapes_script_breakout(): void {
        $module = new SchemaModule(new SettingsStore());
        $ctx    = $this->schemaContext([
            'custom' => [ '@type' => 'Thing', 'name' => '</script><script>alert(1)</script>' ],
        ]);

        ob_start();
        $module->render($ctx);
        $out = (string) ob_get_clean();

        $this->assertSame(1, substr_count($out, '</script>'));
    }

    public function test_home_search_and_404_contexts(): void {
        $home = $this->fullGraph($this->makeContext($this->makeQuery([ 'is_home' => true ])));
        $this->assertContains('WebPage', $this->graphTypes($home));
        $this->assertNotContains('BlogPosting', $this->graphTypes($home));

        $search = $this->fullGraph($this->makeContext($this->makeQuery([ 'is_search' => true ])));
        $this->assertNotContains('WebPage', $this->graphTypes($search));
        $this->assertContains('WebSite', $this->graphTypes($search));

        $notFound = $this->fullGraph($this->makeContext($this->makeQuery([ 'is_404' => true ])));
        $this->assertNotContains('WebPage', $this->graphTypes($notFound));
        $this->assertContains('WebSite', $this->graphTypes($notFound));
    }

    public function test_product_without_woo_uses_fields_only(): void {
        $this->assertFalse(class_exists('WooCommerce', false));

        $ctx = $this->schemaContext([
            'type'   => 'Product',
            'fields' => [ 'price' => '9.99', 'priceCurrency' => 'EUR', 'sku' => 'W-1' ],
        ]);

        $build = ( new ProductPiece(new SettingsStore()) )->build($ctx);

        $this->assertSame('9.99', $build['offers']['price']);
        $this->assertSame('EUR', $build['offers']['priceCurrency']);
        $this->assertSame('W-1', $build['sku']);
    }

    public function test_missing_site_name_prunes_dangling_publisher_refs(): void {
        Functions\when('get_bloginfo')->justReturn('');

        $graph = $this->fullGraph($this->schemaContext([]));
        $types = $this->graphTypes($graph);

        $this->assertNotContains('Organization', $types);

        foreach ($graph as $node) {
            $this->assertNoEmptyValues($node);

            foreach ([ 'publisher', 'provider' ] as $key) {
                if (isset($node[ $key ]['@id'])) {
                    $this->assertNotSame('https://example.com/#organization', $node[ $key ]['@id']);
                }
            }
        }
    }

    public function test_webpage_name_drops_dangling_separators(): void {
        Functions\when('get_the_title')->justReturn('');

        $ctx   = $this->makeContext($this->makeQuery([ 'is_home' => true ]));
        $build = ( new WebpagePiece(new SettingsStore()) )->build($ctx);

        $this->assertSame('My Site', $build['name']);
    }

    public function test_password_protected_post_emits_nothing(): void {
        Functions\when('post_password_required')->justReturn(true);

        $graph = $this->fullGraph($this->schemaContext([]));

        $this->assertSame([], $graph);
    }

    public function test_nested_faq_block_inside_group_is_found(): void {
        Functions\when('get_post_field')->alias(
            static function (string $field, int $id): string {
                if ('post_content' === $field) {
                    return 'grouped content';
                }

                return '';
            }
        );
        Functions\when('parse_blocks')->alias(
            static function (string $content): array {
                return [
                    [
                        'blockName'   => 'core/group',
                        'attrs'       => [],
                        'innerBlocks' => [
                            [
                                'blockName' => 'rankkernel/faq',
                                'attrs'     => [
                                    'questions' => [
                                        [ 'question' => 'Nested?', 'answer' => 'Found.' ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        );

        $piece = new FaqPiece();
        $ctx   = $this->schemaContext([]);

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('Nested?', $build['mainEntity'][0]['name']);
    }

    public function test_faq_block_parses_content_once_per_instance(): void {
        $calls = 0;

        Functions\when('get_post_field')->alias(
            static function (string $field, int $id): string {
                if ('post_content' === $field) {
                    return 'block content';
                }

                return '';
            }
        );
        Functions\when('parse_blocks')->alias(
            static function (string $content) use (&$calls): array {
                ++$calls;

                return [
                    [
                        'blockName' => 'rankkernel/faq',
                        'attrs'     => [
                            'questions' => [
                                [ 'question' => 'What?', 'answer' => 'This.' ],
                            ],
                        ],
                    ],
                ];
            }
        );

        $piece = new FaqPiece();
        $ctx   = $this->schemaContext([]);

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('FAQPage', $build['@type']);
        $this->assertSame(1, $calls);
    }
}
