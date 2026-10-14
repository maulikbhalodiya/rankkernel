<?php
/**
 * Rank Math mapper tests, rank_math postmeta to our payload fields.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\RankMathMapper;

/**
 * Rank Math Mapper Test.
 */
final class RankMathMapperTest extends TestCase {
	/**
	 * Test a fully populated Rank Math row maps every field.
	 */
	public function test_full_row_maps_every_field(): void {
		$result = RankMathMapper::mapPost(
			[
				'rank_math_title'             => '%title% %sep% %sitename%',
				'rank_math_description'       => 'A fine description.',
				'rank_math_canonical_url'     => 'https://example.com/canonical/',
				'rank_math_focus_keyword'     => 'focus key',
				'rank_math_facebook_title'    => 'OG title',
				'rank_math_facebook_image'    => 'https://example.com/og.jpg',
				'rank_math_facebook_image_id' => '42',
				'rank_math_twitter_title'     => 'TW title',
				'rank_math_breadcrumb_title'  => 'Crumb',
				'rank_math_pillar_content'    => 'on',
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
		$this->assertSame( 42, $result['mapped']['og']['image_id'] );
		$this->assertSame( 'TW title', $result['mapped']['twitter']['title'] );
		$this->assertSame( 'Crumb', $result['mapped']['flags']['breadcrumb_title'] );
		$this->assertTrue( $result['mapped']['flags']['pillar'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Test an empty Rank Math row maps nothing.
	 */
	public function test_empty_row_maps_nothing(): void {
		$result = RankMathMapper::mapPost( [], [], 'post' );

		$this->assertSame( [], $result['mapped'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Test explicit noindex and nofollow directives map to inverted booleans.
	 */
	public function test_explicit_noindex_nofollow_map(): void {
		$result = RankMathMapper::mapPost(
			[ 'rank_math_robots' => [ 'noindex', 'nofollow' ] ],
			[],
			'post'
		);

		$this->assertFalse( $result['mapped']['robots']['index'] );
		$this->assertFalse( $result['mapped']['robots']['follow'] );
	}

	/**
	 * Test an empty robots array falls back to the sitewide default.
	 */
	public function test_empty_robots_falls_back_to_sitewide_default(): void {
		$indexed = RankMathMapper::mapPost( [ 'rank_math_robots' => [] ], [ 'pt_post_robots' => [] ], 'post' );
		$this->assertArrayNotHasKey( 'robots', $indexed['mapped'] );

		$noindexed = RankMathMapper::mapPost( [ 'rank_math_robots' => [] ], [ 'pt_post_robots' => [ 'noindex' ] ], 'post' );
		$this->assertFalse( $noindexed['mapped']['robots']['index'] );
	}

	/**
	 * Test an unknown directive maps the known ones and warns about the rest.
	 */
	public function test_unknown_directive_warns(): void {
		$result = RankMathMapper::mapPost(
			[ 'rank_math_robots' => [ 'nosnippet', 'bogus' ] ],
			[],
			'post'
		);

		$this->assertTrue( $result['mapped']['robots']['nosnippet'] );
		$this->assertSame( [ 'robots: unknown directive bogus' ], $result['warnings'] );
	}
}
