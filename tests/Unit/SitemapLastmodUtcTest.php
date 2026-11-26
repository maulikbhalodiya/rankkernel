<?php
/**
 * Sitemap lastmod UTC instant regression tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Sitemaps\IndexBuilder;
use RankKernel\Modules\Sitemaps\SitemapDate;
use RankKernel\Modules\Sitemaps\SitemapSettings;
use RankKernel\Tests\Unit\Support\UtcSitemapFakeWpdb;
use RankKernel\Modules\Sitemaps\Provider\AuthorsProvider;
use RankKernel\Modules\Sitemaps\Provider\PostsProvider;
use RankKernel\Modules\Sitemaps\Provider\TaxonomiesProvider;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'DATE_W3C' ) ) {
	define( 'DATE_W3C', 'Y-m-d\\TH:i:sP' );
}

/**
 * Covers sitemap lastmod instant preservation.
 */
final class SitemapLastmodUtcTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Option store for SitemapSettings.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Site timezone used by the simulated mysql2date().
	 *
	 * @var string
	 */
	private string $siteTimezone = 'UTC';

	/**
	 * Set up WordPress function doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->siteTimezone = 'UTC';
		$this->options      = [];

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => strtolower( preg_replace( '/[^a-z0-9_-]/', '', $v ) ?? '' ) );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'get_permalink' )->alias( static fn ( int $id ): string => 'https://example.com/post/' . $id . '/' );
		Functions\when( 'get_term_link' )->alias(
			static function ( mixed $id ): string {
				$termId = is_object( $id ) && isset( $id->term_id ) ? (int) $id->term_id : (int) $id;

				return 'https://example.com/term/' . $termId . '/';
			}
		);
		Functions\when( 'get_author_posts_url' )->alias( static fn ( int $id ): string => 'https://example.com/author/' . $id . '/' );
		Functions\when( 'get_post_types' )->alias(
			static fn (): array => [
				'post' => 'post',
				'page' => 'page',
			]
		);
		Functions\when( 'get_taxonomies' )->alias( static fn (): array => [ 'category' => 'category' ] );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $v ): bool => $v instanceof \WP_Error );
		Functions\when( 'current_time' )->alias( static fn ( string $type, bool $gmt = false ): string => '2026-01-15 12:00:00' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors wpdb signature.
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- test double mirrors wpdb signature.
		Functions\when( 'trailingslashit' )->alias( static fn ( string $s ): string => rtrim( $s, '/' ) . '/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( mixed $key = '', mixed $value = '', string $url = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- mirrors WordPress signature used by sitemap URLs.
				if ( is_array( $key ) ) {
					foreach ( $key as $k => $v ) {
						$url = $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
					}

					return $url;
				}

				return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
			}
		);
		Functions\when( 'get_term' )->alias( static fn ( int $id, string $tax = '' ): object => (object) [ 'term_id' => $id ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- mirrors WordPress signature.

		$this->stubMysql2date( 'UTC' );
	}

	/**
	 * Tear down Brain Monkey and restore globals.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Simulate WordPress mysql2date() for a configured site timezone.
	 *
	 * @param string $timezone IANA timezone.
	 */
	private function stubMysql2date( string $timezone ): void {
		Functions\when( 'mysql2date' )->alias(
			static function ( string $format, string $date, bool $translate = true ) use ( $timezone ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- mirrors the WordPress signature with translate false in production.
				if ( '' === $date ) {
					return false;
				}

				$datetime = date_create( $date, new \DateTimeZone( $timezone ) );

				return false === $datetime ? false : $datetime->format( $format );
			}
		);
	}

	/**
	 * Build an IndexBuilder with provider max dates.
	 *
	 * @param string $set     Set name.
	 * @param string $owner   posts|tax|authors.
	 * @param string $maxDate GMT MySQL date.
	 * @return IndexBuilder
	 */
	private function builder( string $set, string $owner, string $maxDate ): IndexBuilder {
		$posts = Mockery::mock( PostsProvider::class );
		$tax   = Mockery::mock( TaxonomiesProvider::class );
		$auth  = Mockery::mock( AuthorsProvider::class );

		$sets             = [
			'posts'   => [],
			'tax'     => [],
			'authors' => [],
		];
		$sets[ $owner ][] = $set;

		$posts->shouldReceive( 'getSets' )->andReturn( $sets['posts'] )->byDefault();
		$tax->shouldReceive( 'getSets' )->andReturn( $sets['tax'] )->byDefault();
		$auth->shouldReceive( 'getSets' )->andReturn( $sets['authors'] )->byDefault();
		$posts->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$tax->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$auth->shouldReceive( 'getCount' )->andReturn( 1 )->byDefault();
		$posts->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
		$tax->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
		$auth->shouldReceive( 'getEntries' )->andReturn( [] )->byDefault();
		$posts->shouldReceive( 'getMaxLastmod' )->andReturn( 'posts' === $owner ? $maxDate : '' )->byDefault();
		$tax->shouldReceive( 'getMaxLastmod' )->andReturn( 'tax' === $owner ? $maxDate : '' )->byDefault();
		$auth->shouldReceive( 'getMaxLastmod' )->andReturn( 'authors' === $owner ? $maxDate : '' )->byDefault();

		return new IndexBuilder( $posts, $tax, $auth, '9.9.9-test' );
	}

	/**
	 * Extract and parse all lastmod values from XML.
	 *
	 * @param string $xml XML.
	 * @return int[] Unix timestamps.
	 */
	private function parsedInstants( string $xml ): array {
		preg_match_all( '/<lastmod>([^<]+)<\/lastmod>/', $xml, $matches );
		$out = [];

		foreach ( $matches[1] as $value ) {
			$out[] = ( new \DateTimeImmutable( $value ) )->getTimestamp();
		}

		return $out;
	}

	/**
	 * Site timezones that must preserve the stored UTC instant.
	 *
	 * @return array<string, array{string}>
	 */
	public static function timezone_provider(): array {
		return [
			'UTC'                 => [ 'UTC' ],
			'Asia/Kolkata'        => [ 'Asia/Kolkata' ],
			'America/Los_Angeles' => [ 'America/Los_Angeles' ],
			'Pacific/Kiritimati'  => [ 'Pacific/Kiritimati' ],
		];
	}

	/**
	 * Test index lastmod preserves the UTC instant across site timezones.
	 *
	 * @param string $timezone Site timezone.
	 */
	#[DataProvider( 'timezone_provider' )]
	public function test_index_lastmod_preserves_utc_instant( string $timezone ): void {
		$this->stubMysql2date( $timezone );
		$xml      = $this->builder( 'post', 'posts', '2026-01-15 12:00:00' )->buildIndexXml();
		$expected = ( new \DateTimeImmutable( '2026-01-15 12:00:00 UTC' ) )->getTimestamp();

		$this->assertSame( [ $expected ], $this->parsedInstants( $xml ) );
	}

	/**
	 * Test index taxonomy lastmod preserves the UTC instant.
	 */
	public function test_index_taxonomy_lastmod_preserves_utc_instant(): void {
		$this->stubMysql2date( 'Asia/Kolkata' );
		$xml      = $this->builder( 'category', 'tax', '2026-01-15 12:00:00' )->buildIndexXml();
		$expected = ( new \DateTimeImmutable( '2026-01-15 12:00:00 UTC' ) )->getTimestamp();

		$this->assertSame( [ $expected ], $this->parsedInstants( $xml ) );
	}

	/**
	 * Test index author lastmod preserves the UTC instant.
	 */
	public function test_index_author_lastmod_preserves_utc_instant(): void {
		$this->stubMysql2date( 'America/Los_Angeles' );
		$xml      = $this->builder( 'authors', 'authors', '2026-01-15 12:00:00' )->buildIndexXml();
		$expected = ( new \DateTimeImmutable( '2026-01-15 12:00:00 UTC' ) )->getTimestamp();

		$this->assertSame( [ $expected ], $this->parsedInstants( $xml ) );
	}

	/**
	 * Test DST boundary instants are preserved.
	 */
	public function test_dst_boundaries_preserve_utc_instant(): void {
		$this->stubMysql2date( 'America/Los_Angeles' );

		foreach ( [ '2026-01-15 12:00:00', '2026-07-15 12:00:00' ] as $gmt ) {
			$xml      = $this->builder( 'post', 'posts', $gmt )->buildIndexXml();
			$expected = ( new \DateTimeImmutable( $gmt . ' UTC' ) )->getTimestamp();

			$this->assertSame( [ $expected ], $this->parsedInstants( $xml ) );
		}
	}

	/**
	 * Test midnight-boundary instants are preserved.
	 */
	public function test_midnight_boundary_preserves_utc_instant(): void {
		$this->stubMysql2date( 'Pacific/Kiritimati' );
		$xml      = $this->builder( 'post', 'posts', '2026-01-15 00:30:00' )->buildIndexXml();
		$expected = ( new \DateTimeImmutable( '2026-01-15 00:30:00 UTC' ) )->getTimestamp();

		$this->assertSame( [ $expected ], $this->parsedInstants( $xml ) );
	}

	/**
	 * Test current-time fallback preserves its fixed UTC instant.
	 */
	public function test_current_time_fallback_preserves_utc_instant(): void {
		$this->stubMysql2date( 'Asia/Kolkata' );
		$xml      = $this->builder( 'post', 'posts', '' )->buildIndexXml();
		$expected = ( new \DateTimeImmutable( '2026-01-15 12:00:00 UTC' ) )->getTimestamp();

		$this->assertSame( [ $expected ], $this->parsedInstants( $xml ) );
	}

	/**
	 * Test post entry lastmod uses post_modified_gmt as UTC.
	 */
	public function test_post_entry_lastmod_uses_gmt_instant(): void {
		$this->stubMysql2date( 'America/Los_Angeles' );
		$db              = new UtcSitemapFakeWpdb();
		$db->resultRows  = [
			[
				'ID'                => 7,
				'post_modified_gmt' => '2026-01-15 12:00:00',
				'post_content'      => '',
			],
		];
		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolates a wpdb double.

		$settings = new SitemapSettings();
		$settings->set( [ 'include_images' => false ] );
		$entries  = ( new PostsProvider( $settings ) )->getEntries( 'post', 1, 10 );
		$expected = ( new \DateTimeImmutable( '2026-01-15 12:00:00 UTC' ) )->getTimestamp();

		$this->assertCount( 1, $entries );
		$this->assertSame( $expected, ( new \DateTimeImmutable( $entries[0]['lastmod'] ) )->getTimestamp() );
	}

	/**
	 * Test taxonomy entry lastmod uses theGMT value as UTC.
	 */
	public function test_taxonomy_entry_lastmod_uses_gmt_instant(): void {
		$this->stubMysql2date( 'Asia/Kolkata' );
		$db              = new UtcSitemapFakeWpdb();
		$db->resultRows  = [
			[
				'term_id'     => 5,
				'lastmod_gmt' => '2026-01-15 12:00:00',
			],
		];
		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolates a wpdb double.

		$entries  = ( new TaxonomiesProvider() )->getEntries( 'category', 1, 10 );
		$expected = ( new \DateTimeImmutable( '2026-01-15 12:00:00 UTC' ) )->getTimestamp();

		$this->assertCount( 1, $entries );
		$this->assertSame( $expected, ( new \DateTimeImmutable( $entries[0]['lastmod'] ) )->getTimestamp() );
	}

	/**
	 * Test author newest-post and registration fallback instants are UTC.
	 */
	public function test_author_entry_lastmod_uses_utc_for_post_and_registration(): void {
		$this->stubMysql2date( 'America/Los_Angeles' );
		$db              = new UtcSitemapFakeWpdb();
		$db->resultRows  = [
			[
				'ID'              => 7,
				'post_author'     => 7,
				'lastmod_gmt'     => '2026-01-15 12:00:00',
				'user_registered' => '2020-01-15 12:00:00',
			],
			[
				'ID'              => 8,
				'post_author'     => 8,
				'lastmod_gmt'     => '',
				'user_registered' => '2020-02-15 12:00:00',
			],
		];
		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolates a wpdb double.

		$entries            = ( new AuthorsProvider() )->getEntries( 'authors', 1, 10 );
		$expectedPost       = ( new \DateTimeImmutable( '2026-01-15 12:00:00 UTC' ) )->getTimestamp();
		$expectedRegistered = ( new \DateTimeImmutable( '2020-02-15 12:00:00 UTC' ) )->getTimestamp();

		$this->assertCount( 2, $entries );
		$this->assertSame( $expectedPost, ( new \DateTimeImmutable( $entries[0]['lastmod'] ) )->getTimestamp() );
		$this->assertSame( $expectedRegistered, ( new \DateTimeImmutable( $entries[1]['lastmod'] ) )->getTimestamp() );
	}

	/**
	 * Test invalid or missing GMT dates fail closed.
	 *
	 * @dataProvider invalid_date_provider
	 * @param mixed $value Invalid date value.
	 */
	public function test_invalid_dates_fail_closed( mixed $value ): void {
		$this->assertSame( '', SitemapDate::w3cFromGmt( $value ) );
	}

	/**
	 * Invalid MySQL date values.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_date_provider(): array {
		return [
			'empty'      => [ '' ],
			'zero'       => [ '0000-00-00 00:00:00' ],
			'malformed'  => [ 'not-a-date' ],
			'impossible' => [ '2026-02-30 12:00:00' ],
			'wrong type' => [ 1768478400 ],
		];
	}
}
