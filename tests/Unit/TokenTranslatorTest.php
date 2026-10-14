<?php
/**
 * Token translator tests, Yoast and Rank Math to our token set.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\TokenTranslator;

/**
 * Token Translator Test.
 */
final class TokenTranslatorTest extends TestCase {
	/**
	 * Test our nine known Yoast tokens pass through untouched.
	 */
	public function test_known_yoast_tokens_pass_through(): void {
		[ $value, $unknown ] = TokenTranslator::translateYoast( '%%title%% %%sep%% %%sitename%%' );

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $value );
		$this->assertSame( [], $unknown );
	}

	/**
	 * Test unknown Yoast tokens stay verbatim and are reported.
	 */
	public function test_unknown_yoast_tokens_are_reported(): void {
		[ $value, $unknown ] = TokenTranslator::translateYoast( '%%title%% %%cf_color%%' );

		$this->assertSame( '%%title%% %%cf_color%%', $value );
		$this->assertSame( [ '%%cf_color%%' ], $unknown );
	}

	/**
	 * Test plain text without tokens is untouched.
	 */
	public function test_plain_text_is_untouched(): void {
		[ $value, $unknown ] = TokenTranslator::translateYoast( 'Just a title, 100% plain' );

		$this->assertSame( 'Just a title, 100% plain', $value );
		$this->assertSame( [], $unknown );
	}

	/**
	 * Test Rank Math single percent tokens normalize to double percent.
	 */
	public function test_rank_math_tokens_normalize(): void {
		[ $value, $unknown ] = TokenTranslator::translateRankMath( '%title% %sep% %sitename%' );

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $value );
		$this->assertSame( [], $unknown );
	}

	/**
	 * Test unknown Rank Math tokens are reported in normalized form.
	 */
	public function test_unknown_rank_math_tokens_are_reported(): void {
		[ $value, $unknown ] = TokenTranslator::translateRankMath( '%title% %customfield(foo)%' );

		$this->assertSame( '%%title%% %%customfield(foo)%%', $value );
		$this->assertSame( [ '%%customfield(foo)%%' ], $unknown );
	}

	/**
	 * Test a lone percent sign is not treated as a token.
	 */
	public function test_lone_percent_is_untouched(): void {
		[ $value, $unknown ] = TokenTranslator::translateRankMath( 'Save 50% today' );

		$this->assertSame( 'Save 50% today', $value );
		$this->assertSame( [], $unknown );
	}

	/**
	 * Test malformed, repeated and mixed tokens never interpolate silently.
	 */
	public function test_malformed_repeated_and_mixed_tokens(): void {
		[ $value, $unknown ] = TokenTranslator::translateYoast( 'Shop %%bogus%% twice %%bogus%% and %%title%%' );

		$this->assertSame( 'Shop %%bogus%% twice %%bogus%% and %%title%%', $value );
		$this->assertSame( [ '%%bogus%%' ], $unknown );

		[ $openValue, $openUnknown ] = TokenTranslator::translateYoast( 'Hello %%title' );

		$this->assertSame( 'Hello %%title', $openValue );
		$this->assertSame( [], $openUnknown );

		[ $emptyValue, $emptyUnknown ] = TokenTranslator::translateYoast( '' );

		$this->assertSame( '', $emptyValue );
		$this->assertSame( [], $emptyUnknown );

		[ $rmValue, $rmUnknown ] = TokenTranslator::translateRankMath( '%title% and %bogus% plus 100%' );

		$this->assertSame( '%%title%% and %%bogus%% plus 100%', $rmValue );
		$this->assertSame( [ '%%bogus%%' ], $rmUnknown );
	}

	/**
	 * Test AIOSEO hash tokens translate through the Yoast name map.
	 */
	public function test_aioseo_tokens_translate(): void {
		[ $value, $unknown ] = TokenTranslator::translateAioseo( '#post_title #separator_sa #site_title' );

		$this->assertSame( '%%title%% %%sep%% %%sitename%%', $value );
		$this->assertSame( [], $unknown );
	}

	/**
	 * Test AIOSEO custom field and taxonomy patterns translate.
	 */
	public function test_aioseo_patterns_translate(): void {
		[ $value, $unknown ] = TokenTranslator::translateAioseo( '#custom_field-price #tax_name-brand' );

		$this->assertSame( '%%cf_price%% %%ct_brand%%', $value );
		$this->assertSame( [ '%%cf_price%%', '%%ct_brand%%' ], $unknown );
	}

	/**
	 * Test unknown AIOSEO tokens stay verbatim and are reported.
	 */
	public function test_unknown_aioseo_tokens_are_reported(): void {
		[ $value, $unknown ] = TokenTranslator::translateAioseo( '#post_title #mystery_token' );

		$this->assertSame( '%%title%% #mystery_token', $value );
		$this->assertSame( [ '#mystery_token' ], $unknown );
	}
}
