<?php
/**
 * Instant Indexing log presentation, filters plus tabs plus pages.
 *
 * Pure presentation over the IndexNow outcome log. It applies the search,
 * source and status filters plus pagination from read only query
 * arguments, while InstantIndexingOutcomes owns the code to display
 * mapping. No WordPress APIs are called here, only translation functions,
 * so the whole class stays unit testable without a loaded WordPress.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares the Recent submissions card state for the view.
 */
final class InstantIndexingLogView {
	/**
	 * Rows per table page.
	 */
	public const PER_PAGE = 20;

	/**
	 * Unfiltered status value.
	 */
	public const STATUS_ALL = 'all';

	/**
	 * Unfiltered source value.
	 */
	public const SOURCE_ALL = 'all';

	/**
	 * Normalized log rows, newest first.
	 *
	 * @var array<int, array{url: string, code: int, source: string, time: string, message: string}>
	 */
	private array $rows;

	/**
	 * Current search term, matched against URL plus message.
	 *
	 * @var string
	 */
	private string $search;

	/**
	 * Current source filter, all or auto or manual.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Current status filter, all or one category slug.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Current page number, one based.
	 *
	 * @var int
	 */
	private int $page;

	/**
	 * Screen URL without filter arguments.
	 *
	 * @var string
	 */
	private string $baseUrl;

	/**
	 * Set up the view state.
	 *
	 * @param array<int, array{url: string, code: int, source: string, time: string, message: string}> $rows    Normalized log rows, newest first.
	 * @param string                                                                                   $search  Search term, matched against URL plus message.
	 * @param string                                                                                   $source  Source filter, all or auto or manual.
	 * @param string                                                                                   $status  Status filter, all or one category slug.
	 * @param int                                                                                      $page    Page number, one based.
	 * @param string                                                                                   $baseUrl Screen URL without filter arguments.
	 */
	public function __construct( array $rows, string $search, string $source, string $status, int $page, string $baseUrl ) {
		$this->rows    = $rows;
		$this->search  = $search;
		$this->source  = $source;
		$this->status  = $status;
		$this->page    = $page < 1 ? 1 : $page;
		$this->baseUrl = $baseUrl;
	}

	/**
	 * Build the view state from raw query arguments.
	 *
	 * Every value is sanitized with plain PHP only, unknown status and
	 * source values fall back to all, and the page number is clamped to
	 * a minimum of one. The caller passes the superglobal as is.
	 *
	 * @param array<int, array{url: string, code: int, source: string, time: string, message: string}> $rows    Normalized log rows, newest first.
	 * @param array<string, mixed>                                                                     $query   Raw query arguments.
	 * @param string                                                                                   $baseUrl Screen URL without filter arguments.
	 * @return self The result.
	 */
	public static function fromQuery( array $rows, array $query, string $baseUrl ): self {
		$search = $query['s'] ?? '';
		$search = is_string( $search ) ? trim( substr( $search, 0, 100 ) ) : '';

		$source = $query['rk_source'] ?? self::SOURCE_ALL;
		$source = is_string( $source ) ? $source : self::SOURCE_ALL;

		if ( 'auto' !== $source && 'manual' !== $source ) {
			$source = self::SOURCE_ALL;
		}

		$status = $query['rk_status'] ?? self::STATUS_ALL;
		$status = is_string( $status ) ? $status : self::STATUS_ALL;

		if ( ! in_array( $status, [ self::STATUS_ALL, InstantIndexingOutcomes::CATEGORY_ACCEPTED, InstantIndexingOutcomes::CATEGORY_PENDING, InstantIndexingOutcomes::CATEGORY_REJECTED, InstantIndexingOutcomes::CATEGORY_LIMITED ], true ) ) {
			$status = self::STATUS_ALL;
		}

		$paged = $query['rk_paged'] ?? 1;
		$paged = is_numeric( $paged ) ? (int) $paged : 1;

		return new self( $rows, $search, $source, $status, $paged, $baseUrl );
	}

	/**
	 * Notice state for one submit outcome code.
	 *
	 * The page redirects with one of these codes after a manual submit
	 * that stored no settings flag, so the operator still sees why the
	 * submit did not go through. Unknown codes render no notice.
	 *
	 * @param string $code Outcome code from the redirect.
	 * @return array{type: string, message: string}|null The result.
	 */
	public static function noticeFor( string $code ): ?array {
		if ( 'disabled' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Rejected: the Instant Indexing module is disabled.', 'rankkernel' ),
			];
		}

