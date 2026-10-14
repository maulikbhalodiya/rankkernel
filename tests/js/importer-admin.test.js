'use strict';

/**
 * Importer admin JS unit tests.
 *
 * Verifies:
 *  - init is a no-op without the importer root (no fetch, no throw).
 *  - Preview click POSTs mode to the preview endpoint and renders rows.
 *  - The mode radio switches the mode sent with preview and run.
 *  - Run loops chunks per kind until done, moving progress and announcing.
 *  - Checked exclude boxes are collected into run excludes.
 *  - A failed request shows the error state and announces.
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

function el( tagName, attrs = {}, text = '' ) {
	const listeners = {};
	const attrMap = Object.assign( {}, attrs );
	delete attrMap.className;

	const node = {
		tagName,
		id: attrs.id || '',
		value: attrs.value || '',
		checked: !! attrs.checked,
		disabled: !! attrs.disabled,
		textContent: text,
		innerHTML: '',
		children: [],
		parentNode: null,
		style: { display: '' },
		appendChild( child ) { child.parentNode = node; node.children.push( child ); return child; },
		querySelector( selector ) { return find( node, selector ); },
		querySelectorAll( selector ) { return findAll( node, selector ); },
		addEventListener( type, handler ) { ( listeners[ type ] = listeners[ type ] || [] ).push( handler ); },
		dispatch( type, event = {} ) {
			( listeners[ type ] || [] ).forEach( ( h ) => h( event ) );
		},
		getAttribute( name ) { return Object.prototype.hasOwnProperty.call( attrMap, name ) ? attrMap[ name ] : null; },
		setAttribute( name, val ) { attrMap[ name ] = String( val ); }
	};
	return node;
}

function matches( node, selector ) {
	let pseudo = null;
	const hash = selector.indexOf( ':' );
	if ( hash !== -1 ) {
		pseudo = selector.slice( hash + 1 );
		selector = selector.slice( 0, hash );
	}
	let m = selector.match( /^(\w+)?(?:\[([\w:-]+)(?:="([^"]+)")?\])?$/ );
	if ( ! m ) { return false; }
	const [ , tag, attr, val ] = m;
	if ( tag && tag !== node.tagName ) { return false; }
	if ( attr && ( val === undefined ? node.getAttribute( attr ) === null : node.getAttribute( attr ) !== val ) ) { return false; }
	if ( pseudo && ( 'checked' !== pseudo || ! node.checked ) ) { return false; }
	return true;
}

function find( node, selector ) {
	let found = null;
	( function walk( n ) {
		if ( found ) { return; }
		n.children.forEach( ( c ) => {
			if ( found ) { return; }
			if ( matches( c, selector ) ) { found = c; return; }
			walk( c );
		} );
	} )( node );
	return found;
}

function findAll( node, selector ) {
	const out = [];
	( function walk( n ) {
		n.children.forEach( ( c ) => {
			if ( matches( c, selector ) ) { out.push( c ); }
			walk( c );
		} );
	} )( node );
	return out;
}

function importerDom() {
	const body = el( 'div' );
	const rootEl = el( 'div', { 'data-rk-importer-root': '1' } );
	const previewBtn = el( 'button', { 'data-rk-importer-preview': 'yoast' }, 'Preview' );
	const modeSkip = el( 'input', { name: 'rk-importer-mode', value: 'skip', checked: true } );
	const modeOverwrite = el( 'input', { name: 'rk-importer-mode', value: 'overwrite' } );
	const rows = el( 'tbody', { 'data-rk-importer-rows': '1' } );
	const runBtn = el( 'button', { 'data-rk-importer-run': '1' }, 'Run' );
	const progress = el( 'div', { 'data-rk-importer-progress': '1' } );
	const bar = el( 'div', { 'data-rk-importer-bar': '1' } );
	const announce = el( 'div', { 'data-rk-importer-announce': '1' } );
	const errorBox = el( 'div', { 'data-rk-importer-error': '1' } );
	progress.appendChild( bar );
	rootEl.appendChild( previewBtn );
	rootEl.appendChild( modeSkip );
	rootEl.appendChild( modeOverwrite );
	rootEl.appendChild( rows );
	rootEl.appendChild( runBtn );
	rootEl.appendChild( progress );
	rootEl.appendChild( announce );
	rootEl.appendChild( errorBox );
	body.appendChild( rootEl );

	const document = {
		readyState: 'complete',
		querySelector( selector ) { return find( body, selector ); },
		querySelectorAll( selector ) { return findAll( body, selector ); },
		addEventListener() {}
	};

	return { document, rootEl, previewBtn, modeSkip, modeOverwrite, rows, runBtn, bar, announce, errorBox };
}

function runImporter( sandbox ) {
	vm.runInNewContext( source( 'importer-admin.js' ), sandbox );
}

function sandboxFor( dom, fetchImpl, spoken ) {
	const sandbox = {
		document: dom.document,
		wp: { a11y: { speak( text ) { spoken.push( text ); } }, i18n: { __: ( t ) => t } },
		fetch: fetchImpl
	};
	sandbox.window = Object.assign( sandbox, {
		rankkernelImporter: { restBase: '/wp-json/rankkernel/v1/import', restNonce: 'abc' },
		fetch: sandbox.fetch
	} );
	return sandbox;
}

function okJson( body ) {
	return Promise.resolve( { ok: true, json() { return Promise.resolve( body ); } } );
}

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

test( 'init without the root does nothing', async () => {
	const body = el( 'div' );
	const document = {
		readyState: 'complete',
		querySelector() { return null; },
		querySelectorAll() { return []; },
		addEventListener() {}
	};
	let fetched = 0;
	const sandbox = {
		document,
		wp: { a11y: { speak() {} }, i18n: { __: ( t ) => t } },
		fetch() { fetched += 1; return okJson( {} ); }
	};
	sandbox.window = Object.assign( sandbox, { rankkernelImporter: {}, fetch: sandbox.fetch } );
	runImporter( sandbox );
	await tick();
	assert.equal( fetched, 0 );
} );

test( 'preview click posts mode and renders rows', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, opts } );
		return okJson( {
			source: 'yoast', mode: 'skip', totals: { posts: 1 },
			rows: [ { kind: 'post', source: 'yoast', refId: '7', label: 'Hello', fields: { title: 'Hi' }, conflicts: [], warnings: [], skippedReason: '', slot: '', recommended: false, bulk: null } ],
			skipped: []
		} );
	}, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await tick();
	await tick();
	assert.equal( calls.length, 1 );
	assert.equal( calls[ 0 ].url, '/wp-json/rankkernel/v1/import/yoast/preview' );
	assert.deepEqual( JSON.parse( calls[ 0 ].opts.body ), { mode: 'skip' } );
	assert.match( dom.rows.innerHTML, /Hello/ );
	assert.match( dom.rows.innerHTML, /data-rk-importer-exclude/ );
	assert.ok( spoken.some( ( t ) => /1/.test( t ) ) );
} );

test( 'mode radio switches the mode sent', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, opts } );
		return okJson( { source: 'yoast', mode: 'overwrite', totals: {}, rows: [], skipped: [] } );
	}, spoken );
	runImporter( sandbox );
	dom.modeSkip.checked = false;
	dom.modeOverwrite.checked = true;
	dom.previewBtn.dispatch( 'click' );
	await tick();
	await tick();
	assert.deepEqual( JSON.parse( calls[ 0 ].opts.body ), { mode: 'overwrite' } );
} );

test( 'run loops chunks per kind until done', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, opts } );
		const body = JSON.parse( opts.body );
		const last = 'bulk' === body.kind;
		return okJson( { applied: 2, skipped: 0, failed: 0, done: last || body.offset > 0, total: 10 } );
	}, spoken );
	runImporter( sandbox );
	dom.runBtn.dispatch( 'click' );
	for ( let i = 0; i < 12; i++ ) { await tick(); }
	const kinds = calls.filter( ( c ) => /\/run$/.test( c.url ) ).map( ( c ) => JSON.parse( c.opts.body ).kind + '@' + JSON.parse( c.opts.body ).offset );
	assert.deepEqual( kinds, [ 'posts@0', 'posts@100', 'terms@0', 'terms@100', 'sitewide@0', 'sitewide@100', 'redirects@0', 'redirects@100', 'bulk@0' ] );
	assert.ok( dom.bar.getAttribute( 'style' ).includes( '100%' ) );
	assert.ok( spoken.some( ( t ) => /complete/i.test( t ) ) );
} );

test( 'checked excludes are sent with run', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const box = el( 'input', { 'data-rk-importer-exclude': '1', value: 'post:7', checked: true } );
	dom.rows.appendChild( box );
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, opts } );
		const body = JSON.parse( opts.body );
		return okJson( { applied: 0, skipped: 0, failed: 0, done: true, total: 0 } );
	}, spoken );
	runImporter( sandbox );
	dom.runBtn.dispatch( 'click' );
	for ( let i = 0; i < 12; i++ ) { await tick(); }
	const first = calls.find( ( c ) => /\/run$/.test( c.url ) );
	assert.deepEqual( JSON.parse( first.opts.body ).excludes, [ 'post:7' ] );
} );

test( 'failed request shows the error state', async () => {
	const dom = importerDom();
	const spoken = [];
	const sandbox = sandboxFor( dom, () => Promise.resolve( { ok: false, status: 500 } ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await tick();
	await tick();
	assert.match( dom.errorBox.innerHTML, /failed|error/i );
	assert.ok( spoken.length > 0 );
} );
