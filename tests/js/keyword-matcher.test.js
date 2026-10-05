'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Matcher = require( '../../assets/js/analysis/keyword-matcher.js' );

test( 'normalize folds case, strips accents and turns punctuation into spaces', () => {
	assert.equal( Matcher.normalize( 'Fresh, RED   apples!' ), 'fresh red apples' );
	assert.equal( Matcher.normalize( 'well-known' ), 'well known' );
	assert.equal( Matcher.normalize( '\u041f\u0440\u0438\u0432\u0435\u0442, \u043c\u0438\u0440!' ), '\u043f\u0440\u0438\u0432\u0435\u0442 \u043c\u0438\u0440' );
	assert.equal( Matcher.normalize( 'caf\u00e9' ), 'cafe' );
	assert.equal( Matcher.normalize( 'caf\u00e9', false ), 'caf\u00e9' );
} );

test( 'case folding agrees with PHP mb_strtolower on the known divergence characters', () => {
	// Latvian dotted capital I lowercases to i plus a combining dot, and the
	// combining mark is not a letter, so it collapses to a separator.
	assert.equal( Matcher.normalize( '\u0130', false ), 'i' );
	assert.equal( Matcher.normalize( '\u1e9e', false ), '\u00df' );
	// Greek capital sigma lowercases to a final sigma at the end of a word.
	assert.equal( Matcher.normalize( '\u039f\u0394\u039f\u03a3', false ), '\u03bf\u03b4\u03bf\u03c2' );
} );

test( 'contentWords drops function words and falls back to the full list', () => {
	assert.deepEqual( Matcher.contentWords( 'the red apples of' ), [ 'red', 'apples' ] );
	assert.deepEqual( Matcher.contentWords( 'of the' ), [ 'of', 'the' ] );
} );

test( 'contains matches exact phrases, reorders within one sentence and drops function words', () => {
	assert.equal( Matcher.contains( 'We sell red apples here.', 'red apples' ), true );
	assert.equal( Matcher.contains( 'We sell redapples here.', 'red apples' ), false );
	assert.equal( Matcher.contains( 'Your cat may like this food.', 'cat food' ), true );
	assert.equal( Matcher.contains( 'The weather is cold today. I brew coffee at home.', 'cold brew coffee' ), false );
	assert.equal( Matcher.contains( 'They brew coffee cold in summer.', 'cold brew coffee' ), true );
	assert.equal( Matcher.contains( 'Bicycles women ride daily.', 'bicycles for women' ), true );
	assert.equal( Matcher.contains( 'We sell red apples here.', 'red apple' ), false );
	assert.equal( Matcher.contains( 'We sell red apples here.', 'red apples' ), true );
	assert.equal( Matcher.contains( '', 'red apples' ), false );
	assert.equal( Matcher.contains( 'anything', '   ' ), false );
} );

test( 'occurrences counts non overlapping exact phrases only', () => {
	assert.equal( Matcher.occurrences( 'red apples and more red apples', 'red apples' ), 2 );
	assert.equal( Matcher.occurrences( 'red apples and more red apples', 'apples red' ), 0 );
	assert.equal( Matcher.occurrences( 'aaaa', 'aa' ), 0 );
} );

test( 'occurrences counts every repeat, not every other one', () => {
	// The old padded scan stepped past the trailing space the next
	// occurrence also needed, so a repeated keyword came back at about
	// half its real count. That figure feeds the density ceiling.
	assert.equal( Matcher.occurrences( 'seo seo seo', 'seo' ), 3 );
	assert.equal( Matcher.occurrences( 'seo seo seo seo', 'seo' ), 4 );
	assert.equal( Matcher.occurrences( 'SEO for SEO. SEO!', 'seo' ), 3 );
	assert.equal( Matcher.occurrences( 'a  seo   b', 'seo' ), 1 );
} );

test( 'occurrences ignores partial words and empty input', () => {
	// normalize() folds case and drops punctuation to word boundaries, so
	// seo-tools becomes the two words seo tools and seo does match it. A
	// substring of a longer word must not match.
	assert.equal( Matcher.occurrences( 'seo-tools seo', 'seo' ), 2 );
	assert.equal( Matcher.occurrences( 'seo-tools seo', 'seo tools' ), 1 );
	assert.equal( Matcher.occurrences( 'preseo', 'seo' ), 0 );
	assert.equal( Matcher.occurrences( 'seoseo', 'seo' ), 0 );
	assert.equal( Matcher.occurrences( 'cat care basics', 'at' ), 0 );
	assert.equal( Matcher.occurrences( '', 'seo' ), 0 );
	assert.equal( Matcher.occurrences( 'seo', '' ), 0 );
} );

test( 'density divides occurrences by normalized words', () => {
	assert.equal( Matcher.density( 'red apples and green pears and red apples', 'red apples' ), 25 );
	assert.equal( Matcher.density( '   ', 'red apples' ), 0 );
} );

test( 'occurrences diverges from PHP only while the PCRE2 Unicode table lags', () => {
	// Accepted divergence, documented rather than papered over. Both sides
	// fold every run of non letter, non digit characters into a single space
	// and then count whole words in that alphabet, so the two engines have to
	// agree on which codepoints are letters and digits.
	//
	// PHP runs on PCRE2 10.46, whose Unicode tables predate this engine's.
	// PCRE2 does not treat 357 codepoints across 23 ranges, almost all of
	// them Unicode 15.0 and later additions such as Kawi, Nag Mundari,
	// Todhri and Garay, as a letter or a digit. V8 does. So PHP replaces
	// such a character with a space and counts the keyword after it, while
	// this side keeps the character, reads it as part of a word, and returns
	// one less.
	//
	// Hardcoding the ranges here would pin the behaviour to whichever PCRE2
	// build a host happens to run and would invert on a newer one, and
	// narrowing the word definition to ASCII would drop the Cyrillic and
	// Greek support the suite depends on. No real corpus contains these
	// characters, so the effect on keyword density is nil.
	//
	// KeywordMatcherTest::test_occurrences_diverge_from_javascript_only_while_pcre_unicode_lags
	// is the matching tripwire on the PHP side. If PHP is ever moved to a
	// PCRE2 whose tables know these codepoints, that test fails and this
	// side has to be re-measured, not relaxed.
	for ( const codePoint of [ 0x088F, 0xA7CE, 0x2CEA2 ] ) {
		const character = String.fromCodePoint( codePoint );
		const haystack = 'x' + character + 'seo';

		// Asserted as the absence of a separator rather than as an exact
		// string, because normalize() also lowercases, and some of these
		// codepoints are capitals that fold to a different codepoint.
		assert.equal(
			Matcher.normalize( haystack, false ).includes( ' ' ),
			false,
			'U+' + codePoint.toString( 16 ).toUpperCase() + ' is a word character here, so it is not turned into a separator'
		);
		assert.equal(
			Matcher.occurrences( haystack, 'seo', false ),
			0,
			'U+' + codePoint.toString( 16 ).toUpperCase() + ' is not a word boundary here, so PHP returning 1 is the divergent side'
		);
	}
} );
