<?php
/**
 * WooCommerce seam double for product piece tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

use RankKernel\Modules\Schema\Pieces\ProductPiece;

/**
 * Test double exercising the WooCommerce seam without loading Woo.
 *
 * Forces the Woo path on and feeds canned values, so tests cover the
 * mapping, the override order, and the absent Woo path.
 */
class WooProductDouble extends ProductPiece {
	/**
	 * Canned Woo values keyed by field name.
	 *
	 * @var array<string, string>
	 */
	public static array $woo = [];

	/**
	 * Forced availability flag.
	 *
	 * @var bool
	 */
	public static bool $available = true;

	/**
	 * Is Woo Commerce Available.
	 *
	 * @return array<string, string>
	 */
	protected function isWooCommerceAvailable(): bool {
		return self::$available;
	}

	/**
	 * Read Woo Fields.
	 *
	 * @param int $postId Post Id.
	 * @return array<string, string>
	 */
	protected function readWooFields( int $postId ): array {
		return self::$woo;
	}
}
