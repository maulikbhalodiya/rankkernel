<?php
/**
 * 404 Monitor admin page tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\AdminMenu;
use RankKernel\Admin\NotFoundPage;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Monitor\MonitorRepository;
use RankKernel\Modules\Monitor\MonitorSettings;
use RankKernel\Settings\SettingsStore;

/**
 * Covers the 404 Monitor dashboard without a browser.
 *
 * Uses the in memory monitor double plus Brain Monkey, following the save
 * then redirect pattern of the existing admin tests. RANKERNEL TESTING keeps
 * the redirect helper from exiting.
 */
final class MonitorAdminTest extends TestCase {
	/**
	 * Option storage backing get option and update option.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * In memory 404 table.
	 */
	private MonitorFakeDb $db;

	/**
	 * Last redirect URL captured from wp safe redirect.
	 */
	private string $lastRedirect = '';

	/**
	 * Set up doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->options      = [];
		$this->db           = new MonitorFakeDb();
		$this->lastRedirect = '';

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url ): bool {
				$this->lastRedirect = $url;

				return true;
			}
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-admin/' . ltrim( $p, '/' ) );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/wp-content/plugins/rankkernel/' . $path );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params = [], string $url = '' ): string {
				if ( [] === $params ) {
					return $url;
				}

				$sep = str_contains( $url, '?' ) ? '&' : '?';

				return $url . $sep . http_build_query( $params );
			}
		);
		Functions\when( 'wp_nonce_url' )->alias(
			static function ( string $url ): string {
				return $url . '&_wpnonce=valid';
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', $v ) )
		);
		// Test double for sanitize_text_field, strip_tags mirrors the core behavior closely enough.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $v ): mixed => is_string( $v ) ? stripslashes( $v ) : $v );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr__' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias(
			static function ( string $v ): string {
				$clean = filter_var( $v, FILTER_SANITIZE_URL );

				if ( is_string( $clean ) && '' !== $clean ) {
					return $clean;
				}

				return $v;
			}
		);
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'number_format_i18n' )->alias( static fn ( mixed $n ): string => number_format( (int) $n ) );
		Functions\when( 'current_time' )->alias( static fn (): string => gmdate( 'Y-m-d H:i:s' ) );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'submit_button' )->justReturn( '' );
		Functions\when( 'checked' )->alias(
			static function ( mixed $first, mixed $second ): string {
				$same = (string) $first === (string) $second && '' !== (string) $first;

				if ( $same || ( true === $first && true === $second ) ) {
					return 'checked="checked"';
				}

				return '';
			}
		);
		Functions\when( 'selected' )->alias(
			static function ( mixed $first, mixed $second ): string {
				if ( (string) $first === (string) $second && '' !== (string) $first ) {
					return 'selected="selected"';
				}

				return '';
			}
		);
		Functions\when( 'wp_register_style' )->justReturn( null );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_register_script' )->justReturn( null );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
	}

	/**
	 * Reset globals.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();

		$_POST                     = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	/**
	 * Build the page with the fake database.
	 *
	 * @param bool $redirectsOn Whether the Redirects module reads as enabled.
	 */
	private function makePage( bool $redirectsOn = true ): NotFoundPage {
		$this->options['rankkernel_modules'] = $redirectsOn ? [ 'redirects' ] : [];

		return new NotFoundPage(
			new MonitorRepository( $this->db ),
			new MonitorSettings(),
			new ModuleEnableMap()
		);
	}

	/**
	 * Stub a passing capability plus nonce check.
	 */
	private function allowAccess(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
	}

	/**
	 * Seed one 404 row in the fake table.
	 *
	 * @param string $uri URI to track.
	 * @return int New row id.
	 */
	private function seedEntry( string $uri ): int {
		return $this->db->seed(
			[
				'uri_hash' => hash( 'sha256', $uri ),
				'uri'      => $uri,
			]
		);
	}

