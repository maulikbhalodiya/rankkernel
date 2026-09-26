<?php
/**
 * Instant Indexing log REST controller tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\InstantIndexingOutcomes;
use RankKernel\Modules\InstantIndexing\LogFilters;
use RankKernel\Modules\InstantIndexing\LogQuery;
use RankKernel\Modules\InstantIndexing\LogTable;
use RankKernel\Rest\LogController;

/**
 * Instant Indexing Log Controller Test.
 */
final class RestLogControllerTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Sentinel standing in for the API key, must never reach a response.
	 */
	private const SECRET_KEY = 'rk-live-secret-key-9f3a1c';

	/**
	 * Controller under test.
	 *
	 * @var LogController
	 */
	private LogController $controller;

	/**
	 * Fake database.
	 *
	 * @var InstantIndexingFakeDb
	 */
	private InstantIndexingFakeDb $db;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db = new InstantIndexingFakeDb();

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

		$this->controller = new LogController();

		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( '__' )->alias( static fn( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'current_user_can' )->justReturn( true );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a request double the REST pipeline would hand to the handler.
	 *
	 * @param array<string, mixed> $params Validated, sanitized params.
	 * @return \WP_REST_Request The request double.
	 */
	private function request( array $params = [] ): \WP_REST_Request {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_params' )->andReturn( $params );

		return $request;
	}

	/**
	 * Capture the route the controller registers.
	 *
	 * @return array{0: string, 1: string, 2: array<string, mixed>} The result.
	 */
	private function captureRoute(): array {
		$captured = [];

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $path, array $args ) use ( &$captured ): bool {
				$captured = [ $route_namespace, $path, $args ];

				return true;
			}
		);

		$this->controller->registerRoutes();

		return $captured;
	}

	/**
	 * The registered endpoint args.
	 *
	 * @return array<string, mixed> The result.
	 */
	private function registeredArgs(): array {
		$route = $this->captureRoute();

		return $route[2]['args'];
	}

	/**
	 * Seed one log row with a deterministic URL.
	 *
	 * @param int    $code    Status code.
	 * @param string $source  Source value.
	 * @param string $message Message value.
	 * @param string $created UTC timestamp.
	 * @param string $url     URL, a slug is derived when empty.
	 * @return int Assigned id.
	 */
	private function seed( int $code = 200, string $source = 'manual', string $message = 'Accepted.', string $created = '', string $url = '' ): int {
		$id = $this->db->nextId;

		return $this->db->seed(
			[
				'url'     => '' !== $url ? $url : 'https://example.com/seed-' . $id,
				'code'    => $code,
				'source'  => $source,
				'message' => $message,
				'created' => '' !== $created ? $created : gmdate( 'Y-m-d H:i:s', 1767225600 + $id ),
			]
		);
	}

	/**
	 * Read the response data for one request.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return array<string, mixed> The response data.
	 */
	private function getData( array $params = [] ): array {
		return (array) $this->controller->getLog( $this->request( $params ) )->get_data();
	}

	/**
	 * Test the route is registered on the plugin namespace with GET.
	 */
	public function test_route_is_registered_on_the_plugin_namespace_with_get(): void {
		$route = $this->captureRoute();

		self::assertSame( 'rankkernel/v1', $route[0] );
		self::assertSame( '/instant-indexing/log', $route[1] );
		self::assertSame( 'GET', $route[2]['methods'] );
		self::assertSame( [ $this->controller, 'getLog' ], $route[2]['callback'] );
		self::assertSame( [ $this->controller, 'checkPermission' ], $route[2]['permission_callback'] );
		self::assertArrayHasKey( 'args', $route[2] );
	}

	/**
	 * Test an administrator passes the capability gate.
	 */
	public function test_permission_callback_allows_an_administrator(): void {
		$checked = [];

		Functions\when( 'current_user_can' )->alias(
			static function ( string $capability ) use ( &$checked ): bool {
				$checked[] = $capability;

				return true;
			}
		);

		self::assertTrue( $this->controller->checkPermission() );
		self::assertSame( [ 'manage_options' ], $checked, 'the gate must be the manage_options capability' );
	}

	/**
	 * Test a user without manage_options is refused with a 403.
	 */
	public function test_permission_callback_denies_a_user_without_manage_options(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->controller->checkPermission();

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'rest_forbidden', $result->get_error_code() );
		self::assertSame( 403, $result->get_error_data()['status'] ?? null );
	}

	/**
	 * Test every accepted parameter is declared in the args schema.
	 */
	public function test_args_schema_declares_every_accepted_parameter(): void {
		$args = $this->registeredArgs();

		foreach ( [ 's', 'rk_source', 'rk_status', 'rk_paged', 'rk_per_page' ] as $key ) {
			self::assertArrayHasKey( $key, $args, "the {$key} argument must be declared" );
		}

		self::assertSame( 'string', $args['s']['type'] );
		self::assertSame( 'string', $args['rk_source']['type'] );
		self::assertSame( 'string', $args['rk_status']['type'] );
		self::assertSame( 'integer', $args['rk_paged']['type'] );
		self::assertSame( 'integer', $args['rk_per_page']['type'] );
	}

	/**
	 * Test the string args keep type and enum validation before sanitizing.
	 */
	public function test_string_args_validate_before_sanitizing(): void {
		$args = $this->registeredArgs();

		foreach ( [ 's', 'rk_source', 'rk_status' ] as $key ) {
			self::assertSame( 'sanitize_text_field', $args[ $key ]['sanitize_callback'], "the {$key} arg must sanitize" );
			self::assertSame(
				'rest_validate_request_arg',
				$args[ $key ]['validate_callback'] ?? null,
				"the {$key} arg sanitizes without schema validation"
			);
		}
	}

	/**
	 * Test the integer args keep framework validation and bounded values.
	 */
	public function test_integer_args_keep_their_bounds(): void {
		$args = $this->registeredArgs();

		self::assertSame( 1, $args['rk_paged']['minimum'] );
		self::assertSame( 1, $args['rk_per_page']['minimum'] );
		self::assertSame( LogFilters::MAX_PER_PAGE, $args['rk_per_page']['maximum'] );
		self::assertSame( 200, LogFilters::MAX_PER_PAGE );

		// No custom sanitizer and no custom validator, so core's default
		// rest_parse_request_arg validates and sanitizes these integers.
		foreach ( [ 'rk_paged', 'rk_per_page' ] as $key ) {
			self::assertArrayNotHasKey( 'sanitize_callback', $args[ $key ] );
			self::assertArrayNotHasKey( 'validate_callback', $args[ $key ] );
		}
	}

	/**
	 * Test the enums reject unknown status and source values.
	 */
	public function test_unknown_status_and_source_values_are_rejected_by_the_enum(): void {
		$args = $this->registeredArgs();

		self::assertSame( LogFilters::statuses(), $args['rk_status']['enum'] );
		self::assertSame( LogFilters::SOURCES, $args['rk_source']['enum'] );
		self::assertSame( 'rest_validate_request_arg', $args['rk_status']['validate_callback'] );
		self::assertSame( 'rest_validate_request_arg', $args['rk_source']['validate_callback'] );

		self::assertContains( LogFilters::STATUS_ALL, $args['rk_status']['enum'] );
		self::assertContains( InstantIndexingOutcomes::CATEGORY_ACCEPTED, $args['rk_status']['enum'] );
		self::assertContains( InstantIndexingOutcomes::CATEGORY_PENDING, $args['rk_status']['enum'] );
		self::assertContains( InstantIndexingOutcomes::CATEGORY_REJECTED, $args['rk_status']['enum'] );
		self::assertContains( InstantIndexingOutcomes::CATEGORY_LIMITED, $args['rk_status']['enum'] );

		// The retry category has a count but no tab, so it is not a filter
		// value: the page normalizes it to all and the schema rejects it.
		foreach ( [ 'retry', 'bogus', 'ALL' ] as $status ) {
			self::assertNotContains( $status, $args['rk_status']['enum'], "the {$status} status must not be accepted" );
		}

		self::assertContains( LogFilters::SOURCE_ALL, $args['rk_source']['enum'] );
		self::assertContains( 'auto', $args['rk_source']['enum'] );
		self::assertContains( 'manual', $args['rk_source']['enum'] );

		foreach ( [ 'robot', 'cron', 'AUTO' ] as $source ) {
			self::assertNotContains( $source, $args['rk_source']['enum'], "the {$source} source must not be accepted" );
		}
	}

	/**
	 * Test the response carries exactly the seven expected keys.
	 */
	public function test_response_contains_exactly_the_expected_keys(): void {
		$this->seed( 200, 'manual', 'Accepted.' );
		$this->seed( 400, 'auto', 'Rejected.' );
		$this->seed( 429, 'manual', 'Rate limited.' );

		$data = $this->getData();

		self::assertSame(
			[ 'rows', 'page', 'perPage', 'filteredTotal', 'total', 'statusCounts', 'sourceCounts' ],
			array_keys( $data )
		);

		self::assertCount( 3, $data['rows'] );

		foreach ( $data['rows'] as $row ) {
			self::assertSame( [ 'url', 'host', 'code', 'source', 'time', 'message' ], array_keys( $row ) );
			self::assertIsString( $row['url'] );
			self::assertIsString( $row['host'] );
			self::assertIsInt( $row['code'] );
			self::assertIsString( $row['source'] );
			self::assertIsString( $row['time'] );
			self::assertIsString( $row['message'] );
		}

		self::assertIsInt( $data['page'] );
		self::assertIsInt( $data['perPage'] );
		self::assertIsInt( $data['filteredTotal'] );
		self::assertIsInt( $data['total'] );
		self::assertSame(
			[ 'all', 'accepted', 'pending', 'rejected', 'limited', 'retry' ],
			array_keys( $data['statusCounts'] )
		);
	}

	/**
	 * Test the default request reports page one with the default page size.
	 */
	public function test_default_response_reports_page_one_and_the_default_page_size(): void {
		$this->seed( 200 );

		$data = $this->getData();

		self::assertSame( 1, $data['page'] );
		self::assertSame( LogQuery::PER_PAGE, $data['perPage'] );
		self::assertSame( 1, $data['filteredTotal'] );
		self::assertSame( 1, $data['total'] );
	}

	/**
	 * Test rows and counts are byte for byte the shared query layer output.
	 *
	 * The direct LogQuery call receives the same normalized inputs, so this
	 * fails the moment the controller starts doing its own filtering,
	 * counting, ordering or paging instead of reusing the shared layer.
	 * The query layer carries the row id for the admin retry surfaces and
	 * the REST route withholds it, so the comparison runs over the public
	 * fields, and the filtering, ordering and paging must still match.
	 */
	public function test_rows_and_counts_match_the_shared_query_layer(): void {
		$this->seed( 200, 'manual', 'Accepted alpha.', '', 'https://example.com/alpha' );
		$this->seed( 400, 'manual', 'Rejected alpha.', '', 'https://example.com/alpha-two' );
		$this->seed( 403, 'manual', 'Forbidden alpha.', '', 'https://example.com/alpha-five' );
		$this->seed( 400, 'auto', 'Rejected beta.', '', 'https://example.com/beta' );
		$this->seed( 429, 'manual', 'Limited alpha.', '', 'https://example.com/alpha-three' );
		$this->seed( 200, 'auto', 'Accepted beta.', '', 'https://example.com/beta-two' );
		$this->seed( 503, 'auto', 'Retry later.', '', 'https://example.com/alpha-four' );

		$params = [
			's'           => 'alpha',
			'rk_source'   => 'manual',
			'rk_status'   => InstantIndexingOutcomes::CATEGORY_REJECTED,
			'rk_paged'    => 1,
			'rk_per_page' => 2,
		];

		$data   = $this->getData( $params );
		$direct = LogQuery::fromInput( $params, LogQuery::PER_PAGE );

		$expected = array_map(
			static function ( array $row ): array {
				unset( $row['id'] );

				return $row;
			},
			$direct->rows()
		);

		self::assertSame( $expected, $data['rows'], 'the REST rows must match the shared layer minus the withheld row id' );
		self::assertSame( $direct->filters()->page(), $data['page'] );
		self::assertSame( $direct->filters()->perPage(), $data['perPage'] );
		self::assertSame( $direct->filteredTotal(), $data['filteredTotal'] );
		self::assertSame( $direct->total(), $data['total'] );
		self::assertSame( $direct->statusCounts(), $data['statusCounts'] );
		self::assertSame( $direct->sourceCounts(), $data['sourceCounts'] );

		self::assertSame( 2, $data['filteredTotal'], 'the same filter must narrow both calls identically' );
		self::assertSame( 7, $data['total'], 'the unfiltered total must cover the whole table' );
	}

	/**
	 * Test totals and counts cover the full history, not a page window.
	 */
	public function test_totals_and_counts_cover_the_full_history(): void {
		for ( $i = 0; $i < 250; $i++ ) {
			$this->seed( 0 === $i % 2 ? 200 : 400, 0 === $i % 2 ? 'auto' : 'manual', 'Row.' );
		}

		$data = $this->getData();

		self::assertCount( LogQuery::PER_PAGE, $data['rows'], 'one page comes back, not the whole table' );
		self::assertSame( 250, $data['total'] );
		self::assertSame( 250, $data['filteredTotal'] );
		self::assertSame( 250, $data['statusCounts']['all'] );
		self::assertSame( 125, $data['statusCounts']['accepted'] );
		self::assertSame( 125, $data['statusCounts']['rejected'] );
		self::assertSame( 0, $data['statusCounts']['pending'] );
		self::assertSame( 0, $data['statusCounts']['limited'] );
		self::assertSame( 0, $data['statusCounts']['retry'] );

		$sources = $data['sourceCounts'];
		ksort( $sources );

		self::assertSame(
			[
				'auto'   => 125,
				'manual' => 125,
			],
			$sources
		);
	}

	/**
	 * Test an unbounded page size request cannot produce an unbounded query.
	 */
	public function test_unbounded_page_size_is_clamped(): void {
		for ( $i = 0; $i < 250; $i++ ) {
			$this->seed( 200, 'manual', 'Row.' );
		}

		$enormous = $this->getData( [ 'rk_per_page' => 100000 ] );

		self::assertSame( LogFilters::MAX_PER_PAGE, $enormous['perPage'], 'a huge page size must clamp to the cap' );
		self::assertCount( LogFilters::MAX_PER_PAGE, $enormous['rows'] );
		self::assertSame( 250, $enormous['filteredTotal'], 'the totals stay unaffected by the clamp' );

		self::assertSame( 1, $this->getData( [ 'rk_per_page' => 0 ] )['perPage'] );
		self::assertSame( 1, $this->getData( [ 'rk_per_page' => -5 ] )['perPage'] );
		self::assertSame( 1, $this->getData( [ 'rk_paged' => 0 ] )['page'], 'a page below one clamps to one' );
	}

	/**
	 * Test the page parameter pages through the same shared rows.
	 */
	public function test_page_parameter_pages_through_the_same_rows(): void {
		for ( $i = 0; $i < 25; $i++ ) {
			$this->seed( 200, 'manual', 'Accepted.', gmdate( 'Y-m-d H:i:s', 1767225600 + $i ) );
		}

		$second = $this->getData(
			[
				'rk_per_page' => 10,
				'rk_paged'    => 2,
			]
		);

		self::assertSame( 2, $second['page'] );
		self::assertSame( 10, $second['perPage'] );
		self::assertSame( 10, count( $second['rows'] ) );
		self::assertSame( 'https://example.com/seed-15', $second['rows'][0]['url'] );
		self::assertSame( 25, $second['filteredTotal'] );

		$past = $this->getData(
			[
				'rk_per_page' => 10,
				'rk_paged'    => 99,
			]
		);

		self::assertSame( [], $past['rows'], 'a page past the end returns no rows' );
		self::assertSame( 25, $past['filteredTotal'], 'the totals stay correct past the end' );
	}

	/**
	 * Test the response can never leak the API key or a setting.
	 */
	public function test_response_never_leaks_the_api_key_or_a_setting(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'rankkernel_indexnow_key'  => self::SECRET_KEY,
				'rankkernel_indexnow_file' => '/var/www/private/rankkernel-key.txt',
				'rankkernel_indexnow_auto' => true,
			]
		);

		$this->seed( 200, 'manual', 'Accepted.' );

		$data = $this->getData();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$json = (string) json_encode( $data );

		self::assertStringNotContainsString( self::SECRET_KEY, $json, 'the API key must never appear in the response' );
		self::assertStringNotContainsString( '/var/www/private/rankkernel-key.txt', $json );
		self::assertStringNotContainsString( 'rankkernel_indexnow', $json );

		self::assertSame(
			[ 'rows', 'page', 'perPage', 'filteredTotal', 'total', 'statusCounts', 'sourceCounts' ],
			array_keys( $data )
		);

		self::assertArrayNotHasKey( 'id', $data['rows'][0], 'the raw row id stays out of the response' );
		self::assertArrayNotHasKey( 'key', $data );
		self::assertArrayNotHasKey( 'settings', $data );
	}

	/**
	 * Test a missing table fails open with empty rows and zero counts.
	 */
	public function test_missing_table_returns_empty_rows_and_zero_counts(): void {
		$this->seed( 200 );
		$this->db->tableExists = false;
		LogTable::resetCache();

		$data = $this->getData();

		self::assertSame( [], $data['rows'] );
		self::assertSame( 0, $data['total'] );
		self::assertSame( 0, $data['filteredTotal'] );
		self::assertSame( 0, $data['statusCounts']['all'] );
		self::assertSame( [], $data['sourceCounts'] );
	}
}
