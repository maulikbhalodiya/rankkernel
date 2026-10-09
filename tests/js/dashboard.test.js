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
		innerHTML: '',
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

	const submissionList = el( 'ul', { className: 'wp-submenu' } );
	submissionList.innerHTML = '<li>Dashboard</li><li>Modules</li>';
	const toplevel = el( 'li', { id: 'toplevel_page_rankkernel' } );
	toplevel.appendChild( submissionList );
	body.appendChild( toplevel );

	const byId = {
		'rk-attention-count': attentionCount,
		'rk-attention-tag': attentionTag,
		'rk-attention-stat-icon': statIcon,
		'rk-attention-bar': bar,
		'rk-attention-bar-count': barCount,
		'rk-attention-bar-names': barNames,
		'rk-attention-bar-text': barText,
		toplevel_page_rankkernel: toplevel
	};

	const document = {
		readyState: 'complete',
		getElementById( id ) { return byId[ id ] || null; },
		querySelectorAll( selector ) { return findAll( body, selector ); },
		addEventListener() {}
	};

	return { document, rows: all, bar, barCount, barNames, barText, attentionCount, statIcon, submenu: submissionList };
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
		location: { href: '/wp-admin/admin.php?page=rankkernel' },
		wp: { a11y: { speak() {} }, i18n: { __: ( t ) => t } },
		fetch( url, opts ) {
			calls.push( { url, opts } );
			return Promise.resolve( { ok: true, json() { return Promise.resolve( {} ); }, text() { return Promise.resolve( '' ); } } );
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
	const posts = calls.filter( ( c ) => c.opts && 'POST' === c.opts.method );
	assert.equal( posts.length, 1 );
	assert.equal( posts[ 0 ].url, '/wp-json/rankkernel/v1/modules/redirects' );
	assert.equal( posts[ 0 ].opts.headers[ 'X-WP-Nonce' ], 'abc' );
	assert.equal( posts[ 0 ].opts.body, JSON.stringify( { enabled: true } ) );

	// A successful toggle also reloads the current page to refresh the menu.
	const gets = calls.filter( ( c ) => ! c.opts || ! c.opts.method );
	assert.equal( gets.length, 1 );
	assert.equal( gets[ 0 ].url, '/wp-admin/admin.php?page=rankkernel' );

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

/**
 * A DOMParser stub whose parse returns a "document" exposing the given
 * markup as the rankkernel submenu contents.
 */
function DomParserStub( markup ) {
	return function () {
		return {
			parseFromString() {
				return {
					getElementById( id ) {
						if ( 'toplevel_page_rankkernel' !== id ) {
							return null;
						}

						return {
							querySelector() {
								return { innerHTML: markup };
							}
						};
					}
				};
			}
		};
	};
}

function deferred() {
	let resolve;
	let reject;
	const promise = new Promise( ( res, rej ) => { resolve = res; reject = rej; } );
	return { promise, resolve, reject };
}

test( 'successful toggle swaps the live submenu when markup differs', async () => {
	const dom = dashboardDom( [ { id: 'redirects', label: 'Redirects', enabled: false } ] );
	dom.submenu.innerHTML = '<li>Dashboard</li><li>Redirects</li>';
	const spoken = [];
	const calls = [];
	const sandbox = {
		document: dom.document,
		location: { href: '/wp-admin/admin.php?page=rankkernel' },
		wp: { a11y: { speak( m ) { spoken.push( m ); } }, i18n: { __: ( t ) => t } },
		matchMedia() { return { matches: true }; },
		fetch( url, opts ) {
			calls.push( { url, opts } );
			if ( opts && 'POST' === opts.method ) {
				return Promise.resolve( { ok: true, json() { return Promise.resolve( {} ); } } );
			}

			return Promise.resolve( { ok: true, text() { return Promise.resolve( '<li>Dashboard</li><li>Instant Indexing</li>' ); } } );
		}
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;
	sandbox.window.location = sandbox.location;
	sandbox.window.matchMedia = sandbox.matchMedia;
	sandbox.window.wp = sandbox.wp;
	sandbox.window.DOMParser = DomParserStub( '<li>Dashboard</li><li>Instant Indexing</li>' );

	runDashboard( sandbox );

	dom.rows[ 0 ].form.dispatch( 'submit', { preventDefault() {} } );

	for ( let i = 0; i < 6; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	assert.equal( dom.submenu.innerHTML, '<li>Dashboard</li><li>Instant Indexing</li>' );
	assert.equal( spoken.length, 1 );
	assert.equal( spoken[ 0 ], 'Sidebar menu updated.' );
	assert.equal( dom.rows[ 0 ].btn.getAttribute( 'aria-checked' ), 'true' );
	assert.ok( dom.rows[ 0 ].btn.classList.contains( 'is-on' ) );

	// Reduced motion: the swap is immediate, no fade styles applied.
	assert.equal( dom.submenu.style.transition, undefined );
} );

test( 'unchanged normalized markup leaves the submenu and announcement alone', async () => {
	const dom = dashboardDom( [ { id: '404', label: '404', enabled: true } ] );
	dom.submenu.innerHTML = '<li>Dashboard</li><li>404 Monitor</li>';
	const spoken = [];
	const sandbox = {
		document: dom.document,
		location: { href: '/wp-admin/admin.php?page=rankkernel' },
		wp: { a11y: { speak( m ) { spoken.push( m ); } }, i18n: { __: ( t ) => t } },
		matchMedia() { return { matches: true }; },
		fetch( url, opts ) {
			if ( opts && 'POST' === opts.method ) {
				return Promise.resolve( { ok: true, json() { return Promise.resolve( {} ); } } );
			}

			return Promise.resolve( { ok: true, text() { return Promise.resolve( "\n <li>Dashboard</li>\n <li>404 Monitor</li>\n" ); } } );
		}
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;
	sandbox.window.location = sandbox.location;
	sandbox.window.matchMedia = sandbox.matchMedia;
	sandbox.window.wp = sandbox.wp;
	sandbox.window.DOMParser = DomParserStub( '\n <li>Dashboard</li>\n <li>404 Monitor</li>\n' );

	runDashboard( sandbox );

	dom.rows[ 0 ].form.dispatch( 'submit', { preventDefault() {} } );

	for ( let i = 0; i < 6; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	assert.equal( dom.submenu.innerHTML, '<li>Dashboard</li><li>404 Monitor</li>' );
	assert.equal( spoken.length, 0 );
} );

test( 'no menu refresh fires when the toggle POST fails', async () => {
	const dom = dashboardDom( [ { id: 'robots', label: 'Robots', enabled: false } ] );
	const calls = [];
	const sandbox = {
		document: dom.document,
		location: { href: '/wp-admin/admin.php?page=rankkernel' },
		wp: { a11y: { speak() {} }, i18n: { __: ( t ) => t } },
		matchMedia() { return { matches: true }; },
		fetch( url, opts ) {
			calls.push( { url, opts } );
			return Promise.resolve( { ok: false, status: 500 } );
		}
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;
	sandbox.window.location = sandbox.location;
	sandbox.window.matchMedia = sandbox.matchMedia;
	sandbox.window.wp = sandbox.wp;
	sandbox.window.DOMParser = DomParserStub( '<li>changed</li>' );

	runDashboard( sandbox );

	dom.rows[ 0 ].form.dispatch( 'submit', { preventDefault() {} } );

	for ( let i = 0; i < 6; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	assert.equal( calls.length, 1 );
	assert.equal( dom.rows[ 0 ].form.nativeSubmit, true );
	assert.equal( dom.submenu.innerHTML, '<li>Dashboard</li><li>Modules</li>' );
} );

test( 'a failed menu refresh leaves the menu untouched', async () => {
	const dom = dashboardDom( [ { id: 'metadata', label: 'Metadata', enabled: true } ] );
	dom.submenu.innerHTML = '<li>Dashboard</li>';
	const seen = {};
	const sandbox = {
		document: dom.document,
		location: { href: '/wp-admin/admin.php?page=rankkernel' },
		wp: { a11y: { speak( m ) { seen.spoken = m; } }, i18n: { __: ( t ) => t } },
		matchMedia() { return { matches: true }; },
		fetch( url, opts ) {
			if ( opts && 'POST' === opts.method ) {
				return Promise.resolve( { ok: true, json() { return Promise.resolve( {} ); } } );
			}

			return Promise.reject( new Error( 'offline' ) );
		}
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;
	sandbox.window.location = sandbox.location;
	sandbox.window.matchMedia = sandbox.matchMedia;
	sandbox.window.wp = sandbox.wp;
	sandbox.window.DOMParser = DomParserStub( '<li>changed</li>' );

	runDashboard( sandbox );

	dom.rows[ 0 ].form.dispatch( 'submit', { preventDefault() {} } );

	for ( let i = 0; i < 6; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	assert.equal( dom.submenu.innerHTML, '<li>Dashboard</li>' );
	assert.equal( seen.spoken, undefined );
} );

test( 'the last toggle wins while a refresh is in flight', async () => {
	const dom = dashboardDom( [
		{ id: 'metadata', label: 'Metadata', enabled: true },
		{ id: 'redirects', label: 'Redirects', enabled: false }
	] );
	dom.submenu.innerHTML = '<li>original</li>';
	const gets = [];
	const controllers = [];
	function AbortControllerStub() {
		this.signal = { aborted: false };
		this.abortCalls = 0;
		controllers.push( this );
	}
	AbortControllerStub.prototype.abort = function () {
		this.abortCalls++;
		this.signal.aborted = true;
	};
	const sandbox = {
		document: dom.document,
		location: { href: '/wp-admin/admin.php?page=rankkernel' },
		wp: { a11y: { speak() {} }, i18n: { __: ( t ) => t } },
		matchMedia() { return { matches: true }; },
		AbortController: AbortControllerStub,
		fetch( url, opts ) {
			if ( opts && 'POST' === opts.method ) {
				return Promise.resolve( { ok: true, json() { return Promise.resolve( {} ); } } );
			}

			const d = deferred();
			gets.push( d );
			return d.promise;
		}
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;
	sandbox.window.location = sandbox.location;
	sandbox.window.matchMedia = sandbox.matchMedia;
	sandbox.window.wp = sandbox.wp;
	sandbox.window.AbortController = AbortControllerStub;

	let parsedFrom = null;
	sandbox.window.DOMParser = function () {
		return {
			parseFromString( html ) {
				parsedFrom = html;
				return {
					getElementById( id ) {
						if ( 'toplevel_page_rankkernel' !== id ) {
							return null;
						}

						return { querySelector: () => ( { innerHTML: html } ) };
					}
				};
			}
		};
	};

	runDashboard( sandbox );

	dom.rows[ 0 ].form.dispatch( 'submit', { preventDefault() {} } );

	for ( let i = 0; i < 4; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	dom.rows[ 1 ].form.dispatch( 'submit', { preventDefault() {} } );

	for ( let i = 0; i < 4; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	assert.equal( gets.length, 2 );
	assert.equal( controllers.length, 2 );
	assert.equal( controllers[ 0 ].abortCalls, 1 );

	// The stale first response is dropped even though it would differ.
	gets[ 0 ].resolve( { ok: true, text() { return Promise.resolve( '<li>stale</li>' ); } } );

	for ( let i = 0; i < 4; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	assert.equal( dom.submenu.innerHTML, '<li>original</li>' );

	gets[ 1 ].resolve( { ok: true, text() { return Promise.resolve( '<li>fresh</li>' ); } } );

	for ( let i = 0; i < 6; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	assert.equal( dom.submenu.innerHTML, '<li>fresh</li>' );
} );

test( 'reduced motion is honored when swapping the submenu', async () => {
	const dom = dashboardDom( [ { id: 'metadata', label: 'Metadata', enabled: true } ] );
	dom.submenu.innerHTML = '<li>old</li>';
	const captured = {};
	const sandbox = {
		document: dom.document,
		location: { href: '/wp-admin/admin.php?page=rankkernel' },
		wp: { a11y: { speak() {} }, i18n: { __: ( t ) => t } },
		matchMedia() { return { matches: false }; },
		fetch( url, opts ) {
			if ( opts && 'POST' === opts.method ) {
				return Promise.resolve( { ok: true, json() { return Promise.resolve( {} ); } } );
			}

			return Promise.resolve( { ok: true, text() { return Promise.resolve( '<li>new</li>' ); } } );
		}
	};
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: '/wp-json/rankkernel/v1/modules', restNonce: 'abc' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;
	sandbox.window.fetch = sandbox.fetch;
	sandbox.window.location = sandbox.location;
	sandbox.window.matchMedia = sandbox.matchMedia;
	sandbox.window.wp = sandbox.wp;
	sandbox.window.DOMParser = DomParserStub( '<li>new</li>' );
	sandbox.setTimeout = ( fn ) => { captured.fn = fn; return 1; };
	sandbox.window.setTimeout = sandbox.setTimeout;

	runDashboard( sandbox );

	dom.rows[ 0 ].form.dispatch( 'submit', { preventDefault() {} } );

	for ( let i = 0; i < 6; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	// With motion allowed the submenu starts faded out and the delayed swap was scheduled.
	assert.equal( dom.submenu.style.opacity, '0' );
	assert.equal( typeof captured.fn, 'function' );
	assert.equal( dom.submenu.innerHTML, '<li>old</li>' );

	captured.fn();

	assert.equal( dom.submenu.innerHTML, '<li>new</li>' );
	assert.equal( dom.submenu.style.opacity, '1' );
} );

test( 'menu refresh helpers are exposed for direct unit tests', () => {
	const dom = dashboardDom( [] );
	const sandbox = { document: dom.document, fetch() { throw new Error( 'no fetch' ); } };
	sandbox.window = sandbox;
	sandbox.rankkernelDashboard = { modulesUrl: 'u', restNonce: 'n' };
	sandbox.window.rankkernelDashboard = sandbox.rankkernelDashboard;

	runDashboard( sandbox );

	const api = sandbox.rankkernelMenuRefresh;
	assert.equal( typeof api.submenusEqual, 'function' );
	// Inter-tag whitespace and padding are formatting noise: equal.
	assert.equal( api.submenusEqual( '<li>a</li>\n<li>b</li>', '  <li>a</li><li>b</li> ' ), true );
	// Inner-text spaces change the rendered label, so they stay different: a
	// false "different" only triggers a harmless extra swap, while a false
	// "equal" would skip a needed menu update.
	assert.equal( api.submenusEqual( '<li> a </li>', '<li>a</li>' ), false );
	assert.equal( api.submenusEqual( '<li>a</li>', '<li>b</li>' ), false );
	assert.equal( api.normalizeHtml( ' <li>\n  a\n</li> ' ), '<li> a </li>' );
} );
