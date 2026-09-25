<?php
/**
 * Instant Indexing admin page tests, key secrecy and guarded writes.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\InstantIndexingPage;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\ModuleEnableMap;

/**
 * Instant Indexing Page Test.
 */
final class InstantIndexingPageTest extends TestCase {
	/**
	 * Settings under test, keyed during setUp.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * The stored API key, asserted to never reach rendered admin HTML.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Captured output placeholder.
	 *
	 * @var string
	 */
	private string $output = '';

	/**
	 * Option store backing the get_option and update_option stubs.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		$this->stored = [];
		Functions\when( 'get_option' )->alias(
			function ( string $k, mixed $f = false ): mixed {
				return $this->stored[ $k ] ?? $f;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $k, mixed $v ): bool {
				$this->stored[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'admin_url' )->alias( static fn( string $p = '' ): string => 'https://example.com/wp-admin/' . $p );
		Functions\when( 'trailingslashit' )->alias( static fn( string $u ): string => rtrim( $u, '/' ) . '/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( string $key, string $value, string $url ): string {
				$separator = str_contains( $url, '?' ) ? '&' : '?';

				return $url . $separator . $key . '=' . rawurlencode( $value );
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): string|int|false|null {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double mirrors wp_parse_url with the native parser.
				return parse_url( $url, $component );
			}
		);

		$generated = 0;
		Functions\when( 'wp_generate_password' )->alias(
			static function ( int $length = 12 ) use ( &$generated ): string {
				++$generated;

				return str_pad( 'testkey' . $generated, $length, 'x' );
			}
		);
		Functions\when( 'esc_html' )->alias( static fn( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn( string $v ): string => $v );
		Functions\when( 'esc_url_raw' )->alias( static fn( string $v ): string => $v );
		Functions\when( 'wp_unslash' )->alias(
			static fn( mixed $v ): mixed => is_string( $v ) ? stripslashes( $v ) : $v
		);
		Functions\when( 'esc_html__' )->alias( static fn( string $v ): string => $v );
		Functions\when( 'esc_attr__' )->alias( static fn( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( '__' )->alias( static fn( string $v ): string => $v );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'submit_button' )->justReturn( '' );
		Functions\when( 'checked' )->alias( static fn( bool $c, bool $e = true ): string => ( $c === $e ) ? ' checked' : '' );
		Functions\when( 'selected' )->alias( static fn( bool $c, bool $e = true ): string => ( $c === $e ) ? ' selected' : '' );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$this->settings = new IndexNowSettings();
		$this->key      = $this->settings->ensureKey();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		$_POST = [];
		$_GET  = [];
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build the page under test.
	 *
	 * The enable map reads the option when it is constructed, so the
	 * module state is staged into the option store first.
	 *
	 * @param callable(string[]): void|null $submit        Submission callback override.
	 * @param bool                          $moduleEnabled Whether instant-indexing is enabled.
	 * @return InstantIndexingPage The result.
	 */
	private function page( ?callable $submit = null, bool $moduleEnabled = true ): InstantIndexingPage {
		$this->stored['rankkernel_modules'] = $moduleEnabled ? [ 'instant-indexing' ] : [];

		return new InstantIndexingPage( $this->settings, new ModuleEnableMap(), $submit );
	}

	/**
	 * Rendered HTML with the intentional key file URL removed.
	 *
	 * The administrator only screen links the public key file location,
	 * which contains the key because engines fetch it from that URL.
	 * Key secrecy therefore means the key appears nowhere else, so
	 * tests strip that one intentional URL before asserting absence.
	 *
	 * @param string $html Rendered HTML.
	 * @return string The result.
	 */
	private function htmlWithoutKeyFileUrl( string $html ): string {
		$url = $this->settings->keyLocation();

		if ( '' !== $url ) {
			$html = str_replace( $url, '', $html );
		}

		return $html;
	}

	/**
	 * Test the rendered admin HTML never contains the API key.
	 */
	public function test_render_never_contains_the_key(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( $this->key, $this->htmlWithoutKeyFileUrl( $html ), 'the API key must never reach admin HTML outside the intentional key file URL' );
		$this->assertStringContainsString( 'Instant Indexing', $html );
	}

	/**
	 * Test the wrap opens with the screen reader h1 and the visible title is an h2.
	 *
	 * WordPress relocates third party .notice elements after the first h1 on
	 * the page. Without this anchor the notices collect inside the header
	 * card, so the first element inside the wrap must be the screen reader h1
	 * and the styled title must not be an h1.
	 */
	public function test_render_opens_with_the_screen_reader_h1_and_keeps_the_visible_title_as_h2(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$wrapOpen = '<div class="wrap rk-instant-indexing-wrap">';
		$wrapPos  = strpos( $html, $wrapOpen );
		$this->assertNotFalse( $wrapPos, 'the wrap must render' );

		$insideWrap = substr( $html, $wrapPos + strlen( $wrapOpen ) );
		$this->assertMatchesRegularExpression(
			'/^\s*<h1 class="screen-reader-text">Instant Indexing<\/h1>/',
			$insideWrap,
			'the screen reader h1 must be the first element inside the wrap'
		);

		$this->assertStringContainsString( '<h2 class="rk-page-title">Instant Indexing</h2>', $html );
		$this->assertSame( 1, substr_count( $html, '<h1' ), 'the screen reader heading must be the only h1' );
	}

