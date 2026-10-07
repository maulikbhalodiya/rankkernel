<?php
/**
 * Support screen capability, nonce, upload and redirect tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\SupportPage;
use RankKernel\Support\SupportRequest;
use WP_Error;

/**
 * Support Page Test.
 */
final class SupportPageTest extends TestCase {

	/**
	 * Last redirect URL captured from wp_safe_redirect.
	 *
	 * @var string
	 */
	private string $lastRedirect = '';

	/**
	 * Transient storage.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Paths passed to wp_delete_file.
	 *
	 * @var array<int, string>
	 */
	private array $deleted = [];

	/**
	 * Mail payloads captured from wp_mail.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $mail = [];

	/**
	 * Whether the acting user may use the screen.
	 *
	 * @var bool
	 */
	private bool $allowed = true;

	/**
	 * Whether the nonce check passes.
	 *
	 * @var bool
	 */
	private bool $nonceOk = true;

	/**
	 * How many times wp_handle_upload was reached.
	 *
	 * @var int
	 */
	private int $uploadCalls = 0;

	/**
	 * Allowlist the last wp_handle_upload call received.
	 *
	 * @var array<string, string>
	 */
	private array $uploadMimes = [];

	/**
	 * Set up doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$_POST                     = [];
		$_GET                      = [];
		$_FILES                    = [];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REMOTE_ADDR']    = '203.0.113.7';

		$this->lastRedirect = '';
		$this->transients   = [];
		$this->deleted      = [];
		$this->mail         = [];
		$this->allowed      = true;
		$this->nonceOk      = true;
		$this->uploadCalls  = 0;
		$this->uploadMimes  = [];

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html' )->alias( static fn ( $text ): string => (string) $text );
		Functions\when( 'add_query_arg' )->alias(
			function ( $key, $value = null, $url = '' ): string {
				$params = is_array( $key ) ? $key : [ $key => $value ];

				if ( [] === $params ) {
					return (string) $url;
				}

				$sep = str_contains( (string) $url, '?' ) ? '&' : '?';

				return (string) $url . $sep . http_build_query( $params );
			}
		);
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo esc_html( $text );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( $v ) );
		Functions\when( 'sanitize_email' )->alias( static fn ( string $v ): string => trim( $v ) );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( $v ) => $v );
		Functions\when( 'is_email' )->alias( static fn ( string $e ): bool => (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ) );
		Functions\when( 'current_user_can' )->alias( fn (): bool => $this->allowed );
		Functions\when( 'check_admin_referer' )->alias( fn (): bool => $this->nonceOk );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-admin/' . ltrim( $p, '/' ) );
		Functions\when( 'plugins_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-content/plugins/rankkernel/' . $p );
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'get_bloginfo' )->justReturn( '6.9' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_get_theme' )->justReturn( false );
		Functions\when( 'time' )->alias( static fn (): int => 1000 );
		Functions\when( 'get_current_user_id' )->justReturn( 5 );
		Functions\when( 'get_option' )->alias( static fn ( string $n, $d = false ) => $d );
		Functions\when( 'wp_upload_dir' )->alias(
			static fn (): array => [
				'path'    => '/var/www/uploads',
				'basedir' => '/var/www/uploads',
			]
		);
		Functions\when( 'is_uploaded_file' )->justReturn( true );
		// No opinion by default, so the browser supplied type still decides.
		Functions\when( 'wp_check_filetype_and_ext' )->alias(
			static fn (): array => [
				'ext'  => false,
				'type' => false,
			]
		);
		Functions\when( 'wp_handle_upload' )->alias(
			function ( $file, $overrides = [] ): array {
				$this->uploadCalls++;
				$this->uploadMimes = (array) ( $overrides['mimes'] ?? [] );

				return [
					'path' => '/var/www/uploads/2026/10/shot.png',
					'type' => 'image/png',
				];
			}
		);
		Functions\when( 'wp_delete_file' )->alias(
			function ( string $path ): void {
				$this->deleted[] = $path;
			}
		);
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $message, $headers = [], $attachments = [] ): bool {
				$this->mail[] = [
					'to'          => $to,
					'subject'     => $subject,
					'message'     => $message,
					'headers'     => $headers,
					'attachments' => $attachments,
				];

				return true;
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url ): bool {
				$this->lastRedirect = $url;

				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( string $key ) {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value ): bool {
				$this->transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->transients[ $key ] );

				return true;
			}
		);
	}

	/**
	 * Tear down the fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		$_POST  = [];
		$_GET   = [];
		$_FILES = [];

		parent::tearDown();
	}

	/**
	 * Fills a valid submission into the superglobals.
	 *
	 * @param array<string, string> $overrides Field overrides.
	 * @return void
	 */
	private function postValid( array $overrides = [] ): void {
		$_POST = array_merge(
			[
				SupportRequest::FIELD_SUBMIT   => '1',
				'category'                     => 'bug',
				'subject'                      => 'Sitemap index missing',
				'message'                      => 'The sitemap index renders without child sitemaps.',
				'email'                        => 'owner@example.com',
				SupportRequest::FIELD_CONSENT  => '1',
				SupportRequest::FIELD_HONEYPOT => '',
			],
			$overrides
		);
	}

