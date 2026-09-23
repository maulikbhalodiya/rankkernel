'use strict';

/**
 * Announcement wiring in the two admin screens.
 *
 * Both files are classic DOM scripts, so each one runs here in a vm sandbox
 * with a minimal fake document. The tests pin the spoken text for the bulk
 * select toggle, the best effort fallbacks, the plugin scoped helper name and
 * the literal call sites the WordPress string extractor depends on.
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

function fakeNode( props ) {
	return Object.assign( {
		checked: false,
		value: '',
		listeners: {},
		addEventListener( type, handler ) {
			this.listeners[ type ] = handler;
		},
		fire( type ) {
			this.listeners[ type ]();
		},
		getAttribute() {
			return null;
		},
		appendChild() {},
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		},
		focus() {}
	}, props );
}

function fakeDocument( elements, selectors ) {
	const handlers = [];

	return {
		handlers,
		addEventListener( type, handler ) {
			if ( 'DOMContentLoaded' === type ) {
				handlers.push( handler );
			}
		},
		getElementById( id ) {
			return elements[ id ] || null;
		},
		querySelectorAll( selector ) {
			return selectors[ selector ] || [];
		}
	};
}

function run( file, document, wp ) {
	const sandbox = { document, wp, confirm: () => true };

	sandbox.window = sandbox;

	vm.runInNewContext( source( file ), sandbox );

	return sandbox;
}

function speakingWp( spoken, extra ) {
	return Object.assign(
		{ a11y: { speak: ( text ) => spoken.push( text ) } },
		extra || {}
	);
}

function translatingWp( spoken ) {
	return speakingWp( spoken, { i18n: { __: ( text, domain ) => domain + ':' + text } } );
}

function selectAllFixture() {
	const boxes = [ fakeNode(), fakeNode() ];
	const selectAll = fakeNode();
	const bulkForm = fakeNode( { querySelectorAll: () => boxes } );

	return {
		boxes,
		selectAll,
		document: fakeDocument( { 'rk-bulk-form': bulkForm, 'rk-select-all': selectAll }, {} )
	};
}

function toggleSelectAll( fixture, checked ) {
	fixture.selectAll.checked = checked;
	fixture.selectAll.fire( 'change' );
}

test( 'monitor-admin speaks the translated select all state and exposes no helper global', () => {
	const spoken = [];
	const fixture = selectAllFixture();
	const sandbox = run( 'monitor-admin.js', fixture.document, translatingWp( spoken ) );

	assert.equal( typeof sandbox.rkAnnounce, 'undefined' );
	assert.equal( typeof sandbox.rankkernelAnnounce, 'undefined' );

	fixture.document.handlers.forEach( ( handler ) => handler() );
	toggleSelectAll( fixture, true );
	toggleSelectAll( fixture, false );

	assert.deepEqual( spoken, [
		'rankkernel:All 404 entries selected.',
		'rankkernel:All 404 entries deselected.'
	] );
	assert.equal( fixture.boxes[ 0 ].checked, false );
	assert.equal( fixture.boxes[ 1 ].checked, false );
} );

test( 'monitor-admin speaks the raw string when wp.i18n is absent', () => {
	const spoken = [];
	const fixture = selectAllFixture();

	run( 'monitor-admin.js', fixture.document, speakingWp( spoken ) );

	fixture.document.handlers.forEach( ( handler ) => handler() );
	toggleSelectAll( fixture, true );

	assert.deepEqual( spoken, [ 'All 404 entries selected.' ] );
} );

test( 'monitor-admin stays silent without wp.a11y and does not throw when wp is absent', () => {
	const spoken = [];
	const fixture = selectAllFixture();

	run( 'monitor-admin.js', fixture.document, { i18n: { __: ( text ) => text } } );

	fixture.document.handlers.forEach( ( handler ) => handler() );
	fixture.selectAll.checked = true;

	assert.doesNotThrow( () => fixture.selectAll.fire( 'change' ) );
	assert.deepEqual( spoken, [] );

	const bare = selectAllFixture();
	const sandbox = run( 'monitor-admin.js', bare.document, undefined );

	assert.doesNotThrow( () => {
		sandbox.document.handlers.forEach( ( handler ) => handler() );
	} );
} );

test( 'monitor-admin announces an added exclusion row', () => {
	const spoken = [];
	const document = fakeDocument(
		{
			'rk-exclusion-add': fakeNode(),
			'rk-exclusions-body': fakeNode(),
			'rk-exclusion-template': { content: { cloneNode: () => fakeNode() } }
		},
		{ '.rk-monitor .rk-confirm': [] }
	);

	run( 'monitor-admin.js', document, translatingWp( spoken ) );

	document.handlers.forEach( ( handler ) => handler() );
	document.getElementById( 'rk-exclusion-add' ).fire( 'click' );

	assert.deepEqual( spoken, [ 'rankkernel:Exclusion row added.' ] );
} );

test( 'redirects-admin speaks the translated select all state under the prefixed helper', () => {
	const spoken = [];
	const fixture = selectAllFixture();
	const sandbox = run( 'redirects-admin.js', fixture.document, translatingWp( spoken ) );

	assert.equal( typeof sandbox.rankkernelAnnounce, 'function' );
	assert.equal( typeof sandbox.rkAnnounce, 'undefined' );

	fixture.document.handlers.forEach( ( handler ) => handler() );
	toggleSelectAll( fixture, true );
	toggleSelectAll( fixture, false );

	assert.deepEqual( spoken, [
		'rankkernel:All redirects selected.',
		'rankkernel:All redirects deselected.'
	] );
	assert.equal( fixture.boxes[ 0 ].checked, false );
	assert.equal( fixture.boxes[ 1 ].checked, false );
} );

test( 'redirects-admin announces the applied recommended destination', () => {
	const spoken = [];
	const target = fakeNode();
	const button = fakeNode( { getAttribute: () => 'https://example.org/new-target' } );
	const document = fakeDocument(
		{ 'rk-target': target },
		{ '.rk-redirects .rk-confirm': [], '[data-rk-use-destination]': [ button ] }
	);

	run( 'redirects-admin.js', document, translatingWp( spoken ) );

	document.handlers.forEach( ( handler ) => handler() );
	button.fire( 'click' );

	assert.equal( target.value, 'https://example.org/new-target' );
	assert.deepEqual( spoken, [ 'rankkernel:Recommended destination applied to Destination URL field.' ] );
} );

test( 'every announcement literal is a direct translation argument for the WordPress extractor', () => {
	const announcements = {
		'monitor-admin.js': [
			'All 404 entries selected.',
			'All 404 entries deselected.',
			'Exclusion row added.',
			'Exclusion row removed.',
			'Exclusion row cleared.'
		],
		'redirects-admin.js': [
			'All redirects selected.',
			'All redirects deselected.',
			'Recommended destination applied to Destination URL field.'
		]
	};

	Object.keys( announcements ).forEach( ( file ) => {
		const code = source( file );
		const literals = [];
		const pattern = /__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'rankkernel'\s*\)/g;
		let match;

		while ( ( match = pattern.exec( code ) ) !== null ) {
			literals.push( match[ 1 ] );
		}

		assert.equal(
			( code.match( /__\(/g ) || [] ).length,
			literals.length,
			file + ' must not pass a variable to a translation call'
		);

		announcements[ file ].forEach( ( message ) => {
			assert.ok(
				literals.includes( message ),
				file + ' must pass ' + JSON.stringify( message ) + ' to a translation call as a literal'
			);
		} );
	} );
} );
