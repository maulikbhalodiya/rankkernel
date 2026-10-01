'use strict';

/**
 * Localized global naming in the redirects admin script.
 *
 * The payload is localized under window.rankkernelRedirects, matching the
 * rankkernel prefixed sibling globals. The pre rename window.rkRedirects name
 * must keep working: the script reads it as a fallback and keeps it defined as
 * an alias for any consumer that still reads it.
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

function fakeAnchor() {
	let stored = '';

	return {
		protocol: '',
		host: '',
		pathname: '',
		search: '',
		get href() {
			return stored;
		},
		set href( value ) {
			stored = value;

			const url = new URL( value );

			this.protocol = url.protocol;
			this.host = url.host;
			this.pathname = url.pathname;
			this.search = url.search;
		}
	};
}

function fakeNode( props ) {
	const listeners = {};

	return Object.assign(
		{
			listeners,
			addEventListener( type, handler ) {
				listeners[ type ] = handler;
			},
			fire( type ) {
				listeners[ type ]();
			},
			classList: { add() {}, remove() {} },
			setAttribute() {},
			removeAttribute() {},
			getAttribute() {
				return null;
			},
			querySelector() {
				return null;
			},
			querySelectorAll() {
				return [];
			},
			outerHTML: ''
		},
		props
	);
}

function fakeFormData( form ) {
	this.forEach = ( callback ) => {
		( form.fields || [] ).forEach( ( field ) => callback( field.value, field.name ) );
	};
}

function perPageFixture() {
	const submits = [];
	const perPageForm = fakeNode( {
		fields: [
			{ name: 'page', value: 'rankkernel-redirects' },
			{ name: 'rk_per_page', value: '50' }
		],
		submit() {
			submits.push( 'submit' );
		}
	} );

	perPageForm.getAttribute = ( name ) =>
		( 'action' === name ? 'https://example.org/wp-admin/admin.php' : null );

	const select = fakeNode( { form: perPageForm } );
	const listWrap = fakeNode( {
		querySelector( selector ) {
			return '#rk-perpage-select' === selector ? select : null;
		}
	} );

	const document = {
		getElementById( id ) {
			return 'rk-list-section' === id ? listWrap : null;
		},
		querySelectorAll() {
			return [];
		},
		createElement( tag ) {
			return 'a' === tag ? fakeAnchor() : fakeNode();
		}
	};

	return { submits, select, listWrap, document };
}

function run( document, globals, fetchCalls ) {
	const sandbox = {
		document,
		confirm: () => true,
		FormData: fakeFormData,
		URLSearchParams,
		history: { replaceState() {} },
		addEventListener() {},
		fetch( url, options ) {
			fetchCalls.push( { url, options } );

			return Promise.resolve( {
				ok: true,
				json: () =>
					Promise.resolve( {
						success: true,
						data: { html: '<div id="rk-list-section"></div>' }
					} )
			} );
		}
	};

	sandbox.window = sandbox;

	Object.keys( globals ).forEach( ( name ) => {
		sandbox[ name ] = globals[ name ];
	} );

	vm.runInNewContext( source( 'redirects-admin.js' ), sandbox );

	return sandbox;
}

async function flushPromises() {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

test( 'the canonical rankkernelRedirects global drives the AJAX path and fills the legacy alias', async () => {
	const fixture = perPageFixture();
	const fetchCalls = [];
	const config = {
		ajaxUrl: 'https://example.org/wp-admin/admin-ajax.php',
		nonce: 'nonce-canonical',
		screenSlug: 'rankkernel-redirects'
	};

	const sandbox = run( fixture.document, { rankkernelRedirects: config }, fetchCalls );

	assert.equal( sandbox.rkRedirects, config, 'the legacy name must alias the canonical payload' );

	fixture.select.fire( 'change' );

	await flushPromises();

	assert.equal( fetchCalls.length, 1 );
	assert.equal( fetchCalls[ 0 ].url, config.ajaxUrl );
	assert.equal( fetchCalls[ 0 ].options.method, 'POST' );

	const body = new URLSearchParams( fetchCalls[ 0 ].options.body );

	assert.equal( body.get( '_ajax_nonce' ), 'nonce-canonical', 'the canonical payload must configure the request' );
	assert.equal( fixture.submits.length, 0, 'the GET form must not submit while the AJAX config is present' );
} );

test( 'the legacy rkRedirects global still configures the AJAX path when the canonical name is absent', async () => {
	const fixture = perPageFixture();
	const fetchCalls = [];
	const config = {
		ajaxUrl: 'https://example.org/wp-admin/admin-ajax.php',
		nonce: 'nonce-legacy',
		screenSlug: 'rankkernel-redirects'
	};

	run( fixture.document, { rkRedirects: config }, fetchCalls );

	fixture.select.fire( 'change' );

	await flushPromises();

	assert.equal( fetchCalls.length, 1 );
	assert.equal( fetchCalls[ 0 ].url, config.ajaxUrl );

	const body = new URLSearchParams( fetchCalls[ 0 ].options.body );

	assert.equal( body.get( '_ajax_nonce' ), 'nonce-legacy', 'the legacy payload must remain a working fallback' );
	assert.equal( fixture.submits.length, 0, 'the GET form must not submit while the fallback config is present' );
} );

test( 'no localized config keeps the plain GET fallback and invents no alias', () => {
	const fixture = perPageFixture();
	const fetchCalls = [];
	const sandbox = run( fixture.document, {}, fetchCalls );

	assert.equal( typeof sandbox.rkRedirects, 'undefined', 'an absent payload must not produce an alias global' );

	fixture.select.fire( 'change' );

	assert.equal( fixture.submits.length, 1, 'the GET form must submit when no config exists' );
	assert.equal( fetchCalls.length, 0 );
} );

test( 'the redirects admin script carries no standalone em dash', () => {
	const code = source( 'redirects-admin.js' );

	assert.equal(
		code.indexOf( '\u2014' ),
		-1,
		'an em dash pause is forbidden in code, comments and strings'
	);
} );
