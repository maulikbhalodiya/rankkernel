<?php
/**
 * Schema settings tests, defaults, sanitize, piece wiring, admin page, menu.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\AdminMenu;
use RankKernel\Admin\SchemaSettingsPage;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Schema\Pieces\ArticlePiece;
use RankKernel\Modules\Schema\Pieces\BookPiece;
use RankKernel\Modules\Schema\Pieces\BreadcrumbPiece;
use RankKernel\Modules\Schema\Pieces\PersonPiece;
use RankKernel\Modules\Schema\blocks\FaqBlock;
use RankKernel\Modules\Schema\SchemaModule;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Plugin;
use RankKernel\Settings\SettingsStore;
use WP_Query;

final class SchemaSettingsAdminTest extends TestCase {
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
        Functions\when('plugins_url')->alias(static fn (string $p, string $f = ''): string => 'https://example.com/wp-content/plugins/rankkernel/' . $p);
        Functions\when('wp_register_script')->justReturn(null);
        Functions\when('wp_register_style')->justReturn(null);
        Functions\when('esc_url_raw')->alias(
            static function (string $v): string {
                $v = trim($v);

                if ('' === $v) {
                    return '';
                }

                if (str_starts_with($v, 'https://') || str_starts_with($v, 'http://')) {
                    return $v;
                }

                return '';
            }
        );
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
        Functions\when('admin_url')->alias(static fn (string $p = ''): string => 'https://example.com/wp-admin/' . ltrim($p, '/'));
        Functions\when('home_url')->alias(static fn (string $p = ''): string => 'https://example.com' . $p);
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('submit_button')->justReturn('');
        Functions\when('checked')->alias(
            static fn (mixed $a, mixed $b, bool $echo = true): string => ( (string) $a === (string) $b && '' !== (string) $a ) || ( true === $a && true === $b ) ? 'checked="checked"' : ''
        );
        Functions\when('selected')->alias(
            static fn (mixed $a, mixed $b, bool $echo = true): string => (string) $a === (string) $b ? 'selected="selected"' : ''
        );
        Functions\when('get_post_types')->alias(
            static function (array $a = [], string $o = ''): array {
                return [
                    'post' => (object) [ 'name' => 'post', 'label' => 'Posts' ],
                    'page' => (object) [ 'name' => 'page', 'label' => 'Pages' ],
                ];
            }
        );
        Functions\when('is_preview')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('is_front_page')->justReturn(false);
        Functions\when('is_author')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('get_term_meta')->justReturn([]);
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
        Functions\when('trailingslashit')->alias(static fn (string $v): string => rtrim($v, '/') . '/');
        Functions\when('get_bloginfo')->justReturn('My Site');
        Functions\when('get_permalink')->justReturn('https://example.com/hello/');
        Functions\when('get_the_title')->justReturn('Hello Post');
        Functions\when('get_the_excerpt')->justReturn('Excerpt text');
        Functions\when('get_post_field')->justReturn('');
        Functions\when('get_post_type')->justReturn('post');
        Functions\when('get_the_date')->justReturn('2026-01-01T00:00:00+00:00');
        Functions\when('get_the_modified_date')->justReturn('2026-02-01T00:00:00+00:00');
        Functions\when('get_author_posts_url')->justReturn('https://example.com/author/bob/');
        Functions\when('get_the_author_meta')->justReturn('Bob');
        Functions\when('wp_strip_all_tags')->alias(static fn (string $s): string => strip_tags($s));
        Functions\when('wp_get_attachment_image_url')->justReturn('');
        Functions\when('wp_get_attachment_url')->justReturn('');
        Functions\when('get_post_thumbnail_id')->justReturn(0);
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        $_POST                     = [];
        $_GET                      = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $ref  = new \ReflectionClass(Plugin::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Build a singular query mock.
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

    /**
     * @param array<string, mixed> $overrides Stored settings overrides.
     */
    private function makeContext( WP_Query $query, array $overrides = [] ): Context {
        $stored = array_merge(SettingsStore::defaults(), $overrides);
        Functions\when('get_option')->alias(
            function (string $key, mixed $default = false) use ($stored): mixed {
                if ('rankkernel_settings' === $key) {
                    return $stored;
                }

                return $this->options[ $key ] ?? $default;
            }
        );

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

    private function makePage(): SchemaSettingsPage {
        return new SchemaSettingsPage(new SettingsStore());
    }

    public function test_new_toggle_defaults_are_true(): void {
        $all = ( new SettingsStore() )->all();

        $this->assertTrue($all['schema_breadcrumbs']);
        $this->assertTrue($all['schema_author']);
    }

    public function test_toggle_sanitize_casts_to_bool(): void {
        $store = new SettingsStore();

        $this->assertTrue($store->set([ 'schema_breadcrumbs' => 'yes' ]));
        $this->assertTrue($this->options[ SettingsStore::OPTION ]['schema_breadcrumbs']);

        $this->assertTrue($store->set([ 'schema_author' => false ]));
        $this->assertFalse($this->options[ SettingsStore::OPTION ]['schema_author']);
    }

    public function test_valid_default_type_is_stored(): void {
        $store = new SettingsStore();

        $this->assertTrue($store->set([ 'schema_default_post' => 'NewsArticle' ]));
        $this->assertSame('NewsArticle', $this->options[ SettingsStore::OPTION ]['schema_default_post']);
    }

    public function test_dashed_post_type_slug_default_is_stored(): void {
        $store = new SettingsStore();

        $this->assertTrue($store->set([ 'schema_default_my-type' => 'Event' ]));
        $this->assertSame('Event', $this->options[ SettingsStore::OPTION ]['schema_default_my-type']);
    }

    public function test_invalid_default_type_is_dropped_never_stored(): void {
        $store = new SettingsStore();

        $this->assertFalse($store->set([ 'schema_default_post' => 'EvilType' ]));
        $this->assertArrayNotHasKey(SettingsStore::OPTION, $this->options);

        $this->assertSame('', $store->get('schema_default_post', ''));
    }

    public function test_empty_default_type_means_automatic(): void {
        $store = new SettingsStore();

        $this->assertTrue($store->set([ 'schema_default_page' => '' ]));
        $this->assertSame('', $this->options[ SettingsStore::OPTION ]['schema_default_page']);
    }

    public function test_default_for_post_type_mapping(): void {
        $this->assertSame('BlogPosting', SchemaTypes::defaultForPostType('post'));
        $this->assertSame('Article', SchemaTypes::defaultForPostType('page'));
        $this->assertSame('Article', SchemaTypes::defaultForPostType('product'));
        $this->assertSame('Article', SchemaTypes::defaultForPostType(''));
    }

    public function test_article_payload_type_beats_setting_beats_mapping(): void {
        Functions\when('get_post_type')->justReturn('post');

        $this->stubPostMeta([ 'schema' => [ 'type' => 'NewsArticle' ] ]);
        $ctx = $this->makeContext($this->singularQuery(), [ 'schema_default_post' => 'BlogPosting' ]);

        $this->assertSame('NewsArticle', ( new ArticlePiece(new SettingsStore()) )->build($ctx)['@type']);

        $this->stubPostMeta([]);
        $ctx = $this->makeContext($this->singularQuery(), [ 'schema_default_post' => 'NewsArticle' ]);

        $this->assertSame('NewsArticle', ( new ArticlePiece(new SettingsStore()) )->build($ctx)['@type']);

        $ctx = $this->makeContext($this->singularQuery());

        $this->assertSame('BlogPosting', ( new ArticlePiece(new SettingsStore()) )->build($ctx)['@type']);
    }

    public function test_setting_routes_primary_to_owning_piece_for_cpt(): void {
        Functions\when('get_post_type')->justReturn('book');

        $this->stubPostMeta([]);
        $ctx = $this->makeContext($this->singularQuery(), [ 'schema_default_book' => 'Book' ]);

        $this->assertFalse(( new ArticlePiece(new SettingsStore()) )->isNeeded($ctx));
        $this->assertTrue(( new BookPiece(new SettingsStore()) )->isNeeded($ctx));
        $this->assertSame('Book', ( new BookPiece(new SettingsStore()) )->build($ctx)['@type']);
    }

    public function test_article_invalid_setting_falls_back_to_mapping(): void {
        Functions\when('get_post_type')->justReturn('post');

        $this->stubPostMeta([]);
        $ctx = $this->makeContext($this->singularQuery(), [ 'schema_default_post' => 'EvilType' ]);

        $this->assertSame('BlogPosting', ( new ArticlePiece(new SettingsStore()) )->build($ctx)['@type']);
    }

    public function test_breadcrumb_gated_by_toggle(): void {
        $singular = $this->singularQuery();

        $ctx = $this->makeContext($singular, [ 'schema_breadcrumbs' => false ]);

        $this->assertFalse(( new BreadcrumbPiece(new SettingsStore()) )->isNeeded($ctx));

        $ctx = $this->makeContext($singular);

        $this->assertTrue(( new BreadcrumbPiece(new SettingsStore()) )->isNeeded($ctx));
    }

    public function test_person_gated_by_toggle(): void {
        Functions\when('get_post_field')->alias(
            static fn (string $field, int $id): string => 'post_author' === $field ? '7' : ''
        );

        $singular = $this->singularQuery();

        $ctx = $this->makeContext($singular, [ 'schema_author' => false ]);

        $this->assertFalse(( new PersonPiece(new SettingsStore()) )->isNeeded($ctx));

        $ctx = $this->makeContext($singular);

        $this->assertTrue(( new PersonPiece(new SettingsStore()) )->isNeeded($ctx));
    }

    public function test_render_shows_identity_defaults_and_tools(): void {
        $page = $this->makePage();

        ob_start();
        $page->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Schema Settings', $html);
        $this->assertStringContainsString('name="site_represents"', $html);
        $this->assertStringContainsString('name="org_name"', $html);
        $this->assertStringContainsString('name="org_logo"', $html);
        $this->assertStringContainsString('rk-org-logo-preview', $html);
        $this->assertStringContainsString('rk-org-logo-select', $html);
        $this->assertStringContainsString('rk-org-logo-remove', $html);
        $this->assertStringContainsString('Select image', $html);
        $this->assertStringContainsString('name="org_sameas"', $html);
        $this->assertStringContainsString('name="website_search_action"', $html);
        $this->assertStringContainsString('name="schema_default_post"', $html);
        $this->assertStringContainsString('name="schema_default_page"', $html);
        $this->assertStringContainsString('Posts', $html);
        $this->assertStringContainsString('Automatic', $html);
        $this->assertStringContainsString('name="schema_breadcrumbs"', $html);
        $this->assertStringContainsString('name="schema_author"', $html);
        $this->assertStringContainsString('Rich Results Test', $html);
        $this->assertStringContainsString('Schema Validator', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
        $this->assertStringContainsString('test/rich-results?url=https%3A%2F%2Fexample.com', $html);
    }

    public function test_render_reflects_stored_values(): void {
        $this->options[ SettingsStore::OPTION ] = [
            'site_represents'     => 'person',
            'org_name'            => 'Acme',
            'schema_default_post' => 'NewsArticle',
            'schema_breadcrumbs'  => false,
        ];

        ob_start();
        $this->makePage()->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('value="person"selected', $html);
        $this->assertStringContainsString('value="Acme"', $html);
        $this->assertStringContainsString('value="NewsArticle"selected', $html);
        $this->assertStringNotContainsString('name="schema_breadcrumbs" value="1" checked', $html);
        $this->assertStringContainsString('name="schema_author" value="1" checked', $html);
    }

    public function test_save_persists_and_redirects(): void {
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
        $_POST                     = [
            'rankkernel_schema_save' => '1',
            '_wpnonce'               => 'valid',
            'site_represents'        => 'person',
            'org_name'               => 'Acme',
            'org_logo'               => 'https://example.com/logo.png',
            'org_sameas'             => "https://example.com/a\nnot-a-url\n",
            'website_search_action'  => '1',
            'schema_default_post'    => 'NewsArticle',
            'schema_default_page'    => '',
            'schema_breadcrumbs'     => '1',
        ];

        ob_start();
        $page->maybeHandleSave();
        ob_end_clean();

        $saved = $this->options[ SettingsStore::OPTION ];

        $this->assertSame('person', $saved['site_represents']);
        $this->assertSame('Acme', $saved['org_name']);
        $this->assertSame('https://example.com/logo.png', $saved['org_logo']);
        $this->assertSame([ 'https://example.com/a' ], $saved['org_sameas']);
        $this->assertTrue($saved['website_search_action']);
        $this->assertSame('NewsArticle', $saved['schema_default_post']);
        $this->assertSame('', $saved['schema_default_page']);
        $this->assertTrue($saved['schema_breadcrumbs']);
        $this->assertFalse($saved['schema_author']);
        $this->assertSame(
            'https://example.com/wp-admin/admin.php?page=rankkernel-schema&settings-updated=1',
            $redirect
        );
    }

    public function test_save_drops_unknown_type_value(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('wp_safe_redirect')->justReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST                     = [
            'rankkernel_schema_save' => '1',
            '_wpnonce'               => 'valid',
            'schema_default_post'    => 'EvilType',
        ];

        ob_start();
        $page->maybeHandleSave();
        ob_end_clean();

        $saved = $this->options[ SettingsStore::OPTION ] ?? [];

        $this->assertArrayNotHasKey('schema_default_post', $saved);
    }

    public function test_save_rejects_bad_nonce(): void {
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
        $_POST                     = [
            'rankkernel_schema_save' => '1',
            '_wpnonce'               => 'bad',
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

    public function test_save_rejects_missing_caps(): void {
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
            'rankkernel_schema_save' => '1',
            '_wpnonce'               => 'valid',
        ];

        $this->expectException(\RuntimeException::class);

        ob_start();
        try {
            $page->maybeHandleSave();
        } finally {
            ob_end_clean();
        }
    }

    public function test_block_register_hooks_category_and_block_type(): void {
        $block = new FaqBlock();

        Functions\expect('add_filter')->never();

        Functions\expect('register_block_type')
            ->once()
            ->with(
                \Mockery::on(
                    static function (string $path): bool {
                        return str_ends_with($path, '/faq')
                            && is_readable($path . '/block.json');
                    }
                ),
                \Mockery::type('array')
            )
            ->andReturn(true);

        $block->register();
    }

    public function test_schema_boot_registers_block_behind_enable_gate(): void {
        Functions\when('add_action')->justReturn(true);
        Functions\when('add_filter')->justReturn(true);
        Functions\expect('register_block_type')->twice()->andReturn(true);

        ( new SchemaModule() )->boot();
    }

    public function test_schema_submenu_registered_with_exact_args(): void {
        $menu = new AdminMenu(new SettingsStore(), new ModuleEnableMap());

        Functions\expect('add_submenu_page')
            ->once()
            ->with(
                'rankkernel',
                'Schema Settings',
                'Schema',
                'manage_options',
                'rankkernel-schema',
                \Mockery::type('callable')
            )
            ->andReturn('rankkernel_page_rankkernel-schema');

        Functions\expect('add_action')
            ->once()
            ->with('load-rankkernel_page_rankkernel-schema', \Mockery::type('callable'))
            ->andReturn(true);

        $menu->addSchemaPage();

        $this->assertInstanceOf(SchemaSettingsPage::class, $menu->getSchemaPage());
    }

    public function test_admin_only_wiring_registers_nothing_outside_admin(): void {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('add_action')->justReturn(true);
        Functions\when('add_filter')->justReturn(true);
        Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));

        Plugin::getInstance()->registerCoreServices();

        $this->expectException(\RuntimeException::class);

        Plugin::getInstance()->get('admin_menu');
    }

    public function test_admin_wiring_registers_menu_inside_admin(): void {
        $hooked = [];

        Functions\when('is_admin')->justReturn(true);
        Functions\when('add_action')->alias(
            static function (string $hook, mixed $cb) use (&$hooked): bool {
                $hooked[] = $hook;

                return true;
            }
        );
        Functions\when('add_filter')->justReturn(true);

        Plugin::getInstance()->registerCoreServices();

        $this->assertInstanceOf(AdminMenu::class, Plugin::getInstance()->get('admin_menu'));
        $this->assertContains('admin_menu', $hooked);
    }
}
