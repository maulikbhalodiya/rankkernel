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
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v, string $d = '' ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
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
