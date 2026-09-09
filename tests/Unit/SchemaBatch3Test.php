<?php
/**
 * Schema batch 3 tests, commerce, media, and professional pieces.
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
use RankKernel\Modules\Schema\Pieces\BookPiece;
use RankKernel\Modules\Schema\Pieces\CoursePiece;
use RankKernel\Modules\Schema\Pieces\EventPiece;
use RankKernel\Modules\Schema\Pieces\JobPostingPiece;
use RankKernel\Modules\Schema\Pieces\MusicPiece;
use RankKernel\Modules\Schema\Pieces\ProductPiece;
use RankKernel\Modules\Schema\Pieces\RecipePiece;
use RankKernel\Modules\Schema\Pieces\SchemaHelpers;
use RankKernel\Modules\Schema\Pieces\ServicePiece;
use RankKernel\Modules\Schema\Pieces\SoftwarePiece;
use RankKernel\Modules\Schema\Pieces\VideoPiece;
use RankKernel\Modules\Schema\SchemaModule;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;
use RankKernel\Tests\Unit\Support\WooProductDouble;
use WP_Query;

/**
 * Commerce, media, and professional schema pieces.
 */
final class SchemaBatch3Test extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        WooProductDouble::$woo       = [];
        WooProductDouble::$available = true;

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

    /**
     * @param array<string, mixed> $schema Raw schema payload.
     */
    private function schemaContext( array $schema ): Context {
        $this->stubPostMeta([ 'schema' => $schema ]);

        return $this->makeContext($this->singularQuery());
    }

    private function stubAuthor( int $authorId ): void {
        Functions\when('get_post_field')->alias(
            static function (string $field, int $id) use ($authorId): mixed {
                if ('post_author' === $field) {
                    return $authorId;
                }

                return '';
            }
        );
    }

    private function stubFeaturedImage( string $url ): void {
        Functions\when('get_post_thumbnail_id')->alias(static fn (int $id): int => 5);
        Functions\when('wp_get_attachment_image_url')->alias(static fn (int $id, string $size): string => $url);
    }

    public function test_type_list_covers_batch3_types(): void {
        foreach ([ 'Service', 'Book', 'JobPosting', 'SoftwareApplication', 'MusicRecording' ] as $type) {
            $this->assertSame($type, SchemaTypes::normalize($type));
        }

        $this->assertSame('Article', SchemaTypes::normalize('Carousel'));
    }

    public function test_product_not_needed_for_wrong_type_or_empty_name(): void {
        $piece = new ProductPiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));
        $this->assertFalse($piece->isNeeded($this->schemaContext([ 'type' => 'Recipe' ])));

        Functions\when('get_the_title')->justReturn('');
        $this->assertFalse($piece->isNeeded($this->schemaContext([ 'type' => 'Product' ])));
    }

    public function test_product_builds_full_structure_with_ids(): void {
        $ctx = $this->schemaContext(
            [
                'type'   => 'Product',
                'fields' => [
                    'headline'      => 'Cool Widget',
                    'price'         => '19.99',
                    'priceCurrency' => 'usd',
                    'availability'  => 'out_of_stock',
                    'sku'           => 'WID-1',
                    'ratingValue'   => '4.5',
                    'reviewCount'   => '12',
                ],
            ]
        );

        $piece = new ProductPiece();

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('Product', $build['@type']);
        $this->assertSame('https://example.com/hello/#product', $build['@id']);
        $this->assertSame('Cool Widget', $build['name']);
        $this->assertSame('Excerpt text', $build['description']);
        $this->assertSame('WID-1', $build['sku']);
        $this->assertSame('19.99', $build['offers']['price']);
        $this->assertSame('USD', $build['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/OutOfStock', $build['offers']['availability']);
        $this->assertSame('AggregateRating', $build['aggregateRating']['@type']);
        $this->assertSame('4.5', $build['aggregateRating']['ratingValue']);
        $this->assertSame(12, $build['aggregateRating']['reviewCount']);
    }

    public function test_product_omits_sku_offers_and_rating_when_empty(): void {
        $build = (new ProductPiece())->build($this->schemaContext([ 'type' => 'Product' ]));

        $this->assertSame('Hello Post', $build['name']);
        $this->assertArrayNotHasKey('sku', $build);
        $this->assertArrayNotHasKey('offers', $build);
        $this->assertArrayNotHasKey('aggregateRating', $build);
    }

    public function test_availability_mapping_with_default(): void {
        $this->assertSame('https://schema.org/InStock', SchemaHelpers::availabilityUrl('in_stock'));
        $this->assertSame('https://schema.org/OutOfStock', SchemaHelpers::availabilityUrl('out_of_stock'));
        $this->assertSame('https://schema.org/PreOrder', SchemaHelpers::availabilityUrl('preorder'));
        $this->assertSame('https://schema.org/InStock', SchemaHelpers::availabilityUrl(''));
        $this->assertSame('https://schema.org/InStock', SchemaHelpers::availabilityUrl('backorder'));
    }

    public function test_price_and_currency_validation(): void {
        $this->assertSame('19.99', SchemaHelpers::priceString('19.99'));
        $this->assertSame('', SchemaHelpers::priceString('free'));
        $this->assertSame('', SchemaHelpers::priceString(''));
        $this->assertSame('USD', SchemaHelpers::currency('usd'));
        $this->assertSame('', SchemaHelpers::currency('US'));
        $this->assertSame('', SchemaHelpers::currency('USDD'));
        $this->assertSame('', SchemaHelpers::currency(''));
    }

    public function test_rating_validation(): void {
        $valid = SchemaHelpers::aggregateRating([ 'ratingValue' => '4', 'reviewCount' => '3' ]);

        $this->assertSame('4', $valid['ratingValue']);
        $this->assertSame(3, $valid['reviewCount']);

        $this->assertSame([], SchemaHelpers::aggregateRating([ 'ratingValue' => '7', 'reviewCount' => '3' ]));
        $this->assertSame([], SchemaHelpers::aggregateRating([ 'ratingValue' => 'nah', 'reviewCount' => '3' ]));
        $this->assertSame([], SchemaHelpers::aggregateRating([ 'ratingValue' => '4' ]));
        $this->assertSame([], SchemaHelpers::aggregateRating([ 'ratingValue' => '4', 'reviewCount' => '0' ]));
    }

    public function test_duration_conversion(): void {
        $this->assertSame('PT90M', SchemaHelpers::toDuration('90'));
        $this->assertSame('PT1H30M', SchemaHelpers::toDuration('PT1H30M'));
        $this->assertSame('PT45S', SchemaHelpers::toDuration('PT45S'));
        $this->assertSame('', SchemaHelpers::toDuration('garbage'));
        $this->assertSame('', SchemaHelpers::toDuration('PT'));
        $this->assertSame('', SchemaHelpers::toDuration('1 hour'));
        $this->assertSame('', SchemaHelpers::toDuration(''));
    }

    public function test_date_normalization_drops_invalid(): void {
        $this->assertSame('2026-05-01T10:00:00+00:00', SchemaHelpers::normalizeDate('2026-05-01 10:00:00'));
        $this->assertSame('', SchemaHelpers::normalizeDate('not a date'));
        $this->assertSame('', SchemaHelpers::normalizeDate(''));
    }

    public function test_recipe_needed_by_ingredients_without_type(): void {
        $piece = new RecipePiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));

        $ctx = $this->schemaContext([ 'fields' => [ 'ingredients' => "Flour\nWater" ] ]);

        $this->assertTrue($piece->isNeeded($ctx));
    }

    public function test_recipe_builds_ingredients_instructions_and_durations(): void {
        $ctx = $this->schemaContext(
            [
                'type'   => 'Recipe',
                'fields' => [
                    'headline'     => 'Bread',
                    'ingredients'  => "Flour\n\nWater\n Salt ",
                    'instructions' => "Mix\nBake",
                    'prepTime'     => '15',
                    'cookTime'     => 'PT30M',
                    'totalTime'    => 'soon',
                    'yield'        => '2 loaves',
                ],
            ]
        );

        $this->stubAuthor(7);

        $build = (new RecipePiece())->build($ctx);

        $this->assertSame('Recipe', $build['@type']);
        $this->assertSame('https://example.com/hello/#recipe', $build['@id']);
        $this->assertSame([ 'Flour', 'Water', 'Salt' ], $build['recipeIngredient']);
        $this->assertSame('HowToStep', $build['recipeInstructions'][0]['@type']);
        $this->assertSame('Mix', $build['recipeInstructions'][0]['text']);
        $this->assertCount(2, $build['recipeInstructions']);
        $this->assertSame('PT15M', $build['prepTime']);
        $this->assertSame('PT30M', $build['cookTime']);
        $this->assertArrayNotHasKey('totalTime', $build);
        $this->assertSame('2 loaves', $build['recipeYield']);
        $this->assertSame('https://example.com/author/bob/#author', $build['author']['@id']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $build['datePublished']);
    }

    public function test_event_needed_by_start_date_and_gating(): void {
        $piece = new EventPiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));
        $this->assertFalse($piece->isNeeded($this->schemaContext([ 'type' => 'Event' ])));

        $ctx = $this->schemaContext(
            [ 'fields' => [ 'startDate' => '2026-06-01 19:00:00' ] ]
        );

        $this->assertTrue($piece->isNeeded($ctx));
    }

    public function test_event_builds_location_status_and_offers(): void {
        $ctx = $this->schemaContext(
            [
                'type'   => 'Event',
                'fields' => [
                    'headline'        => 'Concert',
                    'startDate'       => '2026-06-01 19:00:00',
                    'endDate'         => '2026-06-01 22:00:00',
                    'locationName'    => 'Hall',
                    'addressLocality' => 'Springfield',
                    'addressCountry'  => 'US',
                    'performer'       => 'The Band',
                    'eventStatus'     => 'postponed',
                    'price'           => '25',
                    'priceCurrency'   => 'eur',
                ],
            ]
        );

        $build = (new EventPiece())->build($ctx);

        $this->assertSame('Event', $build['@type']);
        $this->assertSame('https://example.com/hello/#event', $build['@id']);
        $this->assertSame('2026-06-01T19:00:00+00:00', $build['startDate']);
        $this->assertSame('2026-06-01T22:00:00+00:00', $build['endDate']);
        $this->assertSame('Place', $build['location']['@type']);
        $this->assertSame('Hall', $build['location']['name']);
        $this->assertSame('Springfield', $build['location']['address']['addressLocality']);
        $this->assertSame('US', $build['location']['address']['addressCountry']);
        $this->assertArrayNotHasKey('streetAddress', $build['location']['address']);
        $this->assertSame('PerformingGroup', $build['performer']['@type']);
        $this->assertSame('The Band', $build['performer']['name']);
        $this->assertSame('https://schema.org/EventPostponed', $build['eventStatus']);
        $this->assertSame('25', $build['offers']['price']);
        $this->assertSame('EUR', $build['offers']['priceCurrency']);
        $this->assertSame('https://example.com/hello/', $build['offers']['url']);
    }

    public function test_event_status_mapping_and_end_date_rule(): void {
        $this->assertSame('https://schema.org/EventScheduled', SchemaHelpers::eventStatusUrl('scheduled'));
        $this->assertSame('https://schema.org/EventCancelled', SchemaHelpers::eventStatusUrl('cancelled'));
        $this->assertSame('https://schema.org/EventPostponed', SchemaHelpers::eventStatusUrl('postponed'));
        $this->assertSame('https://schema.org/EventMovedOnline', SchemaHelpers::eventStatusUrl('moved'));
        $this->assertSame('https://schema.org/EventRescheduled', SchemaHelpers::eventStatusUrl('rescheduled'));
        $this->assertSame('https://schema.org/EventScheduled', SchemaHelpers::eventStatusUrl(''));

        $build = (new EventPiece())->build(
            $this->schemaContext(
                [
                    'type'   => 'Event',
                    'fields' => [
                        'startDate' => '2026-06-01 19:00:00',
                        'endDate'   => '2026-05-01 19:00:00',
                    ],
                ]
            )
        );

        $this->assertArrayNotHasKey('endDate', $build);
        $this->assertArrayNotHasKey('location', $build);
        $this->assertSame('https://schema.org/EventScheduled', $build['eventStatus']);
    }

    public function test_service_builds_provider_area_and_offers(): void {
        $piece = new ServicePiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));

        $ctx = $this->schemaContext(
            [
                'type'   => 'Service',
                'fields' => [
                    'headline'      => 'Plumbing',
                    'areaServed'    => 'Springfield',
                    'price'         => '99',
                    'priceCurrency' => 'USD',
                ],
            ]
        );

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('Service', $build['@type']);
        $this->assertSame('https://example.com/hello/#service', $build['@id']);
        $this->assertSame('https://example.com/#organization', $build['provider']['@id']);
        $this->assertSame('Springfield', $build['areaServed']);
        $this->assertSame('99', $build['offers']['price']);
    }

    public function test_video_requires_all_google_fields(): void {
        $piece = new VideoPiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));
        $this->assertFalse($piece->isNeeded($this->schemaContext([ 'type' => 'VideoObject' ])));

        $this->stubFeaturedImage('https://example.com/frame.jpg');

        $ctx = $this->schemaContext([ 'type' => 'VideoObject' ]);

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('VideoObject', $build['@type']);
        $this->assertSame('https://example.com/hello/#video', $build['@id']);
        $this->assertSame('Hello Post', $build['name']);
        $this->assertSame('Excerpt text', $build['description']);
        $this->assertSame('https://example.com/frame.jpg', $build['thumbnailUrl']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $build['uploadDate']);
    }

    public function test_video_builds_duration_content_and_author(): void {
        $this->stubFeaturedImage('https://example.com/frame.jpg');

        $ctx = $this->schemaContext(
            [
                'type'   => 'VideoObject',
                'fields' => [
                    'duration'   => '5',
                    'contentUrl' => 'https://example.com/clip.mp4',
                ],
            ]
        );

        $this->stubAuthor(7);

        $build = (new VideoPiece())->build($ctx);

        $this->assertSame('PT5M', $build['duration']);
        $this->assertSame('https://example.com/clip.mp4', $build['contentUrl']);
        $this->assertSame('https://example.com/author/bob/#author', $build['author']['@id']);
    }

    public function test_book_builds_author_isbn_publisher(): void {
        $piece = new BookPiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));

        $ctx = $this->schemaContext(
            [
                'type'   => 'Book',
                'fields' => [ 'isbn' => '978-3-16-148410-0' ],
            ]
        );

        $this->stubAuthor(7);

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('Book', $build['@type']);
        $this->assertSame('https://example.com/hello/#book', $build['@id']);
        $this->assertSame('9783161484100', $build['isbn']);
        $this->assertSame('https://example.com/author/bob/#author', $build['author']['@id']);
        $this->assertSame('https://example.com/#organization', $build['publisher']['@id']);
    }

    public function test_book_drops_invalid_isbn(): void {
        $build = (new BookPiece())->build(
            $this->schemaContext(
                [ 'type' => 'Book', 'fields' => [ 'isbn' => 'abc-123!' ] ]
            )
        );

        $this->assertArrayNotHasKey('isbn', $build);
    }

    public function test_course_builds_provider(): void {
        $piece = new CoursePiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));

        $ctx = $this->schemaContext([ 'type' => 'Course' ]);

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('Course', $build['@type']);
        $this->assertSame('https://example.com/hello/#course', $build['@id']);
        $this->assertSame('Hello Post', $build['name']);
        $this->assertSame('https://example.com/#organization', $build['provider']['@id']);
    }

    public function test_job_posting_builds_org_location_salary_dates(): void {
        $piece = new JobPostingPiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));

        $ctx = $this->schemaContext(
            [
                'type'   => 'JobPosting',
                'fields' => [
                    'headline'      => 'Baker',
                    'jobLocation'   => 'Springfield',
                    'salary'        => '45000',
                    'priceCurrency' => 'USD',
                    'validThrough'  => '2026-12-31 23:59:59',
                ],
            ]
        );

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('JobPosting', $build['@type']);
        $this->assertSame('https://example.com/hello/#jobposting', $build['@id']);
        $this->assertSame('Baker', $build['title']);
        $this->assertSame('https://example.com/#organization', $build['hiringOrganization']['@id']);
        $this->assertSame('Place', $build['jobLocation']['@type']);
        $this->assertSame('Springfield', $build['jobLocation']['name']);
        $this->assertSame('MonetaryAmount', $build['baseSalary']['@type']);
        $this->assertSame('45000', $build['baseSalary']['value']);
        $this->assertSame('USD', $build['baseSalary']['currency']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $build['datePosted']);
        $this->assertSame('2026-12-31T23:59:59+00:00', $build['validThrough']);
    }

    public function test_job_posting_company_overrides_org_and_bad_dates_drop(): void {
        $build = (new JobPostingPiece())->build(
            $this->schemaContext(
                [
                    'type'   => 'JobPosting',
                    'fields' => [
                        'company'      => 'Acme',
                        'salary'       => 'lots',
                        'validThrough' => 'not a real date',
                    ],
                ]
            )
        );

        $this->assertSame('Acme', $build['hiringOrganization']['name']);
        $this->assertArrayNotHasKey('baseSalary', $build);
        $this->assertArrayNotHasKey('validThrough', $build);
    }

    public function test_software_builds_category_os_offers_rating(): void {
        $piece = new SoftwarePiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));

        $ctx = $this->schemaContext(
            [
                'type'   => 'SoftwareApplication',
                'fields' => [
                    'appCategory'     => 'Utilities',
                    'operatingSystem' => 'Windows',
                    'price'           => '0',
                    'priceCurrency'   => 'USD',
                    'ratingValue'     => '4',
                    'reviewCount'     => '9',
                ],
            ]
        );

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('SoftwareApplication', $build['@type']);
        $this->assertSame('https://example.com/hello/#software', $build['@id']);
        $this->assertSame('Utilities', $build['applicationCategory']);
        $this->assertSame('Windows', $build['operatingSystem']);
        $this->assertSame('0', $build['offers']['price']);
        $this->assertSame('4', $build['aggregateRating']['ratingValue']);
    }

    public function test_music_builds_artist_and_album(): void {
        $piece = new MusicPiece();

        $this->assertFalse($piece->isNeeded($this->schemaContext([])));

        $ctx = $this->schemaContext(
            [
                'type'   => 'MusicRecording',
                'fields' => [
                    'artist' => 'The Band',
                    'album'  => 'Debut',
                ],
            ]
        );

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('MusicRecording', $build['@type']);
        $this->assertSame('https://example.com/hello/#music', $build['@id']);
        $this->assertSame('MusicGroup', $build['byArtist']['@type']);
        $this->assertSame('The Band', $build['byArtist']['name']);
        $this->assertSame('MusicAlbum', $build['inAlbum']['@type']);
        $this->assertSame('Debut', $build['inAlbum']['name']);
    }

    public function test_music_omits_empty_artist_and_album(): void {
        $build = (new MusicPiece())->build($this->schemaContext([ 'type' => 'MusicRecording' ]));

        $this->assertArrayNotHasKey('byArtist', $build);
        $this->assertArrayNotHasKey('inAlbum', $build);
    }

    public function test_woo_values_flow_through_the_seam(): void {
        WooProductDouble::$woo = [
            'price'        => '29.99',
            'currency'     => 'eur',
            'availability' => 'out_of_stock',
            'sku'          => 'WOO-1',
            'ratingValue'  => '4',
            'reviewCount'  => '5',
        ];

        $ctx   = $this->schemaContext([ 'type' => 'Product' ]);
        $piece = new WooProductDouble();

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('29.99', $build['offers']['price']);
        $this->assertSame('EUR', $build['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/OutOfStock', $build['offers']['availability']);
        $this->assertSame('WOO-1', $build['sku']);
        $this->assertSame('4', $build['aggregateRating']['ratingValue']);
        $this->assertSame(5, $build['aggregateRating']['reviewCount']);
    }

    public function test_woo_payload_overrides_win(): void {
        WooProductDouble::$woo = [
            'price'        => '29.99',
            'currency'     => 'EUR',
            'availability' => 'out_of_stock',
            'sku'          => 'WOO-1',
            'ratingValue'  => '4',
            'reviewCount'  => '5',
        ];

        $ctx = $this->schemaContext(
            [
                'type'   => 'Product',
                'fields' => [
                    'price'        => '9.99',
                    'availability' => 'in_stock',
                    'sku'          => 'FIELD-9',
                ],
            ]
        );

        $build = (new WooProductDouble())->build($ctx);

        $this->assertSame('9.99', $build['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $build['offers']['availability']);
        $this->assertSame('FIELD-9', $build['sku']);
    }

    public function test_woo_absent_yields_product_without_offers_and_no_crash(): void {
        WooProductDouble::$available = false;
        WooProductDouble::$woo       = [ 'price' => '29.99' ];

        $ctx = $this->schemaContext([ 'type' => 'Product' ]);

        $piece = new WooProductDouble();

        $this->assertTrue($piece->isNeeded($ctx));

        $build = $piece->build($ctx);

        $this->assertSame('Product', $build['@type']);
        $this->assertArrayNotHasKey('offers', $build);
    }

    public function test_generator_wiring_includes_batch3_pieces(): void {
        $fields = [
            'price'         => '19.99',
            'priceCurrency' => 'USD',
            'startDate'     => '2026-06-01 19:00:00',
            'thumbnailUrl'  => 'https://example.com/frame.jpg',
            'uploadDate'    => '2026-05-01 10:00:00',
        ];

        $expected = [
            'Product'             => 'Product',
            'Recipe'              => 'Recipe',
            'Event'               => 'Event',
            'Service'             => 'Service',
            'VideoObject'         => 'VideoObject',
            'Book'                => 'Book',
            'Course'              => 'Course',
            'JobPosting'          => 'JobPosting',
            'SoftwareApplication' => 'SoftwareApplication',
            'MusicRecording'      => 'MusicRecording',
        ];

        foreach ($expected as $payloadType => $nodeType) {
            $ctx    = $this->schemaContext(
                [ 'type' => $payloadType, 'fields' => $fields ]
            );
            $module = new SchemaModule(null, null, null, $ctx);
            $graph  = $module->getGenerator()->generate($ctx)['@graph'];
            $types  = array_column($graph, '@type');

            $this->assertContains($nodeType, $types, 'Missing ' . $nodeType . ' for payload type ' . $payloadType);
        }
    }
}
