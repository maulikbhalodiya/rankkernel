'use strict';

/**
 * Filter form auto-submit and dead-click fallback in the redirects
 * admin list.
 *
 * The match/code dropdowns and the debounced search input must refresh
 * the list through the same AJAX refreshForm() path as the submit button.
 * Tab anchors and the filter form must only preventDefault() when the
 * AJAX fetch will actually start; otherwise the browser's normal
 * navigation must run so the click is never silently dead.
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
			fire( type, event ) {
				if ( listeners[ type ] ) {
					listeners[ type ]( event || {} );
				}
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

function filterFixture() {
	const submits = [];
	const matchEl  = fakeNode( {} );
	const codeEl   = fakeNode( {} );
	const searchEl = fakeNode( {} );

	const filterForm = fakeNode( {
		fields: [
			{ name: 'page', value: 'rankkernel-redirects' },
			{ name: 's', value: 'foo' },
			{ name: 'rk_match', value: 'regex' },
			{ name: 'rk_code', value: '301' }
		],
		submit() {
			submits.push( 'submit' );
		},
		querySelector( selector ) {
			if ( '#rk-filter-match' === selector ) {
				return matchEl;
			}

			if ( '#rk-filter-code' === selector ) {
				return codeEl;
			}

			if ( '#rk-search-input' === selector ) {
				return searchEl;
			}

			return null;
		}
	} );

	filterForm.getAttribute = ( name ) =>
		( 'action' === name ? 'https://example.org/wp-admin/admin.php' : null );

	const tabEl = fakeNode( {} );
	tabEl.getAttribute = ( name ) =>
		( 'data-rk-filter-url' === name
			? 'https://example.org/wp-admin/admin.php?page=rankkernel-redirects&rk_status=active'
			: null );

	const listWrap = fakeNode( {
		querySelector( selector ) {
			if ( '#rk-filter-form' === selector ) {
				return filterForm;
			}

			return null;
		},
		querySelectorAll( selector ) {
			if ( '[data-rk-filter-url]' === selector ) {
				return [ tabEl ];
			}

			return [];
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

	return { submits, matchEl, codeEl, searchEl, filterForm, tabEl, listWrap, document };
}

function run( document, rkRedirects, fetchImpl ) {
	const sandbox = {
		document,
		confirm: () => true,
		FormData: fakeFormData,
		URLSearchParams,
		history: { replaceState() {} },
		addEventListener() {},
		setTimeout,
		clearTimeout,
		fetch: fetchImpl
	};

	sandbox.window = sandbox;

	if ( rkRedirects ) {
		sandbox.rkRedirects = rkRedirects;
	}

	vm.runInNewContext( source( 'redirects-admin.js' ), sandbox );

	return sandbox;
}

function okFetch( fetchCalls ) {
	return ( url, options ) => {
		fetchCalls.push( { url, options } );

		return Promise.resolve( {
			ok: true,
			json: () =>
				Promise.resolve( {
					success: true,
					data: { html: '<div id="rk-list-section"></div>' }
				} )
		} );
	};
}

async function flushPromises() {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

const config = {
	ajaxUrl: 'https://example.org/wp-admin/admin-ajax.php',
	nonce: 'nonce-rankkernel_redirects_list',
	screenSlug: 'rankkernel-redirects'
};

test( 'match type dropdown change refreshes the list through the AJAX path', async () => {
	const fixture = filterFixture();
	const fetchCalls = [];

	run( fixture.document, config, okFetch( fetchCalls ) );

	fixture.matchEl.fire( 'change' );

	await flushPromises();

	assert.equal( fixture.submits.length, 0, 'the GET form must not submit' );
	assert.equal( fetchCalls.length, 1 );

	const body = new URLSearchParams( fetchCalls[ 0 ].options.body );

	assert.equal( body.get( 'action' ), 'rankkernel_redirects_list' );
	assert.equal( body.get( 'rk_match' ), 'regex' );
} );

test( 'code dropdown change refreshes the list through the AJAX path', async () => {
	const fixture = filterFixture();
	const fetchCalls = [];

	run( fixture.document, config, okFetch( fetchCalls ) );

	fixture.codeEl.fire( 'change' );

	await flushPromises();

	assert.equal( fixture.submits.length, 0 );
	assert.equal( fetchCalls.length, 1 );

	const body = new URLSearchParams( fetchCalls[ 0 ].options.body );

	assert.equal( body.get( 'rk_code' ), '301' );
} );

test( 'search input refreshes the list once, debounced at 300ms', async () => {
	const fixture = filterFixture();
	const fetchCalls = [];

	run( fixture.document, config, okFetch( fetchCalls ) );

	fixture.searchEl.fire( 'input' );
	fixture.searchEl.fire( 'input' );
	fixture.searchEl.fire( 'input' );

	assert.equal( fetchCalls.length, 0, 'input must not refresh synchronously: the debounce window comes first' );

	await new Promise( ( resolve ) => setTimeout( resolve, 350 ) );
	await flushPromises();

	assert.equal( fetchCalls.length, 1, 'rapid input must collapse into a single refresh' );

	fixture.searchEl.fire( 'input' );

	await new Promise( ( resolve ) => setTimeout( resolve, 350 ) );
	await flushPromises();

	assert.equal( fetchCalls.length, 2, 'a later edit refreshes again after its own debounce window' );
} );

test( 'tab click prevents default and fetches when the AJAX path can run', async () => {
	const fixture = filterFixture();
	const fetchCalls = [];

	run( fixture.document, config, okFetch( fetchCalls ) );

	let prevented = false;
	fixture.tabEl.listeners.click( {
		preventDefault() {
			prevented = true;
		}
	} );

	await flushPromises();

	assert.equal( prevented, true );
	assert.equal( fetchCalls.length, 1 );
} );

test( 'tab click during an in-flight refresh does not preventDefault and does not fetch again', async () => {
	const fixture = filterFixture();
	const fetchCalls = [];

	/* A fetch that never resolves keeps ajaxActive=true. */
	run( fixture.document, config, ( url, options ) => {
		fetchCalls.push( { url, options } );

		return new Promise( () => {} );
	} );

	let firstPrevented = false;
	fixture.tabEl.listeners.click( {
		preventDefault() {
			firstPrevented = true;
		}
	} );

	assert.equal( firstPrevented, true );
	assert.equal( fetchCalls.length, 1 );

	let secondPrevented = false;
	fixture.tabEl.listeners.click( {
		preventDefault() {
			secondPrevented = true;
		}
	} );

	assert.equal( secondPrevented, false, 'no preventDefault when fetchList would early-return: the anchor must navigate normally' );
	assert.equal( fetchCalls.length, 1, 'no second fetch while one is in flight' );
} );

