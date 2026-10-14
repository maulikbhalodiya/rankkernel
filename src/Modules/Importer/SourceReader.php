<?php
/**
 * Source reader contract for importer Detect.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * A source plugin the importer can read from.
 *
 * Readers are read only by contract: detect counts importable rows and
 * never writes, so running Detect on a live site is always safe.
 */
interface SourceReader {
	/**
	 * Machine id, yoast, rank-math or aioseo.
	 *
	 * @return string The id.
	 */
	public function id(): string;

	/**
	 * Human label, plain string, never translated here.
	 *
	 * Translation happens at render time, following the module getName
	 * precedent, so readers stay usable outside admin screens.
	 *
	 * @return string The label.
	 */
	public function label(): string;

	/**
	 * Count importable rows per kind.
	 *
	 * @return array{posts:int,terms:int,settings:bool,redirects:int} The counts.
	 */
	public function detect(): array;

	/**
	 * Fetch one post chunk in stable id order.
	 *
	 * Source shaped data only; the run phase attaches our stored
	 * payload for conflict marking, so readers never read our tables.
	 *
	 * @param int $offset Chunk offset.
	 * @param int $limit Chunk size.
	 * @return array<int, array{id: int, type: string, data: array<string, mixed>}> The items.
	 */
	public function fetchPosts( int $offset, int $limit ): array;

	/**
	 * Fetch one term chunk in stable id order.
	 *
	 * @param int $offset Chunk offset.
	 * @param int $limit Chunk size.
	 * @return array<int, array{id: int, taxonomy: string, data: array<string, mixed>}> The items.
	 */
	public function fetchTerms( int $offset, int $limit ): array;

	/**
	 * Fetch the raw sitewide blob the mapper accepts.
	 *
	 * Shape depends on the source and matches what PreviewBuilder
	 * passes to the sitewide mapper.
	 *
	 * @return array<string, mixed> The raw blob.
	 */
	public function fetchSitewideRaw(): array;

	/**
	 * Fetch raw redirect rows the redirect mapper accepts.
	 *
	 * @return array<int, array<string, mixed>> The raw rows.
	 */
	public function fetchRedirectsRaw(): array;
}
