<?php
/**
 * Noindex flag sync tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\MetadataModule;
use RankKernel\Modules\Metadata\NoindexFlag;

/**
 * Covers the scalar noindex flag that lets sitemap queries skip the
 * serialized LIKE scan, plus the meta hook sync that keeps it true.
 */
final class NoindexFlagTest extends TestCase {
	/**
	 * Written post meta, keyed by object id then meta key.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $written = [];

	/**
	 * Deleted post meta keys by object id.
	 *
	 * @var array<int, string[]>
	 */
	private array $deleted = [];

	/**
	 * Written term meta, keyed by object id then meta key.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $writtenTerms = [];

	/**
	 * Deleted term meta keys by object id.
	 *
	 * @var array<int, string[]>
	 */
	private array $deletedTerms = [];

	/**
	 * Set up doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->written      = [];
		$this->deleted      = [];
		$this->writtenTerms = [];
		$this->deletedTerms = [];

		Functions\when( 'update_post_meta' )->alias(
			function ( int $id, string $key, mixed $value ): bool {
				$this->written[ $id ][ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			function ( int $id, string $key ): bool {
				$this->deleted[ $id ][] = $key;

				return true;
			}
		);
		Functions\when( 'update_term_meta' )->alias(
			function ( int $id, string $key, mixed $value ): bool {
				$this->writtenTerms[ $id ][ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_term_meta' )->alias(
			function ( int $id, string $key ): bool {
				$this->deletedTerms[ $id ][] = $key;

				return true;
			}
		);
	}

	/**
	 * Reset globals.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test a false robots index flag reads as noindex.
	 */
	public function test_false_robots_index_is_noindex(): void {
		$this->assertTrue( NoindexFlag::isNoindex( [ 'robots' => [ 'index' => false ] ] ) );
		$this->assertFalse( NoindexFlag::isNoindex( [ 'robots' => [ 'index' => true ] ] ) );
		$this->assertFalse( NoindexFlag::isNoindex( [ 'robots' => [] ] ) );
		$this->assertFalse( NoindexFlag::isNoindex( [] ) );
		$this->assertFalse( NoindexFlag::isNoindex( null ) );
	}

	/**
	 * Test a noindex payload write sets the post flag.
	 */
	public function test_noindex_payload_write_sets_post_flag(): void {
		NoindexFlag::onPostMetaWrite( 1, 7, '_rankkernel_meta_data', [ 'robots' => [ 'index' => false ] ] );

		$this->assertSame( '1', $this->written[7]['_rankkernel_noindex'] ?? null );
	}

	/**
	 * Test an indexed payload write clears a stale post flag.
	 */
	public function test_indexed_payload_write_deletes_post_flag(): void {
		NoindexFlag::onPostMetaWrite( 1, 7, '_rankkernel_meta_data', [ 'robots' => [ 'index' => true ] ] );

		$this->assertContains( '_rankkernel_noindex', $this->deleted[7] ?? [] );
		$this->assertArrayNotHasKey( '_rankkernel_noindex', $this->written[7] ?? [] );
	}

	/**
	 * Test unrelated meta keys never touch the flag.
	 */
	public function test_unrelated_meta_key_ignored(): void {
		NoindexFlag::onPostMetaWrite( 1, 7, '_thumbnail_id', '123' );

		$this->assertSame( [], $this->written );
		$this->assertSame( [], $this->deleted );
	}

	/**
	 * Test a payload delete removes the post flag.
	 */
	public function test_payload_delete_removes_post_flag(): void {
		NoindexFlag::onPostMetaDelete( [ 1 ], 7, '_rankkernel_meta_data', [] );

		$this->assertContains( '_rankkernel_noindex', $this->deleted[7] ?? [] );
	}

	/**
	 * Test term payload writes sync the term flag.
	 */
	public function test_term_payload_writes_sync_term_flag(): void {
		NoindexFlag::onTermMetaWrite( 1, 9, '_rankkernel_term_data', [ 'robots' => [ 'index' => false ] ] );

		$this->assertSame( '1', $this->writtenTerms[9]['_rankkernel_noindex'] ?? null );

		NoindexFlag::onTermMetaWrite( 2, 9, '_rankkernel_term_data', [ 'robots' => [ 'index' => true ] ] );

		$this->assertContains( '_rankkernel_noindex', $this->deletedTerms[9] ?? [] );
	}

	/**
	 * Test the exclusion fragment carries both the fast path and the fallback.
	 */
	public function test_exclusion_fragment_has_fast_path_and_fallback(): void {
		$sql = NoindexFlag::exclusionSql( 'wp_postmeta', 'post_id', 'p.ID', '_rankkernel_meta_data' );

		$this->assertStringContainsString( '_rankkernel_noindex', $sql );
		$this->assertSame( 2, substr_count( $sql, '_rankkernel_noindex' ), 'fast path plus fallback guard' );
		$this->assertSame( 1, substr_count( $sql, 'm.meta_value LIKE %s' ), 'single LIKE placeholder preserved' );
	}

	/**
	 * Test boot registers the payload sync hooks.
	 */
	public function test_boot_registers_sync_hooks(): void {
		$hooked = [];

		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10, int $args = 1 ) use ( &$hooked ): bool {
				$hooked[] = [ $hook, $callback, $priority, $args ];

				return true;
			}
		);
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_action' )->justReturn( true );

		( new MetadataModule() )->boot();

		foreach ( [
			[ 'added_post_meta', [ NoindexFlag::class, 'onPostMetaWrite' ] ],
			[ 'updated_post_meta', [ NoindexFlag::class, 'onPostMetaWrite' ] ],
			[ 'deleted_post_meta', [ NoindexFlag::class, 'onPostMetaDelete' ] ],
			[ 'added_term_meta', [ NoindexFlag::class, 'onTermMetaWrite' ] ],
			[ 'updated_term_meta', [ NoindexFlag::class, 'onTermMetaWrite' ] ],
			[ 'deleted_term_meta', [ NoindexFlag::class, 'onTermMetaDelete' ] ],
		] as [ $hook, $callback ] ) {
			$this->assertContains( [ $hook, $callback, 10, 4 ], $hooked, $hook . ' must sync the flag' );
		}
	}
}
