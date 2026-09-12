<?php
/**
 * CSV import and export tests over the in memory wpdb double.
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
 * Covers the documented CSV contract without a browser.
 *
 * Every import test writes a real temp file, runs it through the normal
 * validation pipeline, and asserts the stored rows plus the per row report.
 * Export tests assert the header, the column order, formula escaping, and
 * stable ordering. The round trip test exports from one table and imports
 * into another.
 */
final class RedirectsCsvTest extends TestCase {
	/**
	 * In memory redirect table.
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option storage backing get option and update option.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Temp files to remove in tearDown.
	 *
	 * @var string[]
	 */
	private array $tempFiles = [];

	/**
	 * Set up doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db        = new RedirectsFakeDb();
		$this->options   = [];
		$this->tempFiles = [];

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
	 * Reset globals and remove temp files.
	 */
	protected function tearDown(): void {
		foreach ( $this->tempFiles as $path ) {
			if ( is_file( $path ) ) {
				// Temp CSV files created by this test, removed during tearDown.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $path );
			}
		}

		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build the handler over the fake database.
	 */
	private function makeHandler(): CsvHandler {
		return new CsvHandler( new RedirectRepository( $this->db ) );
	}

	/**
	 * Write CSV content to a temp file.
	 */
	private function write_csv( string $content ): string {
		$path = tempnam( sys_get_temp_dir(), 'rkcsv' );

		$this->assertIsString( $path );

		// Temp CSV fixture for the import run, removed during tearDown.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $content );

		$this->tempFiles[] = $path;

		return $path;
	}

	/**
	 * Seed one rule in the fake table.
	 *
	 * @return int New row id.
	 */
	private function seedRule( string $source, string $target, string $code = '301', string $matchType = 'exact', bool $active = true ): int {
		$repo = new RedirectRepository( $this->db );

		return $repo->insert(
			[
				'source'     => $source,
				'target'     => $target,
				'code'       => $code,
				'match_type' => $matchType,
				'is_active'  => $active,
			]
		);
	}

	public function test_valid_file_imports_rows_with_defaults(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/old-one,/new-one,,,,\n" .
			"/old-two,/new-two,302,prefix,no,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 2, $result['created'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertSame( [], $result['errors'] );

		$first = $this->db->rows[1];

		$this->assertSame( '/old-one', $first['source'] );
		$this->assertSame( '/new-one', $first['target'] );
		$this->assertSame( '301', $first['code'] );
		$this->assertSame( 'exact', $first['match_type'] );
		$this->assertSame( 1, (int) $first['is_active'] );

		$second = $this->db->rows[2];

		$this->assertSame( '302', $second['code'] );
		$this->assertSame( 'prefix', $second['match_type'] );
		$this->assertSame( 0, (int) $second['is_active'] );
		$this->assertSame( 0, (int) $second['hits'] );

		$this->assertNotEmpty( $this->options['rankkernel_redirects_validator'] ?? '' );
	}

	public function test_malformed_rows_rejected_per_row_without_corrupting_others(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/good-one,/new-one,,,,\n" .
			"/bad-code,/new-two,999,exact,yes,,\n" .
			"/good-two,/new-three,,,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 2, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 3, $result['errors'][0]['row'] );
		$this->assertCount( 2, $this->db->rows );
	}

	public function test_invalid_match_type_rejected(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/old,/new,301,bogus,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( [], $this->db->rows );
	}

	public function test_invalid_regex_rejected(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"([a-z,/new,301,regex,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( [], $this->db->rows );
	}

	public function test_unsafe_destination_rejected(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/old,javascript:alert(1),301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( [], $this->db->rows );
	}

	public function test_loop_row_rejected_while_others_save(): void {
		$this->seedRule( '/b', '/a' );

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/a,/b,301,exact,yes,,\n" .
			"/c,/d,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 2, $result['errors'][0]['row'] );
		$this->assertStringContainsString( 'loop', strtolower( (string) $result['errors'][0]['reason'] ) );
		$this->assertCount( 2, $this->db->rows );
	}

	public function test_chain_row_warned_and_saved(): void {
		$this->seedRule( '/b', '/c' );

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/a,/b,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 1, $result['warnings'] );
		$this->assertSame( 2, $result['warnings'][0]['row'] );
	}

