'use strict';

/**
 * Rows per page routing in the redirects admin list.
 *
 * The select must refresh the list through the same AJAX path as the tabs,
 * filters, sort headers and pagination instead of submitting its GET form
 * and reloading the whole page. These tests pin both branches: the AJAX
 * refresh when the localized config is present, and the plain submit
 * fallback when it is missing.
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
			{ name: 's', value: '' },
			{ name: 'rk_status', value: 'all' },
			{ name: 'rk_match', value: '' },
			{ name: 'rk_code', value: '' },
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

function run( document, rkRedirects, fetchCalls ) {
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

	if ( rkRedirects ) {
		sandbox.rkRedirects = rkRedirects;
	}

	vm.runInNewContext( source( 'redirects-admin.js' ), sandbox );

	return sandbox;
}

async function flushPromises() {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

test( 'rows per page change refreshes the list through the AJAX path without submitting the form', async () => {
	const fixture = perPageFixture();
	const fetchCalls = [];

	run(
		fixture.document,
		{
			ajaxUrl: 'https://example.org/wp-admin/admin-ajax.php',
			nonce: 'nonce-rankkernel_redirects_list',
			screenSlug: 'rankkernel-redirects'
		},
		fetchCalls
	);

	fixture.select.fire( 'change' );

	await flushPromises();

	assert.equal( fixture.submits.length, 0, 'the GET form must not submit' );
	assert.equal( fetchCalls.length, 1 );
	assert.equal( fetchCalls[ 0 ].url, 'https://example.org/wp-admin/admin-ajax.php' );
	assert.equal( fetchCalls[ 0 ].options.method, 'POST' );

	const body = new URLSearchParams( fetchCalls[ 0 ].options.body );

	assert.equal( body.get( 'action' ), 'rankkernel_redirects_list' );
	assert.equal( body.get( '_ajax_nonce' ), 'nonce-rankkernel_redirects_list' );
	assert.equal( body.get( 'page' ), 'rankkernel-redirects' );
	assert.equal( body.get( 'rk_per_page' ), '50' );
} );

test( 'rows per page change submits the GET form when the AJAX config is missing', () => {
	const fixture = perPageFixture();
	const fetchCalls = [];

	run( fixture.document, null, fetchCalls );

	fixture.select.fire( 'change' );

	assert.equal( fixture.submits.length, 1, 'the GET form must submit as the fallback' );
	assert.equal( fetchCalls.length, 0 );
} );
