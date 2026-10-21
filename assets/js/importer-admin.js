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

	var KINDS = [ 'posts', 'terms', 'redirects', 'sitewide', 'bulk' ];
	var TITLES = { posts: 'Posts', terms: 'Terms', sitewide: 'Sitewide', redirects: 'Redirects', bulk: 'Bulk' };
	var PREFIXES = { posts: 'post:', terms: 'term:', redirects: 'redirect:' };
	var PREVIEW_LIMIT = 5000;
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
		report: null,
		currentKindIndex: -1,
		wizardView: 'kind',
		reviewed: {},
		visitedMax: -1,
		completed: false,
		queries: {},
		runId: null
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

	function setStep( root, id, className ) {
		var step = root.querySelector( '#' + id );
		if ( step ) {
			step.setAttribute( 'class', className );
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
				var err = new Error( 'Request failed with status ' + response.status );
				err.status = response.status;
				return response.json().catch( function () {
					return {};
				} ).then( function ( body ) {
					err.data = body;
					throw err;
				} );
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
		var name = esc( row.label );
		if ( row.title && row.editUrl ) {
			name = '<a href="' + esc( row.editUrl ) + '" target="_blank" rel="noopener">' + esc( row.title ) + '</a><br><span>' + esc( row.label ) + '</span>';
		}
		return '<tr><td>' + name + '</td><td>' + esc( row.kind ) + '</td><td>' + notes.join( '<br>' ) + '</td><td>' + check + '</td></tr>';
	}

	function loadedExcludedCount( kind ) {
		var page = state.pages[ kind ];
		if ( ! page ) {
			return 0;
		}
		var count = 0;
		( page.rows || [] ).forEach( function ( row ) {
			if ( state.excludes[ row.identity ] ) {
				count += 1;
			}
		} );
		return count;
	}

	function totalExcludedForKind( kind ) {
		var count = 0;
		excludeList().forEach( function ( identity ) {
			var prefix = kindPrefix( kind );
			if ( identity.indexOf( prefix ) === 0 ) {
				count += 1;
			}
		} );
		return count;
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
		var excludedCount = excludeCountFor( kind );
		var query = state.queries[ kind ] || '';
		var toolbar = '<div class="rk-importer-kind-toolbar">';
		toolbar += '<label><input type="checkbox" data-rk-importer-exclude-all data-rk-importer-exclude-all-toggle="1"' + ( ( page.rows || [] ).length > 0 && loadedExcludedCount( kind ) === ( page.rows || [] ).length ? ' checked' : '' ) + '> ' + esc( __( 'Exclude all', 'rankkernel' ) ) + '</label>';
		toolbar += ' <button type="button" data-rk-importer-clear data-rk-importer-clear-excludes="1">' + esc( __( 'Clear', 'rankkernel' ) ) + '</button>';
		if ( [ 'posts', 'terms', 'redirects' ].indexOf( kind ) !== -1 ) {
			toolbar += ' <input type="search" data-rk-importer-search="' + esc( kind ) + '" value="' + esc( query ) + '" placeholder="' + esc( __( 'Search', 'rankkernel' ) ) + '">';
			toolbar += ' <button type="button" data-rk-importer-search-clear="' + esc( kind ) + '">' + esc( __( 'Clear search', 'rankkernel' ) ) + '</button>';
		}
		toolbar += ' <span data-rk-importer-exclude-count="' + esc( kind ) + '">' + excludedCount + ' of ' + page.kindTotal + ' ' + esc( __( 'excluded', 'rankkernel' ) ) + '</span>';
		toolbar += '</div>';

		if ( ! ( page.rows || [] ).length ) {
			var emptyMsg = __( 'Nothing to review for this kind.', 'rankkernel' );
			if ( query ) {
				emptyMsg = __( 'No results for', 'rankkernel' ) + ' "' + query + '"';
			}
			mount.innerHTML = toolbar + '<p class="rk-importer-kind-empty">' + esc( emptyMsg ) + '</p>';
			return;
		}
		var html = toolbar + '<h3>' + esc( title ) + ' (' + page.kindTotal + ')</h3>';
		html += '<div class="rk-importer-row-scroll"><table class="widefat"><tbody>';
		page.rows.forEach( function ( row ) { html += rowHtml( row ); } );
		html += '</tbody></table></div>';
		html += '<p class="rk-importer-row-count">' + ( page.rows || [] ).length + ' ' + esc( __( 'rows, scroll to review', 'rankkernel' ) ) + '</p>';
		if ( page.hasMore ) {
			var capNote = query
				? __( 'This search shows at most ', 'rankkernel' ) + PREVIEW_LIMIT + __( ' rows (server cap). Clear the search to see the whole kind.', 'rankkernel' )
				: __( 'Only the first ', 'rankkernel' ) + PREVIEW_LIMIT + __( ' rows are shown (server cap). Use search to find specific rows.', 'rankkernel' );
			html += '<p class="rk-importer-truncation-note">' + esc( capNote ) + '</p>';
		}
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
		var html = '<h3>' + esc( __( 'Not imported', 'rankkernel' ) ) + ' (' + skipped.length + ')</h3><ul class="rk-importer-skipped-list">';
		skipped.forEach( function ( row ) {
			html += '<li>' + esc( ( row.label || row.kind ) + ': ' + ( row.skippedReason || '' ) ) + '</li>';
		} );
		mount.innerHTML = html + '</ul>';
	}

	function previewKind( root, source, kind, offset, q ) {
		var query = ( q === undefined ? ( state.queries[ kind ] || '' ) : q );
		state.queries[ kind ] = query;
		return post( cfg.restBase + '/' + source + '/preview', { mode: mode( root ), kind: kind, offset: 0, limit: PREVIEW_LIMIT, q: query } ).then( function ( data ) {
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

	function kindIndex( kind ) {
		return KINDS.indexOf( kind );
	}

	function wizardTitle( kind ) {
		return TITLES[ kind ] || kind;
	}

	function ensureWizardControls( root ) {
		var mount = root.querySelector( '[data-rk-importer-wizard-nav]' );
		if ( ! mount ) {
			if ( ! document || typeof document.createElement !== 'function' ) {
				return;
			}
			mount = document.createElement( 'div' );
			mount.className = 'rk-importer-wizard-nav';
			mount.setAttribute( 'data-rk-importer-wizard-nav', '1' );
			root.appendChild( mount );
		}
		var confirming = 'confirm' === state.wizardView;
		var first = ! confirming && 0 === state.currentKindIndex;
		var last = ! confirming && state.currentKindIndex === KINDS.length - 1;
		var html = '';
		if ( ! first ) {
			html += '<button type="button" data-rk-importer-back="1" data-rk-importer-prev="1">' + esc( __( 'Back', 'rankkernel' ) ) + '</button>';
		}
		if ( ! confirming ) {
			html += ' <button type="button" data-rk-importer-skip="1" data-rk-importer-skip-kind="1">' + esc( __( 'Skip kind', 'rankkernel' ) ) + '</button>';
		}
		if ( ! last && ! confirming ) {
			html += ' <button type="button" data-rk-importer-next="1" data-rk-importer-kind-next="1">' + esc( __( 'Next', 'rankkernel' ) ) + '</button>';
		}
		mount.innerHTML = html;
	}

	function renderWizardNav( root ) {
		var nav = root.querySelector( '[data-rk-importer-kind-nav]' ) || root.querySelector( '[data-rk-importer-kind-stepper]' );
		if ( ! nav ) {
			if ( ! document || typeof document.createElement !== 'function' ) {
				return;
			}
			nav = document.createElement( 'ol' );
			nav.className = 'rk-importer-kind-nav';
			nav.setAttribute( 'data-rk-importer-kind-nav', '1' );
			nav.setAttribute( 'data-rk-importer-kind-stepper', '1' );
			var firstSection = root.querySelector( '[data-rk-importer-section]' );
			if ( firstSection ) {
				root.insertBefore( nav, firstSection );
			} else {
				root.appendChild( nav );
			}
		}
		var html = '';
		KINDS.forEach( function ( kind, i ) {
			var count = 0;
			var page = state.pages[ kind ];
			if ( page ) {
				count = page.kindTotal;
			}
			var status = 'pending';
			if ( state.completed ) {
				status = 'done';
			} else if ( Object.prototype.hasOwnProperty.call( state.reviewed, kind ) ) {
				status = 'reviewed';
			}
			var current = ( state.wizardView === 'kind' && i === state.currentKindIndex );
			var visited = i <= state.visitedMax;
			html += '<li class="rk-importer-kind-step" data-rk-importer-kind-step="' + esc( kind ) + '" data-rk-importer-kind="' + esc( kind ) + '"' + ( current ? ' aria-current="step"' : '' ) + ( ( 'reviewed' === status || 'done' === status ) ? ' data-reviewed="1"' : '' ) + '>';
			html += '<button type="button" data-rk-importer-kind-jump="' + esc( kind ) + '" data-rk-importer-kind="' + esc( kind ) + '">' + esc( wizardTitle( kind ) ) + '</button>';
			html += ' <span class="rk-importer-kind-count">' + ( page ? count : '' ) + '</span>';
			html += ' <span class="rk-importer-kind-status">' + esc( current ? 'current' : status ) + '</span>';
			if ( 'reviewed' === status || 'done' === status ) {
				html += ' <span class="rk-importer-kind-check" aria-hidden="true">&#10003;</span>';
			}
			html += '</li>';
		} );
		nav.innerHTML = html;
	}

	function showKind( root, index ) {
		if ( index < 0 || index > KINDS.length ) {
			return;
		}
		if ( index === KINDS.length ) {
			state.wizardView = 'confirm';
			state.currentKindIndex = KINDS.length;
			KINDS.forEach( function ( kind ) {
				var mount = root.querySelector( '[data-rk-importer-section="' + kind + '"]' );
				if ( mount ) {
					mount.style.display = 'none';
				}
			} );
			renderWizardNav( root );
			ensureWizardControls( root );
			announce( root, __( 'Confirmation summary', 'rankkernel' ) );
			return;
		}
		state.wizardView = 'kind';
		state.currentKindIndex = index;
		if ( index > state.visitedMax ) {
			state.visitedMax = index;
		}
		KINDS.forEach( function ( kind, i ) {
			var mount = root.querySelector( '[data-rk-importer-section="' + kind + '"]' );
			if ( mount ) {
				mount.style.display = ( i === index ) ? '' : 'none';
			}
		} );
		var kind = KINDS[ index ];
		var title = wizardTitle( kind );
		renderKindSection( root, kind, title );
		if ( 'sitewide' === kind ) {
			renderSitewide( root );
		}
		if ( 'bulk' === kind ) {
			renderBulk( root );
		}
		renderWizardNav( root );
		ensureWizardControls( root );
		announce( root, kind + ': ' + ( state.pages[ kind ] ? state.pages[ kind ].kindTotal : 0 ) );
	}

	function advanceKind( root ) {
		if ( ! state.previewLoaded ) {
			return;
		}
		if ( state.wizardView === 'kind' ) {
			var current = KINDS[ state.currentKindIndex ];
			if ( current ) {
				state.reviewed[ current ] = true;
				var wasSkipped = !! state.skippedKinds[ current ];
				delete state.skippedKinds[ current ];
				if ( wasSkipped ) {
					resetConfirm( root );
				}
				announce( root, current + ' ' + __( 'reviewed', 'rankkernel' ) );
			}
		}
		var nextIndex = state.currentKindIndex + 1;
		if ( nextIndex > KINDS.length ) {
			nextIndex = KINDS.length;
		}
		showKind( root, nextIndex );
	}

	function prevKind( root ) {
		if ( ! state.previewLoaded ) {
			return;
		}
		if ( state.currentKindIndex <= 0 ) {
			return;
		}
		showKind( root, state.currentKindIndex - 1 );
	}

	function skipKind( root ) {
		if ( ! state.previewLoaded ) {
			return;
		}
		if ( state.wizardView !== 'kind' ) {
			return;
		}
		var current = KINDS[ state.currentKindIndex ];
		if ( current ) {
			state.reviewed[ current ] = true;
			state.skippedKinds[ current ] = true;
			resetConfirm( root );
			announce( root, current + ' ' + __( 'skipped, will not import', 'rankkernel' ) );
		}
		var nextIndex = state.currentKindIndex + 1;
		if ( nextIndex > KINDS.length ) {
			nextIndex = KINDS.length;
		}
		showKind( root, nextIndex );
	}

	function jumpKind( root, kind ) {
		if ( ! state.previewLoaded ) {
			return;
		}
		var idx = kindIndex( kind );
		if ( idx === -1 ) {
			return;
		}
		showKind( root, idx );
	}

	function applyExcludeAllLoaded( root, kind, checked ) {
		var page = state.pages[ kind ];
		if ( ! page ) {
			return;
		}
		( page.rows || [] ).forEach( function ( row ) {
			if ( checked ) {
				state.excludes[ row.identity ] = true;
			} else {
				delete state.excludes[ row.identity ];
			}
		} );
		renderKindSection( root, kind, wizardTitle( kind ) );
		if ( 'sitewide' === kind ) {
			renderSitewide( root );
		}
		if ( 'bulk' === kind ) {
			renderBulk( root );
		}
		resetConfirm( root );
		announce( root, excludeCountFor( kind ) + ' of ' + ( page.kindTotal || 0 ) + ' ' + __( 'excluded', 'rankkernel' ) );
	}

	function clearLoadedExcludes( root, kind ) {
		applyExcludeAllLoaded( root, kind, false );
	}

	function preview( root, source ) {
		var sameSource = ( state.source === source && '' !== state.source );
		var preserved = sameSource ? Object.assign( {}, state.excludes ) : null;
		resetState( source, sameSource );
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
			renderKindSection( root, 'sitewide', __( 'Sitewide', 'rankkernel' ) );
			renderSitewide( root );
			renderBulk( root );
			renderKindSection( root, 'bulk', __( 'Bulk', 'rankkernel' ) );
			var skipped = [];
			[ 'posts', 'terms', 'redirects', 'sitewide' ].forEach( function ( kind ) {
				if ( state.pages[ kind ] ) {
					( state.pages[ kind ].skipped || [] ).forEach( function ( row ) { skipped.push( row ); } );
				}
			} );
			renderSkipped( root, skipped );
			var dropped = 0;
			if ( preserved ) {
				[ 'posts', 'terms', 'redirects' ].forEach( function ( kind ) {
					var page = state.pages[ kind ];
					if ( ! page || page.hasMore ) {
						return;
					}
					var ids = {};
					( page.rows || [] ).forEach( function ( row ) { ids[ row.identity ] = true; } );
					var prefix = kindPrefix( kind );
					Object.keys( preserved ).forEach( function ( identity ) {
						if ( identity.indexOf( prefix ) !== 0 ) {
							return;
						}
						if ( ! ids[ identity ] && state.excludes[ identity ] ) {
							delete state.excludes[ identity ];
							dropped += 1;
						}
					} );
				} );
			}
			resetConfirm( root );
			if ( dropped > 0 ) {
				announce( root, dropped + ' ' + __( 'exclusions no longer match the refreshed preview and were cleared.', 'rankkernel' ) );
			}
			state.currentKindIndex = 0;
			state.wizardView = 'kind';
			state.reviewed = {};
			state.visitedMax = 0;
			state.completed = false;
			ensureWizardControls( root );
			renderWizardNav( root );
			showKind( root, 0 );
			setStep( root, 'rk-importer-step-detect', 'is-complete' );
			setStep( root, 'rk-importer-step-preview', 'is-current' );
			state.previewLoaded = true;
			var runGate = root.querySelector( '[data-rk-importer-run]' );
			if ( runGate ) {
				runGate.disabled = false;
			}
			announce( root, __( 'Preview ready. Review the sections, then run the import.', 'rankkernel' ) );
		} ).catch( function () {
			fail( root, __( 'Preview request failed.', 'rankkernel' ) );
		} );
	}

	function resetState( source, preserveExcludes ) {
		var oldExcludes = preserveExcludes ? Object.assign( {}, state.excludes ) : {};
		state.source = source;
		state.previewLoaded = false;
		state.running = false;
		state.confirmed = false;
		state.excludes = preserveExcludes ? oldExcludes : {};
		state.skippedKinds = {};
		state.pages = {};
		state.sitewide = { rows: [], selected: {} };
		state.bulk = { rows: [], selected: [] };
		state.kinds = {};
		state.report = null;
		state.queries = {};
	}

	function selectedRuleCount() {
		return state.bulk.selected.length;
	}

	function confirmHtml( modeStr ) {
		var lines = [];
		lines.push( __( 'Source: ', 'rankkernel' ) + state.source );
		lines.push( __( 'Conflict mode: ', 'rankkernel' ) + modeStr );
		[ 'posts', 'terms', 'redirects' ].forEach( function ( kind ) {
			var page = state.pages[ kind ];
			if ( page ) {
				lines.push( kind + ': ' + page.kindTotal + __( ' eligible, ', 'rankkernel' ) + excludeCountFor( kind ) + __( ' excluded', 'rankkernel' ) );
			}
		} );
		var conflictCount = 0;
		var skippedCount = 0;
		KINDS.forEach( function ( kind ) {
			var page = state.pages[ kind ];
			if ( ! page ) {
				return;
			}
			( page.rows || [] ).forEach( function ( row ) {
				if ( row.conflicts && Object.keys( row.conflicts ).length > 0 ) {
					conflictCount += 1;
				}
			} );
			skippedCount += ( page.skipped || [] ).length;
		} );
		lines.push( __( 'Rows with conflicts in the loaded preview: ', 'rankkernel' ) + conflictCount );
		lines.push( __( 'Unsupported or skipped in the loaded preview: ', 'rankkernel' ) + skippedCount );
		var sitewide = state.pages.sitewide;
		if ( sitewide ) {
			lines.push( __( 'Sitewide templates: ', 'rankkernel' ) + sitewide.rows.length + __( ' candidates, ', 'rankkernel' ) + Object.keys( state.sitewide.selected ).length + __( ' chosen', 'rankkernel' ) );
		}
		Object.keys( state.sitewide.selected ).forEach( function ( slot ) {
			lines.push( slot + ': ' + state.sitewide.selected[ slot ] );
		} );
		lines.push( __( 'Bulk rules chosen: ', 'rankkernel' ) + selectedRuleCount() );
		state.bulk.selected.forEach( function ( identity ) {
			lines.push( identity );
		} );
		var skippedKinds = Object.keys( state.skippedKinds || {} );
		if ( skippedKinds.length > 0 ) {
			lines.push( __( 'Kinds skipped, will not import: ', 'rankkernel' ) + skippedKinds.join( ', ' ) );
		}
		lines.push( __( 'Exclusions: ', 'rankkernel' ) + excludeList().length );
		return '<ul><li>' + lines.map( esc ).join( '</li><li>' ) + '</li></ul>';
	}

	function kindPrefix( kind ) {
		return PREFIXES[ kind ] || ( kind + ':' );
	}

	function excludeCountFor( kind ) {
		var prefix = kindPrefix( kind );
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
			panel.innerHTML = '<h3>' + esc( __( 'Confirm import', 'rankkernel' ) ) + '</h3>' + confirmHtml( mode( root ) );
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
			excludes: excludeList(),
			run_id: state.runId
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
			if ( state.skippedKinds[ kind ] ) {
				return;
			}
			chain = chain.then( function () {
				var options = {};
				if ( 'sitewide' === kind ) {
					options.choices = state.sitewide.selected;
				}
				return runKind( root, source, kind, 0, options );
			} );
		} );
		if ( ! state.skippedKinds.bulk ) {
			state.bulk.selected.forEach( function ( identity ) {
				chain = chain.then( function () {
					return runKind( root, source, 'bulk', 0, { rule: identity } );
				} );
			} );
		}
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
		state.runId = 'rk' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 10 );
		runBtn.disabled = true;
		state.kinds = {};
		setProgress( root, 0 );
		setStep( root, 'rk-importer-step-preview', 'is-complete' );
		setStep( root, 'rk-importer-step-run', 'is-current' );
		return runPlan( root, source ).then( function () {
			setProgress( root, 100 );
			state.completed = true;
			renderWizardNav( root );
			var panel = root.querySelector( '[data-rk-importer-confirm]' );
			if ( panel ) {
				panel.innerHTML = '<h3>' + esc( __( 'Import complete', 'rankkernel' ) ) + '</h3>' + finalReport( root );
			}
			announce( root, __( 'Import complete.', 'rankkernel' ) );
		} ).catch( function ( err ) {
			var panel = root.querySelector( '[data-rk-importer-confirm]' );
			if ( panel ) {
				panel.innerHTML = '<h3>' + esc( __( 'Import stopped', 'rankkernel' ) ) + '</h3>' + finalReport( root );
			}
			if ( err && 409 === err.status ) {
				fail( root, __( 'Another import run is already active. Wait for it to finish and try again.', 'rankkernel' ) );
			} else {
				var reason = ( err && err.data && err.data.message ) ? err.data.message : '';
				var partial = ( err && err.data && err.data.data && err.data.data.partial ) ? err.data.data.partial : null;

				if ( partial ) {
					fail(
						root,
						( reason ? reason + ' ' : '' )
						+ __( 'Progress before the failure:', 'rankkernel' ) + ' '
						+ ( partial.applied || 0 ) + ' ' + __( 'applied', 'rankkernel' ) + ', '
						+ ( partial.skipped || 0 ) + ' ' + __( 'skipped', 'rankkernel' ) + ', '
						+ ( partial.failed || 0 ) + ' ' + __( 'failed', 'rankkernel' ) + '.'
					);
				} else {
					fail( root, reason || __( 'Import run failed partway. Re-run to resume the remaining chunks.', 'rankkernel' ) );
				}
			}
		} ).then( function () {
			var rid = state.runId;
			if ( rid ) {
				post( cfg.restBase + '/' + source + '/release', { run_id: rid } ).catch( function () {} );
			}
			state.runId = null;
			setStep( root, 'rk-importer-step-run', 'is-current' );
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
		var rescanBtn = root.querySelector( '[data-rk-importer-rescan]' );
		if ( rescanBtn ) {
			rescanBtn.addEventListener( 'click', function () {
				window.location.reload();
			} );
		}
		root.addEventListener( 'click', function ( event ) {
			var target = event && event.target;
			if ( ! target || ! target.getAttribute ) {
				return;
			}
			if ( target.getAttribute( 'data-rk-importer-back' ) !== null || target.getAttribute( 'data-rk-importer-prev' ) !== null ) {
				prevKind( root );
				return;
			}
			if ( target.getAttribute( 'data-rk-importer-next' ) !== null || target.getAttribute( 'data-rk-importer-kind-next' ) !== null ) {
				advanceKind( root );
				return;
			}
			if ( target.getAttribute( 'data-rk-importer-skip' ) !== null || target.getAttribute( 'data-rk-importer-skip-kind' ) !== null ) {
				skipKind( root );
				return;
			}
			var jump = target.getAttribute( 'data-rk-importer-kind-jump' ) || target.getAttribute( 'data-rk-importer-kind' );
			if ( jump && ( target.tagName === 'BUTTON' || target.getAttribute( 'data-rk-importer-kind-jump' ) ) ) {
				// Only jump when clicking nav button/item, avoid section mounts.
				if ( KINDS.indexOf( jump ) !== -1 ) {
					jumpKind( root, jump );
					return;
				}
			}
			var clearAttr = target.getAttribute( 'data-rk-importer-clear' ) || target.getAttribute( 'data-rk-importer-clear-excludes' );
			if ( clearAttr !== null ) {
				clearLoadedExcludes( root, KINDS[ state.currentKindIndex ] );
				return;
			}
			var searchClear = target.getAttribute( 'data-rk-importer-search-clear' );
			if ( searchClear ) {
				state.queries[ searchClear ] = '';
				previewKind( root, state.source, searchClear, 0, '' ).then( function () {
					renderKindSection( root, searchClear, TITLES[ searchClear ] || searchClear );
					resetConfirm( root );
				} ).catch( function () {
					fail( root, __( 'Preview request failed.', 'rankkernel' ) );
				} );
				return;
			}
			var exclAllBtn = target.getAttribute( 'data-rk-importer-exclude-all' );
			if ( exclAllBtn !== null && 'BUTTON' === target.tagName ) {
				applyExcludeAllLoaded( root, KINDS[ state.currentKindIndex ], true );
				return;
			}
		} );
		root.addEventListener( 'input', function ( event ) {
			var target = event && event.target;
			if ( ! target || ! target.getAttribute ) {
				return;
			}
			var kind = target.getAttribute( 'data-rk-importer-search' );
			if ( ! kind ) {
				return;
			}
			if ( state.searchTimers && state.searchTimers[ kind ] ) {
				clearTimeout( state.searchTimers[ kind ] );
			}
			state.searchTimers = state.searchTimers || {};
			state.searchTimers[ kind ] = setTimeout( function () {
				state.queries[ kind ] = target.value || '';
				previewKind( root, state.source, kind, 0, state.queries[ kind ] ).then( function () {
					renderKindSection( root, kind, TITLES[ kind ] || kind );
					resetConfirm( root );
				} ).catch( function () {
					fail( root, __( 'Preview request failed.', 'rankkernel' ) );
				} );
			}, 300 );
		} );

		root.addEventListener( 'change', function ( event ) {
			var target = event && event.target;
			if ( ! target || ! target.getAttribute ) {
				return;
			}
			if ( target.getAttribute( 'data-rk-importer-exclude-all' ) !== null ) {
				applyExcludeAllLoaded( root, KINDS[ state.currentKindIndex ], !! target.checked );
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
