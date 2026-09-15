<?php
/**
 * CSV import and export for redirect rules.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Documented CSV contract shared by import and export.
 *
 * Columns in order: source, target, code, match_type, active, hits,
 * last_accessed. Source is always required. Target is required except for
 * the terminal codes 410 and 451. Code defaults to 301. Match type defaults
 * to exact. Active defaults to yes. Hits and last accessed are export only
 * and are ignored on import.
 *
 * Every imported row passes the same pipeline as the admin form:
 * normalization, scheme allowlist, regex compile test, loop detection,
 * chain warning, and duplicate handling on match type plus source hash.
 * One bad row is reported and skipped while the remaining rows still import.
 */
final class CsvHandler {
	/**
	 * Contract header, exact columns in exact order.
	 *
	 * @var string[]
	 */
	public const HEADER = [ 'source', 'target', 'code', 'match_type', 'active', 'hits', 'last_accessed' ];

	/**
	 * Maximum accepted upload size in bytes, two megabytes.
	 */
	public const MAX_FILE_SIZE = 2097152;

	/**
	 * Maximum data rows accepted from one file.
	 */
	public const MAX_ROWS = 5000;

	/**
	 * Rows processed per bounded batch while streaming the file.
	 */
	public const BATCH_SIZE = 200;

	/**
	 * Rule repository.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repository;

	/**
	 * Loop and chain analyzer.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Destination policy checker.
	 *
	 * @var DestinationValidator
	 */
	private DestinationValidator $destinationValidator;

	/**
	 * Constructor, dependencies are injectable for tests.
	 *
	 * @param RedirectRepository|null   $repository           Rule repository, fresh one when null.
	 * @param Validator|null            $validator            Safety analyzer, fresh one when null.
	 * @param DestinationValidator|null $destinationValidator Destination checker, fresh one when null.
	 */
	public function __construct(
		?RedirectRepository $repository = null,
		?Validator $validator = null,
		?DestinationValidator $destinationValidator = null
	) {
		$this->repository           = $repository ?? new RedirectRepository();
		$this->validator            = $validator ?? new Validator();
		$this->destinationValidator = $destinationValidator ?? new DestinationValidator();
	}

	/**
	 * Import redirect rules from a CSV file on disk.
	 *
	 * Reads UTF-8 with an optional BOM tolerated, Unix and Windows line
	 * endings, comma delimiter, double quote wrapping with doubled quotes for
	 * escapes. Enforces the file size cap and the row count cap. Streams in
	 * bounded batches so a large file stays within memory. Each row is
	 * validated before its own write, so a malformed row can never corrupt
	 * the rows around it and a partial duplicate is never left behind.
	 *
	 * @param string $path           Absolute path to the uploaded CSV file.
	 * @param bool   $updateExisting Whether an identical rule is updated instead of skipped.
	 * @param int    $maxRows        Row cap for this run, defaults to MAX_ROWS.
	 * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, reason: string}>, warnings: list<array{row: int, message: string}>} Per row results.
	 */
	public function import_csv( string $path, bool $updateExisting = false, int $maxRows = self::MAX_ROWS ): array {
		$summary = [
			'created'  => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => [],
			'warnings' => [],
		];

		if ( '' === $path || ! is_readable( $path ) ) {
			$summary['errors'][] = [
				'row'    => 0,
				'reason' => __( 'The uploaded file could not be read.', 'rankkernel' ),
			];

			return $summary;
		}

		$size = filesize( $path );

		if ( false !== $size && $size > self::MAX_FILE_SIZE ) {
			$summary['errors'][] = [
				'row'    => 0,
				'reason' => __( 'That file is too large. Please keep CSV imports under 2 MB.', 'rankkernel' ),
			];

			return $summary;
		}

		// Streaming parse keeps memory bounded on large files. Whole file
		// readers would load the entire upload at once, breaking the batch cap.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $path, 'r' );

		if ( false === $handle ) {
			$summary['errors'][] = [
				'row'    => 0,
				'reason' => __( 'The uploaded file could not be read.', 'rankkernel' ),
			];

			return $summary;
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) || ! $this->header_matches( $header ) ) {
			// Paired with the streaming fopen above, part of the same read.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );

			$summary['errors'][] = [
				'row'    => 1,
				'reason' => __( 'That file does not use the RankKernel CSV header. Please export first and keep the header row unchanged.', 'rankkernel' ),
			];

			return $summary;
		}

