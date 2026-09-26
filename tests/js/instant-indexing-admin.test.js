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
		className: 'rk-ui-notice rk-ui-notice-' + type,
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
			if ( '.rk-ui-notice' === selector ) {
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
			if ( selector.indexOf( 'rk-ui-notice-dismiss' ) !== -1 ) {
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

/**
 * Log loader harness, a minimal fake DOM around the Submission history
 * card plus controllable fetch, history and location doubles.
 *
 * Every fake element throws on any innerHTML access, so a test fails the
 * moment the script writes markup instead of textContent. Selectors cover
 * exactly the hooks the loader uses: the GET filter form, the log card,
 * the tabs, the table, the showing label and the pagination.
 */

function logTextNode( text ) {
	return {
		nodeType: 3,
		textContent: String( text ),
		parentNode: null,
		children: []
	};
}

function logClassList( node ) {
	function parts() {
		return String( node.className || '' ).split( /\s+/ ).filter( ( part ) => '' !== part );
	}

	return {
		add( value ) {
			const seen = new Set( parts() );

			seen.add( value );
			node.className = [ ...seen ].join( ' ' );
		},
		remove( value ) {
			node.className = parts().filter( ( part ) => part !== value ).join( ' ' );
		},
		toggle( value, force ) {
			const has = parts().includes( value );
			const next = 'undefined' === typeof force ? ! has : !! force;

			if ( next ) {
				logClassList( node ).add( value );
			} else {
				logClassList( node ).remove( value );
			}

			return next;
		},
		contains( value ) {
			return parts().includes( value );
		}
	};
}

function logParseToken( token ) {
	const out = { tag: null, classes: [], attrName: null, attrValue: null };
	let rest = token;

	const attrStart = rest.indexOf( '[' );

	if ( -1 !== attrStart ) {
		const attrEnd = rest.indexOf( ']', attrStart );
		const body = rest.substring( attrStart + 1, attrEnd );
		const cut = body.indexOf( '=' );

		if ( -1 === cut ) {
			out.attrName = body;
		} else {
			out.attrName = body.substring( 0, cut );

			let raw = body.substring( cut + 1 );

			if ( raw.length >= 2 && '"' === raw[ 0 ] && '"' === raw[ raw.length - 1 ] ) {
				raw = raw.substring( 1, raw.length - 1 );
			}

			out.attrValue = raw;
		}

		rest = rest.substring( 0, attrStart );
	}

	const segments = rest.split( '.' );

	if ( '' !== segments[ 0 ] ) {
		out.tag = segments[ 0 ].toUpperCase();
	}

	for ( let i = 1; i < segments.length; i++ ) {
		if ( '' !== segments[ i ] ) {
			out.classes.push( segments[ i ] );
		}
	}

	return out;
}

function logMatchesToken( node, token ) {
	if ( ! node || ! node.tagName ) {
		return false;
	}

	const parsed = logParseToken( token );

	if ( parsed.tag && node.tagName !== parsed.tag ) {
		return false;
	}

	for ( const name of parsed.classes ) {
		if ( ! node.classList || ! node.classList.contains( name ) ) {
			return false;
		}
	}

	if ( parsed.attrName ) {
		const actual = node.getAttribute ? node.getAttribute( parsed.attrName ) : null;

		if ( null === parsed.attrValue ) {
			if ( null === actual ) {
				return false;
			}
		} else if ( actual !== parsed.attrValue ) {
			return false;
		}
	}

	return true;
}

function logMatches( node, selector ) {
	const tokens = String( selector || '' ).split( /\s+/ ).filter( Boolean );

	if ( 0 === tokens.length ) {
		return false;
	}

	if ( ! logMatchesToken( node, tokens[ tokens.length - 1 ] ) ) {
		return false;
	}

	let ancestor = node.parentNode;

	for ( let i = tokens.length - 2; i >= 0; i-- ) {
		let found = false;

		while ( ancestor ) {
			if ( logMatchesToken( ancestor, tokens[ i ] ) ) {
				found = true;
				ancestor = ancestor.parentNode;
				break;
			}

			ancestor = ancestor.parentNode;
		}

		if ( ! found ) {
			return false;
		}
	}

	return true;
}

function logQueryAll( root, selector ) {
	const out = [];

	function walk( node ) {
		for ( const child of node.children || [] ) {
			if ( logMatches( child, selector ) ) {
				out.push( child );
			}

			walk( child );
		}
	}

	walk( root );

	return out;
}

function logEl( tag, className, attrs ) {
	const listeners = {};
	const el = {
		tagName: String( tag || 'div' ).toUpperCase(),
		className: className || '',
		attrs: Object.assign( {}, attrs || {} ),
		children: [],
		parentNode: null,
		value: '',
		disabled: false,
		_text: '',
		classList: null,
		addEventListener( type, handler ) {
			listeners[ type ] = listeners[ type ] || [];
			listeners[ type ].push( handler );
		},
		fire( type, event ) {
			( listeners[ type ] || [] ).forEach( ( handler ) => handler( event || {} ) );
		},
		appendChild( child ) {
			child.parentNode = el;
			el.children.push( child );

			return child;
		},
		insertBefore( child, ref ) {
			child.parentNode = el;

			const at = ref ? el.children.indexOf( ref ) : -1;

			if ( -1 === at ) {
				el.children.push( child );
			} else {
				el.children.splice( at, 0, child );
			}

			return child;
		},
		removeChild( child ) {
			const at = el.children.indexOf( child );

			if ( -1 !== at ) {
				el.children.splice( at, 1 );
				child.parentNode = null;
			}

			return child;
		},
		replaceChild( fresh, old ) {
			const at = el.children.indexOf( old );

			if ( -1 !== at ) {
				el.children[ at ] = fresh;
				fresh.parentNode = el;
				old.parentNode = null;
			}

			return old;
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( el.attrs, name ) ? el.attrs[ name ] : null;
		},
		setAttribute( name, value ) {
			el.attrs[ name ] = String( value );
		},
		removeAttribute( name ) {
			delete el.attrs[ name ];
		},
		hasAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( el.attrs, name );
		},
		querySelector( selector ) {
			const found = logQueryAll( el, selector );

			return found.length ? found[ 0 ] : null;
		},
		querySelectorAll( selector ) {
			return logQueryAll( el, selector );
		},
		closest( selector ) {
			let node = el;

			while ( node ) {
				if ( logMatches( node, selector ) ) {
					return node;
				}

				node = node.parentNode;
			}

			return null;
		},
		submit() {}
	};

	el.classList = logClassList( el );

	Object.defineProperty( el, 'textContent', {
		get() {
			if ( el.children.length ) {
				return el.children.map( ( child ) => child.textContent ).join( '' );
			}

			return el._text;
		},
		set( value ) {
			el._text = String( value );
			el.children.forEach( ( child ) => {
				child.parentNode = null;
			} );
			el.children.length = 0;
		}
	} );

	Object.defineProperty( el, 'innerHTML', {
		get() {
			throw new Error( 'innerHTML must never be read by the log loader' );
		},
		set() {
			throw new Error( 'innerHTML must never be written by the log loader' );
		}
	} );

	return el;
}

const LOG_ACTION = 'https://example.com/wp-admin/admin.php?page=rankkernel-instant-indexing';
const LOG_REST = 'https://example.com/wp-json/rankkernel/v1/instant-indexing/log';
const LOG_TAB_KEYS = [ 'all', 'accepted', 'pending', 'rejected', 'limited' ];
const LOG_TAB_LABELS = [ 'All', 'Accepted', 'Key pending', 'Rejected', 'Rate limited' ];

function logTabUrl( status ) {
	if ( 'all' === status ) {
		return LOG_ACTION;
	}

	return LOG_ACTION + '&rk_status=' + status;
}

function logRowFixture( row ) {
	const tr = logEl( 'tr' );
	const url = logEl( 'td', 'rk-col-url' );

	url.textContent = row.url;

	const statusCell = logEl( 'td', 'rk-col-status' );
	const statusPill = logEl( 'span', 'rk-ui-pill rk-ui-pill-success' );

	statusPill.textContent = 'Accepted';
	statusCell.appendChild( statusPill );

	const sourceCell = logEl( 'td', 'rk-col-source' );
	const sourcePill = logEl( 'span', 'rk-ui-pill rk-ui-pill-neutral' );

	sourcePill.textContent = 'Auto';
	sourceCell.appendChild( sourcePill );

	const time = logEl( 'td', 'rk-col-time' );

	time.textContent = row.time || '2026-09-25 10:00';

	const message = logEl( 'td', 'rk-col-message' );

	message.textContent = row.message || 'Accepted.';

	tr.appendChild( url );
	tr.appendChild( statusCell );
	tr.appendChild( sourceCell );
	tr.appendChild( time );
	tr.appendChild( message );

	return tr;
}

function mountLog( options ) {
	const opts = options || {};
	const windowListeners = {};
	const pushes = [];
	const requests = [];
	const pending = [];

	const location = { href: opts.locationHref || LOG_ACTION };
	const history = {
		pushState( state, title, url ) {
			pushes.push( String( url ) );
			location.href = String( url );
		}
	};

	const card = logEl( 'div', 'rk-log-card' );

	const header = logEl( 'div', 'rk-ui-card-header' );
	const entryCount = logEl( 'span', 'rk-entry-count' );

	entryCount.textContent = '7 entries';
	header.appendChild( entryCount );
	card.appendChild( header );

	const stats = logEl( 'div', 'rk-stats' );
	const statSpecs = [
		[ 'rk-stat-value', '7' ],
		[ 'rk-stat-value rk-stat-value-positive', '3' ],
		[ 'rk-stat-value rk-stat-value-negative', '2' ],
		[ 'rk-stat-value rk-stat-value-warning', '1' ]
	];

	statSpecs.forEach( ( spec ) => {
		const value = logEl( 'div', spec[ 0 ] );

		value.textContent = spec[ 1 ];
		stats.appendChild( value );
	} );
	card.appendChild( stats );

	const form = logEl( 'form', 'rk-log-filters', { action: LOG_ACTION, method: 'get' } );
	const pageInput = logEl( 'input', '', { type: 'hidden', name: 'page' } );

	pageInput.value = 'rankkernel-instant-indexing';

	const statusInput = logEl( 'input', '', { type: 'hidden', name: 'rk_status' } );

	statusInput.value = opts.status || 'all';

	const searchInput = logEl( 'input', 'rk-ui-search-input', { type: 'search', name: 's', id: 'rk-log-search' } );

	searchInput.value = opts.search || '';

	const sourceInput = logEl( 'select', 'rk-ui-select', { name: 'rk_source', id: 'rk-log-source' } );

	sourceInput.value = opts.source || 'all';

	const submitInput = logEl( 'input', '', { type: 'submit' } );

	submitInput.value = 'Filter';
	form.appendChild( pageInput );
	form.appendChild( statusInput );
	form.appendChild( searchInput );
	form.appendChild( sourceInput );
	form.appendChild( submitInput );

	const toolbar = logEl( 'div', 'rk-log-toolbar' );

	toolbar.appendChild( form );
	card.appendChild( toolbar );

	const tabsNav = logEl( 'nav', 'rk-ui-tabs' );
	const tabCounts = opts.tabCounts || [ 7, 3, 1, 2, 1 ];

	const tabs = LOG_TAB_KEYS.map( ( key, index ) => {
		const current = ( opts.status || 'all' ) === key;
		const link = logEl( 'a', 'rk-ui-tab' + ( current ? ' is-current' : '' ), { href: logTabUrl( key ) } );

		if ( current ) {
			link.setAttribute( 'aria-current', 'page' );
		}

		link.appendChild( logTextNode( LOG_TAB_LABELS[ index ] + ' ' ) );

		const count = logEl( 'span', 'rk-ui-count' );

		count.textContent = String( tabCounts[ index ] );
		link.appendChild( count );
		tabsNav.appendChild( link );

		return link;
	} );

	card.appendChild( tabsNav );

	const wrap = logEl( 'div', 'rk-ui-table-wrap' );
	const table = logEl( 'table', 'rk-ui-table' );
	const tbody = logEl( 'tbody' );

	( opts.rows || [ { url: 'https://example.com/old' } ] ).forEach( ( row ) => {
		tbody.appendChild( logRowFixture( row ) );
	} );
	table.appendChild( tbody );
	wrap.appendChild( table );
	card.appendChild( wrap );

	const footer = logEl( 'div', 'rk-log-footer' );
	const showing = logEl( 'span', 'rk-showing' );

	showing.textContent = 'Showing 1 to 20 of 45 entries';
	footer.appendChild( showing );

	const pageNums = logEl( 'div', 'rk-ui-page-nums', { role: 'navigation', 'aria-label': 'Submission log pages' } );
	const prevOff = logEl( 'span', 'rk-ui-page-link is-disabled' );

	prevOff.textContent = 'Previous';
	prevOff.setAttribute( 'aria-disabled', 'true' );
	pageNums.appendChild( prevOff );

	const currentSpan = logEl( 'span', 'rk-ui-page-link is-current' );

	currentSpan.textContent = '1';
	currentSpan.setAttribute( 'aria-current', 'page' );
	pageNums.appendChild( currentSpan );

	[ 2, 3 ].forEach( ( number ) => {
		const link = logEl( 'a', 'rk-ui-page-link', { href: LOG_ACTION + '&rk_paged=' + number } );

		link.textContent = String( number );
		pageNums.appendChild( link );
	} );

	const next = logEl( 'a', 'rk-ui-page-link', { href: LOG_ACTION + '&rk_paged=2' } );

	next.textContent = 'Next';
	pageNums.appendChild( next );
	footer.appendChild( pageNums );
	card.appendChild( footer );

	const document = {
		readyState: 'complete',
		children: [],
		parentNode: null,
		addEventListener() {},
		getElementById( id ) {
			const found = [];

			function walk( node ) {
				for ( const child of node.children || [] ) {
					if ( child.getAttribute && child.getAttribute( 'id' ) === id ) {
						found.push( child );
					}

					walk( child );
				}
			}

			walk( document );

			return found.length ? found[ 0 ] : null;
		},
		querySelector( selector ) {
			const found = logQueryAll( document, selector );

			return found.length ? found[ 0 ] : null;
		},
		querySelectorAll( selector ) {
			return logQueryAll( document, selector );
		},
		createElement( tag ) {
			return logEl( tag );
		},
		createTextNode( text ) {
			return logTextNode( text );
		},
		appendChild( child ) {
			child.parentNode = document;
			document.children.push( child );

			return child;
		}
	};

	document.appendChild( card );

	const fetchImpl = opts.fetchImpl || null;
	const sandbox = {
		document,
		URL,
		encodeURIComponent,
		decodeURIComponent,
		rankkernelInstantIndexing: {
			siteHost: 'example.com',
			sitePort: '',
			logUrl: 'logUrl' in opts ? opts.logUrl : LOG_REST,
			restNonce: 'restNonce' in opts ? opts.restNonce : 'test-rest-nonce',
			retryNonce: 'retryNonce' in opts ? opts.retryNonce : 'test-retry-nonce'
		},
		location,
		history,
		setTimeout( callback ) {
			callback();

			return 1;
		},
		clearTimeout() {},
		matchMedia() {
			return { matches: false };
		}
	};

	sandbox.window = sandbox;
	sandbox.window.addEventListener = ( type, handler ) => {
		windowListeners[ type ] = windowListeners[ type ] || [];
		windowListeners[ type ].push( handler );
	};
	sandbox.fetch = ( url, fetchOptions ) => {
		const entry = { url: String( url ), options: fetchOptions };

		requests.push( entry );

		if ( fetchImpl ) {
			return fetchImpl( entry, pending.length );
		}

		const gate = {};
		const promise = new Promise( ( resolve, reject ) => {
			gate.resolve = resolve;
			gate.reject = reject;
		} );

		gate.promise = promise;
		gate.entry = entry;
		pending.push( gate );

		return promise;
	};

	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );

	function submit() {
		let prevented = false;

		form.fire( 'submit', {
			preventDefault() {
				prevented = true;
			}
		} );

		return prevented;
	}

	function click( node ) {
		let prevented = false;

		card.fire( 'click', {
			target: node,
			preventDefault() {
				prevented = true;
			}
		} );

		return prevented;
	}

	return {
		card,
		form,
		searchInput,
		sourceInput,
		statusInput,
		submitInput,
		entryCount,
		tabs,
		tbody: () => card.querySelector( '.rk-ui-table tbody' ),
		footer: () => card.querySelector( '.rk-log-footer' ),
		showing: () => card.querySelector( '.rk-showing' ),
		pageNums: () => card.querySelector( '.rk-ui-page-nums' ),
		location,
		pushes,
		requests,
		pending,
		windowListeners,
		submit,
		click
	};
}

