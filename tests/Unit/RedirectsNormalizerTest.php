<?php
/**
 * Normalizer tests, every normalization rule plus hashing.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\Normalizer;

/**
 * Redirects Normalizer Test.
 */
final class RedirectsNormalizerTest extends TestCase {
	/**
	 * Home URL served by the stub, mutable per test.
	 *
	 * @var string
	 */
	private string $homeUrl = 'https://example.com/';

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		$this->homeUrl = 'https://example.com/';
		Normalizer::resetCache();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias(
			function ( string $path = '/' ): string {
				return rtrim( $this->homeUrl, '/' ) . '/' . ltrim( $path, '/' );
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		$this->homeUrl = 'https://example.com/';
		Normalizer::resetCache();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test leading slash added.
	 */
	public function test_leading_slash_added(): void {
		$this->assertSame( '/old', Normalizer::normalize( 'old' ) );
	}

	/**
	 * Test duplicate slashes collapsed.
	 */
	public function test_duplicate_slashes_collapsed(): void {
		$this->assertSame( '/old/page', Normalizer::normalize( '//old///page' ) );
	}

	/**
	 * Test fragment stripped.
	 */
	public function test_fragment_stripped(): void {
		$this->assertSame( '/old', Normalizer::normalize( '/old#section' ) );
	}

	/**
	 * Test query stripped path only.
	 */
	public function test_query_stripped_path_only(): void {
		$this->assertSame( '/old', Normalizer::normalize( '/old?utm=1' ) );
	}

	/**
	 * Test trailing slash trimmed except root.
	 */
	public function test_trailing_slash_trimmed_except_root(): void {
		$this->assertSame( '/old', Normalizer::normalize( '/old/' ) );
		$this->assertSame( '/', Normalizer::normalize( '/' ) );
	}

	/**
	 * Test empty becomes root.
	 */
	public function test_empty_becomes_root(): void {
		$this->assertSame( '/', Normalizer::normalize( '' ) );
		$this->assertSame( '/', Normalizer::normalize( '   ' ) );
	}

	/**
	 * Test full url path extracted.
	 */
	public function test_full_url_path_extracted(): void {
		$this->assertSame( '/old', Normalizer::normalize( 'https://example.com/old?x=1#y' ) );
	}

	/**
	 * Test case sensitive by default.
	 */
	public function test_case_sensitive_by_default(): void {
		$this->assertSame( '/Old', Normalizer::normalize( '/Old' ) );
		$this->assertSame( '/old', Normalizer::normalize( '/Old', true ) );
	}

	/**
	 * Test percent encoding canonicalized.
	 */
	public function test_percent_encoding_canonicalized(): void {
		$this->assertSame( '/A', Normalizer::normalize( '/%41' ) );
		$this->assertSame( '/~user', Normalizer::normalize( '/%7euser' ) );
		$this->assertSame( '/a%2Fb', Normalizer::normalize( '/a%2fb' ) );
	}

	/**
	 * Test unicode nfc folded.
	 */
	public function test_unicode_nfc_folded(): void {
		if ( ! class_exists( \Normalizer::class ) ) {
			$this->markTestSkipped( 'intl normalizer unavailable' );
		}

		$decomposed = "/e\xCC\x81";
		$composed   = "/\xC3\xA9";

		$this->assertSame( $composed, Normalizer::normalize( $decomposed ) );
	}

	/**
	 * Test subdirectory home stripped.
	 */
	public function test_subdirectory_home_stripped(): void {
		$this->homeUrl = 'https://example.com/blog';
		Normalizer::resetCache();

		$this->assertSame( '/old', Normalizer::normalize( '/blog/old' ) );
		$this->assertSame( '/old', Normalizer::normalize( 'https://example.com/blog/old/' ) );
		$this->assertSame( '/other', Normalizer::normalize( '/other' ) );
	}

	/**
	 * Test homepage blocked as source.
	 */
	public function test_homepage_blocked_as_source(): void {
		$this->assertTrue( Normalizer::isBlockedSource( Normalizer::normalize( '/' ) ) );
		$this->assertTrue( Normalizer::isBlockedSource( Normalizer::normalize( 'https://example.com' ) ) );
		$this->assertTrue( Normalizer::isBlockedSource( Normalizer::normalize( '' ) ) );
		$this->assertFalse( Normalizer::isBlockedSource( Normalizer::normalize( '/old' ) ) );
	}

	/**
	 * Test hash is sha256 over type plus path.
	 */
	public function test_hash_is_sha256_over_type_plus_path(): void {
		$this->assertSame( hash( 'sha256', 'exact|/old' ), Normalizer::hash( 'exact', '/old' ) );
		$this->assertSame( 64, strlen( Normalizer::hash( 'exact', '/old' ) ) );
	}

	/**
	 * Test hash folds match type.
	 */
	public function test_hash_folds_match_type(): void {
		$this->assertNotSame( Normalizer::hash( 'exact', '/old' ), Normalizer::hash( 'prefix', '/old' ) );
	}

	/**
	 * Test hash casefolds only when insensitive.
	 */
	public function test_hash_casefolds_only_when_insensitive(): void {
		$this->assertNotSame( Normalizer::hash( 'exact', '/Old' ), Normalizer::hash( 'exact', '/old' ) );
		$this->assertSame( Normalizer::hash( 'exact', '/old', true ), Normalizer::hash( 'exact', '/Old', true ) );
	}

	/**
	 * Test match type and code helpers.
	 */
	public function test_match_type_and_code_helpers(): void {
		$this->assertTrue( Normalizer::isMatchType( 'regex' ) );
		$this->assertFalse( Normalizer::isMatchType( 'fuzzy' ) );
		$this->assertTrue( Normalizer::isCode( '451' ) );
		$this->assertFalse( Normalizer::isCode( '308' ) );
	}

	/**
	 * Test homePath memoization across repeated calls and multisite blog switches.
	 */
	public function test_home_path_memoized_and_multisite_aware(): void {
		$callCount = 0;
		Functions\when( 'home_url' )->alias(
			function ( string $path = '/' ) use ( &$callCount ): string {
				++$callCount;
				return rtrim( $this->homeUrl, '/' ) . '/' . ltrim( $path, '/' );
			}
		);

		Normalizer::resetCache();

		$this->homeUrl = 'https://example.com/blog';
		$this->assertSame( '/blog', Normalizer::homePath() );
		$this->assertSame( 1, $callCount );

		// Subsequent call uses static memory without re-evaluating home_url.
		$this->assertSame( '/blog', Normalizer::homePath() );
		$this->assertSame( 1, $callCount );

		// Simulate multisite switch_to_blog.
		Functions\when( 'get_current_blog_id' )->justReturn( 2 );
		$this->homeUrl = 'https://example.com/shop';

		$this->assertSame( '/shop', Normalizer::homePath() );
		$this->assertSame( 2, $callCount );

		// Repeated call on blog 2 is memoized.
		$this->assertSame( '/shop', Normalizer::homePath() );
		$this->assertSame( 2, $callCount );

		// Switch back to blog 1 and verify original entry is retained from memory without new home_url calls.
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		$this->assertSame( '/blog', Normalizer::homePath() );
		$this->assertSame( 2, $callCount );
	}

	/**
	 * Test normalize memoization across repeated calls and resetCache behavior.
	 */
	public function test_normalize_memoized_and_resettable(): void {
		$parseUrlCalls = 0;
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ) use ( &$parseUrlCalls ): mixed {
				++$parseUrlCalls;
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);

		Normalizer::resetCache();

		$rawUrl = 'https://example.com/old/page?x=1';
		$this->assertSame( '/old/page', Normalizer::normalize( $rawUrl ) );
		// First call invokes wp_parse_url once inside homePath() and once inside normalize() = 2 calls.
		$this->assertSame( 2, $parseUrlCalls );

		// Subsequent call uses cache and does not re-invoke wp_parse_url.
		$this->assertSame( '/old/page', Normalizer::normalize( $rawUrl ) );
		$this->assertSame( 2, $parseUrlCalls );

		// Case-insensitive variant keying.
		$rawCase = 'https://example.com/Old/Page';
		$this->assertSame( '/Old/Page', Normalizer::normalize( $rawCase, false ) );
		$this->assertSame( 3, $parseUrlCalls );

		$this->assertSame( '/old/page', Normalizer::normalize( $rawCase, true ) );
		$this->assertSame( 4, $parseUrlCalls );

		// Reset cache clears static memo and forces re-evaluation.
		Normalizer::resetCache();
		$this->assertSame( '/old/page', Normalizer::normalize( $rawUrl ) );
		$this->assertSame( 6, $parseUrlCalls );
	}

	/**
	 * Test normalize memoization across multisite blog switches.
	 */
	public function test_normalize_multisite_home_path_aware(): void {
		Normalizer::resetCache();

		$this->homeUrl = 'https://example.com/blog';
		$raw           = '/blog/old';

		$this->assertSame( '/old', Normalizer::normalize( $raw ) );

		// Simulate multisite switch_to_blog to blog 2 with homeUrl shop.
		Functions\when( 'get_current_blog_id' )->justReturn( 2 );
		$this->homeUrl = 'https://example.com/shop';

		// Under shop, /blog/old is NOT under homeUrl /shop, so it normalizes to /blog/old.
		$this->assertSame( '/blog/old', Normalizer::normalize( $raw ) );

		// Switch back to blog 1.
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		$this->homeUrl = 'https://example.com/blog';
		$this->assertSame( '/old', Normalizer::normalize( $raw ) );
	}

	/**
	 * Test normalize cache size capping under heavy load with eviction.
	 */
	public function test_normalize_cache_size_capped(): void {
		Normalizer::resetCache();

		for ( $i = 0; $i < Normalizer::MAX_CACHE_SIZE + 10; ++$i ) {
			Normalizer::normalize( '/path-' . $i );
		}

		$reflection = new \ReflectionClass( Normalizer::class );
		$property   = $reflection->getProperty( 'normalizeCache' );
		$property->setAccessible( true );

		$cache = $property->getValue();

		// Cache size must strictly stay bounded at or below MAX_CACHE_SIZE.
		$this->assertLessThanOrEqual( Normalizer::MAX_CACHE_SIZE, count( $cache ) );
		$this->assertSame( Normalizer::MAX_CACHE_SIZE, count( $cache ) );

		// Both an evicted path and a newly requested path normalize correctly.
		$this->assertSame( '/path-0', Normalizer::normalize( '/path-0' ) );
		$this->assertSame( '/path-cap-test', Normalizer::normalize( '/path-cap-test' ) );
	}
}
