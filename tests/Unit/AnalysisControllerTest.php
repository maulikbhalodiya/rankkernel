<?php
/**
 * REST content analysis controller tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Rest\AnalysisController;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Analysis Controller Test.
 */
final class AnalysisControllerTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'number_format_i18n' )->alias( static fn ( float $number, int $decimals = 0 ): string => number_format( $number, $decimals ) );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				if ( PHP_URL_HOST !== $component ) {
					return false;
				}

				return preg_match( '~^[a-z][a-z0-9+.-]*://([^/?#]+)~i', $url, $matches )
					? strtolower( $matches[1] )
					: '';
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
	 * Build a request.
	 *
	 * @param array<string, mixed> $params Params.
	 * @return WP_REST_Request The result.
	 */
	private function request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request();

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	/**
	 * Test permission is refused without the capability.
	 */
	public function test_permission_refused_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = ( new AnalysisController() )->checkPermission( $this->request( [ 'post_id' => 5 ] ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test permission is granted with the capability.
	 */
	public function test_permission_granted_with_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->assertTrue( ( new AnalysisController() )->checkPermission( $this->request( [ 'post_id' => 5 ] ) ) );
	}

	/**
	 * Test permission is refused when the post id is absent or zero.
	 *
	 * The capability grant is deliberately in place, so only the post id guard
	 * can produce the refusal.
	 */
	public function test_permission_refused_without_a_post_id(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$controller = new AnalysisController();

		$missing = $controller->checkPermission( $this->request( [] ) );
		$zero    = $controller->checkPermission( $this->request( [ 'post_id' => 0 ] ) );

		$this->assertInstanceOf( WP_Error::class, $missing );
		$this->assertSame( 'rest_forbidden', $missing->get_error_code() );
		$this->assertInstanceOf( WP_Error::class, $zero );
		$this->assertSame( 'rest_forbidden', $zero->get_error_code() );
	}

	/**
	 * Test permission checks the edit capability for the requested post.
	 */
	public function test_permission_checks_the_capability_for_the_post(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'edit_post', 5 )->andReturn( true );

		$this->assertTrue( ( new AnalysisController() )->checkPermission( $this->request( [ 'post_id' => 5 ] ) ) );
	}

	/**
	 * Test a missing post returns a not found error.
	 */
	public function test_unknown_post_returns_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$result = ( new AnalysisController() )->analyze( $this->request( [ 'post_id' => 5 ] ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rankkernel_unknown_post', $result->get_error_code() );
	}

	/**
	 * Test a missing post id returns a not found error rather than a report.
	 */
	public function test_missing_post_id_returns_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$result = ( new AnalysisController() )->analyze(
			$this->request(
				[
					'content'  => '<p>Red apples are best in autumn.</p>',
					'keywords' => [ 'red apples' ],
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rankkernel_unknown_post', $result->get_error_code() );
	}

	/**
	 * Test the report comes back with a score, checks and keywords.
	 */
	public function test_analyze_returns_the_report(): void {
		Functions\when( 'get_post' )->justReturn( new WP_Post() );

		$result = ( new AnalysisController() )->analyze(
			$this->request(
				[
					'post_id'     => 5,
					'title'       => 'Red apples: a complete guide',
					'description' => 'All about red apples.',
					'slug'        => 'red-apples',
					'content'     => '<p>Red apples are best in autumn. Red apples keep well.</p>',
					'keywords'    => [ 'red apples' ],
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );

		$data = $result->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'score', $data );
		$this->assertArrayHasKey( 'band', $data );
		$this->assertArrayHasKey( 'checks', $data );
		$this->assertArrayHasKey( 'keywords', $data );
		$this->assertSame( 'red apples', $data['keywords'][0]['keyword'] );
	}

	/**
	 * Test an empty keyword list returns an empty report rather than an error.
	 */
	public function test_analyze_without_keywords_returns_an_empty_report(): void {
		Functions\when( 'get_post' )->justReturn( new WP_Post() );

		$result = ( new AnalysisController() )->analyze(
			$this->request(
				[
					'post_id'  => 5,
					'content'  => '<p>Some content here.</p>',
					'keywords' => [],
				]
			)
		);

		$data = $result->get_data();

		$this->assertIsArray( $data );
		$this->assertSame( 0, $data['score'] );
		$this->assertSame( [], $data['checks'] );
	}

	/**
	 * Test the route wires the post method, its handlers and the post id args.
	 */
	public function test_route_wires_the_method_handlers_and_post_id_args(): void {
		$captured = [];

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $restNamespace, string $route, array $args ) use ( &$captured ): bool {
				$captured = [
					'namespace' => $restNamespace,
					'route'     => $route,
					'args'      => $args,
				];

				return true;
			}
		);

		$controller = new AnalysisController();
		$controller->registerRoutes();

		$this->assertSame( 'rankkernel/v1', $captured['namespace'] );
		$this->assertSame( '/analysis', $captured['route'] );
		$this->assertSame( 'POST', $captured['args']['methods'] );
		$this->assertSame( [ $controller, 'checkPermission' ], $captured['args']['permission_callback'] );
		$this->assertTrue( $captured['args']['args']['post_id']['required'] );
		$this->assertSame( 'absint', $captured['args']['args']['post_id']['sanitize_callback'] );
	}

	/**
	 * Test the report carries per keyword checks for supporting keywords.
	 */
	public function test_report_carries_per_keyword_checks(): void {
		Functions\when( 'get_post' )->justReturn( new WP_Post() );

		$result = ( new AnalysisController() )->analyze(
			$this->request(
				[
					'post_id'  => 5,
					'content'  => '<p>Red apples are best in autumn. Autumn harvest ideas help.</p>',
					'keywords' => [ 'red apples', 'autumn harvest' ],
				]
			)
		);

		$data = $result->get_data();

		$this->assertArrayHasKey( 'checks', $data['keywords'][1] );
		$this->assertNotEmpty( $data['keywords'][1]['checks'] );
	}
}
