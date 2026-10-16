<?php
/**
 * Preview row value object for the importer dry run.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * One preview row, takable or skipped with its reason.
 *
 * Rows are display data only: the run phase re-derives apply
 * decisions through PayloadImporter, so preview can never drift
 * from what the import actually does.
 */
final class Row {
	/**
	 * Constructor.
	 *
	 * @param string               $kind          Row kind, post, term, sitewide or redirect.
	 * @param string               $source        Source id, yoast, rank-math or aioseo.
	 * @param int                  $refId         Post or term id, zero otherwise.
	 * @param string               $label         Human label for the preview table.
	 * @param array<string, mixed> $fields        Incoming fields or redirect data.
	 * @param array<string, mixed> $conflicts     Field to existing value in skip mode.
	 * @param string[]             $warnings      Mapper warnings, unknown tokens and friends.
	 * @param string               $skippedReason Empty when the row is takable.
	 * @param string               $slot          Sitewide slot name, empty otherwise.
	 * @param bool                 $recommended   True for the recommended slot candidate.
	 * @param bool                 $bulk          True for rules applied to many posts at run time.
	 * @param string               $identity      Stable unique id for exclusions and choices: post:{id}, term:{taxonomy}:{id}, sitewide:{slot}:{source}, bulk:{kind}:{type}, redirect:{match}:{source}. Empty on skipped rows, which are never excludable.
	 */
	public function __construct(
		public readonly string $kind,
		public readonly string $source,
		public readonly int $refId,
		public readonly string $label,
		public readonly array $fields = [],
		public readonly array $conflicts = [],
		public readonly array $warnings = [],
		public readonly string $skippedReason = '',
		public readonly string $slot = '',
		public readonly bool $recommended = false,
		public readonly bool $bulk = false,
		public readonly string $identity = ''
	) {}
}
