<?php
/**
 * Runner double for importer controller tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * Runner double recording its arguments.
 *
 * Returns a fixed chunk result so controller tests assert wiring,
 * not runner behavior.
 */
final class ImportControllerStubRunner {
	/**
	 * Last run arguments.
	 *
	 * @var array<string, mixed>
	 */
	public array $lastArgs = [];

	/**
	 * Run one chunk.
	 *
	 * @param string               $source   Source id.
	 * @param string               $kind     Chunk kind.
	 * @param int                  $offset   Chunk offset.
	 * @param int                  $limit    Chunk size.
	 * @param string               $mode     Conflict mode.
	 * @param array<int, string>   $excludes Excluded ref ids.
	 * @param array<string, mixed> $options  Extra options.
	 * @param string               $owner    Run id that owns the import lock.
	 * @return array<string, mixed> The result.
	 */
	public function run( string $source, string $kind, int $offset, int $limit, string $mode, array $excludes = [], array $options = [], string $owner = '' ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- double records every argument into lastArgs via compact below, which the sniff cannot see.
		$this->lastArgs = compact( 'source', 'kind', 'offset', 'limit', 'mode', 'excludes', 'options', 'owner' );

		return [
			'applied' => 2,
			'skipped' => 1,
			'failed'  => 0,
			'done'    => 3,
			'total'   => 3,
		];
	}
}
