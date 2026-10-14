<?php
/**
 * Term mapper tests for the importer source mappers.
 *
 * Terms reuse the post payload shape under `_rankkernel_term_data`
 * (Metadata Context reads both through the same sanitize path), but
 * each source stores term SEO under different keys than post SEO, so
 * every mapper gets its own term entry point with its own guard.
 *
 * @package RankKernel
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\AioseoMapper;
use RankKernel\Modules\Importer\RankMathMapper;
use RankKernel\Modules\Importer\YoastMapper;

/**
 * Term mapping guards for all three source mappers.
 */
final class TermMapperTest extends TestCase {
	/**
	 * Yoast taxonomy rows map title, description and noindex.
	 */
	public function test_yoast_term_row_maps_title_desc_noindex(): void {
		$result = YoastMapper::mapTerm(
			[
				'wpseo_title'   => '%%title%% %%sep%% Shop',
				'wpseo_desc'    => 'Buy things',
				'wpseo_noindex' => 'noindex',
			],
			[],
			'product_cat'
		);

		$this->assertSame( '%%title%% %%sep%% Shop', $result['mapped']['title'] );
		$this->assertSame( 'Buy things', $result['mapped']['description'] );
		$this->assertFalse( $result['mapped']['robots']['index'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Empty Yoast taxonomy rows emit nothing so defaults never flood preview.
	 */
	public function test_yoast_empty_term_row_emits_nothing(): void {
		$result = YoastMapper::mapTerm( [], [], 'category' );

		$this->assertSame( [], $result['mapped'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Rank Math termmeta reuses the post contract with taxonomy sitewide slice.
	 */
	public function test_rank_math_termmeta_maps_with_tax_sitewide(): void {
		$result = RankMathMapper::mapTerm(
			[
				'rank_math_title'  => '%title% Deals',
				'rank_math_robots' => [ 'noindex' ],
			],
			[],
			'product_cat'
		);

		$this->assertSame( '%%title%% Deals', $result['mapped']['title'] );
		$this->assertFalse( $result['mapped']['robots']['index'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * A term with no robots of its own inherits the taxonomy default.
	 */
	public function test_rank_math_term_inherits_taxonomy_noindex(): void {
		$result = RankMathMapper::mapTerm(
			[ 'rank_math_title' => 'Deals' ],
			[ 'tax_product_cat_robots' => [ 'noindex' ] ],
			'product_cat'
		);

		$this->assertFalse( $result['mapped']['robots']['index'] );
	}

	/**
	 * AIOSEO termmeta maps through the hash token translator.
	 */
	public function test_aioseo_termmeta_maps_title_and_description(): void {
		$result = AioseoMapper::mapTermRow(
			[
				'title'       => '#post_title Deals',
				'description' => 'Term desc',
			]
		);

		$this->assertSame( '%%title%% Deals', $result['mapped']['title'] );
		$this->assertSame( 'Term desc', $result['mapped']['description'] );
		$this->assertSame( [], $result['warnings'] );
	}
}
