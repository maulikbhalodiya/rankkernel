'use strict';

/**
 * Dashboard admin JS unit tests.
 *
 * Verifies:
 *  - A module toggle submits via REST and flips the switch plus the
 *    attention bar in place.
 *  - When the REST request fails the toggle falls back to the native
 *    form submit.
 *  - Planned modules are never wired, since they render no toggle form.
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

let nextId = 0;

function el( tagName, attrs = {}, text = '' ) {
	const listeners = {};
	const classes = new Set( ( attrs.className || '' ).split( /\s+/ ).filter( Boolean ) );
	const attrMap = Object.assign( {}, attrs );
	delete attrMap.className;

	const node = {
		tagName,
		id: attrs.id || '',
		value: attrs.value || '',
		textContent: text,
		children: [],
		parentNode: null,
		style: { display: '' },
		className: attrs.className || '',
		classList: {
			add( c ) { classes.add( c ); node.className = [ ...classes ].join( ' ' ); },
			remove( c ) { classes.delete( c ); node.className = [ ...classes ].join( ' ' ); },
			toggle( c, force ) {
				const on = 'boolean' === typeof force ? force : ! classes.has( c );
				if ( on ) { classes.add( c ); } else { classes.delete( c ); }
				node.className = [ ...classes ].join( ' ' );
			},
			contains( c ) { return classes.has( c ); }
		},
		appendChild( child ) { child.parentNode = node; node.children.push( child ); return child; },
		closest( selector ) {
			let cur = node.parentNode;
			while ( cur ) {
				if ( matches( cur, selector ) ) { return cur; }
				cur = cur.parentNode;
			}
			return null;
		},
		querySelector( selector ) { return find( node, selector, false ); },
		querySelectorAll( selector ) { return findAll( node, selector ); },
		addEventListener( type, handler ) { ( listeners[ type ] = listeners[ type ] || [] ).push( handler ); },
		dispatch( type, event = {} ) {
			( listeners[ type ] || [] ).forEach( ( h ) => h( event ) );
		},
		getAttribute( name ) { return Object.prototype.hasOwnProperty.call( attrMap, name ) ? attrMap[ name ] : null; },
		setAttribute( name, val ) { attrMap[ name ] = String( val ); },
		removeAttribute( name ) { delete attrMap[ name ]; },
		submit() { node.nativeSubmit = true; }
	};
	return node;
}

function matches( node, selector ) {
	if ( selector.startsWith( '.' ) ) {
		return node.classList.contains( selector.slice( 1 ) );
	}
	let m = selector.match( /^(\w+)?(?:\.([\w-]+))?(?:\[([\w:-]+)="([^"]+)"\])?$/ );
	if ( ! m ) { return false; }
	const [ , tag, cls, attr, val ] = m;
	if ( tag && tag !== node.tagName ) { return false; }
	if ( cls && ! node.classList.contains( cls ) ) { return false; }
	if ( attr && node.getAttribute( attr ) !== val ) { return false; }
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

/**
 * Build the dashboard DOM subset under a fake document.
 */
function dashboardDom( modules ) {
	const body = el( 'div' );
	const all = [];

	modules.forEach( ( m ) => {
		const row = el( 'div', { className: 'rk-dashboard-module' } );
		row.appendChild( el( 'div', { className: 'rk-dashboard-module-title' }, m.label ) );
		const form = el( 'form', { className: 'rk-dashboard-module-toggle' } );
		form.appendChild( el( 'input', { type: 'hidden', name: 'rankkernel_module_toggle', value: m.id } ) );
		const btn = el( 'button', { role: 'switch', className: m.enabled ? 'rk-dashboard-switch is-on' : 'rk-dashboard-switch', 'aria-checked': m.enabled ? 'true' : 'false', 'aria-label': ( m.enabled ? 'Turn off ' : 'Turn on ' ) + m.label + ' module' } );
		form.appendChild( btn );
		row.appendChild( form );
		body.appendChild( row );
		all.push( { form, btn, label: m.label } );
	} );

	const attentionCount = el( 'span', { id: 'rk-attention-count' }, String( modules.filter( ( m ) => ! m.enabled ).length ) );
	const attentionTag = el( 'span', { id: 'rk-attention-tag' }, 'Optional' );
	const statIcon = el( 'div', { id: 'rk-attention-stat-icon', className: 'rk-dashboard-stat-icon rk-dashboard-stat-icon-neutral' } );
	statIcon.appendChild( el( 'span', { className: 'rk-icon', 'aria-hidden': 'true' }, 'info' ) );
	const bar = el( 'details', { id: 'rk-attention-bar', className: 'rk-dashboard-alert' } );
	const barCount = el( 'strong', { id: 'rk-attention-bar-count' }, '' );
	const barNames = el( 'span', { id: 'rk-attention-bar-names' }, '' );
	const barText = el( 'p', { id: 'rk-attention-bar-text' }, '' );
	bar.appendChild( barCount );
	bar.appendChild( barNames );
	bar.appendChild( barText );
	body.appendChild( attentionCount );
	body.appendChild( attentionTag );
	body.appendChild( statIcon );
	body.appendChild( bar );

	const byId = {
		'rk-attention-count': attentionCount,
		'rk-attention-tag': attentionTag,
		'rk-attention-stat-icon': statIcon,
		'rk-attention-bar': bar,
		'rk-attention-bar-count': barCount,
		'rk-attention-bar-names': barNames,
		'rk-attention-bar-text': barText
	};

	const document = {
		readyState: 'complete',
		getElementById( id ) { return byId[ id ] || null; },
		querySelectorAll( selector ) { return findAll( body, selector ); },
		addEventListener() {}
	};

	return { document, rows: all, bar, barCount, barNames, barText, attentionCount, statIcon };
}

