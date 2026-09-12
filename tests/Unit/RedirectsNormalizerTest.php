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

final class RedirectsNormalizerTest extends TestCase {
	/**
	 * Home URL served by the stub, mutable per test.
	 */
	private string $homeUrl = 'https://example.com/';

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

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

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_leading_slash_added(): void {
		$this->assertSame( '/old', Normalizer::normalize( 'old' ) );
	}

	public function test_duplicate_slashes_collapsed(): void {
		$this->assertSame( '/old/page', Normalizer::normalize( '//old///page' ) );
	}

	public function test_fragment_stripped(): void {
		$this->assertSame( '/old', Normalizer::normalize( '/old#section' ) );
	}

	public function test_query_stripped_path_only(): void {
		$this->assertSame( '/old', Normalizer::normalize( '/old?utm=1' ) );
	}

	public function test_trailing_slash_trimmed_except_root(): void {
		$this->assertSame( '/old', Normalizer::normalize( '/old/' ) );
		$this->assertSame( '/', Normalizer::normalize( '/' ) );
	}

	public function test_empty_becomes_root(): void {
		$this->assertSame( '/', Normalizer::normalize( '' ) );
		$this->assertSame( '/', Normalizer::normalize( '   ' ) );
	}

	public function test_full_url_path_extracted(): void {
		$this->assertSame( '/old', Normalizer::normalize( 'https://example.com/old?x=1#y' ) );
	}

	public function test_case_sensitive_by_default(): void {
		$this->assertSame( '/Old', Normalizer::normalize( '/Old' ) );
		$this->assertSame( '/old', Normalizer::normalize( '/Old', true ) );
	}

	public function test_percent_encoding_canonicalized(): void {
		$this->assertSame( '/A', Normalizer::normalize( '/%41' ) );
		$this->assertSame( '/~user', Normalizer::normalize( '/%7euser' ) );
		$this->assertSame( '/a%2Fb', Normalizer::normalize( '/a%2fb' ) );
	}

	public function test_unicode_nfc_folded(): void {
		if ( ! class_exists( \Normalizer::class ) ) {
			$this->markTestSkipped( 'intl normalizer unavailable' );
		}

		$decomposed = "/e\xCC\x81";
		$composed   = "/\xC3\xA9";

		$this->assertSame( $composed, Normalizer::normalize( $decomposed ) );
	}

	public function test_subdirectory_home_stripped(): void {
		$this->homeUrl = 'https://example.com/blog';

		$this->assertSame( '/old', Normalizer::normalize( '/blog/old' ) );
		$this->assertSame( '/old', Normalizer::normalize( 'https://example.com/blog/old/' ) );
		$this->assertSame( '/other', Normalizer::normalize( '/other' ) );
	}

	public function test_homepage_blocked_as_source(): void {
		$this->assertTrue( Normalizer::isBlockedSource( Normalizer::normalize( '/' ) ) );
		$this->assertTrue( Normalizer::isBlockedSource( Normalizer::normalize( 'https://example.com' ) ) );
		$this->assertTrue( Normalizer::isBlockedSource( Normalizer::normalize( '' ) ) );
		$this->assertFalse( Normalizer::isBlockedSource( Normalizer::normalize( '/old' ) ) );
	}

	public function test_hash_is_sha256_over_type_plus_path(): void {
		$this->assertSame( hash( 'sha256', 'exact|/old' ), Normalizer::hash( 'exact', '/old' ) );
		$this->assertSame( 64, strlen( Normalizer::hash( 'exact', '/old' ) ) );
	}

	public function test_hash_folds_match_type(): void {
		$this->assertNotSame( Normalizer::hash( 'exact', '/old' ), Normalizer::hash( 'prefix', '/old' ) );
	}

	public function test_hash_casefolds_only_when_insensitive(): void {
		$this->assertNotSame( Normalizer::hash( 'exact', '/Old' ), Normalizer::hash( 'exact', '/old' ) );
		$this->assertSame( Normalizer::hash( 'exact', '/old', true ), Normalizer::hash( 'exact', '/Old', true ) );
	}

	public function test_match_type_and_code_helpers(): void {
		$this->assertTrue( Normalizer::isMatchType( 'regex' ) );
		$this->assertFalse( Normalizer::isMatchType( 'fuzzy' ) );
		$this->assertTrue( Normalizer::isCode( '451' ) );
		$this->assertFalse( Normalizer::isCode( '308' ) );
	}
}
