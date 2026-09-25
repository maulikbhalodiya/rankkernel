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
	 * Test the rendered admin HTML never contains the API key.
	 */
	public function test_render_never_contains_the_key(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( $this->key, $html, 'the API key must never reach admin HTML' );
		$this->assertStringContainsString( 'Instant Indexing', $html );
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
		$this->assertStringNotContainsString( $this->key, $html );
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

		$this->assertStringNotContainsString( $this->key, $html );
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
		$this->assertStringContainsString( 'Must be URLs on this site. Deleted pages can be submitted too.', $html );
		$this->assertStringNotContainsString( 'redirect sources', $html );
		$this->assertStringNotContainsString( 'name="rankkernel_indexnow_url"', $html );
	}

	/**
	 * Test the screen script is scoped, footer loaded and key free.
	 *
	 * The enqueue path is the only data path from PHP to the browser, so
	 * this pins the security invariant the spec cares about: the asset
	 * loads on this screen only, and the API key reaches neither the
	 * localized object nor the registered script arguments.
	 */
	public function test_enqueue_assets_is_screen_scoped_and_never_passes_the_key(): void {
		$registeredScripts = [];
		$enqueuedScripts   = [];
		$localizedScripts  = [];

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

		$page = $this->page();

		$page->enqueueAssets( 'toplevel_page_rankkernel' );

		$this->assertSame( [], $registeredScripts, 'the script must not register on another screen' );
		$this->assertSame( [], $enqueuedScripts, 'the script must not enqueue on another screen' );
		$this->assertSame( [], $localizedScripts, 'nothing must localize on another screen' );

		$page->enqueueAssets( InstantIndexingPage::HOOK_SUFFIX );

		$handle = 'rankkernel-instant-indexing-admin';

		$this->assertArrayHasKey( $handle, $registeredScripts );
		$this->assertContains( $handle, $enqueuedScripts );
		$this->assertTrue( $registeredScripts[ $handle ]['in_footer'], 'the script must load in the footer' );
		$this->assertStringContainsString( 'instant-indexing-admin.js', $registeredScripts[ $handle ]['src'] );

		$this->assertArrayHasKey( 'rankkernelInstantIndexing', $localizedScripts );
		$this->assertSame(
			[ 'siteHost' => 'example.com' ],
			$localizedScripts['rankkernelInstantIndexing'],
			'the payload must carry the site host only'
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$registeredJson = (string) json_encode( $registeredScripts );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$localizedJson = (string) json_encode( $localizedScripts );

		$this->assertStringNotContainsString( $this->key, $registeredJson, 'the key must never appear in registered script arguments' );
		$this->assertStringNotContainsString( $this->key, $localizedJson, 'the key must never appear in localized data' );
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
}
