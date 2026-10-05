<?php
/**
 * SettingsController REST schema and sanitization tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Rest\SettingsController;
use RankKernel\Settings\SettingsStore;

/**
 * Settings Controller Test.
 */
final class SettingsControllerTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Option array captured from the last store write.
	 *
	 * @var array<string, mixed>
	 */
	private array $written = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->written = [];

		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Capture the endpoint args the controller registers for the POST route.
	 *
	 * @return array<string, mixed> The POST endpoint args.
	 */
	private function captureEndpointArgs(): array {
		$captured = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $route_path, array $args ) use ( &$captured ): bool {
				$captured = $args;
				return true;
			}
		);

		( new SettingsController( new SettingsStore() ) )->registerRoutes();

		foreach ( $captured as $endpoint ) {
			if ( 'POST' === $endpoint['methods'] ) {
				return $endpoint['args'];
			}
		}

		self::fail( 'The POST settings endpoint was not registered.' );
	}

	/**
	 * Build a request double whose params went through the REST pipeline.
	 *
	 * @param array<string, mixed> $params Params the pipeline would expose.
	 * @return \WP_REST_Request The request double.
	 */
	private function makeRequest( array $params ): \WP_REST_Request {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldNotReceive( 'get_json_params' );
		$request->shouldReceive( 'get_params' )->andReturn( $params );

		return $request;
	}

	/**
	 * Expect exactly one store write and capture the merged option array.
	 */
	private function expectStoreWrite(): void {
		Functions\expect( 'update_option' )
			->once()
			->with(
				SettingsStore::OPTION,
				Mockery::on(
					function ( array $data ): bool {
						$this->written = $data;
						return true;
					}
				)
			)
			->andReturn( true );
	}

	/**
	 * Test the endpoint args declare every store default key.
	 */
	public function test_endpoint_args_cover_every_store_default_key(): void {
		$args = $this->captureEndpointArgs();

		foreach ( array_keys( SettingsStore::defaults() ) as $key ) {
			self::assertArrayHasKey( $key, $args, "The endpoint args miss the {$key} setting." );
		}
	}

	/**
	 * Test site_represents keeps its enum and validates before sanitizing.
	 */
	public function test_site_represents_keeps_enum_and_validation(): void {
		$args = $this->captureEndpointArgs();
		$arg  = $args['site_represents'];

		self::assertSame( 'string', $arg['type'] );
		self::assertSame( [ 'organization', 'person' ], $arg['enum'] );
		self::assertSame( 'sanitize_text_field', $arg['sanitize_callback'] );
		self::assertSame( 'rest_validate_request_arg', $arg['validate_callback'] );
	}

	/**
	 * Test every arg with a custom sanitizer declares the schema validator.
	 */
	public function test_custom_sanitizers_keep_schema_validation(): void {
		$args = $this->captureEndpointArgs();

		foreach ( $args as $key => $arg ) {
			if ( isset( $arg['sanitize_callback'] ) ) {
				self::assertSame(
					'rest_validate_request_arg',
					$arg['validate_callback'] ?? null,
					"The {$key} arg sanitizes without validating."
				);
			}
		}
	}

	/**
	 * Test org_sameas validates items through the schema, not a nested callback.
	 */
	public function test_org_sameas_uses_uri_item_schema(): void {
		$args   = $this->captureEndpointArgs();
		$sameas = $args['org_sameas'];

		self::assertSame( 'array', $sameas['type'] );
		self::assertSame( 'string', $sameas['items']['type'] );
		self::assertSame( 'uri', $sameas['items']['format'] );
		self::assertSame( 'esc_url_raw', $sameas['items']['sanitize_callback'] );
		self::assertSame( 'rest_validate_request_arg', $sameas['validate_callback'] );
		self::assertArrayNotHasKey( 'sanitize_callback', $sameas );
	}

	/**
	 * Test boolean and purge args stay schema validated by the framework.
	 */
	public function test_boolean_args_stay_schema_validated(): void {
		$args = $this->captureEndpointArgs();

		foreach ( [ 'website_search_action', 'schema_breadcrumbs', 'schema_author' ] as $key ) {
			self::assertSame( 'boolean', $args[ $key ]['type'] );
			// No custom sanitizer, so core falls back to rest_parse_request_arg
			// and validates the scalar representations it always accepted.
			self::assertArrayNotHasKey( 'sanitize_callback', $args[ $key ] );
		}

		self::assertSame( [ 'boolean', 'null' ], $args['purge_on_uninstall']['type'] );
	}

	/**
	 * Test the handler consumes the validated and sanitized params.
	 */
	public function test_update_settings_uses_validated_and_sanitized_params(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->expectStoreWrite();

		$request = $this->makeRequest(
			[
				'title_template' => 'Sanitized title',
				'org_sameas'     => [ 'https://good.example' ],
			]
		);

		$response = ( new SettingsController( new SettingsStore() ) )->updateSettings( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'Sanitized title', $this->written['title_template'] );
		self::assertSame( [ 'https://good.example' ], $this->written['org_sameas'] );
	}

	/**
	 * Test a missing payload returns the no settings error.
	 */
	public function test_update_settings_returns_400_without_params(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'update_option' )->never();

		$result = ( new SettingsController( new SettingsStore() ) )->updateSettings( $this->makeRequest( [] ) );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'rankkernel_invalid_params', $result->get_error_code() );
		self::assertSame( 400, $result->get_error_data()['status'] ?? null );
	}

	/**
	 * Test scalar boolean representations still normalize instead of breaking.
	 */
	public function test_update_settings_normalizes_scalar_booleans(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->expectStoreWrite();

		$request = $this->makeRequest(
			[
				'website_search_action' => '1',
				'schema_breadcrumbs'    => '0',
				'schema_author'         => 'false',
				'purge_on_uninstall'    => '1',
			]
		);

		$response = ( new SettingsController( new SettingsStore() ) )->updateSettings( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		self::assertTrue( $this->written['website_search_action'] );
		self::assertFalse( $this->written['schema_breadcrumbs'] );
		self::assertFalse( $this->written['schema_author'] );
		self::assertTrue( $this->written['purge_on_uninstall'] );
	}

	/**
	 * Test an invalid purge value is rejected before the store write.
	 */
	public function test_update_settings_rejects_invalid_purge_value(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'update_option' )->never();

		$request = $this->makeRequest( [ 'purge_on_uninstall' => 'notabool!!!' ] );
		$result  = ( new SettingsController( new SettingsStore() ) )->updateSettings( $request );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'rest_invalid_param', $result->get_error_code() );
		self::assertSame( 400, $result->get_error_data()['status'] ?? null );
	}

	/**
	 * Test purge_on_uninstall accepts null as its stored default.
	 */
	public function test_update_settings_accepts_null_purge_value(): void {
		Functions\when( 'get_option' )->justReturn( [ 'purge_on_uninstall' => true ] );

		$this->expectStoreWrite();

		$request  = $this->makeRequest( [ 'purge_on_uninstall' => null ] );
		$response = ( new SettingsController( new SettingsStore() ) )->updateSettings( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		self::assertNull( $this->written['purge_on_uninstall'] );
	}

	/**
	 * Test org_sameas drops the empty entries a URL sanitizer produces.
	 */
	public function test_update_settings_sanitizes_org_sameas_entries(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->expectStoreWrite();

		$request = $this->makeRequest(
			[
				'org_sameas' => [ 'https://good.example', '', 'https://second.example' ],
			]
		);

		$response = ( new SettingsController( new SettingsStore() ) )->updateSettings( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		self::assertSame( [ 'https://good.example', 'https://second.example' ], $this->written['org_sameas'] );
	}

	/**
	 * Test dynamic schema_default keys stay store validated.
	 */
	public function test_update_settings_forwards_dynamic_schema_default_keys(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->expectStoreWrite();

		$request = $this->makeRequest(
			[
				'schema_default_post' => 'Article',
				'schema_default_page' => '',
			]
		);

		$response = ( new SettingsController( new SettingsStore() ) )->updateSettings( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		self::assertSame( 'Article', $this->written['schema_default_post'] );
		self::assertSame( '', $this->written['schema_default_page'] );
	}

	/**
	 * Test an unsupported dynamic schema_default value is dropped by the store.
	 */
	public function test_update_settings_rejects_unsupported_schema_default_value(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'update_option' )->never();

		$request = $this->makeRequest( [ 'schema_default_post' => 'NotAType' ] );
		$result  = ( new SettingsController( new SettingsStore() ) )->updateSettings( $request );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'rankkernel_no_update', $result->get_error_code() );
	}
}