	/**
	 * A user without the capability is refused before anything else runs.
	 */
	public function test_a_user_without_the_capability_is_refused(): void {
		$this->allowed = false;
		$this->postValid();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		( new SupportPage() )->maybeHandleSave();
	}

	/**
	 * A failed nonce check is refused.
	 */
	public function test_a_failed_nonce_check_is_refused(): void {
		$this->nonceOk = false;
		$this->postValid();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		( new SupportPage() )->maybeHandleSave();
	}

	/**
	 * A GET request is left alone.
	 */
	public function test_a_get_request_does_nothing(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_POST                     = [ SupportRequest::FIELD_SUBMIT => '1' ];

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( '', $this->lastRedirect );
		$this->assertSame( [], $this->mail );
	}

	/**
	 * A POST without the submit marker is left alone.
	 */
	public function test_a_post_without_the_submit_marker_does_nothing(): void {
		$_POST = [ 'category' => 'bug' ];

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( '', $this->lastRedirect );
		$this->assertSame( [], $this->mail );
	}

	/**
	 * A filled honeypot is answered as sent and sends nothing.
	 */
	public function test_a_filled_honeypot_is_answered_as_sent_and_sends_nothing(): void {
		$this->postValid( [ SupportRequest::FIELD_HONEYPOT => 'https://spam.example' ] );

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( [], $this->mail );
		$this->assertStringContainsString( 'rk_support=sent', $this->lastRedirect );
	}

	/**
	 * A valid submission reaches the support inbox.
	 */
	public function test_a_valid_submission_reaches_the_support_inbox(): void {
		$this->postValid();

		( new SupportPage() )->maybeHandleSave();

		$this->assertCount( 1, $this->mail );
		$this->assertSame( SupportRequest::RECIPIENT, $this->mail[0]['to'] );
		$this->assertStringContainsString( 'rk_support=sent', $this->lastRedirect );
	}

	/**
	 * An invalid submission is bounced back for correction.
	 */
	public function test_an_invalid_submission_is_bounced_back(): void {
		$this->postValid( [ 'email' => 'not-an-address' ] );

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( [], $this->mail );
		$this->assertStringContainsString( 'rk_support=invalid', $this->lastRedirect );
	}

