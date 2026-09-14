<?php
/**
 * Redirect safety precedence regression tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\NotFoundPage;
use RankKernel\Admin\RedirectsPage;
use RankKernel\Modules\Redirects\CsvHandler;
use RankKernel\Modules\Redirects\DestinationValidator;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Redirects\RedirectsSettings;
use RankKernel\Modules\Redirects\SlugWatcher;
use RankKernel\Modules\Redirects\Validator;

/**
 * Pins the shared safety order across every creation path.
 *
 * Precedence under test: invalid input stays a hard error, definite
 * equivalent self redirect blocks, definite cycle blocks, known chain
 * saves with a warning plus the recommended final destination, and
 * inconclusive analysis warns while still saving, except the slug
 * watcher which fails closed. Regex sources stay verbatim throughout.
 */
final class RedirectsSafetyPrecedenceTest extends TestCase {
	/**
	 * In memory redirect table.
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option storage backing get option and update option.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Last redirect URL captured from wp safe redirect.
	 */
	private string $lastRedirect = '';

	/**
	 * Temp files to remove in tearDown.
	 *
	 * @var string[]
	 */
	private array $tempFiles = [];

	/**
	 * Set up doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->db           = new RedirectsFakeDb();
		$this->options      = [];
		$this->lastRedirect = '';
		$this->tempFiles    = [];

		// Test installs the in memory wpdb double, restored in tearDown.
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

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
		Functions\when( 'home_url' )->alias( static fn ( string $p = '/' ): string => 'https://example.com' . $p );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params = [], string $url = '' ): string {
				if ( [] === $params ) {
					return $url;
				}

				$sep = str_contains( $url, '?' ) ? '&' : '?';

				return $url . $sep . http_build_query( $params );
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
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( 'wp_nonce_url' )->alias(
			static function ( string $url ): string {
				return $url . '&_wpnonce=valid';
			}
		);
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'submit_button' )->justReturn( '' );
		Functions\when( 'checked' )->alias(
			static function ( mixed $first, mixed $second ): string {
				if ( (string) $first === (string) $second && '' !== (string) $first ) {
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
		Functions\when( 'get_permalink' )->alias(
			static function ( mixed $post ): string {
				$arr  = is_object( $post ) ? (array) $post : [];
				$slug = (string) ( $arr['post_name'] ?? '' );

				return 'https://example.com/' . $slug . '/';
			}
		);
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'add_action' )->justReturn( true );
	}

	/**
	 * Reset globals and remove temp files.
	 */
	protected function tearDown(): void {
		foreach ( $this->tempFiles as $path ) {
			if ( is_file( $path ) ) {
				// Temp CSV files created by this test, removed during tearDown.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $path );
			}
		}

		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();

		$_POST                     = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	/**
	 * Build the admin page over the fake database.
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
	 * Build the watcher over the fake database.
	 */
	private function makeWatcher(): SlugWatcher {
		return new SlugWatcher( new RedirectRepository( $this->db ), new RedirectsSettings() );
	}

	/**
	 * Build a post object double.
	 *
	 * @return object Post shaped object.
	 */
	private function makePost( string $slug ): object {
		return (object) [
			'ID'        => 5,
			'post_type' => 'post',
			'post_name' => $slug,
		];
	}

	/**
	 * Seed one rule in the fake table.
	 *
	 * @return int New row id.
	 */
	private function seedRule( string $source, string $target, string $code = '301', string $matchType = 'exact' ): int {
		$repo = new RedirectRepository( $this->db );

		return $repo->insert(
			[
				'source'     => $source,
				'target'     => $target,
				'code'       => $code,
				'match_type' => $matchType,
				'is_active'  => true,
			]
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
	 */
	private function renderPage( RedirectsPage $page ): string {
		ob_start();
		$page->render();
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Write CSV content to a temp file.
	 */
	private function write_csv( string $content ): string {
		$path = tempnam( sys_get_temp_dir(), 'rksafe' );

		$this->assertIsString( $path );
		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test helper writing its own temp CSV fixture.

		$this->tempFiles[] = $path;

		return $path;
	}

	/**
	 * Trailing slash variants of the same path are equivalent.
	 */
	public function test_equivalent_blog_to_blog_slash_blocked(): void {
		$validator = new Validator();

		$this->assertTrue(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/blog',
					'target'     => '/blog/',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
	}

	/**
	 * Leading slash variants of the same path are equivalent in reverse.
	 */
	public function test_equivalent_blog_slash_to_blog_blocked(): void {
		$validator = new Validator();

		$this->assertTrue(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/blog/',
					'target'     => '/blog',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
	}

	/**
	 * Identical paths with and without trailing slash are equivalent.
	 */
	public function test_equivalent_about_paths_blocked(): void {
		$validator = new Validator();

		$this->assertTrue(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/about',
					'target'     => '/about/',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
		$this->assertTrue(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/about/',
					'target'     => '/about',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
	}

	/**
	 * Query strings never separate an equivalent path pair.
	 */
	public function test_equivalent_ignores_query_and_fragment(): void {
		$validator = new Validator();

		$this->assertTrue(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/blog?x=1',
					'target'     => '/blog/?x=1',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
		$this->assertFalse(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/blog?x=1',
					'target'     => '/other?x=1',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
	}

	/**
	 * Absolute internal URLs fold to the same path as relative ones.
	 */
	public function test_equivalent_absolute_internal_url_matches_relative(): void {
		$validator = new Validator();

		$this->assertTrue(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/blog',
					'target'     => 'https://example.com/blog/',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
		$this->assertTrue(
			$validator->is_equivalent_redirect(
				[
					'source'     => '//blog//',
					'target'     => '/blog',
					'code'       => '302',
					'match_type' => 'exact',
				]
			)
		);
	}

	/**
	 * Regex bodies never count as equivalent and stay verbatim.
	 */
	public function test_regex_source_never_equivalent(): void {
		$validator = new Validator();

		$this->assertFalse(
			$validator->is_equivalent_redirect(
				[
					'source'     => '^/old/(.*)$',
					'target'     => '/new/$1',
					'code'       => '301',
					'match_type' => 'regex',
				]
			)
		);
		$this->assertFalse(
			$validator->is_equivalent_redirect(
				[
					'source'     => '^/blog/?$',
					'target'     => '/blog/',
					'code'       => '301',
					'match_type' => 'regex',
				]
			)
		);
	}

	/**
	 * Terminal codes and external targets never count as equivalent.
	 */
	public function test_terminal_and_external_never_equivalent(): void {
		$validator = new Validator();

		$this->assertFalse(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/blog',
					'target'     => '/blog/',
					'code'       => '410',
					'match_type' => 'exact',
				]
			)
		);
		$this->assertFalse(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/blog',
					'target'     => 'https://external.example/blog/',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
		$this->assertFalse(
			$validator->is_equivalent_redirect(
				[
					'source'     => '/a',
					'target'     => '/b',
					'code'       => '301',
					'match_type' => 'exact',
				]
			)
		);
	}

	/**
	 * Equivalence outranks cycle detection in the shared precedence.
	 */
	public function test_assess_safety_reports_equivalent_before_cycle(): void {
		$validator = new Validator();
		$rules     = [
			[
				'id'         => 1,
				'match_type' => 'exact',
				'source'     => '/blog',
				'target'     => '/x',
				'code'       => '301',
				'is_active'  => 1,
			],
			[
				'id'         => 2,
				'match_type' => 'exact',
				'source'     => '/x',
				'target'     => '/blog',
				'code'       => '301',
				'is_active'  => 1,
			],
		];

		$result = $validator->assess_safety(
			[
				'source'     => '/blog',
				'target'     => '/blog/',
				'code'       => '301',
				'match_type' => 'exact',
			],
			$rules
		);

		$this->assertSame( 'equivalent', $result['verdict'] );
	}

	/**
	 * The admin save blocks a self redirect with a field level error.
	 */
	public function test_admin_save_blocks_equivalent_with_field_error(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/blog',
				'rk_target' => '/blog/',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( [], $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'resolve to the same URL', $html );
		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertStringContainsString( 'rk-target-error', $html );
	}

	/**
	 * The admin edit path enforces the same equivalence block.
	 */
	public function test_admin_edit_blocks_equivalent(): void {
		$id = $this->seedRule( '/a', '/b' );

		$this->assertGreaterThan( 0, $id );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rule_id'   => $id,
				'rk_source' => '/a',
				'rk_target' => '/a/',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( '', $this->lastRedirect );
		$this->assertSame( '/b', $this->db->rows[ $id ]['target'] );
	}

	/**
	 * A three rule cycle blocks the admin save.
	 */
	public function test_admin_save_blocks_three_rule_cycle(): void {
		$this->seedRule( '/a', '/b' );
		$this->seedRule( '/b', '/c' );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/c',
				'rk_target' => '/a',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 2, $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'redirect loop', $html );
	}

	/**
	 * A two hop chain saves with the final destination recommended.
	 */
	public function test_admin_save_chain_recommends_final_destination(): void {
		$this->seedRule( '/b', '/c' );
		$this->seedRule( '/c', '/d' );

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

		$this->assertCount( 3, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_chain=', $this->lastRedirect );
		$this->assertStringContainsString( 'rk_final=', $this->lastRedirect );

		$_GET = [
			'rk_notice' => 'saved',
			'rk_chain'  => '/a → /b → /c → /d',
			'rk_final'  => '/d',
		];

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'Redirect chain detected', $html );
		$this->assertStringContainsString( '/d', $html );
		$this->assertStringContainsString( 'Use recommended destination', $html );
		$this->assertStringContainsString( 'data-rk-use-destination', $html );
	}

	/**
	 * An inconclusive chain saves with a warning and no guessed target.
	 */
	public function test_admin_save_inconclusive_chain_warns_and_saves(): void {
		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source'     => '^/old/(.*)$',
				'rk_match_type' => 'regex',
				'rk_target'     => '/new/$1',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertCount( 1, $this->db->rows );
		$this->assertStringContainsString( 'rk_notice=saved', $this->lastRedirect );

		$row = reset( $this->db->rows );

		$this->assertSame( '^/old/(.*)$', (string) $row['source'] );
	}

	/**
	 * CSV import classifies self, cycle, chain, inconclusive, and normal rows.
	 */
	public function test_csv_import_applies_shared_precedence(): void {
		$this->seedRule( '/b', '/a' );
		$this->seedRule( '/chained', '/final' );

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/blog,/blog/,301,exact,yes,,\n" .
			"/a,/b,301,exact,yes,,\n" .
			"/entry,/chained,301,exact,yes,,\n" .
			"/uncertain,/dynamic/\$1,301,exact,yes,,\n" .
			"/plain,/target,301,exact,yes,,\n"
		);

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );
		$summary = $handler->import_csv( $path );

		$this->assertSame( 3, $summary['created'] );
		$this->assertCount( 2, $summary['errors'] );
		$this->assertSame( 2, (int) $summary['errors'][0]['row'] );
		$this->assertStringContainsString( 'same URL', (string) $summary['errors'][0]['reason'] );
		$this->assertSame( 3, (int) $summary['errors'][1]['row'] );
		$this->assertStringContainsString( 'loop', (string) $summary['errors'][1]['reason'] );

		$warningsByRow = [];

		foreach ( $summary['warnings'] as $warning ) {
			if ( is_array( $warning ) ) {
				$warningsByRow[ (int) $warning['row'] ] = (string) $warning['message'];
			}
		}

		$this->assertArrayHasKey( 4, $warningsByRow );
		$this->assertStringContainsString( 'Redirect chain detected', $warningsByRow[4] );
		$this->assertStringContainsString( '/final', $warningsByRow[4] );
		$this->assertArrayHasKey( 5, $warningsByRow );
		$this->assertStringContainsString( 'verify', $warningsByRow[5] );
		$this->assertArrayNotHasKey( 6, $warningsByRow );
	}

	/**
	 * The slug watcher fails closed on an inconclusive loop analysis.
	 */
	public function test_slug_watcher_fails_closed_on_inconclusive(): void {
		$this->seedRule( '^/new-slug$', '/elsewhere', '301', 'regex' );

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertCount( 1, $this->db->rows );
	}

	/**
	 * The slug watcher fails closed on an inconclusive chain analysis.
	 */
	public function test_slug_watcher_fails_closed_on_chain_inconclusive(): void {
		$this->seedRule( '/new-slug', '/mid' );
		$this->seedRule( '^/mid$', '/final', '301', 'regex' );

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertCount( 2, $this->db->rows );
	}

	/**
	 * The 404 creation flow reuses the admin validation pipeline.
	 */
	public function test_create_from_404_uses_admin_validation(): void {
		$monitor = new NotFoundPage();
		$url     = $monitor->createRedirectUrl( '/missing' );

		$this->assertStringContainsString( 'rk_source=', $url );
		$this->assertStringContainsString( 'rk_return=', $url );

		$_GET = [
			'rk_source' => '/missing',
			'rk_return' => NotFoundPage::SLUG,
		];

		$html = $this->renderPage( $this->makePage() );

		$this->assertStringContainsString( '/missing', $html );

		$page = $this->makePage();
		$this->allowAccess();
		$this->postAdd(
			[
				'rk_source' => '/missing',
				'rk_target' => '/missing/',
			]
		);

		ob_start();
		$page->maybeHandleSave();
		ob_end_clean();

		$this->assertSame( [], $this->db->rows );
		$this->assertSame( '', $this->lastRedirect );

		$html = $this->renderPage( $page );

		$this->assertStringContainsString( 'resolve to the same URL', $html );
	}
}
