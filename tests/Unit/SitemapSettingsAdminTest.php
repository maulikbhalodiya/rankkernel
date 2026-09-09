<?php
/**
 * Sitemap settings admin page tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\AdminMenu;
use RankKernel\Admin\SitemapSettingsPage;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Sitemaps\SitemapCache;
use RankKernel\Modules\Sitemaps\SitemapSettings;
use RankKernel\Settings\SettingsStore;

final class SitemapSettingsAdminTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var array<string, mixed> */
    private array $options = [];

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        if (! defined('RANKKERNEL_FILE')) {
            define('RANKKERNEL_FILE', '/tmp/rankkernel.php');
        }

        if (! defined('RANKKERNEL_TESTING')) {
            define('RANKKERNEL_TESTING', true);
        }

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
        Functions\when('sanitize_key')->alias(
            static fn (string $v): string => strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $v))
        );
        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('wp_unslash')->alias(static fn (mixed $v): mixed => is_string($v) ? stripslashes($v) : $v);
        Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_html__')->alias(static fn (string $v, string $d = ''): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_attr')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_textarea')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_url')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: $v);
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
        Functions\when('admin_url')->alias(static fn (string $p = ''): string => 'https://example.com/wp-admin/' . ltrim($p, '/'));
        Functions\when('home_url')->alias(static fn (string $p = ''): string => 'https://example.com' . $p);
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('submit_button')->justReturn('');
        Functions\when('checked')->alias(
            static fn (mixed $a, mixed $b, bool $echo = true): string => ( (string) $a === (string) $b && '' !== (string) $a ) || ( true === $a && true === $b ) ? 'checked="checked"' : ''
        );
        Functions\when('get_post_types')->alias(
            static function (array $a = [], string $o = ''): array {
                return [
                    'post' => (object) [ 'name' => 'post', 'label' => 'Posts' ],
                    'page' => (object) [ 'name' => 'page', 'label' => 'Pages' ],
                ];
            }
        );
        Functions\when('get_taxonomies')->alias(
            static function (array $a = [], string $o = ''): array {
                return [
                    'category' => (object) [ 'name' => 'category', 'label' => 'Categories' ],
                ];
            }
        );
        Functions\when('get_editable_roles')->alias(
            static fn (): array => [
                'administrator' => [ 'name' => 'Administrator' ],
                'editor'        => [ 'name' => 'Editor' ],
            ]
        );
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        $_POST                     = [];
        $_GET                      = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    private function makePage(): SitemapSettingsPage {
        return new SitemapSettingsPage(new SitemapSettings());
    }

    public function test_submenu_render_callback_renders_sitemap_page(): void {
        $menu = new AdminMenu(new SettingsStore(), new ModuleEnableMap());

        $captured = null;

        Functions\when('add_menu_page')->justReturn('toplevel_page_rankkernel');
        Functions\when('add_action')->justReturn(true);
        Functions\when('add_submenu_page')->alias(
            static function (string $parent, string $title, string $menu, string $cap, string $slug, mixed $cb) use (&$captured): string {
                $captured = $cb;

                return 'rankkernel_page_rankkernel-sitemap';
            }
        );

        $menu->addMenuPage();

        $this->assertIsCallable($captured);

        ob_start();
        $captured();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Sitemap Settings', $html);
        $this->assertStringContainsString('nav-tab-wrapper', $html);
    }

    public function test_save_general_saves_invalidates_once_and_redirects(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);

        $redirect = null;

        Functions\when('wp_safe_redirect')->alias(
            static function (string $url) use (&$redirect): bool {
                $redirect = $url;

                return true;
            }
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['tab']               = 'general';
        $_POST                     = [
            'rankkernel_sitemap_save' => '1',
            '_wpnonce'                => 'valid',
            'items_per_page'          => '250',
            'include_images'          => '1',
            'exclude_posts'           => '4,5',
            'exclude_terms'           => '',
        ];

        ob_start();
        $page->maybeHandleSave();
        ob_end_clean();

        $saved = $this->options[ SitemapSettings::OPTION ];

        $this->assertSame(250, $saved['items_per_page']);
        $this->assertTrue($saved['include_images']);
        $this->assertFalse($saved['include_featured_image']);
        $this->assertSame([ 4, 5 ], $saved['exclude_posts']);
        $this->assertSame([], $saved['exclude_terms']);
        $this->assertFalse($saved['include_empty_terms']);

        $validatorWrites = 0;

        foreach ($this->options as $key => $value) {
            if (SitemapCache::VALIDATOR_GLOBAL === $key) {
                ++$validatorWrites;
            }
        }

        $this->assertSame(1, $validatorWrites, 'Save bumps the global validator exactly once');
        $this->assertSame(
            'https://example.com/wp-admin/admin.php?page=rankkernel-sitemap&tab=general&settings-updated=1',
            $redirect
        );
    }

    public function test_save_post_types_checkbox_semantics(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('wp_safe_redirect')->justReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['tab']               = 'post-types';
        $_POST                     = [
            'rankkernel_sitemap_save' => '1',
            '_wpnonce'                => 'valid',
            'pt_post_sitemap'         => '1',
        ];

        ob_start();
        $page->maybeHandleSave();
        ob_end_clean();

        $saved = $this->options[ SitemapSettings::OPTION ];

        $this->assertTrue($saved['pt_post_sitemap']);
        $this->assertFalse($saved['pt_page_sitemap']);
    }

    public function test_save_authors_tab(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);

        $redirect = null;

        Functions\when('wp_safe_redirect')->alias(
            static function (string $url) use (&$redirect): bool {
                $redirect = $url;

                return true;
            }
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['tab']               = 'authors';
        $_POST                     = [
            'rankkernel_sitemap_save' => '1',
            '_wpnonce'                => 'valid',
            'authors_sitemap'         => '1',
            'authors_exclude_roles'   => [ 'editor', 'editor', '' ],
            'authors_exclude_users'   => '8,9',
        ];

        ob_start();
        $page->maybeHandleSave();
        ob_end_clean();

        $saved = $this->options[ SitemapSettings::OPTION ];

        $this->assertTrue($saved['authors_sitemap']);
        $this->assertFalse($saved['authors_include_empty']);
        $this->assertSame([ 'editor' ], $saved['authors_exclude_roles']);
        $this->assertSame([ 8, 9 ], $saved['authors_exclude_users']);
        $this->assertStringContainsString('tab=authors', (string) $redirect);
    }

    public function test_invalid_nonce_no_save_wp_die(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(false);
        Functions\expect('wp_die')->once()->andReturnUsing(
            static function (): void {
                throw new \RuntimeException('wp_die');
            }
        );
        Functions\expect('update_option')->never();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['tab']               = 'general';
        $_POST                     = [
            'rankkernel_sitemap_save' => '1',
            '_wpnonce'                => 'bad',
            'items_per_page'          => '250',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die');

        ob_start();
        try {
            $page->maybeHandleSave();
        } finally {
            ob_end_clean();
        }
    }

    public function test_missing_caps_wp_die(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('wp_die')->once()->andReturnUsing(
            static function (): void {
                throw new \RuntimeException('wp_die');
            }
        );
        Functions\expect('update_option')->never();
        Functions\expect('check_admin_referer')->never();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST                     = [
            'rankkernel_sitemap_save' => '1',
            '_wpnonce'                => 'valid',
        ];

        $this->expectException(\RuntimeException::class);

        ob_start();
        try {
            $page->maybeHandleSave();
        } finally {
            ob_end_clean();
        }
    }

    public function test_unknown_tab_falls_back_to_general(): void {
        $page = $this->makePage();

        $_GET['tab'] = 'nope';

        $this->assertSame('general', $page->currentTab());

        ob_start();
        $page->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('General', $html);
        $this->assertStringContainsString('tab=general" class="nav-tab nav-tab-active', $html);
    }

    public function test_render_authors_tab_shows_roles_and_active_tab(): void {
        $page = $this->makePage();

        $_GET['tab'] = 'authors';

        ob_start();
        $page->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('tab=authors" class="nav-tab nav-tab-active', $html);
        $this->assertStringContainsString('Administrator', $html);
        $this->assertStringContainsString('name="authors_exclude_roles[]"', $html);
        $this->assertStringContainsString('name="authors_exclude_users"', $html);
    }

    public function test_render_post_types_tab_shows_toggles_and_urls(): void {
        $page = $this->makePage();

        $_GET['tab'] = 'post-types';

        ob_start();
        $page->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('name="pt_post_sitemap"', $html);
        $this->assertStringContainsString('https://example.com/post-sitemap.xml', $html);
        $this->assertStringContainsString('Posts', $html);
    }
}
