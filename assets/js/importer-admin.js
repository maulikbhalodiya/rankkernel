/**
 * Importer admin page script.
 *
 * Drives the Detect plus Preview plus Confirm plus Run screen through
 * the import REST endpoint. Configuration is injected by
 * wp_localize_script under window.rankkernelImporter: restBase plus
 * restNonce.
 *
 * Preview pages per kind so large datasets never flood the browser.
 * Exclusions live in a Set keyed by row identity, so selections
 * survive paging. Nothing writes until the user confirms the summary
 * and the chunk loop starts.
 */

/* global fetch */
( function () {
	'use strict';

	var cfg = window.rankkernelImporter || {};

	var __ = ( window.wp && window.wp.i18n && 'function' === typeof window.wp.i18n.__ )
		? window.wp.i18n.__
		: function ( text ) { return text; };

	var KINDS = [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk' ];
	var TITLES = { posts: 'Posts', terms: 'Terms', sitewide: 'Sitewide', redirects: 'Redirects', bulk: 'Bulk' };
	var PREFIXES = { posts: 'post:', terms: 'term:', redirects: 'redirect:' };
	var PAGE = 50;
	var CHUNK = 100;

	var state = {
		source: '',
		running: false,
		confirmed: false,
		excludes: {},
		pages: {},
		sitewide: { rows: [], selected: {} },
		bulk: { rows: [], selected: [] },
		kinds: {},
		report: null
	};

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

	function excludeList() {
		return Object.keys( state.excludes ).filter( function ( key ) {
			return Object.prototype.hasOwnProperty.call( state.excludes, key );
		} );
	}

	function rowHtml( row ) {
		var notes = [];
		var conflicts = row.conflicts || {};
		Object.keys( conflicts ).forEach( function () { notes.push( esc( __( 'Conflicts with the existing value.', 'rankkernel' ) ) ); } );
		( row.warnings || [] ).forEach( function ( w ) { notes.push( esc( w ) ); } );
		if ( row.skippedReason ) {
			notes.push( esc( row.skippedReason ) );
		}
		var checked = state.excludes[ row.identity ] ? ' checked' : '';
		var disabled = row.skippedReason ? ' disabled' : '';
		var check = '<label><input type="checkbox" data-rk-importer-exclude value="' + esc( row.identity ) + '"' + checked + disabled + '> ' + esc( __( 'Exclude', 'rankkernel' ) ) + '</label>';
		return '<tr><td>' + esc( row.label ) + '</td><td>' + esc( row.kind ) + '</td><td>' + notes.join( '<br>' ) + '</td><td>' + check + '</td></tr>';
	}

	function pagerHtml( kind, page ) {
		var html = '<div class="rk-importer-pager" data-rk-importer-pager="' + esc( kind ) + '">';
		html += '<span>' + esc( __( 'Showing ', 'rankkernel' ) + page.rows.length + __( ' of ', 'rankkernel' ) + page.kindTotal ) + '</span>';
		if ( page.offset > 0 ) {
			html += ' <button type="button" data-rk-importer-page="' + esc( kind ) + ':' + Math.max( 0, page.offset - PAGE ) + '">' + esc( __( 'Previous', 'rankkernel' ) ) + '</button>';
		}
		if ( page.hasMore ) {
			html += ' <button type="button" data-rk-importer-page="' + esc( kind ) + ':' + ( page.offset + PAGE ) + '">' + esc( __( 'Next', 'rankkernel' ) ) + '</button>';
		}
		return html + '</div>';
	}

	function renderKindSection( root, kind, title ) {
		var mount = root.querySelector( '[data-rk-importer-section="' + kind + '"]' );
		if ( ! mount ) {
			return;
		}
		var page = state.pages[ kind ];
		if ( ! page ) {
			mount.innerHTML = '';
			return;
		}
		var html = '<h3>' + esc( title ) + ' (' + page.kindTotal + ')</h3>';
		html += '<table class="widefat"><tbody>';
		page.rows.forEach( function ( row ) { html += rowHtml( row ); } );
		html += '</tbody></table>';
		html += pagerHtml( kind, page );
		mount.innerHTML = html;
	}

	function renderSitewide( root ) {
		var mount = root.querySelector( '[data-rk-importer-section="sitewide"]' );
		if ( ! mount ) {
			return;
		}
		var bySlot = {};
		state.sitewide.rows.forEach( function ( row ) {
			if ( row.slot ) {
				( bySlot[ row.slot ] = bySlot[ row.slot ] || [] ).push( row );
			}
		} );
		var html = '<h3>' + esc( __( 'Sitewide templates', 'rankkernel' ) ) + '</h3>';
		html += '<p>' + esc( __( 'RankKernel holds one global template per slot. Pick at most one candidate per slot, or leave the slot unchanged.', 'rankkernel' ) ) + '</p>';
		Object.keys( bySlot ).forEach( function ( slot ) {
			html += '<fieldset><legend>' + esc( slot ) + '</legend>';
			html += '<label><input type="radio" name="rk-importer-slot-' + esc( slot ) + '" value="" data-rk-importer-slot="' + esc( slot ) + '"' + ( state.sitewide.selected[ slot ] ? '' : ' checked' ) + '> ' + esc( __( 'Leave unchanged', 'rankkernel' ) ) + '</label>';
			bySlot[ slot ].forEach( function ( row ) {
				var label = row.label + ( row.recommended ? ' ' + __( '(recommended)', 'rankkernel' ) : '' );
				html += '<label><input type="radio" name="rk-importer-slot-' + esc( slot ) + '" value="' + esc( row.identity ) + '" data-rk-importer-slot="' + esc( slot ) + '"' + ( state.sitewide.selected[ slot ] === row.identity ? ' checked' : '' ) + '> ' + esc( label ) + '</label>';
			} );
			html += '</fieldset>';
		} );
		mount.innerHTML = html;
	}

	function renderBulk( root ) {
		var mount = root.querySelector( '[data-rk-importer-section="bulk"]' );
		if ( ! mount ) {
			return;
		}
		var html = '<h3>' + esc( __( 'Bulk noindex rules', 'rankkernel' ) ) + '</h3>';
		html += '<p>' + esc( __( 'Tick only the rules to apply. Unticked rules change nothing.', 'rankkernel' ) ) + '</p>';
		state.bulk.rows.forEach( function ( row ) {
			var checked = state.bulk.selected.indexOf( row.identity ) !== -1 ? ' checked' : '';
			html += '<label><input type="checkbox" data-rk-importer-rule value="' + esc( row.identity ) + '"' + checked + '> ' + esc( row.label ) + ' (' + ( row.affected || 0 ) + ')</label>';
		} );
		mount.innerHTML = html;
	}

	function renderSkipped( root, skipped ) {
		var mount = root.querySelector( '[data-rk-importer-section="skipped"]' );
		if ( ! mount ) {
			return;
		}
		if ( ! skipped.length ) {
			mount.innerHTML = '';
			return;
		}
		var html = '<h3>' + esc( __( 'Not imported', 'rankkernel' ) ) + ' (' + skipped.length + ')</h3><ul>';
		skipped.forEach( function ( row ) {
			html += '<li>' + esc( ( row.label || row.kind ) + ': ' + ( row.skippedReason || '' ) ) + '</li>';
		} );
		mount.innerHTML = html + '</ul>';
	}

	function previewKind( root, source, kind, offset ) {
		return post( cfg.restBase + '/' + source + '/preview', { mode: mode( root ), kind: kind, offset: offset, limit: PAGE } ).then( function ( data ) {
			state.pages[ kind ] = {
				rows: data.rows || [],
				skipped: data.skipped || [],
				offset: data.offset || 0,
				hasMore: !! data.hasMore,
				kindTotal: data.kindTotal || 0
			};
			return data;
		} );
	}

	function preview( root, source ) {
		resetState( source );
		return previewKind( root, source, 'posts', 0 ).then( function () {
			return previewKind( root, source, 'terms', 0 );
		} ).then( function () {
			return previewKind( root, source, 'redirects', 0 );
		} ).then( function () {
			return previewKind( root, source, 'sitewide', 0 );
		} ).then( function ( sitewide ) {
			state.sitewide.rows = sitewide.rows || [];
			return previewKind( root, source, 'bulk', 0 );
		} ).then( function ( bulk ) {
			state.bulk.rows = bulk.rows || [];
			renderKindSection( root, 'posts', __( 'Posts', 'rankkernel' ) );
			renderKindSection( root, 'terms', __( 'Terms', 'rankkernel' ) );
			renderKindSection( root, 'redirects', __( 'Redirects', 'rankkernel' ) );
			renderSitewide( root );
			renderBulk( root );
			var skipped = [];
			[ 'posts', 'terms', 'redirects', 'sitewide' ].forEach( function ( kind ) {
				( state.pages[ kind ].skipped || [] ).forEach( function ( row ) { skipped.push( row ); } );
			} );
			renderSkipped( root, skipped );
			resetConfirm( root );
			announce( root, __( 'Preview ready. Review the sections, then run the import.', 'rankkernel' ) );
		} ).catch( function () {
			fail( root, __( 'Preview request failed.', 'rankkernel' ) );
		} );
	}

	function resetState( source ) {
		state.source = source;
		state.running = false;
		state.confirmed = false;
		state.excludes = {};
		state.pages = {};
		state.sitewide = { rows: [], selected: {} };
		state.bulk = { rows: [], selected: [] };
		state.kinds = {};
		state.report = null;
	}

	function selectedRuleCount() {
		return state.bulk.selected.length;
	}

	function confirmHtml() {
		var lines = [];
		lines.push( __( 'Source: ', 'rankkernel' ) + state.source );
		[ 'posts', 'terms', 'redirects' ].forEach( function ( kind ) {
			var page = state.pages[ kind ];
			if ( page ) {
				lines.push( kind + ': ' + page.kindTotal + __( ' eligible, ', 'rankkernel' ) + excludeCountFor( kind ) + __( ' excluded', 'rankkernel' ) );
			}
		} );
		var sitewide = state.pages.sitewide;
		if ( sitewide ) {
			lines.push( __( 'Sitewide templates: ', 'rankkernel' ) + sitewide.rows.length + __( ' candidates, ', 'rankkernel' ) + Object.keys( state.sitewide.selected ).length + __( ' chosen', 'rankkernel' ) );
		}
		lines.push( __( 'Bulk rules chosen: ', 'rankkernel' ) + selectedRuleCount() );
		lines.push( __( 'Exclusions: ', 'rankkernel' ) + excludeList().length );
		return '<ul><li>' + lines.map( esc ).join( '</li><li>' ) + '</li></ul>';
	}

	function excludeCountFor( kind ) {
		var prefix = PREFIXES[ kind ] || ( kind + ':' );
		var count = 0;
		excludeList().forEach( function ( identity ) {
			if ( identity.indexOf( prefix ) === 0 ) {
				count += 1;
			}
		} );
		return count;
	}

	function resetConfirm( root ) {
		state.confirmed = false;
		var panel = root.querySelector( '[data-rk-importer-confirm]' );
		if ( panel ) {
			panel.innerHTML = '';
		}
		var runBtn = root.querySelector( '[data-rk-importer-run]' );
		if ( runBtn ) {
			runBtn.textContent = __( 'Run import', 'rankkernel' );
		}
	}

	function showConfirm( root ) {
		state.confirmed = true;
		var panel = root.querySelector( '[data-rk-importer-confirm]' );
		if ( panel ) {
			panel.innerHTML = '<h3>' + esc( __( 'Confirm import', 'rankkernel' ) ) + '</h3>' + confirmHtml();
		}
		var runBtn = root.querySelector( '[data-rk-importer-run]' );
		if ( runBtn ) {
			runBtn.textContent = __( 'Confirm and run', 'rankkernel' );
		}
		announce( root, __( 'Review the summary, then confirm to start the import.', 'rankkernel' ) );
	}

	function collectCheckedExcludes( root ) {
		var boxes = root.querySelectorAll( '[data-rk-importer-exclude]:checked' );
		for ( var i = 0; i < boxes.length; i++ ) {
			if ( boxes[ i ].value ) {
				state.excludes[ boxes[ i ].value ] = true;
			}
		}
	}

	function setProgress( root, pct ) {
		var bar = root.querySelector( '[data-rk-importer-bar]' );
		if ( bar ) {
			bar.setAttribute( 'style', 'width: ' + pct + '%' );
		}
	}

	function kindTotal( kind ) {
		var page = state.pages[ kind ];
		if ( page ) {
			return page.kindTotal;
		}
		if ( 'bulk' === kind ) {
			var total = 0;
			state.bulk.rows.forEach( function ( row ) {
				if ( state.bulk.selected.indexOf( row.identity ) !== -1 ) {
					total += row.affected || 0;
				}
			} );
			return total;
		}
		return 0;
	}

	function overallTotal() {
		var total = 0;
		KINDS.forEach( function ( kind ) {
			total += kindTotal( kind );
		} );
		return total;
	}

	function overallProcessed() {
		var done = 0;
		KINDS.forEach( function ( kind ) {
			var info = state.kinds[ kind ];
			if ( info ) {
				done += info.processed;
			}
		} );
		return done;
	}

	function updateProgress( root ) {
		var total = overallTotal();
		if ( total > 0 ) {
			setProgress( root, Math.min( 100, Math.round( ( overallProcessed() / total ) * 100 ) ) );
		}
	}

	function runKind( root, source, kind, offset, options ) {
		var body = {
			kind: kind,
			offset: offset,
			limit: CHUNK,
			mode: mode( root ),
			excludes: excludeList()
		};
		if ( options.choices ) {
			body.choices = options.choices;
		}
		if ( options.rule ) {
			body.rule = options.rule;
		}
		return post( cfg.restBase + '/' + source + '/run', body ).then( function ( data ) {
			var info = state.kinds[ kind ] || { processed: 0, applied: 0, skipped: 0, failed: 0 };
			info.processed += Math.min( CHUNK, Math.max( 0, ( data.total || 0 ) - info.processed ) );
			info.applied += data.applied || 0;
			info.skipped += data.skipped || 0;
			info.failed += data.failed || 0;
			state.kinds[ kind ] = info;
			updateProgress( root );
			if ( data.done ) {
				announce( root, kind + ': ' + ( data.applied || 0 ) + __( ' applied, ', 'rankkernel' ) + ( data.skipped || 0 ) + __( ' skipped, ', 'rankkernel' ) + ( data.failed || 0 ) + __( ' failed.', 'rankkernel' ) );
				return info;
			}
			return runKind( root, source, kind, offset + CHUNK, options );
		} );
	}

	function runPlan( root, source ) {
		var chain = Promise.resolve();
		[ 'posts', 'terms', 'sitewide', 'redirects' ].forEach( function ( kind ) {
			chain = chain.then( function () {
				var options = {};
				if ( 'sitewide' === kind ) {
					options.choices = state.sitewide.selected;
				}
				return runKind( root, source, kind, 0, options );
			} );
		} );
		state.bulk.selected.forEach( function ( identity ) {
			chain = chain.then( function () {
				return runKind( root, source, 'bulk', 0, { rule: identity } );
			} );
		} );
		return chain;
	}

	function finalReport( root ) {
		var lines = [];
		KINDS.forEach( function ( kind ) {
			var info = state.kinds[ kind ];
			if ( info ) {
				lines.push( kind + ': ' + info.applied + __( ' applied, ', 'rankkernel' ) + info.skipped + __( ' skipped, ', 'rankkernel' ) + info.failed + __( ' failed.', 'rankkernel' ) );
			}
		} );
		return '<ul><li>' + lines.map( esc ).join( '</li><li>' ) + '</li></ul>';
	}

	function run( root, runBtn ) {
		if ( state.running ) {
			return Promise.resolve();
		}
		var source = sourceFor( root, runBtn );
		if ( ! source || source !== state.source ) {
			return Promise.resolve();
		}
		collectCheckedExcludes( root );
		if ( ! state.confirmed ) {
			showConfirm( root );
			return Promise.resolve();
		}
		state.running = true;
		runBtn.disabled = true;
		state.kinds = {};
		setProgress( root, 0 );
		return runPlan( root, source ).then( function () {
			setProgress( root, 100 );
			var panel = root.querySelector( '[data-rk-importer-confirm]' );
			if ( panel ) {
				panel.innerHTML = '<h3>' + esc( __( 'Import complete', 'rankkernel' ) ) + '</h3>' + finalReport( root );
			}
			announce( root, __( 'Import complete.', 'rankkernel' ) );
		} ).catch( function () {
			var panel = root.querySelector( '[data-rk-importer-confirm]' );
			if ( panel ) {
				panel.innerHTML = '<h3>' + esc( __( 'Import stopped', 'rankkernel' ) ) + '</h3>' + finalReport( root );
			}
			fail( root, __( 'Import run failed partway. Re-run to resume the remaining chunks.', 'rankkernel' ) );
		} ).then( function () {
			state.running = false;
			runBtn.disabled = false;
			resetConfirmPartial( root );
		} );
	}

	function resetConfirmPartial( root ) {
		state.confirmed = false;
		var runBtn = root.querySelector( '[data-rk-importer-run]' );
		if ( runBtn ) {
			runBtn.textContent = __( 'Run import', 'rankkernel' );
		}
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

	function onPager( root, spec ) {
		var parts = String( spec ).split( ':' );
		var kind = parts[ 0 ];
		var offset = parseInt( parts[ 1 ], 10 ) || 0;
		previewKind( root, state.source, kind, offset ).then( function () {
			renderKindSection( root, kind, TITLES[ kind ] || kind );
		} ).catch( function () {
			fail( root, __( 'Preview request failed.', 'rankkernel' ) );
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
		root.addEventListener( 'click', function ( event ) {
			var target = event && event.target;
			if ( ! target || ! target.getAttribute ) {
				return;
			}
			var page = target.getAttribute( 'data-rk-importer-page' );
			if ( page ) {
				onPager( root, page );
			}
		} );
		root.addEventListener( 'change', function ( event ) {
			var target = event && event.target;
			if ( ! target || ! target.getAttribute ) {
				return;
			}
			if ( target.getAttribute( 'data-rk-importer-exclude' ) !== null ) {
				if ( target.checked ) {
					state.excludes[ target.value ] = true;
				} else {
					delete state.excludes[ target.value ];
				}
				resetConfirm( root );
				return;
			}
			if ( target.getAttribute( 'data-rk-importer-slot' ) !== null ) {
				var slot = target.getAttribute( 'data-rk-importer-slot' );
				if ( target.value ) {
					state.sitewide.selected[ slot ] = target.value;
				} else {
					delete state.sitewide.selected[ slot ];
				}
				resetConfirm( root );
				return;
			}
			if ( target.getAttribute( 'data-rk-importer-rule' ) !== null ) {
				var at = state.bulk.selected.indexOf( target.value );
				if ( target.checked && at === -1 ) {
					state.bulk.selected.push( target.value );
				}
				if ( ! target.checked && at !== -1 ) {
					state.bulk.selected.splice( at, 1 );
				}
				resetConfirm( root );
			}
		} );
		var modes = root.querySelectorAll( 'input[name="rk-importer-mode"]' );
		for ( var m = 0; m < modes.length; m++ ) {
			( function ( radio ) {
				radio.addEventListener( 'change', function () {
					resetConfirm( root );
				} );
			} )( modes[ m ] );
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
