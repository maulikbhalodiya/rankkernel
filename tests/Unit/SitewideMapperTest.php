<?php
/**
 * Sitewide mapper tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\SitewideMapper;

defined( 'ABSPATH' ) || exit;

/**
 * Sitewide template candidates, noindex rules, and skips per source.
 */
final class SitewideMapperTest extends TestCase {
	/**
	 * Yoast per-type templates become single-slot candidates with post recommended.
	 */
	public function test_yoast_templates_become_candidates_with_post_recommended(): void {
		$result = SitewideMapper::mapYoast(
			[
				'title-post'    => '%%title%% %%sep%% %%sitename%%',
				'metadesc-post' => '%%excerpt%%',
				'title-page'    => '%%title%% %%sep%% %%sitename%%',
			]
		);

		$this->assertCount( 3, $result['candidates'] );
		$this->assertSame( 'title_template', $result['candidates'][0]['slot'] );
		$this->assertSame( 'post', $result['candidates'][0]['source'] );
		$this->assertTrue( $result['candidates'][0]['recommended'] );
		$this->assertFalse( $result['candidates'][2]['recommended'] );
		$this->assertSame( [], $result['noindex_rules'] );
	}

	/**
	 * Yoast post-type noindex flags become bulk rules, tax templates are skipped by name.
	 */
	public function test_yoast_noindex_and_tax_templates(): void {
		$result = SitewideMapper::mapYoast(
			[
				'title-post'           => '%%title%%',
				'noindex-post'         => false,
				'noindex-page'         => true,
				'title-tax-category'   => '%%term_title%%',
				'noindex-tax-category' => 'index',
			]
		);

		$this->assertSame(
			[
				[
					'kind'  => 'post',
					'type'  => 'page',
					'index' => false,
				],
			],
			$result['noindex_rules']
		);
		$this->assertSame( 'title-tax-category', $result['skipped'][0]['label'] );
	}

	/**
	 * Unknown tokens ride along verbatim and are reported, never dropped.
	 */
	public function test_yoast_unknown_tokens_are_reported(): void {
		$result = SitewideMapper::mapYoast(
			[ 'title-post' => '%%title%% %%cf_price%%' ]
		);

		$this->assertSame( [ '%%cf_price%%' ], $result['candidates'][0]['unknowns'] );
		$this->assertStringContainsString( '%%cf_price%%', $result['candidates'][0]['value'] );
	}

	/**
	 * Rank Math percent templates normalize and its robots arrays become rules.
	 */
	public function test_rank_math_templates_and_robots(): void {
		$result = SitewideMapper::mapRankMath(
			[
				'pt_post_title'       => '%title% %sep% %sitename%',
				'pt_post_description' => '%excerpt%',
				'pt_post_robots'      => [ 'index', 'follow' ],
				'pt_page_robots'      => [ 'noindex', 'follow' ],
			]
		);

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $result['candidates'][0]['value'] );
		$this->assertSame(
			[
				[
					'kind'  => 'post',
					'type'  => 'page',
					'index' => false,
				],
			],
			$result['noindex_rules']
		);
	}

	/**
	 * AIOSEO per-type appearance rows become candidates with hash tokens translated.
	 */
	public function test_aioseo_post_type_templates(): void {
		$result = SitewideMapper::mapAioseo(
			[
				'siteTitle'       => '#site_title',
				'metaDescription' => '',
			],
			[
				'post' => [
					'title'           => '#post_title #separator_sa #site_title',
					'metaDescription' => '#post_excerpt',
					'robotsMeta'      => [ 'default' => true ],
				],
			]
		);

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $result['candidates'][0]['value'] );
		$this->assertSame( [], $result['noindex_rules'] );
	}

	/**
	 * AIOSEO nested advanced robotsMeta noindex becomes a post rule.
	 */
	public function test_aioseo_nested_robots_meta_noindex(): void {
		$result = SitewideMapper::mapAioseo(
			[],
			[
				'post' => [
					'title'    => '#post_title',
					'advanced' => [
						'robotsMeta' => [
							'default' => false,
							'noindex' => true,
						],
					],
				],
			]
		);

		$this->assertSame(
			[
				[
					'kind'  => 'post',
					'type'  => 'post',
					'index' => false,
				],
			],
			$result['noindex_rules']
		);
	}

	/**
	 * AIOSEO taxonomy templates become taxonomy sourced candidates and term rules.
	 */
	public function test_aioseo_taxonomy_templates(): void {
		$result = SitewideMapper::mapAioseo(
			[],
			[],
			[
				'category' => [
					'title'           => '#taxonomy_title #separator_sa #site_title',
					'metaDescription' => '#taxonomy_description',
					'advanced'        => [
						'robotsMeta' => [
							'default' => false,
							'noindex' => true,
						],
					],
				],
			]
		);

		$sources = array_column( $result['candidates'], 'source' );
		$this->assertContains( 'taxonomy:category', $sources );
		$this->assertSame(
			[
				[
					'kind'  => 'term',
					'type'  => 'category',
					'index' => false,
				],
			],
			$result['noindex_rules']
		);
	}
}
