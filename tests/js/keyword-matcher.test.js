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
