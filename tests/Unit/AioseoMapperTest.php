<?php
/**
 * Tests for the AIOSEO row mapper.
 *
 * @package RankKernel
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\AioseoMapper;

/**
 * AIOSEO Mapper Test.
 */
final class AioseoMapperTest extends TestCase {
	/**
	 * Build a fully populated AIOSEO row fixture.
	 */
	private function fullRow(): array {
		return [
			'title'               => '#post_title #separator_sa #site_title',
			'description'         => '#post_excerpt',
			'canonical_url'       => 'https://example.com/canonical/',
			'keyphrases'          => '{"focus":{"keyphrase":"Test Focus"}}',
			'og_title'            => 'OG Title',
			'og_description'      => 'OG Desc',
			'og_image_type'       => 'custom',
			'og_image_url'        => 'https://example.com/og.jpg',
			'twitter_use_og'      => 1,
			'twitter_title'       => '',
			'twitter_image_type'  => 'default',
			'twitter_image_url'   => '',
			'robots_default'      => 0,
			'robots_noindex'      => 1,
			'robots_nofollow'     => 1,
			'robots_noarchive'    => 1,
			'robots_nosnippet'    => 0,
			'robots_noimageindex' => 0,
			'robots_max_snippet'  => -1,
		];
	}

	/**
	 * Test a fully populated AIOSEO row maps every field.
	 */
	public function test_full_row_maps_all_fields(): void {
		$result = AioseoMapper::mapRow( $this->fullRow() );

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $result['mapped']['title'] );
		$this->assertSame( '%%excerpt%%', $result['mapped']['description'] );
		$this->assertSame( 'https://example.com/canonical/', $result['mapped']['canonical'] );
		$this->assertSame( [ 'Test Focus' ], $result['mapped']['focus_keywords'] );
		$this->assertSame( 'OG Title', $result['mapped']['og']['title'] );
		$this->assertSame( 'OG Desc', $result['mapped']['og']['description'] );
		$this->assertSame( 'https://example.com/og.jpg', $result['mapped']['og']['image'] );
		$this->assertSame( 'OG Title', $result['mapped']['twitter']['title'] );
		$this->assertSame( 'https://example.com/og.jpg', $result['mapped']['twitter']['image'] );
		$this->assertFalse( $result['mapped']['robots']['index'] );
		$this->assertFalse( $result['mapped']['robots']['follow'] );
		$this->assertTrue( $result['mapped']['robots']['noarchive'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Test a default robots row inherits sitewide without emitting rows.
	 */
	public function test_default_robots_inherits_sitewide_without_rows(): void {
		$row                   = $this->fullRow();
		$row['robots_default'] = 1;

		$result = AioseoMapper::mapRow( $row );

		$this->assertArrayNotHasKey( 'robots', $result['mapped'] );
		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $result['mapped']['title'] );
	}

	/**
	 * Test malformed keyphrases JSON warns instead of mapping.
	 */
	public function test_malformed_keyphrases_warns(): void {
		$row               = $this->fullRow();
		$row['keyphrases'] = 'not json{';

		$result = AioseoMapper::mapRow( $row );

		$this->assertArrayNotHasKey( 'focus_keywords', $result['mapped'] );
		$this->assertNotEmpty( $result['warnings'] );
	}

	/**
	 * Test an empty row maps nothing and warns nothing.
	 */
	public function test_empty_row_maps_nothing(): void {
		$result = AioseoMapper::mapRow( [ 'robots_default' => 1 ] );

		$this->assertSame( [], $result['mapped'] );
		$this->assertSame( [], $result['warnings'] );
	}
}
