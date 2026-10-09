<?php
/**
 * Module enable map normalization tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\ModuleEnableMap;

/**
 * Module Enable Map Test.
 */
final class ModuleEnableMapTest extends TestCase {

	/**
	 * A list shaped option normalizes to itself, deduplicated.
	 */
	public function test_normalize_list_shape(): void {
		$this->assertSame(
			[ 'metadata', 'sitemaps' ],
			ModuleEnableMap::normalizeList( [ 'metadata', 'sitemaps', 'metadata' ] )
		);
	}

	/**
	 * An assoc shaped option keeps truthy keys and drops falsy ones.
	 */
	public function test_normalize_assoc_shape(): void {
		$this->assertSame(
			[ 'metadata', 'sitemaps' ],
			ModuleEnableMap::normalizeList(
				[
					'metadata' => true,
					'sitemaps' => 1,
					'robots'   => false,
					''         => true,
				]
			)
		);
	}

	/**
	 * Garbage in normalizes to an empty list, never to enabled modules.
	 */
	public function test_normalize_garbage(): void {
		$this->assertSame( [], ModuleEnableMap::normalizeList( [] ) );
		$this->assertSame( [], ModuleEnableMap::normalizeList( [ '', 0, false, null ] ) );
		$this->assertSame( [], ModuleEnableMap::normalizeList( [ 'x' => false ] ) );
	}
}
