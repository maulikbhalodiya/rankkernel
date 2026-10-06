<?php
/**
 * ModuleRegistry tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\ModuleRegistry;

/**
 * Module Registry Test.
 */
final class ModuleRegistryTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test all returns map.
	 */
	public function test_all_returns_map(): void {
		$all = ModuleRegistry::all();

		$this->assertSame( ModuleRegistry::MODULES, $all );
		$this->assertCount( 14, $all );
		$this->assertArrayHasKey( 'metadata', $all );
		$this->assertSame( 'Metadata Engine', $all['metadata'] );
		$this->assertArrayHasKey( 'analysis', $all );
		$this->assertSame( 'Content Analysis', $all['analysis'] );
	}

	/**
	 * Test ids returns keys.
	 */
	public function test_ids_returns_keys(): void {
		$ids = ModuleRegistry::ids();

		$expected = array_map( 'strval', array_keys( ModuleRegistry::MODULES ) );
		$this->assertSame( $expected, $ids );
		$this->assertContains( 'sitemaps', $ids );
		$this->assertContains( '404', $ids );
		$this->assertContains( 'image-seo', $ids );
		$this->assertContains( 'headless', $ids );
		$this->assertContains( 'importer', $ids );
	}

	/**
	 * Test has returns true for known.
	 */
	public function test_has_returns_true_for_known(): void {
		$this->assertTrue( ModuleRegistry::has( 'metadata' ) );
		$this->assertTrue( ModuleRegistry::has( 'ai' ) );
		$this->assertTrue( ModuleRegistry::has( 'gutenberg' ) );
	}

	/**
	 * Test has returns false for unknown.
	 */
	public function test_has_returns_false_for_unknown(): void {
		$this->assertFalse( ModuleRegistry::has( 'unknown' ) );
		$this->assertFalse( ModuleRegistry::has( '' ) );
	}

	/**
	 * Test label returns label for known.
	 */
	public function test_label_returns_label_for_known(): void {
		$this->assertSame( 'Metadata Engine', ModuleRegistry::label( 'metadata' ) );
		$this->assertSame( 'Robots.txt & .htaccess', ModuleRegistry::label( 'robots' ) );
		$this->assertSame( 'A read only REST payload for headless builds. Planned.', ModuleRegistry::label( 'headless' ) );
		$this->assertSame( 'Importer', ModuleRegistry::label( 'importer' ) );
		$this->assertContains( 'image-seo', ModuleRegistry::PLANNED );
		$this->assertContains( 'importer', ModuleRegistry::PLANNED );
		$this->assertTrue( ModuleRegistry::isPlanned( 'importer' ) );
		$this->assertTrue( ModuleRegistry::isPlanned( 'ai' ) );
		$this->assertFalse( ModuleRegistry::isPlanned( 'metadata' ) );
	}

	/**
	 * Test label throws for unknown.
	 */
	public function test_label_throws_for_unknown(): void {
		$this->expectException( \InvalidArgumentException::class );
		ModuleRegistry::label( 'not-real' );
	}
}