	/**
	 * Seed many rows for usage threshold tests.
	 *
	 * @param int $count Rows to seed.
	 */
	private function seedMany( int $count ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			$uri = '/old-page-' . $i;

			$this->db->seed(
				[
					'uri_hash' => hash( 'sha256', $uri ),
					'uri'      => $uri,
				]
			);
		}
	}

	/**
	 * Post a Clear Log save.
	 */
	private function postClear(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_clear' => '1',
			'_wpnonce'             => 'valid',
		];
	}

	/**
	 * Render the page and return the markup.
	 */
	private function renderPage( NotFoundPage $page ): string {
		ob_start();
		$page->render();
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * The submenu registers under the RankKernel menu with hooks.
	 */
	public function test_menu_registers_monitor_submenu_with_exact_args(): void {
		$menu = new AdminMenu( new SettingsStore(), new ModuleEnableMap() );

		$captured = null;

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'rankkernel',
				'404 Monitor',
				'404 Monitor',
				'manage_options',
				'rankkernel-404',
				\Mockery::type( 'callable' )
			)
			->andReturnUsing(
				static function ( string $parentSlug, string $title, string $menuTitle, string $cap, string $slug, mixed $cb ) use ( &$captured ): string {
					$captured = $cb;

					return 'rankkernel_page_rankkernel-404';
				}
			);

		Functions\expect( 'add_action' )
			->once()
			->with( 'load-rankkernel_page_rankkernel-404', \Mockery::type( 'callable' ) )
			->andReturn( true );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_enqueue_scripts', \Mockery::type( 'callable' ) )
			->andReturn( true );

		$menu->addMonitorPage();

		$this->assertIsCallable( $captured );

		ob_start();
		$rendered = $captured;
		$rendered();
		$html = ob_get_clean();

		$this->assertStringContainsString( '404 Monitor', is_string( $html ) ? $html : '' );
	}

	/**
	 * Missing capability stops the clear with 403.
	 */
	public function test_clear_without_capability_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$this->postClear();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Failed nonce stops the clear.
	 */
	public function test_clear_with_bad_nonce_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$this->postClear();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Authorized clear empties the log through the bounded path.
	 */
	public function test_clear_authorized_empties_log(): void {
		$this->seedEntry( '/a' );
		$this->seedEntry( '/b' );
		$this->seedEntry( '/c' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postClear();

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 0, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=cleared', $this->lastRedirect );
	}

	/**
	 * Bulk delete removes the chosen rows.
	 */
	public function test_bulk_delete_removes_chosen_rows(): void {
		$this->seedEntry( '/a' );
		$this->seedEntry( '/b' );
		$this->seedEntry( '/c' );

		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_bulk' => '1',
			'_wpnonce'            => 'valid',
			'rk_bulk_action'      => 'delete',
			'entry_ids'           => [ '1', '2', 'nope', '0' ],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 1, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=bulk', $this->lastRedirect );
	}

	/**
	 * Bulk without rows reports an error flag.
	 */
	public function test_bulk_without_selection_reports_error(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_bulk' => '1',
			'_wpnonce'            => 'valid',
			'rk_bulk_action'      => 'delete',
			'entry_ids'           => [],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertStringContainsString( 'rk_error=bulk_none', $this->lastRedirect );
	}

	/**
	 * Row delete removes the entry and redirects.
	 */
	public function test_row_delete_removes_entry(): void {
		$id = $this->seedEntry( '/a' );

		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET                      = [
			'rk_action' => 'delete',
			'entry'     => (string) $id,
			'_wpnonce'  => 'valid',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertArrayNotHasKey( $id, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=deleted', $this->lastRedirect );
	}

	/**
	 * Row delete of a missing entry reports not found.
	 */
	public function test_row_delete_missing_reports_not_found(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET                      = [
			'rk_action' => 'delete',
			'entry'     => '999',
			'_wpnonce'  => 'valid',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertStringContainsString( 'rk_error=not_found', $this->lastRedirect );
	}

	/**
	 * Row actions verify the nonce.
	 */
	public function test_row_action_with_bad_nonce_dies(): void {
		$this->seedEntry( '/a' );

		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET                      = [
			'rk_action' => 'delete',
			'entry'     => '1',
			'_wpnonce'  => 'bad',
		];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Settings save stores sanitized values with clamped ranges.
	 */
	public function test_settings_save_stores_sanitized_values_with_clamping(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_settings_save' => '1',
			'_wpnonce'                     => 'valid',
			'rk_advanced_fields'           => '1',
			'rk_retention_days'            => '0',
			'rk_max_rows'                  => '99999',
			'rk_flood_budget'              => '0',
			'rk_flood_window'              => '99999',
			'rk_excl_comparator'           => [ 'prefix', 'bogus', 'exact' ],
			'rk_excl_value'                => [ '/wp-admin', '/x', '' ],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ MonitorSettings::OPTION ];

		$this->assertTrue( $stored['advanced_fields'] );
		$this->assertFalse( $stored['ignore_query'] );
		$this->assertSame( 1, $stored['retention_days'] );
		$this->assertSame( 10000, $stored['max_rows'] );
		$this->assertSame( 1, $stored['flood_budget'] );
		$this->assertSame( 3600, $stored['flood_window'] );
		$this->assertSame(
			[
				[
					'comparator' => 'prefix',
					'value'      => '/wp-admin',
				],
			],
			$stored['exclusions']
		);
		$this->assertStringContainsString( 'rk_notice=settings', $this->lastRedirect );
	}

	/**
	 * Near limit states flip at the 80 and 90 percent thresholds.
	 */
	public function test_limit_state_thresholds(): void {
		$this->assertSame( 'normal', NotFoundPage::limitState( 0.0 ) );
		$this->assertSame( 'normal', NotFoundPage::limitState( 79.9 ) );
		$this->assertSame( 'warn', NotFoundPage::limitState( 80.0 ) );
		$this->assertSame( 'warn', NotFoundPage::limitState( 89.9 ) );
		$this->assertSame( 'high', NotFoundPage::limitState( 90.0 ) );
		$this->assertSame( 'high', NotFoundPage::limitState( 100.0 ) );
	}

	/**
	 * Below 80 percent renders no near limit notice.
	 */
	public function test_render_below_threshold_shows_no_limit_notice(): void {
		$this->seedMany( 5 );

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( '5 / 1,000', $html );
		$this->assertStringNotContainsString( 'configured entry limit', $html );
	}

	/**
	 * 80 percent renders the informational notice in plain language.
	 */
	public function test_render_warn_threshold_shows_informational_notice(): void {
		$this->options['rankkernel_404_settings'] = [ 'max_rows' => 100 ];
		$this->seedMany( 85 );

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringContainsString( 'approaching its configured entry limit', $html );
		$this->assertStringContainsString( 'oldest entries are removed automatically', $html );
		$this->assertStringContainsString( 'clear the log manually', $html );
		$this->assertStringNotContainsString( 'database', strtolower( $html ) );
	}

	/**
	 * 90 percent renders the stronger notice.
	 */
	public function test_render_high_threshold_shows_stronger_notice(): void {
		$this->options['rankkernel_404_settings'] = [ 'max_rows' => 100 ];
		$this->seedMany( 95 );

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'nearly at its configured entry limit', $html );
		$this->assertStringContainsString( 'oldest entries are removed automatically', $html );
	}

	/**
	 * Create redirect links carry the 404 source to the Redirects screen.
	 */
	public function test_create_redirect_url_prefills_source(): void {
		$page = $this->makePage();
		$url  = $page->createRedirectUrl( '/old-page' );

		$this->assertStringContainsString( 'rankkernel-redirects', $url );
		$this->assertStringContainsString( 'rk_source=', $url );
		$this->assertStringContainsString( rawurlencode( '/old-page' ), $url );
	}

	/**
	 * The row action renders when Redirects is enabled.
	 */
	public function test_render_shows_create_redirect_when_redirects_on(): void {
		$this->seedEntry( '/old-page' );

		$page = $this->makePage( true );
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Create Redirect', $html );
		$this->assertStringNotContainsString( 'Enable Redirects', $html );
	}

	/**
	 * The row action hides with an explanation when Redirects is off.
	 */
	public function test_render_hides_create_redirect_when_redirects_off(): void {
		$this->seedEntry( '/old-page' );

		$page = $this->makePage( false );
		$html = $this->renderPage( $page );

		$this->assertStringNotContainsString( 'Create Redirect', $html );
		$this->assertStringContainsString( 'Enable Redirects', $html );
	}

	/**
	 * Assets load only on the Monitor screen hook.
	 */
	public function test_enqueue_gates_assets_to_screen_hook(): void {
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/p/' . $path );

		$registered = [];
		$enqueued   = [];

		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps = [], string $ver = '' ) use ( &$registered ): void {
				$registered[ $handle ] = $ver . '|' . $src;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle ) use ( &$enqueued ): void {
				$enqueued[] = $handle;
			}
		);
		Functions\when( 'wp_register_script' )->alias(
			static function (): void {
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			static function (): void {
			}
		);

		$page = $this->makePage();
		$page->enqueueAssets( 'toplevel_page_rankkernel' );

		$this->assertSame( [], $registered );
		$this->assertSame( [], $enqueued );

		$page->enqueueAssets( NotFoundPage::HOOK_SUFFIX );

		$this->assertArrayHasKey( 'rankkernel-monitor-admin', $registered );
		$this->assertContains( 'rankkernel-monitor-admin', $enqueued );
		$this->assertStringContainsString( 'monitor-admin.css', (string) $registered['rankkernel-monitor-admin'] );
	}

	/**
	 * Empty screens explain what will appear here.
	 */
	public function test_render_empty_state_explains_tracking(): void {
		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'No 404 entries are being tracked yet', $html );
		$this->assertStringContainsString( 'appears here', $html );
	}

	/**
	 * The list shows rows plus search plus sort controls.
	 */
	public function test_render_list_shows_rows_and_controls(): void {
		$this->seedEntry( '/old-page' );

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( '/old-page', $html );
		$this->assertStringContainsString( 'URL', $html );
		$this->assertStringContainsString( 'Hits', $html );
		$this->assertStringContainsString( 'First Seen', $html );
		$this->assertStringContainsString( 'Last Seen', $html );
		$this->assertStringContainsString( 'Search addresses', $html );
		$this->assertStringContainsString( 'Bulk actions', $html );
	}

	/**
	 * Search with no matches shows the filtered empty state.
	 */
	public function test_render_search_without_matches_shows_filtered_empty_state(): void {
		$this->seedEntry( '/old-page' );

		$page = $this->makePage();

		$_GET = [ 's' => 'zzz-no-match' ];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'No 404 entries match your search', $html );
	}

	/**
	 * The settings card explains every field.
	 */
	public function test_render_settings_explains_fields(): void {
		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Retention days', $html );
		$this->assertStringContainsString( 'Maximum entries', $html );
		$this->assertStringContainsString( 'Flood budget', $html );
		$this->assertStringContainsString( 'Flood window', $html );
		$this->assertStringContainsString( 'Query strings', $html );
		$this->assertStringContainsString( 'Exclusions', $html );
		$this->assertStringContainsString( 'never stored', $html );
	}

	/**
	 * The summary shows usage against the maximum plus retention.
	 */
	public function test_render_summary_shows_usage_and_retention(): void {
		$this->seedEntry( '/a' );
		$this->seedEntry( '/b' );

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Log Status', $html );
		$this->assertStringContainsString( '2 / 1,000', $html );
		$this->assertStringContainsString( '30 days', $html );
		$this->assertStringContainsString( 'rk-clear-form', $html );
		$this->assertStringContainsString( 'Manual clearing is separate', $html );
	}

	/**
	 * Missing capability stops a row delete with 403.
	 */
	public function test_row_delete_without_capability_dies(): void {
		$this->seedEntry( '/a' );

		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET                      = [
			'rk_action' => 'delete',
			'entry'     => '1',
			'_wpnonce'  => 'valid',
		];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Missing capability stops a bulk delete with 403.
	 */
	public function test_bulk_without_capability_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_bulk' => '1',
			'_wpnonce'            => 'valid',
			'rk_bulk_action'      => 'delete',
			'entry_ids'           => [ '1' ],
		];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Failed nonce stops a bulk delete.
	 */
	public function test_bulk_with_bad_nonce_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_bulk' => '1',
			'_wpnonce'            => 'bad',
			'rk_bulk_action'      => 'delete',
			'entry_ids'           => [ '1' ],
		];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Missing capability stops a settings save with 403.
	 */
	public function test_settings_without_capability_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_settings_save' => '1',
			'_wpnonce'                     => 'valid',
		];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Failed nonce stops a settings save.
	 */
	public function test_settings_with_bad_nonce_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_404_settings_save' => '1',
			'_wpnonce'                     => 'bad',
		];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		ob_start();
		try {
			$page->maybeHandleSave();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Create redirect links carry the return target for the round trip.
	 */
	public function test_create_redirect_url_carries_return(): void {
		$page = $this->makePage();
		$url  = $page->createRedirectUrl( '/old-page' );

		$this->assertStringContainsString( 'rankkernel-redirects', $url );
		$this->assertStringContainsString( 'rk_source=', $url );
		$this->assertStringContainsString( 'rk_return=rankkernel-404', $url );
	}

	/**
	 * Settings render collapsed with the exclusions editor affordances.
	 */
	public function test_render_settings_collapsible_with_exclusions_tools(): void {
		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Monitor Settings', $html );
		$this->assertStringContainsString( 'rk-exclusions-body', $html );
		$this->assertStringContainsString( 'Add Exclusion', $html );
		$this->assertStringContainsString( 'rk-exclusion-template', $html );
		$this->assertStringContainsString( 'Remove', $html );
	}

	/**
	 * Row details explain disabled advanced fields instead of empty columns.
	 */
	public function test_row_details_explain_disabled_advanced_fields(): void {
		$this->seedEntry( '/old-page' );

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Details', $html );
		$this->assertStringContainsString( 'logging is off', $html );
		$this->assertStringNotContainsString( '<dt>Referer</dt>', $html );
		$this->assertStringNotContainsString( '<dt>User agent</dt>', $html );
	}

	/**
	 * Row details show referer and user agent when advanced logging is on.
	 */
	public function test_row_details_show_advanced_fields_when_enabled(): void {
		$this->db->seed(
			[
				'uri_hash'   => hash( 'sha256', '/old-page' ),
				'uri'        => '/old-page',
				'referer'    => 'https://example.com/start',
				'user_agent' => 'TestAgent/1.0',
			]
		);

		$this->options[ MonitorSettings::OPTION ] = array_merge(
			MonitorSettings::defaults(),
			[ 'advanced_fields' => true ]
		);

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( '<dt>Referer</dt>', $html );
		$this->assertStringContainsString( 'TestAgent/1.0', $html );
		$this->assertStringNotContainsString( 'logging is off', $html );
	}

	/**
	 * The summary shows the most recent activity when entries exist.
	 */
	public function test_render_summary_shows_recent_activity(): void {
		$this->seedEntry( '/a' );

		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Most recent', $html );
	}

	/**
	 * A redirect save returning here renders its saved notice.
	 */
	public function test_redirect_saved_notice_renders(): void {
		$page = $this->makePage();

		$_GET = [ 'rk_notice' => 'redirect_saved' ];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Redirect saved.', $html );
	}

	/**
	 * A carried inconclusive chain never presents a recommendation as safe.
	 */
	public function test_carried_chain_unknown_hides_recommendation(): void {
		$page = $this->makePage();

		$_GET = [
			'rk_notice'        => 'redirect_saved',
			'rk_chain'         => '/a -> /b',
			'rk_final'         => '/c',
			'rk_chain_unknown' => '1',
		];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Redirect saved.', $html );
		$this->assertStringContainsString( 'Redirect chain detected', $html );
		$this->assertStringContainsString( 'could not determine the final destination', $html );
		$this->assertStringNotContainsString( 'Consider pointing', $html );
	}
}
