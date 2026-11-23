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
use RankKernel\Modules\Sitemaps\Provider\AuthorsProvider;
use RankKernel\Modules\Sitemaps\Provider\PostsProvider;
use RankKernel\Modules\Sitemaps\Provider\TaxonomiesProvider;
use RankKernel\Modules\Sitemaps\SitemapSettings;
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

	/**
	 * Test that author rows prime user and user meta caches in one batch.
	 *
	 * Resolving author URLs fetches user info and permissions, which would
	 * otherwise execute separate queries per author. Priming via cache_users()
	 * loads user records in a single batch query.
	 */
	public function test_authors_provider_primes_user_caches(): void {
		$this->db->postRows = [
			[
				'post_author' => 21,
				'lastmod_gmt' => '2026-01-01 00:00:00',
			],
			[
				'post_author' => 42,
				'lastmod_gmt' => '2026-01-01 00:00:00',
			],
		];

		Functions\when( 'get_author_posts_url' )->alias( static fn ( int $id ): string => 'https://example.com/author/' . $id . '/' );

		Functions\expect( 'cache_users' )->once()->with( [ 21, 42 ] );

		$entries = ( new AuthorsProvider() )->getEntries( 'authors', 1, 10 );

		$this->assertCount( 2, $entries );
	}

	/**
	 * Record the _prime_post_caches calls for an entry build.
	 *
	 * @param array<int, array{0: int[], 1: bool, 2: bool}> $calls By-reference collector for the recorded calls.
	 * @return void
	 */
	private function recordPrimeCalls( array &$calls ): void {
		Functions\when( '_prime_post_caches' )->alias(
			static function ( array $ids, bool $terms = true, bool $meta = true ) use ( &$calls ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress _prime_post_caches signature.
				$calls[] = [ $ids, $terms, $meta ];
			}
		);
	}

	/**
	 * Featured and gallery attachment ids are primed once and deduplicated.
	 */
	public function test_attachment_ids_are_primed_once_deduplicated(): void {
		$this->db->postRows = [
			[
				'ID'                => 1,
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_content'      => '[gallery ids="21,22,11"]',
			],
			[
				'ID'                => 2,
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_content'      => '[gallery ids="22,23,0,-5"]',
			],
		];

		Functions\when( 'get_post_thumbnail_id' )->alias( static fn ( int $id ): int => 1 === $id ? 11 : 12 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_thumbnail_id signature.
		Functions\when( 'wp_get_attachment_image_url' )->alias( static fn ( int $id, string $size = 'full' ): string => 'https://example.com/a' . $id . '.jpg' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress signature.
		Functions\when( 'wp_get_attachment_url' )->alias( static fn ( int $id ): string => 'https://example.com/a' . $id . '.jpg' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress signature.

		$calls = [];
		$this->recordPrimeCalls( $calls );

		$entries = ( new PostsProvider() )->getEntries( 'post', 1, 10 );

		$this->assertCount( 2, $entries );
		$this->assertSame(
			[
				[ [ 1, 2 ], true, true ],
				[ [ 11, 12, 21, 22, 23 ], false, true ],
			],
			$calls
		);
	}

	/**
	 * Disabled image settings produce no attachment-specific priming.
	 */
	public function test_no_attachment_priming_when_images_disabled(): void {
		$this->db->postRows = [
			[
				'ID'                => 1,
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_content'      => '[gallery ids="21"]',
			],
		];

		Functions\when( 'get_post_thumbnail_id' )->alias( static fn ( int $id ): int => 11 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress signature.
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );
		Functions\when( 'wp_get_attachment_url' )->justReturn( '' );

		$calls = [];
		$this->recordPrimeCalls( $calls );

		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'update_option' )->justReturn( true );

		$settings = new SitemapSettings();
		$settings->set( [ 'include_images' => false ] );

		( new PostsProvider( $settings ) )->getEntries( 'post', 1, 10 );

		$this->assertSame( [ [ [ 1 ], true, true ] ], $calls );
	}

	/**
	 * Posts with no attachments add no second priming call.
	 */
	public function test_posts_without_attachments_cause_no_extra_priming(): void {
		$this->db->postRows = [
			[
				'ID'                => 1,
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_content'      => '<p>hello</p>',
			],
		];

		$calls = [];
		$this->recordPrimeCalls( $calls );

		( new PostsProvider() )->getEntries( 'post', 1, 10 );

		$this->assertSame( [ [ [ 1 ], true, true ] ], $calls );
	}

	/**
	 * Disabling featured images keeps gallery attachment priming.
	 */
	public function test_disabled_featured_image_primes_gallery_ids_only(): void {
		$this->db->postRows = [
			[
				'ID'                => 1,
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_content'      => '[gallery ids="21"]',
			],
		];

		Functions\when( 'get_post_thumbnail_id' )->alias( static fn ( int $id ): int => 11 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress signature.
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );
		Functions\when( 'wp_get_attachment_url' )->alias( static fn ( int $id ): string => 'https://example.com/a' . $id . '.jpg' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress signature.

		$calls = [];
		$this->recordPrimeCalls( $calls );

		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'update_option' )->justReturn( true );

		$settings = new SitemapSettings();
		$settings->set( [ 'include_featured_image' => false ] );

		$entries = ( new PostsProvider( $settings ) )->getEntries( 'post', 1, 10 );

		$this->assertSame(
			[
				[ [ 1 ], true, true ],
				[ [ 21 ], false, true ],
			],
			$calls
		);
		$this->assertSame( [ 'https://example.com/a21.jpg' ], $entries[0]['images'] );
	}

	/**
	 * Canonical-excluded posts do not contribute attachment ids.
	 */
	public function test_canonical_excluded_posts_do_not_contribute_attachment_ids(): void {
		$this->db->postRows     = [
			[
				'ID'                => 1,
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_content'      => '[gallery ids="91"]',
			],
			[
				'ID'                => 2,
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_content'      => '[gallery ids="23"]',
			],
		];
		$this->db->postmetaRows = [
			1 => serialize( [ 'canonical' => 'https://example.com/elsewhere/' ] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture mirrors the stored serialized meta payload.
		];

		Functions\when( 'get_post_thumbnail_id' )->alias( static fn ( int $id ): int => 1 === $id ? 91 : 12 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress signature.
		Functions\when( 'wp_get_attachment_image_url' )->alias( static fn ( int $id, string $size = 'full' ): string => 'https://example.com/a' . $id . '.jpg' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress signature.
		Functions\when( 'wp_get_attachment_url' )->alias( static fn ( int $id ): string => 'https://example.com/a' . $id . '.jpg' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress signature.

		$calls = [];
		$this->recordPrimeCalls( $calls );

		$entries = ( new PostsProvider() )->getEntries( 'post', 1, 10 );

		$this->assertCount( 1, $entries );
		$this->assertSame(
			[
				[ [ 1, 2 ], true, true ],
				[ [ 12, 23 ], false, true ],
			],
			$calls
		);
	}
}