function logJson( data, ok ) {
	return {
		ok: ok === undefined ? true : !! ok,
		status: ok === false ? 500 : 200,
		json() {
			return Promise.resolve( data );
		}
	};
}

async function logFlush( rounds ) {
	for ( let i = 0; i < ( rounds || 8 ); i++ ) {
		await Promise.resolve();
		await new Promise( ( resolve ) => setImmediate( resolve ) );
	}
}

function logPayload( overrides ) {
	return Object.assign(
		{
			rows: [
				{ id: 101, url: 'https://example.com/a', host: 'example.com', code: 200, source: 'auto', time: '2026-09-25 10:00', message: 'Accepted.' },
				{ id: 102, url: 'https://example.com/b', host: 'example.com', code: 403, source: 'manual', time: '2026-09-25 09:00', message: 'Rejected permanently, retrying will not help.' }
			],
			page: 1,
			perPage: 20,
			filteredTotal: 45,
			total: 9,
			statusCounts: { all: 7, accepted: 3, pending: 1, rejected: 2, limited: 1, retry: 0 },
			sourceCounts: { auto: 4, manual: 3 }
		},
		overrides || {}
	);
}

test( 'a log filter submit is intercepted and never navigates', () => {
	const fixture = mountLog( { search: 'hello', source: 'manual', status: 'rejected' } );

	assert.equal( fixture.submit(), true, 'the submit event must be prevented' );
	assert.equal( fixture.requests.length, 1 );
	assert.equal( fixture.location.href, LOG_ACTION, 'no navigation may happen while JavaScript handles it' );

	const sent = fixture.requests[ 0 ];

	assert.ok( sent.url.indexOf( 's=hello' ) !== -1, 'the request carries the search' );
	assert.ok( sent.url.indexOf( 'rk_source=manual' ) !== -1, 'the request carries the source' );
	assert.ok( sent.url.indexOf( 'rk_status=rejected' ) !== -1, 'the request carries the status' );
	assert.ok( sent.url.indexOf( 'rk_paged' ) === -1, 'page one stays dropped like the server URLs' );
	assert.equal( sent.options.headers[ 'X-WP-Nonce' ], 'test-rest-nonce', 'the nonce travels in the header' );
	assert.equal( sent.options.credentials, 'same-origin' );
} );

