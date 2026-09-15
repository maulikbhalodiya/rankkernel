<?php
/**
 * Breadcrumb item tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Breadcrumbs\Item;

/**
 * Breadcrumb Item Test.
 */
final class BreadcrumbsItemTest extends TestCase {
	/**
	 * Test constructor stores label and defaults.
	 */
	public function test_constructor_stores_label_and_defaults(): void {
		$item = new Item( 'Home' );

		$this->assertSame( 'Home', $item->label() );
		$this->assertSame( '', $item->url() );
		$this->assertFalse( $item->allowHtml() );
		$this->assertFalse( $item->schemaExcluded() );
	}

	/**
	 * Test constructor stores explicit values.
	 */
	public function test_constructor_stores_explicit_values(): void {
		$item = new Item( 'Page 2', '', false, true );

		$this->assertSame( 'Page 2', $item->label() );
		$this->assertSame( '', $item->url() );
		$this->assertTrue( $item->schemaExcluded() );
	}

	/**
	 * Test linked item keeps its URL.
	 */
	public function test_linked_item_keeps_url(): void {
		$item = new Item( 'News', 'https://example.com/news/' );

		$this->assertSame( 'News', $item->label() );
		$this->assertSame( 'https://example.com/news/', $item->url() );
	}

	/**
	 * Test allow html opt in sticks.
	 */
	public function test_allow_html_opt_in_sticks(): void {
		$item = new Item( 'Bold', 'https://example.com/', true );

		$this->assertTrue( $item->allowHtml() );
	}

	/**
	 * Test state is readonly.
	 */
	public function test_state_is_readonly(): void {
		$item = new Item( 'Home' );

		$this->expectException( \Error::class );

		$ref = new \ReflectionProperty( $item, 'label' );
		$ref->setAccessible( true );
		$ref->setValue( $item, 'Changed' );
	}
}
