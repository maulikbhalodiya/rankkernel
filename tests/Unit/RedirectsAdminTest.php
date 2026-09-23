<?php
/**
 * Redirects admin page tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\AdminMenu;
use RankKernel\Admin\RedirectsPage;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Redirects\CsvHandler;
use RankKernel\Modules\Redirects\DestinationValidator;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Redirects\RedirectsSettings;
use RankKernel\Modules\Redirects\Validator;
use RankKernel\Settings\SettingsStore;

/**
 * Covers the Redirect Manager without a browser.
 *
 * Uses the in memory redirect double plus Brain Monkey, following the save
 * then redirect pattern of the existing admin tests. RANKERNEL TESTING keeps
 * the redirect helper from exiting.
 */
final class RedirectsAdminTest extends TestCase {
	/**
	 * Option storage backing get option and update option.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * In memory redirect table.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * Last redirect URL captured from wp safe redirect.
	 *
	 * @var string
	 */
	private string $lastRedirect = '';

	/**
	 * Payload captured from wp_send_json_success.
	 *
	 * @var array<string, mixed>
	 */
	private array $ajaxPayload = [];

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

		$this->options      = [];
		$this->db           = new RedirectsFakeDb();
		$this->lastRedirect = '';
		$this->ajaxPayload  = [];

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
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/wp-content/plugins/rankkernel/' . $path );
		Functions\when( 'wp_create_nonce' )->alias( static fn ( mixed $action = -1 ): string => 'nonce-' . (string) $action );
		Functions\when( 'wp_localize_script' )->justReturn( true );
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
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				// Test double for wp_parse_url itself, so the native parser is required here.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				$parts = parse_url( $url );

				if ( false === $parts ) {
					return false;
				}

				if ( -1 === $component ) {
					return $parts;
				}

				$map = [
					PHP_URL_SCHEME   => 'scheme',
					PHP_URL_HOST     => 'host',
					PHP_URL_PORT     => 'port',
					PHP_URL_USER     => 'user',
					PHP_URL_PASS     => 'pass',
					PHP_URL_PATH     => 'path',
					PHP_URL_QUERY    => 'query',
					PHP_URL_FRAGMENT => 'fragment',
				];

				$key = $map[ $component ] ?? null;

				if ( null === $key ) {
					return null;
				}

