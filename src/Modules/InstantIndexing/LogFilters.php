<?php
/**
 * Instant Indexing log filter state, normalized in one place.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\InstantIndexing;

defined( 'ABSPATH' ) || exit;

use RankKernel\Admin\InstantIndexingOutcomes;

/**
 * Normalized search, source, status and paging state for the log queries.
 *
 * Every consumer, the server rendered page and the future REST route,
 * builds its query through fromInput(), so the accepted values, the
 * fallbacks and the page size clamp can never drift between the two.
 * Unknown values normalize to the unfiltered default instead of failing.
 * Input is taken exactly as handed over: WordPress unslashing stays at
 * the consumer boundary so an already unslashed REST value is not
 * mangled here.
 */
final class LogFilters {
	/**
	 * Unfiltered status value.
	 */
	public const STATUS_ALL = 'all';

	/**
	 * Unfiltered source value.
	 */
	public const SOURCE_ALL = 'all';

	/**
	 * Largest page size one query may request.
	 */
	public const MAX_PER_PAGE = 200;

	/**
	 * Longest accepted search term, matching the old in memory filter.
	 */
	private const SEARCH_MAX = 100;

	/**
	 * Search term, matched against URL plus message.
	 *
	 * @var string
	 */
	private string $search;

	/**
	 * Source filter, all or auto or manual.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Status filter, all or one tab category slug.
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
	 * Rows per page, between one and MAX_PER_PAGE.
	 *
	 * @var int
	 */
	private int $perPage;

	/**
	 * Set up the normalized state.
	 *
	 * @param string $search  Search term.
	 * @param string $source  Source filter.
	 * @param string $status  Status filter.
	 * @param int    $page    Page number, one based.
	 * @param int    $perPage Rows per page.
	 */
	private function __construct( string $search, string $source, string $status, int $page, int $perPage ) {
		$this->search  = $search;
		$this->source  = $source;
		$this->status  = $status;
		$this->page    = $page;
		$this->perPage = $perPage;
	}

	/**
	 * Normalize raw query arguments into filter state.
	 *
	 * This is the single normalization point for search, source, status,
	 * page and page size. Anything outside the accepted values falls
	 * back to the unfiltered default, the page is at least one, and the
	 * page size is clamped to between one and MAX_PER_PAGE.
	 *
	 * @param array<string, mixed> $input           Raw query arguments.
	 * @param int                  $defaultPerPage  Page size when the input has none.
	 * @return self The result.
	 */
	public static function fromInput( array $input, int $defaultPerPage ): self {
		$search = $input['s'] ?? '';
		$search = is_string( $search ) ? trim( substr( $search, 0, self::SEARCH_MAX ) ) : '';

		$source = $input['rk_source'] ?? self::SOURCE_ALL;
		$source = is_string( $source ) ? $source : self::SOURCE_ALL;

		if ( 'auto' !== $source && 'manual' !== $source ) {
			$source = self::SOURCE_ALL;
		}

		$status = $input['rk_status'] ?? self::STATUS_ALL;
		$status = is_string( $status ) ? $status : self::STATUS_ALL;

		if ( ! in_array( $status, self::statuses(), true ) ) {
			$status = self::STATUS_ALL;
		}

		$page = $input['rk_paged'] ?? 1;
		$page = is_numeric( $page ) ? (int) $page : 1;

		$perPage = $input['rk_per_page'] ?? $defaultPerPage;
		$perPage = is_numeric( $perPage ) ? (int) $perPage : $defaultPerPage;
		$perPage = max( 1, min( self::MAX_PER_PAGE, $perPage ) );

		return new self( $search, $source, $status, max( 1, $page ), $perPage );
	}

	/**
	 * Accepted status filter values, the four tab categories plus all.
	 *
	 * @return string[] The result.
	 */
	public static function statuses(): array {
		return [
			self::STATUS_ALL,
			InstantIndexingOutcomes::CATEGORY_ACCEPTED,
			InstantIndexingOutcomes::CATEGORY_PENDING,
			InstantIndexingOutcomes::CATEGORY_REJECTED,
			InstantIndexingOutcomes::CATEGORY_LIMITED,
		];
	}

	/**
	 * Status codes one tab category covers, empty for all.
	 *
	 * The map lives in InstantIndexingOutcomes, the single authority for
	 * the code to category mapping, so the SQL predicate and the PHP
	 * categoriser cannot silently diverge.
	 *
	 * @param string $status Status filter value.
	 * @return int[] The result.
	 */
	public static function codesFor( string $status ): array {
		if ( self::STATUS_ALL === $status ) {
			return [];
		}

		return InstantIndexingOutcomes::categoryCodes()[ $status ] ?? [];
	}

	/**
	 * Search term.
	 *
	 * @return string The result.
	 */
	public function search(): string {
		return $this->search;
	}

	/**
	 * Source filter.
	 *
	 * @return string The result.
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Status filter.
	 *
	 * @return string The result.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Page number.
	 *
	 * @return int The result.
	 */
	public function page(): int {
		return $this->page;
	}

	/**
	 * Rows per page.
	 *
	 * @return int The result.
	 */
	public function perPage(): int {
		return $this->perPage;
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
	 * Same filters on another page.
	 *
	 * @param int $page Page number, clamped to at least one.
	 * @return self The result.
	 */
	public function withPage( int $page ): self {
		return new self( $this->search, $this->source, $this->status, max( 1, $page ), $this->perPage );
	}
}
