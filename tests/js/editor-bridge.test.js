'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Bridge = require( '../../assets/js/analysis/editor-bridge.js' );

test( 'buildInput maps editor fields and config onto the engine input', () => {
	const input = Bridge.buildInput(
		{ title: 'T', description: 'D', slug: 's', content: '<p>C</p>', keywords: [ 'red apples', ' ' ] },
		{ homeUrl: 'https://example.com/', featuredAlt: 'a cat' }
	);
	assert.equal( input.html, '<p>C</p>' );
	assert.equal( input.title, 'T' );
	assert.equal( input.description, 'D' );
	assert.equal( input.slug, 's' );
	assert.deepEqual( input.keywords, [ 'red apples', ' ' ] );
	assert.equal( input.site_url, 'https://example.com/' );
	assert.equal( input.featured_alt, 'a cat' );
} );

test( 'buildInput is undefined safe', () => {
	const input = Bridge.buildInput( null, null );
	assert.equal( input.html, '' );
	assert.deepEqual( input.keywords, [] );
	assert.equal( input.site_url, '' );
	assert.equal( input.featured_alt, '' );
} );

test( 'signature changes when any scored input changes and is stable otherwise', () => {
	const a = Bridge.signature( Bridge.buildInput( { title: 'T', content: '<p>C</p>', keywords: [ 'k' ] }, { homeUrl: 'https://e.com', featuredAlt: '' } ) );
	const b = Bridge.signature( Bridge.buildInput( { title: 'T', content: '<p>C</p>', keywords: [ 'k' ] }, { homeUrl: 'https://e.com', featuredAlt: '' } ) );
	const c = Bridge.signature( Bridge.buildInput( { title: 'T', content: '<p>C</p>', keywords: [ 'k2' ] }, { homeUrl: 'https://e.com', featuredAlt: '' } ) );
	assert.equal( a, b );
	assert.notEqual( a, c );
} );

test( 'shouldRun skips an identical input and runs on the first or a changed one', () => {
	assert.equal( Bridge.shouldRun( null, 'x' ), true );
	assert.equal( Bridge.shouldRun( 'x', 'x' ), false );
	assert.equal( Bridge.shouldRun( 'x', 'y' ), true );
} );
