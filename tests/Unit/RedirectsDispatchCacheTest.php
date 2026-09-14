<?php
/**
 * Dispatch cache and hit counter verification tests.
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
 * Proves the cache retires correctly under writes.
 *
 * Cold requests read, warm requests reuse, updates, deletes, and
 * deactivations invalidate, counters never write before the response,
 * and shutdown coalesces to one UPDATE per touched rule.
 */
final class RedirectsDispatchCacheTest extends TestCase {
	/**
	 * Fake database.
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

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->db        = new RedirectsFakeDb();
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->options    = [];
		$this->transients = [];
		$this->redirects  = [];

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
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
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( '' );
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
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( true );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( 'wp_unslash' )->alias( static fn ( string $v ): string => stripslashes( $v ) );
	}

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
	 * Build a dispatcher sharing one cache and repository instance.
	 *
	 * @param HitCounter|null $hits Hit counter, fresh one when null.
	 * @return array{0: Redirector, 1: RedirectRepository} Dispatcher plus repository.
	 */
	private function wiredDispatcher( ?HitCounter $hits = null ): array {
		$cache = new RedirectCache();
		$repo  = new RedirectRepository( $this->db, $cache );

		$dispatcher = new Redirector( $repo, $cache, $hits ?? new HitCounter(), new RedirectsSettings() );

		return [ $dispatcher, $repo ];
	}

	public function test_update_invalidates_cached_match(): void {
		[ $dispatcher, $repo ] = $this->wiredDispatcher();

		$id = $repo->insert(
			[
				'source' => '/old',
				'target' => '/v1',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/old';

		$dispatcher->maybeRedirect();

		$this->assertSame( '/v1', $this->redirects[0]['location'] );

		$this->assertTrue( $repo->update( $id, [ 'target' => '/v2' ] ) );

		Redirector::resetSent();

		$dispatcher->maybeRedirect();

		$this->assertCount( 2, $this->redirects );
		$this->assertSame( '/v2', $this->redirects[1]['location'], 'An update must retire the cached destination' );
	}

	public function test_delete_stops_dispatch(): void {
		[ $dispatcher, $repo ] = $this->wiredDispatcher();

		$id = $repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/old';

		$dispatcher->maybeRedirect();

		$this->assertCount( 1, $this->redirects );

		$this->assertTrue( $repo->delete( $id ) );

		Redirector::resetSent();

		$dispatcher->maybeRedirect();

		$this->assertCount( 1, $this->redirects, 'A deleted rule must stop dispatching' );
	}

	public function test_deactivate_stops_dispatch(): void {
		[ $dispatcher, $repo ] = $this->wiredDispatcher();

		$id = $repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/old';

		$dispatcher->maybeRedirect();

		$this->assertCount( 1, $this->redirects );

		$this->assertTrue( $repo->set_active( $id, false ) );

		Redirector::resetSent();

		$dispatcher->maybeRedirect();

		$this->assertCount( 1, $this->redirects, 'A deactivated rule must stop dispatching' );
	}

	public function test_counters_never_write_before_response(): void {
		$hits = new HitCounter();

		[ $dispatcher, $repo ] = $this->wiredDispatcher( $hits );

		$id = $repo->insert(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$writesBefore = $this->db->writes;

		$_SERVER['REQUEST_URI'] = '/old';

		$dispatcher->maybeRedirect();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( $writesBefore, $this->db->writes, 'The redirect response must not wait on a statistics write' );
		$this->assertSame( [ $id => 1 ], $hits->pending() );

		$hits->flush();

		$this->assertSame( [], $hits->pending() );
		$this->assertSame( 1, (int) $this->db->rows[ $id ]['hits'] );
	}

	public function test_uncached_admin_write_retires_frontend_cache(): void {
		[ $dispatcher, $repo ] = $this->wiredDispatcher();

		$id = $repo->insert(
			[
				'source' => '/old',
				'target' => '/v1',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$_SERVER['REQUEST_URI'] = '/old';

		$dispatcher->maybeRedirect();

		$this->assertSame( '/v1', $this->redirects[0]['location'] );

		$adminRepo = new RedirectRepository( $this->db );

		$this->assertTrue( $adminRepo->update( $id, [ 'target' => '/v2' ] ) );

		Redirector::resetSent();

		[ $secondDispatcher ] = $this->wiredDispatcher();

		$secondDispatcher->maybeRedirect();

		$this->assertCount( 2, $this->redirects );
		$this->assertSame( '/v2', $this->redirects[1]['location'], 'An admin save without a cache instance must still retire frontend caches' );
	}

	public function test_multiple_hits_coalesce_to_one_update_per_rule(): void {
		$this->db->rows[1] = [
			'id'            => 1,
			'match_type'    => 'exact',
			'source_hash'   => hash( 'sha256', 'exact|/a' ),
			'source'        => '/a',
			'target'        => '/x',
			'code'          => '301',
			'is_active'     => 1,
			'created'       => '2026-01-01 00:00:00',
			'hits'          => 0,
			'last_accessed' => null,
		];
		$this->db->rows[2] = [
			'id'            => 2,
			'match_type'    => 'exact',
			'source_hash'   => hash( 'sha256', 'exact|/b' ),
			'source'        => '/b',
			'target'        => '/y',
			'code'          => '301',
			'is_active'     => 1,
			'created'       => '2026-01-01 00:00:00',
			'hits'          => 0,
			'last_accessed' => null,
		];

		$hits = new HitCounter();
		$hits->record( 1 );
		$hits->record( 1 );
		$hits->record( 2 );

		$writesBefore = $this->db->writes;

		$hits->flush();

		$this->assertSame( 2, $this->db->writes - $writesBefore, 'One UPDATE per touched rule' );
		$this->assertSame( 2, (int) $this->db->rows[1]['hits'] );
		$this->assertSame( 1, (int) $this->db->rows[2]['hits'] );
	}
}
