<?php
/**
 * Schema graph generator.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;

/**
 * Assembles the Schema.org @graph from registered pieces.
 *
 * Pieces are consulted in registration order. Each piece is gated by
 * isNeeded(), wrapped in a per piece toggle filter, then built, filtered,
 * and merged. Empty builds are dropped. The assembled graph passes the
 * graph filter, then the normalizer, so third party output is cleaned
 * with the same rules as first party output.
 *
 * The graph is not load bearing for page rendering, so a piece or filter
 * that throws is logged and dropped. A failing piece is skipped exactly
 * like one that builds nothing, leaving the remaining graph intact.
 *
 * Disable states, from widest to narrowest: the module gate (boot
 * never runs, nothing renders), the page flag below (the whole graph
 * is suppressed, including the base identity nodes, matching the
 * metabox promise that no structured data prints for the post), the
 * per piece needs_{id} filter (one piece drops out), and unavailable
 * context (a piece builds nothing when its data is missing).
 *
 * Filter seams, few and stable:
 * rankkernel/schema/disabled (bool, Context) kills the page graph.
 * rankkernel/schema/needs_{id} (bool, Context) toggles one piece.
 * rankkernel/schema/piece/{id} (array, Context) edits one output.
 * rankkernel/schema/graph (array, Context) edits the full graph.
 * Third parties add pieces through register(), not new hooks.
 */
final class Generator {
	/**
	 * Registered pieces keyed by piece id.
	 *
	 * @var array<string, PieceInterface>
	 */
	private array $pieces = [];

	/**
	 * Register a piece (replaces any piece with the same id).
	 *
	 * @param PieceInterface $piece Piece to register.
	 */
	public function register( PieceInterface $piece ): void {
		$this->pieces[ $piece->getId() ] = $piece;
	}

	/**
	 * Generate the full JSON-LD document for a request context.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed> Document with @context and @graph.
	 */
	public function generate( Context $ctx ): array {
		$empty = [
			'@context' => 'https://schema.org',
			'@graph'   => [],
		];

		/**
		 * Page level kill switch for the whole graph.
		 *
		 * Runs before the stored per post flag, so integrations can
		 * suppress output for request shapes the payload cannot see
		 * (password walls, staging copies, consent states).
		 *
		 * @param bool    $disabled Whether the graph is disabled.
		 * @param Context $ctx      Current request context.
		 */
		try {
			$disabled = apply_filters( 'rankkernel/schema/disabled', false, $ctx ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
		} catch ( \Throwable $throwable ) {
			// A failing kill switch filter must not break the page. Treat it as
			// not disabled and log the failure.
			$this->logFailure( 'disabled', $throwable );
			$disabled = false;
		}

		if ( $disabled ) {
			return $empty;
		}

		$meta   = $ctx->meta();
		$schema = ( isset( $meta['schema'] ) && is_array( $meta['schema'] ) ) ? $meta['schema'] : [];

		if ( ! empty( $schema['disabled'] ) ) {
			return $empty;
		}

		$postId = $ctx->queriedId();

		if ( $postId > 0 && function_exists( 'post_password_required' ) && post_password_required( $postId ) ) {
			return $empty;
		}

		$graph = [];

		foreach ( $this->pieces as $id => $piece ) {
			try {
				$needed = $piece->isNeeded( $ctx );

				/**
				 * Toggle filter for a single piece.
				 *
				 * @param bool    $needed Whether the piece is needed.
				 * @param Context $ctx    Current request context.
				 */
				$needed = apply_filters( 'rankkernel/schema/needs_' . $id, $needed, $ctx ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
			} catch ( \Throwable $throwable ) {
				// One failing piece is dropped, the rest of the graph stays.
				$this->logFailure( 'needs_' . $id, $throwable );
				continue;
			}

			if ( ! $needed ) {
				continue;
			}

			try {
				$output = $piece->build( $ctx );

				/**
				 * Per piece output filter.
				 *
				 * A single node is an assoc array, a piece may also return
				 * a list of nodes, which merges item by item.
				 *
				 * @param array<mixed, mixed> $output Piece output.
				 * @param Context             $ctx    Current request context.
				 */
				$output = apply_filters( 'rankkernel/schema/piece/' . $id, $output, $ctx ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
			} catch ( \Throwable $throwable ) {
				// A piece that throws is dropped exactly like one that returns
				// nothing, so a single bad piece cannot remove the whole graph.
				$this->logFailure( 'piece/' . $id, $throwable );
				continue;
			}

			if ( ! is_array( $output ) || [] === $output ) {
				continue;
			}

			if ( array_is_list( $output ) ) {
				foreach ( $output as $item ) {
					if ( is_array( $item ) && [] !== $item ) {
						$graph[] = $item;
					}
				}

				continue;
			}

			$graph[] = $output;
		}

		/**
		 * Graph filter for the assembled graph.
		 *
		 * @param array<int, mixed> $graph Assembled piece outputs.
		 * @param Context           $ctx   Current request context.
		 */
		try {
			$graph = apply_filters( 'rankkernel/schema/graph', $graph, $ctx ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
		} catch ( \Throwable $throwable ) {
			// The assembled graph is already valid, so a failing third party
			// graph filter falls back to it instead of losing the schema.
			$this->logFailure( 'graph', $throwable );
		}

		if ( ! is_array( $graph ) ) {
			$graph = [];
		}

		return [
			'@context' => 'https://schema.org',
			'@graph'   => GraphNormalizer::normalize( $graph ),
		];
	}

	/**
	 * Log a failing piece or filter without letting it escape.
	 *
	 * A schema graph is not load bearing for rendering, so a failing piece or
	 * filter is logged and dropped rather than allowed to break the page.
	 *
	 * @param string     $stage     Failing stage identifier.
	 * @param \Throwable $throwable Captured failure.
	 */
	private function logFailure( string $stage, \Throwable $throwable ): void {
		if ( function_exists( 'do_action' ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
			do_action( 'rankkernel/schema/failed', $stage, $throwable );
		}

		if ( function_exists( 'wp_trigger_error' ) ) {
			wp_trigger_error( __METHOD__, $throwable->getMessage(), E_USER_WARNING );
		}
	}
}
