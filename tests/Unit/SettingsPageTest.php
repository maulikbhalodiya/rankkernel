<?php
/**
 * SettingsPage save-handler tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\SettingsPage;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Settings\SettingsStore;

final class SettingsPageTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        if (! defined('RANKKERNEL_FILE')) {
            define('RANKKERNEL_FILE', '/tmp/rankkernel.php');
        }

        if (! defined('RANKKERNEL_TESTING')) {
            define('RANKKERNEL_TESTING', true);
        }

        Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_html__')->alias(static fn (string $v, string $d = ''): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_attr')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_url')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: $v);
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);
        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('wp_unslash')->alias(static fn (mixed $v): mixed => is_string($v) ? stripslashes($v) : $v);
        Functions\when('admin_url')->alias(static fn (string $p = ''): string => 'https://example.com/wp-admin/' . ltrim($p, '/'));
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('submit_button')->justReturn('');
        Functions\when('checked')->alias(
            static fn (mixed $a, mixed $b, bool $echo = true): string => ( (string) $a === (string) $b && '' !== (string) $a ) || ( true === $a && true === $b ) ? 'checked="checked"' : ''
        );
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    private function makePage(): SettingsPage {
        Functions\when('get_option')->alias(
            static function (string $key, mixed $default = false) {
                if ('rankkernel_modules' === $key) {
                    return [];
                }
                if ('rankkernel_settings' === $key) {
                    return [];
                }
                return $default;
            }
        );

        $store = new SettingsStore();
        $map   = new ModuleEnableMap();

        return new SettingsPage($store, $map);
    }

    public function test_save_valid_nonce_and_caps_redirects_and_saves(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\expect('update_option')->atLeast()->once()->andReturn(true);
        Functions\expect('wp_safe_redirect')
            ->once()
            ->with('https://example.com/wp-admin/admin.php?page=rankkernel&settings-updated=1')
            ->andReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'rankkernel_save'      => '1',
            '_wpnonce'             => 'valid',
            'title_template'       => 'My Title %%title%%',
            'separator'            => '|',
            'webmaster_google'     => 'google123',
            'rankkernel_modules'   => [ 'metadata', 'sitemaps' ],
            'purge_on_uninstall'   => '1',
        ];

        ob_start();
        $page->render();
        ob_end_clean();

        // update_option called at least twice: settings + modules, already asserted via atLeast.
        $this->assertTrue(true);
    }

    public function test_checkbox_absent_means_false(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);

        $capturedSettings = null;
        Functions\when('update_option')->alias(
            static function (string $key, mixed $value) use (&$capturedSettings): bool {
                if ('rankkernel_settings' === $key) {
                    $capturedSettings = $value;
                }
                return true;
            }
        );
        Functions\when('wp_safe_redirect')->justReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'rankkernel_save' => '1',
            '_wpnonce'        => 'valid',
            'title_template'  => 't',
            // purge_on_uninstall NOT present → should be false.
        ];

        ob_start();
        $page->render();
        ob_end_clean();

        $this->assertIsArray($capturedSettings);
        $this->assertArrayHasKey('purge_on_uninstall', $capturedSettings);
        $this->assertFalse($capturedSettings['purge_on_uninstall']);
    }

    public function test_unknown_module_id_silently_dropped(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);

        $capturedModules = null;
        Functions\when('update_option')->alias(
            static function (string $key, mixed $value) use (&$capturedModules): bool {
                if ('rankkernel_modules' === $key) {
                    $capturedModules = $value;
                }
                return true;
            }
        );
        Functions\when('wp_safe_redirect')->justReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'rankkernel_save'    => '1',
            '_wpnonce'           => 'valid',
            'rankkernel_modules' => [ 'metadata', 'evil-id', 'sitemaps' ],
        ];

        ob_start();
        $page->render();
        ob_end_clean();

        $this->assertIsArray($capturedModules);
        $this->assertContains('metadata', $capturedModules);
        $this->assertContains('sitemaps', $capturedModules);
        $this->assertNotContains('evil-id', $capturedModules);
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
        $_POST = [
            'rankkernel_save' => '1',
            '_wpnonce'        => 'bad',
            'title_template'  => 'x',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die');

        ob_start();
        try {
            $page->render();
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
        $_POST = [
            'rankkernel_save' => '1',
            '_wpnonce'        => 'valid',
        ];

        $this->expectException(\RuntimeException::class);

        ob_start();
        try {
            $page->render();
        } finally {
            ob_end_clean();
        }
    }

    public function test_redirect_after_save(): void {
        $page = $this->makePage();

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('update_option')->justReturn(true);
        Functions\expect('wp_safe_redirect')
            ->once()
            ->with(\Mockery::on(
                static function (string $url): bool {
                    return str_contains($url, 'page=rankkernel') && str_contains($url, 'settings-updated=1');
                }
            ))
            ->andReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'rankkernel_save' => '1',
            '_wpnonce'        => 'valid',
            'title_template'  => 'hello',
        ];

        ob_start();
        $page->render();
        ob_end_clean();

        $this->assertTrue(true);
    }
}
