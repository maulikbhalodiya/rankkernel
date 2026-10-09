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
		Functions\when( 'update_option' )->justReturn( true );

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
	 * Test a symlink filter path falls back to the default path.
	 */
	public function test_symlink_filter_path_falls_back_to_default(): void {
		$link = ABSPATH . 'llms-symlink-test.txt';

		if ( file_exists( $link ) || is_link( $link ) ) {
			unlink( $link ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		if ( function_exists( 'symlink' ) ) {
			try {
				symlink( '/etc/passwd', $link );
			} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}

			if ( is_link( $link ) ) {
				$this->path = $link;
				$result     = ( new LlmsFileWriter() )->path();
				unlink( $link ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

				$this->assertSame( ABSPATH . 'llms.txt', $result );

				return;
			}
		}

		$this->markTestSkipped( 'Symlinks not supported in this environment.' );
	}

	/**
	 * Test a filter path with a null byte falls back to the default path.
	 */
	public function test_null_byte_filter_path_falls_back_to_default(): void {
		$this->path = ABSPATH . "llms.txt\0.php";

		$this->assertSame( ABSPATH . 'llms.txt', ( new LlmsFileWriter() )->path() );
	}

	/**
	 * Test a relative filter path falls back to the default path.
	 */
	public function test_relative_filter_path_falls_back_to_default(): void {
		$this->path = 'llms.txt';

		$this->assertSame( ABSPATH . 'llms.txt', ( new LlmsFileWriter() )->path() );
	}

	/**
	 * Test a dangling symlink filter path cannot write outside the site root.
	 *
	 * A symlink whose target does not exist fails both file_exists() and
	 * realpath() on the leaf, so a containment check gated on either would let
	 * the write follow the link and create a file outside ABSPATH. The writer
	 * must reject the leaf symlink before the write and never touch the target.
	 */
	public function test_dangling_symlink_filter_path_cannot_write_outside_root(): void {
		if ( ! function_exists( 'symlink' ) ) {
			$this->markTestSkipped( 'Symlinks not supported in this environment.' );
		}

		$outsideDir = '';

		foreach ( [ '/var/tmp', '/dev/shm' ] as $candidate ) {
			if ( is_dir( $candidate ) && is_writable( $candidate ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- test fixture probes for a writable directory outside the site root.
				$outsideDir = $candidate;

				break;
			}
		}

		if ( '' === $outsideDir ) {
			$this->markTestSkipped( 'No writable directory outside ABSPATH to prove the escape target.' );
		}

		$link    = ABSPATH . 'llms-dangling-' . uniqid() . '.txt';
		$target  = $outsideDir . '/rkllms-escape-' . uniqid() . '.txt';
		$default = ABSPATH . 'llms.txt';

		if ( file_exists( $default ) ) {
			unlink( $default ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes a leftover default file.
		}

		try {
			symlink( $target, $link );
		} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- a failed symlink is reported by the is_link guard below.
		}

		if ( ! is_link( $link ) ) {
			$this->markTestSkipped( 'Could not create a test symlink.' );
		}

		try {
			$this->path = $link;

			Functions\when( 'update_option' )->justReturn( true );

			$writer = new LlmsFileWriter();
			$result = $writer->write( "# Generated\n" );

			$this->assertFileDoesNotExist( $target, 'The write must never create a file outside the site root.' );
			$this->assertSame( $default, $writer->path() );
			$this->assertTrue( $result['written'] );
			$this->assertFileExists( $default );
		} finally {
			if ( is_link( $link ) ) {
				unlink( $link ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own symlink.
			}

			if ( file_exists( $target ) ) {
				unlink( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes an escaped file when a bug created one.
			}

			if ( file_exists( $default ) ) {
				unlink( $default ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own default file.
			}
		}
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

	/**
	 * Test that both constants together still block writing.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_both_constants_together_block_writing(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
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

	/**
	 * Test that a constant defined but false does not block writing.
	 *
	 * The guard tests the value, not merely the definition, matching the wording
	 * WordPress uses for these constants. A site that defines the constant as false is
	 * explicitly allowing file edits, so the write must still succeed.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_constant_defined_as_false_does_not_block_writing(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', false );
		}
		if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
			define( 'DISALLOW_FILE_MODS', false );
		}

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
	 * Stub the managed-path option in memory.
	 *
	 * @return array<string, string> Reference to the option store, keyed by test.
	 */
	private function stubManagedOption(): array {
		$store = [];

		Functions\when( 'wp_rand' )->alias( static fn(): int => 12345 );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( &$store ): mixed {
				return array_key_exists( $key, $store ) ? $store[ $key ] : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$store ): bool {
				$store[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( string $key ) use ( &$store ): bool {
				unset( $store[ $key ] );

				return true;
			}
		);

		return $store;
	}

	/**
	 * Test delete removes a file the writer created and clears the record.
	 */
	public function test_delete_removes_managed_file(): void {
		$this->stubManagedOption();

		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );

		$this->assertIsString( $temp );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$this->path = $temp;

		$writer = new LlmsFileWriter();

		$this->assertTrue( $writer->write( "# Site\n" )['written'] );
		$this->assertFileExists( $temp );

		$result = $writer->delete();

		$this->assertTrue( $result['deleted'] );
		$this->assertFileDoesNotExist( $temp );
	}

	/**
	 * Test delete never touches a hand made file the writer refused to overwrite.
	 */
	public function test_delete_refuses_unmanaged_file(): void {
		$this->stubManagedOption();

		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "manual content\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$writer = new LlmsFileWriter();
		$write  = $writer->write( "# Generated\n" );

		$this->assertFalse( $write['written'] );

		$result = $writer->delete();

		$this->assertFalse( $result['deleted'] );
		$this->assertSame( 'unmanaged', $result['reason'] );
		$this->assertSame( "manual content\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test delete on a missing file returns cleanly and drops a stale record.
	 */
	public function test_delete_missing_file_returns_cleanly(): void {
		$this->stubManagedOption();

		$temp = tempnam( sys_get_temp_dir(), 'rkllms' );

		$this->assertIsString( $temp );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$this->path = $temp;

		$writer = new LlmsFileWriter();

		$this->assertTrue( $writer->write( "# Site\n" )['written'] );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- simulate external removal.

		$result = $writer->delete();

		$this->assertFalse( $result['deleted'] );
		$this->assertSame( 'missing', $result['reason'] );
	}
}
