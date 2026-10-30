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
	 * Writable uploads directory used for backup tests.
	 *
	 * @var string
	 */
	private string $uploadsDir = '';

	/**
	 * In-memory private backup option.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $backupStore = [];

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

		$this->backupStore = [];

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = [] ): mixed {
				return HtaccessFile::BACKUP_OPTION === $key ? $this->backupStore : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool {
				unset( $autoload );

				if ( HtaccessFile::BACKUP_OPTION === $key && is_array( $value ) ) {
					$this->backupStore = $value;

					return true;
				}

				return true;
			}
		);

		$this->uploadsDir = sys_get_temp_dir() . '/rkht-uploads-' . uniqid();
		mkdir( $this->uploadsDir, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates a real writable uploads directory.
		Functions\when( 'wp_upload_dir' )->justReturn( [ 'basedir' => $this->uploadsDir ] );

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
		$this->removeUploadsDir();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Remove the temporary uploads tree created for backup tests.
	 */
	private function removeUploadsDir(): void {
		if ( '' === $this->uploadsDir || ! is_dir( $this->uploadsDir ) ) {
			return;
		}

		$backupDir = $this->uploadsDir . '/rankkernel-htaccess-backups';
		$files     = glob( $backupDir . '/*' );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temporary files.
				}
			}
		}

		foreach ( [ 'index.php', '.htaccess' ] as $guard ) {
			$guardPath = $backupDir . '/' . $guard;

			if ( is_file( $guardPath ) ) {
				unlink( $guardPath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temporary guard files.
			}
		}

		if ( is_dir( $backupDir ) ) {
			rmdir( $backupDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temporary directory.
		}

		rmdir( $this->uploadsDir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temporary directory.
	}

	/**
	 * Test read, then save stores a private backup before the write.
	 */
	public function test_read_and_save_writes_a_private_backup(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		$this->assertTrue( $file->exists() );
		$this->assertSame( "# original\n", $file->read() );

		$result = $file->save( "# changed\n" );

		$this->assertTrue( $result['saved'] );
		$this->assertStringStartsWith( 'db:', $result['backup'] );

		$backups = $file->backups();

		$this->assertCount( 1, $backups );
		$this->assertSame( "# original\n", $backups[0]['content'] );
		$this->assertSame( "# changed\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test backup tokens are unique and opaque.
	 */
	public function test_backup_tokens_are_unique_and_opaque(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file   = new HtaccessFile();
		$first  = $file->save( "# one\n" );
		$second = $file->save( "# two\n" );

		$this->assertNotSame( $first['backup'], $second['backup'] );
		$this->assertMatchesRegularExpression( '/^db:\d+:[a-f0-9]{16}$/', $first['backup'] );
		$this->assertCount( 2, $file->backups() );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test save fails closed when the private backup option cannot be written.
	 */
	public function test_save_fails_closed_when_private_backup_storage_fails(): void {
		Functions\when( 'update_option' )->justReturn( false );

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$result = ( new HtaccessFile() )->save( "# changed\n" );

		$this->assertFalse( $result['saved'] );
		$this->assertSame( 'backup_failed', $result['reason'] );
		$this->assertSame( "# original\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test a short write is a failure and leaves the live file untouched.
	 *
	 * The file_put_contents() function can report fewer bytes than the
	 * content length without returning false. The temp file is discarded,
	 * so a truncated .htaccess can never reach the live path.
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
	 * Test a Windows drive-letter path inside the root is accepted.
	 */
	public function test_windows_drive_letter_path_inside_root_is_accepted(): void {
		$existing = tempnam( ABSPATH, 'rk_existing_' );

		$this->assertIsString( $existing );

		$winPath    = 'C:' . $existing;
		$this->path = $winPath;

		if ( 'C:' !== substr( ABSPATH, 0, 2 ) ) {
			// On non-Windows OS, realpath('C:/tmp/...') returns false, so path() falls back.
			$this->assertSame( ABSPATH . '.htaccess', ( new HtaccessFile() )->path() );
		} else {
			$this->assertSame( $winPath, ( new HtaccessFile() )->path() );
		}

		unlink( $existing ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
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
	 * Test repeated saves keep at most BACKUP_KEEP private snapshots.
	 */
	public function test_repeated_saves_prune_to_keep_limit(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# original\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		for ( $i = 0; $i < 7; $i++ ) {
			$result = $file->save( "# changed $i\n" );

			$this->assertTrue( $result['saved'] );
		}

		$this->assertCount( HtaccessFile::BACKUP_KEEP, $file->backups() );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test generateBlock only emits whitelisted directive lines.
	 */
	public function test_generate_block_ignores_unknown_ids(): void {
		$file = new HtaccessFile();

		$block = $file->generateBlock(
			[
				'not_a_directive'          => '1',
				'block_directory_browsing' => '1',
				'<script>'                 => '1',
			]
		);

		$this->assertStringStartsWith( HtaccessFile::MARKER_BEGIN . "\n", $block );
		$this->assertStringEndsWith( HtaccessFile::MARKER_END . "\n", $block );
		$this->assertStringContainsString( 'Options -Indexes', $block );
		$this->assertStringNotContainsString( 'RewriteCond', $block );
		$this->assertStringNotContainsString( '<script>', $block );
	}

	/**
	 * Test generateBlock returns an empty string with no known selection.
	 */
	public function test_generate_block_returns_empty_with_no_selection(): void {
		$file = new HtaccessFile();

		$this->assertSame( '', $file->generateBlock( [] ) );
		$this->assertSame( '', $file->generateBlock( [ 'unknown_id' => '1' ] ) );
	}

	/**
	 * Test saveBlock replaces the managed block and keeps surrounding bytes.
	 *
	 * The WordPress block after the managed block must survive verbatim,
	 * and the old managed directives must be gone.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_save_block_replaces_block_and_preserves_surrounding(): void {
		$surrounding = "# BEGIN WordPress\nRewriteRule ^index\.php$ - [L]\n# END WordPress\n";
		$original    = HtaccessFile::MARKER_BEGIN . "\n# Old note\nOptions -Indexes\n" . HtaccessFile::MARKER_END . "\n\n" . $surrounding;

		$uploadBase = sys_get_temp_dir() . '/rkht-uploads-' . uniqid();

		mkdir( $uploadBase, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture prepares its own temp dir.

		Functions\when( 'wp_upload_dir' )->alias(
			static function () use ( $uploadBase ): array {
				return [ 'basedir' => $uploadBase ];
			}
		);

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, $original ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		$result = $file->saveBlock( [ 'block_ai_crawlers' => '1' ] );

		$this->assertTrue( $result['saved'] );

		$updated = $file->read();

		$this->assertStringContainsString( 'RewriteCond %{HTTP_USER_AGENT} (GPTBot|ClaudeBot|Claude-Web|CCBot|Google-Extended|PerplexityBot|Bytespider|FacebookBot) [NC]', $updated );
		$this->assertStringContainsString( 'RewriteRule ^ - [F,L]', $updated );
		$this->assertStringNotContainsString( 'Options -Indexes', $updated );
		$this->assertStringNotContainsString( '# Old note', $updated );
		$this->assertSame( $surrounding, substr( (string) $updated, (int) strpos( (string) $updated, '# BEGIN WordPress' ) ), 'Content outside the managed block must survive byte for byte.' );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test saveBlock removes the managed block with an empty selection.
	 */
	public function test_save_block_empty_selection_removes_block(): void {
		$surrounding = "# BEGIN WordPress\nRewriteRule ^index\.php$ - [L]\n# END WordPress\n";
		$original    = HtaccessFile::MARKER_BEGIN . "\nOptions -Indexes\n" . HtaccessFile::MARKER_END . "\n\n" . $surrounding;

		$uploadBase = sys_get_temp_dir() . '/rkht-uploads-' . uniqid();

		mkdir( $uploadBase, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture prepares its own temp dir.

		Functions\when( 'wp_upload_dir' )->alias(
			static function () use ( $uploadBase ): array {
				return [ 'basedir' => $uploadBase ];
			}
		);

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, $original ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		$result = $file->saveBlock( [] );

		$this->assertTrue( $result['saved'] );
		$this->assertFalse( $file->hasBlock() );
		$this->assertSame( $surrounding, $file->read(), 'Only the managed block may disappear.' );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test an empty selection on an unmarked file changes nothing.
	 */
	public function test_save_block_without_markers_is_a_noop(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# plain content\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file   = new HtaccessFile();
		$result = $file->saveBlock( [] );

		$this->assertTrue( $result['saved'] );
		$this->assertSame( '', $result['backup'] );
		$this->assertSame( "# plain content\n", $file->read() );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test an empty selection on a missing file does not create one.
	 */
	public function test_save_block_missing_file_is_a_noop(): void {
		$path       = sys_get_temp_dir() . '/rkht-missing-' . uniqid() . '/.htaccess';
		$this->path = $path;

		$result = ( new HtaccessFile() )->saveBlock( [] );

		$this->assertTrue( $result['saved'] );
		$this->assertFalse( file_exists( $path ) );
	}

	/**
	 * Test removeBlock on an unmarked file changes nothing.
	 */
	public function test_remove_block_without_markers_is_a_noop(): void {
		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# plain content\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$result = ( new HtaccessFile() )->removeBlock();

		$this->assertTrue( $result['saved'] );
		$this->assertSame( "# plain content\n", (string) file_get_contents( $temp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads its own temp file.

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test removeBlock on a missing file does not create one.
	 */
	public function test_remove_block_missing_file_is_a_noop(): void {
		$path       = sys_get_temp_dir() . '/rkht-missing-' . uniqid() . '/.htaccess';
		$this->path = $path;

		$result = ( new HtaccessFile() )->removeBlock();

		$this->assertTrue( $result['saved'] );
		$this->assertFalse( file_exists( $path ) );
	}

	/**
	 * Test saveBlock writes nothing when the document would not change.
	 */
	public function test_save_block_is_a_noop_when_unchanged(): void {
		$block = ( new HtaccessFile() )->generateBlock( [ 'block_directory_browsing' => '1' ] );

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, $block . "\n# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		$result = $file->saveBlock(
			[
				'block_directory_browsing' => '1',
				'not_a_directive'          => '1',
			]
		);

		$this->assertTrue( $result['saved'] );
		$this->assertSame( '', $result['backup'], 'A no-op save must not take a backup.' );
		$this->assertFalse( $result['saved'] && '' !== $result['reason'] );
		$this->assertTrue( $file->hasBlock() );
		$this->assertSame( [ 'block_directory_browsing' ], $file->blockSelection() );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}

	/**
	 * Test hasBlock and blockSelection reflect the file state.
	 */
	public function test_has_block_and_selection_reflect_file(): void {
		$uploadBase = sys_get_temp_dir() . '/rkht-uploads-' . uniqid();

		mkdir( $uploadBase, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture prepares its own temp dir.

		Functions\when( 'wp_upload_dir' )->alias(
			static function () use ( $uploadBase ): array {
				return [ 'basedir' => $uploadBase ];
			}
		);

		$temp = tempnam( sys_get_temp_dir(), 'rkht' );

		$this->assertIsString( $temp );

		file_put_contents( $temp, "# BEGIN WordPress\n# END WordPress\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes a temp file outside the plugin.

		$this->path = $temp;

		$file = new HtaccessFile();

		$this->assertFalse( $file->hasBlock() );
		$this->assertSame( [], $file->blockSelection() );

		$file->saveBlock(
			[
				'block_directory_browsing' => '1',
				'block_ai_crawlers'        => '1',
			]
		);

		$this->assertTrue( $file->hasBlock() );
		$this->assertSame( [ 'block_directory_browsing', 'block_ai_crawlers' ], $file->blockSelection() );

		$file->saveBlock( [] );

		$this->assertFalse( $file->hasBlock() );
		$this->assertSame( [], $file->blockSelection() );

		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
	}
}
