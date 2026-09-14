<?php
/**
 * Query string semantics tests over the live dispatcher.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\HitCounter;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\Redirector;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Redirects\RedirectsSettings;

/**
 * Pins every combination from the query behavior contract.
 *
 * Matching always ignores the query string. Preservation appends the
 * incoming query verbatim only when the setting is on, the destination
 * carries no query of its own, and the code is a redirect. Terminal codes
 * send no Location at all. Regex targets send literally, captures are
 * never substituted.
 */
final class RedirectsQuerySemanticsTest extends TestCase {
	/**
	 * Fake database.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Sent redirects.
	 *
	 * @var array<int, array{location: string, status: int}>
	 */
	private array $redirects = [];

	/**
	 * Status headers sent.
	 *
	 * @var int[]
	 */
	private array $statuses = [];

	/**
	 * Original request URI for restoration.
	 *
	 * @var string|null
	 */
	private ?string $originalUri = null;

	/**
	 * Is Admin.
	 *
	 * @var bool
	 */
	private bool $isAdmin = false;

	/**
	 * Is Ajax.
	 *
	 * @var bool
	 */
	private bool $isAjax = false;

	/**
	 * Is Cron.
	 *
	 * @var bool
	 */
	private bool $isCron = false;

	/**
	 * Sitemap Var.
	 *
	 * @var string
	 */
	private string $sitemapVar = '';

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->db         = new RedirectsFakeDb();
		$GLOBALS['wpdb']  = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->options    = [];
		$this->transients = [];
		$this->redirects  = [];
		$this->statuses   = [];
		$this->isAdmin    = false;
		$this->isAjax     = false;
		$this->isCron     = false;
		$this->sitemapVar = '';

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- test fixture preserves the superglobal, restored in tearDown.
			$this->originalUri = $_SERVER['REQUEST_URI'];
		}

		Redirector::resetSent();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_allowed_protocols' )->alias( static fn (): array => [ 'http', 'https' ] );
		Functions\when( 'is_admin' )->alias( fn (): bool => $this->isAdmin );
		Functions\when( 'wp_doing_ajax' )->alias( fn (): bool => $this->isAjax );
		Functions\when( 'wp_doing_cron' )->alias( fn (): bool => $this->isCron );
		Functions\when( 'get_query_var' )->alias(
			function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return $this->sitemapVar;
				}

				return $fallback;
			}
		);
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
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
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_redirect' )->alias(
			function ( string $location, int $status = 302 ): bool {
				$this->redirects[] = [
					'location' => $location,
					'status'   => $status,
				];

				return true;
			}
		);
		Functions\when( 'status_header' )->alias(
			function ( int $code ): void {
				$this->statuses[] = $code;
			}
		);
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( true );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( 'wp_unslash' )->alias( static fn ( string $v ): string => stripslashes( $v ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => $v );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		if ( null !== $this->originalUri ) {
			$_SERVER['REQUEST_URI'] = $this->originalUri;
		} else {
			unset( $_SERVER['REQUEST_URI'] );
		}

		unset( $GLOBALS['wpdb'] );
		Redirector::resetSent();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a dispatcher with real collaborators on the fake database.
	 *
	 * @return Redirector The result.
	 */
	private function dispatcher(): Redirector {
		$repo = new RedirectRepository( $this->db );

		return new Redirector( $repo, new RedirectCache(), new HitCounter(), new RedirectsSettings() );
	}

	/**
	 * Seed one exact rule.
	 *
	 * @param string $source Source.
	 * @param string $target Target.
	 * @param string $code   Code.
	 */
	private function seedExact( string $source, string $target, string $code = '301' ): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source' => $source,
				'target' => $target,
				'code'   => $code,
			]
		);

		$this->assertGreaterThan( 0, $id, 'Seed rule must insert' );
	}

	/**
	 * Turn query preservation off.
	 */
	private function disablePreserve(): void {
		$this->options['rankkernel_redirects_settings'] = [ 'preserve_query' => false ];
	}

	/**
	 * Test incoming query preserved by default.
	 */
	public function test_incoming_query_preserved_by_default(): void {
		$this->seedExact( '/old', '/new' );

		$_SERVER['REQUEST_URI'] = '/old?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?x=1', $this->redirects[0]['location'] );
	}

	/**
	 * Test multiple params preserved verbatim.
	 */
	public function test_multiple_params_preserved_verbatim(): void {
		$this->seedExact( '/old', '/new' );

		$_SERVER['REQUEST_URI'] = '/old?a=1&b=2';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?a=1&b=2', $this->redirects[0]['location'] );
	}

	/**
	 * Test destination query wins over incoming.
	 */
	public function test_destination_query_wins_over_incoming(): void {
		$this->seedExact( '/old', '/new?src=direct' );

		$_SERVER['REQUEST_URI'] = '/old?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?src=direct', $this->redirects[0]['location'] );
	}

	/**
	 * Test preserve off drops incoming query.
	 */
	public function test_preserve_off_drops_incoming_query(): void {
		$this->disablePreserve();
		$this->seedExact( '/old', '/new' );

		$_SERVER['REQUEST_URI'] = '/old?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new', $this->redirects[0]['location'] );
	}

	/**
	 * Test preserve off keeps destination query.
	 */
	public function test_preserve_off_keeps_destination_query(): void {
		$this->disablePreserve();
		$this->seedExact( '/old', '/new?src=direct' );

		$_SERVER['REQUEST_URI'] = '/old?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?src=direct', $this->redirects[0]['location'] );
	}

	/**
	 * Test encoded params pass verbatim.
	 */
	public function test_encoded_params_pass_verbatim(): void {
		$this->seedExact( '/old', '/new' );

		$_SERVER['REQUEST_URI'] = '/old?q=%2Fb%20x';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?q=%2Fb%20x', $this->redirects[0]['location'] );
	}

	/**
	 * Test repeated params pass verbatim.
	 */
	public function test_repeated_params_pass_verbatim(): void {
		$this->seedExact( '/old', '/new' );

		$_SERVER['REQUEST_URI'] = '/old?a=1&a=2';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?a=1&a=2', $this->redirects[0]['location'] );
	}

	/**
	 * Test incoming fragment never reaches destination.
	 */
	public function test_incoming_fragment_never_reaches_destination(): void {
		$this->seedExact( '/old', '/new' );

		$_SERVER['REQUEST_URI'] = '/old?x=1#frag';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?x=1', $this->redirects[0]['location'] );
	}

	/**
	 * Test stored destination fragment stripped at send.
	 */
	public function test_stored_destination_fragment_stripped_at_send(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source' => '/old',
				'target' => '/new#section',
				'code'   => '301',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new', $this->redirects[0]['location'] );
	}

	/**
	 * Test terminal 410 sends no location and ignores query.
	 */
	public function test_terminal_410_sends_no_location_and_ignores_query(): void {
		$this->seedExact( '/gone', '', '410' );

		$_SERVER['REQUEST_URI'] = '/gone?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
		$this->assertSame( [ 410 ], $this->statuses );
	}

	/**
	 * Test terminal 451 sends no location and ignores query.
	 */
	public function test_terminal_451_sends_no_location_and_ignores_query(): void {
		$this->seedExact( '/blocked', '', '451' );

		$_SERVER['REQUEST_URI'] = '/blocked?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
		$this->assertSame( [ 451 ], $this->statuses );
	}

	/**
	 * Test regex rule matches path and sends literal target.
	 */
	public function test_regex_rule_matches_path_and_sends_literal_target(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source'     => '^/old-[0-9]+$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/old-42?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?x=1', $this->redirects[0]['location'] );
	}

	/**
	 * Test regex capture reference sends literally without substitution.
	 */
	public function test_regex_capture_reference_sends_literally_without_substitution(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source'     => '^/old-[0-9]+$',
				'target'     => '/item/$1',
				'code'       => '301',
				'match_type' => 'regex',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/old-42';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/item/$1', $this->redirects[0]['location'] );
	}

	/**
	 * Test source query stripped at storage and ignored at match.
	 */
	public function test_source_query_stripped_at_storage_and_ignored_at_match(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source' => '/old?x=1',
				'target' => '/new',
			]
		);

		$this->assertGreaterThan( 0, $id );
		$this->assertSame( '/old', $this->db->rows[ $id ]['source'] );

		$_SERVER['REQUEST_URI'] = '/old?y=2';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?y=2', $this->redirects[0]['location'] );
	}

	/**
	 * Test pattern rule match ignores query.
	 */
	public function test_pattern_rule_match_ignores_query(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source'     => '/shop',
				'target'     => '/sale',
				'match_type' => 'prefix',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/shop/item?color=red';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/sale?color=red', $this->redirects[0]['location'] );
	}
}
