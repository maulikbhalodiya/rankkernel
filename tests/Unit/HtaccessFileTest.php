<?php
/**
 * .htaccess file handling tests.
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
use RankKernel\Admin\HtaccessFile;

/**
 * Htaccess File Test.
 */
final class HtaccessFileTest extends TestCase {
	/**
	 * Path returned by the path filter.
	 *
	 * @var string
	 */
	private string $path = '';

	/**
	 * Whether the server is reported as supported.
	 *
	 * @var bool
	 */
	private bool $supported = true;

	/**
	 * Byte count the harness reports for file_put_contents().
	 *
	 * Zero delegates to the real built-in.
	 *
	 * @var int
	 */
	public static int $shortWriteBytes = 0;

	/**
	 * Dispatch for the RankKernel\Admin file_put_contents() test harness.
	 *
	 * @param string $filename Target path.
	 * @param mixed  $data     Content.
	 * @param int    $flags    Stream flags.
	 * @param mixed  $context  Stream context.
	 * @return int|false The result.
	 */
	public static function filePutContentsShim( string $filename, mixed $data, int $flags = 0, mixed $context = null ): int|false {
		if ( self::$shortWriteBytes > 0 ) {
			$written = min( self::$shortWriteBytes, strlen( (string) $data ) );

			\file_put_contents( $filename, substr( (string) $data, 0, $written ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test simulates a partial write into its own temp file.

			return $written;
		}

		return \file_put_contents( $filename, $data, $flags, $context ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- harness delegates to the real built-in for every test that is not simulating a partial write.
	}

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->path      = '';
		$this->supported = true;

		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/htaccess/path' === $hook && '' !== $this->path ) {
					return $this->path;
				}
				if ( 'rankkernel/htaccess/supported' === $hook ) {
					return $this->supported;
				}

				return $value;
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => trim( $value ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test read, then save writes a backup first.
	 */
	public function test_read_and_save_writes_a_backup(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		$this->assertTrue( $file->exists() );
		$this->assertSame( "# original\n", $file->read() );

		$result = $file->save( "# changed\n" );

		$this->assertTrue( $result['saved'] );
		$this->assertNotSame( '', $result['backup'] );
		$this->assertFileExists( $result['backup'] );
		$this->assertSame( "# original\n", (string) file_get_contents( $result['backup'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own backup.
		$this->assertSame( "# changed\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
		unlink( $result['backup'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own backup.
	}

	/**
	 * Test a short write is a failure and the backup is restored.
	 *
	 * The file_put_contents() function can report fewer bytes than the
	 * content length without returning false. A truncated .htaccess can
	 * break the whole site, so the save must report failure and the
	 * pre-write backup must be put back. Runs in its own process so the
	 * namespaced shim below is defined before the first call to the
	 * built-in is resolved.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_short_write_is_a_failure_and_restores_the_backup(): void {
		if ( ! function_exists( 'RankKernel\Admin\file_put_contents' ) ) {
			// The production class calls the unqualified built-in, so a
			// namespaced function of the same name intercepts it. The shim
			// delegates to the real built-in unless a test asks for a short
			// write, so the rest of the suite is unaffected.
			eval( 'namespace RankKernel\Admin; function file_put_contents( string $filename, mixed $data, int $flags = 0, mixed $context = null ): int|false { return \RankKernel\Tests\Unit\HtaccessFileTest::filePutContentsShim( $filename, $data, $flags, $context ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only seam for the unqualified built-in call, never evaluated in production.
		}

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path            = $temp;
		self::$shortWriteBytes = 4;

		try {
			$result = ( new HtaccessFile() )->save( "# changed content\n" );
		} finally {
			self::$shortWriteBytes = 0;
		}

		$this->assertFalse( $result['saved'] );
		$this->assertSame( 'write_failed', $result['reason'] );
		$this->assertNotSame( '', $result['backup'] );
		$this->assertSame( "# original\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
		unlink( $result['backup'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own backup.
	}

	/**
	 * Test an unsupported server refuses to save.
	 */
	public function test_unsupported_server_refuses_to_save(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		$this->path      = $temp;
		$this->supported = false;

		$file   = new HtaccessFile();
		$result = $file->save( "# x\n" );

		$this->assertFalse( $result['saved'] );
		$this->assertSame( 'unsupported', $result['reason'] );
		$this->assertFalse( $file->isSupported() );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test that DISALLOW_FILE_MODS constant blocks saving and writability.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_disallow_file_mods_blocks_writability_and_saving(): void {
		if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
			define( 'DISALLOW_FILE_MODS', true );
		}

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path      = $temp;
		$this->supported = true;

		$file = new HtaccessFile();

		$this->assertFalse( $file->isWritable() );

		$result = $file->save( "# attempt\n" );

		$this->assertFalse( $result['saved'] );
		$this->assertSame( 'not_writable', $result['reason'] );
		$this->assertSame( '', $result['backup'] );
		$this->assertSame( "# original\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test that DISALLOW_FILE_EDIT constant blocks saving and writability.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_disallow_file_edit_blocks_writability_and_saving(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path      = $temp;
		$this->supported = true;

		$file = new HtaccessFile();

		$this->assertFalse( $file->isWritable() );

		$result = $file->save( "# attempt\n" );

		$this->assertFalse( $result['saved'] );
		$this->assertSame( 'not_writable', $result['reason'] );
		$this->assertSame( '', $result['backup'] );
		$this->assertSame( "# original\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}
}
