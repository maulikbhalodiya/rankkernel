<?php
/**
 * Chunked CSV export tests, bounded reads plus identical bytes.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\CsvHandler;
use RankKernel\Modules\Redirects\RedirectRepository;

/**
 * Proves export memory stays bounded however many rules exist.
 *
 * Rows stream from the repository in capped batches, the string export and
 * the streamed download emit identical bytes, and the CSV contract of
 * headers, order, and formula escaping is unchanged.
 */
final class RedirectsExportStreamTest extends TestCase {
	/**
	 * In memory redirect table.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db      = new RedirectsFakeDb();
		$this->options = [];

		// Test installs the in memory wpdb double, restored in tearDown.
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				// Test double backing the stubbed wp_parse_url with the native parser.
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_allowed_protocols' )->alias( static fn (): array => [ 'http', 'https' ] );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
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
	 * Prefill exact rules straight into the fake table.
	 *
	 * @param int $count Count.
	 */
	private function seedRows( int $count ): void {
		for ( $i = 1; $i <= $count; $i++ ) {
			$this->db->rows[ $i ] = [
				'id'            => $i,
				'match_type'    => 'exact',
				'source_hash'   => hash( 'sha256', 'exact|/row-' . (string) $i ),
				'source'        => '/row-' . (string) $i,
				'target'        => '/dest-' . (string) $i,
				'code'          => '301',
				'is_active'     => 1,
				'created'       => '2026-01-01 00:00:00',
				'hits'          => $i,
				'last_accessed' => '2026-01-02 00:00:00',
			];
		}

		$this->db->nextId = $count + 1;
	}

	/**
	 * Test large export reads in bounded batches.
	 */
	public function test_large_export_reads_in_bounded_batches(): void {
		$this->seedRows( 1200 );

		$repo    = new RedirectRepository( $this->db );
		$handler = new CsvHandler( $repo );

		$readsBefore = $this->db->ruleReads;
		$csv         = $handler->export_csv();

		$lines = explode( "\n", trim( $csv ) );

		$this->assertCount( 1201, $lines, 'Header plus one line per rule' );
		$this->assertSame( 'source,target,code,match_type,active,hits,last_accessed', $lines[0] );
		$this->assertGreaterThanOrEqual( 4, $this->db->ruleReads - $readsBefore, 'One count plus at least three batches' );
		$this->assertSame( '/row-1,/dest-1,301,exact,yes,1,2026-01-02 00:00:00', $lines[1] );
		$this->assertSame( '/row-1200,/dest-1200,301,exact,yes,1200,2026-01-02 00:00:00', $lines[1200] );
	}

	/**
	 * Test streamed bytes match string export.
	 */
	public function test_streamed_bytes_match_string_export(): void {
		$this->seedRows( 1200 );

		$this->db->rows[7]['source'] = '=HYPERLINK("https://evil.example")';

		$repo    = new RedirectRepository( $this->db );
		$handler = new CsvHandler( $repo );

		$string = $handler->export_csv();

		ob_start();
		$handler->stream_csv();
		$streamed = ob_get_clean();

		$this->assertIsString( $streamed );
		$this->assertSame( $string, $streamed, 'Stream and string paths must emit identical bytes' );
		$this->assertStringContainsString( "'=HYPERLINK", $string, 'Formula cells stay escaped on both paths' );
	}

	/**
	 * Test selected ids export in stable order.
	 */
	public function test_selected_ids_export_in_stable_order(): void {
		$this->seedRows( 10 );

		$repo    = new RedirectRepository( $this->db );
		$handler = new CsvHandler( $repo );

		$csv   = $handler->export_csv( [ 9, 3, 5 ] );
		$lines = explode( "\n", trim( $csv ) );

		$this->assertCount( 4, $lines );
		$this->assertStringStartsWith( '/row-3,', $lines[1] );
		$this->assertStringStartsWith( '/row-5,', $lines[2] );
		$this->assertStringStartsWith( '/row-9,', $lines[3] );
	}

	/**
	 * Test empty table exports header only.
	 */
	public function test_empty_table_exports_header_only(): void {
		$handler = new CsvHandler( new RedirectRepository( $this->db ) );

		$this->assertSame( "source,target,code,match_type,active,hits,last_accessed\n", $handler->export_csv() );
	}
}
