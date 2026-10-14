<?php
/**
 * Term payload importer tests, mapper rows into our termmeta with conflict modes.
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
 * Term Payload Importer Test.
 */
final class PayloadImporterTermTest extends TestCase {
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
	 * Test skip mode fills pristine term fields and keeps customized ones.
	 */
	public function test_skip_mode_fills_pristine_and_keeps_customized(): void {
		Functions\when( 'get_term_meta' )->alias(
			static function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_meta signature.
				return [ 'title' => 'Mine' ];
			}
		);

		$captured = null;
		Functions\when( 'update_term_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$captured ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress update_term_meta signature.
				$captured = $value;

				return true;
			}
		);

		$result = PayloadImporter::applyTermRows(
			[
				[
					'term_id' => 7,
					'fields'  => [
						'title'       => 'Imported',
						'description' => 'Imported desc',
					],
				],
				[
					'term_id' => 8,
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
	 * Test overwrite mode replaces customized term fields.
	 */
	public function test_overwrite_mode_replaces_customized(): void {
		Functions\when( 'get_term_meta' )->alias(
			static function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_meta signature.
				return [ 'title' => 'Mine' ];
			}
		);

		$captured = null;
		Functions\when( 'update_term_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$captured ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress update_term_meta signature.
				$captured = $value;

				return true;
			}
		);

		$result = PayloadImporter::applyTermRows(
			[
				[
					'term_id' => 7,
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
	 * Test a failed term write counts as failed without throwing.
	 */
	public function test_failed_write_counts_failed(): void {
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'update_term_meta' )->justReturn( false );

		$result = PayloadImporter::applyTermRows(
			[
				[
					'term_id' => 7,
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
	 * Test empty term rows write nothing.
	 */
	public function test_empty_rows_write_nothing(): void {
		Functions\expect( 'update_term_meta' )->never();

		$result = PayloadImporter::applyTermRows( [], 'skip' );

		$this->assertSame(
			[
				'applied' => 0,
				'skipped' => 0,
				'failed'  => 0,
			],
			$result
		);
	}

	/**
	 * Test an unknown mode throws.
	 */
	public function test_unknown_mode_throws(): void {
		$this->expectException( \InvalidArgumentException::class );

		PayloadImporter::applyTermRows( [], 'merge' );
	}

	/**
	 * Test a missing stored key counts as pristine.
	 */
	public function test_missing_key_counts_as_pristine(): void {
		Functions\when( 'get_term_meta' )->justReturn( [] );

		$captured = null;
		Functions\when( 'update_term_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$captured ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress update_term_meta signature.
				$captured = $value;

				return true;
			}
		);

		$result = PayloadImporter::applyTermRows(
			[
				[
					'term_id' => 7,
					'fields'  => [ 'title' => 'Imported' ],
				],
			],
			'skip'
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
	 * Test nested robots subfields merge independently.
	 */
	public function test_nested_robots_merge_independently(): void {
		Functions\when( 'get_term_meta' )->alias(
			static function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_meta signature.
				return [ 'robots' => [ 'index' => false ] ];
			}
		);

		$captured = null;
		Functions\when( 'update_term_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$captured ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress update_term_meta signature.
				$captured = $value;

				return true;
			}
		);

		$result = PayloadImporter::applyTermRows(
			[
				[
					'term_id' => 7,
					'fields'  => [
						'robots' => [
							'index'     => true,
							'noarchive' => true,
						],
					],
				],
			],
			'skip'
		);

		$this->assertSame(
			[
				'applied' => 1,
				'skipped' => 0,
				'failed'  => 0,
			],
			$result
		);
		$this->assertFalse( $captured['robots']['index'] );
		$this->assertTrue( $captured['robots']['noarchive'] );
	}

	/**
	 * Test term writes use the term payload key.
	 */
	public function test_term_writes_use_term_payload_key(): void {
		Functions\when( 'get_term_meta' )->justReturn( [] );

		$capturedKey = null;
		Functions\when( 'update_term_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$capturedKey ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_term_meta signature.
				$capturedKey = $key;

				return true;
			}
		);

		PayloadImporter::applyTermRows(
			[
				[
					'term_id' => 7,
					'fields'  => [ 'title' => 'Imported' ],
				],
			],
			'skip'
		);

		$this->assertSame( '_rankkernel_term_data', $capturedKey );
	}

	/**
	 * Test an identical incoming value counts as skipped without writing.
	 */
	public function test_identical_value_counts_as_skipped(): void {
		Functions\when( 'get_term_meta' )->alias(
			static function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_meta signature.
				return [ 'title' => 'Mine' ];
			}
		);
		Functions\expect( 'update_term_meta' )->never();

		$result = PayloadImporter::applyTermRows(
			[
				[
					'term_id' => 7,
					'fields'  => [ 'title' => 'Mine' ],
				],
			],
			'skip'
		);

		$this->assertSame(
			[
				'applied' => 0,
				'skipped' => 1,
				'failed'  => 0,
			],
			$result
		);
	}
}
