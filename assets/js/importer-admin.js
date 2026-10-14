/**
 * Importer admin page script.
 *
 * Drives the Detect plus Preview plus Run screen through the import REST
 * endpoint. Configuration is injected by wp_localize_script under
 * window.rankkernelImporter: restBase plus restNonce.
 */

/* global fetch */
( function () {
	'use strict';

	var cfg = window.rankkernelImporter || {};

	var __ = ( window.wp && window.wp.i18n && 'function' === typeof window.wp.i18n.__ )
		? window.wp.i18n.__
		: function ( text ) { return text; };

	var KINDS = [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk' ];
	var CHUNK = 100;

	function esc( text ) {
		return String( text ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ c ];
		} );
	}

	function announce( root, text ) {
		var region = root.querySelector( '[data-rk-importer-announce]' );
		if ( region ) {
			region.textContent = text;
		}
		if ( window.wp && window.wp.a11y && 'function' === typeof window.wp.a11y.speak ) {
			window.wp.a11y.speak( text, 'polite' );
		}
	}

	function fail( root, text ) {
		var box = root.querySelector( '[data-rk-importer-error]' );
		if ( box ) {
			box.innerHTML = esc( text );
		}
		announce( root, text );
	}

	function mode( root ) {
		var checked = root.querySelector( 'input[name="rk-importer-mode"]:checked' );
		var value = checked ? checked.value : 'skip';
		return 'overwrite' === value ? 'overwrite' : 'skip';
	}

	function post( url, body ) {
		return window.fetch( url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.restNonce || ''
			},
			body: JSON.stringify( body )
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Request failed with status ' + response.status );
			}
			return response.json();
		} );
	}

	function rowHtml( row ) {
		var notes = [];
		( row.conflicts || [] ).forEach( function ( c ) { notes.push( esc( c ) ); } );
		( row.warnings || [] ).forEach( function ( w ) { notes.push( esc( w ) ); } );
		if ( row.skippedReason ) {
			notes.push( esc( row.skippedReason ) );
		}
		var check = '<label><input type="checkbox" data-rk-importer-exclude value="' + esc( row.kind + ':' + row.refId ) + '"' + ( row.skippedReason ? ' disabled' : '' ) + '> ' + esc( __( 'Exclude', 'rankkernel' ) ) + '</label>';
		return '<tr><td>' + esc( row.label ) + '</td><td>' + esc( row.kind ) + '</td><td>' + notes.join( '<br>' ) + '</td><td>' + check + '</td></tr>';
	}

	function preview( root, source ) {
		var rows = root.querySelector( '[data-rk-importer-rows]' );
		return post( cfg.restBase + '/' + source + '/preview', { mode: mode( root ) } ).then( function ( data ) {
			var html = '';
			( data.rows || [] ).forEach( function ( row ) { html += rowHtml( row ); } );
			if ( rows ) {
				rows.innerHTML = html;
			}
			announce( root, __( 'Preview ready: ', 'rankkernel' ) + ( data.rows || [] ).length + __( ' rows.', 'rankkernel' ) );
			return data;
		} ).catch( function () {
			fail( root, __( 'Preview request failed.', 'rankkernel' ) );
		} );
	}

	function sourceFor( root, runBtn ) {
		var explicit = runBtn.getAttribute( 'data-rk-importer-source' );
		if ( explicit ) {
			return explicit;
		}
		if ( root.getAttribute( 'data-rk-importer-last' ) ) {
			return root.getAttribute( 'data-rk-importer-last' );
		}
		var first = root.querySelector( '[data-rk-importer-preview]' );
		if ( first ) {
			return first.getAttribute( 'data-rk-importer-preview' );
		}
		return '';
	}

	function setProgress( root, pct ) {
		var bar = root.querySelector( '[data-rk-importer-bar]' );
		if ( bar ) {
			bar.setAttribute( 'style', 'width: ' + pct + '%' );
		}
	}

	function runKind( root, source, kind, offset, state ) {
		var body = {
			kind: kind,
			offset: offset,
			limit: CHUNK,
			mode: mode( root ),
			excludes: state.excludes,
			choices: {},
			rule: {}
		};
		return post( cfg.restBase + '/' + source + '/run', body ).then( function ( data ) {
			state.applied += data.applied || 0;
			state.total = data.total || state.total;
			if ( state.total > 0 ) {
				setProgress( root, Math.min( 100, Math.round( ( state.applied / state.total ) * 100 ) ) );
			}
			if ( data.done ) {
				return state;
			}
			return runKind( root, source, kind, offset + CHUNK, state );
		} );
	}

	function run( root, runBtn ) {
		var source = sourceFor( root, runBtn );
		if ( ! source ) {
			return Promise.resolve();
		}
		var boxes = root.querySelectorAll( '[data-rk-importer-exclude]:checked' );
		var excludes = [];
		for ( var i = 0; i < boxes.length; i++ ) {
			excludes.push( boxes[ i ].value );
		}
		var state = { applied: 0, total: 0, excludes: excludes };
		var chain = Promise.resolve();
		KINDS.forEach( function ( kind ) {
			chain = chain.then( function () {
				return runKind( root, source, kind, 0, state );
			} );
		} );
		return chain.then( function () {
			setProgress( root, 100 );
			announce( root, __( 'Import complete: ', 'rankkernel' ) + state.applied + __( ' applied.', 'rankkernel' ) );
		} ).catch( function () {
			fail( root, __( 'Import run failed.', 'rankkernel' ) );
		} );
	}

	function bind( root ) {
		var previews = root.querySelectorAll( '[data-rk-importer-preview]' );
		for ( var i = 0; i < previews.length; i++ ) {
			( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var source = btn.getAttribute( 'data-rk-importer-preview' );
					root.setAttribute( 'data-rk-importer-last', source );
					preview( root, source );
				} );
			} )( previews[ i ] );
		}
		var runBtn = root.querySelector( '[data-rk-importer-run]' );
		if ( runBtn ) {
			runBtn.addEventListener( 'click', function () {
				run( root, runBtn );
			} );
		}
	}

	function init() {
		if ( 'undefined' === typeof window.fetch ) {
			return;
		}
		var root = document.querySelector( '[data-rk-importer-root]' );
		if ( ! root ) {
			return;
		}
		bind( root );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
