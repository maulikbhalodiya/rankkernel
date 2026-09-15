<?php
/**
 * Counting wpdb double for breadcrumb query tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

/**
 * Counting wpdb double, every database call bumps the counter.
 */
final class BreadcrumbsCountingWpdb {
	/**
	 * Query count.
	 *
	 * @var int
	 */
	public int $count = 0;

	/**
	 * Count every database call.
	 *
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Call arguments.
	 * @return null The result.
	 */
	public function __call( string $method, array $args ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the wpdb surface, arguments are intentionally uninspected.
		++$this->count;

		return null;
	}
}
