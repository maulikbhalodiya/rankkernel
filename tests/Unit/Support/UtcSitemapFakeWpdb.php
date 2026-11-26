<?php
/**
 * Minimal wpdb double for sitemap UTC instant tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

/**
 * Minimal wpdb double returning configured dates and rows.
 */
final class UtcSitemapFakeWpdb {
	/**
	 * Posts table name.
	 *
	 * @var string
	 */
	public $posts = 'wp_posts';

	/**
	 * Post meta table name.
	 *
	 * @var string
	 */
	public $postmeta = 'wp_postmeta';

	/**
	 * Terms table name.
	 *
	 * @var string
	 */
	public $terms = 'wp_terms';

	/**
	 * Term taxonomy table name.
	 *
	 * @var string
	 */
	public $term_taxonomy = 'wp_term_taxonomy';

	/**
	 * Term relationships table name.
	 *
	 * @var string
	 */
	public $term_relationships = 'wp_term_relationships';

	/**
	 * Term meta table name.
	 *
	 * @var string
	 */
	public $termmeta = 'wp_termmeta';

	/**
	 * Users table name.
	 *
	 * @var string
	 */
	public $users = 'wp_users';

	/**
	 * User meta table name.
	 *
	 * @var string
	 */
	public $usermeta = 'wp_usermeta';

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * MAX date returned by get_var.
	 *
	 * @var string
	 */
	public string $maxDate = '';

	/**
	 * Rows returned by get_results.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $resultRows = [];

	/**
	 * Mirror wpdb::prepare without altering patterns used by the test double.
	 *
	 * @param string $query Query.
	 * @param mixed  ...$args Args.
	 * @return string
	 */
	public function prepare( string $query, mixed ...$args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors wpdb signature.
		return $query;
	}

	/**
	 * Escape LIKE fragments.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Return the configured MAX date.
	 *
	 * @param string|null $query Query.
	 * @return mixed
	 */
	public function get_var( ?string $query = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors wpdb signature.
		return $this->maxDate;
	}

	/**
	 * Return configured entry rows.
	 *
	 * @param string|null $query  Query.
	 * @param mixed       $output Output mode.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( ?string $query = null, mixed $output = null ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors wpdb signature.
		return $this->resultRows;
	}
}
