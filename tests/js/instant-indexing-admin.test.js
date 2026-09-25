'use strict';

/**
 * Live multi URL validation on the Instant Indexing screen.
 *
 * The script is a classic DOM script. The validation rules run through the
 * exposed pure validator with the same host wp_localize_script passes in the
 * browser, and the wiring test mounts the script against a minimal fake
 * document to pin the button state and the blocked submit.
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
		siteHost: siteHost || 'example.com'
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

test( 'blank and duplicate lines are counted once', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://example.com/a\n\nhttps://example.com/a\nhttps://example.com/b' );

	assert.equal( result.total, 2 );
	assert.equal( result.validCount, 2 );
	assert.equal( result.summary, '2 URLs ready to submit.' );
	assert.equal( result.entries[ 1 ].url, 'https://example.com/b' );
} );

function fakeNode( props ) {
	const listeners = {};
	let text = '';

	const node = Object.assign(
		{
			value: '',
			disabled: false,
			className: '',
			children: [],
			attrs: {},
			addEventListener( type, handler ) {
				listeners[ type ] = handler;
			},
			fire( type, event ) {
				if ( listeners[ type ] ) {
					listeners[ type ]( Object.assign( { target: node, preventDefault() {} }, event || {} ) );
				}
			},
			appendChild( child ) {
				this.children.push( child );
			},
			getAttribute( name ) {
				return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
			},
			setAttribute( name, value ) {
				this.attrs[ name ] = value;
			}
		},
		props
	);

	Object.defineProperty( node, 'textContent', {
		get() {
			return text;
		},
		set( value ) {
			text = String( value );
			node.children.length = 0;
		}
	} );

	return node;
}

function mount( value ) {
	const readyHandlers = [];
	const form = fakeNode( {} );
	const field = fakeNode( { value: value, form: form } );
	const status = fakeNode( {} );
	const button = fakeNode( {} );

	const document = {
		readyState: 'loading',
		addEventListener( type, handler ) {
			if ( 'DOMContentLoaded' === type ) {
				readyHandlers.push( handler );
			}
		},
		getElementById( id ) {
			if ( 'rankkernel-indexnow-urls' === id ) {
				return field;
			}
			if ( 'rankkernel-indexnow-urls-status' === id ) {
				return status;
			}
			if ( 'rankkernel-indexnow-submit' === id ) {
				return button;
			}

			return null;
		},
		createElement( tag ) {
			return fakeNode( { tagName: tag } );
		}
	};

	const sandbox = {
		document,
		URL,
		setTimeout( callback ) {
			callback();

			return 1;
		},
		clearTimeout() {},
		rankkernelInstantIndexing: { siteHost: 'example.com' }
	};

	sandbox.window = sandbox;
	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );
	readyHandlers.forEach( ( handler ) => handler() );

	return {
		button,
		field,
		status,
		submit() {
			let prevented = false;

			form.fire( 'submit', {
				preventDefault() {
					prevented = true;
				}
			} );

			return prevented;
		}
	};
}

test( 'the mounted script disables the button and blocks an invalid submit', () => {
	const fixture = mount( 'https://example.com/a\nhttps://evil.test/b' );

	assert.equal( fixture.button.disabled, true );
	assert.equal( fixture.status.children[ 0 ].textContent, '1 of 2 URLs are not valid. Fix them to continue.' );
	assert.equal( fixture.submit(), true, 'an invalid submit event must be prevented' );

	fixture.field.value = 'https://example.com/a\nhttps://example.com/b';
	fixture.field.fire( 'input' );

	assert.equal( fixture.button.disabled, false );
	assert.equal( fixture.status.children[ 0 ].textContent, '2 URLs ready to submit.' );
	assert.equal( fixture.submit(), false, 'a valid submit event must go through' );
} );
