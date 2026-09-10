<?php
/**
 * Schema graph generator.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema;

use RankKernel\Modules\Metadata\Context;

/**
 * Assembles the Schema.org @graph from registered pieces.
 *
 * Pieces are consulted in registration order. Each piece is gated by
 * isNeeded(), wrapped in a per piece toggle filter, then built, filtered,
 * and merged. Empty builds are dropped.
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
        $graph = [];

        $meta   = $ctx->meta();
        $schema = ( isset($meta['schema']) && is_array($meta['schema']) ) ? $meta['schema'] : [];

        if (! empty($schema['disabled'])) {
            return [
                '@context' => 'https://schema.org',
                '@graph'   => [],
            ];
        }

        foreach ($this->pieces as $id => $piece) {
            $needed = $piece->isNeeded($ctx);

            /**
             * Toggle filter for a single piece.
             *
             * @param bool    $needed Whether the piece is needed.
             * @param Context $ctx    Current request context.
             */
            $needed = apply_filters('rankkernel/schema/needs_' . $id, $needed, $ctx);

            if (! $needed) {
                continue;
            }

            $output = $piece->build($ctx);

            /**
             * Per piece output filter.
             *
             * @param array<string, mixed> $output Piece output.
             * @param Context              $ctx    Current request context.
             */
            $output = apply_filters('rankkernel/schema/piece/' . $id, $output, $ctx);

            if (! is_array($output) || [] === $output) {
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
        $graph = apply_filters('rankkernel/schema/graph', $graph, $ctx);

        if (! is_array($graph)) {
            $graph = [];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph'   => array_values($graph),
        ];
    }
}
