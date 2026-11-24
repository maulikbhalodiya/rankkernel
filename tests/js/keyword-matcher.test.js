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

test( 'position returns the first boundary-valid occurrence', () => {
	assert.equal( Matcher.position( 'the cat guide', 'cat' ), 4 );
	assert.equal( Matcher.position( 'red apples guide', 'red apples' ), 0 );
	assert.equal( Matcher.position( 'guide to the cat', 'cat' ), 13 );
	assert.equal( Matcher.position( 'concatenated results with cat', 'cat' ), 26 );
	assert.equal( Matcher.position( 'cat guide and cat tips', 'cat' ), 0 );
	assert.equal( Matcher.position( 'red apples and red apples', 'red apples' ), 0 );
} );

test( 'position rejects fragments and plural fragments', () => {
	assert.equal( Matcher.position( 'concatenated results', 'cat' ), false );
	assert.equal( Matcher.position( 'cats guide', 'cat' ), false );
	assert.equal( Matcher.position( 'red apples guide', 'red apple' ), false );
	assert.equal( Matcher.position( '猫concatenated', 'cat' ), false );
} );

test( 'position skips an invalid first candidate and finds a later match', () => {
	assert.equal( Matcher.position( 'concatenated cat guide', 'cat' ), 13 );
	assert.equal( Matcher.position( 'catxxx cat yyyyyyyyyy', 'cat' ), 7 );
} );

test( 'position handles punctuation, mixed scripts and astral character offsets', () => {
	assert.equal( Matcher.position( 'red, apples guide', 'red apples' ), 0 );
	assert.equal( Matcher.position( '猫 cat 狗', 'cat' ), 2 );
	assert.equal( Matcher.position( '\u{20000} cat', 'cat' ), 2 );
} );

test( 'position preserves unspaced script matches', () => {
	assert.equal( Matcher.position( '咖啡指南', '咖啡' ), 0 );
	assert.equal( Matcher.position( '我喝咖啡', '咖啡' ), 2 );
	assert.equal( Matcher.position( '咖啡店', '咖啡' ), 0 );
	assert.equal( Matcher.position( '서울은', '서울' ), 0 );
	assert.equal( Matcher.position( 'ภาษาไทย', 'ภาษา' ), 0 );
} );

test( 'position returns false for empty input', () => {
	assert.equal( Matcher.position( '', 'cat' ), false );
	assert.equal( Matcher.position( 'cat', '' ), false );
	assert.equal( Matcher.position( 'cat', '   ' ), false );
} );

test( 'occurrences counts non overlapping exact phrases only', () => {
	assert.equal( Matcher.occurrences( 'red apples and more red apples', 'red apples' ), 2 );
	assert.equal( Matcher.occurrences( 'red apples and more red apples', 'apples red' ), 0 );
	assert.equal( Matcher.occurrences( 'aaaa', 'aa' ), 0 );
} );

test( 'density divides occurrences by normalized words', () => {
	assert.equal( Matcher.density( 'red apples and green pears and red apples', 'red apples' ), 25 );
	assert.equal( Matcher.density( '   ', 'red apples' ), 0 );
} );

test( 'containsAny scopes matching to one segment at a time and occurrencesAny sums counts', () => {
	assert.equal( Matcher.containsAny( [ 'We sell red apples here.', 'Other words.' ], 'red apples' ), true );
	assert.equal( Matcher.containsAny( [ 'The red fruit.', 'Apples are sweet.' ], 'red apples' ), false );
	assert.equal( Matcher.containsAny( [], 'red apples' ), false );
	assert.equal( Matcher.containsAny( [ 'red apples here' ], '' ), false );
	assert.equal( Matcher.containsAny( [ 'We picked red, apples today.' ], 'red apples' ), true );
	assert.equal( Matcher.containsAny( [ 'A well-known brand today.' ], 'well known' ), true );
	assert.equal( Matcher.occurrencesAny( [ 'red apples and more red apples', 'one red apples here' ], 'red apples' ), 3 );
	assert.equal( Matcher.occurrencesAny( [ 'The red', 'apples are sweet' ], 'red apples' ), 0 );
	assert.equal( Matcher.occurrencesAny( [], 'red apples' ), 0 );
} );
