<?php
/**
 * SitemapSettings tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\SitemapSettings;

final class SitemapSettingsTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var array<string, mixed> */
    private array $options = [];

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->options = [];

        Functions\when('get_option')->alias(
            function (string $key, mixed $default = false): mixed {
                return $this->options[ $key ] ?? $default;
            }
        );
        Functions\when('update_option')->alias(
            function (string $key, mixed $value, mixed ...$rest): bool {
                $this->options[ $key ] = $value;

                return true;
            }
        );
        Functions\when('absint')->alias(static fn (mixed $v): int => abs((int) $v));
        Functions\when('sanitize_key')->alias(static fn (string $v): string => strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $v)));
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_defaults_exact(): void {
        $this->assertSame(
            [
                'items_per_page'         => 1000,
                'include_images'         => true,
                'include_featured_image' => true,
                'exclude_posts'          => [],
                'exclude_terms'          => [],
                'include_empty_terms'    => false,
                'authors_sitemap'        => true,
                'authors_include_empty'  => false,
                'authors_exclude_roles'  => [],
                'authors_exclude_users'  => [],
            ],
            SitemapSettings::defaults()
        );
    }

    public function test_all_merges_stored_over_defaults(): void {
        $this->options[ SitemapSettings::OPTION ] = [ 'items_per_page' => 250 ];

        $settings = new SitemapSettings();

        $this->assertSame(250, $settings->get('items_per_page'));
        $this->assertTrue($settings->get('include_images'));
        $this->assertSame('fallback', $settings->get('missing_key', 'fallback'));
    }

    public function test_set_whitelists_unknown_keys_away(): void {
        $settings = new SitemapSettings();

        $this->assertTrue($settings->set([ 'include_images' => false, 'evil_key' => 'x' ]));

        $stored = $this->options[ SitemapSettings::OPTION ];

        $this->assertFalse($stored['include_images']);
        $this->assertArrayNotHasKey('evil_key', $stored);
    }

    public function test_set_only_unknown_keys_saves_nothing(): void {
        $settings = new SitemapSettings();

        $this->assertFalse($settings->set([ 'evil_key' => 'x' ]));
        $this->assertArrayNotHasKey(SitemapSettings::OPTION, $this->options);
    }

    public function test_items_per_page_clamped_and_cast(): void {
        $settings = new SitemapSettings();

        $settings->set([ 'items_per_page' => 0 ]);
        $this->assertSame(1, $this->options[ SitemapSettings::OPTION ]['items_per_page']);

        $settings->set([ 'items_per_page' => 99999 ]);
        $this->assertSame(50000, $this->options[ SitemapSettings::OPTION ]['items_per_page']);

        $settings->set([ 'items_per_page' => '1500' ]);
        $this->assertSame(1500, $this->options[ SitemapSettings::OPTION ]['items_per_page']);
    }

    public function test_id_lists_absint_filtered_and_unique(): void {
        $settings = new SitemapSettings();

        $settings->set([ 'exclude_posts' => [ '3', -4, 0, 3, 'abc' ] ]);

        $this->assertSame([ 3, 4 ], $this->options[ SitemapSettings::OPTION ]['exclude_posts']);
    }

    public function test_id_lists_accept_comma_string(): void {
        $settings = new SitemapSettings();

        $settings->set([ 'exclude_terms' => '7, 8,8' ]);

        $this->assertSame([ 7, 8 ], $this->options[ SitemapSettings::OPTION ]['exclude_terms']);
    }

    public function test_dynamic_keys_accepted_others_dropped(): void {
        $settings = new SitemapSettings();

        $this->assertTrue(
            $settings->set(
                [
                    'pt_post_sitemap'     => false,
                    'tax_category_sitemap' => 0,
                    'pt_NOPE_sitemap'     => true,
                    'pt_x'                => true,
                ]
            )
        );

        $stored = $this->options[ SitemapSettings::OPTION ];

        $this->assertFalse($stored['pt_post_sitemap']);
        $this->assertFalse($stored['tax_category_sitemap']);
        $this->assertArrayNotHasKey('pt_NOPE_sitemap', $stored);
        $this->assertArrayNotHasKey('pt_x', $stored);
    }

    public function test_is_type_enabled_true_when_absent(): void {
        $settings = new SitemapSettings();

        $this->assertTrue($settings->isTypeEnabled('pt', 'post'));
        $this->assertTrue($settings->isTypeEnabled('tax', 'category'));

        $settings->set([ 'pt_post_sitemap' => false ]);

        $this->assertFalse($settings->isTypeEnabled('pt', 'post'));
        $this->assertTrue($settings->isTypeEnabled('pt', 'page'));
    }
}
