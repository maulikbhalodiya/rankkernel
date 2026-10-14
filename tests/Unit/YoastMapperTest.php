<?php
/**
 * Yoast mapper tests, legacy postmeta to our payload fields.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\YoastMapper;

/**
 * Yoast Mapper Test.
 */
final class YoastMapperTest extends TestCase {
	/**
	 * Test a fully populated Yoast row maps every field.
	 */
	public function test_full_row_maps_every_field(): void {
		$result = YoastMapper::mapPost(
			[
				'_yoast_wpseo_title'           => '%%title%% %%sep%% %%sitename%%',
				'_yoast_wpseo_metadesc'        => 'A fine description.',
				'_yoast_wpseo_canonical'       => 'https://example.com/canonical/',
				'_yoast_wpseo_focuskw'         => 'focus key',
				'_yoast_wpseo_opengraph-title' => 'OG title',
				'_yoast_wpseo_opengraph-image' => 'https://example.com/og.jpg',
				'_yoast_wpseo_twitter-title'   => 'TW title',
				'_yoast_wpseo_bctitle'         => 'Crumb',
				'_yoast_wpseo_is_cornerstone'  => '1',
			],
			[],
			'post'
		);

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $result['mapped']['title'] );
		$this->assertSame( 'A fine description.', $result['mapped']['description'] );
		$this->assertSame( 'https://example.com/canonical/', $result['mapped']['canonical'] );
		$this->assertSame( [ 'focus key' ], $result['mapped']['focus_keywords'] );
		$this->assertSame( 'OG title', $result['mapped']['og']['title'] );
		$this->assertSame( 'https://example.com/og.jpg', $result['mapped']['og']['image'] );
		$this->assertSame( 'TW title', $result['mapped']['twitter']['title'] );
		$this->assertSame( 'Crumb', $result['mapped']['flags']['breadcrumb_title'] );
		$this->assertTrue( $result['mapped']['flags']['cornerstone'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Test an empty Yoast row maps nothing.
	 */
	public function test_empty_row_maps_nothing(): void {
		$result = YoastMapper::mapPost( [], [], 'post' );

		$this->assertSame( [], $result['mapped'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Test explicit noindex and nofollow flags map to inverted booleans.
	 */
	public function test_explicit_noindex_nofollow_map(): void {
		$result = YoastMapper::mapPost(
			[
				'_yoast_wpseo_meta-robots-noindex'  => '1',
				'_yoast_wpseo_meta-robots-nofollow' => '1',
			],
			[],
			'post'
		);

		$this->assertFalse( $result['mapped']['robots']['index'] );
		$this->assertFalse( $result['mapped']['robots']['follow'] );
	}

	/**
	 * Test the sitewide default applies when the post row carries a zero.
	 */
	public function test_zero_falls_back_to_sitewide_default(): void {
		$meta = [ '_yoast_wpseo_meta-robots-noindex' => '0' ];

		$indexed = YoastMapper::mapPost( $meta, [ 'noindex-post' => false ], 'post' );
		$this->assertTrue( $indexed['mapped']['robots']['index'] );

		$noindexed = YoastMapper::mapPost( $meta, [ 'noindex-post' => true ], 'post' );
		$this->assertFalse( $noindexed['mapped']['robots']['index'] );
	}

	/**
	 * Test a missing triple key falls back to the sitewide default.
	 */
	public function test_missing_triple_falls_back_to_sitewide_default(): void {
		$result = YoastMapper::mapPost( [], [ 'noindex-page' => true ], 'page' );

		$this->assertFalse( $result['mapped']['robots']['index'] );
	}

	/**
	 * Test force index wins over a noindex sitewide default.
	 */
	public function test_force_index_wins_over_sitewide_default(): void {
		$result = YoastMapper::mapPost(
			[ '_yoast_wpseo_meta-robots-noindex' => '2' ],
			[ 'noindex-post' => true ],
			'post'
		);

		$this->assertTrue( $result['mapped']['robots']['index'] );
	}

	/**
	 * Test the advanced comma list maps known directives.
	 */
	public function test_advanced_list_maps_known_directives(): void {
		$result = YoastMapper::mapPost(
			[ '_yoast_wpseo_meta-robots-adv' => 'noarchive,noimageindex,nosnippet' ],
			[],
			'post'
		);

		$this->assertTrue( $result['mapped']['robots']['noarchive'] );
		$this->assertTrue( $result['mapped']['robots']['noimageindex'] );
		$this->assertTrue( $result['mapped']['robots']['nosnippet'] );
	}

	/**
	 * Test unknown tokens in the title warn instead of mapping silently.
	 */
	public function test_unknown_tokens_warn(): void {
		$result = YoastMapper::mapPost(
			[ '_yoast_wpseo_title' => '%%title%% %%cf_color%%' ],
			[],
			'post'
		);

		$this->assertSame( '%%title%% %%cf_color%%', $result['mapped']['title'] );
		$this->assertSame( [ 'title: unknown token %%cf_color%%' ], $result['warnings'] );
	}
}
