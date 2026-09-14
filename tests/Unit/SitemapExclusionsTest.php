<?php
/**
 * Sitemap exclusion tests, noindex payloads and passwords.
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
use RankKernel\Tests\Unit\Support\SitemapExclFakeWpdb;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Sitemap Exclusions Test.
 */
final class SitemapExclusionsTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Db.
	 *
	 * @var SitemapExclFakeWpdb
	 */
	private SitemapExclFakeWpdb $db;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->db        = new SitemapExclFakeWpdb();
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test setup swaps the WordPress global, restored in tearDown.

		Functions\when( 'get_post_types' )->alias(
			static fn ( array $a = [], string $o = '' ): array => [ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_types signature.
				'post' => 'post',
				'page' => 'page',
			]
		);
		Functions\when( 'trailingslashit' )->alias( static fn ( string $s ): string => rtrim( $s, '/' ) . '/' );
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'mysql2date' )->alias( static fn ( string $f, string $d, bool $t = true ): string => gmdate( $f, strtotime( $d ) ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress mysql2date signature.
		Functions\when( 'get_permalink' )->alias( static fn ( int $id ): string => 'https://example.com/?p=' . $id );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );
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
	 * Seed Posts.
	 */
	private function seedPosts(): void {
		// Real serialize() output, exactly what core writes for object
		// typed meta. Never hand write serialized strings.
		$noindex = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
			[
				'title'       => '',
				'description' => '',
				'canonical'   => '',
				'robots'      => [
					'index'  => false,
					'follow' => true,
				],
			]
		);
		$index   = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
			[
				'title'       => '',
				'description' => '',
				'canonical'   => '',
				'robots'      => [
					'index'  => true,
					'follow' => true,
				],
			]
		);

		$this->db->postsRows    = [
			[
				'ID'                => 1,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_modified_gmt' => '2026-01-03 00:00:00',
			],
			[
				'ID'                => 2,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_modified_gmt' => '2026-01-02 00:00:00',
			],
			[
				'ID'                => 3,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => '',
				'post_modified_gmt' => '2026-01-01 00:00:00',
			],
			[
				'ID'                => 4,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_password'     => 'secret',
				'post_modified_gmt' => '2026-01-04 00:00:00',
			],
		];
		$this->db->postmetaRows = [
			1 => $noindex,
			2 => $index,
		];
	}

	/**
	 * Test noindex post excluded from entries and counts.
	 */
	public function test_noindex_post_excluded_from_entries_and_counts(): void {
		$this->seedPosts();

		$provider = new PostsProvider();

		$this->assertSame( 2, $provider->getCount( 'post' ) );

		$entries = $provider->getEntries( 'post', 1, 10 );
		$locs    = array_column( $entries, 'loc' );

		$this->assertNotContains( 'https://example.com/?p=1', $locs );
		$this->assertContains( 'https://example.com/?p=2', $locs );
		$this->assertContains( 'https://example.com/?p=3', $locs );

		$found = false;

		foreach ( $this->db->queries as $sql ) {
			// Prepare escapes quotes (like the real wpdb), unescape first.
			if ( str_contains( stripcslashes( $sql ), 's:5:"index";b:0' ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Entries query must carry the noindex LIKE fragment' );
	}

	/**
	 * Test password post excluded from entries and counts.
	 */
	public function test_password_post_excluded_from_entries_and_counts(): void {
		$this->seedPosts();

		$provider = new PostsProvider();

		$entries = $provider->getEntries( 'post', 1, 10 );
		$locs    = array_column( $entries, 'loc' );

		$this->assertNotContains( 'https://example.com/?p=4', $locs );
		$this->assertSame( 2, $provider->getCount( 'post' ) );
		$this->assertStringContainsString( "post_password = ''", $this->db->lastSql );
	}

	/**
	 * Test noindex term excluded from entries and counts.
	 */
	public function test_noindex_term_excluded_from_entries_and_counts(): void {
		$this->seedPosts();

		$noindex = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
			[
				'robots' => [
					'index'  => false,
					'follow' => true,
				],
			]
		);

		$this->db->termsList     = [ 10, 11, 12 ];
		$this->db->termTaxonomy  = [
			10 => 'category',
			11 => 'category',
			12 => 'category',
		];
		$this->db->relationships = [
			10 => [ 2 ],
			11 => [ 3 ],
			12 => [ 2 ],
		];
		$this->db->termmetaRows  = [ 10 => $noindex ];

		Functions\when( 'get_term' )->alias( static fn ( int $id, string $t = '' ): object => (object) [ 'term_id' => $id ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term signature.
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $v ): bool => $v instanceof \WP_Error );
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_term_link signature.
		Functions\when( 'get_term_link' )->alias( static fn ( mixed $t, string $tax = '' ): string => 'https://example.com/cat/' );

		$provider = new TaxonomiesProvider();

		$this->assertSame( 2, $provider->getCount( 'category' ) );

		$entries = $provider->getEntries( 'category', 1, 10 );

		$this->assertCount( 2, $entries );
	}
}
