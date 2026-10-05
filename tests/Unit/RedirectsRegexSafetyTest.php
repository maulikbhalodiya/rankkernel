<?php
/**
 * Catastrophic backtracking guard tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\RegexSafety;

/**
 * Redirects Regex Safety Test.
 */
final class RedirectsRegexSafetyTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html__' )->alias( static fn ( string $text ): string => $text );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test a quantifier directly before a quantified group is refused.
	 *
	 * This is the classic shape and the one most written by hand.
	 *
	 * @dataProvider provide_nested_quantifier_patterns
	 *
	 * @param string $pattern Regex body under test.
	 */
	public function test_nested_quantifier_patterns_are_refused( string $pattern ): void {
		$this->assertTrue(
			RegexSafety::isUnsafe( $pattern ),
			'A pattern that can backtrack catastrophically must be refused'
		);
	}

	/**
	 * Patterns that make the engine retry every partition of the input.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function provide_nested_quantifier_patterns(): array {
		return array(
			'plus inside plus'  => array( '^(a+)+$' ),
			'star inside star'  => array( '^(a*)*$' ),
			'plus inside star'  => array( '^(a+)*$' ),
			'alternation plus'  => array( '^(a|a)+$' ),
			'class plus inside' => array( '^([a-z]+)+$' ),
			'word class nested' => array( '^(\w+\s?)*$' ),
			'repeated plus run' => array( '^(x+x+)+y$' ),
			'brace bounded'     => array( '^(a{2,})+$' ),
			'greedy dot nested' => array( '^(.*)*$' ),
			'trailing plus'     => array( '^(a+)+b$' ),
			'unanchored class'  => array( '^([^/]+)+$' ),
			'repeated group'    => array( '^(\w+\s?)+$' ),
		);
	}

	/**
	 * Test ordinary redirect patterns stay allowed.
	 *
	 * A save time refusal that blocked these would break real rules.
	 *
	 * @dataProvider provide_safe_patterns
	 *
	 * @param string $pattern Regex body under test.
	 */
	public function test_safe_patterns_are_allowed( string $pattern ): void {
		$this->assertFalse(
			RegexSafety::isUnsafe( $pattern ),
			'An ordinary redirect pattern must stay allowed'
		);
	}

	/**
	 * Ordinary redirect patterns of the shapes operators actually write.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function provide_safe_patterns(): array {
		return array(
			'exact path digits'       => array( '^/old/[0-9]+$' ),
			'capture segment'         => array( '^/shop/(.*)$' ),
			'slug class'              => array( '^/[a-z0-9-]+$' ),
			'brace bounded repeat'    => array( '^/x{2,4}$' ),
			'alternation once'        => array( '^(foo|bar)$' ),
			'single literal'          => array( '^/a$' ),
			'non capture alternation' => array( '^/(?:cat|dog)/[0-9]+$' ),
			'digits only'             => array( '^/\d+$' ),
			'dated path'              => array( '^/post-[0-9]{4}-[0-9]{2}$' ),
			'optional group'          => array( '^[a-z]+(/[a-z]+)?$' ),
			'plugin slug'             => array( '^/rk-[a-z0-9-]+$' ),
			'paginated category'      => array( '^/category/[a-z-]+(/page/[0-9]+)?$' ),
			'two segments'            => array( '^/old/[a-z]+$' ),
			'brace repeat plus class' => array( '^/a{2,3}$' ),
			'single optional letter'  => array( '^/x(y)?$' ),
		);
	}

	/**
	 * Test an empty pattern is refused rather than stored.
	 */
	public function test_empty_pattern_is_refused(): void {
		$this->assertTrue( RegexSafety::isUnsafe( '' ) );
	}

	/**
	 * Test the probe budget is not so low that it refuses every pattern.
	 *
	 * The probe exists to catch shapes the structural scan cannot model, so
	 * it must not become the reason an ordinary pattern is refused. This
	 * guards the budget itself against being tightened into uselessness.
	 */
	public function test_probe_does_not_refuse_ordinary_patterns(): void {
		foreach ( self::provide_safe_patterns() as $label => $case ) {
			$this->assertFalse(
				RegexSafety::exceedsBudget( $case[0] ),
				'The timing probe must not refuse ' . $label
			);
		}
	}

	/**
	 * Test the structural scan is what refuses the dangerous shapes.
	 *
	 * Separates the deterministic check from the timing probe, so a change
	 * to the probe budget cannot silently become the only defence.
	 */
	public function test_structural_scan_refuses_the_dangerous_shapes(): void {
		foreach ( self::provide_nested_quantifier_patterns() as $label => $case ) {
			$this->assertTrue(
				RegexSafety::hasNestedQuantifier( $case[0] ),
				'The structural scan must refuse ' . $label
			);
		}
	}

	/**
	 * Test an unbalanced pattern does not make the scan loop.
	 *
	 * A malformed pattern must be handled by the compile probe, so the scan
	 * reports nothing and defers rather than walking off the end.
	 */
	public function test_unbalanced_pattern_is_handled(): void {
		// An unterminated group defers to the compile probe instead of
		// walking off the end of the pattern.
		$this->assertFalse( RegexSafety::hasNestedQuantifier( '^(a+' ) );

		// A missing closing parenthesis is still a nested quantifier in
		// every part the scan can read, so it is refused on that ground
		// and the compile probe never has to run.
		$this->assertTrue( RegexSafety::hasNestedQuantifier( '(a+)+' ) );

		// A stray closing parenthesis with no group to quantify is left
		// to the compile probe.
		$this->assertFalse( RegexSafety::hasNestedQuantifier( '[a-z]+)+$' ) );
	}
}
