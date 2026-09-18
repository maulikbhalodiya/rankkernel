<?php
/**
 * Llms.txt markdown rendering tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsGenerator;

/**
 * Llms Generator Test.
 */
final class LlmsGeneratorTest extends TestCase {
	/**
	 * Test renders h1 and summary.
	 */
	public function test_renders_h1_and_summary(): void {
		$out = ( new LlmsGenerator() )->render( [], 'Example Site', 'A short summary.' );

		$this->assertStringContainsString( '# Example Site', $out );
		$this->assertStringContainsString( '> A short summary.', $out );
	}

	/**
	 * Test escapes markdown control characters.
	 */
	public function test_escapes_markdown_control_characters(): void {
		$sections = [
			[
				'title' => 'Posts',
				'items' => [
					[
						'title'   => 'A [tricky] #title (x)',
						'url'     => 'https://example.com/a',
						'excerpt' => 'Body [1] (note): #tag',
					],
				],
			],
		];

		$out = ( new LlmsGenerator() )->render( $sections, 'Site', '' );

		$this->assertStringContainsString( '\[tricky\]', $out );
		$this->assertStringContainsString( '\#title', $out );
		$this->assertStringContainsString( '\[1\]', $out );
		$this->assertStringContainsString( '\#tag', $out );
	}

	/**
	 * Test skips empty sections.
	 */
	public function test_skips_empty_sections(): void {
		$sections = [
			[
				'title' => 'Empty',
				'items' => [],
			],
		];

		$out = ( new LlmsGenerator() )->render( $sections, 'Site', '' );

		$this->assertStringNotContainsString( '## Empty', $out );
	}

	/**
	 * Test renders an item with excerpt.
	 */
	public function test_renders_item_with_excerpt(): void {
		$sections = [
			[
				'title' => 'Posts',
				'items' => [
					[
						'title'   => 'Hello',
						'url'     => 'https://example.com/hello',
						'excerpt' => 'World',
					],
				],
			],
		];

		$out = ( new LlmsGenerator() )->render( $sections, 'Site', '' );

		$this->assertStringContainsString( '- [Hello](https://example.com/hello): World', $out );
	}
}
