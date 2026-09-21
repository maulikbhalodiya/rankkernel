'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const TextStats = require( '../../assets/js/analysis/text-stats.js' );

test( 'decodeEntities decodes named and numeric entities', () => {
	assert.equal( TextStats.decodeEntities( '&amp;&copy;' ), '&\u00a9' );
	assert.equal( TextStats.decodeEntities( '&#x1F600;' ), '\u{1F600}' );
	assert.equal( TextStats.decodeEntities( '&#8212;' ), '\u2014' );
	assert.equal( TextStats.decodeEntities( '&nbsp;' ), '\u00a0' );
	assert.equal( TextStats.decodeEntities( '&unknown;' ), '&unknown;' );
} );

test( 'plainText drops script and style, turns breaks and blocks into newlines and decodes entities', () => {
	const html = '<style>p{x:1}</style><script>var a=1;</script><p>One &amp; two</p><br>Three';
	assert.equal( TextStats.plainText( html ), 'One & two\nThree' );
} );

test( 'phpTrim trims only the PHP trim character set and keeps a non breaking space', () => {
	assert.equal( TextStats.phpTrim( '\u00a0 x \u00a0' ), '\u00a0 x \u00a0' );
	assert.equal( TextStats.phpTrim( '\u0000\u000B x \t' ), 'x' );
} );
