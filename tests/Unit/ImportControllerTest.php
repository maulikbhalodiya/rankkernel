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
		Functions\when( 'get_post' )->justReturn( false );
		Functions\when( 'get_term' )->justReturn( false );
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
	 * Test preview rows carry the live post title plus edit link.
	 */
	public function test_preview_post_row_carries_title_and_edit_link(): void {
		$posts = [
			[
				'id'   => 7,
				'type' => 'post',
				'data' => [ '_yoast_wpseo_title' => 'Hello' ],
			],
		];

		Functions\when( 'get_post' )->alias(
			static function ( int $id ): \WP_Post {
				$post       = new \WP_Post();
				$post->ID   = $id;
				$post->post_title = 'Live Hello';

				return $post;
			}
		);
		Functions\when( 'get_the_title' )->alias( static fn ( $post ): string => $post instanceof \WP_Post ? $post->post_title : '' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_the_title signature.
		Functions\when( 'get_edit_post_link' )->alias( static fn ( int $id ): string => 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_edit_post_link signature.

		[ $ctrl ] = $this->makeController( $posts );
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
				]
			)
		);

		$data = $res->get_data();
		$this->assertSame( 'Live Hello', $data['rows'][0]['title'] );
		$this->assertSame( 'https://example.test/wp-admin/post.php?post=7&action=edit', $data['rows'][0]['editUrl'] );
	}

	/**
	 * Test missing posts fall back to the id label without a link.
	 */
	public function test_preview_missing_post_has_no_title_or_link(): void {
		$posts = [
			[
				'id'   => 8,
				'type' => 'post',
				'data' => [ '_yoast_wpseo_title' => 'Ghost' ],
			],
		];

		Functions\when( 'get_post' )->justReturn( false );

		[ $ctrl ] = $this->makeController( $posts );
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
				]
			)
		);

		$data = $res->get_data();
		$this->assertSame( '', $data['rows'][0]['title'] );
		$this->assertSame( '', $data['rows'][0]['editUrl'] );
		$this->assertSame( 8, $data['rows'][0]['refId'] );
	}

	/**
	 * Test users without edit rights get no title or link.
	 */
	public function test_preview_without_edit_rights_has_no_title_or_link(): void {
		$posts = [
			[
				'id'   => 7,
				'type' => 'post',
				'data' => [ '_yoast_wpseo_title' => 'Hello' ],
			],
		];

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'get_post' )->alias(
			static function ( int $id ): \WP_Post {
				$post             = new \WP_Post();
				$post->ID         = $id;
				$post->post_title = 'Live Hello';

				return $post;
			}
		);
		Functions\when( 'get_the_title' )->justReturn( 'Live Hello' );
		Functions\when( 'get_edit_post_link' )->justReturn( 'https://example.test/wp-admin/post.php?post=7&action=edit' );

		[ $ctrl ] = $this->makeController( $posts );
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
				]
			)
		);

		$data = $res->get_data();
		$this->assertSame( '', $data['rows'][0]['title'] );
		$this->assertSame( '', $data['rows'][0]['editUrl'] );
	}

	/**
	 * Test preview term rows carry the term name plus edit link.
	 */
	public function test_preview_term_row_carries_name_and_edit_link(): void {
		Functions\when( 'get_term' )->alias(
			static function ( int $id ): \WP_Term {
				$term           = new \WP_Term();
				$term->term_id  = $id;
				$term->name     = 'Live Cats';
				$term->taxonomy = 'category';

				return $term;
			}
		);
		Functions\when( 'get_taxonomy' )->alias(
			static function ( string $taxonomy ): \WP_Taxonomy {
				return new \WP_Taxonomy();
			}
		);
		Functions\when( 'get_edit_term_link' )->alias( static fn (): string => 'https://example.test/wp-admin/term.php?taxonomy=category&tag_ID=21' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_edit_term_link signature.

		$runner = new ImportControllerStubRunner();
		$ctrl   = new ImportController(
			[ 'yoast' => new ImportControllerStubReader( [], [], [ [ 'id' => 21, 'taxonomy' => 'category', 'data' => [ 'wpseo_title' => 'Cats' ] ] ] ) ],
			static function () use ( $runner ): ImportControllerStubRunner {
				return $runner;
			}
		);
		$res    = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
					'kind'   => 'terms',
				]
			)
		);

		$data = $res->get_data();
		$this->assertSame( 'term:category:21', $data['rows'][0]['identity'] );
		$this->assertSame( 'Live Cats (category)', $data['rows'][0]['title'] );
		$this->assertSame( 'https://example.test/wp-admin/term.php?taxonomy=category&tag_ID=21', $data['rows'][0]['editUrl'] );
	}

	/**
	 * Test preview paginates post rows with stable totals.
	 */
	public function test_preview_paginates_posts(): void {
		$posts = [
			[
				'id'   => 7,
				'type' => 'post',
				'data' => [ '_yoast_wpseo_title' => 'Seven' ],
			],
			[
				'id'   => 8,
				'type' => 'post',
				'data' => [ '_yoast_wpseo_title' => 'Eight' ],
			],
			[
				'id'   => 9,
				'type' => 'post',
				'data' => [ '_yoast_wpseo_title' => 'Nine' ],
			],
		];

		[ $ctrl ] = $this->makeController( $posts );
		$first    = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
					'kind'   => 'posts',
					'offset' => 0,
					'limit'  => 2,
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $first );
		$firstData = $first->get_data();
		$this->assertCount( 2, $firstData['rows'] );
		$this->assertSame( 7, $firstData['rows'][0]['refId'] );
		$this->assertTrue( $firstData['hasMore'] );
		$this->assertSame( 3, $firstData['kindTotal'] );

		$second = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
					'kind'   => 'posts',
					'offset' => 2,
					'limit'  => 2,
				]
			)
		);

		$secondData = $second->get_data();
		$this->assertCount( 1, $secondData['rows'] );
		$this->assertSame( 9, $secondData['rows'][0]['refId'] );
		$this->assertFalse( $secondData['hasMore'] );
	}

	/**
	 * Test preview rejects an unknown kind.
	 */
	public function test_preview_invalid_kind_returns_400(): void {
		[ $ctrl ] = $this->makeController();
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
					'kind'   => 'widgets',
				]
			)
		);

		$this->assertInstanceOf( WP_Error::class, $res );
		$this->assertSame( 400, $res->get_error_data()['status'] ?? null );
	}

	/**
	 * Test preview clamps an oversized page.
	 */
	public function test_preview_limit_is_clamped(): void {
		[ $ctrl ] = $this->makeController();
		$res      = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
					'kind'   => 'posts',
					'limit'  => 9999,
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $res );
		$this->assertArrayHasKey( 'limit', $res->get_data() );
		$this->assertLessThanOrEqual( 100, $res->get_data()['limit'] );
	}

	/**
	 * Test sitewide preview carries candidates plus counted bulk rules.
	 */
	public function test_preview_sitewide_returns_candidates_and_counted_bulk(): void {
		Functions\when( 'wp_count_posts' )->justReturn(
			(object) [
				'publish' => 4,
				'draft'   => 1,
			]
		);

		$runner = new ImportControllerStubRunner();
		$ctrl   = new ImportController(
			[
				'yoast' => new ImportControllerStubReader(
					[],
					[
						'title-post'   => '%%title%%',
						'noindex-post' => true,
					]
				),
			],
			static function () use ( $runner ): ImportControllerStubRunner {
				return $runner;
			}
		);
		$res    = $ctrl->preview(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'mode'   => 'skip',
					'kind'   => 'sitewide',
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $res );
		$data = $res->get_data();

		$candidates = array_values( array_filter( $data['rows'], static fn( $row ) => '' !== ( $row['slot'] ?? '' ) ) );
		$this->assertCount( 1, $candidates );
		$this->assertSame( 'sitewide:title_template:post', $candidates[0]['identity'] );

		$bulks = array_values( array_filter( $data['rows'], static fn( $row ) => true === ( $row['bulk'] ?? false ) ) );
		$this->assertCount( 1, $bulks );
		$this->assertSame( 'bulk:post:post', $bulks[0]['identity'] );
		$this->assertSame( 5, $bulks[0]['affected'] );
	}

	/**
	 * Test run maps runner failures to a structured 400.
	 */
	public function test_run_maps_runner_exceptions_to_400(): void {
		$ctrl = new ImportController(
			[ 'yoast' => new ImportControllerStubReader() ],
			static function (): object {
				return new class() {
					/**
					 * Always fail like an invalid selection would.
					 *
					 * @throws \InvalidArgumentException Always.
					 */
					public function run(): void {
						throw new \InvalidArgumentException( 'Unknown bulk rule.' );
					}
				};
			}
		);
		$res  = $ctrl->runChunk(
			$this->makeRequest(
				[
					'source' => 'yoast',
					'kind'   => 'bulk',
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