		$rowNumber = 1;
		$processed = 0;

		while ( true ) {
			$fields = fgetcsv( $handle );

			if ( false === $fields ) {
				break;
			}

			++$rowNumber;

			if ( ! is_array( $fields ) ) {
				++$summary['skipped'];

				continue;
			}

			if ( $this->is_empty_row( $fields ) ) {
				++$summary['skipped'];

				continue;
			}

			if ( $processed >= $maxRows ) {
				$summary['errors'][] = [
					'row'    => $rowNumber,
					'reason' => sprintf(
						/* translators: %d: maximum accepted CSV rows */
						__( 'The row limit of %d rows was reached. The remaining rows were not imported.', 'rankkernel' ),
						$maxRows
					),
				];

				break;
			}

			++$processed;

			$result = $this->import_row( $this->row_cells( $fields ), $rowNumber, $updateExisting );

			if ( 'created' === $result['status'] ) {
				++$summary['created'];
			} elseif ( 'updated' === $result['status'] ) {
				++$summary['updated'];
			} elseif ( 'skipped' === $result['status'] ) {
				++$summary['skipped'];
			} else {
				$summary['errors'][] = [
					'row'    => $rowNumber,
					'reason' => $result['reason'],
				];
			}

			if ( '' !== $result['warning'] ) {
				$summary['warnings'][] = [
					'row'     => $rowNumber,
					'message' => $result['warning'],
				];
			}

			if ( 0 === ( $processed % self::BATCH_SIZE ) ) {
				$this->breathe();
			}
		}

