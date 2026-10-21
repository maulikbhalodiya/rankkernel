<?php
/**
 * A run that stopped with partial progress.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Import Run Exception.
 *
 * Carries the counts of the work that did happen before the failure,
 * so the screen can report the real progress instead of a bare error
 * message that looks like nothing happened.
 */
final class ImportRunException extends \RuntimeException {
	/**
	 * Counts of the work that completed before the failure.
	 *
	 * @var array<string, int>
	 */
	private array $counts;

	/**
	 * Constructor.
	 *
	 * @param string             $message Failure message.
	 * @param array<string, int> $counts Progress before the failure.
	 * @param \Throwable|null    $previous Previous failure.
	 */
	public function __construct( string $message, array $counts = [], ?\Throwable $previous = null ) {
		parent::__construct( $message, 0, $previous );

		$this->counts = array_map( 'intval', $counts );
	}

	/**
	 * The counts of the work that completed before the failure.
	 *
	 * @return array<string, int> The counts.
	 */
	public function counts(): array {
		return $this->counts;
	}
}
