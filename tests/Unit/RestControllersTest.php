<?php
/**
 * REST boolean strictness tests (F4).
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Rest\ModulesController;
use RankKernel\Rest\SettingsController;
use RankKernel\Settings\SettingsStore;

/**
 * Rest Controllers Test.
 */
final class RestControllersTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Number of stubbed rewrite flushes.
	 *
	 * @var int
	 */
	private int $flushCount = 0;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->flushCount = 0;

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v, string $d = '' ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'flush_rewrite_rules' )->alias(
			function (): void {
				++$this->flushCount;
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Make Modules Request.
	 *
	 * @param mixed $enabled Enabled.
	 * @return \WP_REST_Request The result.
	 */
	private function makeModulesRequest( mixed $enabled ): \WP_REST_Request {
		$req = Mockery::mock( \WP_REST_Request::class );
		$req->shouldReceive( 'get_param' )->with( 'id' )->andReturn( 'metadata' );
		$req->shouldReceive( 'get_json_params' )->andReturn( [ 'enabled' => $enabled ] );
		$req->shouldReceive( 'get_params' )->andReturn( [ 'enabled' => $enabled ] );
		return $req;
	}

	/**
	 * Test modules controller boolean false accepted.
	 */
	public function test_modules_controller_boolean_false_accepted(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );

		$ctrl = new ModulesController();
		$req  = $this->makeModulesRequest( false );

		$res = $ctrl->toggleModule( $req );

		$this->assertInstanceOf( \WP_REST_Response::class, $res );
		$this->assertSame( 200, $res->get_status() );
	}

	/**
	 * Test modules controller invalid string returns 400.
	 */
	public function test_modules_controller_invalid_string_returns_400(): void {
		// Strict: invalid string must 400. "false" string normalizes to false via filter_var (documented choice).
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );

		$ctrl = new ModulesController();
		$req  = $this->makeModulesRequest( 'notabool!!!' );

		$res = $ctrl->toggleModule( $req );

		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'rest_invalid_param', $res->get_error_code() );
		$this->assertSame( 400, $res->get_error_data()['status'] ?? null );
	}

	/**
	 * Test modules controller string false normalizes to false.
	 */
	public function test_modules_controller_string_false_normalizes_to_false(): void {
		// We normalize "false" string to boolean false (filter_var), documented.
		Functions\when( 'get_option' )->justReturn( [ 'metadata' ] );
		Functions\when( 'update_option' )->justReturn( true );

		$ctrl = new ModulesController();
		$req  = $this->makeModulesRequest( 'false' );

		$res = $ctrl->toggleModule( $req );

		$this->assertInstanceOf( \WP_REST_Response::class, $res );
		// After toggling off, metadata should not be in list.
		$data = $res->get_data();
		$this->assertNotContains( 'metadata', $data['modules'] ?? [] );
	}

	/**
	 * Test modules controller boolean true accepted.
	 */
	public function test_modules_controller_boolean_true_accepted(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );

		$ctrl = new ModulesController();
		$req  = $this->makeModulesRequest( true );

		$res = $ctrl->toggleModule( $req );

		$this->assertInstanceOf( \WP_REST_Response::class, $res );
	}

	/**
	 * Test a failed module toggle write returns a storage error and skips the flush.
	 *
	 * The update_option() function returns false both for an unchanged
	 * value and for a failed write. The list here does change, so false
	 * means the toggle was not persisted: the endpoint must not answer 200
	 * with the toggled list and must not flush rewrite rules for a state
	 * that was not stored.
	 */
	public function test_modules_controller_failed_write_returns_storage_error(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'update_option' )->once()->andReturn( false );

		$ctrl = new ModulesController();
		$req  = $this->makeModulesRequest( true );

		$res = $ctrl->toggleModule( $req );

		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'rankkernel_storage_failed', $res->get_error_code() );
		$this->assertSame( 500, $res->get_error_data()['status'] ?? null );
		$this->assertSame( 0, $this->flushCount, 'a failed write must not flush rewrite rules' );
	}

	/**
	 * Test an unchanged toggle is a success without a write or an error.
	 *
	 * This is the other half of the update_option() false ambiguity: the
	 * module is already enabled, so update_option() would report false for
	 * the identical list. It must be read as a no-op success, not as a
	 * storage failure, and must not be written at all.
	 */
	public function test_modules_controller_unchanged_toggle_succeeds_without_write(): void {
		Functions\when( 'get_option' )->justReturn( [ 'metadata' ] );
		Functions\expect( 'update_option' )->never();

		$ctrl = new ModulesController();
		$req  = $this->makeModulesRequest( true );

		$res = $ctrl->toggleModule( $req );

		$this->assertInstanceOf( \WP_REST_Response::class, $res );
		$this->assertSame( 200, $res->get_status() );
		$this->assertContains( 'metadata', $res->get_data()['modules'] ?? [] );
		$this->assertSame( 1, $this->flushCount );
	}

	/**
	 * Test a planned module id is rejected by toggleModule with a clear error.
	 */
	public function test_modules_controller_planned_module_rejected(): void {
		Functions\expect( 'update_option' )->never();

		$ctrl = new ModulesController();
		$req  = Mockery::mock( \WP_REST_Request::class );
		$req->shouldReceive( 'get_param' )->with( 'id' )->andReturn( 'image-seo' );
		$req->shouldReceive( 'get_json_params' )->andReturn( [ 'enabled' => true ] );
		$req->shouldReceive( 'get_params' )->andReturn( [ 'enabled' => true ] );

		$res = $ctrl->toggleModule( $req );

		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'rankkernel_module_planned', $res->get_error_code() );
		$this->assertStringContainsString( 'planned', strtolower( $res->get_error_message() ) );
		$this->assertSame( 400, $res->get_error_data()['status'] ?? null );

		$req2 = Mockery::mock( \WP_REST_Request::class );
		$req2->shouldReceive( 'get_param' )->with( 'id' )->andReturn( 'importer' );
		$req2->shouldReceive( 'get_json_params' )->andReturn( [ 'enabled' => true ] );
		$req2->shouldReceive( 'get_params' )->andReturn( [ 'enabled' => true ] );

		$res2 = $ctrl->toggleModule( $req2 );

		$this->assertInstanceOf( \WP_Error::class, $res2 );
		$this->assertSame( 'rankkernel_module_planned', $res2->get_error_code() );
	}

	/**
	 * Test modules controller register routes schema.
	 */
	public function test_modules_controller_register_routes(): void {
		$registered = [];

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $restNamespace, string $route, array $args ) use ( &$registered ): bool {
				$registered = [
					'namespace' => $restNamespace,
					'route'     => $route,
					'args'      => $args,
				];
				return true;
			}
		);

		$ctrl = new ModulesController();
		$ctrl->registerRoutes();

		$this->assertSame( 'rankkernel/v1', $registered['namespace'] );
		$this->assertSame( '/modules/(?P<id>[a-z0-9-]+)', $registered['route'] );

		$args = $registered['args']['args'] ?? [];
		$this->assertArrayHasKey( 'id', $args );
		$this->assertTrue( $args['id']['required'] );
		$this->assertSame( 'string', $args['id']['type'] );

		$this->assertArrayHasKey( 'enabled', $args );
		$this->assertTrue( $args['enabled']['required'] );
		$this->assertSame( 'boolean', $args['enabled']['type'] );
		$this->assertSame( 'rest_sanitize_request_arg', $args['enabled']['sanitize_callback'] );
		$this->assertSame( 'rest_validate_request_arg', $args['enabled']['validate_callback'] );
	}

	/**
	 * Test settings controller purge boolean false accepted.
	 */
	public function test_settings_controller_purge_boolean_false_accepted(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'update_option' )->justReturn( true );

		$store = new SettingsStore();
		$ctrl  = new SettingsController( $store );

		$req = Mockery::mock( \WP_REST_Request::class );
		$req->shouldReceive( 'get_json_params' )->andReturn( [ 'purge_on_uninstall' => false ] );
		$req->shouldReceive( 'get_params' )->andReturn( [ 'purge_on_uninstall' => false ] );

		$res = $ctrl->updateSettings( $req );

		$this->assertInstanceOf( \WP_REST_Response::class, $res );
	}

	/**
	 * Test settings controller purge string false invalid.
	 */
	public function test_settings_controller_purge_string_false_invalid(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$store = new SettingsStore();
		$ctrl  = new SettingsController( $store );

		$req = Mockery::mock( \WP_REST_Request::class );
		$req->shouldReceive( 'get_json_params' )->andReturn( [ 'purge_on_uninstall' => 'notabool!!!' ] );
		$req->shouldReceive( 'get_params' )->andReturn( [ 'purge_on_uninstall' => 'notabool!!!' ] );

		$res = $ctrl->updateSettings( $req );

		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'rest_invalid_param', $res->get_error_code() );
	}
}
