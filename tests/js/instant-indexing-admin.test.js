'use strict';

/**
 * Live multi URL validation on the Instant Indexing screen.
 *
 * The script is a classic DOM script. The validation rules run through the
 * exposed pure validator with the same host wp_localize_script passes in the
 * browser, and the wiring test mounts the script against a minimal fake
 * document to pin the button state and the blocked submit.
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

function validator( siteHost, sitePort ) {
	const document = {
		readyState: 'complete',
		addEventListener() {},
		getElementById() {
			return null;
		}
	};

	const config = {
		siteHost: siteHost || 'example.com',
		sitePort: sitePort || ''
	};
	const sandbox = { document, URL, rankkernelInstantIndexing: config };

	sandbox.window = sandbox;
	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );

	assert.equal( typeof config.validate, 'function', 'the pure validator must be reachable from the harness' );

	return ( text ) => config.validate( text, config.siteHost, config.sitePort );
}

test( 'a valid same host URL validates clean', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://example.com/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.validCount, 1 );
	assert.equal( result.invalidCount, 0 );
	assert.equal( result.entries[ 0 ].valid, true );
	assert.equal( result.entries[ 0 ].reason, '' );
	assert.equal( result.summary, '1 URLs ready to submit.' );
	assert.equal( result.disabled, false );
} );

test( 'a foreign host URL returns the host reason', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://evil.test/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.validCount, 0 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].valid, false );
	assert.equal( result.entries[ 0 ].reason, 'This URL is not on this site.' );
	assert.equal( result.disabled, true );
} );

test( 'a malformed string returns the not a valid URL reason', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'not a url' );

	assert.equal( result.total, 1 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].reason, 'Not a valid URL.' );
} );

test( 'a www host against an apex site host is invalid', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://www.example.com/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].reason, 'This URL is not on this site.' );
	assert.equal( result.disabled, true );
} );

test( 'a URL with userinfo is invalid in the browser validator', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://user:pass@example.com/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.validCount, 0 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].valid, false );
	assert.equal( result.entries[ 0 ].reason, 'Not a valid URL.' );
	assert.equal( result.disabled, true );
} );

test( 'a URL on a non standard port is invalid', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://example.com:8443/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.validCount, 0 );
	assert.equal( result.invalidCount, 1 );
	assert.equal( result.entries[ 0 ].reason, 'Not a valid URL.' );
	assert.equal( result.disabled, true );
} );

test( "a URL on the site's own port is valid", () => {
	const validate = validator( 'example.com', '8443' );
	const result = validate( 'https://example.com:8443/post' );

	assert.equal( result.total, 1 );
	assert.equal( result.validCount, 1 );
	assert.equal( result.invalidCount, 0 );
	assert.equal( result.entries[ 0 ].valid, true );
	assert.equal( result.entries[ 0 ].reason, '' );
	assert.equal( result.disabled, false );
} );

test( 'the default ports 80 and 443 are allowed without a localized site port', () => {
	const validate = validator( 'example.com' );

	assert.equal( validate( 'https://example.com:80/post' ).invalidCount, 0 );
	assert.equal( validate( 'http://example.com:443/post' ).invalidCount, 0 );
} );

test( 'host case differences are folded on both sides without merging www', () => {
	const upperUrl = validator( 'example.com' )( 'https://EXAMPLE.com/post' );
	const upperHost = validator( 'EXAMPLE.com' )( 'https://example.com/post' );
	const www = validator( 'example.com' )( 'https://WWW.example.com/post' );

	assert.equal( upperUrl.validCount, 1 );
	assert.equal( upperHost.validCount, 1 );
	assert.equal( www.invalidCount, 1 );
	assert.equal( www.entries[ 0 ].reason, 'This URL is not on this site.' );
} );

test( 'an empty input returns the empty state', () => {
	const validate = validator( 'example.com' );
	const result = validate( '' );

	assert.equal( result.total, 0 );
	assert.equal( result.validCount, 0 );
	assert.equal( result.invalidCount, 0 );
	assert.equal( result.entries.length, 0 );
	assert.equal( result.summary, 'Enter at least one URL on this site.' );
	assert.equal( result.disabled, true );
} );

test( 'mixed input reports the correct invalid count', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://example.com/a\nhttps://evil.test/b\nnot a url\nhttps://example.com/c' );

	assert.equal( result.total, 4 );
	assert.equal( result.validCount, 2 );
	assert.equal( result.invalidCount, 2 );
	assert.equal(
		result.entries.map( ( entry ) => entry.valid ).join( ',' ),
		'true,false,false,true'
	);
	assert.equal( result.summary, '2 of 4 URLs are not valid. Fix them to continue.' );
	assert.equal( result.disabled, true );
} );

test( 'blank and duplicate lines are counted once', () => {
	const validate = validator( 'example.com' );
	const result = validate( 'https://example.com/a\n\nhttps://example.com/a\nhttps://example.com/b' );

	assert.equal( result.total, 2 );
	assert.equal( result.validCount, 2 );
	assert.equal( result.summary, '2 URLs ready to submit.' );
	assert.equal( result.entries[ 1 ].url, 'https://example.com/b' );
} );

function fakeNode( props ) {
	const listeners = {};
	let text = '';

	const node = Object.assign(
		{
			value: '',
			disabled: false,
			className: '',
			children: [],
			attrs: {},
			addEventListener( type, handler ) {
				listeners[ type ] = handler;
			},
			fire( type, event ) {
				if ( listeners[ type ] ) {
					listeners[ type ]( Object.assign( { target: node, preventDefault() {} }, event || {} ) );
				}
			},
			appendChild( child ) {
				this.children.push( child );
			},
			getAttribute( name ) {
				return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
			},
			setAttribute( name, value ) {
				this.attrs[ name ] = value;
			}
		},
		props
	);

	Object.defineProperty( node, 'textContent', {
		get() {
			return text;
		},
		set( value ) {
			text = String( value );
			node.children.length = 0;
		}
	} );

	return node;
}

function mount( value ) {
	const readyHandlers = [];
	const form = fakeNode( {} );
	const field = fakeNode( { value: value, form: form } );
	const status = fakeNode( {} );
	const button = fakeNode( {} );

	const document = {
		readyState: 'loading',
		addEventListener( type, handler ) {
			if ( 'DOMContentLoaded' === type ) {
				readyHandlers.push( handler );
			}
		},
		getElementById( id ) {
			if ( 'rankkernel-indexnow-urls' === id ) {
				return field;
			}
			if ( 'rankkernel-indexnow-urls-status' === id ) {
				return status;
			}
			if ( 'rankkernel-indexnow-submit' === id ) {
				return button;
			}

			return null;
		},
		createElement( tag ) {
			return fakeNode( { tagName: tag } );
		}
	};

	const sandbox = {
		document,
		URL,
		setTimeout( callback ) {
			callback();

			return 1;
		},
		clearTimeout() {},
		rankkernelInstantIndexing: { siteHost: 'example.com' }
	};

	sandbox.window = sandbox;
	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );
	readyHandlers.forEach( ( handler ) => handler() );

	return {
		button,
		field,
		status,
		submit() {
			let prevented = false;

			form.fire( 'submit', {
				preventDefault() {
					prevented = true;
				}
			} );

			return prevented;
		}
	};
}

test( 'the mounted script disables the button and blocks an invalid submit', () => {
	const fixture = mount( 'https://example.com/a\nhttps://evil.test/b' );

	assert.equal( fixture.button.disabled, true );
	assert.equal( fixture.status.children[ 0 ].textContent, '1 of 2 URLs are not valid. Fix them to continue.' );
	assert.equal( fixture.submit(), true, 'an invalid submit event must be prevented' );

	fixture.field.value = 'https://example.com/a\nhttps://example.com/b';
	fixture.field.fire( 'input' );

	assert.equal( fixture.button.disabled, false );
	assert.equal( fixture.status.children[ 0 ].textContent, '2 URLs ready to submit.' );
	assert.equal( fixture.submit(), false, 'a valid submit event must go through' );
} );

function noticeFixture( type ) {
	const listeners = {};
	const node = {
		className: 'rk-notice rk-notice-' + type,
		style: {},
		removed: false,
		remove() {
			this.removed = true;
		},
		addEventListener( eventType, handler ) {
			listeners[ eventType ] = handler;
		},
		fire( eventType, event ) {
			if ( listeners[ eventType ] ) {
				listeners[ eventType ]( event || {} );
			}
		},
		closest( selector ) {
			if ( '.rk-notice' === selector ) {
				return node;
			}

			return null;
		}
	};

	return node;
}

function loadWithNotices( types ) {
	const timers = [];
	const notices = types.map( noticeFixture );
	const buttons = notices.map( ( notice ) => {
		const button = {
			listeners: {},
			addEventListener( eventType, handler ) {
				this.listeners[ eventType ] = handler;
			},
			fire( eventType, event ) {
				this.listeners[ eventType ]( event );
			},
			notice
		};

		return button;
	} );

	const document = {
		readyState: 'complete',
		addEventListener() {},
		getElementById() {
			return null;
		},
		querySelectorAll( selector ) {
			if ( selector.indexOf( 'rk-notice-dismiss' ) !== -1 ) {
				return buttons;
			}

			return notices;
		},
		createElement() {
			return fakeNode( {} );
		}
	};

	const sandbox = {
		document,
		URL,
		rankkernelInstantIndexing: { siteHost: 'example.com' }
	};

	sandbox.window = sandbox;
	sandbox.window.setTimeout = ( callback, ms ) => {
		timers.push( { callback, ms } );

		return timers.length;
	};
	sandbox.window.clearTimeout = () => {};
	sandbox.window.matchMedia = () => ( { matches: false } );
	sandbox.setTimeout = sandbox.window.setTimeout;
	sandbox.clearTimeout = () => {};

	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );

	return { timers, notices, buttons };
}

test( 'a success notice is removed after the timer fires', () => {
	const { timers, notices } = loadWithNotices( [ 'success' ] );

	assert.equal( timers.length, 1 );
	assert.equal( timers[ 0 ].ms, 5000 );

	timers[ 0 ].callback();

	assert.equal( notices[ 0 ].removed, true );
} );

test( 'an error notice is never removed by the timer', () => {
	const { timers, notices } = loadWithNotices( [ 'error', 'success', 'info', 'warning' ] );

	assert.equal( timers.length, 3 );

	timers.forEach( ( timer ) => timer.callback() );

	assert.equal( notices[ 0 ].removed, false );
	assert.equal( notices[ 1 ].removed, true );
	assert.equal( notices[ 2 ].removed, true );
	assert.equal( notices[ 3 ].removed, true );
} );

test( 'manual dismissal still hides the notice', () => {
	const { buttons, notices } = loadWithNotices( [ 'success' ] );

	buttons[ 0 ].fire( 'click', { target: { closest: () => notices[ 0 ] } } );

	assert.equal( notices[ 0 ].removed, true );
} );

function toggleNode() {
	const listeners = {};
	const node = {
		attrs: { 'aria-expanded': 'false' },
		focused: false,
		addEventListener( eventType, handler ) {
			listeners[ eventType ] = handler;
		},
		fire( eventType, event ) {
			listeners[ eventType ]( event || {} );
		},
		setAttribute( name, value ) {
			this.attrs[ name ] = String( value );
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
		},
		focus() {
			this.focused = true;
		}
	};

	return node;
}

function panelNode() {
	const listeners = {};
	const node = {
		hidden: true,
		attrs: { hidden: '' },
		scrolled: false,
		addEventListener( eventType, handler ) {
			listeners[ eventType ] = handler;
		},
		fire( eventType, event ) {
			if ( listeners[ eventType ] ) {
				listeners[ eventType ]( event || {} );
			}
		},
		setAttribute( name, value ) {
			this.attrs[ name ] = String( value );
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
		},
		removeAttribute( name ) {
			delete this.attrs[ name ];
		},
		scrollIntoView() {
			this.scrolled = true;
		}
	};

	return node;
}

function hideLinkNode( panel, toggle ) {
	const listeners = {};
	const node = {
		panel,
		toggle,
		addEventListener( eventType, handler ) {
			listeners[ eventType ] = handler;
		},
		fire( eventType, event ) {
			listeners[ eventType ]( Object.assign( { preventDefault() {} }, event || {} ) );
		}
	};

	return node;
}

function openerNode( targetId ) {
	const listeners = {};
	const node = {
		attrs: { 'data-rk-open-panel': targetId },
		addEventListener( eventType, handler ) {
			listeners[ eventType ] = handler;
		},
		fire( eventType, event ) {
			listeners[ eventType ]( event || {} );
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
		}
	};

	return node;
}

function mountToggles() {
	const submitToggle = toggleNode();
	const submitPanel = panelNode();
	const submitHide = hideLinkNode( submitPanel, submitToggle );
	const settingsToggle = toggleNode();
	const settingsPanel = panelNode();
	const settingsHide = hideLinkNode( settingsPanel, settingsToggle );
	const helpToggle = toggleNode();
	const helpPanel = panelNode();
	const helpHide = hideLinkNode( helpPanel, helpToggle );
	const submitOpener = openerNode( 'rk-submit-panel' );
	const byId = {
		'rk-submit-toggle': submitToggle,
		'rk-submit-panel': submitPanel,
		'rk-submit-hide': submitHide,
		'rk-settings-toggle': settingsToggle,
		'rk-settings-panel': settingsPanel,
		'rk-settings-hide': settingsHide,
		'rk-help-toggle': helpToggle,
		'rk-help-panel': helpPanel,
		'rk-help-hide': helpHide
	};

	const document = {
		readyState: 'complete',
		addEventListener() {},
		getElementById( id ) {
			return byId[ id ] || null;
		},
		querySelectorAll( selector ) {
			if ( selector.indexOf( 'data-rk-open-panel' ) !== -1 ) {
				return [ submitOpener ];
			}

			return [];
		},
		createElement() {
			return fakeNode( {} );
		}
	};

	const sandbox = {
		document,
		URL,
		rankkernelInstantIndexing: { siteHost: 'example.com' }
	};

	sandbox.window = sandbox;
	sandbox.window.setTimeout = ( callback ) => {
		callback();

		return 1;
	};
	sandbox.window.clearTimeout = () => {};
	sandbox.window.matchMedia = () => ( { matches: true } );
	sandbox.setTimeout = sandbox.window.setTimeout;
	sandbox.clearTimeout = () => {};

	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );

	return { submitToggle, submitPanel, submitHide, submitOpener, settingsToggle, settingsPanel, settingsHide, helpToggle, helpPanel, helpHide };
}

test( 'the settings container opens on first activation and closes on second', () => {
	const { settingsToggle, settingsPanel } = mountToggles();

	assert.equal( settingsPanel.hidden, true );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'false' );

	settingsToggle.fire( 'click' );

	assert.equal( settingsPanel.hidden, false );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'true' );
	assert.equal( settingsPanel.scrolled, true );

	settingsToggle.fire( 'click' );

	assert.equal( settingsPanel.hidden, true );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'false' );
} );

test( 'the plain text close control closes the container', () => {
	const { settingsToggle, settingsPanel, settingsHide } = mountToggles();

	settingsToggle.fire( 'click' );

	assert.equal( settingsPanel.hidden, false );

	settingsHide.fire( 'click' );

	assert.equal( settingsPanel.hidden, true );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'false' );
} );

test( 'the help container toggles with the same pattern', () => {
	const { helpToggle, helpPanel, helpHide } = mountToggles();

	helpToggle.fire( 'click' );

	assert.equal( helpPanel.hidden, false );
	assert.equal( helpToggle.attrs[ 'aria-expanded' ], 'true' );

	helpHide.fire( 'click' );

	assert.equal( helpPanel.hidden, true );
	assert.equal( helpToggle.attrs[ 'aria-expanded' ], 'false' );
} );

test( 'all three panels start hidden with accurate expanded state', () => {
	const { submitToggle, submitPanel, settingsToggle, settingsPanel, helpToggle, helpPanel } = mountToggles();

	assert.equal( submitPanel.hidden, true );
	assert.equal( settingsPanel.hidden, true );
	assert.equal( helpPanel.hidden, true );
	assert.equal( submitToggle.attrs[ 'aria-expanded' ], 'false' );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'false' );
	assert.equal( helpToggle.attrs[ 'aria-expanded' ], 'false' );
} );

test( 'opening a second panel closes the first one', () => {
	const { settingsToggle, settingsPanel, helpToggle, helpPanel } = mountToggles();

	settingsToggle.fire( 'click' );

	assert.equal( settingsPanel.hidden, false );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'true' );

	helpToggle.fire( 'click' );

	assert.equal( helpPanel.hidden, false );
	assert.equal( helpToggle.attrs[ 'aria-expanded' ], 'true' );
	assert.equal( settingsPanel.hidden, true );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'false' );
} );

test( 'clicking the open control again returns to the all hidden state', () => {
	const { submitToggle, submitPanel, settingsPanel, helpPanel } = mountToggles();

	submitToggle.fire( 'click' );

	assert.equal( submitPanel.hidden, false );

	submitToggle.fire( 'click' );

	assert.equal( submitPanel.hidden, true );
	assert.equal( submitToggle.attrs[ 'aria-expanded' ], 'false' );
	assert.equal( settingsPanel.hidden, true );
	assert.equal( helpPanel.hidden, true );
} );

test( 'the empty log opener jumps straight to the submit panel', () => {
	const { submitOpener, submitToggle, submitPanel, settingsToggle, settingsPanel } = mountToggles();

	settingsToggle.fire( 'click' );

	assert.equal( settingsPanel.hidden, false );

	submitOpener.fire( 'click' );

	assert.equal( submitPanel.hidden, false );
	assert.equal( submitToggle.attrs[ 'aria-expanded' ], 'true' );
	assert.equal( settingsPanel.hidden, true );
	assert.equal( settingsToggle.attrs[ 'aria-expanded' ], 'false' );
} );
