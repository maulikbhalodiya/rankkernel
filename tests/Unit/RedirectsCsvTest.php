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
use RankKernel\Modules\Redirects\RedirectCache;
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
	 *
	 * @var RedirectsFakeDb
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
	 *
	 * The upload probe defaults to a pass so the import tests exercise the
	 * full pipeline with a real temp file, the handler check is covered by
	 * its own test.
	 *
	 * @param callable(string): bool|null $probe Upload probe override.
	 * @return CsvHandler The result.
	 */
	private function makeHandler( ?callable $probe = null ): CsvHandler {
		return new CsvHandler(
			new RedirectRepository( $this->db ),
			null,
			null,
			$probe ?? static fn ( string $path ): bool => true // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub callback signature.
		);
	}

	/**
	 * Write CSV content to a temp file.
	 *
	 * @param string $content Content.
	 * @return string The result.
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
	 * @param string $source    Source.
	 * @param string $target    Target.
	 * @param string $code      Code.
	 * @param string $matchType Match Type.
	 * @param bool   $active    Active.
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

	/**
	 * Test valid file imports rows with defaults.
	 */
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

	/**
	 * Test a path that fails the upload probe is rejected inside the handler.
	 */
	public function test_non_uploaded_path_is_rejected_by_the_handler_probe(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/old-one,/new-one,,,,\n"
		);

		$result = $this->makeHandler( static fn ( string $p ): bool => false )->import_csv( $path ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub callback signature.

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 'The uploaded file could not be read.', $result['errors'][0]['reason'] );
		$this->assertSame( [], $this->db->rows, 'a rejected upload must not write any row' );
	}

	/**
	 * Test a malformed row blocks the whole import, nothing half applied.
	 */
	public function test_malformed_row_blocks_whole_import(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/good-one,/new-one,,,,\n" .
			"/bad-code,/new-two,999,exact,yes,,\n" .
			"/good-two,/new-three,,,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 3, $result['errors'][0]['row'] );
		$this->assertCount( 0, $this->db->rows, 'a refused import writes nothing' );
	}

	/**
	 * Test invalid match type rejected.
	 */
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

	/**
	 * Test invalid regex rejected.
	 */
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

	/**
	 * Test unsafe destination rejected.
	 */
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

	/**
	 * Test a loop row blocks the whole import, nothing half applied.
	 */
	public function test_loop_row_blocks_whole_import(): void {
		$this->seedRule( '/b', '/a' );

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/a,/b,301,exact,yes,,\n" .
			"/c,/d,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 2, $result['errors'][0]['row'] );
		$this->assertStringContainsString( 'loop', strtolower( (string) $result['errors'][0]['reason'] ) );
		$this->assertCount( 1, $this->db->rows, 'a refused import writes nothing' );
	}

	/**
	 * Test dry run reports would-be outcomes and writes nothing.
	 */
	public function test_dry_run_reports_without_writing(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/a,/b,301,exact,yes,,\n" .
			"/c,/d,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path, false, CsvHandler::MAX_ROWS, true );

		$this->assertTrue( $result['dry_run'] );
		$this->assertSame( 2, $result['created'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 0, $this->db->rows, 'a dry run writes nothing' );
	}

	/**
	 * Test an unverifiable row is refused without force and taken with force.
	 */
	public function test_inconclusive_row_needs_force(): void {
		$csv = "source,target,code,match_type,active,hits,last_accessed\n" .
			"/uncertain,/dynamic/$1,301,exact,yes,,\n";

		$plain = $this->makeHandler()->import_csv( $this->write_csv( $csv ) );

		$this->assertSame( 0, $plain['created'] );
		$this->assertCount( 1, $plain['errors'] );
		$this->assertCount( 0, $this->db->rows );

		$forced = $this->makeHandler()->import_csv( $this->write_csv( $csv ), false, CsvHandler::MAX_ROWS, false, true );

		$this->assertSame( 1, $forced['created'] );
		$this->assertSame( [], $forced['errors'] );
		$this->assertSame( 1, $forced['forced'] );
		$this->assertCount( 1, $this->db->rows );
	}

	/**
	 * Test chain row warned and saved.
	 */
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

	/**
	 * Test duplicate updates when update existing is on.
	 */
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

	/**
	 * Test duplicate skips when update existing is off.
	 */
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

	/**
	 * Test a storage failure is reported as an error, not as a duplicate skip.
	 */
	public function test_a_failed_insert_is_reported_as_an_error_not_a_skip(): void {
		$this->db->failInserts = true;

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/old,/new,,,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertSame( 0, $result['skipped'], 'a storage failure must not be reported as a duplicate skip' );
		$this->assertCount( 2, $result['errors'] );
		$this->assertSame( 2, $result['errors'][0]['row'] );
		$this->assertSame( 'The redirect could not be saved. Please try again.', $result['errors'][0]['reason'] );
		$this->assertSame( 0, $result['errors'][1]['row'] );
		$this->assertStringContainsString( 'rolled back', $result['errors'][1]['reason'] );
		$this->assertSame( [], $this->db->rows );
	}

	/**
	 * Test a successful import keeps the real row ids when the cache option is
	 * created during the first insert.
	 */
	public function test_successful_import_uses_real_row_ids_when_cache_invalidates(): void {
		$this->options = [];

		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				if ( RedirectCache::VALIDATOR_OPTION === $key ) {
					$this->db->insert_id = 9999;
				}

				$this->options[ $key ] = $value;

				return true;
			}
		);

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/first,/first-target,301,exact,yes,,\n" .
			"/second,/second-target,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 2, $result['created'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertSame( [ 1, 2 ], array_keys( $this->db->rows ) );
		$this->assertSame( '/first-target', $this->db->rows[1]['target'] );
		$this->assertSame( '/second-target', $this->db->rows[2]['target'] );
	}

	/**
	 * Test rollback uses the actual created redirect ids when cache invalidation
	 * overwrites $wpdb->insert_id during the first insert.
	 */
	public function test_rollback_deletes_created_rows_when_cache_invalidation_changes_insert_id(): void {
		$existingId = $this->seedRule( '/existing', '/keep' );

		// Simulate the cache validator option being created by update_option()
		// after the redirect insert; the old code would return 9999.
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				if ( RedirectCache::VALIDATOR_OPTION === $key ) {
					$this->db->insert_id = 9999;
				}

				$this->options[ $key ] = $value;

				return true;
			}
		);

		// Seed insert is attempt 1, first CSV insert attempt 2 succeeds, the
		// second CSV insert attempt 3 fails and triggers rollback.
		$this->db->failInsertAt = 3;

		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/new-one,/new-one-target,301,exact,yes,,\n" .
			"/new-two,/new-two-target,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['rolled_back'], 'the created row must be rolled back by its real id' );
		$this->assertArrayHasKey( $existingId, $this->db->rows, 'the pre-existing redirect must survive rollback' );
		$this->assertSame( '/keep', $this->db->rows[ $existingId ]['target'] );
		$this->assertCount( 1, $this->db->rows, 'no created row may remain after rollback' );
	}

	/**
	 * Test bom and windows line endings tolerated.
	 */
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

	/**
	 * Test a regex source beginning with '=' is stored with its intended value.
	 *
	 * Import must never put an export-only apostrophe into the stored pattern,
	 * otherwise the matcher compiles and evaluates different text from what the
	 * administrator entered.
	 */
	public function test_regex_source_leading_equals_stored_without_apostrophe(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"=abc,/new,302,regex,no,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( [], $result['errors'] );

		$stored = $this->db->rows[1];

		$this->assertSame( '=abc', $stored['source'] );
		$this->assertSame( '/new', $stored['target'] );
		$this->assertSame( '302', $stored['code'] );
		$this->assertSame( 'regex', $stored['match_type'] );
		$this->assertSame( 0, (int) $stored['is_active'] );
	}

	/**
	 * Test formula-leading regex bodies follow the existing regex validator.
	 *
	 * A leading '+', '-' or '@' is a spreadsheet trigger but can also start a
	 * stored pattern. The regex compile gate still decides acceptance, so
	 * '+abc' is refused while the compiling bodies are stored verbatim with no
	 * apostrophe added.
	 */
	public function test_formula_leading_regex_bodies_follow_the_regex_validator(): void {
		$rowId = 1;

		foreach ( [ '-abc', '@abc' ] as $source ) {
			$path = $this->write_csv(
				"source,target,code,match_type,active,hits,last_accessed\n" .
				$source . ",/new,301,regex,yes,,\n"
			);

			$result = $this->makeHandler()->import_csv( $path );

			$this->assertSame( 1, $result['created'], 'accepted regex source: ' . $source );
			$this->assertSame( [], $result['errors'] );
			$this->assertSame( $source, $this->db->rows[ $rowId ]['source'] );

			++$rowId;
		}

		$rejectedPath = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"+abc,/new,301,regex,yes,,\n"
		);
		$rejected     = $this->makeHandler()->import_csv( $rejectedPath );

		$this->assertSame( 0, $rejected['created'] );
		$this->assertCount( 1, $rejected['errors'] );
	}

	/**
	 * Test a relative formula-looking target is normalized without an apostrophe.
	 *
	 * Import stores the destination validator output, so the relative target
	 * still gets its normal leading slash, but no export-only apostrophe.
	 */
	public function test_relative_formula_target_normalized_without_apostrophe(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/formula,=1+1,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertSame( '/=1+1', $this->db->rows[1]['target'] );
	}

	/**
	 * Test a leading apostrophe already in the file is preserved on import.
	 *
	 * Import never strips an apostrophe, so a value carrying one is stored with
	 * that character intact and no second apostrophe is introduced.
	 */
	public function test_leading_apostrophe_preserved_on_import(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"'quoted,'/dest,301,exact,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertSame( "/'quoted", $this->db->rows[1]['source'] );
		$this->assertSame( "/'/dest", $this->db->rows[1]['target'] );
	}

	/**
	 * Test the row count limit refuses the whole run, nothing half applied.
	 */
	public function test_row_count_limit_stops_the_run(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"/one,/new-one,,,,\n" .
			"/two,/new-two,,,,\n" .
			"/three,/new-three,,,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path, false, 2 );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 4, $result['errors'][0]['row'] );
		$this->assertCount( 0, $this->db->rows, 'a refused import writes nothing' );
	}

	/**
	 * Test export header and column order.
	 */
	public function test_export_header_and_column_order(): void {
		$this->seedRule( '/old', '/new' );

		$csv   = $this->makeHandler()->export_csv();
		$lines = explode( "\n", trim( $csv ) );

		$this->assertSame( 'source,target,code,match_type,active,hits,last_accessed', $lines[0] );
		$this->assertSame( '/old,/new,301,exact,yes,0,', $lines[1] );
	}

	/**
	 * Test export escapes formula cells.
	 */
	public function test_export_escapes_formula_cells(): void {
		$this->seedRule( '/x', '=evil' );

		$csv = $this->makeHandler()->export_csv();

		$this->assertStringContainsString( "'=evil", $csv );
	}

	/**
	 * Test export uses stable id order.
	 */
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

	/**
	 * Test round trip export then import.
	 */
	public function test_round_trip_export_then_import(): void {
		$this->seedRule( '/old-one', '/new-one' );
		$this->seedRule( '/old-two', '/new-two', '302', 'prefix', false );

		$csv = $this->makeHandler()->export_csv();

		$freshDb              = new RedirectsFakeDb();
		$freshDb->tableExists = true;

		// Test swaps in a second in memory table for the round trip target.
		$GLOBALS['wpdb'] = $freshDb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$path = $this->write_csv( $csv );

		$handler = new CsvHandler(
			new RedirectRepository( $freshDb ),
			null,
			null,
			static fn ( string $path ): bool => true // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub callback signature.
		);
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

	/**
	 * Test a catastrophic regex row is rejected by the import.
	 */
	public function test_catastrophic_regex_rejected(): void {
		$path = $this->write_csv(
			"source,target,code,match_type,active,hits,last_accessed\n" .
			"(.+)+,/new,301,regex,yes,,\n"
		);

		$result = $this->makeHandler()->import_csv( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( [], $this->db->rows );
	}
}
