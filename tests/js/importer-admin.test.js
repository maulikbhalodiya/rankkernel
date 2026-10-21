'use strict';

/**
 * Importer admin JS unit tests.
 *
 * Verifies:
 *  - init is a no-op without the importer root (no fetch, no throw).
 *  - Preview click loads every kind and renders sections with pagers.
 *  - Exclusions survive paging via the identity set.
 *  - Sitewide radio picks flow into run choices.
 *  - Bulk runs only for ticked rules with the rule identity.
 *  - Run shows a confirmation summary first and writes nothing until confirmed.
 *  - Changing selections resets the confirmation.
 *  - A second run click while running is ignored.
 *  - Progress never moves backward and ends at 100 percent.
 *  - A mid run failure stops the chain with a partial report.
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
		insertBefore( child, ref ) { child.parentNode = node; const at = ref ? node.children.indexOf( ref ) : -1; if ( at === -1 ) { node.children.push( child ); } else { node.children.splice( at, 0, child ); } return child; },
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

function fakeTarget( attrs, value, checked ) {
	return {
		value: value || '',
		checked: !! checked,
		getAttribute( name ) { return Object.prototype.hasOwnProperty.call( attrs, name ) ? attrs[ name ] : null; }
	};
}

function matches( node, selector ) {
	if ( selector.startsWith( '#' ) ) {
		return node.getAttribute( 'id' ) === selector.slice( 1 );
	}
	if ( selector.startsWith( '.' ) ) {
		const classes = ( node.getAttribute( 'class' ) || '' ).split( /\s+/ );
		return classes.indexOf( selector.slice( 1 ) ) !== -1;
	}
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
	const steps = el( 'ol', { class: 'rk-importer-steps' } );
	const stepDetect = el( 'li', { id: 'rk-importer-step-detect' }, 'Detect' );
	const stepPreview = el( 'li', { id: 'rk-importer-step-preview' }, 'Preview' );
	const stepRun = el( 'li', { id: 'rk-importer-step-run' }, 'Run' );
	steps.appendChild( stepDetect );
	steps.appendChild( stepPreview );
	steps.appendChild( stepRun );
	rootEl.appendChild( steps );
	const previewBtn = el( 'button', { 'data-rk-importer-preview': 'yoast' }, 'Preview' );
	const modeSkip = el( 'input', { name: 'rk-importer-mode', value: 'skip', checked: true } );
	const modeOverwrite = el( 'input', { name: 'rk-importer-mode', value: 'overwrite' } );
	const sections = {};
	[ 'posts', 'terms', 'sitewide', 'redirects', 'bulk', 'skipped' ].forEach( ( kind ) => {
		sections[ kind ] = el( 'div', { 'data-rk-importer-section': kind } );
		rootEl.appendChild( sections[ kind ] );
	} );
	const confirm = el( 'div', { 'data-rk-importer-confirm': '1' } );
	const kindNav = el( 'ol', { 'data-rk-importer-kind-nav': '1', 'data-rk-importer-kind-stepper': '1' } );
	rootEl.appendChild( kindNav );
	const runBtn = el( 'button', { 'data-rk-importer-run': '1' }, 'Run import' );
	const rescanBtn = el( 'button', { 'data-rk-importer-rescan': '1' }, 'Rescan Database' );
	const progress = el( 'div', { 'data-rk-importer-progress': '1' } );
	const bar = el( 'div', { 'data-rk-importer-bar': '1' } );
	const announce = el( 'div', { 'data-rk-importer-announce': '1' } );
	const errorBox = el( 'div', { 'data-rk-importer-error': '1' } );
	progress.appendChild( bar );
	rootEl.appendChild( previewBtn );
	rootEl.appendChild( modeSkip );
	rootEl.appendChild( modeOverwrite );
	rootEl.appendChild( confirm );
	rootEl.appendChild( runBtn );
	rootEl.appendChild( rescanBtn );
	rootEl.appendChild( progress );
	rootEl.appendChild( announce );
	rootEl.appendChild( errorBox );
	body.appendChild( rootEl );

	const document = {
		readyState: 'complete',
		querySelector( selector ) { return find( body, selector ); },
		querySelectorAll( selector ) { return findAll( body, selector ); },
		createElement( tag ) { return el( tag ); },
		addEventListener() {}
	};

	return { document, rootEl, previewBtn, modeSkip, modeOverwrite, sections, confirm, runBtn, rescanBtn, bar, announce, errorBox, stepDetect, stepPreview, stepRun };
}

function runImporter( sandbox ) {
	vm.runInNewContext( source( 'importer-admin.js' ), sandbox );
}

function sandboxFor( dom, fetchImpl, spoken, reloaded ) {
	const sandbox = {
		document: dom.document,
		wp: { a11y: { speak( text ) { spoken.push( text ); } }, i18n: { __: ( t ) => t } },
		fetch: fetchImpl,
		setTimeout,
		clearTimeout
	};
	sandbox.window = Object.assign( sandbox, {
		rankkernelImporter: { restBase: '/wp-json/rankkernel/v1/import', restNonce: 'abc' },
		fetch: sandbox.fetch,
		location: { reload() { if ( reloaded ) { reloaded.count += 1; } } }
	} );
	return sandbox;
}

function okJson( body ) {
	return Promise.resolve( { ok: true, json() { return Promise.resolve( body ); } } );
}

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
const ticks = async ( n ) => { for ( let i = 0; i < n; i++ ) { await tick(); } };

function postRow( id, title ) {
	return { kind: 'post', source: 'yoast', refId: id, identity: 'post:' + id, label: 'Post ' + id, fields: { title }, conflicts: {}, warnings: [], skippedReason: '', slot: '', recommended: false, bulk: false };
}

function previewBody( kind, rows, extra = {} ) {
	return Object.assign( {
		source: 'yoast', mode: 'skip', kind, offset: 0, limit: 50,
		totals: { posts: 3, terms: 0, settings: true, redirects: 1 },
		rows, skipped: [], hasMore: false, kindTotal: rows.length
	}, extra );
}

function previewFetch( calls ) {
	return ( url, opts ) => {
		const body = JSON.parse( opts.body );
		calls.push( { url, body } );
		if ( /\/preview$/.test( url ) ) {
			if ( 'posts' === body.kind ) {
				const all = [ postRow( 7, 'Seven' ), postRow( 8, 'Eight' ), postRow( 9, 'Nine' ) ];
				const rows = all.slice( body.offset || 0, ( body.offset || 0 ) + 2 );
				return okJson( previewBody( 'posts', rows, { hasMore: ( body.offset || 0 ) + rows.length < 3, kindTotal: 3 } ) );
			}
			if ( 'sitewide' === body.kind ) {
				return okJson( previewBody( 'sitewide', [
					{ kind: 'sitewide', source: 'yoast', refId: 0, identity: 'sitewide:title_template:post', label: 'title_template from post', fields: { candidate_source: 'post', value: 'T' }, conflicts: {}, warnings: [], skippedReason: '', slot: 'title_template', recommended: true, bulk: false },
					{ kind: 'sitewide', source: 'yoast', refId: 0, identity: 'sitewide:title_template:page', label: 'title_template from page', fields: { candidate_source: 'page', value: 'P' }, conflicts: {}, warnings: [], skippedReason: '', slot: 'title_template', recommended: false, bulk: false }
				] ) );
			}
			if ( 'bulk' === body.kind ) {
				return okJson( previewBody( 'bulk', [
					{ kind: 'sitewide', source: 'yoast', refId: 0, identity: 'bulk:post:post', label: 'All post posts: noindex', fields: { rule_kind: 'post', rule_type: 'post' }, conflicts: {}, warnings: [], skippedReason: '', slot: '', recommended: false, bulk: true, affected: 12 }
				] ) );
			}
			return okJson( previewBody( body.kind, [] ) );
		}
		return okJson( { applied: 1, skipped: 0, failed: 0, done: true, total: 1 } );
	};
}

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

test( 'preview loads every kind and renders sections with pagers', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	const kinds = calls.filter( ( c ) => /\/preview$/.test( c.url ) ).map( ( c ) => c.body.kind );
	assert.deepEqual( kinds, [ 'posts', 'terms', 'redirects', 'sitewide', 'bulk' ] );
	assert.match( dom.sections.posts.innerHTML, /Post 7/ );
	assert.match( dom.sections.posts.innerHTML, /rows, scroll to review/ );
	assert.match( dom.sections.sitewide.innerHTML, /title_template from post/ );
	assert.match( dom.sections.bulk.innerHTML, /bulk:post:post/ );
	assert.ok( spoken.some( ( t ) => /Preview ready/.test( t ) ) );
} );

test( 'exclusions survive paging', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-exclude': '' }, 'post:7', true ) } );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	const firstRun = calls.find( ( c ) => /\/run$/.test( c.url ) );
	assert.ok( firstRun.body.excludes.includes( 'post:7' ) );
} );

test( 'sitewide radio pick flows into run choices', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-slot': 'title_template' }, 'sitewide:title_template:page', true ) } );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	const sitewide = calls.find( ( c ) => /\/run$/.test( c.url ) && 'sitewide' === c.body.kind );
	assert.deepEqual( sitewide.body.choices, { title_template: 'sitewide:title_template:page' } );
} );

test( 'bulk runs only for ticked rules', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.equal( calls.filter( ( c ) => /\/run$/.test( c.url ) && 'bulk' === c.body.kind ).length, 0 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-rule': '' }, 'bulk:post:post', true ) } );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	const bulk = calls.filter( ( c ) => /\/run$/.test( c.url ) && 'bulk' === c.body.kind );
	assert.equal( bulk.length, 1 );
	assert.equal( bulk[ 0 ].body.rule, 'bulk:post:post' );
} );

test( 'run shows a summary first and writes nothing until confirmed', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.equal( calls.filter( ( c ) => /\/run$/.test( c.url ) ).length, 0 );
	assert.match( dom.confirm.innerHTML, /Confirm import/ );
	assert.match( dom.confirm.innerHTML, /Conflict mode/ );
	assert.match( dom.confirm.innerHTML, /skip/ );
	assert.match( dom.confirm.innerHTML, /Bulk rules chosen/ );
	assert.match( dom.runBtn.textContent, /Confirm/ );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.ok( calls.some( ( c ) => /\/run$/.test( c.url ) ) );
	assert.match( dom.confirm.innerHTML, /Import complete/ );
} );

test( 'changing selections resets the confirmation', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.match( dom.confirm.innerHTML, /Confirm import/ );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-exclude': '' }, 'post:8', true ) } );
	assert.equal( dom.confirm.innerHTML, '' );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.equal( calls.filter( ( c ) => /\/run$/.test( c.url ) ).length, 0 );
	assert.match( dom.confirm.innerHTML, /Confirm import/ );
} );

test( 'a second run click while running is ignored', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	let release;
	const gate = new Promise( ( resolve ) => { release = resolve; } );
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, body: JSON.parse( opts.body ) } );
		if ( /\/run$/.test( url ) ) {
			return gate.then( () => okJson( { applied: 0, skipped: 0, failed: 0, done: true, total: 0 } ) ).then( ( r ) => r );
		}
		return previewFetch( [] )( url, opts );
	}, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await tick();
	const before = calls.filter( ( c ) => /\/run$/.test( c.url ) ).length;
	dom.runBtn.dispatch( 'click' );
	await tick();
	const after = calls.filter( ( c ) => /\/run$/.test( c.url ) ).length;
	assert.equal( before, after );
	release();
	await ticks( 12 );
} );

test( 'progress never moves backward and ends at 100 percent', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const widths = [];
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, body: JSON.parse( opts.body ) } );
		if ( /\/run$/.test( url ) ) {
			const body = JSON.parse( opts.body );
			const totals = { posts: 200, terms: 0, sitewide: 1, redirects: 1, bulk: 0 };
			const total = totals[ body.kind ] || 0;
			const atEnd = body.offset + 100 >= total;
			return okJson( { applied: atEnd ? total : 100, skipped: 0, failed: 0, done: atEnd, total } );
		}
		if ( /\/preview$/.test( url ) ) {
			const body = JSON.parse( opts.body );
			if ( 'posts' === body.kind ) {
				return okJson( previewBody( 'posts', [], { kindTotal: 200, hasMore: true } ) );
			}
			return okJson( previewBody( body.kind, [] ) );
		}
		return okJson( {} );
	}, spoken );
	runImporter( sandbox );
	const origSet = dom.bar.setAttribute.bind( dom.bar );
	dom.bar.setAttribute = ( name, val ) => {
		if ( 'style' === name ) {
			const m = /width: (\d+)%/.exec( val );
			if ( m ) { widths.push( parseInt( m[ 1 ], 10 ) ); }
		}
		return origSet( name, val );
	};
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 20 );
	for ( let i = 1; i < widths.length; i++ ) {
		assert.ok( widths[ i ] >= widths[ i - 1 ], 'progress moved backward at index ' + i );
	}
	assert.equal( widths[ widths.length - 1 ], 100 );
} );

test( 'a mid run failure stops the chain with a partial report', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, body: JSON.parse( opts.body ) } );
		if ( /\/run$/.test( url ) ) {
			const body = JSON.parse( opts.body );
			if ( 'posts' === body.kind && body.offset > 0 ) {
				return Promise.resolve( { ok: false, status: 500 } );
			}
			return okJson( { applied: 1, skipped: 0, failed: 0, done: body.offset > 0, total: 200 } );
		}
		if ( /\/preview$/.test( url ) ) {
			const body = JSON.parse( opts.body );
			return okJson( previewBody( body.kind, 'posts' === body.kind ? [ postRow( 7, 'Seven' ) ] : [], { kindTotal: 'posts' === body.kind ? 200 : 0, hasMore: false } ) );
		}
		return okJson( {} );
	}, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.errorBox.innerHTML, /failed|stopped/i );
	assert.ok( spoken.length > 0 );
	assert.equal( calls.filter( ( c ) => /\/run$/.test( c.url ) && 'terms' === c.body.kind ).length, 0 );
	assert.match( dom.confirm.innerHTML, /stopped/i );
	assert.equal( dom.runBtn.disabled, false );
} );

test( 'steps indicator classes update on transitions', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	assert.equal( dom.stepDetect.getAttribute( 'class' ), null );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.equal( dom.stepDetect.getAttribute( 'class' ), 'is-complete' );
	assert.equal( dom.stepPreview.getAttribute( 'class' ), 'is-current' );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.equal( dom.stepPreview.getAttribute( 'class' ), 'is-complete' );
	assert.equal( dom.stepRun.getAttribute( 'class' ), 'is-current' );
} );

test( 'rescan button reloads the page for a fresh detect', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const reloaded = { count: 0 };
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken, reloaded );
	runImporter( sandbox );
	dom.rescanBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.equal( reloaded.count, 1 );
	assert.equal( calls.filter( ( c ) => /\/preview$|\/run$/.test( c.url ) ).length, 0 );
} );

test( 'preview enables the run button', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.runBtn.disabled = true;
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.equal( dom.runBtn.disabled, false );
} );

test( 'titled rows render escaped links opening in a new tab', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, ( url, opts ) => {
		calls.push( { url, opts } );
		const body = JSON.parse( opts.body );
		if ( /\/preview$/.test( url ) && 'posts' === body.kind ) {
			return okJson( previewBody( 'posts', [
				Object.assign( postRow( 7, 'Seven' ), { title: 'Live Seven', editUrl: 'https://example.test/wp-admin/post.php?post=7&action=edit' } ),
				Object.assign( postRow( 8, 'Eight' ), { title: '<script>alert(1)</script>', editUrl: 'https://example.test/?x="quoted"' } )
			], { kindTotal: 2 } ) );
		}
		return okJson( previewBody( body.kind, [] ) );
	}, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.sections.posts.innerHTML, /<a href="https:\/\/example\.test\/wp-admin\/post\.php\?post=7&amp;action=edit" target="_blank" rel="noopener">Live Seven<\/a>/ );
	assert.match( dom.sections.posts.innerHTML, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/ );
	assert.ok( ! /<script>alert/.test( dom.sections.posts.innerHTML ) );
	assert.match( dom.sections.posts.innerHTML, /Post 7/ );
} );

test( 'edge steps omit dead nav buttons', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	const controls = () => dom.rootEl.querySelector( '[data-rk-importer-wizard-nav]' ).innerHTML;
	assert.ok( controls().indexOf( 'Back' ) === -1 );
	assert.ok( controls().indexOf( 'Next' ) !== -1 );
	for ( let i = 0; i < 4; i++ ) {
		dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-next': '1' } ) } );
		await ticks( 2 );
	}
	assert.ok( controls().indexOf( 'Next' ) === -1 );
	assert.ok( controls().indexOf( 'Back' ) !== -1 );
	assert.ok( controls().indexOf( 'Skip kind' ) !== -1 );
} );

test( 'skipped kinds stay out of the run and show in confirm', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-skip': '1' } ) } );
	await ticks( 2 );
	for ( let i = 0; i < 4; i++ ) {
		dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-next': '1' } ) } );
		await ticks( 2 );
	}
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.match( dom.confirm.innerHTML, /will not import/ );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	const kinds = calls.filter( ( c ) => /\/run$/.test( c.url ) ).map( ( c ) => c.body.kind );
	assert.ok( kinds.length > 0 );
	assert.ok( kinds.indexOf( 'posts' ) === -1 );
} );

test( 'next after skip re-includes the kind', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-skip': '1' } ) } );
	await ticks( 2 );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-kind-jump': 'posts' } ) } );
	await ticks( 2 );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-next': '1' } ) } );
	await ticks( 2 );
	for ( let i = 0; i < 4; i++ ) {
		dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-next': '1' } ) } );
		await ticks( 2 );
	}
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	const kinds = calls.filter( ( c ) => /\/run$/.test( c.url ) ).map( ( c ) => c.body.kind );
	assert.ok( kinds.indexOf( 'posts' ) !== -1 );
} );

test( 'wizard chrome stays hidden until preview loads', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	assert.equal( dom.rootEl.querySelector( '[data-rk-importer-wizard-nav]' ), null );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-next': '1' } ) } );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-skip': '1' } ) } );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-kind-jump': 'terms' } ) } );
	await ticks( 2 );
	assert.equal( dom.rootEl.querySelector( '[data-rk-importer-wizard-nav]' ), null );
	assert.equal( dom.rootEl.querySelector( '[data-rk-importer-kind-nav]' ).innerHTML, '' );
	assert.ok( ! spoken.some( ( t ) => /reviewed|skipped/.test( t ) ) );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.ok( dom.rootEl.querySelector( '[data-rk-importer-wizard-nav]' ) );
	assert.match( dom.rootEl.querySelector( '[data-rk-importer-kind-nav]' ).innerHTML, /posts/ );
} );

test( 'wizard nav lists kinds with current first and none reviewed', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	const nav = dom.rootEl.querySelector( '[data-rk-importer-kind-nav]' );
	assert.ok( nav );
	assert.match( nav.innerHTML, /posts/ );
	assert.match( nav.innerHTML, /terms/ );
	assert.match( nav.innerHTML, /redirects/ );
	assert.match( nav.innerHTML, /sitewide/ );
	assert.match( nav.innerHTML, /bulk/ );
	assert.match( nav.innerHTML, /aria-current="step"/ );
	assert.match( nav.innerHTML, />current</ );
	assert.equal( ( nav.innerHTML.match( /data-reviewed="1"/g ) || [] ).length, 0 );
} );

test( 'next marks reviewed and advances, back returns keeping it', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	const next = () => dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-next': '1' } ) } );
	const back = () => dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-back': '1' } ) } );
	next();
	await ticks( 2 );
	let nav = dom.rootEl.querySelector( '[data-rk-importer-kind-nav]' );
	assert.ok( /data-rk-importer-kind="posts"[^>]*data-reviewed="1"|data-reviewed="1"[^>]*data-rk-importer-kind="posts"/.test( nav.innerHTML ) );
	back();
	await ticks( 2 );
	nav = dom.rootEl.querySelector( '[data-rk-importer-kind-nav]' );
	assert.ok( /data-rk-importer-kind="posts"[^>]*data-reviewed="1"|data-reviewed="1"[^>]*data-rk-importer-kind="posts"/.test( nav.innerHTML ) );
	assert.match( nav.innerHTML, /aria-current="step"/ );
} );

test( 'free jumping allows any kind at any time', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	const before = dom.rootEl.querySelector( '[data-rk-importer-kind-nav]' ).innerHTML;
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-kind-jump': 'bulk' } ) } );
	await ticks( 2 );
	const jumped = dom.rootEl.querySelector( '[data-rk-importer-kind-nav]' ).innerHTML;
	assert.notEqual( jumped, before );
} );

test( 'exclude-all checks loaded rows and clear removes them', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-exclude-all': '', checked: true }, '', true ) } );
	await ticks( 2 );
	assert.match( dom.sections.posts.innerHTML, /2 of 3 excluded/ );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	const firstRun = calls.find( ( c ) => /\/run$/.test( c.url ) );
	assert.ok( firstRun.body.excludes.includes( 'post:7' ) );
	assert.ok( firstRun.body.excludes.includes( 'post:8' ) );
} );

test( 'confirm lists per-kind decisions after walking all kinds', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	for ( let i = 0; i < 5; i++ ) {
		dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-next': '1' } ) } );
		await ticks( 2 );
	}
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.match( dom.confirm.innerHTML, /Confirm import/ );
	assert.match( dom.confirm.innerHTML, /posts/ );
	assert.match( dom.confirm.innerHTML, /Bulk rules chosen/ );
} );

test( 're-preview of same source keeps exclusions', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-exclude': '' }, 'post:7', true ) } );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.sections.posts.innerHTML, /checked/ );
	assert.match( dom.sections.posts.innerHTML, /1 of 3 excluded/ );
} );

test( 're-preview dropping a row clears its exclusion and announces', async () => {
	const dom = importerDom();
	const spoken = [];
	let previewCount = 0;
	const calls = [];
	const fetchImpl = ( url, opts ) => {
		const body = JSON.parse( opts.body );
		calls.push( { url, body } );
		if ( /\/preview$/.test( url ) ) {
			previewCount += 1;
			if ( 'posts' === body.kind ) {
				const rows = 1 === previewCount
					? [ postRow( 7, 'Seven' ), postRow( 8, 'Eight' ) ]
					: [ postRow( 8, 'Eight' ), postRow( 9, 'Nine' ) ];
				return okJson( previewBody( 'posts', rows, { hasMore: false, kindTotal: rows.length } ) );
			}
			return okJson( previewBody( body.kind, [] ) );
		}
		return okJson( { applied: 1, skipped: 0, failed: 0, done: true, total: 1 } );
	};
	const sandbox = sandboxFor( dom, fetchImpl, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-exclude': '' }, 'post:7', true ) } );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.ok( spoken.some( ( t ) => /cleared/.test( t ) ) );
	assert.match( dom.sections.posts.innerHTML, /0 of 2 excluded/ );
} );

test( 'switching source clears exclusions', async () => {
	const dom = importerDom();
	const spoken = [];
	const btn2 = el( 'button', { 'data-rk-importer-preview': 'other' }, 'Preview2' );
	dom.rootEl.appendChild( btn2 );
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-exclude': '' }, 'post:7', true ) } );
	btn2.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.sections.posts.innerHTML, /0 of 3 excluded/ );
} );

test( 'truncated kind keeps exclusions after re-preview', async () => {
	const dom = importerDom();
	const spoken = [];
	let previewCount = 0;
	const calls = [];
	const fetchImpl = ( url, opts ) => {
		const body = JSON.parse( opts.body );
		calls.push( { url, body } );
		if ( /\/preview$/.test( url ) && 'posts' === body.kind ) {
			previewCount += 1;
			const rows = 1 === previewCount ? [ postRow( 7, 'Seven' ) ] : [ postRow( 8, 'Eight' ) ];
			return okJson( previewBody( 'posts', rows, { hasMore: true, kindTotal: 3 } ) );
		}
		if ( /\/preview$/.test( url ) ) {
			return okJson( previewBody( body.kind, [] ) );
		}
		return okJson( { applied: 1, skipped: 0, failed: 0, done: true, total: 1 } );
	};
	const sandbox = sandboxFor( dom, fetchImpl, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.rootEl.dispatch( 'change', { target: fakeTarget( { 'data-rk-importer-exclude': '' }, 'post:7', true ) } );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.sections.posts.innerHTML, /1 of 3 excluded/ );
} );

test( 'search change invalidates confirmation', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.match( dom.confirm.innerHTML, /Confirm import/ );
	const input = el( 'input', { 'data-rk-importer-search': 'posts' }, '' );
	input.value = 'seven';
	dom.rootEl.dispatch( 'input', { target: input } );
	await new Promise( ( resolve ) => setTimeout( resolve, 350 ) );
	assert.equal( dom.confirm.innerHTML, '' );
	assert.equal( dom.runBtn.textContent, 'Run import' );
} );

test( 'skip kind after confirmation invalidates confirmation', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const sandbox = sandboxFor( dom, previewFetch( calls ), spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	assert.match( dom.confirm.innerHTML, /Confirm import/ );
	dom.rootEl.dispatch( 'click', { target: fakeTarget( { 'data-rk-importer-skip': '1' } ) } );
	await ticks( 2 );
	assert.equal( dom.confirm.innerHTML, '' );
	assert.equal( dom.runBtn.textContent, 'Run import' );
} );

test( 'run requests carry a shared run_id and release is called', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const fetchImpl = ( url, opts ) => {
		calls.push( { url, body: JSON.parse( opts.body ) } );
		if ( /\/release$/.test( url ) ) {
			return okJson( { released: true } );
		}
		if ( /\/preview$/.test( url ) ) {
			return previewFetch( [] )( url, opts );
		}
		return okJson( { applied: 1, skipped: 0, failed: 0, done: true, total: 1 } );
	};
	const sandbox = sandboxFor( dom, fetchImpl, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	const runs = calls.filter( ( c ) => /\/run$/.test( c.url ) );
	assert.ok( runs.length > 0 );
	const ids = new Set( runs.map( ( c ) => c.body.run_id ) );
	assert.equal( ids.size, 1 );
	const rid = [ ...ids ][ 0 ];
	assert.match( rid, /^[A-Za-z0-9_-]{8,64}$/ );
	const releases = calls.filter( ( c ) => /\/release$/.test( c.url ) );
	assert.equal( releases.length, 1 );
	assert.equal( releases[ 0 ].body.run_id, rid );
} );

test( '409 response stops the chain and shows busy message', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const fetchImpl = ( url, opts ) => {
		calls.push( { url, body: JSON.parse( opts.body ) } );
		if ( /\/run$/.test( url ) ) {
			return Promise.resolve( { ok: false, status: 409, json() { return Promise.resolve( { code: 'rankkernel_import_busy' } ); } } );
		}
		if ( /\/preview$/.test( url ) ) {
			return previewFetch( [] )( url, opts );
		}
		return okJson( {} );
	};
	const sandbox = sandboxFor( dom, fetchImpl, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.errorBox.innerHTML, /already active/i );
	assert.ok( spoken.some( ( t ) => /already active/i.test( t ) ) );
	assert.equal( calls.filter( ( c ) => /\/run$/.test( c.url ) && 'terms' === c.body.kind ).length, 0 );
} );

test( 'truncation note names the cap for the kind and for a search', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const fetchImpl = ( url, opts ) => {
		const body = JSON.parse( opts.body );
		calls.push( { url, body } );
		if ( /\/preview$/.test( url ) && 'posts' === body.kind ) {
			return okJson( previewBody( 'posts', [ postRow( 7, 'Seven' ) ], { hasMore: true, kindTotal: 9 } ) );
		}
		if ( /\/preview$/.test( url ) ) {
			return okJson( previewBody( body.kind, [] ) );
		}
		return okJson( { applied: 1, skipped: 0, failed: 0, done: true, total: 1 } );
	};
	const sandbox = sandboxFor( dom, fetchImpl, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.sections.posts.innerHTML, /Only the first 5000 rows are shown/ );
	const input = el( 'input', { 'data-rk-importer-search': 'posts' }, '' );
	input.value = 'seven';
	dom.rootEl.dispatch( 'input', { target: input } );
	await new Promise( ( resolve ) => setTimeout( resolve, 350 ) );
	assert.match( dom.sections.posts.innerHTML, /This search shows at most 5000 rows/ );
} );

test( 'a failed run reports the progress made before the failure', async () => {
	const dom = importerDom();
	const spoken = [];
	const calls = [];
	const fetchImpl = ( url, opts ) => {
		calls.push( { url, body: JSON.parse( opts.body ) } );
		if ( /\/run$/.test( url ) ) {
			return Promise.resolve( {
				ok: false,
				status: 500,
				json() {
					return Promise.resolve( {
						code: 'rankkernel_run_failed',
						message: 'The import lock was lost, so the run stopped before it wrote more records.',
						data: { status: 500, partial: { applied: 7, skipped: 2, failed: 1 } }
					} );
				}
			} );
		}
		if ( /\/preview$/.test( url ) ) {
			return previewFetch( [] )( url, opts );
		}
		return okJson( { applied: 1, skipped: 0, failed: 0, done: true, total: 1 } );
	};
	const sandbox = sandboxFor( dom, fetchImpl, spoken );
	runImporter( sandbox );
	dom.previewBtn.dispatch( 'click' );
	await ticks( 12 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 2 );
	dom.runBtn.dispatch( 'click' );
	await ticks( 12 );
	assert.match( dom.errorBox.innerHTML, /lock was lost/ );
	assert.match( dom.errorBox.innerHTML, /7 applied/ );
	assert.match( dom.errorBox.innerHTML, /2 skipped/ );
	assert.match( dom.errorBox.innerHTML, /1 failed/ );
} );
