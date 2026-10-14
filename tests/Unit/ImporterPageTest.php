<?php
/**
 * Importer page tests, skeleton render pin.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\ImporterPage;

/**
 * Importer Page Test.
 */
final class ImporterPageTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'esc_attr__' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', __FILE__ );
		}
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test the skeleton renders the three step shell.
	 *
	 * The step list is the framework contract: later phases fill each
	 * step, but the shell shape stays pinned here.
	 */
	public function test_render_shows_the_three_step_shell(): void {
		$page = new ImporterPage();

		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Importer', $html );
		$this->assertStringContainsString( 'rk-importer-step-detect', $html );
		$this->assertStringContainsString( 'rk-importer-step-preview', $html );
		$this->assertStringContainsString( 'rk-importer-step-run', $html );
	}

	/**
	 * Test assets enqueue only on the importer screen with REST data.
	 *
	 * The page script drives preview and chunked run through the import
	 * REST endpoint, so it needs the localized base URL plus nonce.
	 */
	public function test_enqueue_assets_registers_script_with_rest_data(): void {
		$enqueued  = [];
		$localized = [];

		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->alias(
			function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'rest_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/wp-json/' . $path );
		Functions\when( 'wp_create_nonce' )->alias( static fn ( string $action = '' ): string => 'nonce:' . $action ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_create_nonce signature.
		Functions\when( 'wp_localize_script' )->alias(
			static function ( string $handle, string $objectName, array $data ) use ( &$localized ): void {
				$localized[ $objectName ] = $data;
			}
		);

		$page = new ImporterPage();

		$page->enqueueAssets( 'other_page' );
		$this->assertSame( [], $enqueued );

		$page->enqueueAssets( ImporterPage::HOOK_SUFFIX );
		$this->assertSame( [ 'rankkernel-importer-admin' ], $enqueued );
		$this->assertSame( 'https://example.com/wp-json/rankkernel/v1/import', $localized['rankkernelImporter']['restBase'] );
		$this->assertSame( 'nonce:wp_rest', $localized['rankkernelImporter']['restNonce'] );
	}

	/**
	 * Test ready detect rows carry preview buttons for the page script.
	 *
	 * The script binds one preview request per source button, so a row
	 * with importable data must expose its source id in a data attr.
	 */
	public function test_render_ready_rows_carry_preview_buttons(): void {
		$page = new ImporterPage(
			[
				new ImportControllerStubReader(
					[
						[
							'id'   => 1,
							'type' => 'post',
							'data' => [],
						],
						[
							'id'   => 2,
							'type' => 'post',
							'data' => [],
						],
						[
							'id'   => 3,
							'type' => 'post',
							'data' => [],
						],
					]
				),
			]
		);

		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'data-rk-importer-root', $html );
		$this->assertStringContainsString( 'data-rk-importer-preview="yoast"', $html );
	}

	/**
	 * Test the run path markup matches the page script contract.
	 *
	 * Per-kind section mounts, the confirmation panel, conflict mode
	 * radio defaulting to skip, run button, progress bar, announce
	 * region and error box must all exist or the script has nothing
	 * to drive.
	 */
	public function test_render_has_run_path_markup(): void {
		$page = new ImporterPage();

		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		foreach ( [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk', 'skipped' ] as $section ) {
			$this->assertStringContainsString( 'data-rk-importer-section="' . $section . '"', $html );
		}

		$this->assertStringContainsString( 'data-rk-importer-confirm="1"', $html );
		$this->assertStringContainsString( 'name="rk-importer-mode"', $html );
		$this->assertStringContainsString( 'data-rk-importer-run', $html );
		$this->assertStringContainsString( 'data-rk-importer-bar', $html );
		$this->assertStringContainsString( 'data-rk-importer-announce', $html );
		$this->assertStringContainsString( 'data-rk-importer-error', $html );
	}
}
