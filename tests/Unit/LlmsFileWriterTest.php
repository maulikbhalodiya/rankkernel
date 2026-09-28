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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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

	/**
	 * Test an out of tree filter path falls back to the default path.
	 */
	public function test_out_of_tree_filter_path_falls_back_to_default(): void {
		$this->path = '/etc/passwd';

		$this->assertSame( ABSPATH . 'llms.txt', ( new LlmsFileWriter() )->path() );
	}

	/**
	 * Test a climbed out path falls back to the default path.
	 */
	public function test_climbed_out_path_falls_back_to_default(): void {
		$this->path = ABSPATH . '../etc/passwd';

		$this->assertSame( ABSPATH . 'llms.txt', ( new LlmsFileWriter() )->path() );
	}

	/**
	 * Test write reports a failure when the target directory is not writable.
	 */
	public function test_write_reports_failure_when_directory_not_writable(): void {
		$dir = sys_get_temp_dir() . '/rkllms-' . uniqid();

		$this->assertTrue( mkdir( $dir, 0755 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates its own temp directory.
		chmod( $dir, 0555 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- test fixture removes write permission to exercise the guard.

		$this->path = $dir . '/llms.txt';

		$warned = false;

		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- the test turns a write warning into an assertion so the is_writable guard is proven.
			static function () use ( &$warned ): bool {
				$warned = true;

				return true;
			}
		);

		try {
			$result = ( new LlmsFileWriter() )->write( "# Site\n" );
		} finally {
			restore_error_handler();
		}

		$this->assertFalse( $warned );
		$this->assertFalse( $result['written'] );
		$this->assertSame( 'write_failed', $result['reason'] );

		chmod( $dir, 0755 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- test fixture restores permission so it can clean up.
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temp directory.
	}

	/**
	 * Test that DISALLOW_FILE_EDIT constant blocks writing.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_disallow_file_edit_blocks_writing(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );

		$this->assertIsString( $temp );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$this->path = $temp;

		$result = ( new LlmsFileWriter() )->write( "# Site\n" );

		$this->assertFalse( $result['written'] );
		$this->assertSame( 'not_writable', $result['reason'] );
		$this->assertFileDoesNotExist( $temp );
	}

	/**
	 * Test that DISALLOW_FILE_MODS constant blocks writing.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_disallow_file_mods_blocks_writing(): void {
		if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
			define( 'DISALLOW_FILE_MODS', true );
		}

		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );

		$this->assertIsString( $temp );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$this->path = $temp;

		$result = ( new LlmsFileWriter() )->write( "# Site\n" );

		$this->assertFalse( $result['written'] );
		$this->assertSame( 'not_writable', $result['reason'] );
		$this->assertFileDoesNotExist( $temp );
	}
}
