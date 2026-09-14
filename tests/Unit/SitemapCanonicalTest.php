<?php
/**
 * Sitemap canonical mismatch tests.
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
 * Sitemap Canonical Test.
 */
final class SitemapCanonicalTest extends TestCase {
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
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_permalink signature.
		Functions\when( 'get_permalink' )->alias( static fn ( int $id ): string => 'https://example.com/hello/' );
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
	 * Seed Post Rows.
	 */
	private function seedPostRows(): void {
		$this->db->postRows     = [
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
			[
				'ID'                => 4,
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
		];
		$this->db->postmetaRows = [
			// Real serialize() output, exactly what core writes for
			// object typed meta. Never hand write serialized strings.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
			1 => serialize( [ 'canonical' => 'https://example.com/hello/' ] ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
			2 => serialize( [ 'canonical' => 'https://example.com/elsewhere/' ] ),
			// Older JSON rows still decode (backward compatibility).
			3 => (string) json_encode( [ 'canonical' => '' ] ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
			4 => 'not-json{{{',
		];
	}

	/**
	 * Test post canonical matrix.
	 */
	public function test_post_canonical_matrix(): void {
		$this->seedPostRows();

		$entries = ( new PostsProvider() )->getEntries( 'post', 1, 10 );
		$locs    = array_column( $entries, 'loc' );

		$this->assertContains( 'https://example.com/hello/', $locs );
		$this->assertCount( 3, $entries, 'Equal, empty, and malformed canonicals stay, different ones drop' );
	}

	/**
	 * Test term canonical matrix.
	 */
	public function test_term_canonical_matrix(): void {
		$this->db->termRows     = [
			[
				'term_id'     => 10,
				'lastmod_gmt' => '2026-01-01 00:00:00',
			],
			[
				'term_id'     => 11,
				'lastmod_gmt' => '2026-01-01 00:00:00',
			],
		];
		$this->db->termmetaRows = [
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
			10 => serialize( [ 'canonical' => 'https://example.com/cat/' ] ),
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
			11 => (string) json_encode( [ 'canonical' => 'https://example.com/other/' ] ),
		];

		Functions\when( 'get_term' )->alias( static fn ( int $id, string $t = '' ): object => (object) [ 'term_id' => $id ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term signature.
		Functions\when( 'get_term_link' )->alias(
			static function ( mixed $t, string $tax = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_link signature.
				$id = is_object( $t ) && isset( $t->term_id ) ? (int) $t->term_id : (int) $t;

				return 10 === $id ? 'https://example.com/cat/' : 'https://example.com/cat-other/';
			}
		);

		$entries = ( new TaxonomiesProvider() )->getEntries( 'category', 1, 10 );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'https://example.com/cat/', $entries[0]['loc'] );
	}
}
