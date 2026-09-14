<?php
/**
 * Schema Generator tests, gating, filters, and document shape.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\Generator;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Schema Generator Test.
 */
final class SchemaGeneratorTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $v ): string => rtrim( $v, '/' ) . '/' );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Make Context.
	 *
	 * @return Context The result.
	 */
	private function makeContext(): Context {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( 1 )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		return new Context( $query, new SettingsStore() );
	}

	/**
	 * Stub Piece.
	 *
	 * @param string $id     Id.
	 * @param bool   $needed Needed.
	 * @param array  $output Output.
	 * @return PieceInterface The result.
	 */
	private function stubPiece( string $id, bool $needed, array $output ): PieceInterface {
		return new class($id, $needed, $output) implements PieceInterface {
			/**
			 * Create a new instance.
			 *
			 * @param privatereadonlystring $id     Id.
			 * @param privatereadonlybool   $needed Needed.
			 * @param privatereadonlyarray  $output Output.
			 */
			public function __construct(
				private readonly string $id,
				private readonly bool $needed,
				private readonly array $output
			) {
			}

			/**
			 * Get Id.
			 *
			 * @return string The result.
			 */
			public function getId(): string {
				return $this->id;
			}

			/**
			 * Is Needed.
			 *
			 * @param Context $ctx Ctx.
			 * @return bool The result.
			 */
			public function isNeeded( Context $ctx ): bool {
				return $this->needed;
			}

			/**
			 * Build.
			 *
			 * @param Context $ctx Ctx.
			 * @return array The result.
			 */
			public function build( Context $ctx ): array {
				return $this->output;
			}
		};
	}

	/**
	 * Test only needed pieces build.
	 */
	public function test_only_needed_pieces_build(): void {
		$ctx       = $this->makeContext();
		$generator = new Generator();
		$generator->register( $this->stubPiece( 'on', true, [ '@type' => 'WebSite' ] ) );
		$generator->register( $this->stubPiece( 'off', false, [ '@type' => 'Article' ] ) );

		$doc = $generator->generate( $ctx );

		$this->assertSame( 'https://schema.org', $doc['@context'] );
		$this->assertCount( 1, $doc['@graph'] );
		$this->assertSame( 'WebSite', $doc['@graph'][0]['@type'] );
	}

	/**
	 * Test empty builds dropped.
	 */
	public function test_empty_builds_dropped(): void {
		$ctx       = $this->makeContext();
		$generator = new Generator();
		$generator->register( $this->stubPiece( 'empty', true, [] ) );
		$generator->register( $this->stubPiece( 'full', true, [ '@type' => 'Organization' ] ) );

		$doc = $generator->generate( $ctx );

		$this->assertCount( 1, $doc['@graph'] );
		$this->assertSame( 'Organization', $doc['@graph'][0]['@type'] );
	}

	/**
	 * Test graph filter modifies output.
	 */
	public function test_graph_filter_modifies_output(): void {
		$ctx       = $this->makeContext();
		$generator = new Generator();
		$generator->register( $this->stubPiece( 'site', true, [ '@type' => 'WebSite' ] ) );

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/schema/graph' === $hook && is_array( $value ) ) {
					$value[] = [ '@type' => 'Injected' ];
				}

				return $value;
			}
		);

		$doc = $generator->generate( $ctx );

		$this->assertCount( 2, $doc['@graph'] );
		$this->assertSame( 'Injected', $doc['@graph'][1]['@type'] );
	}

	/**
	 * Test needs filter can disable piece.
	 */
	public function test_needs_filter_can_disable_piece(): void {
		$ctx       = $this->makeContext();
		$generator = new Generator();
		$generator->register( $this->stubPiece( 'article', true, [ '@type' => 'BlogPosting' ] ) );

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/schema/needs_article' === $hook ) {
					return false;
				}

				return $value;
			}
		);

		$doc = $generator->generate( $ctx );

		$this->assertSame( [], $doc['@graph'] );
	}

	/**
	 * Test piece filter can alter output.
	 */
	public function test_piece_filter_can_alter_output(): void {
		$ctx       = $this->makeContext();
		$generator = new Generator();
		$generator->register(
			$this->stubPiece(
				'website',
				true,
				[
					'@type' => 'WebSite',
					'name'  => 'Old',
				]
			)
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/schema/piece/website' === $hook && is_array( $value ) ) {
					$value['name'] = 'New';
				}

				return $value;
			}
		);

		$doc = $generator->generate( $ctx );

		$this->assertSame( 'New', $doc['@graph'][0]['name'] );
	}

	/**
	 * Test disabled payload yields empty graph.
	 */
	public function test_disabled_payload_yields_empty_graph(): void {
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'schema' => [
					'disabled' => true,
					'type'     => 'Article',
				],
			]
		);

		$generator = new Generator();
		$generator->register( $this->stubPiece( 'article', true, [ '@type' => 'Article' ] ) );

		$doc = $generator->generate( $this->makeContext() );

		$this->assertSame( [], $doc['@graph'] );
	}
}
