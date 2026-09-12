<?php
/**
 * Loop and chain detection tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\Validator;

final class RedirectsValidatorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build an active exact rule.
	 *
	 * @param int    $id Rule id.
	 * @param string $source Source path.
	 * @param string $target Target path.
	 * @param string $type Matcher.
	 * @param string $code Status code.
	 * @param int    $active Active flag.
	 * @return array<string, mixed>
	 */
	private function rule( int $id, string $source, string $target, string $type = 'exact', string $code = '301', int $active = 1 ): array {
		return [
			'id'         => $id,
			'match_type' => $type,
			'source'     => $source,
			'target'     => $target,
			'code'       => $code,
			'is_active'  => $active,
		];
	}

	public function test_direct_loop_blocked(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 1, '/a', '/b' ) ];

		$result = $validator->detect_loop(
			[
				'source' => '/b',
				'target' => '/a',
				'code'   => '301',
			],
			$rules
		);

		$this->assertTrue( $result['has_cycle'] );
		$this->assertSame( [ '/b', '/a', '/b' ], $result['path'] );
		$this->assertFalse( $result['inconclusive'] );
	}

	public function test_two_hop_loop_blocked(): void {
		$validator = new Validator();
		$rules     = [
			$this->rule( 1, '/a', '/b' ),
			$this->rule( 2, '/b', '/c' ),
		];

		$result = $validator->detect_loop(
			[
				'source' => '/c',
				'target' => '/a',
				'code'   => '301',
			],
			$rules
		);

		$this->assertTrue( $result['has_cycle'] );
		$this->assertSame( [ '/c', '/a', '/b', '/c' ], $result['path'] );
	}

	public function test_three_hop_loop_blocked(): void {
		$validator = new Validator();
		$rules     = [
			$this->rule( 1, '/a', '/b' ),
			$this->rule( 2, '/b', '/c' ),
			$this->rule( 3, '/c', '/d' ),
		];

		$result = $validator->detect_loop(
			[
				'source' => '/d',
				'target' => '/a',
				'code'   => '301',
			],
			$rules
		);

		$this->assertTrue( $result['has_cycle'] );
		$this->assertSame( [ '/d', '/a', '/b', '/c', '/d' ], $result['path'] );
	}

	public function test_longer_cycle_blocked(): void {
		$validator = new Validator();
		$rules     = [
			$this->rule( 1, '/a', '/b' ),
			$this->rule( 2, '/b', '/c' ),
			$this->rule( 3, '/c', '/d' ),
			$this->rule( 4, '/d', '/e' ),
			$this->rule( 5, '/e', '/f' ),
		];

		$result = $validator->detect_loop(
			[
				'source' => '/f',
				'target' => '/a',
				'code'   => '301',
			],
			$rules
		);

		$this->assertTrue( $result['has_cycle'] );
		$this->assertSame( '/f', $result['path'][0] );
		$this->assertSame( '/f', end( $result['path'] ) );
	}

	public function test_inactive_rule_ignored(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 1, '/a', '/b', 'exact', '301', 0 ) ];

		$result = $validator->detect_loop(
			[
				'source' => '/b',
				'target' => '/a',
				'code'   => '301',
			],
			$rules
		);

		$this->assertFalse( $result['has_cycle'] );
		$this->assertFalse( $result['inconclusive'] );
	}

	public function test_terminal_proposed_rule_has_no_cycle(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 1, '/a', '/b' ) ];

		$result = $validator->detect_loop(
			[
				'source' => '/b',
				'target' => '',
				'code'   => '410',
			],
			$rules
		);

		$this->assertFalse( $result['has_cycle'] );
	}

	public function test_pattern_rule_branch_inconclusive(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 5, '^/a$', '/c', 'regex' ) ];

		$result = $validator->detect_loop(
			[
				'source' => '/new',
				'target' => '/a',
				'code'   => '301',
			],
			$rules
		);

		$this->assertFalse( $result['has_cycle'] );
		$this->assertTrue( $result['inconclusive'] );
	}

	public function test_capture_target_inconclusive(): void {
		$validator = new Validator();

		$result = $validator->detect_loop(
			[
				'source' => '/new',
				'target' => '/a/$1',
				'code'   => '301',
			],
			[]
		);

		$this->assertFalse( $result['has_cycle'] );
		$this->assertTrue( $result['inconclusive'] );
	}

	public function test_external_target_inconclusive(): void {
		$validator = new Validator();

		$result = $validator->detect_loop(
			[
				'source' => '/new',
				'target' => 'https://external.example/x',
				'code'   => '301',
			],
			[]
		);

		$this->assertFalse( $result['has_cycle'] );
		$this->assertTrue( $result['inconclusive'] );
	}

	public function test_depth_cap_stops_long_traversal(): void {
		$validator = new Validator();
		$rules     = [];

		for ( $i = 1; $i <= 12; $i++ ) {
			$rules[] = $this->rule( $i, '/n' . (string) $i, '/n' . (string) ( $i + 1 ) );
		}

		$result = $validator->detect_loop(
			[
				'source' => '/start',
				'target' => '/n1',
				'code'   => '301',
			],
			$rules
		);

		$this->assertFalse( $result['has_cycle'] );
		$this->assertTrue( $result['inconclusive'] );
	}

	public function test_node_cap_stops_wide_traversal(): void {
		$validator = new Validator();
		$rules     = [];

		for ( $i = 1; $i <= 60; $i++ ) {
			$rules[] = $this->rule( $i, '/t', '/u' . (string) $i, 'prefix' );
		}

		$result = $validator->detect_loop(
			[
				'source' => '/start',
				'target' => '/t',
				'code'   => '301',
			],
			$rules
		);

		$this->assertFalse( $result['has_cycle'] );
		$this->assertTrue( $result['inconclusive'] );
	}

	public function test_chain_one_intermediate_recommends_final(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 2, '/b', '/c' ) ];

		$result = $validator->detect_chain(
			[
				'source' => '/a',
				'target' => '/b',
			],
			$rules
		);

		$this->assertTrue( $result['has_chain'] );
		$this->assertSame( [ '/a', '/b', '/c' ], $result['chain'] );
		$this->assertSame( '/c', $result['final'] );
		$this->assertFalse( $result['inconclusive'] );
	}

	public function test_chain_two_intermediates(): void {
		$validator = new Validator();
		$rules     = [
			$this->rule( 2, '/b', '/c' ),
			$this->rule( 3, '/c', '/d' ),
		];

		$result = $validator->detect_chain(
			[
				'source' => '/a',
				'target' => '/b',
			],
			$rules
		);

		$this->assertTrue( $result['has_chain'] );
		$this->assertSame( [ '/a', '/b', '/c', '/d' ], $result['chain'] );
		$this->assertSame( '/d', $result['final'] );
	}

	public function test_chain_longer_than_cap_is_inconclusive(): void {
		$validator = new Validator();
		$rules     = [];

		for ( $i = 1; $i <= 7; $i++ ) {
			$rules[] = $this->rule( $i, '/n' . (string) $i, '/n' . (string) ( $i + 1 ) );
		}

		$result = $validator->detect_chain(
			[
				'source' => '/start',
				'target' => '/n1',
			],
			$rules
		);

		$this->assertTrue( $result['has_chain'] );
		$this->assertNull( $result['final'] );
		$this->assertTrue( $result['inconclusive'] );
	}

	public function test_chain_through_external_terminates(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 2, '/b', 'https://external.example/x' ) ];

		$result = $validator->detect_chain(
			[
				'source' => '/a',
				'target' => '/b',
			],
			$rules
		);

		$this->assertTrue( $result['has_chain'] );
		$this->assertSame( 'https://external.example/x', $result['final'] );
		$this->assertFalse( $result['inconclusive'] );
	}

	public function test_chain_through_pattern_is_inconclusive(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 2, '/b', '/c', 'prefix' ) ];

		$result = $validator->detect_chain(
			[
				'source' => '/a',
				'target' => '/b',
			],
			$rules
		);

		$this->assertNull( $result['final'] );
		$this->assertTrue( $result['inconclusive'] );
	}

	public function test_single_hop_is_not_a_chain(): void {
		$validator = new Validator();

		$result = $validator->detect_chain(
			[
				'source' => '/a',
				'target' => '/b',
			],
			[]
		);

		$this->assertFalse( $result['has_chain'] );
		$this->assertSame( [ '/a', '/b' ], $result['chain'] );
		$this->assertSame( '/b', $result['final'] );
		$this->assertFalse( $result['inconclusive'] );
	}

	public function test_chain_never_reports_a_block(): void {
		$validator = new Validator();
		$rules     = [ $this->rule( 2, '/b', '/c' ) ];

		$result = $validator->detect_chain(
			[
				'source' => '/a',
				'target' => '/b',
			],
			$rules
		);

		$this->assertArrayNotHasKey( 'blocked', $result );
		$this->assertArrayNotHasKey( 'has_cycle', $result );
	}
}
