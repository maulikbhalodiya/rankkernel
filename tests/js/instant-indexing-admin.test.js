'use strict';

/**
 * Live multi URL validation on the Instant Indexing screen.
 *
 * The script is a classic DOM script, so it runs here in a vm sandbox with
 * a minimal fake document. Each test calls the exposed pure validator with
 * the same host wp_localize_script passes in the browser, so no DOM is
 * needed for the validation rules themselves.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );

function source( file ) {
	return readFileSync( path.join( root, 'assets', 'js', file ), 'utf8' );
}

function validator( siteHost ) {
	const document = {
		readyState: 'complete',
		addEventListener() {},
		getElementById() {
			return null;
		}
	};

	const config = {
		siteHost: siteHost || 'example.com',
		maxUrls: 10000
	};
	const sandbox = { document, URL, rankkernelInstantIndexing: config };

	sandbox.window = sandbox;
	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );

	assert.equal( typeof config.validate, 'function', 'the pure validator must be reachable from the harness' );

	return ( text ) => config.validate( text, config.siteHost );
}

test( 'a valid same host URL validates clean', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://example.com/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.validCount, 1 );
	assert.equal( result.invalidCount, 0 );
	assert.equal( result.entries[ 0 ].valid, true );
	assert.equal( result.entries[ 0 ].reason, '' );
	assert.equal( result.summary, '1 URLs ready to submit.' );
	assert.equal( result.disabled, false );
} );

test( 'a foreign host URL returns the host reason', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://evil.test/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.validCount, 0 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].valid, false );
	assert.equal( result.entries[ 0 ].reason, 'This URL is not on this site.' );
	assert.equal( result.disabled, true );
} );

test( 'a malformed string returns the not a valid URL reason', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'not a url' );

	assert.equal( result.total, 1 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].reason, 'Not a valid URL.' );
} );

test( 'a www host against an apex site host is invalid', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://www.example.com/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].reason, 'This URL is not on this site.' );
	assert.equal( result.disabled, true );
} );

test( 'an empty input returns the empty state', () => {
	const validate = validator( 'example.com' );
	const result = validate( '' );

	assert.equal( result.total, 0 );
	assert.equal( result.validCount, 0 );
	assert.equal( result.invalidCount, 0 );
	assert.equal( result.entries.length, 0 );
	assert.equal( result.summary, 'Enter at least one URL on this site.' );
	assert.equal( result.disabled, true );
} );

test( 'mixed input reports the correct invalid count', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://example.com/a\nhttps://evil.test/b\nnot a url\nhttps://example.com/c' );

	assert.equal( result.total, 4 );
	assert.equal( result.validCount, 2 );
	assert.equal( result.invalidCount, 2 );
	assert.equal(
		result.entries.map( ( entry ) => entry.valid ).join( ',' ),
		'true,false,false,true'
	);
	assert.equal( result.summary, '2 of 4 URLs are not valid. Fix them to continue.' );
	assert.equal( result.disabled, true );
} );