				return $parts[ $key ] ?? null;
			}
		);
		Functions\when( 'wp_allowed_protocols' )->alias( static fn (): array => [ 'http', 'https' ] );
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
		Functions\when( 'esc_textarea' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
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
		Functions\when( 'wp_kses' )->alias(
			static function ( string $v, array $allowed ): string {
				$tags = '';
				foreach ( array_keys( $allowed ) as $tag ) {
					$tags .= '<' . (string) $tag . '>';
				}

				return strip_tags( $v, $tags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test double emulating the kses allowlist with a native tag filter.
			}
		);
		Functions\when( 'number_format_i18n' )->alias( static fn ( mixed $n ): string => number_format( (int) $n ) );
		Functions\when( 'current_time' )->alias( static fn (): string => gmdate( 'Y-m-d H:i:s' ) );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'submit_button' )->justReturn( '' );
		Functions\when( 'check_ajax_referer' )->justReturn( 1 );
		Functions\when( 'wp_send_json_success' )->alias(
			function ( mixed $data = null ): void {
				$this->ajaxPayload = is_array( $data ) ? $data : [];
			}
		);
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
	}

	/**
	 * Reset globals.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();

		$_POST                     = [];
		$_GET                      = [];
		$_FILES                    = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	/**
	 * Build the page with the fake database.
	 *
	 * @return RedirectsPage The result.
	 */
	private function makePage(): RedirectsPage {
		return new RedirectsPage(
			new RedirectRepository( $this->db ),
			new RedirectsSettings(),
			new Validator(),
			new DestinationValidator()
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
	 * Seed one rule in the fake table.
	 *
	 * @param string $source    Source.
	 * @param string $target    Target.
	 * @param string $code      Code.
	 * @param string $matchType Match Type.
	 * @param bool   $active    Active.
	 * @return int New row id.
	 */
	private function seedRule( string $source, string $target, string $code = '301', string $matchType = 'exact', bool $active = true ): int {
		$repo = new RedirectRepository( $this->db );

		return $repo->insert(
			[
				'source'     => $source,
				'target'     => $target,
				'code'       => $code,
				'match_type' => $matchType,
				'is_active'  => $active,
			]
		);
	}

	/**
	 * Post an add form save.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 */
	private function postAdd( array $overrides = [] ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array_merge(
			[
				'rankkernel_redirect_save' => '1',
				'_wpnonce'                 => 'valid',
				'rk_source'                => '/a',
				'rk_match_type'            => 'exact',
				'rk_target'                => '/b',
				'rk_code'                  => '301',
				'rk_active'                => '1',
			],
			$overrides
		);
	}

	/**
	 * Render the page and return the markup.
	 *
	 * @param RedirectsPage $page Page.
	 * @return string The result.
	 */
	private function renderPage( RedirectsPage $page ): string {
		ob_start();
		$page->render();
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Invoke the AJAX list handler with the given posted filters.
	 *
	 * @param RedirectsPage        $page    Page.
	 * @param array<string, mixed> $filters Filters posted alongside the action and nonce.
	 * @return string Rendered list section HTML.
	 */
	private function ajaxList( RedirectsPage $page, array $filters = [] ): string {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array_merge(
			[
				'action'      => 'rankkernel_redirects_list',
				'_ajax_nonce' => 'nonce-rankkernel_redirects_list',
			],
			$filters
		);

		$page->handleAjaxList();

		$html = $this->ajaxPayload['html'] ?? '';

		return is_string( $html ) ? $html : '';
	}

	/**
	 * The submenu registers under the RankKernel menu with hooks.
	 */
	public function test_menu_registers_redirects_submenu_with_exact_args(): void {
		$menu = new AdminMenu( new SettingsStore(), new ModuleEnableMap() );

		$captured = null;

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'rankkernel',
				'Redirects',
				'Redirects',
				'manage_options',
				'rankkernel-redirects',
				\Mockery::type( 'callable' )
			)
			->andReturnUsing(
				static function ( string $parentSlug, string $title, string $menuTitle, string $cap, string $slug, mixed $cb ) use ( &$captured ): string {
					$captured = $cb;

					return 'rankkernel_page_rankkernel-redirects';
				}
			);

		Functions\expect( 'add_action' )
			->once()
			->with( 'load-rankkernel_page_rankkernel-redirects', \Mockery::type( 'callable' ) )
			->andReturn( true );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_enqueue_scripts', \Mockery::type( 'callable' ) )
			->andReturn( true );

		$menu->addRedirectsPage();

		$this->assertIsCallable( $captured );

		ob_start();
		$rendered = $captured;
		$rendered();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Redirects', is_string( $html ) ? $html : '' );
	}

	/**
	 * Missing capability stops the save with 403.
	 */
	public function test_form_save_without_capability_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$this->postAdd();

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
	 * Non-uploaded file path is rejected by the upload probe.
	 */
	public function test_import_non_uploaded_file_rejected(): void {
		$tmp = (string) tempnam( sys_get_temp_dir(), 'rkcsv' );
		file_put_contents( $tmp, "source,target,code,match_type,active,hits,last_accessed\n/from,/to,301,exact,yes,0,\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		try {
			$page = new RedirectsPage(
				new RedirectRepository( $this->db ),
				new RedirectsSettings(),
				new Validator(),
				new DestinationValidator(),
				static fn ( string $p ): bool => false // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub callback signature.
			);

			$this->allowAccess();

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = [
				'rankkernel_redirect_import' => '1',
				'_wpnonce'                   => 'valid',
			];
			$_FILES                    = [
				'rk_csv_file' => [
					'name'     => 'test.csv',
					'type'     => 'text/csv',
					'tmp_name' => $tmp,
					'error'    => UPLOAD_ERR_OK,
					'size'     => 100,
				],
			];

			ob_start();
			$page->maybeHandleSave();
			ob_end_clean();

			$result = $page->import_result();
			$this->assertIsArray( $result );
			$this->assertSame( 'The uploaded file could not be read.', $result['errors'][0]['reason'] );
		} finally {
			unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Invalid file extension fails wp_check_filetype.
	 */
	public function test_import_invalid_filetype_rejected(): void {
		$tmp = (string) tempnam( sys_get_temp_dir(), 'rkcsv' );
		file_put_contents( $tmp, 'some content' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		Functions\when( 'wp_check_filetype' )->alias(
			static fn (): array => [
				'ext'  => false,
				'type' => false,
			]
		);

		try {
			$page = new RedirectsPage(
				new RedirectRepository( $this->db ),
				new RedirectsSettings(),
				new Validator(),
				new DestinationValidator(),
				static fn ( string $p ): bool => true // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub callback signature.
			);

			$this->allowAccess();

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = [
				'rankkernel_redirect_import' => '1',
				'_wpnonce'                   => 'valid',
			];
			$_FILES                    = [
				'rk_csv_file' => [
					'name'     => 'malicious.php',
					'type'     => 'text/plain',
					'tmp_name' => $tmp,
					'error'    => UPLOAD_ERR_OK,
					'size'     => 50,
				],
			];

			ob_start();
			$page->maybeHandleSave();
			ob_end_clean();

			$result = $page->import_result();
			$this->assertIsArray( $result );
			$this->assertSame( 'Invalid file type. Please upload a valid CSV file.', $result['errors'][0]['reason'] );
		} finally {
			unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * A valid CSV passes the upload probe and the type gate, reaches CsvHandler
	 * and imports its rows.
	 */
	public function test_import_valid_csv_reaches_handler_and_imports_rows(): void {
		$tmp = (string) tempnam( sys_get_temp_dir(), 'rkcsv' );
		file_put_contents( $tmp, "source,target,code,match_type,active,hits,last_accessed\n/old-one,/new-one,,,,\n/old-two,/new-two,302,prefix,no,,\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		Functions\when( 'wp_check_filetype' )->alias(
			static fn (): array => [
				'ext'  => 'csv',
				'type' => 'text/csv',
			]
		);

		try {
			$page = new RedirectsPage(
				new RedirectRepository( $this->db ),
				new RedirectsSettings(),
				new Validator(),
				new DestinationValidator(),
				static fn ( string $p ): bool => true // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub callback signature.
			);

			$this->allowAccess();

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = [
				'rankkernel_redirect_import' => '1',
				'_wpnonce'                   => 'valid',
			];
			$_FILES                    = [
				'rk_csv_file' => [
					'name'     => 'rules.csv',
					'type'     => 'text/csv',
					'tmp_name' => $tmp,
					'error'    => UPLOAD_ERR_OK,
					'size'     => 100,
				],
			];

			ob_start();
			$page->maybeHandleSave();
			ob_end_clean();

			$result = $page->import_result();
			$this->assertIsArray( $result );
			$this->assertSame( 2, $result['created'] );
			$this->assertSame( [], $result['errors'] );

			$rows = array_values( $this->db->rows );

			$this->assertCount( 2, $rows );
			$this->assertSame( '/old-one', $rows[0]['source'] );
			$this->assertSame( '/new-one', $rows[0]['target'] );
			$this->assertSame( '/old-two', $rows[1]['source'] );
		} finally {
			unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Failed nonce stops the save.
	 */
	public function test_form_save_with_bad_nonce_dies(): void {
		$page = $this->makePage();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);

		$this->postAdd();

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
	 * Valid input saves and redirects with a saved flag.
	 */
	public function test_add_valid_redirect_saves_and_redirects(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd();

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 1, $this->db->rows );

		$row = reset( $this->db->rows );

		$this->assertSame( '/a', (string) $row['source'] );
		$this->assertSame( '/b', (string) $row['target'] );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );
		$this->assertStringNotContainsString( 'rk_chain=', $this->lastRedirect );
	}

	/**
	 * A loop blocks the save and shows the cycle path.
	 */
	public function test_add_loop_blocks_save_and_shows_path(): void {
		$this->seedRule( '/b', '/a' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/a',
				'rk_target' => '/b',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'redirect loop', $html );
		$this->assertStringContainsString( '/a → /b → /a', $html );
	}

	/**
	 * A chain saves with a warning plus the recommended destination.
	 */
	public function test_add_chain_saves_with_warning(): void {
		$this->seedRule( '/b', '/c' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/a',
				'rk_target' => '/b',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 2, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_chain=', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_final=', $this->lastRedirect );

		$_GET = [
			'rk_notice' => 'saved',
			'rk_chain'  => '/a → /b → /c',
			'rk_final'  => '/c',
		];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Redirect chain detected', $html );
		$this->assertStringContainsString( '/a → /b → /c', $html );
		$this->assertStringContainsString( '/c', $html );
	}

	/**
	 * A chain through a pattern rule saves with an information notice.
	 */
	public function test_add_pattern_chain_saves_with_info(): void {
		$this->seedRule( '/shop', '/sale', '301', 'prefix' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/a',
				'rk_target' => '/shop/item',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 2, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_chain_unknown=1', $this->lastRedirect );
	}

	/**
	 * A possible loop through a pattern rule saves with a warning.
	 */
	public function test_add_possible_pattern_loop_saves_with_warning(): void {
		$this->seedRule( '/old-.*', '/new', '301', 'regex' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/a',
				'rk_target' => '/old-123',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 2, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_mayloop=1', $this->lastRedirect );
	}

	/**
	 * Duplicate source plus match type stays on the page with an error.
	 */
	public function test_add_duplicate_source_shows_error(): void {
		$this->seedRule( '/a', '/b' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/a',
				'rk_target' => '/c',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'already exists', $html );
	}

	/**
	 * The home page source is rejected.
	 */
	public function test_add_home_source_shows_error(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd( [ 'rk_source' => '/' ] );

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 0, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'home page', $html );
	}

	/**
	 * Unsafe destination schemes are rejected.
	 */
	public function test_add_unsafe_destination_shows_error(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd( [ 'rk_target' => 'javascript:alert(1)' ] );

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 0, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'not valid', $html );
	}

	/**
	 * Edit updates the row and redirects with an updated flag.
	 */
	public function test_edit_updates_rule_and_redirects(): void {
		$id = $this->seedRule( '/a', '/b' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rule_id'   => (string) $id,
				'rk_source' => '/a',
				'rk_target' => '/c',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( '/c', (string) $this->db->rows[ $id ]['target'] );
		$this->assertStringContainsString( 'rk_notice=updated', $this->lastRedirect );
	}

	/**
	 * Editing a missing row stays on the page with an error.
	 */
	public function test_edit_missing_row_shows_error(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rule_id'   => '999',
				'rk_source' => '/a',
				'rk_target' => '/c',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'no longer exists', $html );
	}

	/**
	 * Row delete removes the rule and redirects.
	 */
	public function test_row_delete_removes_rule(): void {
		$id = $this->seedRule( '/a', '/b' );

		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET                      = [
			'rk_action' => 'delete',
			'rule'      => (string) $id,
			'_wpnonce'  => 'valid',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertArrayNotHasKey( $id, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=deleted', $this->lastRedirect );
	}

	/**
	 * Row activate and deactivate flip the flag.
	 */
	public function test_row_activate_and_deactivate_flip_flag(): void {
		$id = $this->seedRule( '/a', '/b', '301', 'exact', false );

		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET                      = [
			'rk_action' => 'activate',
			'rule'      => (string) $id,
			'_wpnonce'  => 'valid',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( 1, (int) $this->db->rows[ $id ]['is_active'] );
		$this->assertStringContainsString( 'rk_notice=activated', $this->lastRedirect );

		$_GET = [
			'rk_action' => 'deactivate',
			'rule'      => (string) $id,
			'_wpnonce'  => 'valid',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( 0, (int) $this->db->rows[ $id ]['is_active'] );
		$this->assertStringContainsString( 'rk_notice=deactivated', $this->lastRedirect );
	}

	/**
	 * Row actions verify the nonce.
	 */
	public function test_row_action_with_bad_nonce_dies(): void {
		$this->seedRule( '/a', '/b' );

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
			'rule'      => '1',
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
	 * Bulk delete removes the chosen rows.
	 */
	public function test_bulk_delete_removes_chosen_rows(): void {
		$this->seedRule( '/a', '/b' );
		$this->seedRule( '/c', '/d' );
		$this->seedRule( '/e', '/f' );

		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_redirect_bulk' => '1',
			'_wpnonce'                 => 'valid',
			'rk_bulk_action'           => 'delete',
			'rule_ids'                 => [ '1', '2', 'nope', '0' ],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 1, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=bulk', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_bulk=delete', $this->lastRedirect );
	}

	/**
	 * Bulk activate flips the chosen rows.
	 */
	public function test_bulk_activate_flips_chosen_rows(): void {
		$this->seedRule( '/a', '/b', '301', 'exact', false );
		$this->seedRule( '/c', '/d', '301', 'exact', false );

		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_redirect_bulk' => '1',
			'_wpnonce'                 => 'valid',
			'rk_bulk_action'           => 'activate',
			'rule_ids'                 => [ '1', '2' ],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( 1, (int) $this->db->rows[1]['is_active'] );
		$this->assertSame( 1, (int) $this->db->rows[2]['is_active'] );
		$this->assertStringContainsString( 'rk_bulk=activate', $this->lastRedirect );
	}

	/**
	 * Bulk without rows or action reports an error flag.
	 */
	public function test_bulk_without_selection_reports_error(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_redirect_bulk' => '1',
			'_wpnonce'                 => 'valid',
			'rk_bulk_action'           => 'delete',
			'rule_ids'                 => [],
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertStringContainsString( 'rk_error=bulk_none', $this->lastRedirect );
	}

	/**
	 * Settings save stores sanitized values.
	 */
	public function test_settings_save_stores_sanitized_values(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'rankkernel_redirect_settings_save' => '1',
			'_wpnonce'                          => 'valid',
			'rk_preserve_query'                 => '1',
			'rk_rules_per_page'                 => '500',
		];

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$stored = $this->options[ RedirectsSettings::OPTION ];

		$this->assertTrue( $stored['preserve_query'] );
		$this->assertFalse( $stored['auto_slug_redirect'] );
		$this->assertSame( 100, $stored['rules_per_page'] );
		$this->assertStringContainsString( 'rk_notice=settings', $this->lastRedirect );
		$this->assertArrayHasKey(
			RedirectCache::VALIDATOR_OPTION,
			$this->options,
			'A settings save must invalidate the redirect match cache'
		);
	}

	/**
	 * Assets load only on the Redirects screen hook.
	 */
	public function test_enqueue_gates_assets_to_screen_hook(): void {
		Functions\when( 'plugins_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/p/' . $path );

		$registeredStyles  = [];
		$registeredScripts = [];
		$enqueuedStyles    = [];
		$enqueuedScripts   = [];

		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps = [], string $ver = '' ) use ( &$registeredStyles ): void {
				$registeredStyles[ $handle ] = $ver . '|' . $src;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle ) use ( &$enqueuedStyles ): void {
				$enqueuedStyles[] = $handle;
			}
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

		$page = $this->makePage();
		$page->enqueueAssets( 'toplevel_page_rankkernel' );

		$this->assertSame( [], $registeredStyles );
		$this->assertSame( [], $enqueuedStyles );
		$this->assertSame( [], $registeredScripts );
		$this->assertSame( [], $enqueuedScripts );

		$page->enqueueAssets( RedirectsPage::HOOK_SUFFIX );

		$this->assertArrayHasKey( 'rankkernel-redirects-admin', $registeredStyles );
		$this->assertContains( 'rankkernel-redirects-admin', $enqueuedStyles );
		$this->assertStringContainsString( 'redirects-admin.css', (string) $registeredStyles['rankkernel-redirects-admin'] );

		$this->assertArrayHasKey( 'rankkernel-redirects-admin', $registeredScripts );
		$this->assertContains( 'rankkernel-redirects-admin', $enqueuedScripts );
		$this->assertSame( [ 'wp-a11y', 'wp-i18n' ], $registeredScripts['rankkernel-redirects-admin']['deps'] );
		$this->assertStringContainsString( 'redirects-admin.js', (string) $registeredScripts['rankkernel-redirects-admin']['src'] );
	}

	/**
	 * Empty screens guide the admin toward the first redirect.
	 */
	public function test_render_empty_state_invites_first_redirect(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'No redirects yet', $html );
		$this->assertStringContainsString( 'Add your first redirect', $html );
	}

	/**
	 * The list shows rows plus search plus filters.
	 */
	public function test_render_list_shows_rows_and_controls(): void {
		$this->seedRule( '/a', '/b' );
		$this->seedRule( '/c', '/d', '302', 'exact', false );

		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( '/a', $html );
		$this->assertStringContainsString( '/b', $html );
		$this->assertStringContainsString( 'Search redirects', $html );
		$this->assertStringContainsString( 'Bulk actions', $html );
		$this->assertStringContainsString( 'Active', $html );
		$this->assertStringContainsString( 'Inactive', $html );
	}

	/**
	 * Search with no matches shows the filtered empty state.
	 */
	public function test_render_search_without_matches_shows_filtered_empty_state(): void {
		$this->seedRule( '/a', '/b' );

		$page = $this->makePage();
		$this->allowAccess();

		$_GET = [ 's' => 'zzz-no-match' ];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'No redirects match your search', $html );
	}

	/**
	 * The form explains every field inline.
	 */
	public function test_render_form_explains_fields(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Source URL', $html );
		$this->assertStringContainsString( 'Destination URL', $html );
		$this->assertStringContainsString( 'What do the match types mean', $html );
		$this->assertStringContainsString( 'Which redirect type should I use', $html );
	}

	/**
	 * The edit form prefills the stored row.
	 */
	public function test_render_edit_form_prefills_row(): void {
		$id = $this->seedRule( '/a', '/b' );

		$page = $this->makePage();
		$this->allowAccess();

		$_GET = [ 'rk_edit' => (string) $id ];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Edit Redirect', $html );
		$this->assertStringContainsString( 'value="/a"', $html );
		$this->assertStringContainsString( 'value="/b"', $html );
	}

	/**
	 * Missing capability stops a row action with 403.
	 */
	public function test_row_action_without_capability_dies(): void {
		$this->seedRule( '/a', '/b' );

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
			'rule'      => '1',
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
	 * Missing capability stops a bulk action with 403.
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
			'rankkernel_redirect_bulk' => '1',
			'_wpnonce'                 => 'valid',
			'rk_bulk_action'           => 'delete',
			'rule_ids'                 => [ '1' ],
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
	 * Failed nonce stops a bulk action.
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
			'rankkernel_redirect_bulk' => '1',
			'_wpnonce'                 => 'bad',
			'rk_bulk_action'           => 'delete',
			'rule_ids'                 => [ '1' ],
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
			'rankkernel_redirect_settings_save' => '1',
			'_wpnonce'                          => 'bad',
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
	 * Failed nonce stops a CSV import.
	 */
	public function test_import_with_bad_nonce_dies(): void {
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
			'rankkernel_redirect_import' => '1',
			'_wpnonce'                   => 'bad',
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
	 * Failed nonce stops a CSV export.
	 */
	public function test_export_with_bad_nonce_dies(): void {
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
			'rk_action' => 'export',
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
	 * Loop plus chain inconclusive saves with both flags, never a clean pass.
	 */
	public function test_inconclusive_loop_and_chain_saves_with_both_flags(): void {
		$this->seedRule( '/old-.*', '/new', '301', 'regex' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/a',
				'rk_target' => '/old-123',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 2, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_mayloop=1', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_chain_unknown=1', $this->lastRedirect );

		$_GET = [
			'rk_notice'        => 'saved',
			'rk_mayloop'       => '1',
			'rk_chain_unknown' => '1',
		];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'could not fully verify', $html );
	}

	/**
	 * A save past the pattern cap stays on the page with a clear message.
	 */
	public function test_add_pattern_past_cap_shows_limit_message(): void {
		for ( $i = 1; $i <= 500; $i++ ) {
			$this->db->rows[ $i ] = [
				'id'            => $i,
				'match_type'    => 'prefix',
				'source_hash'   => hash( 'sha256', 'prefix|/cap-' . (string) $i ),
				'source'        => '/cap-' . (string) $i,
				'target'        => '/x',
				'code'          => '301',
				'is_active'     => 1,
				'created'       => '2026-01-01 00:00:00',
				'hits'          => 0,
				'last_accessed' => null,
			];
		}

		$this->db->nextId = 501;

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source'     => '/one-more',
				'rk_match_type' => 'prefix',
				'rk_target'     => '/x',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 500, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect, 'A capped save must not redirect away' );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'pattern rule limit', $html );
	}

	/**
	 * The editor stays closed by default so the list leads.
	 */
	public function test_editor_closed_by_default(): void {
		$page = $this->makePage();
		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertMatchesRegularExpression( '/<div\b[^>]*id="rk-redirect-editor"[^>]*\shidden\b/', $html );
	}

	/**
	 * The toggle target opens the editor server side.
	 */
	public function test_editor_opens_with_rk_open(): void {
		$page = $this->makePage();

		$_GET = [ 'rk_open' => '1' ];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'aria-expanded="true"', $html );
		$this->assertStringNotContainsString( 'id="rk-redirect-editor" hidden', $html );
		$this->assertStringContainsString( 'Add Redirect', $html );
	}

	/**
	 * Edit reuses the same editor with update wording plus cancel.
	 */
	public function test_edit_mode_reuses_editor_with_cancel(): void {
		$id = $this->seedRule( '/a', '/b' );

		$page = $this->makePage();

		$_GET = [ 'rk_edit' => (string) $id ];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Edit Redirect', $html );
		$this->assertStringContainsString( 'rk-editor-heading', $html );
		$this->assertStringContainsString( 'Cancel', $html );
		$this->assertStringContainsString( 'aria-expanded="true"', $html );
		$this->assertStringNotContainsString( 'id="rk-redirect-editor" hidden', $html );
	}

	/**
	 * Terminal codes hide and disable the destination field.
	 */
	public function test_terminal_code_hides_destination(): void {
		$id = $this->seedRule( '/gone', '', '410' );

		$page = $this->makePage();

		$_GET = [ 'rk_edit' => (string) $id ];

		$html = $this->renderPage( $page );

		$this->assertMatchesRegularExpression( '/<div\b[^>]*id="rk-target-row"[^>]*\shidden\b/', $html );
		$this->assertStringContainsString( 'disabled', $html );
		$this->assertStringContainsString( 'no destination is needed', $html );
	}

	/**
	 * A 404 prefill opens the editor with source plus return target.
	 */
	public function test_prefill_from_404_opens_editor_with_return(): void {
		$page = $this->makePage();

		$_GET = [
			'rk_source' => '/missing',
			'rk_return' => 'rankkernel-404',
		];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'aria-expanded="true"', $html );
		$this->assertStringContainsString( 'value="/missing"', $html );
		$this->assertStringContainsString( 'name="rk_return" value="rankkernel-404"', $html );
		$this->assertStringContainsString( 'Source prefilled', $html );
	}

	/**
	 * A save from the monitor flow routes back to the monitor.
	 */
	public function test_save_with_return_routes_to_monitor(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd( [ 'rk_return' => 'rankkernel-404' ] );

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertStringContainsString( 'rankkernel-404', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );
	}

	/**
	 * An unknown return target never routes the save elsewhere.
	 */
	public function test_save_with_bad_return_stays_on_redirects(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd( [ 'rk_return' => 'http://evil.example/' ] );

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertStringContainsString( 'rankkernel-redirects', $this->lastRedirect );
		$this->assertStringNotContainsString( 'rankkernel-404', $this->lastRedirect );
	}

	/**
	 * A source fragment is rejected with an explanation.
	 */
	public function test_add_fragment_source_shows_error(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd( [ 'rk_source' => '/a#section' ] );

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 0, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'never sent to the server', $html );
	}

	/**
	 * An overlong regex pattern is rejected with the length cap.
	 */
	public function test_add_long_regex_shows_length_error(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source'     => str_repeat( 'a', 201 ),
				'rk_match_type' => 'regex',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 0, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'under 200 characters', $html );
	}

	/**
	 * A blocked destination scheme names the problem.
	 */
	public function test_add_blocked_scheme_names_problem(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd( [ 'rk_target' => 'javascript:alert(1)' ] );

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 0, $this->db->rows );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'blocked scheme', $html );
	}

	/**
	 * A deterministic chain recommends the direct destination.
	 */
	public function test_chain_with_final_recommends_direct_destination(): void {
		$page = $this->makePage();

		$_GET = [
			'rk_chain' => '/a -> /b',
			'rk_final' => '/c',
		];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Redirect chain detected', $html );
		$this->assertStringContainsString( 'Consider pointing', $html );
	}

	/**
	 * An inconclusive chain never presents a recommendation as safe.
	 */
	public function test_chain_unknown_hides_recommendation(): void {
		$page = $this->makePage();

		$_GET = [
			'rk_chain'         => '/a -> /b',
			'rk_final'         => '/c',
			'rk_chain_unknown' => '1',
		];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Redirect chain detected', $html );
		$this->assertStringContainsString( 'could not determine the final destination', $html );
		$this->assertStringNotContainsString( 'Consider pointing', $html );
	}

	/**
	 * Table controls carry accessible screen-reader and ARIA labels.
	 */
	public function test_table_controls_have_accessible_labels(): void {
		$this->seedRule( '/a', '/b' );
		$this->seedRule( '/c', '/d' );

		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( '<label for="rk-bulk-action"', $html );
		$this->assertStringContainsString( 'id="rk-bulk-action"', $html );
		$this->assertStringContainsString( '<label for="rk-select-all"', $html );
		$this->assertStringContainsString( 'id="rk-select-all"', $html );
		$this->assertStringContainsString( '<label for="rk-search-input"', $html );
		$this->assertStringContainsString( 'id="rk-search-input"', $html );
		// The status filter is a labeled tab nav now, one tab per status view.
		$this->assertStringContainsString( 'aria-label="Filter redirects by status"', $html );
		$this->assertSame( 3, preg_match_all( '/class="rk-tab(?:\s|")/', $html ), 'One status tab per All, Active and Inactive view' );
		$this->assertStringContainsString( '<label for="rk-filter-match"', $html );
		$this->assertStringContainsString( 'id="rk-filter-match"', $html );
		$this->assertStringContainsString( '<label for="rk-filter-code"', $html );
		$this->assertStringContainsString( 'id="rk-filter-code"', $html );

		$this->assertStringContainsString( 'Select bulk action', $html );
		$this->assertStringContainsString( 'Select All', $html );
		$this->assertStringContainsString( 'Search redirects', $html );
		$this->assertStringContainsString( '>All <span class="count">', $html );
		$this->assertStringContainsString( '>Active <span class="count">', $html );
		$this->assertStringContainsString( '>Inactive <span class="count">', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ), 'Exactly one status tab may be marked as current' );
		$this->assertStringContainsString( 'Filter by match type', $html );
		$this->assertStringContainsString( 'Filter by redirect type', $html );

		$this->assertStringContainsString( 'aria-label="Select redirect for /a"', $html );
		$this->assertStringContainsString( 'aria-label="Select redirect for /c"', $html );
		$this->assertSame( 2, substr_count( $html, 'aria-label="Select redirect for' ) );
		$this->assertStringContainsString( 'aria-label="Edit redirect for /a"', $html );
		$this->assertStringContainsString( 'aria-label="Deactivate redirect for /a"', $html );
		$this->assertStringContainsString( 'aria-label="Delete redirect for /a"', $html );
	}

	/**
	 * The header pill, action labels, table columns, pagination copy, empty
	 * state icons, editor counters, and icon font match the design system.
	 */
	public function test_render_matches_design_components_and_copy(): void {
		for ( $i = 1; $i <= 25; $i++ ) {
			$this->seedRule( '/old-' . (string) $i, '/new-' . (string) $i );
		}

		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'rk-count-pill', $html );
		$this->assertStringContainsString( '25 Total Rules', $html );
		$this->assertStringContainsString( 'Export CSV', $html );
		$this->assertStringNotContainsString( 'Import / Export', $html );
		$this->assertStringContainsString( '>Source<', $html );
		$this->assertStringContainsString( '>Destination<', $html );
		$this->assertStringContainsString( '>Actions<', $html );
		$this->assertStringContainsString( 'rk-col-actions', $html );
		$this->assertStringContainsString( 'Showing <strong>1 to 20</strong> of <strong>25</strong> redirects', $html );
		$this->assertStringContainsString( '25 redirects', $html );
		$this->assertStringContainsString( 'Page 1 of 2', $html );
		$this->assertStringContainsString( 'Rows per page:', $html );
		$this->assertStringContainsString( 'rk-page-num', $html );
		$this->assertStringContainsString( 'rk-selected-chip', $html );
		$this->assertStringContainsString( 'rk-source-count', $html );
		$this->assertStringContainsString( 'rk-target-count', $html );
		$this->assertStringContainsString( '0 chars', $html );
		$this->assertStringContainsString( 'Advanced options', $html );
		$this->assertStringContainsString( 'rk-advanced-arrow', $html );
		$this->assertStringContainsString( '<span class="rk-icon rk-search-icon" aria-hidden="true">search</span>', $html );
		$this->assertStringNotContainsString( '&#10007;', $html );
		$this->assertStringNotContainsString( '&#10003;', $html );
		$this->assertStringNotContainsString( '&#9881;', $html );
	}

	/**
	 * The CSV upload hint states the same cap the server enforces.
	 *
	 * The hint is derived from CsvHandler::MAX_FILE_SIZE, so it can never
	 * invite a file the importer then rejects.
	 */
	public function test_render_csv_upload_hint_matches_server_size_cap(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->renderPage( $page );

		$this->assertStringContainsString(
			'.csv files only (up to ' . size_format( CsvHandler::MAX_FILE_SIZE ) . ')',
			$html
		);
		$this->assertStringNotContainsString( 'up to 5 MB', $html );
	}

	/**
	 * The rows per page override narrows the list without touching settings.
	 */
	public function test_render_per_page_override_narrows_list(): void {
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->seedRule( '/old-' . (string) $i, '/new-' . (string) $i );
		}

		$page = $this->makePage();
		$this->allowAccess();

		$_GET = [ 'rk_per_page' => '10' ];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Showing <strong>1 to 10</strong> of <strong>12</strong> redirects', $html );
		$this->assertStringContainsString( 'Page 1 of 2', $html );
		$this->assertArrayNotHasKey( RedirectsSettings::OPTION, $this->options, 'The display override must not write the stored setting' );
	}

	/**
	 * The empty states use the design icons, one variant per situation.
	 */
	public function test_render_empty_states_use_design_icons(): void {
		$page = $this->makePage();
		$this->allowAccess();

		$emptyHtml = $this->renderPage( $page );

		$this->assertStringContainsString( '<span class="rk-icon" aria-hidden="true">alt_route</span>', $emptyHtml );
		$this->assertStringContainsString( '<span class="rk-icon" aria-hidden="true">add</span>', $emptyHtml );
		$this->assertStringNotContainsString( 'search_off', $emptyHtml );

		$this->seedRule( '/a', '/b' );

		$_GET = [ 's' => 'zzz-no-match' ];

		$filteredHtml = $this->renderPage( $page );

		$this->assertStringContainsString( '<span class="rk-icon" aria-hidden="true">search_off</span>', $filteredHtml );
		$this->assertStringContainsString( '<span class="rk-icon" aria-hidden="true">filter_alt_off</span>', $filteredHtml );
		$this->assertStringNotContainsString( 'alt_route', $filteredHtml );
	}

	/**
	 * The AJAX refresh applies the posted status filter, the reported bug.
	 */
	public function test_ajax_list_applies_posted_status_filter(): void {
		$this->seedRule( '/active-old', '/active-new' );
		$this->seedRule( '/inactive-old', '/inactive-new', '301', 'exact', false );

		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->ajaxList( $page, [ 'rk_status' => 'inactive' ] );

		$this->assertStringContainsString( 'Showing <strong>1 to 1</strong> of <strong>1</strong> redirects', $html );
		$this->assertStringContainsString( '/inactive-old', $html );
		$this->assertStringNotContainsString( '/active-old', $html );
	}

	/**
	 * The AJAX refresh applies the posted rows per page value.
	 */
	public function test_ajax_list_applies_posted_per_page_value(): void {
		$this->seedRule( '/a', '/b' );
		$this->seedRule( '/c', '/d' );
		$this->seedRule( '/e', '/f' );

		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->ajaxList( $page, [ 'rk_per_page' => '1' ] );

		$this->assertStringContainsString( 'Showing <strong>1 to 1</strong> of <strong>3</strong> redirects', $html );
		$this->assertStringContainsString( 'Page 1 of 3', $html );
	}

	/**
	 * The status tabs count under the other filters, so an active status
	 * filter leaves the All tab at the combined active plus inactive total
	 * while pagination keeps reporting the filtered total.
	 */
	public function test_ajax_list_status_filter_keeps_all_tab_total_across_statuses(): void {
		$this->seedRule( '/active-one', '/active-one-new' );
		$this->seedRule( '/active-two', '/active-two-new' );
		$this->seedRule( '/inactive-one', '/inactive-one-new', '301', 'exact', false );

		$page = $this->makePage();
		$this->allowAccess();

		$html = $this->ajaxList( $page, [ 'rk_status' => 'inactive' ] );

		// The All tab excludes the status filter itself, so it must not shrink
		// to the filtered status total.
		$this->assertStringContainsString( '>All <span class="count">3</span>', $html );
		$this->assertStringContainsString( '>Active <span class="count">2</span>', $html );
		$this->assertStringContainsString( '>Inactive <span class="count">1</span>', $html );

		// Pagination and the item count keep reflecting the filtered total.
		$this->assertStringContainsString( 'Showing <strong>1 to 1</strong> of <strong>1</strong> redirects', $html );
		$this->assertStringContainsString( '<span class="rk-items-count">1 redirects</span>', $html );

		$this->assertStringContainsString( '/inactive-one', $html );
		$this->assertStringNotContainsString( '/active-one', $html );
	}
}
