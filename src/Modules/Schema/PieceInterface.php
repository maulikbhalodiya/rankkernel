<?php
/**
 * Schema piece contract.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema;

/**
 * Contract for a single Schema.org piece of the @graph.
 */
interface PieceInterface {
    public function getId(): string;              // e.g. 'article'
    public function isNeeded( \RankKernel\Modules\Metadata\Context $ctx ): bool;
    /**
     * @return array<string, mixed>
     */
    public function build( \RankKernel\Modules\Metadata\Context $ctx ): array;
}
