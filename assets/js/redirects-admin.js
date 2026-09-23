/**
 * Redirect Manager — progressive enhancement layer.
 *
 * Features handled here:
 *
 *  PANELS (accordion — only one open at a time):
 *    - Add/Edit editor (#rk-redirect-editor)
 *    - Import/Export card (#rk-redirect-csv)
 *    - Settings card (#rk-redirect-settings)
 *    All three share a single openPanel() / closePanel() / togglePanel()
 *    contract: clicking a header button opens its target and closes every
 *    other panel simultaneously. Cancel/Hide buttons inside panels close
 *    only their own panel.
 *
 *  AJAX LIST REFRESH:
 *    Intercepts tab clicks, filter-form submits, sort-header clicks,
 *    pagination clicks and rows-per-page changes. Fetches only
 *    #rk-list-section via WordPress AJAX, injects the HTML response, and
 *    re-binds all list-level event listeners.
 *    Falls back silently to a full page load on any AJAX error.
 *    The browser URL is kept in sync via history.replaceState so bookmarks
 *    and the back button still work. Loading state is shown on the section.
 *
 *  EDITOR INTERNALS:
 *    - Match type / code select live-sync (hints, regex row, terminal row)
 *    - Regex pattern live validation
 *    - Fragment character warning in source field
 *    - Source and destination character counters
 *    - "Use recommended destination" button (chain notice)
 *
 *  UTILITIES:
 *    - Notice dismiss
 *    - Bulk select-all checkbox plus the selected count chip
 *    - Destructive action confirmations
 *    - File-input label display
 *
 * Every feature degrades gracefully — the page is fully functional without JS.
 *
 * Configuration injected by wp_localize_script under window.rkRedirects:
 *   ajaxUrl    {string}  admin-ajax.php URL
 *   nonce      {string}  Nonce for rankkernel_redirects_list
 *   screenSlug {string}  Admin page slug
 *
 * @package RankKernel
 */

/* global rkRedirects */

/**
 * Speak an accessible announcement when wp.a11y is available.
 *
 * Announcement is best effort by contract: speak when wp.a11y is present, stay silent otherwise.
 * The helper takes an already translated string. Every announcement literal is translated at its
 * own call site, because the WordPress string extractor only reads literal arguments to a
 * translation function.
 *
 * @param {string} text Translated message to announce.
 */
function rankkernelAnnounce( text ) {
	if ( ! window.wp || ! window.wp.a11y || typeof window.wp.a11y.speak !== 'function' ) {
		return;
	}

	window.wp.a11y.speak( text );
}

