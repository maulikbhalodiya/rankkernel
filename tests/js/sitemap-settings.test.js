'use strict';

/**
 * Sitemap Settings JS unit tests.
 *
 * Verifies the client side tabs from
 * assets/js/sitemap-settings-admin.js: panels hide and show, the tab
 * highlight follows, pushState fires without a scroll jump, and the
 * fallback works when history is unavailable.
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

function fakeTabLink( href, tabId ) {
	const classes = new Set();
	const attrs = { href };

	if ( tabId ) {
		attrs[ 'data-rk-tab' ] = tabId;
	}

	return {
		classes,
		attrs,
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( attrs, name ) ? attrs[ name ] : null;
		},
		setAttribute( name, val ) {
			attrs[ name ] = String( val );
		},
		removeAttribute( name ) {
			delete attrs[ name ];
		},
		classList: {
			toggle( name, force ) {
				if ( force ) {
					classes.add( name );
				} else {
					classes.delete( name );
				}
			}
		},
		listeners: {},
		addEventListener( type, handler ) {
			this.listeners[ type ] = handler;
		},
		fire( type, event = {} ) {
			if ( this.listeners[ type ] ) {
				this.listeners[ type ]( Object.assign( { preventDefault() {} }, event ) );
			}
		}
	};
}

function fakePanel( tabId ) {
	return {
		id: 'rk-sitemap-panel-' + tabId,
		hidden: false,
		attrs: { 'data-rk-tab-panel': tabId },
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
		},
		setAttribute( name, val ) {
			this.attrs[ name ] = String( val );
		},
		scrollIntoView() {
			this.scrolled = true;
		}
	};
}

function loadTabs( search, hash = '', withHistory = true ) {
	const tabIds = [ 'general', 'post-types', 'taxonomies', 'authors' ];
	const links = tabIds.map( ( id ) =>
		fakeTabLink( 'admin.php?page=rankkernel-sitemap&tab=' + id, id )
	);
	const panels = tabIds.map( ( id ) => fakePanel( id ) );
	const byId = {};

	panels.forEach( ( panel ) => {
		byId[ panel.id.replace( 'rk-sitemap-panel-', '' ) ] = panel;
	} );

	const windowListeners = {};
	const document = {
		readyState: 'complete',
		addEventListener() {},
		querySelector( selector ) {
			if ( '.rk-ui-tabs' === selector ) {
				return {
					querySelectorAll( sel ) {
						return 'a.rk-ui-tab' === sel ? links : [];
					}
				};
			}

			const panelMatch = selector.match( /^\[data-rk-tab-panel="(.+)"\]$/ );

			if ( panelMatch ) {
				return byId[ panelMatch[ 1 ] ] || null;
			}

			return null;
		},
		getElementById( id ) {
			return panels.find( ( p ) => p.id === id ) || null;
		}
	};

	const sandbox = {
		document,
		location: { search, hash, href: '' },
		addEventListener( type, handler ) {
			windowListeners[ type ] = handler;
		}
	};

	if ( withHistory ) {
		sandbox.history = {
			pushed: [],
			pushState( state, title, url ) {
				this.pushed.push( url );
			}
		};
	}

	sandbox.window = sandbox;

	vm.runInNewContext( source( 'sitemap-settings-admin.js' ), sandbox );

	return { links, panels, byId, windowListeners, sandbox, api: sandbox.rankkernelSitemapSettings };
}

test( 'pickTab follows a known tab argument and falls back to the first tab', () => {
	const { api } = loadTabs( '' );
	const ids = [ 'general', 'post-types', 'taxonomies', 'authors' ];

	assert.equal( api.pickTab( '?tab=authors', ids ), 'authors' );
	assert.equal( api.pickTab( '?settings-updated=1&tab=taxonomies', ids ), 'taxonomies' );
	assert.equal( api.pickTab( '?tab=nope', ids ), 'general' );
	assert.equal( api.pickTab( '', ids ), 'general' );
	assert.equal( api.pickTab( '?tab=authors', [] ), '' );
} );

test( 'loading with a tab argument opens that panel and hides the rest', () => {
	const { links, byId } = loadTabs( '?tab=taxonomies' );

	assert.ok( links[ 2 ].classes.has( 'is-current' ) );
	assert.ok( ! links[ 0 ].classes.has( 'is-current' ) );
	assert.equal( links[ 2 ].attrs[ 'aria-current' ], 'page' );
	assert.equal( 'taxonomies' in byId && byId.taxonomies.hidden, false );
	assert.equal( byId.general.hidden, true );
	assert.equal( byId[ 'post-types' ].hidden, true );
	assert.equal( byId.authors.hidden, true );
} );

test( 'loading without a tab argument opens the first panel', () => {
	const { links, byId } = loadTabs( '' );

	assert.ok( links[ 0 ].classes.has( 'is-current' ) );
	assert.equal( byId.general.hidden, false );
	assert.equal( byId[ 'post-types' ].hidden, true );
	assert.equal( byId.taxonomies.hidden, true );
	assert.equal( byId.authors.hidden, true );
} );

test( 'clicking a tab moves the highlight, swaps panels, and pushStates the href', () => {
	const { links, byId, sandbox } = loadTabs( '' );
	let prevented = false;

	links[ 1 ].fire( 'click', { preventDefault() { prevented = true; } } );

	assert.equal( prevented, true );
	assert.equal( sandbox.history.pushed.length, 1 );
	assert.equal( sandbox.history.pushed[ 0 ], 'admin.php?page=rankkernel-sitemap&tab=post-types' );
	assert.ok( ! links[ 0 ].classes.has( 'is-current' ) );
	assert.ok( links[ 1 ].classes.has( 'is-current' ) );
	assert.equal( links[ 1 ].attrs[ 'aria-current' ], 'page' );
	assert.equal( links[ 0 ].attrs[ 'aria-current' ], undefined );
	assert.equal( byId.general.hidden, true );
	assert.equal( byId[ 'post-types' ].hidden, false );
	assert.equal( byId.taxonomies.hidden, true );
	assert.equal( byId.authors.hidden, true );
} );

test( 'a popstate move reactivates the panel from the address', () => {
	const harness = loadTabs( '' );

	assert.equal( harness.byId.general.hidden, false );

	harness.sandbox.location.search = '?tab=authors';
	harness.windowListeners.popstate();

	assert.ok( ! harness.links[ 0 ].classes.has( 'is-current' ) );
	assert.ok( harness.links[ 3 ].classes.has( 'is-current' ) );
	assert.equal( harness.byId.general.hidden, true );
	assert.equal( harness.byId.authors.hidden, false );
} );

test( 'pushState is guarded so a missing history falls back to the href', () => {
	const { links, byId, sandbox } = loadTabs( '', '', false );
	let threw = null;

	try {
		links[ 3 ].fire( 'click' );
	} catch ( error ) {
		threw = error;
	}

	assert.equal( threw, null );
	assert.equal( sandbox.location.href, 'admin.php?page=rankkernel-sitemap&tab=authors' );
	assert.equal( byId.authors.hidden, false );
	assert.ok( links[ 3 ].classes.has( 'is-current' ) );
} );

test( 'a hash target scrolls into view on load', () => {
	const { byId } = loadTabs( '?tab=general', '#rk-sitemap-panel-general' );

	assert.equal( byId.general.scrolled, true );
} );