test( 'a log pagination click requests the correct page', () => {
	const fixture = mountLog( { search: 'hello', source: 'manual', status: 'rejected' } );
	const pageTwo = fixture.pageNums().children.filter( ( node ) => 'A' === node.tagName && '2' === node.textContent )[ 0 ];

	assert.ok( pageTwo, 'the fixture must render a page two link' );
	assert.equal( fixture.click( pageTwo ), true, 'the click must be prevented' );
	assert.equal( fixture.requests.length, 1 );
	assert.ok( fixture.requests[ 0 ].url.indexOf( 'rk_paged=2' ) !== -1, 'the request carries the page' );
	assert.ok( fixture.requests[ 0 ].url.indexOf( 's=hello' ) !== -1, 'the request keeps the search' );
	assert.ok( fixture.requests[ 0 ].url.indexOf( 'rk_source=manual' ) !== -1, 'the request keeps the source' );
	assert.ok( fixture.requests[ 0 ].url.indexOf( 'rk_status=rejected' ) !== -1, 'the request keeps the status' );
} );

test( 'a successful log response updates rows, counts, tabs, stats and pagination', async () => {
	const fixture = mountLog( { search: 'hello', source: 'manual', status: 'all' } );

	fixture.pending.length = 0;
	fixture.submit();

	assert.equal( fixture.requests.length, 1 );

	fixture.pending[ 0 ].resolve( logJson( logPayload() ) );
	await logFlush();

	const rows = fixture.tbody().children;

	assert.equal( rows.length, 2 );

	const urlCell = rows[ 0 ].children[ 0 ];

	assert.equal( urlCell.textContent, 'https://example.com/a' );
	assert.equal( urlCell.children.length, 0, 'the URL cell must hold text only' );

	const statusPill = rows[ 1 ].children[ 1 ].children[ 0 ];

	assert.equal( statusPill.textContent, 'Rejected' );
	assert.equal( statusPill.className, 'rk-ui-pill rk-ui-pill-danger' );

	const sourcePill = rows[ 1 ].children[ 2 ].children[ 0 ];

	assert.equal( sourcePill.textContent, 'Manual' );
	assert.equal( sourcePill.className, 'rk-ui-pill rk-pill-source-manual' );

	assert.equal( fixture.entryCount.textContent, '9 entries' );
	assert.equal( fixture.card.querySelector( '.rk-stat-value-positive' ).textContent, '4', 'accepted covers accepted plus pending' );
	assert.equal( fixture.card.querySelector( '.rk-stat-value-negative' ).textContent, '2' );
	assert.equal( fixture.card.querySelector( '.rk-stat-value-warning' ).textContent, '1' );

	const tabCounts = fixture.card.querySelectorAll( '.rk-ui-tabs .rk-ui-tab' ).map( ( tab ) => tab.querySelector( '.rk-ui-count' ).textContent );

	assert.deepEqual( tabCounts, [ '7', '3', '1', '2', '1' ] );
	assert.ok( fixture.tabs[ 0 ].getAttribute( 'href' ).indexOf( 's=hello' ) !== -1, 'tab hrefs follow the new search' );

	assert.equal( fixture.showing().textContent, 'Showing 1 to 20 of 45 entries' );

	const current = fixture.pageNums().children.filter( ( node ) => 'SPAN' === node.tagName && node.classList.contains( 'is-current' ) )[ 0 ];

	assert.equal( current.textContent, '1' );

	const pageTwo = fixture.pageNums().children.filter( ( node ) => 'A' === node.tagName && '2' === node.textContent )[ 0 ];

	assert.ok( pageTwo.getAttribute( 'href' ).indexOf( 'rk_paged=2' ) !== -1 );

	assert.equal( fixture.pushes.length, 1, 'the filtered view is pushed once' );
	assert.ok( fixture.pushes[ 0 ].indexOf( 's=hello' ) !== -1 );

	assert.equal( fixture.searchInput.disabled, false, 'controls are usable again' );
	assert.equal( fixture.sourceInput.disabled, false );
	assert.equal( fixture.submitInput.disabled, false );
	assert.equal( fixture.card.getAttribute( 'aria-busy' ), null );
} );