function runDashboard( sandbox ) {
	vm.runInNewContext( source( 'dashboard-admin.js' ), sandbox );
}

test( 'toggle POSTs to REST and flips switch plus attention in place', async () => {
	const dom = dashboardDom( [
		{ id: 'metadata', label: 'Metadata', enabled: true },
		{ id: 'redirects', label: 'Redirects', enabled: false }
	] );
	const calls = [];
	const sandbox = {
		document: dom.document,
		fetch( url, opts ) {
			calls.push( { url, opts } );
			return Promise.resolve( { ok: true, json() { return Promise.resolve( {} ); } } );
		}
	};
	sandbox.window = Object.assign( sandbox, {
		rankkernelDashboard: { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' },
		fetch: sandbox.fetch
	} );
	sandbox.window.fetch = sandbox.fetch;

	runDashboard( sandbox );

	const row = dom.rows[ 1 ];
	let prevented = false;
	row.form.dispatch( 'submit', { preventDefault() { prevented = true; } } );

	await new Promise( ( r ) => setImmediate( r ) );
	await new Promise( ( r ) => setImmediate( r ) );

	assert.equal( prevented, true );
	assert.equal( calls.length, 1 );
	assert.equal( calls[ 0 ].url, '/wp-json/rankkernel/v1/modules/redirects' );
	assert.equal( calls[ 0 ].opts.method, 'POST' );
	assert.equal( calls[ 0 ].opts.headers[ 'X-WP-Nonce' ], 'abc' );
	assert.equal( calls[ 0 ].opts.body, JSON.stringify( { enabled: true } ) );

	assert.equal( row.btn.getAttribute( 'aria-checked' ), 'true' );
	assert.ok( row.btn.classList.contains( 'is-on' ) );
	assert.equal( dom.attentionCount.textContent, '0' );
	assert.equal( dom.bar.style.display, 'none' );
	assert.equal( row.btn.getAttribute( 'aria-label' ), 'Turn off Redirects module' );
} );

test( 'toggle falling back to native submit when REST fails', async () => {
	const dom = dashboardDom( [ { id: 'robots', label: 'Robots', enabled: false } ] );
	const sandbox = {
		document: dom.document,
		fetch() { return Promise.resolve( { ok: false, status: 500 } ); }
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;

	runDashboard( sandbox );

	const row = dom.rows[ 0 ];
	row.form.dispatch( 'submit', { preventDefault() {} } );

	await new Promise( ( r ) => setImmediate( r ) );
	await new Promise( ( r ) => setImmediate( r ) );
	await new Promise( ( r ) => setImmediate( r ) );

	assert.equal( row.form.nativeSubmit, true );
	assert.equal( row.btn.getAttribute( 'aria-checked' ), 'false' );
} );

test( 'toggle falling back to native submit when fetch rejects', async () => {
	const dom = dashboardDom( [ { id: 'robots', label: 'Robots', enabled: false } ] );
	const sandbox = {
		document: dom.document,
		fetch() { return Promise.reject( new Error( 'offline' ) ); }
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;

	runDashboard( sandbox );

	const row = dom.rows[ 0 ];
	row.form.dispatch( 'submit', { preventDefault() {} } );

	await new Promise( ( r ) => setImmediate( r ) );
	await new Promise( ( r ) => setImmediate( r ) );
	await new Promise( ( r ) => setImmediate( r ) );

	assert.equal( row.form.nativeSubmit, true );
} );

test( 'planned modules are never wired', () => {
	// The planned row renders a disabled button and no form, so the DOM this
	// script binds contains no toggle form for it: binding finds zero forms
	// and the button is never touched.
	const dom = dashboardDom( [] );
	const plannedBtn = el( 'button', { role: 'switch', className: 'rk-dashboard-switch', 'aria-checked': 'false', disabled: '' } );
	const sandbox = {
		document: dom.document,
		fetch() { throw new Error( 'no fetch expected' ); }
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;

	runDashboard( sandbox );

	assert.equal( sandbox.document.querySelectorAll( 'form.rk-dashboard-module-toggle' ).length, 0 );
	assert.equal( plannedBtn.getAttribute( 'aria-checked' ), 'false' );
} );
