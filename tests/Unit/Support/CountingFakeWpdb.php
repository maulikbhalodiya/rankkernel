<?php
/**
 * Query counting wpdb double.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

/**
 * A wpdb double that records every query instead of running one.
 *
 * Every accessor answers with an empty result and bumps the counter, so a test
 * can assert that a code path issued no SQL at all. That is a stronger claim
 * than asserting a collaborator was not invoked, because a builder reached
 * through some other route still shows up here.
 */
final class CountingFakeWpdb {
	/**
	 * Number of queries issued through this double.
	 *
	 * @var int
	 */
	public int $queries = 0;

	/**
	 * The SQL of every query issued, in order.
	 *
	 * @var string[]
	 */
	public array $log = [];

	/**
	 * Record one query.
	 *
	 * @param string $sql SQL text.
	 */
	private function record( string $sql ): void {
		++$this->queries;

		$this->log[] = $sql;
	}

	/**
	 * Prepare.
	 *
	 * @param string $query Query.
	 * @param mixed  ...$args Args.
	 * @return string The query.
	 */
	public function prepare( string $query, mixed ...$args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb prepare signature.
		return $query;
	}

	/**
	 * Get results.
	 *
	 * @param string|null $query  Query.
	 * @param mixed       $output Output.
	 * @return array<int, array<string, mixed>> Always empty.
	 */
	public function get_results( ?string $query = null, mixed $output = null ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb get_results signature.
		$this->record( (string) $query );

		return [];
	}

	/**
	 * Get var.
	 *
	 * @param string|null $query Query.
	 * @return mixed Always null.
	 */
	public function get_var( ?string $query = null ): mixed {
		$this->record( (string) $query );

		return null;
	}

	/**
	 * Get row.
	 *
	 * @param string|null $query Query.
	 * @param string      $output Output.
	 * @param int         $y      Row offset.
	 * @return object Always an empty object.
	 */
	public function get_row( ?string $query = null, string $output = 'OBJECT', int $y = 0 ): object { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb get_row signature.
		$this->record( (string) $query );

		return (object) [];
	}

	/**
	 * Get col.
	 *
	 * @param string|null $query Query.
	 * @param int         $x     Column offset.
	 * @return mixed Always null.
	 */
	public function get_col( ?string $query = null, int $x = 0 ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- test double mirrors the $wpdb get_col signature.
		$this->record( (string) $query );

		return null;
	}
}
