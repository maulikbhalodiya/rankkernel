<?php
/**
 * Import controller test.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Rest\ImportController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests the importer REST controller.
 */
final class ImportControllerTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey and WordPress stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ): string {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post_meta' )->justReturn( false );
		Functions\when( 'get_term_meta' )->justReturn( false );
		Functions\when( '__' )->alias(
			static function ( string $text ): string {
				return $text;
			}
		);
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Make a request double.
	 *
	 * @param array<string, mixed> $params Params.
	 * @return WP_REST_Request The request.
	 */
	private function makeRequest( array $params ): WP_REST_Request {
		$req = Mockery::mock( WP_REST_Request::class );
		$req->shouldReceive( 'get_param' )->andReturnUsing(
			static function ( string $key ) use ( $params ) {
				return $params[ $key ] ?? null;
			}
		);
		$req->shouldReceive( 'get_json_params' )->andReturn( $params );
		$req->shouldReceive( 'get_params' )->andReturn( $params );
		return $req;
	}

	/**
	 * Make a controller with canned posts.
	 *
	 * @param array<int, array{id: int, type: string, data: array<string, mixed>}> $posts Posts.
	 * @return array{0: ImportController, 1: ImportControllerStubRunner} The pair.
	 */
	private function makeController( array $posts = [] ): array {
		$runner = new ImportControllerStubRunner();
		$ctrl   = new ImportController(
			[ 'yoast' => new ImportControllerStubReader( $posts ) ],
			static function () use ( $runner ): ImportControllerStubRunner {
				return $runner;
			}
		);
		return [ $ctrl, $runner ];
	}

	/**
	 * Test routes are registered for preview and run.
	 */
	public function test_register_routes_pins_preview_and_run(): void {
		$registered = [];

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $restNamespace, string $route, array $args ) use ( &$registered ): bool {
				$registered[] = [
					'namespace' => $restNamespace,
					'route'     => $route,
					'args'      => $args,
				];
				return true;
			}
		);

		[ $ctrl ] = $this->makeController();
		$ctrl->registerRoutes();

		$this->assertCount( 2, $registered );
		$this->assertSame( 'rankkernel/v1', $registered[0]['namespace'] );
		$this->assertSame( 'POST', $registered[0]['args']['methods'] );
		$this->assertStringContainsString( 'preview', $registered[0]['route'] );
		$this->assertStringContainsString( 'run', $registered[1]['route'] );
		$this->assertSame( [ $ctrl, 'checkPermission' ], $registered[0]['args']['permission_callback'] );
	}

	/**
	 * Test permission allows managers and blocks others.
	 */
	public function test_check_permission_gates_on_manage_options(): void {
		[ $ctrl ] = $this->makeController();
		$this->assertTrue( $ctrl->checkPermission() );

		Functions\when( 'current_user_can' )->justReturn( false );
		$res = $ctrl->checkPermission();
		$this->assertInstanceOf( WP_Error::class, $res );
		$this->assertSame( 403, $res->get_error_data()['status'] ?? null );
	}

	/**
	 * Test preview rejects an unknown source.
	 */
	public function test_preview_unknown_source_returns_400(): void {
		[ $ctrl ] = $this->makeController();
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'nope',
					'mode'   => 'skip',
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $res );
		$this->assertSame( 400, $res->get_error_data()['status'] ?? null );
	}

	/**
	 * Test preview rejects an invalid mode.
	 */
	public function test_preview_invalid_mode_returns_400(): void {
		[ $ctrl ] = $this->makeController();
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'merge',
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $res );
		$this->assertSame( 400, $res->get_error_data()['status'] ?? null );
	}

	/**
	 * Test preview returns counts and rows.
	 */
	public function test_preview_returns_counts_and_rows(): void {
		$posts = [
			[
				'id'   => 7,
				'type' => 'post',
				'data' => [ '_yoast_wpseo_title' => 'Hello' ],
			],
		];

		[ $ctrl ] = $this->makeController( $posts );
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $res );
		$data = $res->get_data();
		$this->assertSame( 'yoast', $data['source'] );
		$this->assertSame( 'skip', $data['mode'] );
		$this->assertSame( 1, $data['totals']['posts'] );
		$this->assertNotEmpty( $data['rows'] );
		$this->assertSame( 7, $data['rows'][0]['refId'] );
	}

	/**
	 * Test run rejects an unknown source.
	 */
	public function test_run_unknown_source_returns_400(): void {
		[ $ctrl ] = $this->makeController();
		$res      = $ctrl->runChunk(
			$this->makeRequest(
				[
					'source' => 'nope',
					'kind'   => 'posts',
					'mode'   => 'skip',
					'offset' => 0,
					'limit'  => 50,
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $res );
		$this->assertSame( 400, $res->get_error_data()['status'] ?? null );
	}

	/**
	 * Test run rejects an invalid kind.
	 */
	public function test_run_invalid_kind_returns_400(): void {
		[ $ctrl ] = $this->makeController();
		$res      = $ctrl->runChunk(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'kind'   => 'widgets',
					'mode'   => 'skip',
					'offset' => 0,
					'limit'  => 50,
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $res );
		$this->assertSame( 400, $res->get_error_data()['status'] ?? null );
	}

	/**
	 * Test run executes the runner and clamps the limit.
	 */
	public function test_run_executes_runner_and_clamps_limit(): void {
		[ $ctrl, $runner ] = $this->makeController();
		$res               = $ctrl->runChunk(
			$this->makeRequest(
				[
					'source'   => 'yoast',
					'kind'     => 'posts',
					'mode'     => 'overwrite',
					'offset'   => 0,
					'limit'    => 9999,
					'excludes' => [ 'post:9' ],
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $res );
		$data = $res->get_data();
		$this->assertSame( 2, $data['applied'] );
		$this->assertSame( 3, $data['done'] );
		$this->assertSame( 'yoast', $runner->lastArgs['source'] );
		$this->assertSame( 'posts', $runner->lastArgs['kind'] );
		$this->assertSame( 'overwrite', $runner->lastArgs['mode'] );
		$this->assertSame( [ 'post:9' ], $runner->lastArgs['excludes'] );
		$this->assertLessThanOrEqual( 200, $runner->lastArgs['limit'] );
	}
}
