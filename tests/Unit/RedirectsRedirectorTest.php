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
use RankKernel\Modules\Redirects\RedirectTable;

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
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => $v );
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
	 * Build a dispatcher whose matcher throws on every lookup.
	 *
	 * The cache and repository stay real so the throw path is exercised
	 * through the production lookup order.
	 *
	 * @return Redirector The result.
	 */
	private function throwingMatcherDispatcher(): Redirector {
		$repo    = new RedirectRepository( $this->db );
		$matcher = new class() {
			/**
			 * Fail every lookup.
			 *
			 * @param string $path Request path.
			 * @return array<string, mixed>|null Never returns, always throws.
			 * @throws \RuntimeException Always, to exercise the fail open path.
			 */
			public function match( string $path ): ?array {
				unset( $path );

				throw new \RuntimeException( 'matcher failure' );

				// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable -- keeps the declared return contract explicit for the throwing seam.
				return null;
			}
		};

		return new Redirector( $repo, new RedirectCache(), new HitCounter(), new RedirectsSettings(), $matcher );
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
	 * Test cache hit runs zero rule queries and bypasses table existence probe.
	 */
	public function test_cache_hit_runs_zero_rule_queries(): void {
		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		Redirector::resetSent();
		RedirectTable::resetCache();

		$readsAfterFirst  = $this->db->ruleReads;
		$probesAfterFirst = $this->db->schemaProbes;

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 2, $this->redirects );
		$this->assertSame( $readsAfterFirst, $this->db->ruleReads, 'Cache hit must run zero rule queries' );
		$this->assertSame( $probesAfterFirst, $this->db->schemaProbes, 'Cache hit must bypass table existence probe' );
	}

	/**
	 * Test table existence probe runs on cold cache miss.
	 */
	public function test_table_exists_probed_on_cache_miss(): void {
		$this->seedExact();

		RedirectTable::resetCache();
		$this->db->schemaProbes = 0;

		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( 1, $this->db->schemaProbes, 'Cold cache miss must probe table existence' );
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

	/**
	 * Test the blocked homepage returns before the table probe a normal source pays.
	 */
	public function test_blocked_source_returns_before_the_table_probe(): void {
		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/';

		Redirector::resetSent();
		RedirectTable::resetCache();
		$this->db->schemaProbes = 0;

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects, 'The homepage must not redirect' );
		$this->assertSame( 0, $this->db->schemaProbes, 'A blocked source must not probe the redirect table' );

		$_SERVER['REQUEST_URI'] = '/old';

		Redirector::resetSent();
		RedirectTable::resetCache();
		$this->db->schemaProbes = 0;

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( 1, $this->db->schemaProbes, 'A normal source still probes the table exactly once' );
	}

	/**
	 * Test a throwing matcher fails open and performs no redirect.
	 */
	public function test_throwing_matcher_fails_open(): void {
		$this->seedExact();

		$_SERVER['REQUEST_URI'] = '/old';

		$this->throwingMatcherDispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects, 'A throwing matcher must not redirect' );
		$this->assertSame( [], $this->statuses, 'A throwing matcher must send no status' );
		$this->assertContains( 'rankkernel/redirect/failed', $this->actions, 'The matcher failure must be logged' );
	}

	/**
	 * Test a repeat of the same source to destination pair answers 410 instead of redirecting.
	 */
	public function test_repeat_hop_answers_gone_instead_of_redirecting(): void {
		$this->seedExact( '/loops', '/new' );

		$_SERVER['REQUEST_URI'] = '/loops';

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects, 'The first hop must redirect' );

		// Second hop of the same pair, the marker cookie is now present.
		$marked = Redirector::markedHops();

		$this->assertCount( 1, $marked, 'The first hop must record a marker' );

		$_COOKIE = [ $marked[0] => '1' ];

		Redirector::resetSent();
		$this->statuses = [];

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, $this->redirects, 'The second hop must not redirect again' );
		$this->assertSame( [ 410 ], $this->statuses, 'The repeated hop must answer 410' );
		$this->assertContains( 'rankkernel/redirect/loop', $this->actions );

		$_COOKIE = [];
	}

	/**
	 * Test the hop marker does not block a different destination for the same source.
	 */
	public function test_hop_marker_does_not_block_a_different_destination(): void {
		$this->seedExact( '/loops', '/new' );

		$_SERVER['REQUEST_URI'] = '/loops';

		$this->dispatcher()->maybeRedirect();

		$_COOKIE = [ 'rankkernel_rh_' . str_repeat( '0', 16 ) => '1' ];

		Redirector::resetSent();

		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 2, $this->redirects, 'A marker for another pair must not block this hop' );

		$_COOKIE = [];
	}

	/**
	 * Build a dispatcher whose matcher always returns one fixed rule row.
	 *
	 * Lets a test present a row shape the repository would never write, such
	 * as a rule with no source hash.
	 *
	 * @param array<string, mixed> $rule Row the matcher returns.
	 * @return Redirector The result.
	 */
	private function fixedMatcherDispatcher( array $rule ): Redirector {
		$repo    = new RedirectRepository( $this->db );
		$matcher = new class( $rule ) {
			/**
			 * Row to return.
			 *
			 * @var array<string, mixed>
			 */
			private array $rule;

			/**
			 * Set up the matcher.
			 *
			 * @param array<string, mixed> $rule Row to return.
			 */
			public function __construct( array $rule ) {
				$this->rule = $rule;
			}

			/**
			 * Always return the fixed row.
			 *
			 * @param string $path Request path.
			 * @return array<string, mixed>|null The fixed row.
			 */
			public function match( string $path ): ?array {
				unset( $path );

				return $this->rule;
			}
		};

		return new Redirector( $repo, new RedirectCache(), new HitCounter(), new RedirectsSettings(), $matcher );
	}

	/**
	 * Build a redirect rule row with no source hash.
	 *
	 * @param string $target Target.
	 * @return array<string, mixed> Rule row.
	 */
	private function hashlessRule( string $target = '/new' ): array {
		return [
			'id'          => 7,
			'source'      => '/hashless',
			'source_hash' => '',
			'target'      => $target,
			'code'        => '301',
			'match_type'  => 'exact',
			'is_active'   => 1,
		];
	}

	/**
	 * Test a rule with no source hash is still bounded by the hop guard.
	 *
	 * A row with no source hash is one the repository never writes, but it is
	 * exactly the malformed row most likely to loop, and the marker name used
	 * to come back empty for it. Empty read as no guard available, so the pair
	 * redirected with nothing bounding it.
	 */
	public function test_rule_without_a_source_hash_is_still_bounded(): void {
		$_SERVER['REQUEST_URI'] = '/hashless';

		$this->fixedMatcherDispatcher( $this->hashlessRule() )->maybeRedirect();

		$this->assertCount( 1, $this->redirects, 'The first hop must redirect' );

		$marked = Redirector::markedHops();

		$this->assertCount( 1, $marked, 'A row with no source hash must still record a marker' );
		$this->assertNotSame( '', $marked[0], 'A bounded pair must have a real marker name' );
		$this->assertStringStartsWith( 'rankkernel_rh_', $marked[0] );
		$this->assertContains( 'rankkernel/redirect/failed', $this->actions, 'The missing hash must be surfaced' );

		$_COOKIE = [ $marked[0] => '1' ];

		Redirector::resetSent();
		$this->statuses = [];

		$this->fixedMatcherDispatcher( $this->hashlessRule() )->maybeRedirect();

		$this->assertCount( 1, $this->redirects, 'The repeat must not redirect again' );
		$this->assertSame( [ 410 ], $this->statuses, 'The repeat must answer 410' );

		$_COOKIE = [];
	}

	/**
	 * Test the marker name stays specific when the source hash is missing.
	 *
	 * A single shared name for every unbounded rule would answer 410 for one
	 * rule because an unrelated one had looped.
	 */
	public function test_marker_for_a_hashless_rule_does_not_cover_another_pair(): void {
		$_SERVER['REQUEST_URI'] = '/hashless';

		$this->fixedMatcherDispatcher( $this->hashlessRule( '/new' ) )->maybeRedirect();

		$first = Redirector::markedHops()[0];

		$_COOKIE = [];

		Redirector::resetSent();
		$this->redirects = [];

		// A different request path, so the lookup does not come back from the
		// dispatch cache with the row the first hop already cached.
		$_SERVER['REQUEST_URI'] = '/hashless-two';
		$this->fixedMatcherDispatcher( $this->hashlessRule( '/elsewhere' ) )->maybeRedirect();

		$this->assertCount( 1, $this->redirects, 'A different destination is a different pair and must redirect' );
		$this->assertNotSame( $first, Redirector::markedHops()[0], 'Each pair needs its own marker' );
	}

	/**
	 * Test the hop marker is recorded even when output already started.
	 *
	 * The record used to sit behind the headers_sent check, so a response that
	 * had begun sending left no marker at all and the same pair was redirected
	 * again rather than answered.
	 */
	public function test_hop_marker_is_recorded_when_output_already_started(): void {
		Functions\when( 'headers_sent' )->justReturn( true );

		$_SERVER['REQUEST_URI'] = '/old';

		$this->seedExact();
		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, Redirector::markedHops(), 'The bound must hold even when the cookie cannot be sent' );
		$this->assertContains(
			'rankkernel/redirect/failed',
			$this->actions,
			'The skipped cookie must be reported rather than swallowed'
		);
	}

	/**
	 * Test a refused cookie is reported instead of being suppressed.
	 */
	public function test_refused_cookie_is_reported(): void {
		Functions\when( 'setcookie' )->justReturn( false );

		$_SERVER['REQUEST_URI'] = '/old';

		$this->seedExact();
		$this->dispatcher()->maybeRedirect();

		$this->assertCount( 1, Redirector::markedHops(), 'The in process bound must still be recorded' );
		$this->assertContains(
			'rankkernel/redirect/failed',
			$this->actions,
			'A refused cookie must be reported through the plugin failure convention'
		);
	}

	/**
	 * Test a throwing cache read fails open and performs no redirect.
	 */
	public function test_throwing_cache_fails_open(): void {
		$this->seedExact();

		Functions\when( 'get_transient' )->alias(
			static function ( string $key ): mixed {
				if ( str_starts_with( $key, 'rkredir_' ) ) {
					throw new \RuntimeException( 'cache failure' );
				}

				return false;
			}
		);

		$_SERVER['REQUEST_URI'] = '/old';

		$this->dispatcher()->maybeRedirect();

		$this->assertSame( [], $this->redirects, 'A throwing cache must not redirect' );
		$this->assertSame( [], $this->statuses, 'A throwing cache must send no status' );
		$this->assertContains( 'rankkernel/redirect/failed', $this->actions, 'The cache failure must be logged' );
	}
}
