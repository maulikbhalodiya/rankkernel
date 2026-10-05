'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );

function source( file ) {
	return readFileSync( path.join( root, 'assets', 'js', file ), 'utf8' );
}

function classList( initial ) {
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

function element( props ) {
	const attrs = Object.assign( {}, props.attrs || {} );
	const listeners = {};
	const dispatchedEvents = [];

	return Object.assign(
		{
			attrs,
			value: props.value || '',
			src: '',
			style: { display: props.display || '' },
			classList: classList( props.classes ),
			focused: false,
			dispatchedEvents,
			focus() {
				this.focused = true;
			},
			dispatchEvent( evt ) {
				dispatchedEvents.push( evt );
			},
			addEventListener( type, handler ) {
				listeners[ type ] = handler;
			},
			fire( type, event ) {
				if ( listeners[ type ] ) {
					listeners[ type ]( Object.assign( { target: this, preventDefault() {} }, event || {} ) );
				}
			},
			getAttribute( name ) {
				return Object.prototype.hasOwnProperty.call( attrs, name ) ? attrs[ name ] : null;
			},
			setAttribute( name, value ) {
				attrs[ name ] = value;
			},
			removeAttribute( name ) {
				delete attrs[ name ];
			},
			closest( selector ) {
				if ( selector.startsWith( '#' ) && this.getAttribute( 'id' ) === selector.slice( 1 ) ) {
					return this;
				}

				if ( '.rk-settings-nav a' === selector && this.getAttribute( 'href' ) ) {
					return this;
				}

				if ( selector.startsWith( '.' ) && this.classList.contains( selector.slice( 1 ) ) ) {
					return this;
				}

				return null;
			},
			querySelector() {
				return null;
			},
			querySelectorAll() {
				return [];
			}
		},
		props
	);
}

function socialNodes() {
	const image = element( { attrs: { id: 'rk-social-default-image' } } );
	const id = element( { attrs: { id: 'rk-social-default-image-id' }, value: '' } );
	const preview = element( { attrs: { id: 'rk-social-default-image-preview' } } );
	const select = element( { attrs: { id: 'rk-social-default-image-select' } } );
	const remove = element( { attrs: { id: 'rk-social-default-image-remove' } } );

	return { image, id, preview, select, remove };
}

function settingsFixture() {
	const readyHandlers = [];
	const clickHandlers = [];
	const current = {};
	const form = { submit() {} };
	const body = {
		innerHTML: '',
		classList: classList(),
		closest( selector ) {
			return 'form' === selector ? form : null;
		}
	};
	const general = element( {
		attrs: { href: 'https://example.test/wp-admin/admin.php?page=rankkernel-general&section=general' },
		classes: [ 'is-active' ]
	} );
	const social = element( {
		attrs: { href: 'https://example.test/wp-admin/admin.php?page=rankkernel-general&section=social' }
	} );
	const links = [ general, social ];
	const shell = {
		querySelector( selector ) {
			if ( '.rk-settings' === selector ) {
				return shell;
			}
			if ( '.rk-settings-body' === selector ) {
				return body;
			}
			if ( '.rk-settings-nav a.is-active' === selector ) {
				return links.find( ( link ) => link.classList.contains( 'is-active' ) ) || null;
			}

			return null;
		},
		querySelectorAll( selector ) {
			return '.rk-settings-nav a' === selector ? links : [];
		},
		addEventListener( type, handler ) {
			if ( 'click' === type ) {
				clickHandlers.push( { target: shell, handler } );
			}
		}
	};

	Object.defineProperty( body, 'innerHTML', {
		get() {
			return this._html || '';
		},
		set( html ) {
			this._html = html;
			Object.keys( current ).forEach( ( key ) => delete current[ key ] );

			if ( html.includes( 'social' ) ) {
				Object.assign( current, socialNodes() );
			}
		}
	} );

	const document = {
		readyState: 'loading',
		createEvent() {
			return {
				initEvent( type ) {
					this.type = type;
				}
			};
		},
		addEventListener( type, handler ) {
			if ( 'DOMContentLoaded' === type ) {
				readyHandlers.push( handler );
			}
			if ( 'click' === type ) {
				clickHandlers.push( { target: document, handler } );
			}
		},
		querySelector( selector ) {
			return '.rk-settings' === selector ? shell : null;
		},
		querySelectorAll() {
			return [];
		},
		getElementById( id ) {
			return Object.values( current ).find( ( node ) => node.getAttribute( 'id' ) === id ) || null;
		}
	};

	const frame = {
		opened: 0,
		on( type, handler ) {
			if ( 'select' === type ) {
				this.selectHandler = handler;
			}
		},
		open() {
			this.opened += 1;
		},
		state() {
			return {
				get() {
					return {
						first() {
							return frame.attachment;
						}
					};
				}
			};
		},
		attachment: {
			get( name ) {
				return name === 'id' ? 101 : 'https://example.test/first.jpg';
			}
		}
	};

	const spoken = [];
	const wp = {
		media() {
			return frame;
		},
		a11y: {
			speak( text ) {
				spoken.push( text );
			}
		},
		i18n: {
			__( text ) {
				return text;
			},
			sprintf( format, arg ) {
				return String( format || '' ).replace( '%s', String( arg || '' ) );
			}
		}
	};

	const fetchCalls = [];
	const location = { href: 'https://example.test/wp-admin/admin.php?page=rankkernel-general&section=general' };
	const sandbox = {
		document,
		Event: typeof Event !== 'undefined' ? Event : class Event { constructor( type ) { this.type = type; } },
		FormData: class {
			constructor() {}
			set() {}
		},
		URL,
		history: { pushState() {} },
		location,
		confirm: () => true,
		addEventListener() {},
		wp,
		fetch( url ) {
			fetchCalls.push( url );

			return Promise.resolve( {
				ok: true,
				text: () => Promise.resolve( url.includes( 'social' ) ? '<div data-section="social"></div>' : '<div data-section="general"></div>' )
			} );
		}
	};

	sandbox.window = sandbox;
	vm.runInNewContext( source( 'settings-admin.js' ), sandbox );
	readyHandlers.forEach( ( handler ) => handler() );

	function click( node ) {
		clickHandlers
			.filter( ( entry ) => entry.target === document || entry.target === shell )
			.forEach( ( entry ) => entry.handler( { target: node, preventDefault() {} } ) );
	}

	async function swap( link ) {
		click( link );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}

	return { body, current, document, frame, general, social, click, swap, fetchCalls, location, spoken };
}

test( 'media picker remains bound after a settings section swap and a second interaction', async () => {
	const fixture = settingsFixture();

	await fixture.swap( fixture.social );

	assert.ok( fixture.current.select );
	fixture.click( fixture.current.select );
	assert.equal( typeof fixture.frame.selectHandler, 'function' );
	fixture.frame.selectHandler();

	assert.equal( fixture.current.image.value, 'https://example.test/first.jpg' );
	assert.equal( fixture.current.id.value, '101' );
	assert.equal( fixture.frame.opened, 1 );

	fixture.frame.attachment = {
		get( name ) {
			return name === 'id' ? 202 : 'https://example.test/second.jpg';
		}
	};
	await fixture.swap( fixture.general );
	await fixture.swap( fixture.social );
	fixture.click( fixture.current.select );
	fixture.frame.selectHandler();

	assert.equal( fixture.current.image.value, 'https://example.test/second.jpg' );
	assert.equal( fixture.current.id.value, '202' );
	assert.equal( fixture.frame.opened, 2 );
	assert.equal( fixture.fetchCalls.length, 3 );
} );

test( 'a nav link with a malformed href falls back to normal navigation', () => {
	const fixture = settingsFixture();
	const badHref = 'http://[invalid';

	const badLink = element( { attrs: { href: badHref } } );

	assert.doesNotThrow( () => {
		fixture.click( badLink );
	}, 'a malformed href must not kill the click handler' );

	assert.equal( fixture.location.href, badHref, 'the guard falls back to navigation instead of dying silently' );
} );

test( 'token chip click inserts token at cursor position, dispatches input event, and announces via wp.a11y', () => {
	const fixture = settingsFixture();

	const input = element( {
		attrs: { id: 'rk-title-template' },
		value: 'Site - '
	} );
	input.selectionStart = 7;
	input.selectionEnd = 7;

	const chip = element( {
		classes: [ 'rk-token-chip' ],
		attrs: {
			'data-token': '%%title%%',
			'data-target': 'rk-title-template'
		}
	} );

	fixture.current.input = input;

	fixture.click( chip );

	assert.equal( input.value, 'Site - %%title%%' );
	assert.equal( input.selectionStart, 16 );
	assert.equal( input.selectionEnd, 16 );
	assert.equal( input.focused, true );
	assert.equal( input.dispatchedEvents.length, 1 );
	assert.equal( input.dispatchedEvents[0].type, 'input' );
	assert.deepEqual( fixture.spoken, [ 'Inserted token %%title%%.' ] );
} );

test( 'token chip click appends token when selection range is absent', () => {
	const fixture = settingsFixture();

	const input = element( {
		attrs: { id: 'rk-desc-template' },
		value: 'My Description '
	} );

	const chip = element( {
		classes: [ 'rk-token-chip' ],
		attrs: {
			'data-token': '%%excerpt%%',
			'data-target': 'rk-desc-template'
		}
	} );

	fixture.current.input = input;

	fixture.click( chip );

	assert.equal( input.value, 'My Description %%excerpt%%' );
	assert.equal( input.focused, true );
	assert.equal( input.dispatchedEvents.length, 1 );
	assert.equal( input.dispatchedEvents[0].type, 'input' );
	assert.deepEqual( fixture.spoken, [ 'Inserted token %%excerpt%%.' ] );
} );
