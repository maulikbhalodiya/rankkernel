'use strict';

/**
 * Classic metadata editor localisation contract.
 *
 * metadata-editor.js is a classic DOM script, so it runs here in a vm
 * sandbox with a minimal fake document. The tests pin the guarded wp.i18n
 * shim (no ReferenceError when the handle is missing, translation when it
 * is present), the localised strings map taking precedence, the
 * tokenInsert key the server publishes, and the literal call sites the
 * WordPress string extractor depends on.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );

function source() {
	return readFileSync( path.join( root, 'assets', 'js', 'metadata-editor.js' ), 'utf8' );
}

function fakeNode( props ) {
	const attrs = Object.assign( {}, ( props && props.attrs ) || {} );

	return Object.assign(
		{
			value: '',
			type: '',
			tagName: '',
			checked: false,
			disabled: false,
			className: '',
			textContent: '',
			innerHTML: '',
			children: [],
			listeners: {},
			style: {},
			parentNode: null,
			classList: {
				add() {},
				remove() {},
				toggle() {}
			},
			addEventListener( type, handler ) {
				this.listeners[ type ] = handler;
			},
			setAttribute( name, value ) {
				attrs[ name ] = String( value );
			},
			getAttribute( name ) {
				return Object.prototype.hasOwnProperty.call( attrs, name ) ? attrs[ name ] : null;
			},
			removeAttribute( name ) {
				delete attrs[ name ];
			},
			appendChild( child ) {
				this.children.push( child );
				child.parentNode = this;

				return child;
			},
			removeChild( child ) {
				this.children = this.children.filter( ( item ) => item !== child );
				child.parentNode = null;

				return child;
			},
			insertBefore( child, before ) {
				const index = this.children.indexOf( before );
				this.children.splice( index < 0 ? this.children.length : index, 0, child );
				child.parentNode = this;

				return child;
			},
			contains() {
				return true;
			},
			querySelector() {
				return null;
			},
			querySelectorAll() {
				return [];
			},
			closest() {
				return null;
			},
			focus() {},
			setSelectionRange() {}
		},
		props || {},
		{ __attrs: attrs }
	);
}

function fakeRoot( config ) {
	const settings = config || {};
	const queryMap = settings.query || {};
	const allMap = settings.queryAll || {};
	const node = fakeNode();

	node.querySelector = ( selector ) => queryMap[ selector ] || null;
	node.querySelectorAll = ( selector ) => allMap[ selector ] || [];

	return node;
}

function boot( options ) {
	const settings = options || {};
	const roots = settings.roots || [];
	const byId = settings.byId || {};
	const listeners = {};

	const sandbox = {
		URL,
		setTimeout,
		clearTimeout,
		document: {
			readyState: 'complete',
			getElementById( id ) {
				return byId[ id ] || null;
			},
			querySelector() {
				return null;
			},
			querySelectorAll( selector ) {
				return '[data-rankkernel-meta-editor]' === selector ? roots : [];
			},
			createElement( tag ) {
				return fakeNode( { tagName: tag } );
			},
			addEventListener( type, handler ) {
				listeners[ type ] = handler;
			}
		}
	};

	sandbox.window = sandbox;

	if ( Object.prototype.hasOwnProperty.call( settings, 'wp' ) ) {
		sandbox.wp = settings.wp;
	}

	sandbox.rankkernelMetaEditor = settings.cfg || {};

	vm.runInNewContext( source(), sandbox );

	return {
		sandbox,
		listeners,
		init() {
			if ( listeners.DOMContentLoaded ) {
				listeners.DOMContentLoaded();
			}
		}
	};
}

function translatingWp( seen ) {
	return {
		i18n: {
			__( text, domain ) {
				if ( seen ) {
					seen.push( [ text, domain ] );
				}

				return 'rankkernel:' + text;
			}
		}
	};
}

function statusWord( fixture, status ) {
	return fixture.sandbox.rankkernelMetaEditorPreview.statusWord( status );
}

test( 'the classic metadata editor renders the raw English status words when wp.i18n is absent', () => {
	const noWp = boot( {} );
	assert.equal( statusWord( noWp, 'warn' ), 'Long' );
	assert.equal( statusWord( noWp, 'over' ), 'Too long' );
	assert.equal( statusWord( noWp, 'ok' ), 'OK' );

	const emptyWp = boot( { wp: {} } );
	assert.equal( statusWord( emptyWp, 'over' ), 'Too long' );

	const noI18n = boot( { wp: { i18n: {} } } );
	assert.equal( statusWord( noI18n, 'over' ), 'Too long' );
} );

test( 'the classic metadata editor uses wp.i18n when the handle is present', () => {
	const seen = [];
	const fixture = boot( { wp: translatingWp( seen ) } );

	assert.equal( statusWord( fixture, 'warn' ), 'rankkernel:Long' );
	assert.equal( statusWord( fixture, 'over' ), 'rankkernel:Too long' );
	assert.equal( statusWord( fixture, 'ok' ), 'rankkernel:OK' );
	assert.deepEqual( seen, [
		[ 'Long', 'rankkernel' ],
		[ 'Too long', 'rankkernel' ],
		[ 'OK', 'rankkernel' ]
	] );
} );

test( 'the classic metadata editor prefers the localised strings map over wp.i18n', () => {
	const fixture = boot( {
		wp: translatingWp(),
		cfg: { strings: { longLabel: 'LOCAL LONG', tooLongLabel: 'LOCAL TOO LONG' } }
	} );

	assert.equal( statusWord( fixture, 'warn' ), 'LOCAL LONG' );
	assert.equal( statusWord( fixture, 'over' ), 'LOCAL TOO LONG' );
	assert.equal( statusWord( fixture, 'ok' ), 'rankkernel:OK' );
} );

test( 'the classic metadata editor consumes the server tokenInsert string for the token picker', () => {
	const list = fakeNode();
	const editorRoot = fakeRoot( { queryAll: { '[data-rankkernel-tokens]': [ list ] } } );

	const fixture = boot( {
		cfg: {
			strings: { tokenInsert: 'LOCALIZED INSERT' },
			tokenLabels: { title: 'Title' }
		},
		roots: [ editorRoot ]
	} );
	fixture.init();

	assert.equal( list.children.length, 1 );
	assert.equal( list.children[ 0 ].getAttribute( 'aria-label' ), 'LOCALIZED INSERT title (Title)' );
} );

test( 'the classic metadata editor translates the token picker label when the map lacks the key', () => {
	const list = fakeNode();
	const editorRoot = fakeRoot( { queryAll: { '[data-rankkernel-tokens]': [ list ] } } );

	const fixture = boot( {
		wp: translatingWp(),
		cfg: { tokenLabels: { title: 'Title' } },
		roots: [ editorRoot ]
	} );
	fixture.init();

	assert.equal( list.children[ 0 ].getAttribute( 'aria-label' ), 'rankkernel:Insert token title (Title)' );
} );

test( 'the classic metadata editor renders the localised select and change image labels', () => {
	const ogSelect = fakeNode();
	const ogRow = fakeNode( { attrs: { 'data-rk-image-row': 'og' } } );
	ogRow.querySelector = ( selector ) => ( {
		'img': fakeNode(),
		'[data-rankkernel-remove-image]': fakeNode(),
		'[data-rankkernel-select-image]': ogSelect
	}[ selector ] || null );

	const twSelect = fakeNode();
	const twRow = fakeNode( { attrs: { 'data-rk-image-row': 'twitter' } } );
	twRow.querySelector = ( selector ) => ( {
		'img': fakeNode(),
		'[data-rankkernel-remove-image]': fakeNode(),
		'[data-rankkernel-select-image]': twSelect
	}[ selector ] || null );

	const editorRoot = fakeRoot( {
		query: { '[name="rankkernel_meta_og[image]"]': fakeNode( { value: 'https://example.com/og.jpg' } ) },
		queryAll: { '[data-rk-image-row]': [ ogRow, twRow ] }
	} );

	const fixture = boot( {
		cfg: { strings: { changeImage: 'LOCAL CHANGE', selectImage: 'LOCAL SELECT' } },
		roots: [ editorRoot ]
	} );
	fixture.init();

	assert.equal( ogSelect.textContent, 'LOCAL CHANGE' );
	assert.equal( twSelect.textContent, 'LOCAL SELECT' );
} );

test( 'every user visible classic metadata editor literal is a direct translation argument', () => {
	const code = source();
	const literals = [];
	const pattern = /__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'rankkernel'\s*\)/g;
	let match;

	while ( ( match = pattern.exec( code ) ) !== null ) {
		literals.push( match[ 1 ] );
	}

	[
		'Long',
		'Too long',
		'OK',
		'No tokens available for this post type.',
		'Insert token',
		'chars only',
		'chars',
		'Inherited from the template',
		'Custom override active',
		'Untitled',
		'Small image card',
		'Large image card',
		'Change image',
		'Select image',
		'Custom',
		'Default',
		'Enter a full URL starting with http:// or https://. Invalid input is ignored on save.',
		'Disabled for this post. No structured data prints.',
		'Custom type: ',
		'Status: ',
		'Noindex is on: this post is hidden from search results.',
		'Select preview image',
		'Use this image'
	].forEach( ( literal ) => {
		assert.ok(
			literals.includes( literal ),
			JSON.stringify( literal ) + ' must be a literal translation argument'
		);
	} );

	assert.doesNotMatch( code, /str\(\s*'[a-zA-Z]+',\s*'/ );

	assert.match(
		code,
		/var __ = window\.wp && window\.wp\.i18n && window\.wp\.i18n\.__ \? window\.wp\.i18n\.__ : function \( text \) \{ return text; \};/
	);
} );

test( 'the media select chain is guarded and falls back to the URL field', () => {
	const code = source();

	assert.ok(
		code.includes( "attachment = frame.state().get( 'selection' ).first();" ),
		'the select handler must still read the first selection'
	);
	assert.ok(
		code.includes( '} catch ( error ) {\n\t\t\t\tattachment = null;\n\t\t\t}' ),
		'a failing media frame must not escape the select callback'
	);
} );
