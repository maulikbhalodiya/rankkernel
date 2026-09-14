<?php
/**
 * Redirector dispatch tests, guards plus query budgets.
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
 * Redirects Redirector Test.
 */
final class RedirectsRedirectorTest extends TestCase {
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
	 * Fired actions.
	 *
	 * @var string[]
	 */
	private array $actions = [];

	/**
	 * Registered hooks.
	 *
	 * @var array<int, array{hook: string, priority: int}>
	 */
	private array $hooks = [];

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
	 * Original request URI for restoration.
	 *
	 * @var string|null
	 */
	private ?string $originalUri = null;

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

		$this->db        = new RedirectsFakeDb();
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double, restored in tearDown.

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
		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10 ): bool {
				$this->hooks[] = [
					'hook'     => $hook,
					'priority' => $priority,
				];

				return true;
			}
		);
		Functions\when( 'do_action' )->alias(
			function ( string $hook ): void {
				$this->actions[] = $hook;
			}
		);
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
	private function seedExact( string $source = '/old', string $target = '/new', string $code = '301' ): void {
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
	 * Test cold miss exact runs one indexed lookup.
	 */
	public function test_cold_miss_exact_runs_one_indexed_lookup(): void {
		$this->seedExact();

		$readsBefore = $this->db->ruleReads;

		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new', $this->redirects[0]['location'] );
		$this->assertSame( 301, $this->redirects[0]['status'] );
		$this->assertSame( 1, $this->db->ruleReads - $readsBefore, 'Cold exact miss must cost one indexed lookup' );
	}

	/**
	 * Test cache hit runs zero rule queries.
	 */
	public function test_cache_hit_runs_zero_rule_queries(): void {
		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		Redirector::resetSent();

		$readsAfterFirst = $this->db->ruleReads;

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 2, $this->redirects );
		$this->assertSame( $readsAfterFirst, $this->db->ruleReads, 'Cache hit must run zero rule queries' );
	}

	/**
	 * Test admin requests skipped.
	 */
	public function test_admin_requests_skipped(): void {
		$this->seedExact();

		$this->isAdmin          = true;
		$_SERVER['REQUEST_URI'] = '/old';
		$readsBefore            = $this->db->reads;

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
		$this->assertSame( $readsBefore, $this->db->reads );
	}

	/**
	 * Test ajax requests skipped.
	 */
	public function test_ajax_requests_skipped(): void {
		$this->seedExact();

		$this->isAjax           = true;
		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
	}

	/**
	 * Test cron requests skipped.
	 */
	public function test_cron_requests_skipped(): void {
		$this->seedExact();

		$this->isCron           = true;
		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
	}

	/**
	 * Test sitemap requests skipped.
	 */
	public function test_sitemap_requests_skipped(): void {
		$this->seedExact();

		$this->sitemapVar       = 'post';
		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
	}

	/**
	 * Test homepage never redirects.
	 */
	public function test_homepage_never_redirects(): void {
		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
	}

	/**
	 * Test gone sends status without location.
	 */
	public function test_gone_sends_status_without_location(): void {
		$this->seedExact( '/gone', '', '410' );

		$_SERVER['REQUEST_URI'] = '/gone';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
		$this->assertSame( [ 410 ], $this->statuses );
	}

	/**
	 * Test query preserved by default.
	 */
	public function test_query_preserved_by_default(): void {
		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/old?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?x=1', $this->redirects[0]['location'] );
	}

	/**
	 * Test query dropped when setting off.
	 */
	public function test_query_dropped_when_setting_off(): void {
		$this->options[ RedirectsSettings::OPTION ] = [ 'preserve_query' => false ];

		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/old?x=1';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new', $this->redirects[0]['location'] );
	}

	/**
	 * Test reentry sends exactly once.
	 */
	public function test_reentry_sends_exactly_once(): void {
		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/old';

		$dispatcher = $this->dispatcher();

		$dispatcher->maybeRedirect();
		$dispatcher->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertContains( 'rankkernel/redirect/reentry', $this->actions );
	}

	/**
	 * Test unsafe destination never sent.
	 */
	public function test_unsafe_destination_never_sent(): void {
		$this->seedExact( '/evil', 'javascript:alert(1)' );

		$_SERVER['REQUEST_URI'] = '/evil';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
		$this->assertSame( [], $this->statuses );
	}

	/**
	 * Test crlf destination never sent.
	 */
	public function test_crlf_destination_never_sent(): void {
		$this->seedExact( '/split', "/new\r\nLocation: https://evil.example/" );

		$_SERVER['REQUEST_URI'] = '/split';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
		$this->assertSame( [], $this->statuses );
	}

	/**
	 * Test missing table fails open.
	 */
	public function test_missing_table_fails_open(): void {
		$this->db->tableExists = false;

		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects );
	}

	/**
	 * Test register uses template redirect priority one.
	 */
	public function test_register_uses_template_redirect_priority_one(): void {
		$this->dispatcher()->register();

		$found = array_values(
			array_filter(
				$this->hooks,
				static fn ( array $h ): bool => 'template_redirect' === $h['hook'] && 1 === $h['priority']
			)
		);

		$this->assertCount( 1, $found );
	}
}
