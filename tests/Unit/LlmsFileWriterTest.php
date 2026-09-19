<?php
/**
 * Physical llms.txt writer tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsFileWriter;

/**
 * Llms File Writer Test.
 */
final class LlmsFileWriterTest extends TestCase {
	/**
	 * Path returned by the filter stub.
	 *
	 * @var string
	 */
	private string $path = '';

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->path = '';

		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/llms/physical_file' === $hook && '' !== $this->path ) {
					return $this->path;
				}

				return $value;
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test write creates a file when none exists.
	 */
	public function test_write_creates_when_absent(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );

		$this->assertIsString( $temp );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$this->path = $temp;

		$result = ( new LlmsFileWriter() )->write( "# Site\n" );

		$this->assertTrue( $result['written'] );
		$this->assertFileExists( $temp );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test write refuses to overwrite an existing file.
	 */
	public function test_write_refuses_to_overwrite(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "manual content\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$writer = new LlmsFileWriter();
		$result = $writer->write( "# Generated\n" );

		$this->assertFalse( $result['written'] );
		$this->assertSame( 'exists', $result['reason'] );
		$this->assertSame( "manual content\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}
}
