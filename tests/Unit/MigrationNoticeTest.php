<?php
/**
 * Migration downgrade administrator notice tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\MigrationNotice;

/**
 * Covers the request-local downgrade notice.
 */
final class MigrationNoticeTest extends TestCase {
	/**
	 * Set up WordPress function doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'esc_html' )->alias(
			static fn ( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' )
		);
		Functions\when( '__' )->alias(
			static function ( string $text, string $domain = '' ): string {
				unset( $domain );

				return $text;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel' ] );
		Functions\when( 'get_option' )->justReturn( '0.2.0' );
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a notice with the supplied downgrade versions.
	 *
	 * @param string $ledger Stored ledger version.
	 * @param string $code   Running code version.
	 * @return MigrationNotice
	 */
	private function notice( string $ledger = '0.2.0', string $code = '0.1.0' ): MigrationNotice {
		$notice = new MigrationNotice();
		$notice->capture( $ledger, $code );

		return $notice;
	}

	/**
	 * Render a notice and return the emitted output.
	 *
	 * @param MigrationNotice $notice Notice.
	 * @return string
	 */
	private function render( MigrationNotice $notice ): string {
		ob_start();
		$notice->render();

		return (string) ob_get_clean();
	}

	/**
	 * Test the expected downgrade action and admin notices hooks are registered.
	 */
	public function test_registers_downgrade_and_admin_notice_hooks(): void {
		$hooks = [];

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ) use ( &$hooks ): bool {
				unset( $callback, $priority, $accepted_args );
				$hooks[] = $hook;

				return true;
			}
		);

		( new MigrationNotice() )->register();

		$this->assertSame( [ 'rankkernel/migration/downgrade', 'admin_notices' ], $hooks );
	}

	/**
	 * Test a downgrade renders one warning on a RankKernel screen.
	 */
	public function test_renders_warning_for_admin_on_rankkernel_screen(): void {
		$output = $this->render( $this->notice() );

		$this->assertStringContainsString( 'notice notice-warning is-dismissible', $output );
		$this->assertStringContainsString( '0.2.0', $output );
		$this->assertStringContainsString( '0.1.0', $output );
		$this->assertStringContainsString( 'migrations have been skipped for safety', $output );
	}

	/**
	 * Test the Plugins screen also shows the warning.
	 */
	public function test_renders_on_plugins_screen(): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'plugins' ] );

		$this->assertStringContainsString( 'notice-warning', $this->render( $this->notice() ) );
	}

	/**
	 * Test repeated action captures and render calls do not duplicate output.
	 */
	public function test_repeated_capture_and_render_emit_once(): void {
		$notice = $this->notice();

		$notice->capture( '0.2.0', '0.1.0' );
		$first  = $this->render( $notice );
		$second = $this->render( $notice );

		$this->assertStringContainsString( 'notice-warning', $first );
		$this->assertSame( '', $second );
	}

	/**
	 * Test non-administrators never see the notice.
	 */
	public function test_non_admin_sees_nothing(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( '', $this->render( $this->notice() ) );
	}

	/**
	 * Test unrelated admin screens do not show the notice.
	 */
	public function test_unrelated_screen_sees_nothing(): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'dashboard' ] );

		$this->assertSame( '', $this->render( $this->notice() ) );
	}

	/**
	 * Test a compatible ledger suppresses the notice and clears request state.
	 */
	public function test_compatible_ledger_suppresses_notice(): void {
		Functions\when( 'get_option' )->justReturn( '0.1.0' );

		$this->assertSame( '', $this->render( $this->notice() ) );
	}

	/**
	 * Test no notice is rendered without a downgrade capture.
	 */
	public function test_uncaptured_notice_renders_nothing(): void {
		$this->assertSame( '', $this->render( new MigrationNotice() ) );
	}

	/**
	 * Test missing and malformed ledgers fail closed without a notice.
	 *
	 * @dataProvider malformed_ledger_provider
	 * @param string $ledger Ledger value.
	 */
	public function test_missing_or_malformed_ledger_has_no_notice( string $ledger ): void {
		Functions\when( 'get_option' )->justReturn( $ledger );

		$this->assertSame( '', $this->render( $this->notice() ) );
	}

	/**
	 * Ledger values that must not produce a downgrade notice.
	 *
	 * @return array<string, array{string}>
	 */
	public static function malformed_ledger_provider(): array {
		return [
			'missing'   => [ '0.0.0' ],
			'malformed' => [ 'not-a-version' ],
			'empty'     => [ '' ],
		];
	}

	/**
	 * Test restored compatible versions suppress a previously captured notice.
	 */
	public function test_restored_ledger_suppresses_notice(): void {
		$notice = $this->notice();

		Functions\when( 'get_option' )->justReturn( '0.1.0' );

		$this->assertSame( '', $this->render( $notice ) );
	}

	/**
	 * Test version-like HTML cannot escape the notice markup.
	 */
	public function test_hostile_versions_are_escaped(): void {
		Functions\when( 'get_option' )->justReturn( '0.2.0<script>alert(1)</script>' );

		$output = $this->render( $this->notice( '0.2.0<script>alert(1)</script>', '0.1.0' ) );

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $output );
	}

	/**
	 * Test network-admin style screens do not receive the site notice.
	 */
	public function test_network_admin_screen_has_no_notice(): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) [ 'id' => 'toplevel_page_rankkernel-network' ] );

		$this->assertSame( '', $this->render( $this->notice() ) );
	}

	/**
	 * Test the notice does not write any persistent option.
	 */
	public function test_render_performs_no_option_writes(): void {
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'delete_option' )->never();

		$this->assertStringContainsString( 'notice-warning', $this->render( $this->notice() ) );
	}

	/**
	 * Test missing screen API fails closed.
	 */
	public function test_missing_screen_api_fails_closed(): void {
		Functions\when( 'get_current_screen' )->justReturn( null );

		$this->assertSame( '', $this->render( $this->notice() ) );
	}
}
