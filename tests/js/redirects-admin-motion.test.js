'use strict';

/**
 * Redirect manager panel scrolling and the reduced motion preference.
 *
 * The script is a classic DOM script wrapped in an IIFE. The test mounts it
 * against a minimal fake document holding one panel and one toggle button,
 * fires the toggle click, and inspects the options handed to scrollIntoView.
 * The editor panel takes a focus branch instead of scrolling, so the scroll
 * assertions open a different panel.
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
	const node = Object.assign( {
		attrs: {},
		dataset: {},
		listeners: {},
		scrolled: null,
		addEventListener( type, handler ) {
			node.listeners[ type ] = handler;
		},
		removeEventListener() {},
		setAttribute( name, value ) {
			node.attrs[ name ] = String( value );
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( node.attrs, name ) ? node.attrs[ name ] : null;
		},
		hasAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( node.attrs, name );
		},
		removeAttribute( name ) {
			delete node.attrs[ name ];
		},
		focus() {
			node.focused = true;
		},
		scrollIntoView( options ) {
			node.scrolled = options;
		}
	}, props );

	return node;
}

/**
 * Mount the redirect manager and open the CSV panel.
 *
 * @param {boolean|null} reducedMotion Media query result, null to omit matchMedia.
 * @return {Object} The panel node after the toggle was clicked.
 */
function openCsvPanel( reducedMotion ) {
	const panel = fakeNode( { id: 'rk-redirect-csv', attrs: { hidden: '' } } );
	const toggle = fakeNode( {
		id: 'rk-toggle-csv',
		attrs: { 'data-rk-panel-toggle': 'rk-redirect-csv' }
	} );

	const document = {
		readyState: 'complete',
		addEventListener() {},
		getElementById( id ) {
			return 'rk-redirect-csv' === id ? panel : null;
		},
		querySelectorAll( selector ) {
			if ( '[data-rk-panel-toggle]' === selector ) {
				return [ toggle ];
			}

			return [];
		},
		querySelector() {
			return null;
		},
		createElement( tag ) {
			return fakeNode( { tagName: tag } );
		}
	};

	const sandbox = {
		document,
		window: {},
		console,
		setTimeout() {},
		clearTimeout() {},
		fetch() {
			return Promise.resolve( { ok: true, text: () => Promise.resolve( '' ) } );
		}
	};

	if ( null !== reducedMotion ) {
		sandbox.window.matchMedia = () => ( { matches: reducedMotion } );
	}

	sandbox.globalThis = sandbox;
	vm.createContext( sandbox );
	vm.runInContext( source( 'redirects-admin.js' ), sandbox );

	assert.ok( typeof toggle.listeners.click === 'function', 'the toggle is bound' );
	toggle.listeners.click( { preventDefault() {} } );

	return panel;
}

test( 'opening a panel scrolls smoothly when motion is allowed', () => {
	const panel = openCsvPanel( false );

	assert.ok( panel.scrolled, 'the panel scrolled' );
	assert.equal( 'smooth', panel.scrolled.behavior );
	assert.equal( 'nearest', panel.scrolled.block );
} );

test( 'opening a panel scrolls instantly when motion is reduced', () => {
	const panel = openCsvPanel( true );

	assert.ok( panel.scrolled, 'the panel still scrolled' );
	assert.equal( 'auto', panel.scrolled.behavior, 'a reduced motion visitor must not get a smooth scroll' );
} );

test( 'a missing matchMedia falls back to a smooth scroll rather than throwing', () => {
	const panel = openCsvPanel( null );

	assert.ok( panel.scrolled, 'the panel scrolled' );
	assert.equal( 'smooth', panel.scrolled.behavior );
} );