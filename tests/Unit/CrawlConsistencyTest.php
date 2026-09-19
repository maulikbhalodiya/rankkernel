<?php
/**
 * Crawl Signals consistency tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\CrawlConsistency;
use RankKernel\Modules\Robots\CrawlerPolicy;

/**
 * Crawl Consistency Test.
 */
final class CrawlConsistencyTest extends TestCase {
	/**
	 * Core robots block with a scoped disallow, the normal public site shape.
	 */
	private const CORE_ROBOTS = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test no llms warnings at all when llms.txt is off.
	 */
	public function test_no_warnings_when_llms_is_off(): void {
		$policies                  = CrawlerPolicy::defaults();
		$policies['perplexitybot'] = CrawlerPolicy::BLOCK;

		$this->assertSame( [], CrawlConsistency::warnings( $policies, false, "User-agent: *\nDisallow: /\n" ) );
	}

	/**
	 * Test the shipped defaults raise no warning, since blocking training
	 * crawlers is the deliberate default and says nothing about llms.txt.
	 */
	public function test_default_policies_raise_no_warning(): void {
		$this->assertSame( [], CrawlConsistency::warnings( CrawlerPolicy::defaults(), true, self::CORE_ROBOTS ) );
	}

	/**
	 * Test an AI search crawler blocked on purpose warns and is named.
	 */
	public function test_blocked_ai_search_crawler_warns_and_is_named(): void {
		$policies                  = CrawlerPolicy::defaults();
		$policies['perplexitybot'] = CrawlerPolicy::BLOCK;

		$warnings = CrawlConsistency::warnings( $policies, true, self::CORE_ROBOTS );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'PerplexityBot', $warnings[0] );
	}

	/**
	 * Test several blocked AI search crawlers fold into one warning.
	 */
	public function test_multiple_blocked_consumers_fold_into_one_warning(): void {
		$policies                  = CrawlerPolicy::defaults();
		$policies['perplexitybot'] = CrawlerPolicy::BLOCK;
		$policies['oai-searchbot'] = CrawlerPolicy::BLOCK;

		$warnings = CrawlConsistency::warnings( $policies, true, self::CORE_ROBOTS );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'PerplexityBot', $warnings[0] );
		$this->assertStringContainsString( 'OAI-SearchBot', $warnings[0] );
	}

	/**
	 * Test blocking a training crawler is not an llms.txt conflict.
	 */
	public function test_blocked_training_crawler_is_not_a_warning(): void {
		$policies                    = CrawlerPolicy::defaults();
		$policies['gptbot']          = CrawlerPolicy::BLOCK;
		$policies['google-extended'] = CrawlerPolicy::BLOCK;
		$policies['ccbot']           = CrawlerPolicy::BLOCK;

		$this->assertSame( [], CrawlConsistency::warnings( $policies, true, self::CORE_ROBOTS ) );
	}

	/**
	 * Test a user triggered fetcher block is advisory, not a conflict.
	 */
	public function test_blocked_user_fetcher_is_not_a_warning(): void {
		$policies                 = CrawlerPolicy::defaults();
		$policies['chatgpt-user'] = CrawlerPolicy::BLOCK;

		$this->assertSame( [], CrawlConsistency::warnings( $policies, true, self::CORE_ROBOTS ) );
	}

	/**
	 * Test an allowed AI search crawler raises no warning.
	 */
	public function test_allowed_ai_search_crawler_raises_no_warning(): void {
		$policies                  = CrawlerPolicy::defaults();
		$policies['perplexitybot'] = CrawlerPolicy::ALLOW;

		$this->assertSame( [], CrawlConsistency::warnings( $policies, true, self::CORE_ROBOTS ) );
	}

	/**
	 * Test a Custom policy is the owner own rule and raises no warning.
	 */
	public function test_custom_policy_raises_no_warning(): void {
		$policies                  = CrawlerPolicy::defaults();
		$policies['perplexitybot'] = CrawlerPolicy::CUSTOM;

		$this->assertSame( [], CrawlConsistency::warnings( $policies, true, self::CORE_ROBOTS ) );
	}

	/**
	 * Test a wildcard group that disallows the whole site warns.
	 */
	public function test_wildcard_disallow_all_warns(): void {
		$warnings = CrawlConsistency::warnings( CrawlerPolicy::defaults(), true, "User-agent: *\nDisallow: /\n" );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'whole site', $warnings[0] );
	}

	/**
	 * Test a wildcard group that disallows the llms.txt route warns.
	 */
	public function test_wildcard_disallow_llms_route_warns(): void {
		$warnings = CrawlConsistency::warnings( CrawlerPolicy::defaults(), true, "User-agent: *\nDisallow: /llms.txt\n" );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( '/llms.txt', $warnings[0] );
	}

	/**
	 * Test a scoped wildcard disallow, such as wp-admin, is not a conflict.
	 */
	public function test_scoped_wildcard_disallow_is_not_a_conflict(): void {
		$this->assertSame( [], CrawlConsistency::warnings( CrawlerPolicy::defaults(), true, self::CORE_ROBOTS ) );
	}

	/**
	 * Test a crawler specific group does not leak into the wildcard reading.
	 */
	public function test_crawler_specific_group_does_not_leak(): void {
		$robots = "User-agent: GPTBot\nDisallow: /\n\nUser-agent: *\nDisallow: /wp-admin/\n";

		$this->assertSame( [], CrawlConsistency::warnings( CrawlerPolicy::defaults(), true, $robots ) );
	}

	/**
	 * Test both conflicts are reported together, crawler first.
	 */
	public function test_both_conflicts_are_reported(): void {
		$policies                  = CrawlerPolicy::defaults();
		$policies['perplexitybot'] = CrawlerPolicy::BLOCK;

		$warnings = CrawlConsistency::warnings( $policies, true, "User-agent: *\nDisallow: /\n" );

		$this->assertCount( 2, $warnings );
		$this->assertStringContainsString( 'PerplexityBot', $warnings[0] );
		$this->assertStringContainsString( 'whole site', $warnings[1] );
	}
}
