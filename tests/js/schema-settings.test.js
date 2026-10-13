'use strict';

/**
 * Schema Settings JS unit tests.
 *
 * Verifies live screen reader announcements (wp.a11y.speak) when:
 *  - Selecting or removing the organization logo.
 *  - Adding or removing social profile chips.
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

function fakeElement( tagName = 'div', attrs = {} ) {
	const children = [];
	const listeners = {};
	const attrMap = Object.assign( {}, attrs );

	return {
		tagName,
		value: '',
		src: '',
		style: { display: '' },
		children,
		listeners,
		classList: {
			add() {},
			remove() {},
			toggle() {}
		},
		get firstChild() {
			return children[ 0 ] || null;
		},
		appendChild( child ) {
			children.push( child );
			child.parentNode = this;
			return child;
		},
		removeChild( child ) {
			const index = children.indexOf( child );
			if ( index !== -1 ) {
				children.splice( index, 1 );
			}
			return child;
		},
		querySelector( selector ) {
			if ( selector.startsWith( '.' ) ) {
				const className = selector.slice( 1 );
				return children.find( ( c ) => c.className && c.className.includes( className ) ) || null;
			}
			return null;
		},
		querySelectorAll( selector ) {
			if ( selector.startsWith( '.' ) ) {
				const className = selector.slice( 1 );
				return children.filter( ( c ) => c.className && c.className.includes( className ) );
			}
			return [];
		},
		addEventListener( type, handler ) {
			listeners[ type ] = handler;
		},
		fire( type, event = {} ) {
			if ( listeners[ type ] ) {
				listeners[ type ]( Object.assign( { target: this, preventDefault() {} }, event ) );
			}
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( attrMap, name ) ? attrMap[ name ] : null;
		},
		setAttribute( name, val ) {
			attrMap[ name ] = String( val );
		},
		focus() {},
		setCustomValidity() {},
		reportValidity() {}
	};
}

test( 'schema-settings announces logo selection and removal', () => {
	const spoken = [];
	let selectHandler = null;

	const logoSelect = fakeElement( 'button', { id: 'rk-org-logo-select' } );
	const logoRemove = fakeElement( 'button', { id: 'rk-org-logo-remove' } );
	const logoInput = fakeElement( 'input', { id: 'rk-org-logo' } );
	const logoPreview = fakeElement( 'img', { id: 'rk-org-logo-preview' } );

	const nodes = {
		'rk-org-logo-select': logoSelect,
		'rk-org-logo-remove': logoRemove,
		'rk-org-logo': logoInput,
		'rk-org-logo-preview': logoPreview
	};

	const document = {
		readyState: 'complete',
		getElementById( id ) {
			return nodes[ id ] || null;
		},
		addEventListener() {}
	};

	const wp = {
		media() {
			return {
				on( event, handler ) {
					if ( 'select' === event ) {
						selectHandler = handler;
					}
				},
				open() {},
				state() {
					return {
						get() {
							return {
								first() {
									return {
										get( key ) {
											return 'url' === key ? 'https://example.com/logo.png' : null;
										}
									};
								}
							};
						}
					};
				}
			};
		},
		a11y: {
			speak( msg ) {
				spoken.push( msg );
			}
		}
	};

	const sandbox = { document, wp };
	sandbox.window = sandbox;

	vm.runInNewContext( source( 'schema-settings.js' ), sandbox );

	// Trigger logo select
	logoSelect.fire( 'click' );
	if ( selectHandler ) {
		selectHandler();
	}

	assert.equal( logoInput.value, 'https://example.com/logo.png' );
	assert.deepEqual( spoken, [ 'Organization logo updated.' ] );

	// Trigger logo remove
	logoRemove.fire( 'click' );
	assert.equal( logoInput.value, '' );
	assert.deepEqual( spoken, [ 'Organization logo updated.', 'Organization logo removed.' ] );
} );

test( 'a scriptable media URL is refused and never reaches the field', () => {
	const spoken = [];
	let selectHandler = null;

	const logoInput = fakeElement( 'input', { id: 'rk-org-logo' } );
	const logoPreview = fakeElement( 'img', { id: 'rk-org-logo-preview' } );

	const nodes = {
		'rk-org-logo-select': fakeElement( 'button', { id: 'rk-org-logo-select' } ),
		'rk-org-logo': logoInput,
		'rk-org-logo-preview': logoPreview
	};

	const document = {
		readyState: 'complete',
		getElementById( id ) {
			return nodes[ id ] || null;
		},
		addEventListener() {}
	};

	const wp = {
		media() {
			return {
				on( event, handler ) {
					if ( 'select' === event ) {
						selectHandler = handler;
					}
				},
				open() {},
				state() {
					return {
						get() {
							return {
								first() {
									return {
										get( key ) {
											return 'url' === key ? 'javascript:alert(1)' : null;
										}
									};
								}
							};
						}
					};
				}
			};
		},
		a11y: {
			speak( msg ) {
				spoken.push( msg );
			}
		}
	};

	const sandbox = { document, wp };
	sandbox.window = sandbox;

	vm.runInNewContext( source( 'schema-settings.js' ), sandbox );

	nodes[ 'rk-org-logo-select' ].fire( 'click' );
	if ( selectHandler ) {
		selectHandler();
	}

	assert.equal( logoInput.value, '', 'a scriptable scheme must never reach the option field' );
	assert.deepEqual( spoken, [], 'a refused selection announces nothing' );
} );

test( 'schema-settings announces social profile chip additions and removals', () => {
	const spoken = [];

	const sameasApp = fakeElement( 'div', {
		id: 'rk-schema-sameas-app',
		'data-remove-label': 'Remove',
		'data-invalid-label': 'Invalid URL'
	} );
	const sameasTextarea = fakeElement( 'textarea', { id: 'rk-org-sameas' } );
	const sameasChips = fakeElement( 'ul', { id: 'rk-schema-sameas-chips' } );
	const sameasNewInput = fakeElement( 'input', { id: 'rk-schema-sameas-new' } );
	const sameasAddBtn = fakeElement( 'button', { id: 'rk-schema-sameas-add' } );

	const nodes = {
		'rk-schema-sameas-app': sameasApp,
		'rk-org-sameas': sameasTextarea,
		'rk-schema-sameas-chips': sameasChips,
		'rk-schema-sameas-new': sameasNewInput,
		'rk-schema-sameas-add': sameasAddBtn
	};

	const document = {
		readyState: 'complete',
		getElementById( id ) {
			return nodes[ id ] || null;
		},
		createElement( tag ) {
			return fakeElement( tag );
		},
		createTextNode( txt ) {
			return { textContent: txt };
		},
		addEventListener() {}
	};

	const wp = {
		a11y: {
			speak( msg ) {
				spoken.push( msg );
			}
		}
	};

	const sandbox = { document, wp };
	sandbox.window = sandbox;

	vm.runInNewContext( source( 'schema-settings.js' ), sandbox );

	// Add profile URL
	sameasNewInput.value = 'https://twitter.com/example';
	sameasAddBtn.fire( 'click' );

	assert.equal( sameasTextarea.value, 'https://twitter.com/example' );
	assert.deepEqual( spoken, [ 'Added profile URL https://twitter.com/example.' ] );

	// Add duplicate profile URL
	sameasNewInput.value = 'https://twitter.com/example';
	sameasAddBtn.fire( 'click' );

	assert.equal( sameasTextarea.value, 'https://twitter.com/example' );
	assert.deepEqual( spoken, [
		'Added profile URL https://twitter.com/example.',
		'Profile URL https://twitter.com/example is already in the list.'
	] );

	// Remove chip
	const chipBtn = sameasChips.children[ 0 ].children[ 1 ]; // remove button in chip
	chipBtn.fire( 'click' );

	assert.equal( sameasTextarea.value, '' );
	assert.deepEqual( spoken, [
		'Added profile URL https://twitter.com/example.',
		'Profile URL https://twitter.com/example is already in the list.',
		'Removed profile URL https://twitter.com/example.'
	] );
} );

function fakeTabLink( href ) {
	const classes = new Set();
	const attrs = { href };

	return {
		classes,
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( attrs, name ) ? attrs[ name ] : null;
		},
		setAttribute( name, val ) {
			attrs[ name ] = String( val );
		},
		removeAttribute( name ) {
			delete attrs[ name ];
		},
		hasAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( attrs, name );
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

function fakeTabSection( id ) {
	return { id, hidden: false };
}

function loadTabs( hash ) {
	const ids = [ 'section-identity', 'section-defaults', 'section-output' ];
	const links = ids.map( ( id ) => fakeTabLink( '#' + id ) );
	const sections = ids.map( ( id ) => fakeTabSection( id ) );
	const byId = {};

	sections.forEach( ( section ) => {
		byId[ section.id ] = section;
	} );

	const windowListeners = {};
	const document = {
		readyState: 'complete',
		addEventListener() {},
		querySelector( selector ) {
			if ( '.rk-schema-nav-list' === selector ) {
				return {
					querySelectorAll() {
						return links;
					}
				};
			}

			return null;
		},
		getElementById( id ) {
			return byId[ id ] || null;
		}
	};

	const sandbox = {
		document,
		location: { hash },
		history: {
			pushed: [],
			pushState( state, title, url ) {
				this.pushed.push( url );
			}
		},
		addEventListener( type, handler ) {
			windowListeners[ type ] = handler;
		}
	};
	sandbox.window = sandbox;

	vm.runInNewContext( source( 'schema-settings.js' ), sandbox );

	return { links, sections, windowListeners, sandbox, api: sandbox.rankkernelSchemaSettings };
}

test( 'pickSection follows a known hash and falls back to the first section', () => {
	const { api } = loadTabs( '' );
	const ids = [ 'section-identity', 'section-defaults', 'section-output' ];

	assert.equal( api.pickSection( '#section-output', ids ), 'section-output' );
	assert.equal( api.pickSection( 'section-defaults', ids ), 'section-defaults' );
	assert.equal( api.pickSection( '#nope', ids ), 'section-identity' );
	assert.equal( api.pickSection( '', ids ), 'section-identity' );
	assert.equal( api.pickSection( '#section-output', [] ), '' );
} );

test( 'sectionIds reads fragment links and skips the rest', () => {
	const { api } = loadTabs( '' );
	const ids = api.sectionIds( [ fakeTabLink( '#a' ), fakeTabLink( '#b' ), fakeTabLink( 'https://example.com/' ) ] );

	assert.equal( ids.length, 2 );
	assert.equal( ids[ 0 ], 'a' );
	assert.equal( ids[ 1 ], 'b' );
} );

test( 'loading with a hash opens that section and highlights its tab', () => {
	const { links, sections } = loadTabs( '#section-output' );

	assert.ok( links[ 2 ].classes.has( 'is-current' ) );
	assert.ok( ! links[ 0 ].classes.has( 'is-current' ) );
	assert.ok( ! links[ 1 ].classes.has( 'is-current' ) );
	assert.equal( links[ 2 ].hasAttribute( 'aria-current' ), true );
	assert.equal( links[ 0 ].hasAttribute( 'aria-current' ), false );
	assert.equal( sections[ 2 ].hidden, false );
	assert.equal( sections[ 0 ].hidden, true );
	assert.equal( sections[ 1 ].hidden, true );
} );

test( 'loading without a hash opens the first section', () => {
	const { links, sections } = loadTabs( '' );

	assert.ok( links[ 0 ].classes.has( 'is-current' ) );
	assert.equal( sections[ 0 ].hidden, false );
	assert.equal( sections[ 1 ].hidden, true );
	assert.equal( sections[ 2 ].hidden, true );
} );

test( 'clicking a tab moves the highlight and the visible section', () => {
	const { links, sections, sandbox } = loadTabs( '' );
	let prevented = false;

	links[ 1 ].fire( 'click', { preventDefault() { prevented = true; } } );

	assert.equal( prevented, true );
	assert.equal( sandbox.history.pushed.length, 1 );
	assert.equal( sandbox.history.pushed[ 0 ], '#section-defaults' );
	assert.ok( ! links[ 0 ].classes.has( 'is-current' ) );
	assert.ok( links[ 1 ].classes.has( 'is-current' ) );
	assert.equal( sections[ 0 ].hidden, true );
	assert.equal( sections[ 1 ].hidden, false );
	assert.equal( sections[ 2 ].hidden, true );
} );

test( 'a hash change after load moves the open section', () => {
	const harness = loadTabs( '' );

	assert.ok( harness.links[ 0 ].classes.has( 'is-current' ) );

	harness.sandbox.location.hash = '#section-output';
	harness.windowListeners.hashchange();

	assert.ok( ! harness.links[ 0 ].classes.has( 'is-current' ) );
	assert.ok( harness.links[ 2 ].classes.has( 'is-current' ) );
	assert.equal( harness.sections[ 0 ].hidden, true );
	assert.equal( harness.sections[ 2 ].hidden, false );
} );

function previewHarness( { clipboardText = null, execResult = true } = {} ) {
	const spoken = [];
	const copied = [];
	const created = [];

	const code = { textContent: '{"@context":"https://schema.org"}' };
	const copyButton = fakeElement( 'button', { id: 'rk-schema-copy-json' } );
	const exportButton = fakeElement( 'button', { id: 'rk-schema-export-json' } );

	const nodes = {
		'rk-schema-copy-json': copyButton,
		'rk-schema-export-json': exportButton,
		'rk-schema-preview-code': code
	};

	const body = {
		appended: [],
		appendChild( child ) {
			this.appended.push( child );
			child.parentNode = this;
			return child;
		},
		removeChild( child ) {
			child.parentNode = null;
			return child;
		}
	};

	const document = {
		readyState: 'complete',
		body,
		getElementById( id ) {
			return nodes[ id ] || null;
		},
		addEventListener() {},
		querySelector() {
			return null;
		},
		createElement( tag ) {
			const el = fakeElement( tag );
			created.push( el );
			return el;
		},
		execCommand( command ) {
			copied.push( command );
			return execResult;
		}
	};

	const sandbox = {
		document,
		wp: {
			a11y: {
				speak( msg ) {
					spoken.push( msg );
				}
			}
		},
		URL: {
			created: [],
			revoked: [],
			createObjectURL( blob ) {
				this.created.push( blob );
				return 'blob:preview';
			},
			revokeObjectURL( url ) {
				this.revoked.push( url );
			}
		},
		Blob: function ( parts ) {
			this.parts = parts;
		}
	};
	sandbox.window = sandbox;

	if ( null !== clipboardText ) {
		sandbox.navigator = {
			clipboard: {
				writeText( text ) {
					copied.push( text );
					return {
						then( onOk ) {
							onOk();
						}
					};
				}
			}
		};
	}

	vm.runInNewContext( source( 'schema-settings.js' ), sandbox );

	return { sandbox, spoken, copied, created, body, code, copyButton, exportButton };
}

test( 'schema-settings copies the preview JSON through the clipboard API', () => {
	const harness = previewHarness( { clipboardText: true } );

	harness.copyButton.fire( 'click' );

	assert.deepEqual( harness.copied, [ '{"@context":"https://schema.org"}' ] );
	assert.deepEqual( harness.spoken, [ 'Preview JSON copied to clipboard.' ] );
} );

test( 'schema-settings falls back to execCommand without a clipboard API', () => {
	const harness = previewHarness();

	harness.copyButton.fire( 'click' );

	assert.deepEqual( harness.copied, [ 'copy' ] );
	assert.deepEqual( harness.spoken, [ 'Preview JSON copied to clipboard.' ] );
	assert.equal( harness.created[ 0 ].value, '{"@context":"https://schema.org"}' );
	assert.equal( harness.created[ 0 ].parentNode, null );
} );

test( 'schema-settings downloads the preview JSON on export', () => {
	const harness = previewHarness();

	const link = harness.created;
	harness.exportButton.fire( 'click' );

	assert.equal( link.length, 1 );
	assert.equal( link[ 0 ].download, 'rankkernel-schema.json' );
	assert.equal( link[ 0 ].href, 'blob:preview' );
	assert.equal( link[ 0 ].parentNode, null );

	assert.deepEqual( harness.spoken, [ 'Preview JSON downloaded.' ] );
	assert.deepEqual( harness.sandbox.URL.revoked, [ 'blob:preview' ] );
	assert.equal( harness.sandbox.URL.created[ 0 ].parts.length, 1 );
	assert.equal( harness.sandbox.URL.created[ 0 ].parts[ 0 ], '{"@context":"https://schema.org"}' );
} );

test( 'schema-settings copy and export refuse an empty preview', () => {
	const harness = previewHarness();
	const api = harness.sandbox.window.rankkernelSchemaSettings;

	harness.code.textContent = '';

	assert.equal( api.copyPreviewJson( harness.code ), false );
	assert.equal( api.exportPreviewJson( harness.code ), false );
	assert.deepEqual( harness.spoken, [] );
} );
