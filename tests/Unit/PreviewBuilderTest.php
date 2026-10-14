<?php
/**
 * Preview builder tests, mapper outputs into preview rows.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\PreviewBuilder;

/**
 * Preview Builder Test.
 */
final class PreviewBuilderTest extends TestCase {
	/**
	 * Test a Yoast post with signal and empty existing yields a clean row.
	 */
	public function test_post_row_with_signal_and_no_conflicts(): void {
		$result = PreviewBuilder::buildPreview(
			'yoast',
			[
				[
					'id'       => 7,
					'type'     => 'post',
					'data'     => [ '_yoast_wpseo_title' => 'My Title' ],
					'existing' => [],
				],
			],
			[],
			[],
			[],
			'skip'
		);

		$this->assertCount( 1, $result['rows'] );
		$this->assertCount( 0, $result['skipped'] );
		$row = $result['rows'][0];
		$this->assertSame( 'post', $row->kind );
		$this->assertSame( 7, $row->refId );
		$this->assertSame( 'My Title', $row->fields['title'] );
		$this->assertSame( [], $row->conflicts );
	}

	/**
	 * Test skip mode marks an already set field as a conflict.
	 */
	public function test_post_row_conflict_marks_old_value_in_skip_mode(): void {
		$result = PreviewBuilder::buildPreview(
			'yoast',
			[
				[
					'id'       => 7,
					'type'     => 'post',
					'data'     => [ '_yoast_wpseo_title' => 'Incoming' ],
					'existing' => [ 'title' => 'Ours' ],
				],
			],
			[],
			[],
			[],
			'skip'
		);

		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( [ 'title' => 'Ours' ], $result['rows'][0]->conflicts );
	}

	/**
	 * Test overwrite mode never marks conflicts.
	 */
	public function test_post_row_has_no_conflicts_in_overwrite_mode(): void {
		$result = PreviewBuilder::buildPreview(
			'yoast',
			[
				[
					'id'       => 7,
					'type'     => 'post',
					'data'     => [ '_yoast_wpseo_title' => 'Incoming' ],
					'existing' => [ 'title' => 'Ours' ],
				],
			],
			[],
			[],
			[],
			'overwrite'
		);

		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( [], $result['rows'][0]->conflicts );
	}

	/**
	 * Test a post with no importable signal lands on the skipped list.
	 */
	public function test_post_without_signal_goes_to_skipped(): void {
		$result = PreviewBuilder::buildPreview(
			'yoast',
			[
				[
					'id'       => 7,
					'type'     => 'post',
					'data'     => [],
					'existing' => [],
				],
			],
			[],
			[],
			[],
			'skip'
		);

		$this->assertCount( 0, $result['rows'] );
		$this->assertCount( 1, $result['skipped'] );
		$this->assertSame( 'post', $result['skipped'][0]->kind );
		$this->assertNotSame( '', $result['skipped'][0]->skippedReason );
	}

	/**
	 * Test sitewide templates become candidate rows with a recommended pick.
	 */
	public function test_sitewide_candidates_recommended_and_skipped(): void {
		$result = PreviewBuilder::buildPreview(
			'yoast',
			[],
			[],
			[
				'title-post'         => '%%title%%',
				'title-page'         => 'Page %%title%%',
				'title-tax-category' => 'Cat',
				'noindex-post'       => true,
			],
			[],
			'skip'
		);

		$candidates = array_values( array_filter( $result['rows'], static fn( $row ) => 'title_template' === $row->slot ) );
		$this->assertCount( 2, $candidates );
		$recommended = array_values( array_filter( $candidates, static fn( $row ) => $row->recommended ) );
		$this->assertCount( 1, $recommended );
		$this->assertSame( 'post', $recommended[0]->fields['candidate_source'] );

		$bulks = array_values( array_filter( $result['rows'], static fn( $row ) => $row->bulk ) );
		$this->assertCount( 1, $bulks );

		$this->assertNotCount( 0, $result['skipped'] );
	}

	/**
	 * Test redirect rows map and unsupported codes land on skipped.
	 */
	public function test_redirect_rows_and_skips(): void {
		$result = PreviewBuilder::buildPreview(
			'yoast',
			[],
			[],
			[],
			[
				[
					'origin' => '/a',
					'url'    => '/b',
					'type'   => '301',
					'format' => 'plain',
				],
				[
					'origin' => '/gone',
					'url'    => '',
					'type'   => '410',
					'format' => 'plain',
				],
			],
			'skip'
		);

		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( 'redirect', $result['rows'][0]->kind );
		$this->assertSame( 301, $result['rows'][0]->fields['code'] );
		$this->assertCount( 1, $result['skipped'] );
	}

	/**
	 * Test AIOSEO redirects are reported unsupported, never attempted.
	 */
	public function test_aioseo_redirects_reported_unsupported(): void {
		$result = PreviewBuilder::buildPreview( 'aioseo', [], [], [], [ [ 'anything' => 1 ] ], 'skip' );

		$this->assertCount( 0, $result['rows'] );
		$this->assertCount( 1, $result['skipped'] );
		$this->assertStringContainsString( 'Pro', $result['skipped'][0]->skippedReason );
	}

	/**
	 * Test an unknown conflict mode throws.
	 */
	public function test_invalid_mode_throws(): void {
		$this->expectException( InvalidArgumentException::class );

		PreviewBuilder::buildPreview( 'yoast', [], [], [], [], 'merge' );
	}

	/**
	 * Test every takable row carries a stable unique identity.
	 */
	public function test_takable_rows_carry_stable_unique_identities(): void {
		$result = PreviewBuilder::buildPreview(
			'yoast',
			[
				[
					'id'       => 7,
					'type'     => 'post',
					'data'     => [ '_yoast_wpseo_title' => 'My Title' ],
					'existing' => [],
				],
			],
			[
				[
					'id'       => 9,
					'taxonomy' => 'category',
					'data'     => [ 'wpseo_title' => 'Cats' ],
					'existing' => [],
				],
			],
			[
				'title-post'   => '%%title%%',
				'title-page'   => 'Page %%title%%',
				'noindex-post' => true,
			],
			[
				[
					'origin' => '/a',
					'url'    => '/b',
					'type'   => '301',
					'format' => 'plain',
				],
				[
					'origin' => '/c',
					'url'    => '/d',
					'type'   => '302',
					'format' => 'regex',
				],
			],
			'skip'
		);

		$identities = array_map( static fn( $row ) => $row->identity, $result['rows'] );

		$this->assertContains( 'post:7', $identities );
		$this->assertContains( 'term:category:9', $identities );
		$this->assertContains( 'sitewide:title_template:post', $identities );
		$this->assertContains( 'sitewide:title_template:page', $identities );
		$this->assertContains( 'bulk:post:post', $identities );
		$this->assertContains( 'redirect:exact:/a', $identities );
		$this->assertContains( 'redirect:regex:/c', $identities );
		$this->assertSame( $identities, array_unique( $identities ) );
		foreach ( $identities as $identity ) {
			$this->assertNotSame( '', $identity );
		}
	}
}