		if ( 'empty' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Rejected: no URLs were provided.', 'rankkernel' ),
			];
		}

		if ( 'host' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Could not submit. The URL host does not match this site. See the log below for details.', 'rankkernel' ),
			];
		}

		if ( 'unvalidated' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Could not submit. One or more URLs could not be validated. See the log below for details.', 'rankkernel' ),
			];
		}

		if ( 'mixed' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Some URLs were rejected. See the log below for details.', 'rankkernel' ),
			];
		}

		if ( 'cleared' === $code ) {
			return [
				'type'    => 'info',
				'message' => __( 'Log cleared.', 'rankkernel' ),
			];
		}

		return null;
	}

	/**
	 * Current search term.
	 *
	 * @return string The result.
	 */
	public function search(): string {
		return $this->search;
	}

	/**
	 * Current source filter.
	 *
	 * @return string The result.
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Current status filter.
	 *
	 * @return string The result.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Current page number, clamped to the available pages.
	 *
	 * @return int The result.
	 */
	public function page(): int {
		$pages = $this->pageCount();

		if ( $this->page > $pages ) {
			return $pages;
		}

		return $this->page;
	}

	/**
	 * Whether any filter narrows the table.
	 *
	 * @return bool The result.
	 */
	public function hasFilter(): bool {
		return '' !== $this->search || self::SOURCE_ALL !== $this->source || self::STATUS_ALL !== $this->status;
	}

	/**
	 * Rows after search plus source plus status, newest first.
	 *
	 * @return array<int, array{url: string, code: int, source: string, time: string, message: string}> The result.
	 */
	public function filteredRows(): array {
		$filtered = [];

		foreach ( $this->rows as $row ) {
			if ( self::SOURCE_ALL !== $this->source && $row['source'] !== $this->source ) {
				continue;
			}

			if ( self::STATUS_ALL !== $this->status && InstantIndexingOutcomes::categoryFor( (int) $row['code'] ) !== $this->status ) {
				continue;
			}

			if ( '' !== $this->search && false === stripos( $row['url'] . ' ' . $row['message'], $this->search ) ) {
				continue;
			}

			$filtered[] = $row;
		}

		return $filtered;
	}

	/**
	 * Count of rows after search plus source plus status.
	 *
	 * @return int The result.
	 */
	public function totalFiltered(): int {
		return count( $this->filteredRows() );
	}

	/**
	 * Status tabs with real counts over the search plus source rows.
	 *
	 * Counts ignore the active status tab itself, so every tab shows how
	 * many rows it would display.
	 *
	 * @return array<int, array{key: string, label: string, url: string, count: int, current: bool}> The result.
	 */
	public function tabs(): array {
		$counts = [
			self::STATUS_ALL                           => 0,
			InstantIndexingOutcomes::CATEGORY_ACCEPTED => 0,
			InstantIndexingOutcomes::CATEGORY_PENDING  => 0,
			InstantIndexingOutcomes::CATEGORY_REJECTED => 0,
			InstantIndexingOutcomes::CATEGORY_LIMITED  => 0,
		];

		foreach ( $this->rows as $row ) {
			if ( self::SOURCE_ALL !== $this->source && $row['source'] !== $this->source ) {
				continue;
			}

			if ( '' !== $this->search && false === stripos( $row['url'] . ' ' . $row['message'], $this->search ) ) {
				continue;
			}

			++$counts[ self::STATUS_ALL ];

			$category = InstantIndexingOutcomes::categoryFor( (int) $row['code'] );

			if ( isset( $counts[ $category ] ) ) {
				++$counts[ $category ];
			}
		}

		$labels = [
			self::STATUS_ALL                           => __( 'All', 'rankkernel' ),
			InstantIndexingOutcomes::CATEGORY_ACCEPTED => __( 'Accepted', 'rankkernel' ),
			InstantIndexingOutcomes::CATEGORY_PENDING  => __( 'Key pending', 'rankkernel' ),
			InstantIndexingOutcomes::CATEGORY_REJECTED => __( 'Rejected', 'rankkernel' ),
			InstantIndexingOutcomes::CATEGORY_LIMITED  => __( 'Rate limited', 'rankkernel' ),
		];

		$tabs = [];

		foreach ( $labels as $key => $label ) {
			$tabs[] = [
				'key'     => $key,
				'label'   => $label,
				'url'     => $this->url(
					[
						's'         => $this->search,
						'rk_source' => $this->source,
						'rk_status' => $key,
					]
				),
				'count'   => $counts[ $key ],
				'current' => $key === $this->status,
			];
		}

		return $tabs;
	}

	/**
	 * Enriched rows for the current page.
	 *
	 * @return array<int, array{url: string, code: int, source: string, time: string, message: string, category: string, statusLabel: string, statusPill: string, sourceLabel: string, sourcePill: string}> The result.
	 */
	public function pageRows(): array {
		$filtered = $this->filteredRows();
		$offset   = ( $this->page() - 1 ) * self::PER_PAGE;
		$slice    = array_slice( $filtered, $offset, self::PER_PAGE );
		$rows     = [];

		foreach ( $slice as $row ) {
			$category = InstantIndexingOutcomes::categoryFor( (int) $row['code'] );

			$rows[] = [
				'url'         => $row['url'],
				'code'        => (int) $row['code'],
				'source'      => $row['source'],
				'time'        => $row['time'],
				'message'     => $row['message'],
				'category'    => $category,
				'statusLabel' => InstantIndexingOutcomes::statusLabel( $category ),
				'statusPill'  => InstantIndexingOutcomes::statusPill( $category ),
				'sourceLabel' => InstantIndexingOutcomes::sourceLabel( $row['source'] ),
				'sourcePill'  => InstantIndexingOutcomes::sourcePill( $row['source'] ),
			];
		}

		return $rows;
	}

	/**
	 * Number of available pages, at least one.
	 *
	 * @return int The result.
	 */
	public function pageCount(): int {
		return max( 1, (int) ceil( $this->totalFiltered() / self::PER_PAGE ) );
	}

	/**
	 * Pagination links for the footer, numbered with gaps.
	 *
	 * @return array{show: bool, prevUrl: string, nextUrl: string, pages: array<int, array{label: string, url: string, current: bool, gap: bool}>} The result.
	 */
	public function pagination(): array {
		$total = $this->pageCount();
		$pages = [];

		foreach ( $this->pageNumbers( $total ) as $number ) {
			if ( 0 === $number ) {
				$pages[] = [
					'label'   => '…',
					'url'     => '',
					'current' => false,
					'gap'     => true,
				];

				continue;
			}

			$pages[] = [
				'label'   => (string) $number,
				'url'     => $this->url(
					[
						's'         => $this->search,
						'rk_source' => $this->source,
						'rk_status' => $this->status,
						'rk_paged'  => $number,
					]
				),
				'current' => $number === $this->page(),
				'gap'     => false,
			];
		}

		return [
			'show'    => $total > 1,
			'prevUrl' => $this->page() > 1 ? $this->pageUrl( $this->page() - 1 ) : '',
			'nextUrl' => $this->page() < $total ? $this->pageUrl( $this->page() + 1 ) : '',
			'pages'   => $pages,
		];
	}

	/**
	 * Visible range for the footer label, one based.
	 *
	 * @return array{from: int, to: int, total: int} The result.
	 */
	public function showing(): array {
		$total = $this->totalFiltered();

		if ( 0 === $total ) {
			return [
				'from'  => 0,
				'to'    => 0,
				'total' => 0,
			];
		}

		$from = ( $this->page() - 1 ) * self::PER_PAGE + 1;

		return [
			'from'  => $from,
			'to'    => min( $from + self::PER_PAGE - 1, $total ),
			'total' => $total,
		];
	}

	/**
	 * URL that clears every filter.
	 *
	 * @return string The result.
	 */
	public function clearUrl(): string {
		return $this->baseUrl;
	}

	/**
	 * Screen URL plus the preserved filter arguments.
	 *
	 * @return string The result.
	 */
	public function filtersActionUrl(): string {
		return $this->baseUrl;
	}

	/**
	 * Page link for one page number with every filter preserved.
	 *
	 * @param int $number Page number.
	 * @return string The result.
	 */
	private function pageUrl( int $number ): string {
		return $this->url(
			[
				's'         => $this->search,
				'rk_source' => $this->source,
				'rk_status' => $this->status,
				'rk_paged'  => $number,
			]
		);
	}

	/**
	 * Screen URL with the given arguments, defaults dropped.
	 *
	 * Empty search plus all source plus all status plus page one is the
	 * bare screen URL, anything else is appended as a query string.
	 *
	 * @param array<string, string|int> $params Arguments to encode.
	 * @return string The result.
	 */
	private function url( array $params ): string {
		$clean = [];

		foreach ( $params as $key => $value ) {
			$text = (string) $value;

			if ( 'rk_paged' === $key ) {
				if ( '1' !== $text ) {
					$clean[ $key ] = $text;
				}

				continue;
			}

			if ( 'rk_status' === $key && self::STATUS_ALL === $text ) {
				continue;
			}

			if ( 'rk_source' === $key && self::SOURCE_ALL === $text ) {
				continue;
			}

			if ( '' === $text ) {
				continue;
			}

			$clean[ $key ] = $text;
		}

		if ( [] === $clean ) {
			return $this->baseUrl;
		}

		$separator = str_contains( $this->baseUrl, '?' ) ? '&' : '?';

		return $this->baseUrl . $separator . http_build_query( $clean );
	}

	/**
	 * Page numbers with zero marking an ellipsis gap.
	 *
	 * Every page shows when there are seven or fewer, otherwise the
	 * first plus the last plus a window around the current page.
	 *
	 * @param int $total Available pages.
	 * @return int[] The result.
	 */
	private function pageNumbers( int $total ): array {
		if ( $total <= 7 ) {
			return range( 1, $total );
		}

		$current = $this->page();
		$numbers = [ 1 ];

		if ( $current > 3 ) {
			$numbers[] = 0;
		}

		foreach ( [ $current - 1, $current, $current + 1 ] as $number ) {
			if ( $number > 1 && $number < $total ) {
				$numbers[] = $number;
			}
		}

		if ( $current < $total - 2 ) {
			$numbers[] = 0;
		}

		$numbers[] = $total;

		return $numbers;
	}
}
