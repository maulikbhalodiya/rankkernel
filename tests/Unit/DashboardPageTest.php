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
use RankKernel\Database\Migrations\MigrationRunner;
use RankKernel\Modules\ModuleRegistry;

/*
 * The dashboard view prints the plugin version from RANKKERNEL_VERSION, which
 * rankkernel.php defines as the single version source. The bootstrap does not
 * load the plugin file, so the constant is declared here the same way the
 * metadata and schema metabox tests already do it.
 */
if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
	define( 'RANKKERNEL_VERSION', '0.1.0-test' );
}

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

		/*
		 * RANKKERNEL_VERSION is declared once at file scope above, so the value
		 * is fixed for the whole run. Do not redeclare it here with a different
		 * value, because the file scope guard always wins and the two values
		 * would silently disagree.
		 */

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
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => trim( $value ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '', string $file = '' ): string => 'https://example.com/' . $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
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
		$registered = [];
		$enqueued   = [];

		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps = [], string $ver = '' ) use ( &$registered ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_register_style signature.
				$registered[ $handle ] = $deps;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);

		$page = new DashboardPage();

		$page->enqueueAssets( 'other_page' );
		$this->assertSame( [], $enqueued );

		$page->enqueueAssets( DashboardPage::HOOK_SUFFIX );
		$this->assertSame( [ 'rankkernel-admin', 'rankkernel-dashboard-admin' ], $enqueued );
		// The sheet styles rk-ui components, so the UI layer must print first.
		$this->assertSame( [ 'rankkernel-admin', 'rankkernel-ui' ], $registered['rankkernel-dashboard-admin'] );
	}

	/**
	 * Test render outputs accessible aria-labels for module action links and buttons.
	 */
	public function test_render_outputs_accessible_aria_labels(): void {
		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'aria-label="Settings for Metadata Engine"', $output );
		$this->assertStringContainsString( 'aria-label="Turn off Metadata Engine module"', $output );
		$this->assertStringContainsString( 'aria-label="Turn on Robots.txt &amp; .htaccess module"', $output );
	}

	/**
	 * Test a recorded migration failure is surfaced on the dashboard.
	 *
	 * A failure that only reached the PHP log was invisible once the log
	 * rotated, so the operator had no way to know migrations were stuck.
	 */
	public function test_render_surfaces_recorded_migration_failure(): void {
		$this->options[ MigrationRunner::FAILURES ] = [
			'0.2.0' => [
				'attempts' => 4,
				'message'  => 'table already exists',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '0.2.0', $output, 'The failing version must be visible' );
		$this->assertStringContainsString( '4 times', $output, 'The attempt count must be visible' );
		$this->assertStringContainsString( 'table already exists', $output, 'The error must be visible' );
		$this->assertStringContainsString( 'Clear and run again', $output, 'A recovery control must be offered' );
		$this->assertStringContainsString( 'rankkernel_migration_clear', $output, 'The control must post its handler' );
	}

	/**
	 * Test the migration failure notice keeps the form out of the paragraph.
	 *
	 * A form inside a p is invalid HTML. Browsers repair it by closing the
	 * paragraph early, which moves the button outside the notice and leaves
	 * assistive technology parsing a fragment it cannot name.
	 */
	public function test_render_does_not_nest_the_form_inside_a_paragraph(): void {
		$this->options[ MigrationRunner::FAILURES ] = [
			'0.2.0' => [
				'attempts' => 4,
				'message'  => 'table already exists',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$formAt = strpos( $output, '<form method="post"' );

		$this->assertNotFalse( $formAt, 'The recovery form must be rendered' );

		$openBefore = substr( $output, 0, $formAt );
		$openP      = strrpos( $openBefore, '<p>' );
		$closeP     = strrpos( $openBefore, '</p>' );

		$this->assertNotFalse( $openP, 'The failure text must sit in a paragraph' );
		$this->assertGreaterThan(
			$openP,
			$closeP,
			'The paragraph holding the failure text must close before the form opens'
		);
	}

	/**
	 * Test a healthy install shows no migration failure notice.
	 */
	public function test_render_omits_migration_notice_when_healthy(): void {
		ob_start();
		( new DashboardPage() )->render();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'Clear and run again', $output );
	}

	/**
	 * Test the clear and rerun control removes only the named version.
	 */
	public function test_migration_clear_removes_the_named_version_only(): void {
		$this->options[ MigrationRunner::FAILURES ] = [
			'0.2.0' => [
				'attempts' => 2,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
			'0.3.0' => [
				'attempts' => 1,
				'message'  => 'other',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		$_SERVER['REQUEST_METHOD']           = 'POST';
		$_POST['rankkernel_migration_clear'] = '0.2.0';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertArrayNotHasKey( '0.2.0', $this->options[ MigrationRunner::FAILURES ], 'The named failure must be cleared' );
		$this->assertArrayHasKey( '0.3.0', $this->options[ MigrationRunner::FAILURES ], 'An unrelated failure must survive' );
		$this->assertArrayNotHasKey(
			MigrationRunner::LEDGER,
			array_diff_key( $this->options, [ MigrationRunner::FAILURES => 1 ] ),
			'Clearing a failure must never write the ledger'
		);
	}

	/**
	 * Test the guard is the die inside check_admin_referer, not a return branch.
	 *
	 * WordPress kills the request itself on a bad nonce, so the return value
	 * is never false and a test against it proves nothing. Stubbing it to
	 * return false anyway pins the real contract: the code must not branch on
	 * that value, because doing so would refuse a request WordPress considers
	 * verified.
	 */
	public function test_migration_clear_does_not_branch_on_the_referer_return_value(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $location ): void {
				$this->redirects[] = $location;
			}
		);

		$this->options[ MigrationRunner::FAILURES ] = [
			'0.2.0' => [
				'attempts' => 2,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		$_SERVER['REQUEST_METHOD']           = 'POST';
		$_POST['rankkernel_migration_clear'] = '0.2.0';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertArrayNotHasKey(
			'0.2.0',
			$this->options[ MigrationRunner::FAILURES ],
			'A verified request must be honoured, so the referer return value must not be branched on'
		);
	}

	/**
	 * Test the clear and rerun control ignores a value that is not a version.
	 *
	 * The option is keyed by version, and clearFailure() removes whatever key
	 * it is handed. Sanitizing proves the value is clean text, not that it
	 * names a migration, so without a shape check a request naming an
	 * arbitrary stored key deletes that key. The seeded key below is not a
	 * version shape, so it must survive.
	 */
	public function test_migration_clear_ignores_a_value_that_is_not_a_version(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $location ): void {
				$this->redirects[] = $location;
			}
		);

		$this->options[ MigrationRunner::FAILURES ] = [
			'not a version' => [
				'attempts' => 2,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
			'0.2.0'         => [
				'attempts' => 2,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		$_SERVER['REQUEST_METHOD']           = 'POST';
		$_POST['rankkernel_migration_clear'] = 'not a version';

		( new DashboardPage() )->maybeHandleSave();

		$this->assertArrayHasKey(
			'not a version',
			$this->options[ MigrationRunner::FAILURES ],
			'A key that is not a version shape must never reach clearFailure'
		);
		$this->assertArrayHasKey( '0.2.0', $this->options[ MigrationRunner::FAILURES ], 'A valid version must be untouched too' );
	}

	/**
	 * Test the clear and rerun control refuses without the capability.
	 */
	public function test_migration_clear_requires_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->options[ MigrationRunner::FAILURES ] = [
			'0.2.0' => [
				'attempts' => 2,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		$_SERVER['REQUEST_METHOD']           = 'POST';
		$_POST['rankkernel_migration_clear'] = '0.2.0';

		$this->expectException( \RuntimeException::class );

		try {
			( new DashboardPage() )->maybeHandleSave();
		} finally {
			$this->assertArrayHasKey( '0.2.0', $this->options[ MigrationRunner::FAILURES ], 'A refused request must clear nothing' );
			$this->assertSame( [], $this->redirects, 'A refused request must not redirect away' );
		}
	}

	/**
	 * Test the clear and rerun control refuses a bad nonce.
	 */
	public function test_migration_clear_requires_valid_nonce(): void {
		// WordPress does not return false from check_admin_referer() on a bad
		// nonce, it calls wp_die() itself and never comes back. The stub
		// reproduces that contract rather than returning a value production
		// can never produce, so the test covers the path that actually runs.
		Functions\when( 'check_admin_referer' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$this->options[ MigrationRunner::FAILURES ] = [
			'0.2.0' => [
				'attempts' => 2,
				'message'  => 'boom',
				'failedAt' => '2026-01-01 00:00:00',
			],
		];

		$_SERVER['REQUEST_METHOD']           = 'POST';
		$_POST['rankkernel_migration_clear'] = '0.2.0';

		$this->expectException( \RuntimeException::class );

		try {
			( new DashboardPage() )->maybeHandleSave();
		} finally {
			$this->assertArrayHasKey( '0.2.0', $this->options[ MigrationRunner::FAILURES ], 'A bad nonce must clear nothing' );
		}
	}

	/**
	 * Test the module toggle refuses a bad nonce.
	 *
	 * TQA-02. The toggle had a capability test but no nonce test, so deleting
	 * the check_admin_referer call from handleToggle left the suite green. It
	 * is the one nonce gate on this page with no rejection coverage, because a
	 * bad nonce must leave the module map exactly as it was.
	 */
	public function test_toggle_requires_valid_nonce(): void {
		Functions\when( 'check_admin_referer' )->justReturn( false );

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_POST['rankkernel_module_toggle'] = 'robots';

		$this->expectException( \RuntimeException::class );

		try {
			( new DashboardPage() )->maybeHandleSave();
		} finally {
			$this->assertSame(
				[ 'metadata', 'sitemaps' ],
				$this->options['rankkernel_modules'],
				'a bad nonce must toggle no module'
			);
			$this->assertSame( [], $this->redirects, 'a bad nonce must not redirect away' );
		}
	}
}
