'use strict';

/**
 * Media picker translation wiring and tab indentation contract.
 *
 * schema-settings.js and settings-admin.js are classic DOM scripts, so each
 * one runs here in a vm sandbox with a minimal fake document. The tests pin
 * that the wp.media frame title and button text are passed through the
 * translation call at their call site, that the raw English literal still
 * ships when wp.i18n is missing, that every translation argument is a string
 * literal the WordPress extractor can read, and that the re-indented files
 * use tabs rather than spaces.
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
	const attrs = Object.assign( {}, ( props && props.attrs ) || {} );

	return Object.assign(
		{
			value: '',
			src: '',
			style: { display: '' },
			listeners: {},
			classList: {
				add() {},
				toggle() {}
			},
			addEventListener( type, handler ) {
				this.listeners[ type ] = handler;
			},
			fire( type, event ) {
				if ( this.listeners[ type ] ) {
					this.listeners[ type ]( Object.assign( { target: this, preventDefault() {} }, event || {} ) );
				}
			},
			getAttribute( name ) {
				return Object.prototype.hasOwnProperty.call( attrs, name ) ? attrs[ name ] : null;
			},
			setAttribute( name, value ) {
				attrs[ name ] = value;
			},
			closest() {
				return null;
			}
		},
		props || {}
	);
}

function mediaWp( i18n ) {
	const frames = [];
	const wp = {
		media( options ) {
			const frame = {
				options,
				opened: 0,
				on() {},
				open() {
					this.opened += 1;
				},
				state() {
					return {
						get() {
							return {
								first() {
									return null;
								}
							};
						}
					};
				}
			};

			frames.push( frame );

			return frame;
		}
	};

	if ( i18n ) {
		wp.i18n = i18n;
	}

	return { frames, wp };
}

function translating( seen ) {
	return {
		__( text, domain ) {
			seen.push( [ text, domain ] );

			return 'translated:' + text;
		}
	};
}

function runSchemaSettings( i18n ) {
	const nodes = {
		'rk-org-logo-select': fakeNode( { attrs: { id: 'rk-org-logo-select' } } ),
		'rk-org-logo': fakeNode( { attrs: { id: 'rk-org-logo' } } ),
		'rk-org-logo-preview': fakeNode( { attrs: { id: 'rk-org-logo-preview' } } )
	};
	const document = {
		readyState: 'complete',
		getElementById( id ) {
			return nodes[ id ] || null;
		},
		addEventListener() {}
	};
	const { frames, wp } = mediaWp( i18n );
	const sandbox = { document, wp };

	sandbox.window = sandbox;
	vm.runInNewContext( source( 'schema-settings.js' ), sandbox );

	return { frames, select: nodes[ 'rk-org-logo-select' ] };
}

function runSettingsAdmin( i18n ) {
	const clickHandlers = [];
	const form = { submit() {} };
	const body = {
		innerHTML: '',
		classList: { toggle() {} },
		closest( selector ) {
			return 'form' === selector ? form : null;
		}
	};
	const shell = {
		querySelector( selector ) {
			return '.rk-settings-body' === selector ? body : null;
		},
		querySelectorAll() {
			return [];
		},
		addEventListener( type, handler ) {
			if ( 'click' === type ) {
				clickHandlers.push( { target: shell, handler } );
			}
		}
	};
	const document = {
		readyState: 'complete',
		querySelector( selector ) {
			return '.rk-settings' === selector ? shell : null;
		},
		getElementById() {
			return null;
		},
		addEventListener( type, handler ) {
			if ( 'click' === type ) {
				clickHandlers.push( { target: document, handler } );
			}
		}
	};
	const { frames, wp } = mediaWp( i18n );
	const sandbox = {
		document,
		wp,
		addEventListener() {}
	};

	sandbox.window = sandbox;
	vm.runInNewContext( source( 'settings-admin.js' ), sandbox );

	const selectNode = {
		closest( selector ) {
			return '#rk-social-default-image-select' === selector ? selectNode : null;
		}
	};

	function click() {
		clickHandlers
			.filter( ( entry ) => entry.target === document || entry.target === shell )
			.forEach( ( entry ) => entry.handler( { target: selectNode, preventDefault() {} } ) );
	}

	return { click, frames };
}

test( 'schema-settings passes the media frame title and button text through wp.i18n', () => {
	const seen = [];
	const fixture = runSchemaSettings( translating( seen ) );

	fixture.select.fire( 'click' );

	assert.equal( fixture.frames.length, 1 );
	assert.equal( fixture.frames[0].options.title, 'translated:Select organization logo' );
	assert.equal( fixture.frames[0].options.button.text, 'translated:Use this image' );
	assert.deepEqual( seen, [
		[ 'Select organization logo', 'rankkernel' ],
		[ 'Use this image', 'rankkernel' ]
	] );
} );

test( 'schema-settings keeps the raw media frame strings when wp.i18n is absent', () => {
	const fixture = runSchemaSettings( undefined );

	assert.doesNotThrow( () => fixture.select.fire( 'click' ) );
	assert.equal( fixture.frames.length, 1 );
	assert.equal( fixture.frames[0].options.title, 'Select organization logo' );
	assert.equal( fixture.frames[0].options.button.text, 'Use this image' );
} );

test( 'settings-admin passes the media frame title and button text through wp.i18n', () => {
	const seen = [];
	const fixture = runSettingsAdmin( translating( seen ) );

	fixture.click();

	assert.equal( fixture.frames.length, 1 );
	assert.equal( fixture.frames[0].options.title, 'translated:Select default social image' );
	assert.equal( fixture.frames[0].options.button.text, 'translated:Use this image' );
	assert.deepEqual( seen, [
		[ 'Select default social image', 'rankkernel' ],
		[ 'Use this image', 'rankkernel' ]
	] );
} );

test( 'settings-admin keeps the raw media frame strings when wp.i18n is absent', () => {
	const fixture = runSettingsAdmin( undefined );

	assert.doesNotThrow( () => fixture.click() );
	assert.equal( fixture.frames.length, 1 );
	assert.equal( fixture.frames[0].options.title, 'Select default social image' );
	assert.equal( fixture.frames[0].options.button.text, 'Use this image' );
} );

test( 'each schema-settings translation argument is a literal the extractor can read', () => {
	const code = source( 'schema-settings.js' );
	const literals = [];
	const pattern = /__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'rankkernel'\s*\)/g;
	let match;

	while ( ( match = pattern.exec( code ) ) !== null ) {
		literals.push( match[ 1 ] );
	}

	assert.equal(
		( code.match( /__\(/g ) || [] ).length,
		literals.length,
		'must not pass a variable to a translation call'
	);
	assert.ok( literals.includes( 'Select organization logo' ) );
	assert.ok( literals.includes( 'Use this image' ) );
} );

test( 'the re-indented admin scripts indent with tabs, never spaces', () => {
	[ 'schema-metabox.js', 'schema-settings.js' ].forEach( ( file ) => {
		const code = source( file );
		const offenders = code
			.split( '\n' )
			.map( ( line, index ) => ( /^ +/.test( line ) && ! /^ \*/.test( line ) ? index + 1 : 0 ) )
			.filter( ( line ) => line > 0 );

		assert.deepEqual(
			offenders,
			[],
			file + ' has space-indented lines (docblock lines excepted): ' + offenders.join( ', ' )
		);
		assert.match( code, /^\t/m, file + ' must use a tab for at least one indent level' );
	} );
} );
