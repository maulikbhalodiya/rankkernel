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

/**
 * Crawl Consistency Test.
 */
final class CrawlConsistencyTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

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
	 * Test no warnings when llms is off.
	 */
	public function test_no_warnings_when_llms_off(): void {
		$this->assertSame( [], CrawlConsistency::warnings( [ 'gptbot' ], false, "User-agent: *\nDisallow: /\n" ) );
	}

	/**
	 * Test warns when llms is on and crawlers are blocked.
	 */
	public function test_warns_when_crawlers_blocked(): void {
		$warnings = CrawlConsistency::warnings( [ 'gptbot' ], true, "User-agent: *\nDisallow: /wp-admin/\n" );

		$this->assertCount( 1, $warnings );
	}

	/**
	 * Test warns when the wildcard group disallows everything.
	 */
	public function test_warns_on_wildcard_disallow_all(): void {
		$warnings = CrawlConsistency::warnings( [], true, "User-agent: *\nDisallow: /\n" );

		$this->assertCount( 1, $warnings );
	}

	/**
	 * Test a scoped disallow is not a global warning.
	 */
	public function test_scoped_disallow_is_not_global(): void {
		$warnings = CrawlConsistency::warnings( [], true, "User-agent: *\nDisallow: /wp-admin/\n" );

		$this->assertSame( [], $warnings );
	}
}