	public function test_duplicate_updates_when_update_existing_is_on(): void {
		$this->seedRule( '/old', '/first' );

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/old,/second,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path, true );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['updated'] );
		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( '/second', $this->db->rows[1]['target'] );
	}

	public function test_duplicate_skips_when_update_existing_is_off(): void {
		$this->seedRule( '/old', '/first' );

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/old,/second,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path, false );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( '/first', $this->db->rows[1]['target'] );
	}

	public function test_bom_and_windows_line_endings_tolerated(): void {
		$path = $this->write_csv(
			"\xEF\xBB\xBFsource,target,code,match_type,active,hits,last_accessed\r\n" .
			"/old,/new,,,,\r\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertSame( '/old', $this->db->rows[1]['source'] );
	}

	public function test_formula_prefix_neutralized_on_import(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/formula,=HYPERLINK(\"http://evil.example/x\"),301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 1, $result['created'] );

		$stored = (string) $this->db->rows[1]['target'];

		$this->assertStringContainsString( "'=", $stored );
	}

	public function test_row_count_limit_stops_the_run(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/one,/new-one,,,,\n" .
			"/two,/new-two,,,,\n" .
			"/three,/new-three,,,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path, false, 2 );

		$this->assertSame( 2, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 4, $result['errors'][0]['row'] );
		$this->assertCount( 2, $this->db->rows );
	}

	public function test_export_header_and_column_order(): void {
		$this->seedRule( '/old', '/new' );

		$csv   = $this->makeHandler()->export_csv();
		$lines = explode( "\n", trim( $csv ) );

		$this->assertSame( 'source,target,code,match_type,active,hits,last_accessed', $lines[0] );
		$this->assertSame( '/old,/new,301,exact,yes,0,', $lines[1] );
	}

	public function test_export_escapes_formula_cells(): void {
		$this->seedRule( '/x', '=evil' );

		$csv = $this->makeHandler()->export_csv();

		$this->assertStringContainsString( "'=evil", $csv );
	}

	public function test_export_uses_stable_id_order(): void {
		$this->seedRule( '/b', '/new-b' );
		$this->seedRule( '/a', '/new-a' );

		$csv  = $this->makeHandler()->export_csv();
		$posB = strpos( $csv, '/b,' );
		$posA = strpos( $csv, '/a,' );

		$this->assertIsInt( $posB );
		$this->assertIsInt( $posA );
		$this->assertLessThan( $posA, $posB );
	}

	public function test_round_trip_export_then_import(): void {
		$this->seedRule( '/old-one', '/new-one' );
		$this->seedRule( '/old-two', '/new-two', '302', 'prefix', false );

		$csv = $this->makeHandler()->export_csv();

		$freshDb              = new RedirectsFakeDb();
		$freshDb->tableExists = true;

		// Test swaps in a second in memory table for the round trip target.
		$GLOBALS['wpdb'] = $freshDb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$path = $this->write_csv( $csv );

		$handler = new CsvHandler( new RedirectRepository( $freshDb ) );
		$result  = $handler->import_csv( $path );

		$this->assertSame( 2, $result['created'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 2, $freshDb->rows );

		$rows = array_values( $freshDb->rows );

		$this->assertSame( '/old-one', $rows[0]['source'] );
		$this->assertSame( '/new-one', $rows[0]['target'] );
		$this->assertSame( '/old-two', $rows[1]['source'] );
		$this->assertSame( '302', $rows[1]['code'] );
		$this->assertSame( 'prefix', $rows[1]['match_type'] );
		$this->assertSame( 0, (int) $rows[1]['is_active'] );
	}
}
