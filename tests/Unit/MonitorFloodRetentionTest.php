<?php
/**
 * 404 flood and retention interaction tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\FloodGuard;
use RankKernel\Modules\Monitor\Logger;
use RankKernel\Modules\Monitor\MonitorRepository;
use RankKernel\Modules\Monitor\MonitorSettings;
use RankKernel\Modules\Monitor\Pruner;

/**
 * Proves unique URL floods cannot force unbounded growth.
 *
 * The per window new URI budget throttles intake without IP storage or
 * cron, repeat hits on known URIs always keep counting for free, window
 * expiry restores intake, and the pruner holds the table at the
 * configured maximum with oldest first bounded deletes.
 */
final class MonitorFloodRetentionTest extends TestCase {
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

	private bool $is404 = true;

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
		$this->is404      = true;

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
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( '' );
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
		Functions\when( 'add_action' )->justReturn( true );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		if ( null !== $this->originalUri ) {
			$_SERVER['REQUEST_URI'] = $this->originalUri;
		}

		unset( $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );

		Logger::resetLogged();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a logger over the test doubles.
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

	public function test_configured_budget_controls_unique_intake(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 5 ];

		$logger = $this->makeLogger();

		for ( $i = 1; $i <= 6; $i++ ) {
			Logger::resetLogged();

			$this->request( $logger, '/unique-' . (string) $i );
		}

		$this->assertSame( 5, $this->repo->count() );
		$this->assertNull( $this->repo->findByHash( hash( 'sha256', '/unique-6' ) ) );
		$this->assertTrue( FloodGuard::isSuppressed() );
	}

	public function test_window_reset_restores_intake(): void {
		$this->options['rankkernel_404_settings'] = [
			'flood_budget' => 1,
			'flood_window' => 300,
		];

		$logger = $this->makeLogger();

		$this->request( $logger, '/first' );

		Logger::resetLogged();

		$this->request( $logger, '/second' );

		$this->assertSame( 1, $this->repo->count() );

		$this->transients['rk404_flood_1'] = [
			'start' => time() - 400,
			'count' => 1,
		];

		Logger::resetLogged();

		$this->request( $logger, '/second' );

		$this->assertSame( 2, $this->repo->count() );
		$this->assertNotNull( $this->repo->findByHash( hash( 'sha256', '/second' ) ) );
	}

	public function test_repeated_hits_never_spend_budget(): void {
		$this->options['rankkernel_404_settings'] = [ 'flood_budget' => 1 ];

		$logger = $this->makeLogger();

		$this->request( $logger, '/known' );

		for ( $i = 0; $i < 3; $i++ ) {
			Logger::resetLogged();

			$this->request( $logger, '/known' );
		}

		Logger::resetLogged();

		$this->request( $logger, '/stranger' );

		$row = $this->repo->findByHash( hash( 'sha256', '/known' ) );

		$this->assertIsArray( $row );
		$this->assertSame( 4, (int) $row['hits'] );
		$this->assertSame( 1, $this->repo->count(), 'Repeats cost rows, the stranger is suppressed' );
	}

	public function test_pruner_holds_flood_intake_at_maximum_oldest_first(): void {
		$this->options['rankkernel_404_settings'] = [
			'flood_budget' => 10,
			'max_rows'     => 5,
		];

		$settings = new MonitorSettings();
		$logger   = new Logger( $this->repo, $settings );

		for ( $i = 1; $i <= 4; $i++ ) {
			Logger::resetLogged();

			$this->request( $logger, '/flood-' . (string) $i );
		}

		$this->assertSame( 4, $this->repo->count() );

		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/older-a' ),
				'uri'           => '/older-a',
				'last_accessed' => '2026-01-01 00:00:00',
			]
		);
		$this->db->seed(
			[
				'uri_hash'      => hash( 'sha256', '/older-b' ),
				'uri'           => '/older-b',
				'last_accessed' => '2026-01-02 00:00:00',
			]
		);

		$this->assertSame( 6, $this->repo->count() );

		$pruner  = new Pruner( $this->repo, $settings );
		$deleted = $pruner->pruneByCount();

		$this->assertGreaterThanOrEqual( 1, $deleted );
		$this->assertLessThanOrEqual( 5, $this->repo->count() );
		$this->assertNull(
			$this->repo->findByHash( hash( 'sha256', '/older-a' ) ),
			'Oldest rows prune first'
		);
		$this->assertNotNull( $this->repo->findByHash( hash( 'sha256', '/flood-4' ) ) );
	}

	public function test_no_ip_address_is_read_or_stored(): void {
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';

		$this->request( $this->makeLogger(), '/missing-page' );

		$row = $this->repo->findByHash( hash( 'sha256', '/missing-page' ) );

		$this->assertIsArray( $row );

		$keys = array_keys( $row );

		sort( $keys );

		$this->assertSame( [ 'created', 'hits', 'id', 'last_accessed', 'referer', 'uri', 'uri_hash', 'user_agent' ], $keys );
	}
}
