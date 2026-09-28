<?php
/**
 * AI crawler catalogue tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\CrawlerPolicy;

/**
 * Crawler Policy Test.
 */
final class CrawlerPolicyTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test the required crawler tokens are catalogued with a purpose.
	 */
	public function test_required_crawlers_catalogued(): void {
		$tokens = [];

		foreach ( CrawlerPolicy::all() as $crawler ) {
			$tokens[] = (string) $crawler['token'];

			$this->assertContains( (string) $crawler['purpose'], array_keys( CrawlerPolicy::PURPOSES ) );
			$this->assertContains( (string) $crawler['default'], [ CrawlerPolicy::ALLOW, CrawlerPolicy::BLOCK, CrawlerPolicy::CUSTOM ] );
		}

		foreach ( [ 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'CCBot' ] as $required ) {
			$this->assertContains( $required, $tokens );
		}
	}

	/**
	 * Test defaults block training and allow search and user fetches.
	 */
	public function test_defaults_block_training_allow_search(): void {
		$defaults = CrawlerPolicy::defaults();

		$this->assertSame( CrawlerPolicy::BLOCK, $defaults['gptbot'] );
		$this->assertSame( CrawlerPolicy::BLOCK, $defaults['google-extended'] );
		$this->assertSame( CrawlerPolicy::BLOCK, $defaults['ccbot'] );
		$this->assertSame( CrawlerPolicy::ALLOW, $defaults['oai-searchbot'] );
		$this->assertSame( CrawlerPolicy::ALLOW, $defaults['perplexitybot'] );
		$this->assertSame( CrawlerPolicy::ALLOW, $defaults['chatgpt-user'] );
	}

	/**
	 * Test policy sanitisation rejects unknown values.
	 */
	public function test_sanitize_policy_rejects_unknown(): void {
		$this->assertSame( CrawlerPolicy::BLOCK, CrawlerPolicy::sanitizePolicy( 'block' ) );
		$this->assertSame( CrawlerPolicy::ALLOW, CrawlerPolicy::sanitizePolicy( 'ALLOW' ) );
		$this->assertSame( CrawlerPolicy::CUSTOM, CrawlerPolicy::sanitizePolicy( 'nonsense' ) );
		$this->assertSame( CrawlerPolicy::CUSTOM, CrawlerPolicy::sanitizePolicy( [ 'x' ] ) );
	}

	/**
	 * Test map sanitisation keeps known slugs and fills defaults.
	 */
	public function test_sanitize_map_fills_defaults(): void {
		$map = CrawlerPolicy::sanitizeMap(
			[
				'gptbot'  => 'allow',
				'unknown' => 'block',
			]
		);

		$this->assertSame( 'allow', $map['gptbot'] );
		$this->assertArrayNotHasKey( 'unknown', $map );
		$this->assertSame( CrawlerPolicy::ALLOW, $map['oai-searchbot'] );
	}

	/**
	 * Test malformed filter entries fall back or drop, well formed survive.
	 */
	public function test_malformed_filter_entries_fall_back_or_drop(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/robots/crawlers' === $hook ) {
					return [
						'gptbot' => [
							'label' => 'Broken',
							'token' => "Bad\r\nToken",
						],
						'bogus'  => 'not an array',
						'intkey' => [
							'label'   => 'Fine',
							'token'   => 'FineBot',
							'purpose' => 'training',
							'default' => 'block',
							'note'    => 'A new bot.',
						],
					];
				}

				return $value;
			}
		);

		$all = CrawlerPolicy::all();

		$this->assertSame( 'GPTBot', $all['gptbot']['token'] );
		$this->assertArrayNotHasKey( 'bogus', $all );
		$this->assertSame( 'FineBot', $all['intkey']['token'] );
	}
}