	/**
	 * Test the configured state is shown when a key exists.
	 */
	public function test_render_shows_the_configured_state_when_a_key_exists(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Key configured', $html );
		$this->assertStringNotContainsString( 'No key configured', $html );
		$this->assertStringNotContainsString( $this->key, $this->htmlWithoutKeyFileUrl( $html ) );
	}

	/**
	 * Test the unconfigured state is shown when no key exists.
	 */
	public function test_render_shows_the_unconfigured_state_when_no_key_exists(): void {
		unset( $this->stored[ IndexNowSettings::OPTION ] );

		$page = new InstantIndexingPage( new IndexNowSettings() );
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'No key configured', $html );
		$this->assertStringNotContainsString( 'Key configured', $html );
		$this->assertStringNotContainsString( $this->key, $html );
	}

	/**
	 * Test a rendered log table still never contains the key.
	 */
	public function test_render_never_contains_the_key_when_the_log_has_entries(): void {
		$this->settings->logEntry( 'https://example.com/post', 200, 'manual', 'Accepted.' );

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( $this->key, $this->htmlWithoutKeyFileUrl( $html ) );
		$this->assertStringContainsString( 'https://example.com/post', $html );
	}

	/**
	 * Test regeneration never echoes the replacement key.
	 */
	public function test_regenerate_never_echoes_the_new_key(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		$old   = $this->settings->getKey();
		$_POST = [ 'rankkernel_indexnow_action' => 'regenerate' ];

		ob_start();
		$this->page()->maybeHandleSave();
		$output = (string) ob_get_clean();

		$new = $this->settings->getKey();

		$this->assertNotSame( $old, $new );
		$this->assertSame( '', $output );
		$this->assertStringNotContainsString( $new, $output );
	}

	/**
	 * Test regeneration requires authorization and leaves the key unchanged.
	 *
	 * Covers both rejection paths required by the spec matrix: without
	 * manage_options and without a valid nonce. Each must stop at wp_die
	 * with a 403 and leave the stored key byte identical. The captured
	 * nonce action proves the regenerate branch verifies its own action,
	 * so reusing the save or submit action would fail this test.
	 */
	public function test_regenerate_requires_authorization_and_leaves_the_key_unchanged(): void {
		$dies         = 0;
		$responseCode = 0;
		$nonceActions = [];

		Functions\when( 'wp_die' )->alias(
			static function ( string $message = '', string $title = '', array $args = [] ) use ( &$dies, &$responseCode ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the wp_die signature and records only the response code.
				++$dies;
				$responseCode = (int) ( $args['response'] ?? 0 );

				throw new \RuntimeException( 'wp_die' );
			}
		);

		$old   = $this->settings->getKey();
		$_POST = [ 'rankkernel_indexnow_action' => 'regenerate' ];

		// Without manage_options the capability check must stop the write.
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		try {
			$this->page()->maybeHandleSave();
		} catch ( \RuntimeException $exception ) {
			// wp_die is expected, the assertions below prove it was the 403 path.
			$this->assertSame( 'wp_die', $exception->getMessage() );
		}

		$this->assertSame( 1, $dies, 'regenerate without manage_options must stop at wp_die' );
		$this->assertSame( 403, $responseCode, 'the capability rejection must send a 403' );

		// With the capability but a failed nonce the nonce check must stop the write.
		$dies         = 0;
		$responseCode = 0;

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->alias(
			static function ( string $action = '' ) use ( &$nonceActions ): bool {
				$nonceActions[] = $action;

				return false;
			}
		);

		try {
			$this->page()->maybeHandleSave();
		} catch ( \RuntimeException $exception ) {
			// wp_die is expected, the assertions below prove it was the 403 path.
			$this->assertSame( 'wp_die', $exception->getMessage() );
		}

		$this->assertSame( 1, $dies, 'regenerate without a valid nonce must stop at wp_die' );
		$this->assertSame( 403, $responseCode, 'the nonce rejection must send a 403' );
		$this->assertSame( [ 'rankkernel_indexnow_regenerate' ], $nonceActions, 'the regenerate branch must verify its own nonce action' );
		$this->assertNotContains( 'rankkernel_indexnow_save', $nonceActions );
		$this->assertNotContains( 'rankkernel_indexnow_submit', $nonceActions );

		// Both rejected attempts must leave the stored key byte identical.
		$option = (array) ( $this->stored[ IndexNowSettings::OPTION ] ?? [] );

		$this->assertSame( bin2hex( $old ), bin2hex( $this->settings->getKey() ), 'the key accessor must return the old key byte for byte' );
		$this->assertSame( $old, $option['api_key'] ?? '', 'the stored option must still hold the old key' );
	}

	/**
	 * Test the auto submit checkbox treats a missing field as false.
	 */
	public function test_save_treats_an_absent_checkbox_as_false(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		$this->settings->set( [ 'auto_submit' => true ] );

		$_POST = [ 'rankkernel_indexnow_action' => 'save' ];
		$this->page()->maybeHandleSave();

		$this->assertFalse( $this->settings->getAutoSubmit() );

		$_POST = [
			'rankkernel_indexnow_action'      => 'save',
			'rankkernel_indexnow_auto_submit' => '1',
		];
		$this->page()->maybeHandleSave();

		$this->assertTrue( $this->settings->getAutoSubmit() );
	}

	/**
	 * Test a manual submit is rejected without capability.
	 */
	public function test_manual_submit_is_rejected_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://example.com/a',
		];

		$this->expectException( \RuntimeException::class );

		$this->page()->maybeHandleSave();
	}

	/**
	 * Test a manual submit is rejected without a valid nonce.
	 */
	public function test_manual_submit_is_rejected_without_a_valid_nonce(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://example.com/a',
		];

		$this->expectException( \RuntimeException::class );

		$this->page()->maybeHandleSave();
	}

	/**
	 * Test a foreign host is rejected by the host check before the callback.
	 *
	 * The wp_http_validate_url double returns the URL unchanged here, so
	 * the request reaches the hash_equals host comparison, which is the
	 * boundary under test. Validation is not the reason this URL is
	 * refused.
	 */
	public function test_manual_submit_rejects_a_url_on_another_host_without_submitting(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

		$called = 0;
		$page   = $this->page(
			function () use ( &$called ): void {
				$called++;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://evil.test/a',
		];
		$page->maybeHandleSave();

		$this->assertSame( 0, $called, 'a foreign host must never be submitted' );

		$entries = $this->settings->logEntries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Rejected: the URL host does not match this site.', $entries[0]['message'] );
	}

	/**
	 * Test a foreign host is logged as a host rejection and never fetched.
	 */
	public function test_a_foreign_host_is_logged_as_rejected_and_never_fetched(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );
		Functions\when( 'wp_json_encode' )->alias( static fn( mixed $d ): string => (string) json_encode( $d ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.

		$fetches = 0;
		$fetch   = function () use ( &$fetches ): array {
			++$fetches;

			return [
				'response' => [ 'code' => 200 ],
				'body'     => '',
			];
		};
		Functions\when( 'wp_remote_get' )->alias( $fetch );
		Functions\when( 'wp_safe_remote_get' )->alias( $fetch );
		Functions\when( 'wp_remote_head' )->alias( $fetch );
		Functions\when( 'wp_safe_remote_post' )->alias( $fetch );
		Functions\when( 'wp_remote_post' )->alias( $fetch );

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://evil.test/a',
		];

		$this->page()->maybeHandleSave();

		$this->assertSame( 0, $fetches, 'a rejected URL must never be fetched' );

		$entries = $this->settings->logEntries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'https://evil.test/a', $entries[0]['url'] );
		$this->assertSame( 0, $entries[0]['code'] );
		$this->assertSame( 'manual', $entries[0]['source'] );
		$this->assertSame( 'Rejected: the URL host does not match this site.', $entries[0]['message'] );
	}

	/**
	 * Test a single URL in the textarea still submits, the original behaviour.
	 */
	public function test_manual_submit_sends_a_valid_same_host_url(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

		$calls    = 0;
		$received = [];
		$page     = $this->page(
			function ( array $urls ) use ( &$calls, &$received ): void {
				++$calls;
				$received = $urls;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://example.com/a',
		];
		$page->maybeHandleSave();

		$this->assertSame( 1, $calls );
		$this->assertSame( [ 'https://example.com/a' ], $received );
	}

	/**
	 * Test an uppercase host URL is accepted by the case folded host check.
	 *
	 * The browser validator folds host case too, so this pins the
	 * agreement between the two layers: an uppercase host reaches the
	 * submit callback instead of being rejected.
	 */
	public function test_an_uppercase_host_url_is_accepted_by_the_site_host_check(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

		$received = [];
		$page     = $this->page(
			function ( array $urls ) use ( &$received ): void {
				$received = $urls;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://EXAMPLE.com/a',
		];
		$page->maybeHandleSave();

		$this->assertSame( [ 'https://EXAMPLE.com/a' ], $received, 'an uppercase host must be accepted, matching the browser validator' );
		$this->assertSame( [], $this->settings->logEntries() );
	}

	/**
	 * Test three valid URLs in the textarea submit in a single call.
	 */
	public function test_a_textarea_with_three_valid_urls_submits_them_in_one_call(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

		$calls    = 0;
		$received = [];
		$page     = $this->page(
			function ( array $urls ) use ( &$calls, &$received ): void {
				++$calls;
				$received = $urls;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => "https://example.com/a\nhttps://example.com/b\r\nhttps://example.com/c",
		];
		$page->maybeHandleSave();

		$this->assertSame( 1, $calls, 'three valid URLs must submit in one call' );
		$this->assertSame(
			[ 'https://example.com/a', 'https://example.com/b', 'https://example.com/c' ],
			$received
		);
		$this->assertSame( [], $this->settings->logEntries() );
	}

	/**
	 * Test blank lines and duplicates are removed before submission.
	 */
	public function test_blank_lines_and_duplicates_are_removed_before_submission(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

		$received = [];
		$page     = $this->page(
			function ( array $urls ) use ( &$received ): void {
				$received = $urls;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => "https://example.com/a\n\n   \nhttps://example.com/a\nhttps://example.com/b\n",
		];
		$page->maybeHandleSave();

		$this->assertSame( [ 'https://example.com/a', 'https://example.com/b' ], $received );
		$this->assertSame( [], $this->settings->logEntries() );
	}

	/**
	 * Test a mixed paste submits only the valid URLs and logs each invalid one.
	 *
	 * The validator double mirrors wp_http_validate_url for this test, so
	 * the malformed line fails validation and the foreign host passes it
	 * and fails the host check, which is the exact boundary under test.
	 */
	public function test_a_mixed_paste_submits_the_valid_urls_and_logs_each_invalid_one(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias(
			static function ( string $url ): string|false {
				$validated = filter_var( $url, FILTER_VALIDATE_URL );

				return is_string( $validated ) ? $validated : false;
			}
		);

		$calls    = 0;
		$received = [];
		$page     = $this->page(
			function ( array $urls ) use ( &$calls, &$received ): void {
				++$calls;
				$received = $urls;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => "https://example.com/a\nhttps://evil.test/b\nnot a url\nhttps://example.com/c",
		];
		$page->maybeHandleSave();

		$this->assertSame( 1, $calls, 'the valid URLs must submit in one call' );
		$this->assertSame( [ 'https://example.com/a', 'https://example.com/c' ], $received );

		$entries = $this->settings->logEntries();

		$this->assertCount( 2, $entries );
		// The log is newest first, so the malformed line logged last is first.
		$this->assertSame( 'not a url', $entries[0]['url'] );
		$this->assertSame( 'Rejected: the URL could not be validated.', $entries[0]['message'] );
		$this->assertSame( 'https://evil.test/b', $entries[1]['url'] );
		$this->assertSame( 'Rejected: the URL host does not match this site.', $entries[1]['message'] );
	}

	/**
	 * Test an empty textarea is rejected with the empty list message.
	 */
	public function test_an_empty_textarea_is_rejected_without_submitting(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		$called = 0;
		$page   = $this->page(
			function () use ( &$called ): void {
				++$called;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => "\n   \n",
		];
		$page->maybeHandleSave();

		$this->assertSame( 0, $called, 'an empty textarea must never reach the submit callback' );

		$entries = $this->settings->logEntries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Rejected: no URLs were provided.', $entries[0]['message'] );
	}

	/**
	 * Test the submit form renders the multi URL textarea and status region.
	 */
	public function test_render_shows_the_multi_url_textarea(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="rankkernel_indexnow_urls"', $html );
		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( 'id="rankkernel-indexnow-urls-status"', $html );
		$this->assertStringContainsString( 'rk-instant-indexing', $html );
		$this->assertStringContainsString( 'rk-urls-input', $html );
		$this->assertStringContainsString( 'Must be URLs on this site. Deleted pages can be submitted too.', $html );
		$this->assertStringNotContainsString( 'redirect sources', $html );
		$this->assertStringNotContainsString( 'name="rankkernel_indexnow_url"', $html );
	}

	/**
	 * Test the render pins the submit button id the script looks for.
	 *
	 * The validation script finds the button by id, and this suite stubs
	 * submit_button to return an empty string, so the id is asserted on
	 * the captured call arguments instead of the rendered HTML.
	 */
	public function test_render_pins_the_submit_button_id_the_script_looks_for(): void {
		$buttonCalls = [];

		Functions\when( 'submit_button' )->alias(
			static function ( ...$args ) use ( &$buttonCalls ): void {
				$buttonCalls[] = $args;
			}
		);

		$page = $this->page();
		ob_start();
		$page->render();
		ob_get_clean();

		$ids = [];

		foreach ( $buttonCalls as $args ) {
			$attributes = is_array( $args[4] ?? null ) ? $args[4] : [];
			$ids[]      = (string) ( $attributes['id'] ?? '' );
		}

		$this->assertContains(
			'rankkernel-indexnow-submit',
			$ids,
			'the rendered submit button must carry the id the validation script looks for'
		);
	}

	/**
	 * Test the stats strip numbers derive from the log rows.
	 *
	 * Two 200 plus one 202 read as three accepted, one 400 plus one
	 * refused row read as two rejected, one 429 reads as limited, and one
	 * 503 lands in the total only, so none of the four cards can be hard
	 * coded.
	 */
	public function test_render_stats_derive_from_the_log_rows(): void {
		$this->settings->logEntry( 'https://example.com/a', 200, 'manual', 'Accepted.' );
		$this->settings->logEntry( 'https://example.com/b', 200, 'auto', 'Accepted.' );
		$this->settings->logEntry( 'https://example.com/c', 202, 'manual', 'Accepted, the key is pending verification.' );
		$this->settings->logEntry( 'https://example.com/d', 400, 'manual', 'Rejected permanently, retrying will not help.' );
		$this->settings->logEntry( 'https://example.com/e', 0, 'auto', 'Rejected: the URL host does not match this site.' );
		$this->settings->logEntry( 'https://example.com/f', 429, 'manual', 'Temporary failure, retry later.' );
		$this->settings->logEntry( 'https://example.com/g', 503, 'auto', 'Temporary failure, retry later.' );

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<div class="rk-stat-value">7</div>', $html );
		$this->assertStringContainsString( '<div class="rk-stat-value rk-stat-value-positive">3</div>', $html );
		$this->assertStringContainsString( '<div class="rk-stat-value rk-stat-value-negative">2</div>', $html );
		$this->assertStringContainsString( '<div class="rk-stat-value rk-stat-value-warning">1</div>', $html );
		$this->assertStringContainsString( '>Rejected <span class="count">2</span>', $html );
		$this->assertStringContainsString( 'rk-pill-accepted">Accepted', $html );
		$this->assertStringContainsString( 'rk-pill-pending">Key pending', $html );
		$this->assertStringContainsString( 'rk-pill-rejected">Rejected', $html );
		$this->assertStringContainsString( 'rk-pill-limited">Rate limited', $html );
		$this->assertStringContainsString( 'rk-pill-retry">Retry later', $html );
		$this->assertStringNotContainsString( $this->key, $this->htmlWithoutKeyFileUrl( $html ) );
	}

	/**
	 * Test a refused submit renders the error notice with the reason.
	 */
	public function test_render_shows_the_error_notice_for_a_refused_submit(): void {
		$_GET['rk_indexnow_notice'] = 'host';

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'rk-notice-error', $html );
		$this->assertStringContainsString( 'does not match this site', $html );
		$this->assertStringNotContainsString( $this->key, $this->htmlWithoutKeyFileUrl( $html ) );
	}

	/**
	 * Test the cleared log renders the info notice.
	 */
	public function test_render_shows_the_info_notice_for_a_cleared_log(): void {
		$_GET['rk_indexnow_notice'] = 'cleared';

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'rk-notice-info', $html );
		$this->assertStringContainsString( 'Log cleared.', $html );
	}

	/**
	 * Test an unknown notice code renders no notice.
	 */
	public function test_render_ignores_an_unknown_notice_code(): void {
		$_GET['rk_indexnow_notice'] = 'not-a-real-code"><script';

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'rk-notice-error', $html );
		$this->assertStringNotContainsString( 'rk-notice-info', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * Test the saved notice still renders after the redesign.
	 */
	public function test_render_keeps_the_settings_saved_notice(): void {
		$_GET['settings-updated'] = '1';

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'rk-notice-success', $html );
		$this->assertStringContainsString( 'Settings saved.', $html );
	}

	/**
	 * Test the header pill follows the automatic submission setting.
	 */
	public function test_render_header_pill_follows_the_auto_submit_setting(): void {
		$this->settings->set( [ 'auto_submit' => true ] );

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Automatic submission on', $html );
		$this->assertStringNotContainsString( 'Automatic submission off', $html );

		$this->settings->set( [ 'auto_submit' => false ] );

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Automatic submission off', $html );
		$this->assertStringNotContainsString( 'Automatic submission on', $html );
	}

	/**
	 * Test the clear log form empties the log and redirects with a notice.
	 */
	public function test_clear_empties_the_log_and_redirects_with_a_notice(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		$nonceActions = [];
		Functions\when( 'check_admin_referer' )->alias(
			static function ( string $action = '' ) use ( &$nonceActions ): bool {
				$nonceActions[] = $action;

				return true;
			}
		);

		$redirect = '';
		Functions\when( 'wp_safe_redirect' )->alias(
			static function ( string $url ) use ( &$redirect ): bool {
				$redirect = $url;

				return true;
			}
		);

		$this->settings->logEntry( 'https://example.com/a', 200, 'manual', 'Accepted.' );

		$_POST = [ 'rankkernel_indexnow_action' => 'clear' ];
		$this->page()->maybeHandleSave();

		$this->assertSame( [], $this->settings->logEntries(), 'clear must empty the log' );
		$this->assertSame( [ 'rankkernel_indexnow_clear' ], $nonceActions, 'the clear branch must verify its own nonce action' );
		$this->assertStringContainsString( 'rk_indexnow_notice=cleared', $redirect );
		$this->assertStringNotContainsString( 'settings-updated', $redirect );
	}

	/**
	 * Test a refused submit redirects with the reason code.
	 */
	public function test_refused_submit_redirects_with_the_reason_code(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

		$redirect = '';
		Functions\when( 'wp_safe_redirect' )->alias(
			static function ( string $url ) use ( &$redirect ): bool {
				$redirect = $url;

				return true;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://evil.test/a',
		];
		$this->page()->maybeHandleSave();

		$this->assertStringContainsString( 'rk_indexnow_notice=host', $redirect );
		$this->assertStringNotContainsString( 'settings-updated', $redirect );
	}

	/**
	 * Test the screen script is scoped, footer loaded and key free.
	 *
	 * The enqueue path is the only data path from PHP to the browser, so
	 * this pins the security invariant the spec cares about: the assets
	 * load on this screen only, and the API key reaches neither the
	 * localized object nor the registered script arguments.
	 */
	public function test_enqueue_assets_is_screen_scoped_and_never_passes_the_key(): void {
		$registeredScripts = [];
		$enqueuedScripts   = [];
		$localizedScripts  = [];
		$registeredStyles  = [];
		$enqueuedStyles    = [];

		Functions\when( 'plugins_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.com/wp-content/plugins/rankkernel/' . $path
		);
		Functions\when( 'wp_register_script' )->alias(
			static function ( string $handle, string $src, array $deps = [], string $ver = '', bool $inFooter = false ) use ( &$registeredScripts ): void {
				$registeredScripts[ $handle ] = [
					'src'       => $src,
					'deps'      => $deps,
					'ver'       => $ver,
					'in_footer' => $inFooter,
				];
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			static function ( string $handle ) use ( &$enqueuedScripts ): void {
				$enqueuedScripts[] = $handle;
			}
		);
		Functions\when( 'wp_localize_script' )->alias(
			static function ( string $handle, string $objectName, array $data ) use ( &$localizedScripts ): void {
				$localizedScripts[ $objectName ] = $data;
			}
		);
		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps = [], string $ver = '' ) use ( &$registeredStyles ): void {
				$registeredStyles[ $handle ] = [
					'src'  => $src,
					'deps' => $deps,
					'ver'  => $ver,
				];
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle ) use ( &$enqueuedStyles ): void {
				$enqueuedStyles[] = $handle;
			}
		);

		$page = $this->page();

		$page->enqueueAssets( 'toplevel_page_rankkernel' );

		$this->assertSame( [], $registeredScripts, 'the script must not register on another screen' );
		$this->assertSame( [], $enqueuedScripts, 'the script must not enqueue on another screen' );
		$this->assertSame( [], $localizedScripts, 'nothing must localize on another screen' );
		$this->assertSame( [], $registeredStyles, 'the stylesheet must not register on another screen' );
		$this->assertSame( [], $enqueuedStyles, 'the stylesheet must not enqueue on another screen' );

		$page->enqueueAssets( InstantIndexingPage::HOOK_SUFFIX );

		$handle = 'rankkernel-instant-indexing-admin';

		$this->assertArrayHasKey( $handle, $registeredScripts );
		$this->assertContains( $handle, $enqueuedScripts );
		$this->assertTrue( $registeredScripts[ $handle ]['in_footer'], 'the script must load in the footer' );
		$this->assertStringContainsString( 'instant-indexing-admin.js', $registeredScripts[ $handle ]['src'] );

		$this->assertArrayHasKey( $handle, $registeredStyles, 'the stylesheet must register on this screen' );
		$this->assertContains( $handle, $enqueuedStyles, 'the stylesheet must enqueue on this screen' );
		$this->assertStringContainsString( 'instant-indexing-admin.css', $registeredStyles[ $handle ]['src'] );

		$this->assertArrayHasKey( 'rankkernelInstantIndexing', $localizedScripts );
		$this->assertSame(
			[
				'siteHost' => 'example.com',
				'sitePort' => '',
			],
			$localizedScripts['rankkernelInstantIndexing'],
			'the payload must carry the site host and the site port only'
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$registeredJson = (string) json_encode( $registeredScripts );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$localizedJson = (string) json_encode( $localizedScripts );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$stylesJson = (string) json_encode( $registeredStyles );

		$this->assertStringNotContainsString( $this->key, $registeredJson, 'the key must never appear in registered script arguments' );
		$this->assertStringNotContainsString( $this->key, $localizedJson, 'the key must never appear in localized data' );
		$this->assertStringNotContainsString( $this->key, $stylesJson, 'the key must never appear in registered style arguments' );
	}

	/**
	 * Test the localized site port derives from the same home URL as the host.
	 *
	 * A site running on a non standard port must tell the browser which
	 * port to allow, while a default port localizes as an empty string.
	 */
	public function test_enqueue_localizes_the_site_port_derived_from_home_url(): void {
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'http://example.com:8080' . $path );
		Functions\when( 'plugins_url' )->alias( static fn( string $path = '' ): string => 'https://example.com/wp-content/plugins/rankkernel/' . $path );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );

		$localizedScripts = [];
		Functions\when( 'wp_localize_script' )->alias(
			static function ( string $handle, string $objectName, array $data ) use ( &$localizedScripts ): void {
				$localizedScripts[ $objectName ] = $data;
			}
		);

		$this->page()->enqueueAssets( InstantIndexingPage::HOOK_SUFFIX );

		$this->assertSame(
			[
				'siteHost' => 'example.com',
				'sitePort' => '8080',
			],
			$localizedScripts['rankkernelInstantIndexing'],
			'the port must derive from the home URL and nothing else may be localized'
		);
	}

	/**
	 * Test a disabled module refuses the manual submit before the callback.
	 */
	public function test_manual_submit_is_refused_while_the_module_is_disabled(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

		$called = 0;
		$page   = $this->page(
			function () use ( &$called ): void {
				$called++;
			},
			false
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://example.com/a',
		];
		$page->maybeHandleSave();

		$this->assertSame( 0, $called, 'a disabled module must never reach the submit callback' );

		$entries = $this->settings->logEntries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Rejected: the Instant Indexing module is disabled.', $entries[0]['message'] );
	}

	/**
	 * Test a disabled module never reaches the transport.
	 */
	public function test_a_disabled_module_never_reaches_the_transport(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );
		Functions\when( 'wp_json_encode' )->alias( static fn( mixed $d ): string => (string) json_encode( $d ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.

		$transportCalls = 0;
		$transport      = function () use ( &$transportCalls ): array {
			++$transportCalls;

			return [
				'response' => [ 'code' => 200 ],
				'body'     => '',
			];
		};
		Functions\when( 'wp_safe_remote_post' )->alias( $transport );
		Functions\when( 'wp_remote_post' )->alias( $transport );

		// The default callback routes through InstantIndexingModule, so a
		// missing gate would construct the client and reach for the network.
		$page = $this->page( null, false );

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_urls'   => 'https://example.com/a',
		];
		$page->maybeHandleSave();

		$this->assertSame( 0, $transportCalls, 'a disabled module must make zero outbound requests' );

		$entries = $this->settings->logEntries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Rejected: the Instant Indexing module is disabled.', $entries[0]['message'] );
	}

	/**
	 * Test the settings container plus the help card start collapsed.
	 *
	 * The header Settings control and the header help icon are real
	 * toggles with expanded state plus a controls target, each panel
	 * hides with the hidden attribute, and each Hide control is a plain
	 * text link, never a button, so assistive tech reports the state and
	 * the form inside keeps posting. The help card is the single help
	 * panel, opened from the header icon, never a panel inside itself.
	 */
	public function test_render_collapses_settings_and_help_by_default(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rk-settings-toggle" aria-expanded="false" aria-controls="rk-settings-panel"', $html );
		$this->assertStringContainsString( 'id="rk-settings-panel" class="rk-settings-panel" hidden', $html );
		$this->assertStringContainsString( 'id="rk-help-toggle" aria-expanded="false" aria-controls="rk-help-panel"', $html );
		$this->assertStringContainsString( 'class="rk-card rk-help-card rk-help-panel" id="rk-help-panel" hidden', $html );
		$this->assertSame( 1, substr_count( $html, 'id="rk-help-panel"' ), 'the help card must be the only help panel' );
		$this->assertSame( 1, substr_count( $html, 'id="rk-help-toggle"' ), 'the header icon must be the only help toggle' );
		$this->assertStringNotContainsString( 'class="rk-help-panel" hidden', $html, 'no help panel may nest inside the help card' );
		$this->assertStringContainsString( '<a href="#rk-settings" class="rk-collapse-hide" id="rk-settings-hide"', $html );
		$this->assertStringContainsString( '<a href="#rk-help-panel" class="rk-collapse-hide" id="rk-help-hide"', $html );
		$this->assertStringNotContainsString( '<button type="button" class="rk-collapse-hide"', $html );
		$this->assertStringNotContainsString( 'id="rk-settings-hide"><button', $html );
		$this->assertStringContainsString( 'name="rankkernel_indexnow_auto_submit"', $html );
		$this->assertStringContainsString( 'name="rankkernel_indexnow_action" value="save"', $html );
		$this->assertStringContainsString( 'name="rankkernel_indexnow_action" value="regenerate"', $html );
	}

	/**
	 * Test the help card walks the real flow in plain words.
	 *
	 * Each step names behaviour the plugin owns, and the card promises
	 * nothing about a retry queue, a quota, a schedule, or bulk work,
	 * because none of those exist.
	 */
	public function test_render_help_card_covers_the_real_flow(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'How Instant Indexing works', $html );
		$this->assertStringContainsString( 'Both are off by default', $html );
		$this->assertStringContainsString( 'never sent to your browser', $html );
		$this->assertStringContainsString( 'serves that file virtually', $html );
		$this->assertStringContainsString( 'not sent more than once every 10 minutes', $html );
		$this->assertStringContainsString( '202 means accepted and the key is still pending verification', $html );
		$this->assertStringContainsString( 'not that the page was indexed', $html );
		$this->assertStringContainsString( 'There is no telemetry', $html );
		$this->assertStringNotContainsString( 'retry queue', $html );
		$this->assertStringNotContainsString( 'quota', $html );
		$this->assertStringNotContainsString( 'bulk submission', $html );
	}

	/**
	 * Test the key file URL renders as a new tab link with the guard rel.
	 *
	 * The location contains the key because engines fetch it from that
	 * URL, which is intentional on this administrator only screen. The
	 * standalone key still appears nowhere else in the markup.
	 */
	public function test_render_shows_key_file_url_as_new_tab_link(): void {
		$labels = [];

		Functions\when( 'submit_button' )->alias(
			static function ( ...$args ) use ( &$labels ): void {
				$labels[] = (string) ( $args[0] ?? '' );
			}
		);

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$url = $this->settings->keyLocation();

		$this->assertNotSame( '', $url );
		$this->assertStringContainsString( 'href="' . $url . '"', $html );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
		$this->assertContains( 'Verify key file', $labels, 'the verify control must render with its own label' );
		$this->assertStringContainsString( 'name="rankkernel_indexnow_action" value="verify"', $html );
		$this->assertStringContainsString( 'should show only the key as plain text', $html );
		$this->assertStringNotContainsString( $this->key, $this->htmlWithoutKeyFileUrl( $html ) );
	}

	/**
	 * Test a passed check reports the code without echoing the body.
	 */
	public function test_render_verify_success_reports_code_without_body(): void {
		$_GET['rk_indexnow_notice'] = 'verified';
		$_GET['rk_verify_code']     = '200';

		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Key file verified', $html );
		$this->assertStringContainsString( 'Returned 200', $html );
		$this->assertStringNotContainsString( $this->key, $this->htmlWithoutKeyFileUrl( $html ) );
	}

	/**
	 * Test the verify action needs the capability plus its own nonce.
	 *
	 * Both rejection paths stop at wp_die with a 403. The captured nonce
	 * action proves the verify branch verifies its own action, so reusing
	 * another action would fail this test.
	 */
	public function test_verify_requires_capability_and_own_nonce(): void {
		$dies         = 0;
		$responseCode = 0;
		$nonceActions = [];

		Functions\when( 'wp_die' )->alias(
			static function ( string $message = '', string $title = '', array $args = [] ) use ( &$dies, &$responseCode ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the wp_die signature and records only the response code.
				++$dies;
				$responseCode = (int) ( $args['response'] ?? 0 );

				throw new \RuntimeException( 'wp_die' );
			}
		);

		$_POST = [ 'rankkernel_indexnow_action' => 'verify' ];

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		try {
			$this->page()->maybeHandleSave();
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'wp_die', $exception->getMessage() );
		}

		$this->assertSame( 1, $dies );
		$this->assertSame( 403, $responseCode );

		$dies         = 0;
		$responseCode = 0;

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->alias(
			static function ( string $action = '' ) use ( &$nonceActions ): bool {
				$nonceActions[] = $action;

				return false;
			}
		);

		try {
			$this->page()->maybeHandleSave();
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'wp_die', $exception->getMessage() );
		}

		$this->assertSame( 1, $dies );
		$this->assertSame( 403, $responseCode );
		$this->assertSame( [ 'rankkernel_indexnow_verify' ], $nonceActions );
	}

	/**
	 * Test the verify action fetches only the own key file location.
	 *
	 * A caller supplied URL in POST is ignored, the stub records the one
	 * URL the check was given, and the redirect carries the verified code
	 * plus the status without echoing the response body.
	 */
	public function test_verify_requests_only_the_own_key_file(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		$expected  = $this->settings->keyLocation();
		$requested = '';

		Functions\when( 'wp_safe_remote_get' )->alias(
			static function ( string $url ) use ( &$requested ): array {
				$requested = $url;

				return [
					'response' => [ 'code' => 200 ],
					'body'     => 'placeholder',
				];
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn(): bool => false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn(): int => 200 );
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function (): string {
				return $this->settings->getKey();
			}
		);

		$redirect = '';
		Functions\when( 'wp_safe_redirect' )->alias(
			static function ( string $url ) use ( &$redirect ): bool {
				$redirect = $url;

				return true;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'verify',
			'rankkernel_indexnow_urls'   => 'https://evil.test/steer',
		];

		ob_start();
		$this->page()->maybeHandleSave();
		$output = (string) ob_get_clean();

		$this->assertSame( $expected, $requested, 'the check must fetch the own key file, never a caller supplied URL' );
		$this->assertStringContainsString( 'rk_indexnow_notice=verified', $redirect );
		$this->assertStringContainsString( 'rk_verify_code=200', $redirect );
		$this->assertSame( '', $output );
		$this->assertStringNotContainsString( $this->settings->getKey(), $output );
	}

	/**
	 * Test a failed check redirects with the failed code and no body.
	 */
	public function test_verify_failure_redirects_with_failed_code_and_no_body(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_remote_get' )->alias(
			static function (): array {
				return [
					'response' => [ 'code' => 404 ],
					'body'     => 'marker body text',
				];
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn(): bool => false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn(): int => 404 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn(): string => 'marker body text' );

		$redirect = '';
		Functions\when( 'wp_safe_redirect' )->alias(
			static function ( string $url ) use ( &$redirect ): bool {
				$redirect = $url;

				return true;
			}
		);

		$_POST = [ 'rankkernel_indexnow_action' => 'verify' ];

		ob_start();
		$this->page()->maybeHandleSave();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'rk_indexnow_notice=verify_failed', $redirect );
		$this->assertStringContainsString( 'rk_verify_code=404', $redirect );
		$this->assertSame( '', $output );
		$this->assertStringNotContainsString( 'marker body text', $output );
	}
}
