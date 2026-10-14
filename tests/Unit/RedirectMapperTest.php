<?php
/**
 * Redirect mapper tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\RedirectMapper;

/**
 * RedirectMapper turns source redirect rows into our redirect rows.
 *
 * Each mapped row carries source, target, code and match_type. Rows the
 * importer cannot place carry a skip reason instead and surface on the
 * NOT-imported list, never silently dropped.
 */
final class RedirectMapperTest extends TestCase {
	/**
	 * Yoast Premium plain rows map origin to source and url to target.
	 */
	public function test_map_yoast_plain_row(): void {
		$rows = RedirectMapper::mapYoast(
			[
				[
					'origin' => 'old-page',
					'url'    => '/new-page',
					'type'   => '301',
					'format' => 'plain',
				],
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'old-page', $rows[0]['source'] );
		$this->assertSame( '/new-page', $rows[0]['target'] );
		$this->assertSame( 301, $rows[0]['code'] );
		$this->assertSame( 'exact', $rows[0]['match_type'] );
		$this->assertArrayNotHasKey( 'skip_reason', $rows[0] );
	}

	/**
	 * Yoast regex rows become regex match rows.
	 */
	public function test_map_yoast_regex_row(): void {
		$rows = RedirectMapper::mapYoast(
			[
				[
					'origin' => 'old-(.*)',
					'url'    => '/new-$1',
					'type'   => '302',
					'format' => 'regex',
				],
			]
		);

		$this->assertSame( 'regex', $rows[0]['match_type'] );
		$this->assertSame( 302, $rows[0]['code'] );
	}

	/**
	 * Status codes outside our set are skipped with a reason.
	 */
	public function test_map_yoast_unsupported_code_skipped(): void {
		$rows = RedirectMapper::mapYoast(
			[
				[
					'origin' => 'gone',
					'url'    => '/',
					'type'   => '410',
					'format' => 'plain',
				],
			]
		);

		$this->assertArrayHasKey( 'skip_reason', $rows[0] );
	}

	/**
	 * Rank Math active exact rows map pattern to source.
	 */
	public function test_map_rank_math_row(): void {
		$rows = RedirectMapper::mapRankMath(
			[
				[
					'sources'     => [
						[
							'pattern'    => 'legacy',
							'comparison' => 'exact',
						],
					],
					'url_to'      => '/modern',
					'header_code' => 301,
					'status'      => 'active',
				],
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'legacy', $rows[0]['source'] );
		$this->assertSame( '/modern', $rows[0]['target'] );
		$this->assertSame( 301, $rows[0]['code'] );
		$this->assertSame( 'exact', $rows[0]['match_type'] );
	}

	/**
	 * Inactive Rank Math rows are skipped, never imported silently off.
	 */
	public function test_map_rank_math_inactive_skipped(): void {
		$rows = RedirectMapper::mapRankMath(
			[
				[
					'sources'     => [
						[
							'pattern'    => 'sleeping',
							'comparison' => 'exact',
						],
					],
					'url_to'      => '/awake',
					'header_code' => 301,
					'status'      => 'inactive',
				],
			]
		);

		$this->assertArrayHasKey( 'skip_reason', $rows[0] );
	}

	/**
	 * Duplicate sources inside one batch keep one row and name the repeat.
	 */
	public function test_duplicate_sources_dedupe(): void {
		$rows = RedirectMapper::mapYoast(
			[
				[
					'origin' => 'dup',
					'url'    => '/one',
					'type'   => '301',
					'format' => 'plain',
				],
				[
					'origin' => 'dup',
					'url'    => '/two',
					'type'   => '301',
					'format' => 'plain',
				],
			]
		);

		$this->assertCount( 2, $rows );
		$this->assertSame( '/one', $rows[0]['target'] );
		$this->assertArrayHasKey( 'skip_reason', $rows[1] );
		$this->assertStringContainsString( 'duplicate', $rows[1]['skip_reason'] );
	}

	/**
	 * Serialized Rank Math sources decode like the live table stores them.
	 */
	public function test_map_rank_math_serialized_sources(): void {
		$rows = RedirectMapper::mapRankMath(
			[
				[
					'sources'     => 'a:1:{i:0;a:3:{s:6:"ignore";s:4:"case";s:7:"pattern";s:16:"qrolic-services/";s:10:"comparison";s:5:"exact";}}',
					'url_to'      => '/services/',
					'header_code' => 301,
					'status'      => 'active',
				],
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'qrolic-services/', $rows[0]['source'] );
		$this->assertSame( '/services/', $rows[0]['target'] );
		$this->assertArrayNotHasKey( 'skip_reason', $rows[0] );
	}

	/**
	 * Empty sources and targets are named skips, never takable rows.
	 */
	public function test_empty_source_and_target_skipped(): void {
		$rows = RedirectMapper::mapYoast(
			[
				[
					'origin' => '',
					'url'    => '/somewhere',
					'type'   => '301',
					'format' => 'plain',
				],
				[
					'origin' => '/nowhere',
					'url'    => '',
					'type'   => '301',
					'format' => 'plain',
				],
			]
		);

		$this->assertCount( 2, $rows );
		$this->assertArrayHasKey( 'skip_reason', $rows[0] );
		$this->assertArrayHasKey( 'skip_reason', $rows[1] );
	}

	/**
	 * Unreadable Rank Math sources are named skips, never silent corruption.
	 */
	public function test_non_array_sources_skipped(): void {
		$rows = RedirectMapper::mapRankMath(
			[
				[
					'sources'     => 'not-a-serialized-array',
					'url_to'      => '/modern',
					'header_code' => 301,
					'status'      => 'active',
				],
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertArrayHasKey( 'skip_reason', $rows[0] );
	}
}