	/**
	 * A refused rate limit stops the send.
	 */
	public function test_a_refused_rate_limit_stops_the_send(): void {
		$this->postValid();

		// Fill the bucket, and give the window an expiry in the future.
		$this->transients[ 'rankkernel_support_rl_' . md5( '203.0.113.7' ) ] = SupportRequest::RATE_LIMIT_MAX;
		Functions\when( 'get_option' )->alias(
			static fn ( string $name, $fallback = false ) => str_starts_with( $name, '_transient_timeout_' )
				? 1300
				: $fallback
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		( new SupportPage() )->maybeHandleSave();
	}

	/**
	 * A rejected form never stores the screenshot.
	 *
	 * Storing first would leave a private image in a public uploads folder for
	 * a submission that was never sent.
	 */
	public function test_a_rejected_form_never_stores_the_screenshot(): void {
		$this->postValid( [ 'category' => '' ] );

		$_FILES = [
			SupportRequest::FIELD_SCREENSHOT => [
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
				'name'     => 'shot.png',
				'type'     => 'image/png',
				'tmp_name' => '/tmp/shot.png',
			],
		];

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( [], $this->mail );
		$this->assertSame( 0, $this->uploadCalls );
		$this->assertStringContainsString( 'rk_support=invalid', $this->lastRedirect );
		$this->assertSame( [], $this->deleted );
	}

	/**
	 * A stored screenshot rides along with the mail and is then removed.
	 */
	public function test_a_stored_screenshot_is_attached_then_removed(): void {
		$this->postValid();

		$_FILES = [
			SupportRequest::FIELD_SCREENSHOT => [
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
				'name'     => 'shot.png',
				'type'     => 'image/png',
				'tmp_name' => '/tmp/shot.png',
			],
		];

		( new SupportPage() )->maybeHandleSave();

		$this->assertCount( 1, $this->mail );
		$this->assertSame( 1, $this->uploadCalls );
		$this->assertSame( [ '/var/www/uploads/2026/10/shot.png' ], $this->mail[0]['attachments'] );
		$this->assertSame( [ '/var/www/uploads/2026/10/shot.png' ], $this->deleted );
	}

	/**
	 * Several screenshots ride along with the mail and are then removed.
	 */
	public function test_several_screenshots_are_attached_then_removed(): void {
		$this->postValid();

		$_FILES = [
			SupportRequest::FIELD_SCREENSHOT => [
				'name'     => [ 'a.png', 'b.png' ],
				'type'     => [ 'image/png', 'image/png' ],
				'tmp_name' => [ '/tmp/a.png', '/tmp/b.png' ],
				'error'    => [ UPLOAD_ERR_OK, UPLOAD_ERR_OK ],
				'size'     => [ 1024, 1024 ],
			],
		];

		( new SupportPage() )->maybeHandleSave();

		$this->assertCount( 1, $this->mail );
		$this->assertSame( 2, $this->uploadCalls );
		$this->assertCount( 2, $this->mail[0]['attachments'] );
		$this->assertCount( 2, $this->deleted );
		$this->assertStringContainsString( 'rk_support=sent', $this->lastRedirect );
	}

	/**
	 * More screenshots than the cap are refused before anything is stored.
	 */
	public function test_more_screenshots_than_the_cap_are_refused(): void {
		$this->postValid();

		$_FILES = [
			SupportRequest::FIELD_SCREENSHOT => [
				'name'     => array_fill( 0, SupportRequest::SCREENSHOT_MAX_COUNT + 1, 'shot.png' ),
				'type'     => array_fill( 0, SupportRequest::SCREENSHOT_MAX_COUNT + 1, 'image/png' ),
				'tmp_name' => array_fill( 0, SupportRequest::SCREENSHOT_MAX_COUNT + 1, '/tmp/shot.png' ),
				'error'    => array_fill( 0, SupportRequest::SCREENSHOT_MAX_COUNT + 1, UPLOAD_ERR_OK ),
				'size'     => array_fill( 0, SupportRequest::SCREENSHOT_MAX_COUNT + 1, 1024 ),
			],
		];

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( [], $this->mail );
		$this->assertSame( 0, $this->uploadCalls );
		$this->assertStringContainsString( 'rk_support=invalid', $this->lastRedirect );
	}

	/**
	 * The upload allowlist is keyed by extension, not by MIME type.
	 */
	public function test_the_upload_allowlist_is_keyed_by_extension(): void {
		$this->postValid();

		$_FILES = [
			SupportRequest::FIELD_SCREENSHOT => [
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
				'name'     => 'shot.png',
				'type'     => 'image/png',
				'tmp_name' => '/tmp/shot.png',
			],
		];

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( 'image/png', $this->uploadMimes['png'] );
		$this->assertSame( 'image/jpeg', $this->uploadMimes['jpeg'] );
		$this->assertArrayNotHasKey( 'image/png', $this->uploadMimes );
	}

	/**
	 * An oversized screenshot is refused before anything is stored.
	 */
	public function test_an_oversized_screenshot_is_refused(): void {
		$this->postValid();

		$_FILES = [
			SupportRequest::FIELD_SCREENSHOT => [
				'error' => UPLOAD_ERR_OK,
				'size'  => SupportRequest::SCREENSHOT_MAX_BYTES + 1,
				'name'  => 'shot.png',
				'type'  => 'image/png',
			],
		];

		( new SupportPage() )->maybeHandleSave();

		$this->assertSame( [], $this->mail );
		$this->assertSame( 0, $this->uploadCalls );
		$this->assertStringContainsString( 'rk_support=invalid', $this->lastRedirect );
	}

	/**
	 * Field errors survive the redirect in a transient, not in the URL.
	 */
	public function test_field_errors_survive_the_redirect_in_a_transient(): void {
		$this->postValid( [ 'email' => 'not-an-address' ] );

		( new SupportPage() )->maybeHandleSave();

		$this->assertStringNotContainsString( 'not-an-address', $this->lastRedirect );

		$stash = $this->transients['rankkernel_support_stash_5'] ?? null;

		$this->assertIsArray( $stash );
		$this->assertArrayHasKey( 'email', $stash['errors'] );
	}

	/**
	 * Stashed errors are handed back once and then cleared.
	 */
	public function test_stashed_errors_are_handed_back_once(): void {
		$this->transients['rankkernel_support_stash_5'] = [
			'errors' => [ 'subject' => 'Add a short subject.' ],
			'values' => [ 'subject' => 'Sitemap' ],
		];

		$page = new SupportPage();

		$this->assertSame( [ 'subject' => 'Add a short subject.' ], $page->errors() );
		$this->assertSame( [ 'subject' => 'Sitemap' ], $page->values() );
		$this->assertArrayNotHasKey( 'rankkernel_support_stash_5', $this->transients );
	}

	/**
	 * The screen renders the category list and the success notice.
	 */
	public function test_the_screen_renders_the_category_list_and_notice(): void {
		$_GET[ SupportPage::NOTICE_ARG ] = 'sent';

		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'selected' )->justReturn( '' );
		Functions\when( 'checked' )->justReturn( '' );
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_html' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_textarea' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_url' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_attr__' )->alias( static fn( string $t ): string => $t );
		Functions\when( 'wp_get_document_title' )->justReturn( '' );

