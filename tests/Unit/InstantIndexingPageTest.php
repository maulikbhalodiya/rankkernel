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
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$stored = [];
		Functions\when( 'get_option' )->alias(
			static function ( string $k, mixed $f = false ) use ( &$stored ): mixed {
				return $stored[ $k ] ?? $f;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $k, mixed $v ) use ( &$stored ): bool {
				$stored[ $k ] = $v;
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
	 * @param callable(string): void|null $submit Submission callback override.
	 * @return InstantIndexingPage The result.
	 */
	private function page( ?callable $submit = null ): InstantIndexingPage {
		return new InstantIndexingPage( $this->settings, $submit );
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
	 * Test the configured state renders without the key.
	 */
	public function test_render_shows_configured_state_without_the_key(): void {
		$page = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'configured', strtolower( $html ) );
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
			'rankkernel_indexnow_url'    => 'https://example.com/a',
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
			'rankkernel_indexnow_url'    => 'https://example.com/a',
		];

		$this->expectException( \RuntimeException::class );

		$this->page()->maybeHandleSave();
	}

	/**
	 * Test a foreign host is rejected before the submit callback runs.
	 */
	public function test_manual_submit_rejects_a_url_on_another_host_without_submitting(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );

		$called = 0;
		$page   = $this->page(
			function () use ( &$called ): void {
				$called++;
			}
		);

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_url'    => 'https://evil.test/a',
		];
		$page->maybeHandleSave();

		$this->assertSame( 0, $called, 'a foreign host must never be submitted' );
	}

	/**
	 * Test a foreign host is logged as rejected and never fetched.
	 */
	public function test_a_foreign_host_is_logged_as_rejected_and_never_fetched(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\expect( 'wp_remote_get' )->never();
		Functions\expect( 'wp_safe_remote_get' )->never();
		Functions\expect( 'wp_remote_head' )->never();
		Functions\expect( 'wp_remote_post' )->never();

		$_POST = [
			'rankkernel_indexnow_action' => 'submit',
			'rankkernel_indexnow_url'    => 'https://evil.test/a',
		];

		$this->page()->maybeHandleSave();

		$entries = $this->settings->logEntries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'https://evil.test/a', $entries[0]['url'] );
		$this->assertSame( 0, $entries[0]['code'] );
		$this->assertSame( 'manual', $entries[0]['source'] );
	}

	/**
	 * Test a valid same host URL reaches the submit callback once.
	 */
	public function test_manual_submit_sends_a_valid_same_host_url(): void {
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
			'rankkernel_indexnow_url'    => 'https://example.com/a',
		];
		$page->maybeHandleSave();

		$this->assertSame( 1, $called );
	}
}