test( 'filter form submit during an in-flight refresh does not preventDefault', async () => {
	const fixture = filterFixture();
	const fetchCalls = [];

	run( fixture.document, config, ( url, options ) => {
		fetchCalls.push( { url, options } );

		return new Promise( () => {} );
	} );

	let firstPrevented = false;
	fixture.filterForm.listeners.submit( {
		preventDefault() {
			firstPrevented = true;
		}
	} );

	assert.equal( firstPrevented, true );

	let secondPrevented = false;
	fixture.filterForm.listeners.submit( {
		preventDefault() {
			secondPrevented = true;
		}
	} );

	assert.equal( secondPrevented, false, 'the form must fall back to a normal GET submit instead of a silent dead submit' );
	assert.equal( fetchCalls.length, 1 );
} );

test( 'dropdown change during an in-flight refresh falls back to form.submit()', () => {
	const fixture = filterFixture();
	const fetchCalls = [];

	run( fixture.document, config, ( url, options ) => {
		fetchCalls.push( { url, options } );

		return new Promise( () => {} );
	} );

	fixture.tabEl.listeners.click( { preventDefault() {} } );
	assert.equal( fetchCalls.length, 1 );

	fixture.matchEl.fire( 'change' );

	assert.equal( fixture.submits.length, 1, 'the native form submit must run as the fallback' );
	assert.equal( fetchCalls.length, 1 );
} );

test( 'tab click without fetch navigates instead of throwing', () => {
	const fixture = filterFixture();

	const sandbox = run( fixture.document, config, undefined );
	sandbox.location = { href: '' };

	fixture.tabEl.listeners.click( { preventDefault() {} } );

	assert.equal(
		sandbox.location.href,
		'https://example.org/wp-admin/admin.php?page=rankkernel-redirects&rk_status=active',
		'without fetch the click must fall back to a normal navigation before any state is touched'
	);
} );
