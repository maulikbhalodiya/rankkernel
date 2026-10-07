'use strict';

/**
 * 404 Monitor settings gear toggle tests.
 *
 * The script is a classic DOM script. These tests mount it against a minimal
 * fake document and pin the single-click open, the re-click close, and the
 * banner link opening without touching the URL hash. Mutation proof: drop the
 * toggle's setOpen call and the first test fails; drop the banner's
 * preventDefault and the third test fails.
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

function fakeNode() {
	const listeners = {};
	const attrs = {};

	return {
		hidden: true,
		listeners,
		scrolls: 0,
		addEventListener( type, handler ) {
			listeners[ type ] = handler;
		},
		fire( type, event = {} ) {
			const merged = Object.assign( { preventDefault() { this.prevented = true; } }, event );

			if ( listeners[ type ] ) {
				listeners[ type ]( merged );
			}

			return merged;
		},
		setAttribute( name, value ) {
			attrs[ name ] = value;
		},
		removeAttribute( name ) {
			delete attrs[ name ];
		},
		getAttribute( name ) {
			return attrs[ name ];
		},
		scrollIntoView() {
			this.scrolls++;
		}
	};
}

function mount() {
	const toggle = fakeNode();
	const panel = fakeNode();
	const banner = fakeNode();

	panel.hidden = true;
	toggle.setAttribute( 'aria-expanded', 'false' );

	const nodes = {};
	nodes[ 'rk-monitor-settings-toggle' ] = toggle;
	nodes[ 'rk-monitor-settings' ] = panel;
	nodes[ 'rk-monitor-settings-link' ] = banner;

	const domContentLoaded = [];
	const document = {
		addEventListener( type, handler ) {
			if ( 'DOMContentLoaded' === type ) {
				domContentLoaded.push( handler );
			}
		},
		getElementById( id ) {
			return nodes[ id ] || null;
		}
	};

	const sandbox = { document, window: {} };
	sandbox.window = sandbox;
	vm.runInNewContext( source( 'monitor-settings.js' ), sandbox );

	domContentLoaded.forEach( ( handler ) => handler() );

	return { toggle, panel, banner, sandbox };
}

test( 'the panel starts collapsed with aria-expanded false', () => {
	const { toggle, panel } = mount();

	assert.equal( panel.hidden, true );
	assert.equal( toggle.getAttribute( 'aria-expanded' ), 'false' );
} );

test( 'first gear click opens the panel, sets aria-expanded and scrolls', () => {
	const { toggle, panel } = mount();

	toggle.fire( 'click' );

	assert.equal( panel.hidden, false );
	assert.equal( toggle.getAttribute( 'aria-expanded' ), 'true' );
	assert.equal( panel.scrolls, 1 );
} );

test( 'a re-click on the gear hides the panel again', () => {
	const { toggle, panel } = mount();

	toggle.fire( 'click' );
	toggle.fire( 'click' );

	assert.equal( panel.hidden, true );
	assert.equal( toggle.getAttribute( 'aria-expanded' ), 'false' );
} );

test( 'the banner link opens the panel and prevents the bare anchor jump', () => {
	const { toggle, panel, banner } = mount();

	const event = banner.fire( 'click' );

	assert.equal( event.prevented, true, 'preventDefault must run or the URL hash jumps' );
	assert.equal( panel.hidden, false );
	assert.equal( toggle.getAttribute( 'aria-expanded' ), 'true' );
} );

test( 'the wiring is exposed for reuse and missing nodes are safe', () => {
	const { sandbox } = mount();

	assert.equal( typeof sandbox.rankkernelMonitorSettings.wire, 'function' );
	assert.equal( sandbox.rankkernelMonitorSettings.wire( null ), null );
} );
