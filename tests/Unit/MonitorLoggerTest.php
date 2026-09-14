<?php
/**
 * 404 logger tests, gating plus dedupe plus privacy.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\Logger;
use RankKernel\Modules\Monitor\MonitorRepository;
use RankKernel\Modules\Monitor\MonitorSettings;

final class MonitorLoggerTest extends TestCase {
	/**
	 * Fake database.
	 */
	private MonitorFakeDb $db;

	/**
	 * Repository under test.
	 */
	private MonitorRepository $repo;

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
	 * Registered hooks.
	 *
	 * @var array<int, array{hook: string, priority: int}>
	 */
	private array $hooks = [];

	private bool $is404 = true;

	private bool $isAdmin = false;

	private bool $isAjax = false;

	private bool $isCron = false;

	private string $sitemapVar = '';

	private string $xslVar = '';

	/**
	 * Original request URI for restoration.
	 *
	 * @var string|null
	 */
	private ?string $originalUri = null;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db   = new MonitorFakeDb();
		$this->repo = new MonitorRepository( $this->db );

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

		$this->options    = [];
		$this->transients = [];
		$this->hooks      = [];
		$this->is404      = true;
		$this->isAdmin    = false;
		$this->isAjax     = false;
		$this->isCron     = false;
		$this->sitemapVar = '';
		$this->xslVar     = '';

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			$this->originalUri = $_SERVER['REQUEST_URI'];
		}

		unset( $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'] );

		Logger::resetLogged();

		// Test double backing the stubbed wp_parse_url with the native parser.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ): mixed => parse_url( $url, $component ) );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'is_404' )->alias( fn (): bool => $this->is404 );
		Functions\when( 'is_admin' )->alias( fn (): bool => $this->isAdmin );
		Functions\when( 'wp_doing_ajax' )->alias( fn (): bool => $this->isAjax );
		Functions\when( 'wp_doing_cron' )->alias( fn (): bool => $this->isCron );
		Functions\when( 'get_query_var' )->alias(
			function ( string $key, mixed $fallback = '' ): mixed {
				if ( 'rankkernel_sitemap' === $key ) {
					return $this->sitemapVar;
				}

				if ( 'rankkernel_sitemap_xsl' === $key ) {
					return $this->xslVar;
				}

				return $fallback;
			}
		);
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
		Functions\when( 'delete_option' )->alias(
			function ( string $key ): bool {
				unset( $this->options[ $key ] );

				return true;
			}
		);
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
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-01 12:00:00' );
		Functions\when( 'wp_unslash' )->alias( static fn ( string $v ): string => stripslashes( $v ) );
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( string $v ): string {
				$stripped = preg_replace( '/<[^>]*>/', '', $v );

				return trim( is_string( $stripped ) ? $stripped : $v );
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
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		if ( null !== $this->originalUri ) {
			$_SERVER['REQUEST_URI'] = $this->originalUri;
		}

		unset( $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'] );

		Logger::resetLogged();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a logger over the test doubles, HTTP 200 unless changed.
	 */
	private function makeLogger(): Logger {
		$logger = new Logger( $this->repo, new MonitorSettings() );
		$logger->setResponseCodeOverride( 200 );

		return $logger;
	}

	/**
	 * Simulate one request for a URI.
	 */
	private function request( Logger $logger, string $uri ): void {
		$_SERVER['REQUEST_URI'] = $uri;

		$logger->maybeLog();
	}

	public function test_genuine_404_creates_exactly_one_row(): void {
		$logger = $this->makeLogger();

		$this->request( $logger, '/missing-page' );

		$this->assertSame( 1, $this->repo->count() );

		$row = $this->repo->findByHash( hash( 'sha256', '/missing-page' ) );

		$this->assertIsArray( $row );
		$this->assertSame( 1, (int) $row['hits'] );

		$keys = array_keys( $row );

		sort( $keys );

		$this->assertSame( [ 'created', 'hits', 'id', 'last_accessed', 'referer', 'uri', 'uri_hash', 'user_agent' ], $keys );
	}

	public function test_repeat_404_increments_same_row_across_requests(): void {
		$logger = $this->makeLogger();

		$this->request( $logger, '/missing-page' );

		Logger::resetLogged();

		$this->request( $logger, '/missing-page' );

		$this->assertSame( 1, $this->repo->count() );

		$row = $this->repo->findByHash( hash( 'sha256', '/missing-page' ) );

		$this->assertIsArray( $row );
		$this->assertSame( 2, (int) $row['hits'] );
	}

	public function test_second_hit_inside_one_request_counts_once(): void {
		$logger = $this->makeLogger();

		$_SERVER['REQUEST_URI'] = '/missing-page';

		$logger->maybeLog();
		$logger->maybeLog();

		$row = $this->repo->findByHash( hash( 'sha256', '/missing-page' ) );

		$this->assertIsArray( $row );
		$this->assertSame( 1, (int) $row['hits'] );
	}

	public function test_insert_schedules_shutdown_prune(): void {
		$logger = $this->makeLogger();

		$this->request( $logger, '/missing-page' );

		$shutdown = array_values( array_filter( $this->hooks, static fn ( array $h ): bool => 'shutdown' === $h['hook'] ) );

		$this->assertNotEmpty( $shutdown );
	}

	public function test_skips_non_404(): void {
		$this->is404 = false;

		$this->request( $this->makeLogger(), '/missing-page' );

		$this->assertSame( 0, $this->repo->count() );
	}

	public function test_skips_admin_ajax_and_cron(): void {
		$logger = $this->makeLogger();

		$this->isAdmin = true;

		$this->request( $logger, '/admin-skipped' );

		$this->isAdmin = false;
		$this->isAjax  = true;

		Logger::resetLogged();

		$this->request( $logger, '/ajax-skipped' );

		$this->isAjax = false;
		$this->isCron = true;

		Logger::resetLogged();

		$this->request( $logger, '/cron-skipped' );

		$this->assertSame( 0, $this->repo->count() );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_skips_rest_requests(): void {
		define( 'REST_REQUEST', true );

		$this->request( $this->makeLogger(), '/rest-skipped' );

		$this->assertSame( 0, $this->repo->count() );
	}

	public function test_skips_sitemap_requests(): void {
		$logger = $this->makeLogger();

		$this->sitemapVar = 'index';

		$this->request( $logger, '/sitemap-skipped' );

		$this->sitemapVar = '';
		$this->xslVar     = 'index';

		Logger::resetLogged();

		$this->request( $logger, '/xsl-skipped' );

		$this->assertSame( 0, $this->repo->count() );
	}

	public function test_skips_gone_and_unavailable_codes(): void {
		$logger = $this->makeLogger();

		$logger->setResponseCodeOverride( 410 );

		$this->request( $logger, '/gone-code' );

		$logger->setResponseCodeOverride( 451 );

		Logger::resetLogged();

		$this->request( $logger, '/legal-code' );

		$this->assertSame( 0, $this->repo->count() );
	}

	public function test_skips_static_assets_but_logs_content(): void {
		$logger = $this->makeLogger();

		foreach ( [ '/theme/app.css', '/img/photo.PNG', '/fonts/icons.woff2', '/media/clip.mp4' ] as $asset ) {
			$this->request( $logger, $asset );

			Logger::resetLogged();
		}

		$this->assertSame( 0, $this->repo->count() );

		$this->request( $logger, '/real-article' );

		$this->assertSame( 1, $this->repo->count() );
	}

	public function test_skips_probe_patterns(): void {
		$logger = $this->makeLogger();

		$this->request( $logger, '/.env' );

		Logger::resetLogged();

		$this->request( $logger, '/wp-config.php.bak' );

		$this->assertSame( 0, $this->repo->count() );
	}

	public function test_exclusions_are_honored_before_any_write(): void {
		$this->options['rankkernel_404_settings'] = [
			'exclusions' => [
				[
					'comparator' => 'prefix',
					'value'      => '/private',
				],
			],
		];

		$writesBefore = $this->db->writes;

		$this->request( $this->makeLogger(), '/private/doc' );

		$this->assertSame( 0, $this->repo->count() );
		$this->assertSame( $writesBefore, $this->db->writes );

		Logger::resetLogged();

		$this->request( $this->makeLogger(), '/public/doc' );

		$this->assertSame( 1, $this->repo->count() );
	}

	public function test_ignore_query_on_collapses_variants(): void {
		$logger = $this->makeLogger();

		$this->request( $logger, '/gone?ref=a' );

		Logger::resetLogged();

		$this->request( $logger, '/gone?ref=b' );

		$this->assertSame( 1, $this->repo->count() );

		$row = $this->repo->findByHash( hash( 'sha256', '/gone' ) );

		$this->assertIsArray( $row );
		$this->assertSame( 2, (int) $row['hits'] );
	}

	public function test_ignore_query_off_keeps_variants_distinct(): void {
		$this->options['rankkernel_404_settings'] = [ 'ignore_query' => false ];

		$logger = $this->makeLogger();

		$this->request( $logger, '/gone?ref=a' );

		Logger::resetLogged();

		$this->request( $logger, '/gone?ref=b' );

		$this->assertSame( 2, $this->repo->count() );
	}

	public function test_advanced_fields_off_by_default(): void {
		$_SERVER['HTTP_REFERER']    = 'https://referrer.example/entry';
		$_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';

		$this->request( $this->makeLogger(), '/missing-page' );

		$row = $this->repo->findByHash( hash( 'sha256', '/missing-page' ) );

		$this->assertIsArray( $row );
		$this->assertSame( '', $row['referer'] );
		$this->assertSame( '', $row['user_agent'] );
	}

	public function test_advanced_fields_on_captures_and_truncates(): void {
		$this->options['rankkernel_404_settings'] = [ 'advanced_fields' => true ];

		$_SERVER['HTTP_REFERER']    = 'https://referrer.example/entry';
		$_SERVER['HTTP_USER_AGENT'] = str_repeat( 'a', 300 );

		$this->request( $this->makeLogger(), '/missing-page' );

		$row = $this->repo->findByHash( hash( 'sha256', '/missing-page' ) );

		$this->assertIsArray( $row );
		$this->assertSame( 'https://referrer.example/entry', $row['referer'] );
		$this->assertSame( 255, strlen( (string) $row['user_agent'] ) );
	}

	public function test_flood_budget_suppresses_new_uris_but_keeps_existing_counting(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 2 ];

		$logger = $this->makeLogger();

		$this->request( $logger, '/new-one' );

		Logger::resetLogged();

		$this->request( $logger, '/new-two' );

		Logger::resetLogged();

		$this->request( $logger, '/new-suppressed' );

		$this->assertSame( 2, $this->repo->count() );
		$this->assertNull( $this->repo->findByHash( hash( 'sha256', '/new-suppressed' ) ) );
		$this->assertArrayHasKey( 'rankkernel_404_suppressed', $this->options );

		Logger::resetLogged();

		$this->request( $logger, '/new-one' );

		$row = $this->repo->findByHash( hash( 'sha256', '/new-one' ) );

		$this->assertIsArray( $row );
		$this->assertSame( 2, (int) $row['hits'] );
	}
}
