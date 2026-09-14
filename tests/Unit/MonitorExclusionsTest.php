<?php
/**
 * 404 exclusion matching tests, all comparators, no regex.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\Exclusions;

final class MonitorExclusionsTest extends TestCase {
	public function test_exact_matches_full_uri_only(): void {
		$rules = [
			[
				'comparator' => 'exact',
				'value'      => '/gone',
			],
		];

		$this->assertTrue( Exclusions::matches( '/gone', $rules ) );
		$this->assertFalse( Exclusions::matches( '/gone/deeper', $rules ) );
		$this->assertFalse( Exclusions::matches( '/gon', $rules ) );
	}

	public function test_prefix_matches_leading_segment(): void {
		$rules = [
			[
				'comparator' => 'prefix',
				'value'      => '/private',
			],
		];

		$this->assertTrue( Exclusions::matches( '/private/doc', $rules ) );
		$this->assertTrue( Exclusions::matches( '/private', $rules ) );
		$this->assertFalse( Exclusions::matches( '/public/private', $rules ) );
	}

	public function test_contains_matches_anywhere(): void {
		$rules = [
			[
				'comparator' => 'contains',
				'value'      => 'temp',
			],
		];

		$this->assertTrue( Exclusions::matches( '/files/temp/file', $rules ) );
		$this->assertFalse( Exclusions::matches( '/files/perm/file', $rules ) );
	}

	public function test_suffix_matches_trailing_segment(): void {
		$rules = [
			[
				'comparator' => 'suffix',
				'value'      => '.pdf',
			],
		];

		$this->assertTrue( Exclusions::matches( '/files/report.pdf', $rules ) );
		$this->assertFalse( Exclusions::matches( '/files/report.pdf/view', $rules ) );
	}

	public function test_wildcard_star_matches_any_sequence(): void {
		$rules = [
			[
				'comparator' => 'wildcard',
				'value'      => '/docs/*/draft',
			],
		];

		$this->assertTrue( Exclusions::matches( '/docs/2026/draft', $rules ) );
		$this->assertTrue( Exclusions::matches( '/docs/a/b/draft', $rules ) );
		$this->assertFalse( Exclusions::matches( '/docs/2026/final', $rules ) );

		$open = [
			[
				'comparator' => 'wildcard',
				'value'      => '/tmp*',
			],
		];

		$this->assertTrue( Exclusions::matches( '/tmp', $open ) );
		$this->assertTrue( Exclusions::matches( '/tmpfile', $open ) );
	}

	public function test_wildcard_treats_other_characters_as_literal(): void {
		$rules = [
			[
				'comparator' => 'wildcard',
				'value'      => '/file.(pdf)',
			],
		];

		$this->assertTrue( Exclusions::matches( '/file.(pdf)', $rules ) );
		$this->assertFalse( Exclusions::matches( '/fileXpdfY', $rules ) );
	}

	public function test_matching_is_case_sensitive(): void {
		$rules = [
			[
				'comparator' => 'exact',
				'value'      => '/Gone',
			],
		];

		$this->assertTrue( Exclusions::matches( '/Gone', $rules ) );
		$this->assertFalse( Exclusions::matches( '/gone', $rules ) );
	}

	public function test_malformed_rules_never_match_and_never_fatal(): void {
		$this->assertFalse( Exclusions::matches( '/anything', [] ) );
		$this->assertFalse(
			Exclusions::matches(
				'/anything',
				[
					'not-an-array',
					[
						'comparator' => 'regex',
						'value'      => '/anything',
					],
					[
						'comparator' => 'exact',
						'value'      => '',
					],
					[
						'comparator' => 'unknown',
						'value'      => '/anything',
					],
				]
			)
		);
	}

	public function test_first_match_short_circuits(): void {
		$rules = [
			[
				'comparator' => 'prefix',
				'value'      => '/a',
			],
			[
				'comparator' => 'exact',
				'value'      => '/a/b',
			],
		];

		$this->assertTrue( Exclusions::matches( '/a/b', $rules ) );
		$this->assertTrue( Exclusions::isComparator( 'suffix' ) );
		$this->assertFalse( Exclusions::isComparator( 'regex' ) );
	}
}
