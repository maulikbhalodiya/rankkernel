<?php
/**
 * Instant Indexing log presentation, filters plus tabs plus pages.
 *
 * Pure presentation over the IndexNow outcome log. The query layer owns
 * filtering, counting, ordering and paging in SQL, and InstantIndexingOutcomes
 * owns the code to display mapping, so this class only shapes that state for
 * the template. No WordPress APIs are called here, only translation functions,
 * so the whole class stays unit testable without a loaded WordPress.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\InstantIndexing\LogFilters;
use RankKernel\Modules\InstantIndexing\LogQuery;

/**
 * Prepares the Submission history card state for the view.
 */
final class InstantIndexingLogView {
	/**
	 * Rows per table page.
	 */
	public const PER_PAGE = 20;

	/**
	 * Unfiltered status value.
	 */
	public const STATUS_ALL = LogFilters::STATUS_ALL;

	/**
	 * Unfiltered source value.
	 */
	public const SOURCE_ALL = LogFilters::SOURCE_ALL;

	/**
	 * Query layer behind every number on the card.
	 *
	 * @var LogQuery
	 */
	private LogQuery $query;

	/**
	 * Screen URL without filter arguments.
	 *
	 * @var string
	 */
	private string $baseUrl;

	/**
	 * Set up the view state.
	 *
	 * @param LogQuery $query   Normalized filters plus the read layer.
	 * @param string   $baseUrl Screen URL without filter arguments.
	 */
	public function __construct( LogQuery $query, string $baseUrl ) {
		$this->query   = $query;
		$this->baseUrl = $baseUrl;
	}

	/**
	 * Build the view state from raw query arguments.
	 *
	 * Normalization lives in the query layer, so the page and the future
	 * REST route share the same accepted values and fallbacks.
	 *
	 * @param array<string, mixed> $query   Raw query arguments.
	 * @param string               $baseUrl Screen URL without filter arguments.
	 * @return self The result.
	 */
	public static function fromQuery( array $query, string $baseUrl ): self {
		return new self( LogQuery::fromInput( $query, self::PER_PAGE ), $baseUrl );
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

		if ( 'retried' === $code ) {
			return [
				'type'    => 'info',
				'message' => __( 'Re-submitted. A new entry was added to the log below.', 'rankkernel' ),
			];
		}

		if ( 'retry_missing' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Could not retry. No log entry was selected.', 'rankkernel' ),
			];
		}

		if ( 'retry_notfound' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Could not retry. The selected log entry no longer exists.', 'rankkernel' ),
			];
		}

		if ( 'retry_unvalidated' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Could not retry. The stored URL could not be validated.', 'rankkernel' ),
			];
		}

		if ( 'retry_host' === $code ) {
			return [
				'type'    => 'error',
				'message' => __( 'Could not retry. The stored URL host does not match this site.', 'rankkernel' ),
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
		return $this->query->filters()->search();
	}

	/**
	 * Current source filter.
	 *
	 * @return string The result.
	 */
	public function source(): string {
		return $this->query->filters()->source();
	}

	/**
	 * Current status filter.
	 *
	 * @return string The result.
	 */
	public function status(): string {
		return $this->query->filters()->status();
	}

	/**
	 * Current page number, clamped to the available pages.
	 *
	 * The query layer returns an empty set for a page past the end, and
	 * this clamp keeps the displayed page and its links on the last real
	 * page, matching the old in memory behaviour.
	 *
	 * @return int The result.
	 */
	public function page(): int {
		$page  = $this->query->filters()->page();
		$pages = $this->query->pageCount();

		if ( $page > $pages ) {
			return $pages;
		}

		return $page;
	}

	/**
	 * Whether any filter narrows the table.
	 *
	 * @return bool The result.
	 */
	public function hasFilter(): bool {
		return $this->query->filters()->hasFilter();
	}

	/**
	 * Count of rows after search plus source plus status.
	 *
	 * @return int The result.
	 */
	public function totalFiltered(): int {
		return $this->query->filteredTotal();
	}

	/**
	 * Status tabs with real counts over the search plus source rows.
	 *
	 * Counts come from the query layer, which ignores the active status
	 * tab itself, so every tab shows how many rows it would display.
	 *
	 * @return array<int, array{key: string, label: string, url: string, count: int, current: bool}> The result.
	 */
	public function tabs(): array {
		$counts = $this->query->statusCounts();

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
						's'         => $this->search(),
						'rk_source' => $this->source(),
						'rk_status' => $key,
					]
				),
				'count'   => $counts[ $key ],
				'current' => $key === $this->status(),
			];
		}

		return $tabs;
	}

	/**
	 * Enriched rows for the current page.
	 *
	 * Each row carries its id as an int, so the template can build a retry
	 * form that posts the id alone, alongside the display fields.
	 *
	 * @return array<int, array{id: int, url: string, code: int, source: string, time: string, message: string, category: string, statusLabel: string, statusPill: string, sourceLabel: string, sourcePill: string}> The result.
	 */
	public function pageRows(): array {
		$rows   = $this->query->withPage( $this->page() )->rows();
		$result = [];

		foreach ( $rows as $row ) {
			$category = InstantIndexingOutcomes::categoryFor( (int) $row['code'] );

			$result[] = [
				'id'          => (int) $row['id'],
				'url'         => (string) $row['url'],
				'code'        => (int) $row['code'],
				'source'      => (string) $row['source'],
				'time'        => (string) $row['time'],
				'message'     => (string) $row['message'],
				'category'    => $category,
				'statusLabel' => InstantIndexingOutcomes::statusLabel( $category ),
				'statusPill'  => InstantIndexingOutcomes::statusPill( $category ),
				'sourceLabel' => InstantIndexingOutcomes::sourceLabel( (string) $row['source'] ),
				'sourcePill'  => InstantIndexingOutcomes::sourcePill( (string) $row['source'] ),
			];
		}

		return $result;
	}

	/**
	 * Number of available pages, at least one.
	 *
	 * @return int The result.
	 */
	public function pageCount(): int {
		return $this->query->pageCount();
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
						's'         => $this->search(),
						'rk_source' => $this->source(),
						'rk_status' => $this->status(),
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
	 * Filter form action, the bare screen URL.
	 *
	 * The form carries the current filter values and the page slug as
	 * its own fields, so the action itself preserves no arguments.
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
				's'         => $this->search(),
				'rk_source' => $this->source(),
				'rk_status' => $this->status(),
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
