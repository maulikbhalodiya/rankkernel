<?php
/**
 * Reader fetch method tests over an in memory wpdb double.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\AioseoReader;
use RankKernel\Modules\Importer\RankMathReader;
use RankKernel\Modules\Importer\YoastReader;

/**
 * Reader Fetch Methods Test.
 *
 * Fetch methods return chunk shaped items the PreviewBuilder consumes:
 * posts carry id, type and source shaped data, terms carry id,
 * taxonomy and data. Sitewide and redirect fetchers return the raw
 * blobs the mappers already accept.
 */
final class FetchMethodsTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Seed the options used by every reader.
	 *
	 * @param array<string, mixed> $options Option name to value.
	 */
	private function seedOptions( array $options ): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- stub mirrors the WordPress get_option signature.
			static function ( $name, $default = false ) use ( $options ) {
				return array_key_exists( (string) $name, $options ) ? $options[ (string) $name ] : $default;
			}
		);
	}

	/**
	 * Yoast post chunks carry ids, types and meta rows.
	 */
	public function test_yoast_fetch_posts_returns_chunk_with_types_and_meta(): void {
		$this->seedOptions( [] );
		$db = new ImporterFakeDb();
		$db->seedPostTypes(
			[
				7 => 'post',
				9 => 'page',
			]
		);
		$db->seedPostmeta(
			[
				7 => [ '_yoast_wpseo_title' => 'T7' ],
				9 => [ '_yoast_wpseo_metadesc' => 'D9' ],
			]
		);

		$items = ( new YoastReader( $db ) )->fetchPosts( 0, 50 );

		$this->assertCount( 2, $items );
		$this->assertSame( 7, $items[0]['id'] );
		$this->assertSame( 'post', $items[0]['type'] );
		$this->assertSame( 'T7', $items[0]['data']['_yoast_wpseo_title'] );
		$this->assertSame( 'page', $items[1]['type'] );
	}

	/**
	 * Yoast term chunks flatten the taxonomy meta option.
	 */
	public function test_yoast_fetch_terms_flattens_taxonomy_option(): void {
		$this->seedOptions(
			[
				'wpseo_taxonomy_meta' => [
					'category' => [ 3 => [ 'wpseo_title' => 'C3' ] ],
					'post_tag' => [ 5 => [ 'wpseo_desc' => 'T5' ] ],
				],
			]
		);
		$db = new ImporterFakeDb();

		$items = ( new YoastReader( $db ) )->fetchTerms( 1, 1 );

		$this->assertCount( 1, $items );
		$this->assertSame( 5, $items[0]['id'] );
		$this->assertSame( 'post_tag', $items[0]['taxonomy'] );
		$this->assertSame( 'T5', $items[0]['data']['wpseo_desc'] );
	}

	/**
	 * Rank Math post chunks read rank_math_ meta with types.
	 */
	public function test_rank_math_fetch_posts_returns_chunk(): void {
		$this->seedOptions( [] );
		$db = new ImporterFakeDb();
		$db->seedPostTypes( [ 3 => 'post' ] );
		$db->seedPostmeta( [ 3 => [ 'rank_math_title' => 'R3' ] ] );

		$items = ( new RankMathReader( $db ) )->fetchPosts( 0, 50 );

		$this->assertCount( 1, $items );
		$this->assertSame( 'R3', $items[0]['data']['rank_math_title'] );
	}

	/**
	 * AIOSEO post chunks read the owned posts table.
	 */
	public function test_aioseo_fetch_posts_reads_owned_table(): void {
		$this->seedOptions( [] );
		$db = new ImporterFakeDb();
		$db->seedPostTypes( [ 11 => 'post' ] );
		$db->seedAioseoPosts(
			[
				[
					'post_id'     => 11,
					'title'       => 'A11',
					'description' => 'AD11',
				],
			]
		);

		$items = ( new AioseoReader( $db ) )->fetchPosts( 0, 50 );

		$this->assertCount( 1, $items );
		$this->assertSame( 11, $items[0]['id'] );
		$this->assertSame( 'A11', $items[0]['data']['title'] );
	}

	/**
	 * AIOSEO exposes raw taxonomy templates for the sitewide mapper.
	 */
	public function test_aioseo_fetch_taxonomies_returns_raw_templates(): void {
		$this->seedOptions(
			[
				'aioseo_options_dynamic' => [
					'searchAppearance' => [
						'taxonomies' => [
							'category' => [
								'title'           => '#taxonomy_title',
								'metaDescription' => 'D',
							],
						],
					],
				],
			]
		);
		$db = new ImporterFakeDb();

		$tax = ( new AioseoReader( $db ) )->fetchTaxonomies();

		$this->assertSame( '#taxonomy_title', $tax['category']['title'] );
	}

	/**
	 * Rank Math term chunks carry taxonomies and meta rows.
	 */
	public function test_rank_math_fetch_terms_returns_chunk_with_taxonomy(): void {
		$this->seedOptions( [] );
		$db = new ImporterFakeDb();
		$db->seedTermTaxonomies( [ 4 => 'category' ] );
		$db->seedTermmeta( [ 4 => [ 'rank_math_title' => 'RC4' ] ] );

		$items = ( new RankMathReader( $db ) )->fetchTerms( 0, 50 );

		$this->assertCount( 1, $items );
		$this->assertSame( 4, $items[0]['id'] );
		$this->assertSame( 'category', $items[0]['taxonomy'] );
		$this->assertSame( 'RC4', $items[0]['data']['rank_math_title'] );
	}

	/**
	 * AIOSEO term fallback strips the _aioseo_ prefix for the row mapper.
	 */
	public function test_aioseo_fetch_terms_strips_prefix(): void {
		$this->seedOptions( [] );
		$db = new ImporterFakeDb();
		$db->seedTermmeta( [ 6 => [ '_aioseo_title' => 'AX6' ] ] );

		$items = ( new AioseoReader( $db ) )->fetchTerms( 0, 50 );

		$this->assertCount( 1, $items );
		$this->assertSame( 'AX6', $items[0]['data']['title'] );
		$this->assertArrayNotHasKey( '_aioseo_title', $items[0]['data'] );
	}

	/**
	 * Redirect fetchers return raw rows, AIOSEO returns none.
	 */
	public function test_fetch_redirects_raw_per_source(): void {
		$this->seedOptions(
			[
				'wpseo-premium-redirects-base' => [
					[
						'origin' => '/a',
						'url'    => '/b',
						'type'   => 301,
						'format' => 'plain',
					],
				],
			]
		);
		$db = new ImporterFakeDb( [], [], true );
		$db->seedRmRedirects(
			[
				[
					'sources'     => '[{"pattern":"\\/c","comparison":"exact"}]',
					'url_to'      => '/d',
					'header_code' => 301,
					'status'      => 'active',
				],
			]
		);

		$yoast  = ( new YoastReader( $db ) )->fetchRedirectsRaw();
		$rm     = ( new RankMathReader( $db ) )->fetchRedirectsRaw();
		$aioseo = ( new AioseoReader( $db ) )->fetchRedirectsRaw();

		$this->assertCount( 1, $yoast );
		$this->assertSame( '/a', $yoast[0]['origin'] );
		$this->assertCount( 1, $rm );
		$this->assertSame( [], $aioseo );
	}

	/**
	 * An empty source fetches nothing anywhere.
	 */
	public function test_empty_source_fetches_nothing(): void {
		$this->seedOptions( [] );
		$db = new ImporterFakeDb();

		foreach ( [ new YoastReader( $db ), new RankMathReader( $db ), new AioseoReader( $db ) ] as $reader ) {
			$this->assertSame( [], $reader->fetchPosts( 0, 50 ) );
			$this->assertSame( [], $reader->fetchTerms( 0, 50 ) );
			$this->assertSame( [], $reader->fetchRedirectsRaw() );
		}
	}
}
