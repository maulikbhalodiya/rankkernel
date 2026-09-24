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
	 * Register the routes and return the captured endpoint args.
	 *
	 * @return array<string, mixed> The registered args.
	 */
	private function registeredRouteArgs(): array {
		$captured = [];

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $restNamespace, string $route, array $args ) use ( &$captured ): bool {
				$captured = $args;
				return true;
			}
		);

		( new AnalysisController() )->registerRoutes();

		return $captured;
	}

	/**
	 * Reproduce sanitize_text_field closely enough for the assertions.
	 *
	 * WordPress removes script and style blocks with their contents, strips
	 * the remaining tags, collapses whitespace and trims the result.
	 *
	 * @param string $text Raw text.
	 * @return string The result.
	 */
	private static function fakeSanitizeTextField( string $text ): string {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = (string) preg_replace( '/<[^>]*>/', '', $text );
		$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Stub the text and array sanitizers the route callbacks use.
	 *
	 * The stubs mirror the WordPress behaviour the endpoint depends on:
	 * sanitize_text_field strips tags and collapses whitespace, and
	 * rest_sanitize_array keeps the framework list normalisation.
	 *
	 * @param callable|null $textSanitizer Optional replacement for sanitize_text_field.
	 */
	private function stubSanitizers( ?callable $textSanitizer = null ): void {
		Functions\when( 'sanitize_text_field' )->alias( $textSanitizer ?? static fn ( string $text ): string => self::fakeSanitizeTextField( $text ) );
		Functions\when( 'rest_sanitize_array' )->alias(
			static function ( mixed $value ): array {
				if ( is_scalar( $value ) ) {
					return (array) preg_split( '/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
				}

				return is_array( $value ) ? array_values( $value ) : [];
			}
		);
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

	/**
	 * Test every endpoint arg wires a sanitize and a validate callback.
	 */
	public function test_route_wires_sanitize_and_validate_callbacks_for_every_arg(): void {
		$args = $this->registeredRouteArgs()['args'];

		$expected = [
			'title'       => 'sanitize_text_field',
			'description' => 'sanitize_text_field',
			'slug'        => 'sanitize_text_field',
			'content'     => 'wp_kses_post',
		];

		foreach ( $expected as $key => $sanitizer ) {
			$this->assertSame( $sanitizer, $args[ $key ]['sanitize_callback'], $key );
			$this->assertSame( 'rest_validate_request_arg', $args[ $key ]['validate_callback'], $key );
		}

		$this->assertSame( [ AnalysisController::class, 'sanitizeKeywords' ], $args['keywords']['sanitize_callback'] );
		$this->assertSame( 'rest_validate_request_arg', $args['keywords']['validate_callback'] );
		$this->assertSame( 'absint', $args['post_id']['sanitize_callback'] );
		$this->assertSame( 'rest_validate_request_arg', $args['post_id']['validate_callback'] );
	}

	/**
	 * Test the item schema carries no sanitizer, because WordPress never runs it.
	 */
	public function test_keyword_items_schema_does_not_carry_a_dead_sanitizer(): void {
		$args = $this->registeredRouteArgs()['args'];

		$this->assertSame( [ 'type' => 'string' ], $args['keywords']['items'] );
	}

	/**
	 * Test the keyword sanitizer runs on every element and keeps the list.
	 */
	public function test_keyword_sanitizer_cleans_each_element_and_preserves_the_list(): void {
		$calls = [];

		$this->stubSanitizers(
			static function ( string $text ) use ( &$calls ): string {
				$calls[] = $text;

				return self::fakeSanitizeTextField( $text );
			}
		);

		$hostile = [ '<b>red apples</b>', "autumn\nharvest", '<script>alert(1)</script>' ];
		$clean   = AnalysisController::sanitizeKeywords( $hostile );

		$this->assertSame( $hostile, $calls );
		$this->assertSame( [ 'red apples', 'autumn harvest', '' ], $clean );
		$this->assertCount( 3, $clean );
	}

	/**
	 * Test a scalar keyword parameter keeps the framework list behaviour.
	 */
	public function test_scalar_keywords_keep_the_framework_list_behaviour(): void {
		$this->stubSanitizers();

		$this->assertSame( [ 'red', 'apples', 'autumn' ], AnalysisController::sanitizeKeywords( 'red apples,autumn' ) );
	}

	/**
	 * Test hostile keyword markup is normalised before the analyzer sees it.
	 */
	public function test_hostile_keywords_reach_the_analyzer_sanitized(): void {
		Functions\when( 'get_post' )->justReturn( new WP_Post() );
		$this->stubSanitizers();

		$args     = $this->registeredRouteArgs()['args'];
		$sanitize = $args['keywords']['sanitize_callback'];
		$hostile  = [ '<b>red apples</b>', '<i>autumn harvest</i>' ];

		// Without the REST layer the analyzer echoes the markup it is handed.
		$raw = ( new AnalysisController() )->analyze(
			$this->request(
				[
					'post_id'  => 5,
					'content'  => '<p>red apples and more.</p>',
					'keywords' => $hostile,
				]
			)
		);
		$this->assertSame( '<b>red apples</b>', $raw->get_data()['keywords'][0]['keyword'] );

		// WordPress runs the top level sanitize_callback, so the analyzer only
		// ever receives the normalised keyword.
		$clean = call_user_func( $sanitize, $hostile );

		$result = ( new AnalysisController() )->analyze(
			$this->request(
				[
					'post_id'  => 5,
					'content'  => '<p>red apples and more.</p>',
					'keywords' => $clean,
				]
			)
		);

		$data = $result->get_data();

		$this->assertSame( 'red apples', $data['keywords'][0]['keyword'] );
		$this->assertSame( 'autumn harvest', $data['keywords'][1]['keyword'] );
	}

	/**
	 * Test the title, description, slug and content sanitizers clean input.
	 */
	public function test_string_sanitizers_are_applied_to_hostile_input(): void {
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $text ): string => self::fakeSanitizeTextField( $text ) );
		Functions\when( 'wp_kses_post' )->alias(
			static function ( string $html ): string {
				return (string) preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
			}
		);

		$args = $this->registeredRouteArgs()['args'];

		$this->assertSame( 'Red apples', call_user_func( $args['title']['sanitize_callback'], '<b>Red apples</b>' ) );
		$this->assertSame( 'All about apples', call_user_func( $args['description']['sanitize_callback'], "<i>All about</i>\napples" ) );
		$this->assertSame( 'red-apples', call_user_func( $args['slug']['sanitize_callback'], '  red-apples  ' ) );
		$this->assertSame( '<p>Keep</p>', call_user_func( $args['content']['sanitize_callback'], '<p>Keep</p><script>alert(1)</script>' ) );
	}
}
