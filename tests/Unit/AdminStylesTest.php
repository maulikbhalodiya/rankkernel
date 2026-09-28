<?php
/**
 * Admin stylesheet handle contract tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\AdminStyles;

/**
 * Locks the shared admin style layer to registered handles.
 *
 * The token layer and the optional UI layer used to reach feature
 * stylesheets through a CSS @import, which the browser fetches sequentially
 * after parsing each parent sheet. These tests pin the registered handles,
 * the UI dependency on the token handle, the fail safe guards, and the
 * removal of the @import chain.
 */
final class AdminStylesTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
			define( 'RANKKERNEL_VERSION', '0.1.0' );
		}

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Capture registered styles and enqueued handles.
	 *
	 * @param array<string, array{src: string, deps: array<string>, ver: string}> $registered Captured registrations.
	 * @param array<string>                                                       $enqueued   Captured enqueue handles.
	 * @return void
	 */
	private function captureStyles( array &$registered, array &$enqueued ): void {
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.

		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps = [], string $ver = '' ) use ( &$registered ): void {
				$registered[ $handle ] = [
					'src'  => $src,
					'deps' => $deps,
					'ver'  => $ver,
				];
			}
		);

		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);
	}

	/**
	 * The token layer registers and enqueues its own versioned handle.
	 */
	#[RunInSeparateProcess]
	public function test_token_layer_registers_and_enqueues_its_own_versioned_handle(): void {
		$registered = [];
		$enqueued   = [];

		$this->captureStyles( $registered, $enqueued );

		AdminStyles::enqueueTokenLayer( (string) RANKKERNEL_FILE );

		$this->assertArrayHasKey( AdminStyles::TOKEN_HANDLE, $registered );
		$this->assertStringContainsString( 'rankkernel-admin.css', $registered[ AdminStyles::TOKEN_HANDLE ]['src'] );
		$this->assertSame( [], $registered[ AdminStyles::TOKEN_HANDLE ]['deps'] );
		$this->assertSame( '0.1.0', $registered[ AdminStyles::TOKEN_HANDLE ]['ver'] );
		$this->assertSame( [ AdminStyles::TOKEN_HANDLE ], $enqueued );
	}

	/**
	 * The UI layer declares the token handle as its dependency.
	 */
	#[RunInSeparateProcess]
	public function test_ui_layer_depends_on_the_token_handle(): void {
		$registered = [];
		$enqueued   = [];

		$this->captureStyles( $registered, $enqueued );

		AdminStyles::enqueueUiLayer( (string) RANKKERNEL_FILE );

		$this->assertArrayHasKey( AdminStyles::UI_HANDLE, $registered );
		$this->assertStringContainsString( 'rankkernel-ui.css', $registered[ AdminStyles::UI_HANDLE ]['src'] );
		$this->assertSame( [ AdminStyles::TOKEN_HANDLE ], $registered[ AdminStyles::UI_HANDLE ]['deps'] );
		$this->assertSame( [ AdminStyles::UI_HANDLE ], $enqueued );
	}

	/**
	 * The token layer registers nothing when plugins_url is unavailable.
	 */
	#[RunInSeparateProcess]
	public function test_token_layer_fails_safe_without_plugins_url(): void {
		Functions\expect( 'wp_register_style' )->never();
		Functions\expect( 'wp_enqueue_style' )->never();

		AdminStyles::enqueueTokenLayer( (string) RANKKERNEL_FILE );

		$this->assertTrue( true );
	}

	/**
	 * No feature stylesheet pulls the token layer with a render blocking import.
	 */
	public function test_feature_sheets_carry_no_css_import(): void {
		$sheets = [
			'analysis-column.css',
			'dashboard-admin.css',
			'metadata-classic.css',
			'metadata-editor.css',
			'redirects-admin.css',
			'settings-admin.css',
			'instant-indexing-admin.css',
		];

		foreach ( $sheets as $sheet ) {
			$path = dirname( __DIR__, 2 ) . '/assets/css/' . $sheet;

			$this->assertFileExists( $path );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local plugin asset, not a remote URL.
			$css = (string) file_get_contents( $path );

			$this->assertStringNotContainsString( '@import', $css, $sheet . ' must not use a render blocking @import.' );
		}
	}
}
