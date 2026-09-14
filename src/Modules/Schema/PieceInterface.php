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
	/**
	 * Get Id.
	 *
	 * @return string The result.
	 */
	public function getId(): string;              // E.g. article identifier.
	/**
	 * Is Needed.
	 *
	 * @param \RankKernel\Modules\Metadata\Context $ctx Ctx.
	 * @return bool The result.
	 */
	public function isNeeded( \RankKernel\Modules\Metadata\Context $ctx ): bool;
	/**
	 * Build.
	 *
	 * @param \RankKernel\Modules\Metadata\Context $ctx Ctx.
	 * @return array<string, mixed>
	 */
	public function build( \RankKernel\Modules\Metadata\Context $ctx ): array;
}