test( 'a slow earlier log response never overwrites a newer one', async () => {
	const fixture = mountLog();

	fixture.submit();
	fixture.click( fixture.pageNums().children.filter( ( node ) => 'A' === node.tagName && '2' === node.textContent )[ 0 ] );

	assert.equal( fixture.requests.length, 2 );
	assert.ok( fixture.requests[ 1 ].url.indexOf( 'rk_paged=2' ) !== -1 );

	fixture.pending[ 1 ].resolve( logJson( logPayload( { rows: [ { url: 'https://example.com/page-two', host: 'example.com', code: 200, source: 'auto', time: '2026-09-25 10:00', message: 'Accepted.' } ] } ) ) );
	await logFlush();

	fixture.pending[ 0 ].resolve( logJson( logPayload( { rows: [ { url: 'https://example.com/page-one', host: 'example.com', code: 200, source: 'auto', time: '2026-09-25 10:00', message: 'Accepted.' } ] } ) ) );
	await logFlush();

	assert.equal( fixture.tbody().children[ 0 ].children[ 0 ].textContent, 'https://example.com/page-two', 'the stale first response must not win' );
	assert.equal( fixture.pushes.length, 1, 'only the newer request pushes' );
	assert.ok( fixture.pushes[ 0 ].indexOf( 'rk_paged=2' ) !== -1 );
} );

