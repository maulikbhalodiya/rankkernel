<?php
/**
 * Payload importer tests, mapper rows into our postmeta with conflict modes.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\PayloadImporter;

/**
 * Payload Importer Test.
 */
final class PayloadImporterTest extends TestCase {
	/**
	 * Set up Brain Monkey.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test skip mode fills pristine fields and keeps customized ones.
	 */
	public function test_skip_mode_fills_pristine_and_keeps_customized(): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				return [ 'title' => 'Mine' ];
			}
		);

		$captured = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$captured ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress update_post_meta signature.
				$captured = $value;

				return true;
			}
		);

		$result = PayloadImporter::applyRows(
			[
				[
					'post_id' => 7,
					'fields'  => [
						'title'       => 'Imported',
						'description' => 'Imported desc',
					],
				],
				[
					'post_id' => 8,
					'fields'  => [ 'title' => 'Imported' ],
				],
			],
			'skip'
		);

		$this->assertSame(
			[
				'applied' => 1,
				'skipped' => 1,
				'failed'  => 0,
			],
			$result
		);
		$this->assertSame( 'Mine', $captured['title'] );
		$this->assertSame( 'Imported desc', $captured['description'] );
	}

	/**
	 * Test overwrite mode replaces customized fields.
	 */
	public function test_overwrite_mode_replaces_customized(): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				return [ 'title' => 'Mine' ];
			}
		);

		$captured = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$captured ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress update_post_meta signature.
				$captured = $value;

				return true;
			}
		);

		$result = PayloadImporter::applyRows(
			[
				[
					'post_id' => 7,
					'fields'  => [ 'title' => 'Imported' ],
				],
			],
			'overwrite'
		);

		$this->assertSame(
			[
				'applied' => 1,
				'skipped' => 0,
				'failed'  => 0,
			],
			$result
		);
		$this->assertSame( 'Imported', $captured['title'] );
	}

	/**
	 * Test a failed write counts as failed without throwing.
	 */
	public function test_failed_write_counts_failed(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( false );

		$result = PayloadImporter::applyRows(
			[
				[
					'post_id' => 7,
					'fields'  => [ 'title' => 'Imported' ],
				],
			],
			'skip'
		);

		$this->assertSame(
			[
				'applied' => 0,
				'skipped' => 0,
				'failed'  => 1,
			],
			$result
		);
	}

	/**
	 * Test empty rows write nothing.
	 */
	public function test_empty_rows_write_nothing(): void {
		Functions\expect( 'update_post_meta' )->never();

		$result = PayloadImporter::applyRows( [], 'skip' );

		$this->assertSame(
			[
				'applied' => 0,
				'skipped' => 0,
				'failed'  => 0,
			],
			$result
		);
	}
}
