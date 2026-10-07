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