( function () {
	'use strict';

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	// The translator falls back to the raw string when wp.i18n is absent. Every user facing
	// literal is translated at its own call site, because the WordPress extractor only reads
	// literal arguments to a translation function.
	var __ = ( window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function' )
		? window.wp.i18n.__
		: function ( text ) {
			return text;
		};

	/* ------------------------------------------------------------------
	 * Panel accordion
	 *
	 * Panels: #rk-redirect-editor, #rk-redirect-csv, #rk-redirect-settings
	 * Toggled by buttons with data-rk-panel-toggle="<panel-id>"
	 * Only one panel may be open at a time.
	 * ------------------------------------------------------------------ */

	var PANEL_IDS = [
		'rk-redirect-editor',
		'rk-redirect-csv',
		'rk-redirect-settings',
	];

	/**
	 * Close all panels silently, update aria-expanded on their toggle buttons.
	 */
	function closeAllPanels() {
		PANEL_IDS.forEach( function ( id ) {
			var panel = document.getElementById( id );

			if ( panel ) {
				panel.setAttribute( 'hidden', '' );
			}

			/* Reset the aria-expanded on every toggle button for this panel. */
			document.querySelectorAll( '[data-rk-panel-toggle="' + id + '"]' ).forEach( function ( btn ) {
				btn.setAttribute( 'aria-expanded', 'false' );
			} );
		} );
	}

	/**
	 * Open one panel and close every other panel.
	 *
	 * @param {string} panelId ID of the panel to open.
	 * @param {Element|null} triggerBtn The button that triggered the open (to focus back on close).
	 */
	function openPanel( panelId, triggerBtn ) {
		closeAllPanels();

		var panel = document.getElementById( panelId );

		if ( ! panel ) {
			return;
		}

		panel.removeAttribute( 'hidden' );

		document.querySelectorAll( '[data-rk-panel-toggle="' + panelId + '"]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-expanded', 'true' );
		} );

		/* If opening the editor, focus the first input. */
		if ( 'rk-redirect-editor' === panelId ) {
			var source = document.getElementById( 'rk-source' );

			if ( source ) {
				source.focus();
			}
		} else {
			/* Scroll the panel into view for others. */
			panel.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		/* Store the trigger so we can focus back when the panel is closed. */
		if ( triggerBtn ) {
			panel.dataset.rkOpenedBy = triggerBtn.id || '';
		}
	}

	/**
	 * Close a specific panel and return focus to the button that opened it.
	 *
	 * @param {string} panelId ID of the panel to close.
	 */
	function closePanel( panelId ) {
		var panel = document.getElementById( panelId );

		if ( ! panel ) {
			return;
		}

		panel.setAttribute( 'hidden', '' );

		document.querySelectorAll( '[data-rk-panel-toggle="' + panelId + '"]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-expanded', 'false' );
		} );

		var openedById = panel.dataset.rkOpenedBy || '';

		if ( openedById ) {
			var opener = document.getElementById( openedById );

			if ( opener ) {
				opener.focus();
			}
		}
	}

	/**
	 * Toggle a panel: open it if closed, close it if open.
	 *
	 * @param {string}  panelId    ID of the panel.
	 * @param {Element} triggerBtn The button that was clicked.
	 */
	function togglePanel( panelId, triggerBtn ) {
		var panel = document.getElementById( panelId );

		if ( ! panel ) {
			return;
		}

		if ( panel.hasAttribute( 'hidden' ) ) {
			openPanel( panelId, triggerBtn );
		} else {
			closePanel( panelId );
		}
	}

	/* Bind all toggle buttons (present at page load). */
	document.querySelectorAll( '[data-rk-panel-toggle]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			togglePanel( btn.getAttribute( 'data-rk-panel-toggle' ), btn );
		} );
	} );

	/* "Cancel" / "Hide" buttons inside panels — close only their own panel. */
	document.querySelectorAll( '#rk-editor-cancel, .rk-editor-cancel-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			closePanel( 'rk-redirect-editor' );
		} );
	} );

	document.querySelectorAll( '.rk-settings-hide' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			closePanel( 'rk-redirect-settings' );
		} );
	} );

	/* ------------------------------------------------------------------
	 * AJAX list refresh
	 *
	 * Any element with data-rk-filter-url="<url>" triggers an AJAX refresh
	 * of #rk-list-section when clicked. The filter form submit does the
	 * same by serialising its fields.
	 * ------------------------------------------------------------------ */

	var cfg        = ( typeof window.rkRedirects !== 'undefined' ) ? window.rkRedirects : null;
	var listWrap   = document.getElementById( 'rk-list-section' );
	var ajaxActive = false;

	/**
	 * Build a query-string from a URL's search params, extending or
	 * replacing values supplied in overrides.
	 *
	 * @param {string} baseUrl   A full admin URL.
	 * @param {Object} overrides Key/value pairs to merge in.
	 * @return {string} The full URL with merged params.
	 */
	function buildFilterUrl( baseUrl, overrides ) {
		var parser = document.createElement( 'a' );
		parser.href = baseUrl;

		var params = {};
		var search = parser.search.replace( /^\?/, '' );

		search.split( '&' ).forEach( function ( part ) {
			if ( '' === part ) {
				return;
			}

			var kv = part.split( '=' );
			params[ decodeURIComponent( kv[ 0 ] ) ] = decodeURIComponent( kv[ 1 ] || '' );
		} );

		Object.keys( overrides ).forEach( function ( key ) {
			params[ key ] = overrides[ key ];
		} );

		var qs = Object.keys( params )
			.filter( function ( k ) {
				return '' !== params[ k ];
			} )
			.map( function ( k ) {
				return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
			} )
			.join( '&' );

		return parser.protocol + '//' + parser.host + parser.pathname + ( qs ? '?' + qs : '' );
	}

	/**
	 * Fetch the list section via AJAX and inject it into the page.
	 *
	 * @param {string} filterUrl Full admin URL with filter/sort/page params.
	 * @param {boolean} pushState Whether to push to browser history.
	 */
	function fetchList( filterUrl, pushState ) {
		if ( ! cfg || ! listWrap || ajaxActive ) {
			return;
		}

		ajaxActive = true;

		/* Extract just the query-string params the server needs. */
		var parser = document.createElement( 'a' );
		parser.href = filterUrl;
		var qs = parser.search;

		/* Build the AJAX POST body. */
		var body = new URLSearchParams();
		body.append( 'action', 'rankkernel_redirects_list' );
		body.append( '_ajax_nonce', cfg.nonce );

		/* Append every filter param from the URL. */
		new URLSearchParams( qs ).forEach( function ( val, key ) {
			body.append( key, val );
		} );

		/* Visual loading state. */
		listWrap.classList.add( 'rk-list-loading' );
		listWrap.setAttribute( 'aria-busy', 'true' );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				return response.json();
			} )
			.then( function ( data ) {
				if ( data.success && typeof data.data.html === 'string' ) {
					listWrap.outerHTML = data.data.html;

					/* Re-acquire the reference after innerHTML swap. */
					listWrap = document.getElementById( 'rk-list-section' );

					if ( listWrap ) {
						listWrap.classList.remove( 'rk-list-loading' );
						listWrap.removeAttribute( 'aria-busy' );
					}

					/* Rebind list-level events (bulk, confirm, select-all). */
					bindListEvents();

					/* Keep the browser URL in sync. */
					if ( pushState && window.history && window.history.replaceState ) {
						window.history.replaceState( {}, '', filterUrl );
					}

					rankkernelAnnounce( __( 'Redirect list updated.', 'rankkernel' ) );
				} else {
					throw new Error( 'Unexpected response' );
				}
			} )
			.catch( function () {
				/* Silent fallback: let the normal link navigate. */
				if ( listWrap ) {
					listWrap.classList.remove( 'rk-list-loading' );
					listWrap.removeAttribute( 'aria-busy' );
				}

				window.location.href = filterUrl;
			} )
			.finally( function () {
				ajaxActive = false;
			} );
	}

	/**
	 * Serialise a GET form and refresh the list through the AJAX path.
	 *
	 * @param {Element} form Form whose fields become the filter URL.
	 */
	function refreshForm( form ) {
		var data   = new FormData( form );
		var params = {};

		data.forEach( function ( val, key ) {
			params[ key ] = String( val );
		} );

		var baseUrl = form.getAttribute( 'action' ) || window.location.href;

		fetchList( buildFilterUrl( baseUrl, params ), true );
	}

	/**
	 * Write the selected row count into the bulk bar chip, hiding it when
	 * nothing is selected. Called on load, after every AJAX swap, and on
	 * every row checkbox change.
	 */
	function updateSelectedChip() {
		var chip = document.getElementById( 'rk-selected-chip' );

		if ( ! chip ) {
			return;
		}

		var bulkForm = document.getElementById( 'rk-bulk-form' );

		if ( ! bulkForm ) {
			return;
		}

		var checked = bulkForm.querySelectorAll( 'input[name="rule_ids[]"]:checked' ).length;

		if ( checked > 0 ) {
			chip.textContent = String( checked ) + ' ' + __( 'selected', 'rankkernel' );
			chip.removeAttribute( 'hidden' );
		} else {
			chip.textContent = '';
			chip.setAttribute( 'hidden', '' );
		}
	}

	/**
	 * Bind the select-all checkbox: mirror its state onto every row checkbox and
	 * announce the new state. Called once on load and again after every AJAX swap.
	 */
	function bindSelectAll() {
		var selectAll = document.getElementById( 'rk-select-all' );
		var bulkForm  = document.getElementById( 'rk-bulk-form' );

		if ( ! selectAll || ! bulkForm ) {
			return;
		}

		selectAll.addEventListener( 'change', function () {
			bulkForm.querySelectorAll( 'input[name="rule_ids[]"]' ).forEach( function ( cb ) {
				cb.checked = selectAll.checked;
			} );

			updateSelectedChip();

			rankkernelAnnounce( selectAll.checked
				? __( 'All redirects selected.', 'rankkernel' )
				: __( 'All redirects deselected.', 'rankkernel' ) );
		} );
	}

	/**
	 * Bind list-level event listeners. Called once on load and again after
	 * every AJAX swap so dynamically inserted markup is covered.
	 */
	function bindListEvents() {
		bindSelectAll();
		updateSelectedChip();

		if ( ! listWrap ) {
			return;
		}

		/* Row checkboxes keep the selected count chip in sync. */
		listWrap.querySelectorAll( 'input[name="rule_ids[]"]' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', updateSelectedChip );
		} );

		/*
		 * Rows per page changes refresh the list through the same AJAX path
		 * as the other list controls. When the localized AJAX config is
		 * missing the plain GET submit still runs, and without JavaScript
		 * the form keeps working through its own submit button.
		 */
		var perPageSelect = listWrap.querySelector( '#rk-perpage-select' );

		if ( perPageSelect ) {
			perPageSelect.addEventListener( 'change', function () {
				var perPageForm = perPageSelect.form || null;

				if ( ! perPageForm ) {
					return;
				}

				if ( ! cfg ) {
					if ( typeof perPageForm.submit === 'function' ) {
						perPageForm.submit();
					}

					return;
				}

				refreshForm( perPageForm );
			} );
		}

		/* Tab links and data-rk-filter-url anchors / buttons. */
		listWrap.querySelectorAll( '[data-rk-filter-url]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( event ) {
				var url = el.getAttribute( 'data-rk-filter-url' );

				if ( ! url || ! cfg ) {
					return;
				}

				event.preventDefault();
				fetchList( url, true );
			} );
		} );

		/* Filter form submit: serialise fields into a URL then AJAX-fetch. */
		var filterForm = listWrap.querySelector( '#rk-filter-form' );

		if ( filterForm && cfg ) {
			filterForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				refreshForm( filterForm );
			} );
		}

		/* Destructive row confirm links. */
		listWrap.querySelectorAll( '.rk-confirm' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				var message = link.getAttribute( 'data-rk-confirm' ) || __( 'Are you sure?', 'rankkernel' );

				if ( ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );

		/* Bulk form confirmation. */
		var bulkForm = listWrap.querySelector( '#rk-bulk-form' );

		if ( bulkForm ) {
			bulkForm.addEventListener( 'submit', function ( event ) {
				var actionSel = bulkForm.querySelector( '#rk-bulk-action' );

				if ( actionSel && 'delete' === actionSel.value ) {
					var message = bulkForm.getAttribute( 'data-rk-confirm' ) || __( 'Are you sure?', 'rankkernel' );

					if ( ! window.confirm( message ) ) {
						event.preventDefault();
					}
				}
			} );
		}
	}

	/* Initial bind on page load. */
	bindListEvents();

	/* Handle back/forward navigation — re-fetch the list for the URL state. */
	if ( window.history ) {
		window.addEventListener( 'popstate', function () {
			fetchList( window.location.href, false );
		} );
	}

	/* ------------------------------------------------------------------
	 * Notice dismiss
	 * ------------------------------------------------------------------ */

	document.querySelectorAll( '.rk-redirects .rk-notice-dismiss' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var notice = btn.closest( '.rk-notice' );

			if ( notice ) {
				notice.style.transition = 'opacity 0.2s';
				notice.style.opacity    = '0';

				setTimeout( function () {
					notice.remove();
				}, 200 );
			}
		} );
	} );

	/* ------------------------------------------------------------------
	 * Editor field internals (match type, code, regex, fragment warning)
	 * ------------------------------------------------------------------ */

	var editor = document.getElementById( 'rk-redirect-editor' );

	if ( editor ) {
		var matchSelect   = document.getElementById( 'rk-match' );
		var matchHintEl   = document.getElementById( 'rk-match-hint' );
		var regexRow      = document.getElementById( 'rk-regex-row' );
		var feedback      = document.getElementById( 'rk-regex-feedback' );
		var sourceInput   = document.getElementById( 'rk-source' );
		var sourceHintEl  = document.getElementById( 'rk-source-hint' );
		var sourceCountEl = document.getElementById( 'rk-source-count' );
		var codeSelect    = document.getElementById( 'rk-code' );
		var codeHintEl    = document.getElementById( 'rk-code-hint' );
		var targetRow     = document.getElementById( 'rk-target-row' );
		var targetInput   = document.getElementById( 'rk-target' );
		var targetCountEl = document.getElementById( 'rk-target-count' );
		var terminalNote  = document.getElementById( 'rk-terminal-note' );

		function selectedHint( select ) {
			var opt = select.options[ select.selectedIndex ];

			return opt ? ( opt.getAttribute( 'data-hint' ) || '' ) : '';
		}

		function isTerminalCode() {
			return Boolean( codeSelect && ( '410' === codeSelect.value || '451' === codeSelect.value ) );
		}

		function isRegexMode() {
			return Boolean( matchSelect && 'regex' === matchSelect.value );
		}

		function checkRegex() {
			if ( ! feedback || ! sourceInput ) {
				return;
			}

			var pattern  = sourceInput.value;
			var message  = feedback.getAttribute( 'data-msg-empty' ) || '';
			var ok       = false;
			var advisory = false;

			if ( '' !== pattern && pattern.length > 200 ) {
				message = feedback.getAttribute( 'data-msg-long' ) || message;
			} else if ( '' !== pattern ) {
				try {
					void new RegExp( pattern ); // eslint-disable-line no-new
					ok      = true;
					message = feedback.getAttribute( 'data-msg-valid' ) || message;

					var trimmed = pattern.replace( /^\s+|\s+$/g, '' );

					if ( 0 !== trimmed.indexOf( '^' ) || trimmed.length - 1 !== trimmed.lastIndexOf( '$' ) ) {
						message += ' ' + ( feedback.getAttribute( 'data-msg-anchor' ) || '' );
					}
				} catch ( e ) {
					/*
					 * JavaScript and PHP compile different pattern dialects, so
					 * a browser compile failure is not proof that the server
					 * would reject the pattern. Show an advisory and leave the
					 * verdict to the server.
					 */
					advisory = true;
					message  = feedback.getAttribute( 'data-msg-preview' ) || message;
				}
			}

			feedback.textContent = message.replace( /\s+$/, '' );
			feedback.className   = advisory ? 'rk-form-hint' : ( ok ? 'rk-regex-ok' : 'rk-regex-bad' );
		}

		function warnFragment() {
			if ( ! sourceInput || ! sourceHintEl || ! sourceHintEl.getAttribute( 'data-fragment' ) ) {
				return;
			}

			var existing = document.getElementById( 'rk-source-live' );

			if ( ! isRegexMode() && -1 !== sourceInput.value.indexOf( '#' ) ) {
				if ( ! existing ) {
					existing          = document.createElement( 'p' );
					existing.id        = 'rk-source-live';
					existing.className = 'rk-field-error';
					existing.setAttribute( 'role', 'status' );
					sourceHintEl.parentNode.insertBefore( existing, sourceHintEl );
				}

				existing.textContent = sourceHintEl.getAttribute( 'data-fragment' );
			} else if ( existing ) {
				existing.remove();
			}
		}

		function syncCounts() {
			if ( sourceInput && sourceCountEl ) {
				sourceCountEl.textContent = formatCount( sourceInput.value.length );
			}

			if ( targetInput && targetCountEl ) {
				targetCountEl.textContent = formatCount( targetInput.value.length );
			}
		}

		function formatCount( len ) {
			return String( len ) + ' ' + ( 1 === len ? __( 'char', 'rankkernel' ) : __( 'chars', 'rankkernel' ) );
		}

		function syncMatch() {
			if ( matchHintEl && matchSelect ) {
				matchHintEl.textContent = selectedHint( matchSelect );
			}

			if ( regexRow ) {
				if ( isRegexMode() ) {
					regexRow.removeAttribute( 'hidden' );
				} else {
					regexRow.setAttribute( 'hidden', '' );
				}
			}

			checkRegex();
			warnFragment();
		}

		function syncCode() {
			if ( codeHintEl && codeSelect ) {
				codeHintEl.textContent = selectedHint( codeSelect );
			}

			var terminal = isTerminalCode();

			if ( targetRow ) {
				if ( terminal ) {
					targetRow.setAttribute( 'hidden', '' );
				} else {
					targetRow.removeAttribute( 'hidden' );
				}
			}

			if ( targetInput ) {
				targetInput.disabled = terminal;
			}

			if ( terminalNote ) {
				if ( terminal ) {
					terminalNote.removeAttribute( 'hidden' );
				} else {
					terminalNote.setAttribute( 'hidden', '' );
				}
			}
		}

		if ( matchSelect ) {
			matchSelect.addEventListener( 'change', syncMatch );
		}

		if ( codeSelect ) {
			codeSelect.addEventListener( 'change', syncCode );
		}

		if ( sourceInput ) {
			sourceInput.addEventListener( 'input', function () {
				if ( isRegexMode() ) {
					checkRegex();
				}

				warnFragment();
				syncCounts();
			} );
		}

		if ( targetInput ) {
			targetInput.addEventListener( 'input', syncCounts );
		}

		syncCounts();
	}

	/* ------------------------------------------------------------------
	 * Use recommended destination (chain warning notice)
	 * ------------------------------------------------------------------ */

	document.querySelectorAll( '[data-rk-use-destination]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var destination = button.getAttribute( 'data-rk-use-destination' ) || '';

			if ( '' === destination ) {
				return;
			}

			var destField = document.getElementById( 'rk-target' );

			if ( ! destField ) {
				return;
			}

			openPanel( 'rk-redirect-editor', button );
			destField.value = destination;
			destField.focus();
			rankkernelAnnounce( __( 'Recommended destination applied to Destination URL field.', 'rankkernel' ) );
		} );
	} );

	/* ------------------------------------------------------------------
	 * File input: show selected filename in the drop zone
	 * ------------------------------------------------------------------ */

	var fileInput     = document.getElementById( 'rk-csv-file' );
	var fileZoneLabel = fileInput ? document.querySelector( '.rk-file-zone-label' ) : null;

	if ( fileInput && fileZoneLabel ) {
		var originalLabel = fileZoneLabel.textContent;

		fileInput.addEventListener( 'change', function () {
			fileZoneLabel.textContent =
				( fileInput.files && fileInput.files.length > 0 )
					? fileInput.files[ 0 ].name
					: originalLabel;
		} );
	}

}() );
