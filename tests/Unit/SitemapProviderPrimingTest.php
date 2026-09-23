<?php
/**
 * Sitemap provider cache priming tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\Provider\PostsProvider;
use RankKernel\Modules\Sitemaps\Provider\TaxonomiesProvider;
use RankKernel\Tests\Unit\Support\SitemapCanonFakeWpdb;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Pins the cache priming contract of both entry providers.
 */
final class SitemapProviderPrimingTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Db.
	 *
	 * @var SitemapCanonFakeWpdb
	 */
	private SitemapCanonFakeWpdb $db;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->db        = new SitemapCanonFakeWpdb();
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test setup swaps the WordPress global, restored in tearDown.

		Functions\when( 'get_post_types' )->alias( static fn ( array $a = [], string $o = '' ): array => [ 'post' => 'post' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_types signature.
		Functions\when( 'trailingslashit' )->alias( static fn ( string $s ): string => rtrim( $s, '/' ) . '/' );
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'mysql2date' )->alias( static fn ( string $f, string $d, bool $t = true ): string => gmdate( $f, strtotime( $d ) ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress mysql2date signature.
		Functions\when( 'get_permalink' )->alias( static fn ( int $id ): string => 'https://example.com/hello/' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_permalink signature.
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $v ): bool => $v instanceof \WP_Error );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that post rows prime the post, term and meta caches in one batch.
	 *
	 * The entry loop reads the category relationship cache on %category%
	 * permalink structures and the _thumbnail_id meta for featured images,
	 * so both priming flags must stay on or those reads go back to being
	 * one query per entry.
	 */
	public function test_posts_provider_primes_post_caches_with_term_and_meta_caches(): void {
		$this->db->postRows = [
			[
				'ID'                => 1,
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
			[
				'ID'                => 2,
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
			[
				'ID'                => 3,
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
		];

		Functions\expect( '_prime_post_caches' )->once()->with( [ 1, 2, 3 ], true, true );

		$entries = ( new PostsProvider() )->getEntries( 'post', 1, 10 );

		$this->assertCount( 3, $entries );
	}

	/**
	 * Test that term rows prime term objects and leave term meta alone.
	 *
	 * Nothing on the taxonomy entry path reads term meta, so its priming
	 * flag stays off while the term objects arrive in a single batch.
	 */
	public function test_taxonomies_provider_primes_term_caches_without_term_meta(): void {
		$this->db->termRows = [
			[
				'term_id'     => 10,
				'lastmod_gmt' => '2026-01-01 00:00:00',
			],
			[
				'term_id'     => 11,
				'lastmod_gmt' => '2026-01-01 00:00:00',
			],
		];

		Functions\when( 'get_term' )->alias( static fn ( int $id, string $t = '' ): object => (object) [ 'term_id' => $id ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term signature.
		Functions\when( 'get_term_link' )->alias(
			static function ( mixed $t, string $tax = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_link signature.
				$id = is_object( $t ) && isset( $t->term_id ) ? (int) $t->term_id : (int) $t;

				return 'https://example.com/cat-' . $id . '/';
			}
		);

		Functions\expect( '_prime_term_caches' )->once()->with( [ 10, 11 ], false );

		$entries = ( new TaxonomiesProvider() )->getEntries( 'category', 1, 10 );

		$this->assertCount( 2, $entries );
	}
}
