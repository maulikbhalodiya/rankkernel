<?php
/**
 * Dashboard page tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\DashboardPage;
use RankKernel\Modules\ModuleRegistry;

/**
 * Dashboard Page Test.
 */
final class DashboardPageTest extends TestCase {
	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Redirected URLs.
	 *
	 * @var string[]
	 */
	private array $redirects = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
			define( 'RANKKERNEL_VERSION', '0.1.0' );
		}

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', __FILE__ );
		}

		$this->options             = [ 'rankkernel_modules' => [ 'metadata', 'sitemaps' ] ];
		$this->redirects           = [];
		$_POST                     = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url ): void {
				$this->redirects[] = $url;
			}
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/wp-admin/' . $path );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => trim( $value ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.
		Functions\when( 'esc_html__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		$_POST = [];
		$_GET  = [];
		unset( $_SERVER['REQUEST_METHOD'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test cards include every registry module with its state.
	 */
	public function test_cards_list_registry_modules(): void {
		$cards = ( new DashboardPage() )->cards();

		$this->assertCount( count( ModuleRegistry::all() ), $cards );

		$ids = array_column( $cards, 'id' );
		$this->assertContains( 'robots', $ids );
		$this->assertContains( 'metadata', $ids );

		$byId = [];
		foreach ( $cards as $card ) {
			$byId[ $card['id'] ] = $card;
		}

		$this->assertTrue( $byId['metadata']['enabled'] );
		$this->assertFalse( $byId['robots']['enabled'] );
		$this->assertStringContainsString( 'rankkernel-general', $byId['robots']['settingsUrl'] );
		$this->assertSame( '', $byId['ai']['settingsUrl'] );
	}

	/**
	 * Test toggling on enables a module.
	 */
	public function test_toggle_enables_module(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'robots';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertContains( 'robots', $this->options['rankkernel_modules'] );
		$this->assertNotEmpty( $this->redirects );
	}

	/**
	 * Test toggling off disables a module.
	 */
	public function test_toggle_disables_module(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'metadata';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertNotContains( 'metadata', $this->options['rankkernel_modules'] );
		$this->assertContains( 'sitemaps', $this->options['rankkernel_modules'] );
	}

	/**
	 * Test an unknown module id is ignored.
	 */
	public function test_toggle_ignores_unknown_module(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'evil-id';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertSame( [ 'metadata', 'sitemaps' ], $this->options['rankkernel_modules'] );
	}

	/**
	 * Test the toggle requires the capability.
	 */
	public function test_toggle_requires_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'robots';

		$this->expectException( \RuntimeException::class );

		( new DashboardPage() )->maybeHandleSave();
	}

	/**
	 * Test asset enqueue is gated to the dashboard screen.
	 */
	public function test_enqueue_is_gated(): void {
		$enqueued = 0;

		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->alias(
			function () use ( &$enqueued ): void {
				++$enqueued;
			}
		);

		$page = new DashboardPage();

		$page->enqueueAssets( 'other_page' );
		$this->assertSame( 0, $enqueued );

		$page->enqueueAssets( DashboardPage::HOOK_SUFFIX );
		$this->assertSame( 1, $enqueued );
	}
}
