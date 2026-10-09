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
	 * Test a valid in tree filtered path is accepted.
	 */
	public function test_valid_in_tree_filtered_path_is_accepted(): void {
		$validPath  = ABSPATH . 'custom.htaccess';
		$this->path = $validPath;

		$this->assertSame( $validPath, ( new HtaccessFile() )->path() );
	}

	/**
	 * Test an out of tree filter path falls back to the default path.
	 */
	public function test_out_of_tree_filter_path_falls_back_to_default(): void {
		$this->path = '/etc/passwd';

		$this->assertSame( ABSPATH . '.htaccess', ( new HtaccessFile() )->path() );
	}

	/**
	 * Test a symlink leaf filter path falls back to the default path.
	 */
	/**
	 * A dangling symlink whose target does not exist must fall back.
	 *
	 * This is the case that a leaf check gated on file_exists() or realpath()
	 * cannot catch, because both of those fail on a link with no target, while a
	 * later write would still follow the link.
	 */
	public function test_dangling_symlink_filter_path_falls_back_to_default(): void {
		if ( ! function_exists( 'symlink' ) ) {
			$this->markTestSkipped( 'symlink function is unavailable.' );
		}

		$outsideDir = sys_get_temp_dir() . '/rk_dangling_' . uniqid();
		mkdir( $outsideDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		$symlink     = ABSPATH . 'htaccess_dangling_' . uniqid();
		$outsideFile = $outsideDir . '/pwned.txt';

		try {
			symlink( $outsideFile, $symlink );
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		if ( ! is_link( $symlink ) ) {
			rmdir( $outsideDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			$this->markTestSkipped( 'Symlink creation failed or is unsupported on this platform.' );
		}

		$this->path = $symlink;

		$this->assertSame( ABSPATH . '.htaccess', ( new HtaccessFile() )->path() );
		$this->assertFileDoesNotExist( $outsideFile );

		unlink( $symlink ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		rmdir( $outsideDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * A relative path must fall back rather than resolve against the CWD.
	 */
	public function test_relative_filter_path_falls_back_to_default(): void {
		$this->path = 'rk-relative.htaccess';

		$this->assertSame( ABSPATH . '.htaccess', ( new HtaccessFile() )->path() );
	}

	/**
	 * A path carrying a null byte must fall back before any filesystem call.
	 */
	public function test_null_byte_filter_path_falls_back_to_default(): void {
		$this->path = ABSPATH . "rk-null\0.htaccess";

		$this->assertSame( ABSPATH . '.htaccess', ( new HtaccessFile() )->path() );
	}

	/**
	 * An existing file inside the root must still be accepted.
	 */
	public function test_existing_file_inside_root_is_accepted(): void {
		$existing = tempnam( ABSPATH, 'rk_existing_' );

		$this->assertIsString( $existing );

		$this->path = $existing;

		$this->assertSame( $existing, ( new HtaccessFile() )->path() );

		unlink( $existing ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * Saving through a dangling symlink must not create a file outside the root.
	 */
	public function test_save_through_dangling_symlink_writes_nothing_outside_root(): void {
		if ( ! function_exists( 'symlink' ) ) {
			$this->markTestSkipped( 'symlink function is unavailable.' );
		}

		$outsideDir = sys_get_temp_dir() . '/rk_save_' . uniqid();
		mkdir( $outsideDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		$symlink     = ABSPATH . 'htaccess_save_' . uniqid();
		$outsideFile = $outsideDir . '/pwned.txt';

		try {
			symlink( $outsideFile, $symlink );
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		if ( ! is_link( $symlink ) ) {
			rmdir( $outsideDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			$this->markTestSkipped( 'Symlink creation failed or is unsupported on this platform.' );
		}

		$this->path      = $symlink;
		$this->supported = true;

		$file = new HtaccessFile();

		// The hostile filter is contained, so the write lands on the default
		// path. What must never happen is the link being followed outwards.
		$this->assertSame( ABSPATH . '.htaccess', $file->path() );

		$before = $this->readFixture( ABSPATH . '.htaccess' );

		$file->save( 'PWNED' );

		// The link was not followed: the outside target was never created, and
		// the write went to the contained default path instead.
		$this->assertFileDoesNotExist( $outsideFile, 'no file may be created outside the site root' );
		$this->assertNotSame( $before, $this->readFixture( ABSPATH . '.htaccess' ), 'the contained default path receives the write' );

		if ( null === $before ) {
			unlink( ABSPATH . '.htaccess' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		} else {
			file_put_contents( ABSPATH . '.htaccess', $before ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- restoring the shared temp fixture.
		}

		unlink( $symlink ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		rmdir( $outsideDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Reads a fixture path, returning null when it does not exist.
	 *
	 * @param string $path Absolute fixture path.
	 * @return string|null Contents, or null when absent.
	 */
	private function readFixture( string $path ): ?string {
		if ( ! is_file( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local test fixture.

		return is_string( $contents ) ? $contents : null;
	}

	/**
	 * A symlink at the leaf position must fall back to the default path.
	 */
	public function test_symlink_leaf_filter_path_falls_back_to_default(): void {
		if ( ! function_exists( 'symlink' ) ) {
			$this->markTestSkipped( 'symlink function is unavailable.' );
		}

		$target  = tempnam( sys_get_temp_dir(), 'rktarget' );
		$symlink = ABSPATH . 'htaccess_symlink_' . uniqid();

		$this->assertIsString( $target );

		try {
			symlink( $target, $symlink );
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		if ( ! is_link( $symlink ) ) {
			unlink( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			$this->markTestSkipped( 'Symlink creation failed or is unsupported on this platform.' );
		}

		$this->path = $symlink;

		$this->assertSame( ABSPATH . '.htaccess', ( new HtaccessFile() )->path() );

		unlink( $symlink ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * Test a climbed out path falls back to the default path.
	 */
	public function test_climbed_out_path_falls_back_to_default(): void {
		$this->path = ABSPATH . '../etc/passwd';

		$this->assertSame( ABSPATH . '.htaccess', ( new HtaccessFile() )->path() );
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

	/**
	 * Test backups land in the guarded uploads dir, never next to the live file.
	 */
	public function test_backups_live_outside_the_site_root_file(): void {
		$uploadBase = sys_get_temp_dir() . '/rkht-uploads-' . uniqid();

		mkdir( $uploadBase, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture prepares its own temp dir.

		Functions\when( 'wp_upload_dir' )->alias(
			static function () use ( $uploadBase ): array {
				return [ 'basedir' => $uploadBase ];
			}
		);

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();
		$dir  = $file->backupDir();

		$this->assertStringStartsWith( $uploadBase, $dir );
		$this->assertNotSame( dirname( (string) $temp ), $dir, 'Backups must not sit next to the live file.' );
		$this->assertFileExists( $dir . '/index.php' );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		$listed   = glob( $dir . '/*' );
		$dotted   = glob( $dir . '/.*' );
		$leftover = array();
		foreach ( array_merge( is_array( $listed ) ? $listed : array(), is_array( $dotted ) ? $dotted : array() ) as $leftover ) {
			if ( is_file( $leftover ) ) {
				unlink( $leftover ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleans its own temp dir.
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temp dir.
		rmdir( $uploadBase ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temp dir.
	}

	/**
	 * Test repeated saves keep at most BACKUP_KEEP backups.
	 */
	public function test_repeated_saves_prune_to_keep_limit(): void {
		$uploadBase = sys_get_temp_dir() . '/rkht-uploads-' . uniqid();

		mkdir( $uploadBase, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture prepares its own temp dir.

		Functions\when( 'wp_upload_dir' )->alias(
			static function () use ( $uploadBase ): array {
				return [ 'basedir' => $uploadBase ];
			}
		);

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		for ( $i = 0; $i < 7; $i++ ) {
			$result = $file->save( "# changed $i\n" );

			$this->assertTrue( $result['saved'] );
		}

		$backups = glob( $file->backupDir() . '/htaccess-backup-*' );

		$this->assertIsArray( $backups );
		$this->assertLessThanOrEqual( HtaccessFile::BACKUP_KEEP, count( $backups ) );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.

		foreach ( $backups as $backup ) {
			unlink( $backup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own backups.
		}

		// Capture the path once: backupDir() recreates its guard files as a
		// side effect, so calling it again inside rmdir() would repopulate
		// the directory being removed.
		$backupDir = $file->backupDir();

		$listedFiles = glob( $backupDir . '/*' );
		$dottedFiles = glob( $backupDir . '/.*' );
		foreach ( array_merge( is_array( $listedFiles ) ? $listedFiles : array(), is_array( $dottedFiles ) ? $dottedFiles : array() ) as $leftover ) {
			if ( is_file( $leftover ) ) {
				unlink( $leftover ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleans its own temp dir.
			}
		}

		rmdir( $backupDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temp dir.
		rmdir( $uploadBase ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temp dir.
	}
}