		// Paired with the streaming fopen above, part of the same read.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		if ( $summary['created'] > 0 || $summary['updated'] > 0 ) {
			RedirectCache::invalidateAll();
		}

		return $summary;
	}

	/**
	 * Export rules to a CSV string using the documented contract.
	 *
	 * Rows leave in stable id order with the header row first, Unix line
	 * endings, no BOM. Free text cells are escaped against formula injection.
	 * Rows stream from the repository in bounded batches, so only one batch
	 * plus the output document sits in memory at a time.
	 *
	 * @param int[] $ids Optional row ids, all rows when empty.
	 * @return string CSV document.
	 */
	public function export_csv( array $ids = [] ): string {
		$lines  = [ $this->csv_line( self::HEADER ) ];
		$offset = 0;
		$total  = $this->repository->export_count( $ids );

		while ( $offset < $total ) {
			$rows = $this->repository->export_batch( $ids, RedirectRepository::EXPORT_BATCH, $offset );

			if ( [] === $rows ) {
				break;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$lines[] = $this->csv_line( $this->export_cells( $row ) );
			}

			$offset += count( $rows );
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Stream rules as CSV straight to the output buffer in bounded batches.
	 *
	 * Emits the identical bytes export_csv returns, but row memory never
	 * exceeds one batch however many rules exist, which keeps the admin
	 * download flat. Callers send the CSV headers first, then call this.
	 *
	 * @param int[] $ids Optional row ids, all rows when empty.
	 */
	public function stream_csv( array $ids = [] ): void {
		echo $this->csv_line( self::HEADER ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV bytes are the download body, escaping would corrupt the format.

		$offset = 0;
		$total  = $this->repository->export_count( $ids );

		while ( $offset < $total ) {
			$rows = $this->repository->export_batch( $ids, RedirectRepository::EXPORT_BATCH, $offset );

			if ( [] === $rows ) {
				break;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				echo $this->csv_line( $this->export_cells( $row ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV bytes are the download body, escaping would corrupt the format.
			}

			$offset += count( $rows );
		}
	}

	/**
	 * Map one rule row to the seven contract cells in header order.
	 *
	 * @param array<string, mixed> $row Rule row.
	 * @return list<string> Contract cells in header order.
	 */
	private function export_cells( array $row ): array {
		return [
			(string) ( $row['source'] ?? '' ),
			(string) ( $row['target'] ?? '' ),
			(string) ( $row['code'] ?? '301' ),
			(string) ( $row['match_type'] ?? 'exact' ),
			1 === (int) ( $row['is_active'] ?? 0 ) ? 'yes' : 'no',
			(string) ( $row['hits'] ?? '0' ),
			(string) ( $row['last_accessed'] ?? '' ),
		];
	}

	/**
	 * Neutralize a raw imported cell, never evaluate it.
	 *
	 * Trims the value, then prefixes a single quote when the first character
	 * is a spreadsheet formula trigger, so the stored text stays inert.
	 *
	 * @param string $value Raw cell text.
	 * @return string Sanitized cell text.
	 */
	public static function sanitize_cell( string $value ): string {
		$value = trim( $value );

		if ( '' !== $value && 1 === preg_match( '/\A[=+\-@\t\r]/u', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Escape free text for export against formula injection.
	 *
	 * Prefixes a single quote when the first character is a spreadsheet
	 * formula trigger, so opening the file can never run a formula.
	 *
	 * @param string $value Cell text about to be written.
	 * @return string Escaped cell text.
	 */
	public static function escape_cell( string $value ): string {
		if ( '' !== $value && 1 === preg_match( '/\A[=+\-@\t\r]/u', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Whether the file header matches the documented contract.
	 *
	 * Tolerates the UTF-8 BOM on the first cell and trims each cell, then
	 * requires the exact columns in the exact order.
	 *
	 * @param list<string|null> $header Raw header cells.
	 * @return bool True for a contract header.
	 */
	private function header_matches( array $header ): bool {
		$cells = [];

		foreach ( array_slice( $header, 0, count( self::HEADER ) ) as $index => $cell ) {
			$text = (string) $cell;

			if ( 0 === $index ) {
				$text = (string) preg_replace( '/\A\xEF\xBB\xBF/', '', $text );
			}

			$cells[] = trim( $text );
		}

		return self::HEADER === $cells;
	}

	/**
	 * Whether a raw CSV row carries no data at all.
	 *
	 * @param array<int, mixed> $fields Raw row cells.
	 * @return bool True when every cell is empty.
	 */
	private function is_empty_row( array $fields ): bool {
		foreach ( $fields as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Map a raw CSV row to the seven contract cells, padding short rows.
	 *
	 * @param array<int, mixed> $fields Raw row cells.
	 * @return list<string> Contract cells in header order.
	 */
	private function row_cells( array $fields ): array {
		$cells = [];

		foreach ( array_slice( $fields, 0, count( self::HEADER ) ) as $cell ) {
			$cells[] = (string) $cell;
		}

		return array_pad( $cells, count( self::HEADER ), '' );
	}

	/**
	 * Validate and save one row, collecting its single outcome.
	 *
	 * Runs the full pipeline before any write: normalization, code and match
	 * type checks, destination policy, regex compile test, duplicate handling
	 * on match type plus source hash, loop rejection, then chain warning. The
	 * write happens only after every check passes.
	 *
	 * @param array<int, string> $cells          Contract cells in header order.
	 * @param int                $rowNumber      One based file row number for reports.
	 * @param bool               $updateExisting Whether an identical rule is updated instead of skipped.
	 * @return array{status: string, reason: string, warning: string} Single row outcome.
	 */
	private function import_row( array $cells, int $rowNumber, bool $updateExisting ): array {
		$sourceRaw = self::sanitize_cell( $cells[0] );
		$targetRaw = self::sanitize_cell( $cells[1] );

		$codeRaw = strtolower( trim( $cells[2] ) );
		$code    = '' === $codeRaw ? '301' : $codeRaw;

		$matchRaw  = strtolower( trim( $cells[3] ) );
		$matchType = '' === $matchRaw ? 'exact' : $matchRaw;

		$activeRaw = strtolower( trim( $cells[4] ) );
		$activeRaw = '' === $activeRaw ? 'yes' : $activeRaw;

		if ( '' === trim( $cells[0] ) ) {
			return $this->row_error( __( 'The source address is required.', 'rankkernel' ) );
		}

		if ( strlen( $sourceRaw ) > 2000 ) {
			return $this->row_error( __( 'That source is too long. Please keep it under 2000 characters.', 'rankkernel' ) );
		}

		if ( ! Normalizer::isCode( $code ) ) {
			return $this->row_error( __( 'That redirect type is not supported. Use 301, 302, 307, 410 or 451.', 'rankkernel' ) );
		}

		if ( ! Normalizer::isMatchType( $matchType ) ) {
			return $this->row_error( __( 'That match type is not supported. Use exact, prefix, contains, suffix, wildcard or regex.', 'rankkernel' ) );
		}

		if ( 'yes' !== $activeRaw && 'no' !== $activeRaw ) {
			return $this->row_error( __( 'The active flag must be yes or no.', 'rankkernel' ) );
		}

		$isActive = 'yes' === $activeRaw;

		if ( strlen( $targetRaw ) > 2000 ) {
			return $this->row_error( __( 'That destination is too long. Please keep it under 2000 characters.', 'rankkernel' ) );
		}

		$checked = $this->destinationValidator->validate( $targetRaw, $code );

		if ( ! $checked['valid'] ) {
			return $this->row_error(
				sprintf(
					/* translators: %s: reason the destination was rejected */
					__( 'That destination is not valid: %s.', 'rankkernel' ),
					$checked['reason']
				)
			);
		}

		$source = Normalizer::normalizeSource( $sourceRaw, $matchType );

		if ( '' === $source || Normalizer::isBlockedSource( $source ) ) {
			return $this->row_error( __( 'The home page cannot be used as a redirect source. Please use a path such as /old page.', 'rankkernel' ) );
		}

		if ( 'regex' === $matchType && ! $this->regex_compiles( $cells[0] ) ) {
			return $this->row_error( __( 'That regex pattern could not be compiled. Please check the pattern and try again.', 'rankkernel' ) );
		}

		$editingId = 0;
		$existing  = $this->repository->lookup( $source, $matchType );

		if ( is_array( $existing ) ) {
			if ( ! $updateExisting ) {
				return [
					'status'  => 'skipped',
					'reason'  => '',
					'warning' => '',
				];
			}

			$editingId = (int) ( $existing['id'] ?? 0 );
		}

		$proposed = [
			'source'     => $source,
			'target'     => $checked['destination'],
			'code'       => $code,
			'match_type' => $matchType,
		];

		$candidates = $this->candidates( $editingId, $proposed, $isActive );
		$safety     = $this->validator->assess_safety( $proposed, $candidates );
		$loop       = $safety['loop'];

		if ( 'equivalent' === $safety['verdict'] ) {
			return $this->row_error(
				sprintf(
					/* translators: %s: equivalence explanation */
					__( '%s The row was not imported.', 'rankkernel' ),
					Validator::equivalent_message()
				)
			);
		}

		if ( $loop['has_cycle'] ) {
			return $this->row_error(
				sprintf(
					/* translators: %s: redirect chain path showing the loop */
					__( 'This redirect would create a redirect loop: %s. The row was not imported.', 'rankkernel' ),
					implode( ' → ', $loop['path'] )
				)
			);
		}

		if ( $this->patternCapReached( $editingId, $proposed, $isActive ) ) {
			return $this->row_error(
				sprintf(
					/* translators: %d: maximum active pattern rules */
					__( 'The active pattern rule limit of %d is reached. The row was not imported.', 'rankkernel' ),
					RedirectRepository::MAX_PATTERNS
				)
			);
		}

		$row = [
			'source'     => $source,
			'match_type' => $matchType,
			'target'     => $checked['destination'],
			'code'       => $code,
			'is_active'  => $isActive,
		];

		if ( $editingId > 0 ) {
			$saved = $this->repository->update( $editingId, $row );

			if ( ! $saved ) {
				return $this->row_error( __( 'The redirect could not be saved. Please try again.', 'rankkernel' ) );
			}

			$status = 'updated';
		} else {
			$newId = $this->repository->insert( $row );

			if ( $newId <= 0 ) {
				return [
					'status'  => 'skipped',
					'reason'  => '',
					'warning' => '',
				];
			}

			$status = 'created';
		}

		$warning = '';
		$chain   = $safety['chain'];

		if ( $chain['has_chain'] && [] !== $chain['chain'] ) {
			$warning = sprintf(
				/* translators: %s: redirect chain path */
				__( 'Redirect chain detected: %s. The row was saved.', 'rankkernel' ),
				implode( ' → ', $chain['chain'] )
			);

			if ( is_string( $chain['final'] ) && '' !== $chain['final'] ) {
				$warning .= ' ' . sprintf(
					/* translators: %s: recommended final destination */
					__( 'Consider pointing the source directly to %s.', 'rankkernel' ),
					$chain['final']
				);
			}

			if ( $loop['inconclusive'] || $chain['inconclusive'] ) {
				$warning .= ' ' . __( 'The analysis could not fully verify every branch, so please verify it manually.', 'rankkernel' );
			}
		} elseif ( $loop['inconclusive'] || $chain['inconclusive'] ) {
			$warning = __( 'The analysis could not fully verify the final destination. Saved as entered, please verify it manually.', 'rankkernel' );
		}

		return [
			'status'  => $status,
			'reason'  => '',
			'warning' => $warning,
		];
	}

	/**
	 * Whether importing the proposed row would exceed the pattern cap.
	 *
	 * Rows already inside the active pattern set never count as growth, so
	 * updates that keep a rule active keep passing at the limit.
	 *
	 * @param int                  $editingId Row id being updated, zero when adding.
	 * @param array<string, mixed> $proposed  Proposed source, target, code, match type.
	 * @param bool                 $isActive  Whether the proposed rule stays active.
	 * @return bool True when the cap blocks this row.
	 */
	private function patternCapReached( int $editingId, array $proposed, bool $isActive ): bool {
		if ( ! $isActive || 'exact' === (string) ( $proposed['match_type'] ?? 'exact' ) ) {
			return false;
		}

		if ( $this->repository->count_patterns() < RedirectRepository::MAX_PATTERNS ) {
			return false;
		}

		if ( $editingId > 0 ) {
			$current = $this->repository->get( $editingId );

			if ( is_array( $current )
				&& 1 === (int) ( $current['is_active'] ?? 0 )
				&& 'exact' !== (string) ( $current['match_type'] ?? 'exact' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build an error outcome for one row.
	 *
	 * @param string $reason Translated reason for the report.
	 * @return array{status: string, reason: string, warning: string}
	 */
	private function row_error( string $reason ): array {
		return [
			'status'  => 'error',
			'reason'  => $reason,
			'warning' => '',
		];
	}

	/**
	 * Candidate rows for safety analysis, including the proposed rule itself.
	 *
	 * The proposed rule takes part so a rule that matches its own target is
	 * reported. The updated row is excluded by id when present.
	 *
	 * @param int                  $editingId Row id being updated, zero when adding.
	 * @param array<string, mixed> $proposed  Proposed source, target, code, match type.
	 * @param bool                 $isActive  Whether the proposed rule stays active.
	 * @return array<int, array<string, mixed>>
	 */
	private function candidates( int $editingId, array $proposed, bool $isActive ): array {
		$rows = $this->repository->find_cycle_candidates();
		$out  = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( $editingId > 0 && (int) ( $row['id'] ?? 0 ) === $editingId ) {
				continue;
			}

			$out[] = $row;
		}

		$out[] = array_merge( $proposed, [ 'is_active' => $isActive ? 1 : 0 ] );

		return $out;
	}

	/**
	 * Whether a regex source compiles under the matcher wrapping.
	 *
	 * Mirrors the matcher length cap and delimiter handling so the import
	 * rejects patterns the frontend would fail closed on.
	 *
	 * @param string $pattern Raw regex body as entered.
	 * @return bool True when the pattern compiles cleanly.
	 */
	private function regex_compiles( string $pattern ): bool {
		$pattern = trim( $pattern );

		if ( '' === $pattern || strlen( $pattern ) > 200 ) {
			return false;
		}

		$wrapped = '#' . str_replace( '#', '\\#', $pattern ) . '#u';

		// Bounded compile probe for an imported pattern. The probe handler
		// swallows only the compile warning and is always restored below.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static fn (): bool => true );

		try {
			$result = preg_match( $wrapped, '/' );
		} finally {
			restore_error_handler();
		}

		return false !== $result && PREG_NO_ERROR === preg_last_error();
	}

	/**
	 * Encode one CSV line with quote wrapping and doubled quotes.
	 *
	 * @param array<int, string> $fields Cell values in column order.
	 * @return string Encoded line without the line ending.
	 */
	private function csv_line( array $fields ): string {
		$encoded = [];

		foreach ( $fields as $field ) {
			$cell = self::escape_cell( (string) $field );

			if ( str_contains( $cell, '"' ) || str_contains( $cell, ',' ) || str_contains( $cell, "\n" ) || str_contains( $cell, "\r" ) ) {
				$encoded[] = '"' . str_replace( '"', '""', $cell ) . '"';
			} else {
				$encoded[] = $cell;
			}
		}

		return implode( ',', $encoded );
	}

	/**
	 * Yield control briefly between batches on long imports.
	 *
	 * Keeps a bounded import friendly to shared hosting by resetting the
	 * script time budget when the host allows it.
	 */
	private function breathe(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 60 );
		}
	}
}