test( 'a network failure falls back to a normal navigation with usable controls', async () => {
	const fixture = mountLog( { search: 'hello', source: 'manual' } );

	fixture.submit();

	assert.equal( fixture.searchInput.disabled, true, 'controls lock while in flight' );

	fixture.pending[ 0 ].reject( new Error( 'offline' ) );
	await logFlush();

	assert.ok( fixture.location.href.indexOf( 's=hello' ) !== -1, 'the fallback navigates to the filter URL' );
	assert.ok( fixture.location.href.indexOf( 'rk_source=manual' ) !== -1 );
	assert.equal( fixture.searchInput.disabled, false, 'controls unlock on failure' );
	assert.equal( fixture.sourceInput.disabled, false );
	assert.equal( fixture.submitInput.disabled, false );
	assert.equal( fixture.card.getAttribute( 'aria-busy' ), null );
	assert.equal( fixture.tbody().children.length, 1, 'the stale row stays until the navigation lands' );
} );

test( 'a non 2xx log response falls back instead of rendering', async () => {
	const fixture = mountLog( { search: 'hello' } );

	fixture.submit();
	fixture.pending[ 0 ].resolve( logJson( {}, false ) );
	await logFlush();

	assert.ok( fixture.location.href.indexOf( 's=hello' ) !== -1, 'the fallback navigates to the filter URL' );
	assert.equal( fixture.searchInput.disabled, false );
} );

