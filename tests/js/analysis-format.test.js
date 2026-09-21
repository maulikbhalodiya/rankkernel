'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const F = require( '../../assets/js/analysis/analysis-format.js' );

test( 'numberFormat matches PHP number_format in the C locale', () => {
	assert.equal( F.numberFormat( 25, 2 ), '25.00' );
	assert.equal( F.numberFormat( 12.5, 0 ), '13' );
	assert.equal( F.numberFormat( 0.5, 0 ), '1' );
	assert.equal( F.numberFormat( 2.5, 0 ), '3' );
	assert.equal( F.numberFormat( 25.0, 2 ), '25.00' );
} );

test( 'format substitutes sequential and positional placeholders', () => {
	assert.equal( F.format( 'a %s b %d', [ 'x', 4 ] ), 'a x b 4' );
	assert.equal( F.format( 'appears %1$d time(s), density %2$s', [ 2, '25.00' ] ), 'appears 2 time(s), density 25.00' );
} );

test( 'format routes the template through the translator before substitution', () => {
	const translate = ( text ) => text.replace( 'Your keyword', 'Votre mot cle' );
	assert.equal( F.format( 'Your keyword "%s" appears.', [ 'x' ], translate ), 'Votre mot cle "x" appears.' );
} );

test( 'the message table carries the exact spec strings', () => {
	assert.equal( F.MESSAGES.keyword_in_title_pass, 'Your keyword "%s" appears in the SEO title.' );
	assert.equal( F.MESSAGES.density_count, 'Your keyword appears %1$d time(s), a density of %2$s percent.' );
	assert.equal( F.MESSAGES.content_length, 'The content is %d words long. Google states there is no ideal word count, so treat this as a completeness signal rather than a length requirement.' );
	assert.equal( F.MESSAGES.default_na, 'Not applicable yet.' );
} );
