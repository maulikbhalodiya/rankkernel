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
}
