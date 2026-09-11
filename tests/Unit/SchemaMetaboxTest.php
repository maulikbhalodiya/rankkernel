<?php
/**
 * SchemaMetabox tests, render, save, export, wiring.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\SchemaMetabox;
use RankKernel\Plugin;

final class SchemaMetaboxTest extends TestCase {
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

        if (! defined('RANKKERNEL_VERSION')) {
            define('RANKKERNEL_VERSION', '0.1.0-test');
        }

        Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
        Functions\when('esc_url_raw')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: '');
        Functions\when('absint')->alias(static fn (mixed $v): int => abs((int) $v));
        Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('get_option')->alias(static fn (string $k, mixed $d = false): mixed => $d);
        Functions\when('get_post_type')->alias(static fn (mixed $p = null): string => 'post');
        Functions\when('checked')->alias(
            static fn (mixed $a, mixed $b, bool $echo = true): string => ( (string) $a === (string) $b && '' !== (string) $a ) || ( true === $a && true === $b ) ? 'checked="checked"' : ''
        );
        Functions\when('esc_html__')->alias(static fn (string $v, string $d = ''): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_attr')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_textarea')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        Functions\when('esc_url')->alias(static fn (string $v): string => filter_var($v, FILTER_SANITIZE_URL) ?: $v);
        Functions\when('__')->alias(static fn (string $v, string $d = ''): string => $v);

        $unslash = static function (mixed $v) use (&$unslash): mixed {
            if (is_array($v)) {
                $out = [];

                foreach ($v as $k => $item) {
                    $out[ $k ] = $unslash($item);
                }

                return $out;
            }

            return is_string($v) ? stripslashes($v) : $v;
        };
        Functions\when('wp_unslash')->alias($unslash);

        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('wp_nonce_url')->alias(static fn (string $u, string $a = ''): string => $u . '&_wpnonce=test');
        Functions\when('admin_url')->alias(static fn (string $p = ''): string => 'https://example.com/wp-admin/' . ltrim($p, '/'));
        Functions\when('wp_json_encode')->alias(static fn (mixed $d, int $o = 0): string => (string) json_encode($d, $o));
        Functions\when('add_query_arg')->alias(
            static function (string $key, string $value, string $url): string {
                $sep = str_contains($url, '?') ? '&' : '?';

                return $url . $sep . $key . '=' . $value;
            }
        );
        Functions\when('wp_die')->alias(
            static function (string $msg = ''): void {
                throw new \RuntimeException('wp_die: ' . $msg);
            }
        );
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        $_POST  = [];
        $_GET   = [];
        $_FILES = [];

        $ref  = new \ReflectionClass(Plugin::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Stored payload for render tests.
     *
     * @return array<string, mixed>
     */
    private function renderPayload(): array {
        return [
            'title'  => 'Keep me',
            'schema' => [
                'type'   => 'FAQPage',
                'fields' => [
                    'headline'    => 'My Headline',
                    'description' => 'My description',
                ],
                'faq'    => [
                    'questions' => [
                        [ 'question' => 'What is this?', 'answer' => 'An answer.' ],
                        [ 'question' => 'Why?', 'answer' => 'Because.' ],
                    ],
                ],
                'howto'  => [
                    'name'      => 'Do the thing',
                    'steps'     => [
                        [ 'title' => 'Step one', 'text' => 'Do it.', 'image' => '' ],
                    ],
                    'totalTime' => 'PT5M',
                    'cost'      => 'Free',
                ],
                'custom' => [ 'foo' => 'bar' ],
            ],
        ];
    }

    private function stubRenderCommon( array $payload ): void {
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($payload): mixed {
                return $payload;
            }
        );
        Functions\when('get_permalink')->alias(static fn (int $id): string => 'https://example.com/hello/');
    }

    private function renderBox( int $postId = 7 ): string {
        $box  = new SchemaMetabox();
        $post = (object) [ 'ID' => $postId ];

        ob_start();
        $box->renderBox($post);

        return (string) ob_get_clean();
    }

    public function test_render_selector_fields_faq_links_and_controls(): void {
        $this->stubRenderCommon($this->renderPayload());

        $out = $this->renderBox();

        $this->assertStringContainsString('<select name="rankkernel_schema_type"', $out);
        $this->assertStringContainsString('value="FAQPage" selected="selected"', $out);
        $this->assertStringContainsString('value="My Headline"', $out);
        $this->assertStringNotContainsString('name="rankkernel_schema_faq[', $out);
        $this->assertStringNotContainsString('rankkernel_schema_howto_name', $out);
        $this->assertStringContainsString('All required fields for FAQPage are present.', $out);
        $this->assertStringContainsString('rich-results', $out);
        $this->assertStringContainsString('validator.schema.org', $out);
        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('rel="noopener"', $out);
        $this->assertStringContainsString('rankkernel_schema_export', $out);
        $this->assertStringContainsString('name="rankkernel_schema_import"', $out);
        $this->assertStringContainsString('accept=".json', $out);
        $this->assertStringContainsString('name="rankkernel_schema_custom"', $out);
    }

    public function test_render_automatic_shows_resolved_default(): void {
        $payload = $this->renderPayload();
        $payload['schema']['type'] = '';

        $this->stubRenderCommon($payload);

        $out = $this->renderBox();

        $this->assertStringContainsString('Automatic (Blog Posting)', $out);
    }

    public function test_render_disable_checkbox_and_conditional_rows(): void {
        $this->stubRenderCommon($this->renderPayload());

        $out = $this->renderBox();

        $this->assertStringContainsString('name="rankkernel_schema_disabled"', $out);
        $this->assertStringContainsString('Disable schema output for this post', $out);
        $this->assertStringContainsString('data-rankkernel-field-types="Product,Event,Service,SoftwareApplication"', $out);
        $this->assertStringContainsString('Manual field overrides (optional)', $out);
        $this->assertStringContainsString('Advanced: custom JSON, import, export', $out);
    }

    public function test_render_validation_lists_missing_event_fields(): void {
        $payload             = $this->renderPayload();
        $payload['schema']   = [
            'type'   => 'Event',
            'fields' => [],
            'faq'    => [ 'questions' => [] ],
            'howto'  => [ 'name' => '', 'steps' => [], 'totalTime' => '', 'cost' => '' ],
            'custom' => [],
        ];
        $this->stubRenderCommon($payload);

        $out = $this->renderBox();

        $this->assertStringContainsString('Name (headline) is required for Event.', $out);
        $this->assertStringContainsString('Start date is required for Event.', $out);
        $this->assertStringContainsString('Location name is required for Event.', $out);
    }

    public function test_render_escapes_script_answer(): void {
        $payload = $this->renderPayload();
        $payload['schema']['faq']['questions'] = [
            [ 'question' => 'XSS?', 'answer' => '<script>alert(1)</script>' ],
        ];
        $this->stubRenderCommon($payload);

        $out = $this->renderBox();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
    }

    /**
     * Base stored payload for save tests.
     *
     * @return array<string, mixed>
     */
    private function storedPayload(): array {
        return [
            'title'          => 'Keep me',
            'description'    => 'Keep desc',
            'canonical'      => 'https://example.com/keep/',
            'robots'         => [ 'index' => true, 'follow' => true ],
            'og'             => [ 'title' => 'OG', 'image_id' => 3 ],
            'twitter'        => [ 'card' => 'summary' ],
            'focus_keywords' => [ 'keep' ],
            'schema'         => [ 'type' => 'Article', 'fields' => [ 'headline' => 'Old' ] ],
            'flags'          => [ 'pillar' => true ],
        ];
    }

    /**
     * Valid POST body for save tests.
     *
     * @return array<string, mixed>
     */
    private function validPost(): array {
        return [
            'rankkernel_schema_nonce'        => 'valid',
            'rankkernel_schema_type'         => 'Product',
            'rankkernel_schema_fields'       => [
                'headline' => 'Widget',
                'price'    => '9.99',
                'evil'     => 'dropped',
            ],
            'rankkernel_schema_faq'          => [
                [ 'question' => 'Good?', 'answer' => 'Yes.' ],
                [ 'question' => '', 'answer' => 'Dropped row.' ],
            ],
            'rankkernel_schema_howto_name'   => 'Build it',
            'rankkernel_schema_howto_steps'  => [
                [ 'title' => 'Step one', 'text' => 'Do it.', 'image' => '' ],
            ],
            'rankkernel_schema_howto_totaltime' => 'PT5M',
            'rankkernel_schema_howto_cost'   => 'Free',
            'rankkernel_schema_custom'       => '{"foo":"bar"}',
        ];
    }

    public function test_save_valid_post_merges_schema_subtree_only(): void {
        $stored = $this->storedPayload();
        $saved  = null;
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($stored): mixed {
                return $stored;
            }
        );
        Functions\when('update_post_meta')->alias(
            static function (int $id, string $key, mixed $value) use (&$saved): bool {
                $saved = $value;

                return true;
            }
        );

        $_POST = $this->validPost();

        $box = new SchemaMetabox();
        $box->handleSave(11, (object) [ 'ID' => 11 ]);

        $redirect = $box->filterRedirect('https://example.com/wp-admin/post.php?post=11&action=edit');
        $this->assertStringContainsString('rankkernel_schema_msg=saved', $redirect);

        $this->assertIsArray($saved);
        $this->assertSame('Keep me', $saved['title']);
        $this->assertSame('Keep desc', $saved['description']);
        $this->assertSame('https://example.com/keep/', $saved['canonical']);
        $this->assertSame($stored['robots'], $saved['robots']);
        $this->assertSame($stored['og'], $saved['og']);
        $this->assertSame($stored['twitter'], $saved['twitter']);
        $this->assertSame([ 'keep' ], $saved['focus_keywords']);
        $this->assertSame($stored['flags'], $saved['flags']);
        $this->assertSame('Product', $saved['schema']['type']);
        $this->assertSame('Widget', $saved['schema']['fields']['headline']);
        $this->assertSame('9.99', $saved['schema']['fields']['price']);
        $this->assertArrayNotHasKey('evil', $saved['schema']['fields']);
        $this->assertCount(1, $saved['schema']['faq']['questions']);
        $this->assertSame('Good?', $saved['schema']['faq']['questions'][0]['question']);
        $this->assertSame([ 'foo' => 'bar' ], $saved['schema']['custom']);
    }

    public function test_save_disabled_flag_stored(): void {
        $stored = $this->storedPayload();
        $saved  = null;
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($stored): mixed {
                return $stored;
            }
        );
        Functions\when('update_post_meta')->alias(
            static function (int $id, string $key, mixed $value) use (&$saved): bool {
                $saved = $value;

                return true;
            }
        );

        $_POST = $this->validPost();
        $_POST['rankkernel_schema_disabled'] = '1';

        $box = new SchemaMetabox();
        $box->handleSave(11, (object) [ 'ID' => 11 ]);

        $this->assertTrue($saved['schema']['disabled']);
    }

    public function test_save_preserves_stored_faq_howto_without_posted_keys(): void {
        $stored = $this->storedPayload();
        $stored['schema']['faq']   = [ 'questions' => [ [ 'question' => 'Kept?', 'answer' => 'Kept.' ] ] ];
        $stored['schema']['howto'] = [ 'name' => 'Kept How', 'steps' => [], 'totalTime' => '', 'cost' => '' ];
        $saved  = null;
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($stored): mixed {
                return $stored;
            }
        );
        Functions\when('update_post_meta')->alias(
            static function (int $id, string $key, mixed $value) use (&$saved): bool {
                $saved = $value;

                return true;
            }
        );

        $_POST = $this->validPost();
        unset($_POST['rankkernel_schema_faq'], $_POST['rankkernel_schema_howto_name'], $_POST['rankkernel_schema_howto_steps'], $_POST['rankkernel_schema_howto_totaltime'], $_POST['rankkernel_schema_howto_cost']);

        $box = new SchemaMetabox();
        $box->handleSave(11, (object) [ 'ID' => 11 ]);

        $this->assertSame([ 'questions' => [ [ 'question' => 'Kept?', 'answer' => 'Kept.' ] ] ], $saved['schema']['faq']);
        $this->assertSame('Kept How', $saved['schema']['howto']['name']);
    }

    public function test_save_autosave_skipped(): void {
        Functions\when('wp_is_post_autosave')->justReturn(true);
        Functions\expect('update_post_meta')->never();

        $_POST = $this->validPost();

        $box = new SchemaMetabox();
        $box->handleSave(11, (object) [ 'ID' => 11 ]);

        $this->assertTrue(true);
    }

    public function test_save_revision_skipped(): void {
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(99);
        Functions\expect('update_post_meta')->never();

        $_POST = $this->validPost();

        $box = new SchemaMetabox();
        $box->handleSave(99, (object) [ 'ID' => 99 ]);

        $this->assertTrue(true);
    }

    public function test_save_missing_caps_wp_die(): void {
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('update_post_meta')->never();

        $_POST = $this->validPost();

        $box = new SchemaMetabox();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die');
        $box->handleSave(11, (object) [ 'ID' => 11 ]);
    }

    public function test_save_bad_nonce_wp_die(): void {
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(false);
        Functions\expect('update_post_meta')->never();

        $_POST = $this->validPost();

        $box = new SchemaMetabox();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die');
        $box->handleSave(11, (object) [ 'ID' => 11 ]);
    }

    public function test_save_invalid_custom_json_error_path(): void {
        $stored = $this->storedPayload();
        $saved  = null;
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($stored): mixed {
                return $stored;
            }
        );
        Functions\when('update_post_meta')->alias(
            static function (int $id, string $key, mixed $value) use (&$saved): bool {
                $saved = $value;

                return true;
            }
        );

        $_POST                 = $this->validPost();
        $_POST['rankkernel_schema_custom'] = '{broken';

        $box = new SchemaMetabox();
        $box->handleSave(11, (object) [ 'ID' => 11 ]);

        $redirect = $box->filterRedirect('https://example.com/wp-admin/post.php');
        $this->assertStringContainsString('rankkernel_schema_msg=invalid-json', $redirect);
        $this->assertIsArray($saved);
        $this->assertSame([], $saved['schema']['custom']);
        $this->assertSame('Widget', $saved['schema']['fields']['headline']);
        $this->assertSame('Keep me', $saved['title']);
    }

    private function writeImportTmp( string $contents ): string {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'rkimport');
        file_put_contents($tmp, $contents);

        return $tmp;
    }

    public function test_save_import_valid_replaces_schema(): void {
        $stored = $this->storedPayload();
        $saved  = null;
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($stored): mixed {
                return $stored;
            }
        );
        Functions\when('update_post_meta')->alias(
            static function (int $id, string $key, mixed $value) use (&$saved): bool {
                $saved = $value;

                return true;
            }
        );
        Functions\when('wp_check_filetype')->alias(
            static fn (string $f, array $m): array => [ 'ext' => 'json', 'type' => 'application/json' ]
        );

        $tmp = $this->writeImportTmp('{"type":"Product","fields":{"headline":"Imported"},"custom":{"a":1}}');

        try {
            $_POST  = [ 'rankkernel_schema_nonce' => 'valid' ];
            $_FILES = [
                'rankkernel_schema_import' => [
                    'name'     => 'post-schema.json',
                    'type'     => 'application/json',
                    'tmp_name' => $tmp,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => 99,
                ],
            ];

            $box = new SchemaMetabox(static fn (string $p): bool => true);
            $box->handleSave(11, (object) [ 'ID' => 11 ]);

            $redirect = $box->filterRedirect('https://example.com/wp-admin/post.php');
            $this->assertStringContainsString('rankkernel_schema_msg=saved', $redirect);
            $this->assertIsArray($saved);
            $this->assertSame('Product', $saved['schema']['type']);
            $this->assertSame('Imported', $saved['schema']['fields']['headline']);
            $this->assertSame([ 'a' => 1 ], $saved['schema']['custom']);
            $this->assertSame('Keep me', $saved['title']);
        } finally {
            unlink($tmp);
        }
    }

    public function test_save_import_garbage_saves_nothing(): void {
        $stored = $this->storedPayload();
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($stored): mixed {
                return $stored;
            }
        );
        Functions\expect('update_post_meta')->never();
        Functions\when('wp_check_filetype')->alias(
            static fn (string $f, array $m): array => [ 'ext' => 'json', 'type' => 'application/json' ]
        );

        $tmp = $this->writeImportTmp('not json{{{');

        try {
            $_POST  = [ 'rankkernel_schema_nonce' => 'valid' ];
            $_FILES = [
                'rankkernel_schema_import' => [
                    'name'     => 'post-schema.json',
                    'type'     => 'application/json',
                    'tmp_name' => $tmp,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => 11,
                ],
            ];

            $box = new SchemaMetabox(static fn (string $p): bool => true);
            $box->handleSave(11, (object) [ 'ID' => 11 ]);

            $redirect = $box->filterRedirect('https://example.com/wp-admin/post.php');
            $this->assertStringContainsString('rankkernel_schema_msg=invalid-import', $redirect);
        } finally {
            unlink($tmp);
        }
    }

    public function test_export_outputs_exact_schema_bytes(): void {
        $stored = $this->storedPayload();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(1);
        Functions\when('get_post_meta')->alias(
            static function (int $id, string $key, bool $single) use ($stored): mixed {
                return $stored;
            }
        );
        Functions\when('get_post_field')->alias(static fn (string $f, int $id): string => 'hello-world');
        Functions\when('sanitize_file_name')->alias(static fn (string $v): string => $v);

        $_GET = [ 'post' => '5' ];

        $box = new SchemaMetabox();

        ob_start();
        $box->handleExport();
        $out = (string) ob_get_clean();

        $this->assertSame((string) json_encode($stored['schema']), $out);
    }

    public function test_export_missing_caps_rejected(): void {
        Functions\when('current_user_can')->justReturn(false);

        $_GET = [ 'post' => '5' ];

        $box = new SchemaMetabox();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die');

        ob_start();
        try {
            $box->handleExport();
        } finally {
            ob_end_clean();
        }
    }

    public function test_export_bad_nonce_rejected(): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(false);

        $_GET = [ 'post' => '5' ];

        $box = new SchemaMetabox();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die');

        ob_start();
        try {
            $box->handleExport();
        } finally {
            ob_end_clean();
        }
    }

    public function test_add_boxes_skips_attachment_and_unknown_types(): void {
        Functions\when('get_post_types')->alias(static fn (array $a): array => [ 'post', 'page' ]);
        Functions\expect('add_meta_box')->never();

        $box = new SchemaMetabox();
        $box->addBoxes('attachment', (object) [ 'ID' => 1 ]);
        $box->addBoxes('evil', (object) [ 'ID' => 1 ]);

        $this->assertTrue(true);
    }

    public function test_add_boxes_registers_public_types(): void {
        Functions\when('get_post_types')->alias(static fn (array $a): array => [ 'post', 'page' ]);
        Functions\expect('add_meta_box')->once()->andReturnUsing(
            static function (string $id, string $title, callable $cb, string $type): void {
                \PHPUnit\Framework\Assert::assertSame('rankkernel-schema', $id);
                \PHPUnit\Framework\Assert::assertSame('post', $type);
            }
        );

        $box = new SchemaMetabox();
        $box->addBoxes('post', (object) [ 'ID' => 1 ]);

        $this->assertTrue(true);
    }

    public function test_enqueue_only_on_post_screens(): void {
        $screen       = new \stdClass();
        $screen->base = 'post';
        Functions\when('get_current_screen')->alias(static fn (): \stdClass => $screen);
        Functions\when('plugins_url')->alias(static fn (string $p, string $f = ''): string => 'https://example.com/wp-content/plugins/rankkernel/' . $p);
        Functions\when('wp_register_script')->justReturn(true);
        Functions\expect('wp_enqueue_script')->once()->with('rankkernel-schema-metabox');

        $box = new SchemaMetabox();
        $box->enqueueAssets('post.php');

        $this->assertTrue(true);
    }

    public function test_enqueue_skipped_off_post_screens(): void {
        Functions\when('get_current_screen')->alias(static fn (): \stdClass => new \stdClass());
        Functions\when('plugins_url')->alias(static fn (string $p, string $f = ''): string => 'x');
        Functions\expect('wp_register_script')->never();
        Functions\expect('wp_enqueue_script')->never();

        $box = new SchemaMetabox();
        $box->enqueueAssets('edit.php');

        $this->assertTrue(true);
    }

    private function stubCoreServices(): void {
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
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('get_term_meta')->justReturn([]);
        Functions\when('do_action')->justReturn(null);
        Functions\when('load_plugin_textdomain')->justReturn(true);
    }

    public function test_no_metabox_hooks_when_not_admin(): void {
        $this->stubCoreServices();
        Functions\when('is_admin')->justReturn(false);

        $hooks = [];
        Functions\when('add_action')->alias(
            static function (string $hook, mixed ...$rest) use (&$hooks): bool {
                $hooks[] = $hook;

                return true;
            }
        );
        Functions\when('add_filter')->justReturn(true);

        $plugin = Plugin::getInstance();
        $plugin->registerCoreServices();

        $this->assertNotContains('add_meta_boxes', $hooks);
        $this->assertNotContains('admin_post_rankkernel_schema_export', $hooks);

        $this->expectException(\RuntimeException::class);
        $plugin->get('schema_metabox');
    }

    public function test_metabox_registered_when_admin(): void {
        $this->stubCoreServices();
        Functions\when('is_admin')->justReturn(true);

        $hooks = [];
        Functions\when('add_action')->alias(
            static function (string $hook, mixed ...$rest) use (&$hooks): bool {
                $hooks[] = $hook;

                return true;
            }
        );
        Functions\when('add_filter')->justReturn(true);

        $plugin = Plugin::getInstance();
        $plugin->registerCoreServices();

        $this->assertContains('add_meta_boxes', $hooks);
        $this->assertContains('save_post', $hooks);
        $this->assertContains('admin_post_rankkernel_schema_export', $hooks);
        $this->assertInstanceOf(SchemaMetabox::class, $plugin->get('schema_metabox'));
    }
}
