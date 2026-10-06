'use strict';

/**
 * Diagnostics for the Classic analysis panel.
 *
 * Only the two TinyMCE read failures may log, and only when window.console is
 * available. Every other defensive catch stays silent. The script runs in a vm
 * with a minimal fake document and injected timers, following the pattern of
 * analysis-editor-i18n.test.js.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );
const Analyzer = require( '../../assets/js/analysis/analyzer.js' );
const Bridge = require( '../../assets/js/analysis/editor-bridge.js' );

function source() {
	return readFileSync( path.join( root, 'assets', 'js', 'analysis-editor.js' ), 'utf8' );
}

function fakeNode( props ) {
	return Object.assign( {
		value: '',
		className: '',
		textContent: '',
		children: [],
		attributes: {},
		listeners: {},
		get innerHTML() {
			return this._innerHTML || '';
		},
		set innerHTML( value ) {
			this._innerHTML = value;
			this.children = [];
		},
		addEventListener( type, handler ) {
			this.listeners[ type ] = handler;
		},
		setAttribute( name, value ) {
			this.attributes[ name ] = value;
		},
		appendChild( child ) {
			this.children.push( child );
			return child;
		}
	}, props || {} );
}

function fakeDocument( elements ) {
	return {
		getElementById( id ) {
			return elements[ id ] || null;
		},
		querySelector( selector ) {
			return elements[ selector ] || null;
		},
		createElement( tag ) {
			return fakeNode( { tagName: tag } );
		}
	};
}

function boot( options ) {
	const settings = options || {};
	const warns = [];
	const results = fakeNode();
	const score = fakeNode();
	const content = fakeNode( { value: '<p>red apples in the textarea.</p>' } );
	const elements = {
		'[data-rk-analysis-results="1"]': results,
		'[data-rk-analysis-score="1"]': score,
		'rankkernel-meta-focus-keywords': fakeNode( { value: 'red apples' } ),
		title: fakeNode( { value: 'Red apples guide' } ),
		post_name: fakeNode( { value: 'red-apples-guide' } ),
		'rankkernel-meta-description': fakeNode( { value: 'All about red apples.' } ),
		content: content
	};
	const timeouts = [];
	const sandbox = {
		document: fakeDocument( elements ),
		rankkernelMetaEditor: {
			analysis: { path: '/wp-json/rankkernel/v1/analysis' },
			homeUrl: 'https://example.com'
		},
		RankKernelAnalysis: {
			Analyzer: settings.analyzer || Analyzer,
			EditorBridge: Bridge
		},
		setTimeout( callback ) {
			timeouts.push( callback );
			return timeouts.length;
		},
		clearTimeout() {},
		setInterval() {
			return 1;
		},
		clearInterval() {}
	};

	if ( settings.tinymce ) {
		sandbox.tinymce = settings.tinymce;
	}

	if ( false !== settings.console ) {
		sandbox.console = {
			warn() {
				warns.push( Array.prototype.slice.call( arguments ) );
			}
		};
	}

	sandbox.window = sandbox;

	vm.runInNewContext( source(), sandbox );

	return {
		results,
		content,
		warns,
		timeouts,
		flush() {
			while ( timeouts.length > 0 ) {
				timeouts.shift()();
			}
		}
	};
}

function throwingEditor( error ) {
	return {
		rankernelAnalysisBound: false,
		isHidden() {
			return false;
		},
		getContent() {
			throw new Error( error );
		},
		on() {}
	};
}

test( 'a failing TinyMCE body read warns once and falls back to the textarea', () => {
	const editor = throwingEditor( 'read failed' );
	const seen = [];
	const analyzer = {
		analyze( input ) {
			seen.push( input.html );
			return { score: 1, band: 'problem', checks: [] };
		}
	};
	const fixture = boot( { tinymce: { get() { return editor; } }, analyzer } );
	fixture.flush();

	assert.equal( fixture.warns.length, 1 );
	assert.match( fixture.warns[ 0 ][ 0 ], /TinyMCE/ );
	assert.match( fixture.warns[ 0 ][ 0 ], /body/ );
	assert.equal( fixture.warns[ 0 ][ 1 ].message, 'read failed' );
	assert.deepEqual( seen, [ '<p>red apples in the textarea.</p>' ] );
} );

test( 'a failing TinyMCE bind read warns and leaves the panel alive', () => {
	const tinymce = {
		get() {
			throw new Error( 'bind failed' );
		}
	};
	const fixture = boot( { tinymce } );
	fixture.flush();

	assert.equal( fixture.warns.length, 2 );
	assert.match( fixture.warns[ 0 ][ 0 ], /TinyMCE/ );
	assert.match( fixture.warns[ 0 ][ 0 ], /by id/ );
	assert.equal( fixture.warns[ 0 ][ 1 ].message, 'bind failed' );
	assert.match( fixture.warns[ 1 ][ 0 ], /TinyMCE/ );
	assert.match( fixture.warns[ 1 ][ 0 ], /body/ );
} );

test( 'a failed analyze run stays silent on the console', () => {
	const analyzer = {
		analyze() {
			throw new Error( 'engine failure' );
		}
	};
	const fixture = boot( { analyzer } );
	fixture.flush();

	assert.equal( fixture.warns.length, 0 );
	assert.equal( fixture.results.children[ 0 ].textContent, 'The analysis could not be run. Try again.' );
} );

test( 'a missing console object does not break the TinyMCE fallback', () => {
	const editor = throwingEditor( 'read failed' );
	const fixture = boot( { tinymce: { get() { return editor; } }, console: false } );
	fixture.flush();

	assert.equal( fixture.warns.length, 0 );
	assert.ok( fixture.results.children.length > 0 );
} );