test( 'an error code in the log body falls back instead of rendering', async () => {
	const fixture = mountLog( { search: 'hello' } );

	fixture.submit();
	fixture.pending[ 0 ].resolve( logJson( { code: 'rest_forbidden', message: 'Sorry.', data: { status: 403 } } ) );
	await logFlush();

	assert.ok( fixture.location.href.indexOf( 's=hello' ) !== -1, 'the fallback navigates to the filter URL' );
	assert.equal( fixture.tbody().children.length, 1, 'no error payload ever reaches the table' );
} );

test( 'a hostile log URL renders as literal text through textContent', async () => {
	const evil = '<img src=x onerror=alert(1)>https://example.com/a';
	const fixture = mountLog();

	fixture.submit();
	fixture.pending[ 0 ].resolve( logJson( logPayload( { rows: [ { url: evil, host: 'example.com', code: 0, source: 'manual', time: '<b>now</b>', message: '<script>alert(1)</script>' } ] } ) ) );
	await logFlush();

	const row = fixture.tbody().children[ 0 ];

	assert.equal( row.children[ 0 ].textContent, evil, 'markup stays literal text' );
	assert.equal( row.children[ 0 ].children.length, 0, 'no element may be parsed from the URL' );
	assert.equal( row.children[ 3 ].textContent, '<b>now</b>' );
	assert.equal( row.children[ 4 ].textContent, '<script>alert(1)</script>' );
	assert.equal( row.children[ 4 ].children.length, 0, 'no element may be parsed from the message' );
} );

test( 'an empty log response swaps the table for the empty filtered block', async () => {
	const fixture = mountLog();

	fixture.submit();
	fixture.pending[ 0 ].resolve( logJson( logPayload( { rows: [], filteredTotal: 0, statusCounts: { all: 0, accepted: 0, pending: 0, rejected: 0, limited: 0, retry: 0 } } ) ) );
	await logFlush();

	assert.equal( fixture.card.querySelector( '.rk-ui-table tbody' ), null, 'no stale table may remain' );
	assert.equal( fixture.card.querySelector( '.rk-log-footer' ), null, 'no stale footer may remain' );

	const empty = fixture.card.querySelector( '.rk-empty-filtered' );

	assert.ok( empty, 'the empty filtered block renders like the server path' );
	assert.ok( empty.textContent.indexOf( 'No submissions match your filters.' ) !== -1 );
	assert.equal( fixture.showing(), null );
} );

