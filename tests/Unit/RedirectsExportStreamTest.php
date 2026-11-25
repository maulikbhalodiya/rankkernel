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
	 * Plant one rule with raw values straight into the table, bypassing import.
	 *
	 * @param string               $source Source.
	 * @param string               $target Target.
	 * @param array<string, mixed> $extra  Extra fields.
	 * @return int New row id.
	 */
	private function plantRawRow( string $source, string $target, array $extra = [] ): int {
		$id = $this->db->nextId;
		++$this->db->nextId;

		$this->db->rows[ $id ] = array_merge(
			[
				'id'            => $id,
				'match_type'    => 'exact',
				'source_hash'   => hash( 'sha256', 'exact|' . $source ),
				'source'        => $source,
				'target'        => $target,
				'code'          => '301',
				'is_active'     => 1,
				'created'       => '2026-01-01 00:00:00',
				'hits'          => 0,
				'last_accessed' => null,
			],
			$extra
		);

		return $id;
	}

	/**
	 * Parse a CSV document into records with the native CSV reader.
	 *
	 * Uses fgetcsv so quoted fields with embedded commas, quotes and newlines
	 * are decoded the way a spreadsheet would read them.
	 *
	 * @param string $csv CSV document.
	 * @return list<list<string>> Parsed records.
	 */
	private function parseCsvDocument( string $csv ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream used only to decode a CSV string.
		$handle = fopen( 'php://temp', 'w+' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory stream used only to decode a CSV string.
		fwrite( $handle, $csv );
		rewind( $handle );

		$records = [];

		while ( true ) {
			$fields = fgetcsv( $handle, 0, ',', '"', '' );
			if ( false === $fields ) {
				break;
			}
			$records[] = $fields;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- paired with the in-memory stream opened above.
		fclose( $handle );

		return $records;
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

	/**
	 * Test raw stored formula values get exactly one protective apostrophe.
	 */
	public function test_export_escapes_raw_stored_formula_values(): void {
		$values = [ '=1+1', '+2+3', '-2+3', '@SUM(1,2)' ];

		foreach ( $values as $value ) {
			$this->plantRawRow( $value, '/dest' );
		}

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );
		$records = $this->parseCsvDocument( $handler->export_csv() );

		$this->assertSame( CsvHandler::HEADER, $records[0] );
		$this->assertCount( count( $values ) + 1, $records );

		foreach ( $values as $index => $value ) {
			$record = $records[ $index + 1 ];

			$this->assertCount( 7, $record );
			$this->assertSame( "'" . $value, $record[0], 'source must carry exactly one protective apostrophe' );
			$this->assertSame( '/dest', $record[1], 'an ordinary target must not be escaped' );
		}
	}

	/**
	 * Test leading tab and carriage return cells are escaped on export.
	 */
	public function test_export_escapes_leading_tab_and_cr_cells(): void {
		$this->plantRawRow( "\tsource", "\rdest" );

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );
		$records = $this->parseCsvDocument( $handler->export_csv() );

		$this->assertSame( "'\tsource", $records[1][0] );
		$this->assertSame( "'\rdest", $records[1][1] );
	}

	/**
	 * Test ordinary, apostrophe, empty, numeric and multibyte cells pass through.
	 */
	public function test_export_preserves_non_formula_cells(): void {
		$this->plantRawRow(
			'/old',
			'https://example.com/new',
			[
				'code' => '302',
				'hits' => 42,
			]
		);
		$this->plantRawRow(
			"'=stored",
			"'/dest",
			[
				'code' => '307',
				'hits' => 0,
			]
		);
		$this->plantRawRow(
			'',
			'',
			[
				'code' => '410',
				'hits' => 0,
			]
		);
		$this->plantRawRow(
			'/café/日本語',
			'/目標',
			[
				'code' => '301',
				'hits' => 7,
			]
		);
		$this->plantRawRow(
			'0',
			'1',
			[
				'code' => '301',
				'hits' => 0,
			]
		);

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );
		$records = $this->parseCsvDocument( $handler->export_csv() );

		$this->assertSame( CsvHandler::HEADER, $records[0] );
		$this->assertCount( 6, $records );
		$this->assertSame( [ '/old', 'https://example.com/new', '302', 'exact', 'yes', '42', '' ], $records[1] );
		$this->assertSame( [ "'=stored", "'/dest", '307', 'exact', 'yes', '0', '' ], $records[2], 'an existing apostrophe stays single' );
		$this->assertSame( [ '', '', '410', 'exact', 'yes', '0', '' ], $records[3] );
		$this->assertSame( [ '/café/日本語', '/目標', '301', 'exact', 'yes', '7', '' ], $records[4] );
		$this->assertSame( [ '0', '1', '301', 'exact', 'yes', '0', '' ], $records[5], 'numeric fields stay unchanged' );
	}

	/**
	 * Test embedded delimiters and newlines round trip through the CSV parser.
	 */
	public function test_export_quotes_embedded_delimiters_and_newlines(): void {
		$source = '/a,b';
		$target = '/say "hi"';
		$multi  = "/line1\nline2";

		$this->plantRawRow( $source, $target );
		$this->plantRawRow( $multi, '/dest' );
		$this->plantRawRow( '=comma,quote"', "=two\nlines" );

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );
		$records = $this->parseCsvDocument( $handler->export_csv() );

		$this->assertCount( 4, $records );
		$this->assertSame( [ $source, $target, '301', 'exact', 'yes', '0', '' ], $records[1] );
		$this->assertSame( [ $multi, '/dest', '301', 'exact', 'yes', '0', '' ], $records[2] );
		$this->assertSame( [ "'=comma,quote\"", "'=two\nlines", '301', 'exact', 'yes', '0', '' ], $records[3] );
	}

	/**
	 * Test export never mutates the stored rows.
	 */
	public function test_export_leaves_stored_rows_unchanged(): void {
		$this->plantRawRow( '=1+1', '+2+3' );
		$before = $this->db->rows;

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );
		$handler->export_csv();

		$this->assertSame( $before, $this->db->rows );
	}

	/**
	 * Test streamed bytes match string export for hostile and quoted cells.
	 */
	public function test_stream_matches_export_for_formula_comma_quote_and_multiline(): void {
		$this->plantRawRow( '=1+1', '+2+3' );
		$this->plantRawRow( '/a,b', '/say "hi"' );
		$this->plantRawRow( "/line1\nline2", '@SUM(1,2)' );
		$this->plantRawRow( "\tcmd", "\rcmd" );

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );

		$string = $handler->export_csv();

		ob_start();
		$handler->stream_csv();
		$streamed = ob_get_clean();

		$this->assertIsString( $streamed );
		$this->assertSame( $string, $streamed, 'stream and string exports must emit identical bytes' );
	}

	/**
	 * Test normal round trip stability and the formula-leading reimport limit.
	 *
	 * Normal stored values survive export then import unchanged. A formula
	 * leading value is escaped for the file, and reimport keeps that protective
	 * apostrophe as literal data because import never auto-unescapes it.
	 */
	public function test_export_reimport_stability_and_formula_limitation(): void {
		$this->plantRawRow( '/old-one', '/new-one' );
		$this->plantRawRow(
			'/a,b',
			'/say "hi"',
			[
				'code'       => '302',
				'match_type' => 'prefix',
				'is_active'  => 0,
			]
		);
		$this->plantRawRow( '=1+1', '/dest' );

		$handler = new CsvHandler( new RedirectRepository( $this->db ) );
		$csv     = $handler->export_csv();

		$this->assertStringContainsString( "'=1+1,/dest", $csv );

		$db              = new RedirectsFakeDb();
		$db->tableExists = true;
		$originalDb      = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$path = tempnam( sys_get_temp_dir(), 'rkcsv' );
		$this->assertIsString( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $csv );

		try {
			$result = ( new CsvHandler(
				new RedirectRepository( $db ),
				null,
				null,
				static fn ( string $p ): bool => true // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub callback signature.
			) )->import_csv( $path );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $path );
			$GLOBALS['wpdb'] = $originalDb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$this->assertSame( 3, $result['created'] );
		$this->assertSame( [], $result['errors'] );

		$first  = $db->rows[1];
		$second = $db->rows[2];
		$third  = $db->rows[3];

		$this->assertSame( '/old-one', $first['source'] );
		$this->assertSame( '/new-one', $first['target'] );
		$this->assertSame( '/a,b', $second['source'] );
		$this->assertSame( '/say "hi"', $second['target'] );
		$this->assertSame( '302', $second['code'] );
		$this->assertSame( 'prefix', $second['match_type'] );
		$this->assertSame( 0, (int) $second['is_active'] );
		$this->assertSame( "/'=1+1", $third['source'], 'the export apostrophe is kept as data, never auto-stripped' );
	}
}
