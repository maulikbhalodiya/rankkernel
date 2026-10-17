<?php
/**
 * Canned source reader double for importer controller tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use RankKernel\Modules\Importer\SourceReader;

/**
 * Canned source reader double.
 *
 * Answers one fixed post list so controller tests never touch
 * source tables.
 */
final class ImportControllerStubReader implements SourceReader {
	/**
	 * Post items.
	 *
	 * @var array<int, array{id: int, type: string, data: array<string, mixed>}>
	 */
	private array $posts;

	/**
	 * Term items.
	 *
	 * @var array<int, array{id: int, taxonomy: string, data: array<string, mixed>}>
	 */
	private array $terms;

	/**
	 * Raw sitewide blob.
	 *
	 * @var array<string, mixed>
	 */
	private array $sitewide;

	/**
	 * Build the double.
	 *
	 * @param array<int, array{id: int, type: string, data: array<string, mixed>}>     $posts    Post items.
	 * @param array<string, mixed>                                                     $sitewide Raw sitewide blob.
	 * @param array<int, array{id: int, taxonomy: string, data: array<string, mixed>}> $terms    Term items.
	 */
	public function __construct( array $posts = [], array $sitewide = [], array $terms = [] ) {
		$this->posts    = $posts;
		$this->sitewide = $sitewide;
		$this->terms    = $terms;
	}

	/**
	 * Machine id.
	 *
	 * @return string The id.
	 */
	public function id(): string {
		return 'yoast';
	}

	/**
	 * Human label.
	 *
	 * @return string The label.
	 */
	public function label(): string {
		return 'Yoast';
	}

	/**
	 * Count importable rows.
	 *
	 * @return array{posts:int,terms:int,settings:bool,redirects:int} The counts.
	 */
	public function detect(): array {
		return [
			'posts'     => count( $this->posts ),
			'terms'     => 0,
			'settings'  => false,
			'redirects' => 0,
		];
	}

	/**
	 * Fetch one post chunk.
	 *
	 * @param int    $offset Chunk offset.
	 * @param int    $limit Chunk size.
	 * @param string $search Optional search string.
	 * @return array<int, array{id: int, type: string, data: array<string, mixed>}> The items.
	 */
	public function fetchPosts( int $offset, int $limit, string $search = '' ): array {
		return array_slice( $this->posts, $offset, $limit );
	}

	/**
	 * Fetch one term chunk.
	 *
	 * @param int    $offset Chunk offset.
	 * @param int    $limit Chunk size.
	 * @param string $search Optional search string.
	 * @return array<int, array{id: int, taxonomy: string, data: array<string, mixed>}> The items.
	 */
	public function fetchTerms( int $offset, int $limit, string $search = '' ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- double mirrors the SourceReader signature.
		return array_slice( $this->terms, $offset, $limit );
	}

	/**
	 * Fetch the raw sitewide blob.
	 *
	 * @return array<string, mixed> The raw blob.
	 */
	public function fetchSitewideRaw(): array {
		return $this->sitewide;
	}

	/**
	 * Fetch raw redirect rows, always empty.
	 *
	 * @return array<int, array<string, mixed>> The raw rows.
	 */
	public function fetchRedirectsRaw(): array {
		return [];
	}
}