test( 'the log loader returns quietly when its elements are absent', () => {
	const document = {
		readyState: 'complete',
		children: [],
		parentNode: null,
		addEventListener() {},
		getElementById() {
			return null;
		},
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		},
		createElement( tag ) {
			return logEl( tag );
		},
		createTextNode( text ) {
			return logTextNode( text );
		}
	};
	const sandbox = {
		document,
		URL,
		encodeURIComponent,
		decodeURIComponent,
		rankkernelInstantIndexing: { siteHost: 'example.com', sitePort: '', logUrl: LOG_REST, restNonce: 'test-rest-nonce' },
		location: { href: LOG_ACTION },
		history: { pushState() {} },
		setTimeout( callback ) {
			callback();

			return 1;
		},
		clearTimeout() {},
		matchMedia() {
			return { matches: false };
		}
	};

	sandbox.window = sandbox;
	sandbox.window.addEventListener = () => {};
	sandbox.fetch = () => {
		throw new Error( 'fetch must never run without the log elements' );
	};

	vm.runInNewContext( source( 'instant-indexing-admin.js' ), sandbox );

	assert.equal( sandbox.window.rankkernelInstantIndexing.logLoader.categoryFor( 200 ), 'accepted', 'the pure helpers still load' );
} );

test( 'a log submit without the localized REST config navigates normally', () => {
	const fixture = mountLog( { logUrl: '', restNonce: '' } );

	assert.equal( fixture.submit(), false, 'no handler may prevent the plain GET submit' );
	assert.equal( fixture.requests.length, 0, 'no request may run without the REST config' );
} );

/**
 * AJAX rebuilt retry control.
 *
 * The server renders a retry form per retryable row, but the AJAX refresh
 * rebuilds every row from the REST payload. These tests pin the rebuilt
 * Actions cell so a search, a filter change or a page click can no longer
 * erase the retry control, and pin the input rule that only the row id
 * travels while the stored URL never does.
 */

function retryFields( form ) {
	const byName = {};

	form.querySelectorAll( 'input' ).forEach( ( field ) => {
		byName[ field.getAttribute( 'name' ) ] = field.getAttribute( 'value' );
	} );

	return byName;
}

function assertNoLinkOrUrl( form, url ) {
	const walk = ( node ) => {
		assert.equal( node.getAttribute( 'href' ), null, 'no node in the retry form may carry an href' );

		for ( const child of node.children || [] ) {
			walk( child );
		}
	};

	walk( form );

	assert.equal( String( form.textContent ).indexOf( url ), -1, 'the stored URL must never appear in the JS built retry form' );
	assert.equal( String( form.textContent ).indexOf( 'href' ), -1, 'no href text may reach the JS built retry form' );
}

test( 'an AJAX log render rebuilds the Actions cell with the retry form for a retryable row', async () => {
	const fixture = mountLog();

	fixture.submit();
	fixture.pending[ 0 ].resolve( logJson( logPayload() ) );
	await logFlush();

	const rows = fixture.tbody().children;
	const cell = rows[ 1 ].querySelector( '.rk-col-actions' );

	assert.ok( cell, 'a retryable row must carry an Actions cell after an AJAX render' );

	const form = cell.querySelector( '.rk-retry-form' );

	assert.ok( form, 'the Actions cell must carry the retry form the server renders' );
	assert.equal( form.getAttribute( 'method' ), 'post', 'the retry form must post' );
	assert.equal( form.getAttribute( 'action' ), '', 'the retry form action must stay empty' );

	const fields = retryFields( form );

	assert.equal( fields._wpnonce, 'test-retry-nonce', 'the form must carry the localized retry nonce' );
	assert.equal( fields.rankkernel_indexnow_action, 'retry', 'the form must carry the retry action marker' );
	assert.equal( fields.rankkernel_indexnow_id, '102', 'the form must carry the row id from the REST row' );
	assert.ok( form.querySelector( '.rk-retry-submit' ), 'the form must carry a submit control' );
} );

