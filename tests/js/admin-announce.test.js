'use strict';

/**
 * Announcement wiring in the three admin screens.
 *
 * All three files are classic DOM scripts, so each one runs here in a vm
 * sandbox with a minimal fake document. The tests pin the spoken text for the
 * bulk select toggle, the settings section load and save actions, the best
 * effort fallbacks, the plugin scoped helper names and the literal call sites
 * the WordPress string extractor depends on.
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

function run( file, document, wp, extra ) {
	const sandbox = Object.assign( { document, wp, confirm: () => true }, extra || {} );

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

function fakeClassList( initial ) {
	const values = new Set( initial || [] );

	return {
		add( value ) {
			values.add( value );
		},
		remove( value ) {
			values.delete( value );
		},
		toggle( value, force ) {
			if ( force ) {
				values.add( value );
			} else {
				values.delete( value );
			}
		},
		contains( value ) {
			return values.has( value );
		}
	};
}

function successReply( html ) {
	return () => Promise.resolve( { ok: true, text: () => Promise.resolve( html ) } );
}

function errorReply() {
	return () => Promise.reject( new Error( 'network error' ) );
}

function httpErrorReply() {
	return () => Promise.resolve( { ok: false, text: () => Promise.resolve( '' ) } );
}

function settingsFixture() {
	const fetchCalls = [];
	const replies = [];
	const formData = { instance: null };
	const form = fakeNode( {
		submitted: false,
		submit() {
			this.submitted = true;
		}
	} );
	const body = fakeNode( {
		classList: fakeClassList(),
		closest( selector ) {
			return 'form' === selector ? form : null;
		}
	} );
	const nav = fakeNode( {
		href: 'https://example.test/wp-admin/admin.php?page=rankkernel-general&section=robots',
		classList: fakeClassList( [ 'is-active' ] ),
		getAttribute( name ) {
			return 'href' === name ? this.href : null;
		},
		setAttribute() {},
		removeAttribute() {},
		closest( selector ) {
			return '.rk-settings-nav a' === selector ? this : null;
		}
	} );
	const shell = fakeNode( {
		querySelector( selector ) {
			if ( '.rk-settings-body' === selector ) {
				return body;
			}

			if ( '.rk-settings-nav a.is-active' === selector && nav.classList.contains( 'is-active' ) ) {
				return nav;
			}

			return null;
		},
		querySelectorAll( selector ) {
			return '.rk-settings-nav a' === selector ? [ nav ] : [];
		}
	} );

	function actionButton( name ) {
		return fakeNode( {
			name,
			closest( selector ) {
				if ( '[name="' + name + '"]' === selector ) {
					return this;
				}

				return 'button' === selector ? this : null;
			}
		} );
	}

	const save = actionButton( 'rk_robots_save' );
	const reset = actionButton( 'rk_robots_reset' );
	const llmsReset = actionButton( 'rk_llms_reset' );
	const document = fakeDocument( {}, {} );

	document.readyState = 'loading';
	document.querySelector = ( selector ) => ( '.rk-settings' === selector ? shell : null );

	const location = { href: 'https://example.test/wp-admin/admin.php?page=rankkernel-general&section=robots' };
	const globals = {
		URL,
		location,
		history: { pushState() {} },
		addEventListener() {},
		FormData: class {
			constructor() {
				this.entries = {};
				formData.instance = this;
			}

			set( key, value ) {
				this.entries[ key ] = value;
			}
		},
		fetch( url, init ) {
			fetchCalls.push( { url, init } );

			const reply = replies.shift();

			return reply ? reply() : Promise.reject( new Error( 'fetch not stubbed' ) );
		}
	};

	function boot( wp ) {
		const sandbox = run( 'settings-admin.js', document, wp, globals );

		document.handlers.forEach( ( handler ) => handler() );

		return sandbox;
	}

	function click( node ) {
		shell.listeners.click( { target: node, preventDefault() {} } );
	}

	function flush() {
		return new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}

	return {
		boot,
		click,
		flush,
		document,
		form,
		formData,
		location,
		nav,
		save,
		reset,
		llmsReset,
		replies,
		fetchCalls
	};
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

test( 'settings-admin announces a section load under a private prefixed helper', async () => {
	const spoken = [];
	const fixture = settingsFixture();
	const sandbox = fixture.boot( translatingWp( spoken ) );

	assert.equal( typeof sandbox.rankkernelAnnounce, 'undefined' );
	assert.equal( typeof sandbox.rkAnnounce, 'undefined' );
	assert.match( source( 'settings-admin.js' ), /function rankkernelAnnounce\( text \) \{/ );

	fixture.replies.push( successReply( '<div class="rk-settings-body">fresh</div>' ) );
	fixture.click( fixture.nav );
	await fixture.flush();

	assert.equal( fixture.fetchCalls.length, 1 );
	assert.deepEqual( spoken, [ 'rankkernel:Settings section loaded.' ] );
} );

test( 'settings-admin announces Settings updated for a save and Settings reset for a reset', async () => {
	const spoken = [];
	const fixture = settingsFixture();
	fixture.boot( translatingWp( spoken ) );

	fixture.replies.push( successReply( '<div></div>' ) );
	fixture.click( fixture.save );
	await fixture.flush();

	assert.equal( fixture.formData.instance.entries.rk_robots_save, '1' );
	assert.deepEqual( spoken, [ 'rankkernel:Settings updated.' ] );

	fixture.replies.push( successReply( '<div></div>' ) );
	fixture.click( fixture.reset );
	await fixture.flush();

	assert.equal( fixture.formData.instance.entries.rk_robots_reset, '1' );
	assert.deepEqual( spoken, [ 'rankkernel:Settings updated.', 'rankkernel:Settings reset.' ] );

	fixture.replies.push( successReply( '<div></div>' ) );
	fixture.click( fixture.llmsReset );
	await fixture.flush();

	assert.deepEqual( spoken, [
		'rankkernel:Settings updated.',
		'rankkernel:Settings reset.',
		'rankkernel:Settings reset.'
	] );
} );

test( 'settings-admin speaks the raw string when wp.i18n is absent', async () => {
	const spoken = [];
	const fixture = settingsFixture();
	fixture.boot( speakingWp( spoken ) );

	fixture.replies.push( successReply( '<div></div>' ) );
	fixture.click( fixture.save );
	await fixture.flush();

	fixture.replies.push( successReply( '<div></div>' ) );
	fixture.click( fixture.reset );
	await fixture.flush();

	assert.deepEqual( spoken, [ 'Settings updated.', 'Settings reset.' ] );
} );

test( 'settings-admin stays silent on a failed request and keeps the full page fallbacks', async () => {
	const spoken = [];
	const fixture = settingsFixture();
	fixture.boot( translatingWp( spoken ) );

	fixture.replies.push( errorReply() );
	fixture.click( fixture.nav );
	await fixture.flush();

	assert.equal( fixture.location.href, fixture.nav.href );
	assert.deepEqual( spoken, [] );

	fixture.replies.push( errorReply() );
	fixture.click( fixture.save );
	await fixture.flush();

	assert.equal( fixture.form.submitted, true );
	assert.deepEqual( spoken, [] );

	fixture.replies.push( httpErrorReply() );
	fixture.click( fixture.reset );
	await fixture.flush();

	assert.deepEqual( spoken, [] );
} );

test( 'settings-admin stays silent without wp.a11y and does not throw when wp is absent', async () => {
	const spoken = [];
	const fixture = settingsFixture();
	fixture.boot( { i18n: { __: ( text, domain ) => domain + ':' + text } } );

	fixture.replies.push( successReply( '<div></div>' ) );
	fixture.click( fixture.save );
	await fixture.flush();

	assert.deepEqual( spoken, [] );
	assert.equal( fixture.form.submitted, false );

	const bare = settingsFixture();
	bare.boot( undefined );
	bare.replies.push( successReply( '<div></div>' ) );

	assert.doesNotThrow( () => {
		bare.click( bare.save );
	} );

	await bare.flush();

	assert.equal( bare.form.submitted, false );
	assert.deepEqual( spoken, [] );
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
		],
		'settings-admin.js': [
			'Settings section loaded.',
			'Settings updated.',
			'Settings reset.'
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