		ob_start();
		( new SupportPage() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Something is not working', $html );
		$this->assertStringContainsString( 'Suggestion or feature request', $html );
		$this->assertStringContainsString( 'Question about using RankKernel', $html );
		$this->assertStringContainsString( 'Thank you', $html );
	}

	/**
	 * The honeypot field sits inside the form, so a bot posts it.
	 */
	public function test_the_honeypot_field_sits_inside_the_form(): void {
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'selected' )->justReturn( '' );
		Functions\when( 'checked' )->justReturn( '' );
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_html' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_textarea' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_url' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'esc_attr__' )->alias( static fn( string $t ): string => $t );

		ob_start();
		( new SupportPage() )->render();
		$html = (string) ob_get_clean();

		$form_start = strpos( $html, '<form' );
		$form_end   = strpos( $html, '</form>' );
		$honeypot   = strpos( $html, SupportRequest::FIELD_HONEYPOT );

		$this->assertIsInt( $form_start );
		$this->assertIsInt( $form_end );
		$this->assertIsInt( $honeypot );
		$this->assertGreaterThan( $form_start, $honeypot );
		$this->assertLessThan( $form_end, $honeypot );
	}

	/**
	 * The screen menu slug is stable, because the view posts back to it.
	 */
	public function test_the_screen_slug_matches_the_view_action(): void {
		$this->assertSame( 'rankkernel-support', SupportPage::SLUG );
		$this->assertSame( 'rankkernel_page_rankkernel-support', SupportPage::HOOK_SUFFIX );
	}
}