test( 'the JS built retry form carries only the row id and never the URL or a link', async () => {
	const payload = 'https://example.com/a?x=1&"<script>alert(1)</script>';
	const fixture = mountLog();

	fixture.submit();
	fixture.pending[ 0 ].resolve(
		logJson(
			logPayload(
				{
					rows: [
						{ id: 77, url: payload, host: 'example.com', code: 503, source: 'auto', time: '2026-09-25 10:00', message: 'Retry later.' }
					]
				}
			)
		)
	);
	await logFlush();

	const row = fixture.tbody().children[ 0 ];
	const cell = row.querySelector( '.rk-col-actions' );
	const form = row.querySelector( '.rk-retry-form' );

	assert.ok( cell, 'a 503 row must carry the Actions cell after an AJAX render' );
	assert.ok( form, 'a 503 row must carry the retry form after an AJAX render' );

	const names = form.querySelectorAll( 'input' ).map( ( field ) => field.getAttribute( 'name' ) );

	assert.equal( names.join( ',' ), '_wpnonce,rankkernel_indexnow_action,rankkernel_indexnow_id', 'the form must carry the nonce, the action marker and the row id only' );
	assert.equal( names.indexOf( 'rankkernel_indexnow_url' ), -1, 'the retry form must not carry a URL field' );
	assertNoLinkOrUrl( cell, payload );

	assert.equal( row.children[ 0 ].textContent, payload, 'the URL stays literal text in its own cell' );
	assert.equal( row.children[ 0 ].children.length, 0, 'no element may be parsed from the URL' );
} );

test( 'an empty to rows AJAX rebuild adds the Actions header and the retry cell', async () => {
	const fixture = mountLog();

	fixture.submit();
	fixture.pending[ 0 ].resolve( logJson( logPayload( { rows: [], filteredTotal: 0, statusCounts: { all: 0, accepted: 0, pending: 0, rejected: 0, limited: 0, retry: 0 } } ) ) );
	await logFlush();

	assert.equal( fixture.card.querySelector( '.rk-ui-table-wrap' ), null, 'the empty response must remove the table first' );

	fixture.pending.length = 0;
	fixture.submit();
	fixture.pending[ 0 ].resolve(
		logJson(
			logPayload(
				{
					rows: [
						{ id: 55, url: 'https://example.com/again', host: 'example.com', code: 503, source: 'auto', time: '2026-09-25 10:00', message: 'Retry later.' }
					]
				}
			)
		)
	);
	await logFlush();

	const wrap = fixture.card.querySelector( '.rk-ui-table-wrap' );

	assert.ok( wrap, 'the table must be rebuilt from empty to rows' );

	const header = wrap.querySelector( '.rk-col-actions' );

	assert.ok( header, 'the rebuilt table must carry the Actions column' );
	assert.equal( header.tagName, 'TH', 'the first actions node must be the header cell' );
	assert.equal( header.textContent, 'Actions' );

	const cell = fixture.tbody().children[ 0 ].querySelector( '.rk-col-actions' );

	assert.ok( cell, 'the rebuilt retryable row must carry the Actions cell' );
	assert.equal( cell.tagName, 'TD' );
	assert.ok( cell.querySelector( '.rk-retry-form' ), 'the rebuilt cell must carry the retry form' );
} );

test( 'an AJAX log render leaves accepted and pending rows without an Actions cell', async () => {
	const fixture = mountLog();

	fixture.submit();
	fixture.pending[ 0 ].resolve(
		logJson(
			logPayload(
				{
					rows: [
						{ id: 11, url: 'https://example.com/accepted', host: 'example.com', code: 200, source: 'auto', time: '2026-09-25 10:00', message: 'Accepted.' },
						{ id: 12, url: 'https://example.com/pending', host: 'example.com', code: 202, source: 'auto', time: '2026-09-25 09:00', message: 'Accepted, the key is pending verification.' }
					]
				}
			)
		)
	);
	await logFlush();

	const rows = fixture.tbody().children;

	assert.equal( rows.length, 2 );
	assert.equal( rows[ 0 ].querySelector( '.rk-col-actions' ), null, 'an accepted row must not carry an Actions cell' );
	assert.equal( rows[ 1 ].querySelector( '.rk-col-actions' ), null, 'a pending row must not carry an Actions cell' );
	assert.equal( rows[ 0 ].children.length, 5, 'an accepted row keeps the five server columns' );
	assert.equal( rows[ 1 ].children.length, 5, 'a pending row keeps the five server columns' );
} );

test( 'a retryable row without a row id carries no retry control', async () => {
	const fixture = mountLog();

	fixture.submit();
	fixture.pending[ 0 ].resolve(
		logJson(
			logPayload(
				{
					rows: [
						{ url: 'https://example.com/no-id', host: 'example.com', code: 503, source: 'auto', time: '2026-09-25 10:00', message: 'Retry later.' }
					]
				}
			)
		)
	);
	await logFlush();

	const row = fixture.tbody().children[ 0 ];

	assert.equal( row.querySelector( '.rk-col-actions' ), null, 'a row without an id cannot be addressed, so no control may render' );
	assert.equal( row.querySelector( '.rk-retry-form' ), null );
} );
