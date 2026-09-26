/**
 * Instant Indexing manual submission, live multi URL validation.
 *
 * Enables the submit button only while every non empty line is an absolute
 * http or https URL on this site. The server validates again, so the button
 * ships enabled in the HTML and a user without JavaScript can still submit.
 * Only the site host and the site port are localized into this file, the API
 * key never reaches the browser.
 *
 * The log loader below is a progressive enhancement over the server rendered
 * Submission history card. The filter form stays a plain GET form and the
 * pagination stays real anchors, so the card keeps working with JavaScript
 * disabled. When JavaScript runs, submits and page clicks fetch the same
 * data from the REST log route and update the table in place.
 *
 * Plain script, no build step. Loaded on the Instant Indexing screen only.
 */
( function () {
	'use strict';

	var FIELD_ID = 'rankkernel-indexnow-urls';
	var STATUS_ID = 'rankkernel-indexnow-urls-status';
	var BUTTON_ID = 'rankkernel-indexnow-submit';
	var DEBOUNCE_MS = 150;

	var REASON_NOT_A_URL = 'Not a valid URL.';
	var REASON_FOREIGN_HOST = 'This URL is not on this site.';
	var MESSAGE_EMPTY = 'Enter at least one URL on this site.';
	var MAX_VALID_SHOWN = 5;
	var MAX_INVALID_SHOWN = 50;

	/**
	 * Whether a parsed port may be submitted.
	 *
	 * An empty port string means the URL used the scheme default. The
	 * explicit default ports 80 and 443 are always allowed, and the
	 * localized site port is allowed when the site runs on one.
	 */
	function portAllowed( port, sitePort ) {
		var value = String( port || '' );

		if ( '' === value || '80' === value || '443' === value ) {
			return true;
		}

		return '' !== String( sitePort || '' ) && String( sitePort ) === value;
	}

	/**
	 * Reason a single line cannot be submitted, empty when it can.
	 *
	 * Mirrors the server rules: http or https only, no userinfo, no port
	 * beyond 80, 443 and the localized site port, and a host that case
	 * folds to the site host while www and the apex stay different.
	 */
	function reasonFor( url, host, sitePort ) {
		var parsed;

		if ( 'undefined' === typeof URL ) {
			return REASON_NOT_A_URL;
		}

		try {
			parsed = new URL( url );
		} catch ( error ) {
			return REASON_NOT_A_URL;
		}

		if ( 'http:' !== parsed.protocol && 'https:' !== parsed.protocol ) {
			return REASON_NOT_A_URL;
		}

		if ( '' !== String( parsed.username || '' ) || '' !== String( parsed.password || '' ) ) {
			return REASON_NOT_A_URL;
		}

		if ( ! portAllowed( parsed.port, sitePort ) ) {
			return REASON_NOT_A_URL;
		}

		if ( String( parsed.hostname ).toLowerCase() !== String( host || '' ).toLowerCase() ) {
			return REASON_FOREIGN_HOST;
		}

		return '';
	}

	/**
	 * Summary line for the current state of the field.
	 */
	function summaryFor( total, invalidCount ) {
		if ( 0 === total ) {
			return MESSAGE_EMPTY;
		}

		if ( 0 === invalidCount ) {
			return total + ' URLs ready to submit.';
		}

		return invalidCount + ' of ' + total + ' URLs are not valid. Fix them to continue.';
	}

	/**
	 * Validate every non empty line, pure and DOM free.
	 *
	 * Duplicates are counted once, preserving order, so the live count
	 * matches what the server will submit. Returns the per line entries
	 * plus the summary line and whether the submit button must stay
	 * disabled.
	 */
	function validate( text, host, sitePort ) {
		var lines = String( text || '' ).split( /\r\n|\r|\n/ );
		var seen = Object.create( null );
		var entries = [];
		var validCount = 0;
		var invalidCount = 0;
		var index;
		var url;
		var reason;

		for ( index = 0; index < lines.length; index++ ) {
			url = lines[ index ].trim();

			if ( '' === url || seen[ url ] ) {
				continue;
			}

			seen[ url ] = true;

			reason = reasonFor( url, host, sitePort );

			if ( '' === reason ) {
				validCount += 1;
			} else {
				invalidCount += 1;
			}

			entries.push( { url: url, valid: '' === reason, reason: reason } );
		}

		return {
			total: entries.length,
			validCount: validCount,
			invalidCount: invalidCount,
			entries: entries,
			summary: summaryFor( entries.length, invalidCount ),
			disabled: 0 === entries.length || invalidCount > 0
		};
	}

	/**
	 * Render the summary and the per line status, textContent only.
	 *
	 * A URL is user input, so it is never written as markup. The summary
	 * icon comes from CSS, the line icons are icon font spans marked
	 * decorative. The summary stays the first child of the status region
	 * so assistive tech announces it first.
	 */
	function render( status, button, result ) {
		var summary = document.createElement( 'p' );
		var list;
		var item;
		var icon;
		var text;
		var entry;
		var index;
		var shown;
		var validShown = 0;
		var invalidShown = 0;
		var more;

		if ( result.invalidCount > 0 ) {
			status.className = 'rk-validation rk-validation-warning';
		} else if ( result.total > 0 ) {
			status.className = 'rk-validation rk-validation-success';
		} else {
			status.className = 'rk-validation rk-validation-empty';
		}

		summary.className = 'rk-validation-summary';
		summary.textContent = result.summary;

		status.textContent = '';
		status.appendChild( summary );

		if ( result.total > 0 ) {
			list = document.createElement( 'ul' );
			list.className = 'rk-validation-list';

			for ( index = 0; index < result.entries.length; index++ ) {
				entry = result.entries[ index ];

				if ( entry.valid ) {
					if ( validShown >= MAX_VALID_SHOWN ) {
						continue;
					}

					validShown += 1;
					item = document.createElement( 'li' );
					item.className = 'rk-validation-item is-valid';

					icon = document.createElement( 'span' );
					icon.className = 'rk-icon';
					icon.setAttribute( 'aria-hidden', 'true' );
					icon.textContent = 'check_circle';

					text = document.createElement( 'span' );
					text.textContent = entry.url;

					item.appendChild( icon );
					item.appendChild( text );
				} else {
					if ( invalidShown >= MAX_INVALID_SHOWN ) {
						continue;
					}

					invalidShown += 1;
					item = document.createElement( 'li' );
					item.className = 'rk-validation-item is-invalid';

					text = document.createElement( 'span' );
					text.textContent = entry.url;

					more = document.createElement( 'span' );
					more.className = 'rk-validation-reason';
					more.textContent = entry.reason;

					item.appendChild( text );
					item.appendChild( more );
				}

				list.appendChild( item );
			}

			shown = validShown + invalidShown;

			if ( shown < result.total ) {
				item = document.createElement( 'li' );
				item.className = 'rk-validation-more';
				item.textContent = '+ ' + ( result.total - shown ) + ' more URLs';
				list.appendChild( item );
			}

			status.appendChild( list );
		}

		if ( button ) {
			button.disabled = result.disabled;
		}
	}

	/**
	 * Localized configuration, an empty object when the script is loaded
	 * without wp_localize_script.
	 */
	function config() {
		if ( 'undefined' === typeof window || ! window.rankkernelInstantIndexing ) {
			return {};
		}

		return window.rankkernelInstantIndexing;
	}

	function onReady( callback ) {
		if ( document.readyState !== 'loading' ) {
			callback();
		} else {
			document.addEventListener( 'DOMContentLoaded', callback );
		}
	}

	/**
	 * Whether the user prefers reduced motion.
	 *
	 * Guards matchMedia so the script stays safe where it is missing,
	 * as in the node test harness.
	 */
	function prefersReducedMotion() {
		if ( 'undefined' === typeof window || ! window.matchMedia ) {
			return false;
		}

		try {
			return !! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		} catch ( error ) {
			return false;
		}
	}

	/**
	 * Remove a notice from view, keeping assistive tech announcements.
	 *
	 * The notice keeps its role attribute until removal, so screen
	 * readers announce it before it leaves. Removal uses remove() when
	 * present and display none as the fallback.
	 */
	function hideNotice( notice ) {
		if ( ! notice ) {
			return;
		}

		if ( notice.remove ) {
			notice.remove();
		} else if ( notice.style ) {
			notice.style.display = 'none';
		}
	}

	/**
	 * Whether a notice is an error notice, which must persist.
	 *
	 * Error notices usually need reading and action, so they never
	 * auto dismiss. Success, info and warning notices dismiss after
	 * the timeout.
	 */
	function isErrorNotice( notice ) {
		var name = notice && notice.className ? String( notice.className ) : '';

		return name.indexOf( 'rk-ui-notice-error' ) !== -1;
	}

	/**
	 * Dismiss notices automatically after five seconds, errors persist.
	 *
	 * Manual dismissal keeps working through wireDismiss. The timer
	 * handle comes from the window so tests can capture it. No motion
	 * is applied, so there is nothing to gate on reduced motion.
	 */
	function wireAutoDismiss( scope ) {
		var notices;
		var index;
		var schedule;

		if ( ! scope || ! scope.querySelectorAll ) {
			return;
		}

		notices = scope.querySelectorAll( '.rk-instant-indexing .rk-ui-notice' );

		schedule = ( 'undefined' !== typeof window && window.setTimeout ) ? window.setTimeout : setTimeout;

		for ( index = 0; index < notices.length; index++ ) {
			( function ( notice ) {
				if ( isErrorNotice( notice ) ) {
					return;
				}

				schedule( function () {
					hideNotice( notice );
				}, 5000 );
			}( notices[ index ] ) );
		}
	}

	/**
	 * Hide our own notice banners through their dismiss buttons.
	 *
	 * WordPress core notices dismiss through their own script, ours hide
	 * locally so no request is needed.
	 */
	function wireDismiss( scope ) {
		var buttons = scope.querySelectorAll( '.rk-instant-indexing .rk-ui-notice-dismiss' );
		var index;

		for ( index = 0; index < buttons.length; index++ ) {
			buttons[ index ].addEventListener( 'click', function ( event ) {
				var notice = event.target && event.target.closest ? event.target.closest( '.rk-ui-notice' ) : null;

				hideNotice( notice );
			} );
		}
	}

	/**
	 * Whether a collapsible panel is currently open.
	 *
	 * The hidden property is the state source, with the attribute as
	 * the fallback for older markup.
	 */
	function isOpen( panel ) {
		if ( 'undefined' !== typeof panel.hidden ) {
			return ! panel.hidden;
		}

		return ! ( panel.getAttribute && panel.getAttribute( 'hidden' ) !== null );
	}

	/**
	 * Set the open state of a collapsible panel plus its toggle.
	 *
	 * Opening never clears form fields, so entered input survives a
	 * close plus reopen cycle. Closing only hides, it never disables,
	 * so every nonce field plus hidden action input still posts when
	 * the container is open again.
	 */
	function setOpen( panel, toggle, open ) {
		if ( ! panel ) {
			return;
		}

		if ( 'undefined' !== typeof panel.hidden ) {
			panel.hidden = ! open;
		}

		if ( panel.setAttribute && panel.removeAttribute ) {
			if ( open ) {
				panel.removeAttribute( 'hidden' );
			} else {
				panel.setAttribute( 'hidden', '' );
			}
		}

		if ( toggle && toggle.setAttribute ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	/**
	 * Bring an opened panel into view without surprising motion users.
	 *
	 * Smooth scrolling applies only when reduced motion is off. The
	 * call is guarded so markup without scrollIntoView stays safe.
	 */
	function scrollPanelIntoView( panel ) {
		if ( ! panel || ! panel.scrollIntoView ) {
			return;
		}

		try {
			panel.scrollIntoView( { block: 'nearest', behavior: prefersReducedMotion() ? 'auto' : 'smooth' } );
		} catch ( error ) {
			try {
				panel.scrollIntoView();
			} catch ( ignored ) {
			}
		}
	}

	/**
	 * Resolve one switcher entry to its live nodes, nulls for missing ids.
	 *
	 * Entries with a missing toggle or panel are skipped by the switcher,
	 * so a screen that renders only some panels still wires the rest.
	 */
	function resolveEntry( scope, entry ) {
		if ( ! scope || ! scope.getElementById || ! entry ) {
			return null;
		}

		var toggle = scope.getElementById( entry.toggleId );
		var panel  = scope.getElementById( entry.panelId );
		var hide   = entry.hideId ? scope.getElementById( entry.hideId ) : null;

		if ( ! toggle || ! panel ) {
			return null;
		}

		return { toggle: toggle, panel: panel, hide: hide };
	}

	/**
	 * Open one entry while closing every other entry, aria kept in sync.
	 *
	 * Opening never clears form fields, so entered input survives a close
	 * plus reopen cycle. Closing only hides, it never disables, so every
	 * nonce field plus hidden action input still posts when the panel is
	 * open again.
	 */
	function openOnly( resolved, active ) {
		var index;

		for ( index = 0; index < resolved.length; index++ ) {
			setOpen( resolved[ index ].panel, resolved[ index ].toggle, resolved[ index ] === active );
		}
	}

	/**
	 * Wire the header controls as a mutually exclusive panel switcher.
	 *
	 * Each control opens its own panel and closes whichever panel was
	 * open, if any. Clicking the same control again closes its panel and
	 * returns to the all hidden state. Each Hide link only closes its own
	 * panel and returns focus to its control. Every control keeps
	 * aria expanded accurate through setOpen.
	 */
	function wirePanelSwitcher( scope, entries ) {
		var resolved = [];
		var index;
		var entry;

		if ( ! scope || ! scope.getElementById || ! entries ) {
			return;
		}

		for ( index = 0; index < entries.length; index++ ) {
			entry = resolveEntry( scope, entries[ index ] );

			if ( entry ) {
				resolved.push( entry );
			}
		}

		if ( 0 === resolved.length ) {
			return [];
		}

		for ( index = 0; index < resolved.length; index++ ) {
			( function ( current ) {
				setOpen( current.panel, current.toggle, false );

				current.toggle.addEventListener( 'click', function () {
					var willOpen = ! isOpen( current.panel );

					if ( willOpen ) {
						openOnly( resolved, current );
						scrollPanelIntoView( current.panel );
					} else {
						setOpen( current.panel, current.toggle, false );
					}
				} );

				if ( current.hide ) {
					current.hide.addEventListener( 'click', function ( event ) {
						if ( event && event.preventDefault ) {
							event.preventDefault();
						}

						setOpen( current.panel, current.toggle, false );

						if ( current.toggle.focus ) {
							try {
								current.toggle.focus();
							} catch ( error ) {
							}
						}
					} );
				}
				}( resolved[ index ] ) );
		}

		return resolved;
	}

	/**
	 * Wire opener buttons that jump straight to one panel.
	 *
	 * The empty log call to action opens the submit panel without
	 * toggling, so operators reach the form in one click. The opener
	 * closes any open panel first, keeping the switcher exclusive.
	 */
	function wirePanelOpeners( scope, resolved ) {
		var openers;
		var index;

		if ( ! scope || ! scope.querySelectorAll || ! resolved ) {
			return;
		}

		openers = scope.querySelectorAll( '[data-rk-open-panel]' );

		for ( index = 0; index < openers.length; index++ ) {
			( function ( opener ) {
				var targetId = opener.getAttribute ? opener.getAttribute( 'data-rk-open-panel' ) : null;

				if ( ! targetId ) {
					return;
				}

				opener.addEventListener( 'click', function () {
					var match;
					var cursor;

					for ( cursor = 0; cursor < resolved.length; cursor++ ) {
						if ( resolved[ cursor ].panel === scope.getElementById( targetId ) ) {
							match = resolved[ cursor ];
						}
					}

					if ( ! match ) {
						return;
					}

					openOnly( resolved, match );
					scrollPanelIntoView( match.panel );

					if ( match.toggle.focus ) {
						try {
							match.toggle.focus( { preventScroll: true } );
						} catch ( error ) {
							try {
								match.toggle.focus();
							} catch ( ignored ) {
							}
						}
					}
				} );
			}( openers[ index ] ) );
		}
	}

	/**
	 * Wire one collapsible card to its header toggle plus its Hide link.
	 *
	 * The header control toggles, the Hide link only closes. Both keep
	 * aria expanded on the toggle in sync with the panel state.
	 */
	function wireCollapsible( scope, toggleId, panelId, hideId ) {
		var toggle;
		var panel;
		var hide;

		if ( ! scope || ! scope.getElementById ) {
			return;
		}

		toggle = scope.getElementById( toggleId );
		panel  = scope.getElementById( panelId );
		hide   = scope.getElementById( hideId );

		if ( ! toggle || ! panel ) {
			return;
		}

		setOpen( panel, toggle, isOpen( panel ) );

		toggle.addEventListener( 'click', function () {
			var open = ! isOpen( panel );

			setOpen( panel, toggle, open );

			if ( open ) {
				scrollPanelIntoView( panel );
			}
		} );

		if ( hide ) {
			hide.addEventListener( 'click', function ( event ) {
				if ( event && event.preventDefault ) {
					event.preventDefault();
				}

				setOpen( panel, toggle, false );

				if ( toggle.focus ) {
					try {
						toggle.focus();
					} catch ( error ) {
					}
				}
			} );
		}
	}

	/**
	 * Status tab keys in server render order, InstantIndexingLogView::tabs.
	 */
	var LOG_TAB_KEYS = [ 'all', 'accepted', 'pending', 'rejected', 'limited' ];

	/**
	 * Longest search the server accepts, LogFilters::SEARCH_MAX.
	 */
	var LOG_SEARCH_MAX = 100;

	/**
	 * Fallback page size when the response carries none, LogQuery::PER_PAGE.
	 */
	var LOG_PER_PAGE_FALLBACK = 20;

	/**
	 * Display category for one stored status code.
	 *
	 * Mirrors InstantIndexingOutcomes::categoryFor, which stays the source
	 * of truth. The mapping is repeated here so the table can render from
	 * the raw REST rows without a second request.
	 */
	function logCategoryFor( code ) {
		if ( 200 === code ) {
			return 'accepted';
		}

		if ( 202 === code ) {
			return 'pending';
		}

		if ( 429 === code ) {
			return 'limited';
		}

		if ( 0 === code || 400 === code || 403 === code || 405 === code || 422 === code ) {
			return 'rejected';
		}

		return 'retry';
	}

	/**
	 * English pill label for one display category.
	 *
	 * Mirrors InstantIndexingOutcomes::statusLabel. The server translates
	 * these, the script cannot, so an AJAX refresh shows the English
	 * labels while the numbers always agree with the server.
	 */
	function logStatusLabel( category ) {
		if ( 'pending' === category ) {
			return 'Key pending';
		}

		if ( 'rejected' === category ) {
			return 'Rejected';
		}

		if ( 'limited' === category ) {
			return 'Rate limited';
		}

		if ( 'retry' === category ) {
			return 'Retry later';
		}

		return 'Accepted';
	}

	/**
	 * Pill class for one display category.
	 *
	 * Mirrors InstantIndexingOutcomes::statusPill, stable class names.
	 */
	function logStatusPill( category ) {
		if ( 'pending' === category ) {
			return 'rk-ui-pill rk-ui-pill-info';
		}

		if ( 'rejected' === category ) {
			return 'rk-ui-pill rk-ui-pill-danger';
		}

		if ( 'limited' === category || 'retry' === category ) {
			return 'rk-ui-pill rk-ui-pill-warning';
		}

		return 'rk-ui-pill rk-ui-pill-success';
	}

	/**
	 * English source label, mirroring InstantIndexingOutcomes::sourceLabel.
	 */
	function logSourceLabel( source ) {
		return 'manual' === source ? 'Manual' : 'Auto';
	}

	/**
	 * Source pill class, mirroring InstantIndexingOutcomes::sourcePill.
	 */
	function logSourcePill( source ) {
		return 'manual' === source ? 'rk-ui-pill rk-pill-source-manual' : 'rk-ui-pill rk-ui-pill-neutral';
	}

	/**
	 * Stats strip numbers from the response counts.
	 *
	 * Mirrors InstantIndexingOutcomes::statsFromCounts: accepted covers
	 * accepted plus pending, the retry category has no card of its own.
	 */
	function logStatsFromCounts( counts, total ) {
		function num( value ) {
			return 'number' === typeof value ? value : 0;
		}

		return {
			total: total,
			accepted: num( counts.accepted ) + num( counts.pending ),
			rejected: num( counts.rejected ),
			limited: num( counts.limited )
		};
	}

	/**
	 * Search term trimmed exactly like LogFilters::fromInput trims it.
	 */
	function logNormalizeSearch( value ) {
		return String( value || '' ).slice( 0, LOG_SEARCH_MAX ).trim();
	}

	/**
	 * Filter state read from the form fields.
	 *
	 * The form is the single source of truth, so the AJAX request always
	 * carries what a plain GET submit would carry. Unknown source and
	 * status values fall back to the unfiltered default, like the server.
	 * Every selector below is a fixed literal, no response value ever
	 * reaches a selector.
	 */
	function logReadState( form ) {
		var searchEl = form.querySelector( '[name="s"]' );
		var sourceEl = form.querySelector( '[name="rk_source"]' );
		var statusEl = form.querySelector( '[name="rk_status"]' );
		var slugEl = form.querySelector( '[name="page"]' );

		var source = sourceEl && 'string' === typeof sourceEl.value ? sourceEl.value : 'all';
		var status = statusEl && 'string' === typeof statusEl.value ? statusEl.value : 'all';

		if ( 'auto' !== source && 'manual' !== source ) {
			source = 'all';
		}

		if ( LOG_TAB_KEYS.indexOf( status ) === -1 ) {
			status = 'all';
		}

		return {
			search: logNormalizeSearch( searchEl && 'string' === typeof searchEl.value ? searchEl.value : '' ),
			source: source,
			status: status,
			slug: slugEl && 'string' === typeof slugEl.value ? slugEl.value : ''
		};
	}

	/**
	 * REST query string for one filter state plus page, defaults dropped.
	 */
	function logRestQuery( state, page ) {
		var parts = [];

		if ( '' !== state.search ) {
			parts.push( 's=' + encodeURIComponent( state.search ) );
		}

		if ( 'all' !== state.source ) {
			parts.push( 'rk_source=' + encodeURIComponent( state.source ) );
		}

		if ( 'all' !== state.status ) {
			parts.push( 'rk_status=' + encodeURIComponent( state.status ) );
		}

		if ( page > 1 ) {
			parts.push( 'rk_paged=' + page );
		}

		return parts.join( '&' );
	}

	/**
	 * Screen URL for one filter state plus page, defaults dropped.
	 *
	 * Mirrors InstantIndexingLogView::url: the bare action stays bare,
	 * anything else is appended as a query string. The page slug travels
	 * as a field of the GET form, so it is skipped here when the action
	 * already carries it.
	 */
	function logPageUrl( action, state, page ) {
		var base = String( action || '' );
		var parts = [];

		if ( '' !== state.slug && base.indexOf( 'page=' ) === -1 ) {
			parts.push( 'page=' + encodeURIComponent( state.slug ) );
		}

		if ( '' !== state.search ) {
			parts.push( 's=' + encodeURIComponent( state.search ) );
		}

		if ( 'all' !== state.source ) {
			parts.push( 'rk_source=' + encodeURIComponent( state.source ) );
		}

		if ( 'all' !== state.status ) {
			parts.push( 'rk_status=' + encodeURIComponent( state.status ) );
		}

		if ( page > 1 ) {
			parts.push( 'rk_paged=' + page );
		}

		if ( 0 === parts.length ) {
			return base;
		}

		return base + ( base.indexOf( '?' ) !== -1 ? '&' : '?' ) + parts.join( '&' );
	}

	/**
	 * Query parameters of one URL as a plain object.
	 */
	function logParseQuery( href ) {
		var params = {};
		var text = String( href || '' );
		var start = text.indexOf( '?' );

		if ( -1 === start ) {
			return params;
		}

		var hash = text.indexOf( '#', start );
		var query = text.substring( start + 1, -1 === hash ? text.length : hash );
		var pairs = query.split( '&' );
		var index;
		var cut;
		var rawKey;
		var rawValue;

		for ( index = 0; index < pairs.length; index++ ) {
			if ( '' === pairs[ index ] ) {
				continue;
			}

			cut = pairs[ index ].indexOf( '=' );
			rawKey = -1 === cut ? pairs[ index ] : pairs[ index ].substring( 0, cut );
			rawValue = -1 === cut ? '' : pairs[ index ].substring( cut + 1 );

			try {
				params[ decodeURIComponent( rawKey ) ] = decodeURIComponent( rawValue.replace( /\+/g, ' ' ) );
			} catch ( error ) {
				params[ rawKey ] = rawValue;
			}
		}

		return params;
	}

	/**
	 * Page numbers with zero marking an ellipsis gap.
	 *
	 * Mirrors InstantIndexingLogView::pageNumbers: every page shows when
	 * there are seven or fewer, otherwise the first plus the last plus a
	 * window around the current page.
	 */
	function logPageNumbers( total, current ) {
		var numbers = [];
		var index;

		if ( total <= 7 ) {
			for ( index = 1; index <= total; index++ ) {
				numbers.push( index );
			}

			return numbers;
		}

		numbers.push( 1 );

		if ( current > 3 ) {
			numbers.push( 0 );
		}

		var window = [ current - 1, current, current + 1 ];

		for ( index = 0; index < window.length; index++ ) {
			if ( window[ index ] > 1 && window[ index ] < total ) {
				numbers.push( window[ index ] );
			}
		}

		if ( current < total - 2 ) {
			numbers.push( 0 );
		}

		numbers.push( total );

		return numbers;
	}

	/**
	 * Visible range for the footer label, mirroring LogView::showing.
	 */
	function logShowing( page, perPage, filteredTotal ) {
		if ( filteredTotal <= 0 ) {
			return { from: 0, to: 0, total: 0 };
		}

		var from = ( page - 1 ) * perPage + 1;

		return {
			from: from,
			to: Math.min( from + perPage - 1, filteredTotal ),
			total: filteredTotal
		};
	}

	/**
	 * Footer label text for one visible range, server template in English.
	 */
	function logShowingText( showing ) {
		return 'Showing ' + showing.from + ' to ' + showing.to + ' of ' + showing.total + ' entries';
	}

	/**
	 * Create one element with an optional class plus textContent.
	 *
	 * Every response value reaches the page through this helper, so every
	 * value is written as text and never as markup.
	 */
	function logEl( doc, tag, className, text ) {
		var node = doc.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( 'undefined' !== typeof text ) {
			node.textContent = text;
		}

		return node;
	}

	/**
	 * Remove one node through its parent, quietly when already detached.
	 */
	function logRemove( node ) {
		if ( node && node.parentNode && node.parentNode.removeChild ) {
			node.parentNode.removeChild( node );
		}
	}

	/**
	 * Whether one display category offers a retry control.
	 *
	 * The PHP view is the authority: src/Admin/Views/instant-indexing.php
	 * sets its retryable flag as the negation of the success set, so an
	 * accepted or pending row never carries a control while every other
	 * category does. The rule is repeated here so the AJAX rebuilt table
	 * matches the server table instead of drifting from it.
	 */
	function logIsRetryable( category ) {
		return 'accepted' !== category && 'pending' !== category;
	}

	/**
	 * One hidden input for the retry form, name plus value as attributes.
	 */
	function logField( doc, name, value ) {
		var input = logEl( doc, 'input', '' );

		input.setAttribute( 'type', 'hidden' );
		input.setAttribute( 'name', name );
		input.setAttribute( 'value', String( value ) );

		return input;
	}

	/**
	 * Actions cell for one retryable row, the same form the PHP view builds.
	 *
	 * The form posts, it never fetches: method post with an empty action,
	 * the localized retry nonce, the action marker and the row id alone.
	 * The stored URL stays absent by design, it is never a field, never a
	 * link and never an href.
	 */
	function logBuildActions( doc, id ) {
		var cfg = config();
		var nonce = cfg && 'string' === typeof cfg.retryNonce ? cfg.retryNonce : '';
		var cell = logEl( doc, 'td', 'rk-col-actions' );
		var form = logEl( doc, 'form', 'rk-retry-form' );
		var submit = logEl( doc, 'button', 'button secondary rk-retry-submit', 'Retry' );

		form.setAttribute( 'method', 'post' );
		form.setAttribute( 'action', '' );
		form.appendChild( logField( doc, '_wpnonce', nonce ) );
		form.appendChild( logField( doc, 'rankkernel_indexnow_action', 'retry' ) );
		form.appendChild( logField( doc, 'rankkernel_indexnow_id', id ) );
		submit.setAttribute( 'type', 'submit' );
		form.appendChild( submit );
		cell.appendChild( form );

		return cell;
	}

	/**
	 * One table row for one raw REST row.
	 *
	 * The URL is displayed as text, exactly like the server markup, never
	 * as a link. Pills reuse the shared rk-ui classes, labels and classes
	 * come from the mirrored taxonomy above. A row that failed carries the
	 * same retry form the server renders, rebuilt from the REST row id.
	 */
	function logBuildRow( doc, row ) {
		var data = row || {};
		var code = 'number' === typeof data.code ? data.code : 0;
		var id = 'number' === typeof data.id && data.id > 0 ? data.id : 0;
		var category = logCategoryFor( code );
		var source = 'manual' === data.source ? 'manual' : 'auto';

		var tr = logEl( doc, 'tr' );
		var urlCell = logEl( doc, 'td', 'rk-col-url', String( data.url || '' ) );

		var statusCell = logEl( doc, 'td', 'rk-col-status' );
		statusCell.appendChild( logEl( doc, 'span', logStatusPill( category ), logStatusLabel( category ) ) );

		var sourceCell = logEl( doc, 'td', 'rk-col-source' );
		sourceCell.appendChild( logEl( doc, 'span', logSourcePill( source ), logSourceLabel( source ) ) );

		var timeCell = logEl( doc, 'td', 'rk-col-time', String( data.time || '' ) );
		var messageCell = logEl( doc, 'td', 'rk-col-message', String( data.message || '' ) );

		tr.appendChild( urlCell );
		tr.appendChild( statusCell );
		tr.appendChild( sourceCell );
		tr.appendChild( timeCell );
		tr.appendChild( messageCell );

		if ( logIsRetryable( category ) && id > 0 ) {
			tr.appendChild( logBuildActions( doc, id ) );
		}

		return tr;
	}

	/**
	 * Table skeleton matching the server markup, for the empty to rows turn.
	 */
	function logBuildTable( doc ) {
		var headers = [ 'URL', 'Status', 'Source', 'Time (UTC)', 'Message', 'Actions' ];
		var classes = [ 'rk-col-url', 'rk-col-status', 'rk-col-source', 'rk-col-time', 'rk-col-message', 'rk-col-actions' ];
		var wrap = logEl( doc, 'div', 'rk-ui-table-wrap' );
		var table = logEl( doc, 'table', 'rk-ui-table' );
		var head = logEl( doc, 'thead' );
		var row = logEl( doc, 'tr' );
		var body = logEl( doc, 'tbody' );
		var index;
		var cell;

		for ( index = 0; index < headers.length; index++ ) {
			cell = logEl( doc, 'th', classes[ index ], headers[ index ] );
			cell.setAttribute( 'scope', 'col' );
			row.appendChild( cell );
		}

		head.appendChild( row );
		table.appendChild( head );
		table.appendChild( body );
		wrap.appendChild( table );

		return { wrap: wrap, body: body };
	}

	/**
	 * Footer skeleton matching the server markup.
	 */
	function logBuildFooter( doc ) {
		var footer = logEl( doc, 'div', 'rk-log-footer' );

		footer.appendChild( logEl( doc, 'span', 'rk-showing', '' ) );

		return footer;
	}

	/**
	 * Empty filtered block matching the server markup.
	 */
	function logBuildEmpty( doc, clearUrl ) {
		var box = logEl( doc, 'div', 'rk-empty rk-empty-filtered' );
		var iconWrap = logEl( doc, 'div', 'rk-empty-icon' );
		var icon = logEl( doc, 'span', 'rk-icon', 'search' );

		iconWrap.setAttribute( 'aria-hidden', 'true' );
		icon.setAttribute( 'aria-hidden', 'true' );
		iconWrap.appendChild( icon );

		var clear = logEl( doc, 'a', 'rk-ui-btn rk-ui-btn-secondary' );
		var clearIcon = logEl( doc, 'span', 'rk-icon', 'filter_alt_off' );

		clear.setAttribute( 'href', clearUrl );
		clearIcon.setAttribute( 'aria-hidden', 'true' );
		clear.appendChild( clearIcon );
		clear.appendChild( doc.createTextNode( 'Clear filters' ) );

		box.appendChild( iconWrap );
		box.appendChild( logEl( doc, 'p', 'rk-empty-title', 'No submissions match your filters.' ) );
		box.appendChild( logEl( doc, 'p', 'rk-empty-body', 'Try a different search term or clear the filters.' ) );
		box.appendChild( clear );

		return box;
	}

	/**
	 * Pagination block matching the server markup for one page window.
	 */
	function logBuildPagination( doc, action, state, current, totalPages ) {
		var nav = logEl( doc, 'div', 'rk-ui-page-nums' );

		nav.setAttribute( 'role', 'navigation' );
		nav.setAttribute( 'aria-label', 'Submission log pages' );

		if ( current > 1 ) {
			var prev = logEl( doc, 'a', 'rk-ui-page-link', 'Previous' );
			prev.setAttribute( 'href', logPageUrl( action, state, current - 1 ) );
			nav.appendChild( prev );
		} else {
			var prevOff = logEl( doc, 'span', 'rk-ui-page-link is-disabled', 'Previous' );
			prevOff.setAttribute( 'aria-disabled', 'true' );
			nav.appendChild( prevOff );
		}

		var numbers = logPageNumbers( totalPages, current );
		var index;

		for ( index = 0; index < numbers.length; index++ ) {
			if ( 0 === numbers[ index ] ) {
				var gap = logEl( doc, 'span', 'rk-ui-page-gap', '…' );
				gap.setAttribute( 'aria-hidden', 'true' );
				nav.appendChild( gap );
			} else if ( numbers[ index ] === current ) {
				var here = logEl( doc, 'span', 'rk-ui-page-link is-current', String( numbers[ index ] ) );
				here.setAttribute( 'aria-current', 'page' );
				nav.appendChild( here );
			} else {
				var link = logEl( doc, 'a', 'rk-ui-page-link', String( numbers[ index ] ) );
				link.setAttribute( 'href', logPageUrl( action, state, numbers[ index ] ) );
				nav.appendChild( link );
			}
		}

		if ( current < totalPages ) {
			var next = logEl( doc, 'a', 'rk-ui-page-link', 'Next' );
			next.setAttribute( 'href', logPageUrl( action, state, current + 1 ) );
			nav.appendChild( next );
		} else {
			var nextOff = logEl( doc, 'span', 'rk-ui-page-link is-disabled', 'Next' );
			nextOff.setAttribute( 'aria-disabled', 'true' );
			nav.appendChild( nextOff );
		}

		return nav;
	}

	/**
	 * Status tabs in place: counts, current mark and hrefs.
	 *
	 * Hrefs are rebuilt too, so a tab clicked after an AJAX filter still
	 * carries the current search plus source instead of a stale pair.
	 * Tabs keep navigating normally, only their numbers refresh in place.
	 */
	function logRenderTabs( card, action, state, counts ) {
		var tabs = card.querySelectorAll( '.rk-ui-tabs .rk-ui-tab' );
		var index;
		var key;
		var tab;
		var countNode;
		var tabState;
		var active;

		for ( index = 0; index < tabs.length && index < LOG_TAB_KEYS.length; index++ ) {
			key = LOG_TAB_KEYS[ index ];
			tab = tabs[ index ];
			active = key === state.status;

			countNode = tab.querySelector ? tab.querySelector( '.rk-ui-count' ) : null;

			if ( countNode ) {
				countNode.textContent = String( 'number' === typeof counts[ key ] ? counts[ key ] : 0 );
			}

			if ( tab.setAttribute && tab.removeAttribute ) {
				tabState = { search: state.search, source: state.source, status: key, slug: state.slug };
				tab.setAttribute( 'href', logPageUrl( action, tabState, 1 ) );

				if ( tab.classList && tab.classList.toggle ) {
					tab.classList.toggle( 'is-current', active );
				}

				if ( active ) {
					tab.setAttribute( 'aria-current', 'page' );
				} else {
					tab.removeAttribute( 'aria-current' );
				}
			}
		}
	}

	/**
	 * Stats strip plus entry count in place, numbers only.
	 */
	function logRenderNumbers( card, stats, total ) {
		function setValue( selector, value ) {
			var node = card.querySelector( selector );

			if ( node ) {
				node.textContent = String( value );
			}
		}

		setValue( '.rk-stats .rk-stat-value', stats.total );
		setValue( '.rk-stat-value-positive', stats.accepted );
		setValue( '.rk-stat-value-negative', stats.rejected );
		setValue( '.rk-stat-value-warning', stats.limited );

		var entryCount = card.querySelector( '.rk-entry-count' );

		if ( entryCount ) {
			entryCount.textContent = total + ' entries';
		}
	}

	/**
	 * Table plus footer in place for one response.
	 *
	 * Swaps between the table and the empty filtered block exactly like
	 * the server does, so a filter with no matches never leaves a stale
	 * table behind. Pagination and the clear filters link are rebuilt
	 * from the same state the request carried.
	 */
	function logRenderTable( doc, card, action, state, data, current, perPage, filteredTotal ) {
		var rows = data.rows;

		if ( 0 === rows.length ) {
			logRemove( card.querySelector( '.rk-ui-table-wrap' ) );
			logRemove( card.querySelector( '.rk-log-footer' ) );

			if ( ! card.querySelector( '.rk-empty-filtered' ) ) {
				card.appendChild( logBuildEmpty( doc, action ) );
			}

			return;
		}

		logRemove( card.querySelector( '.rk-empty-filtered' ) );

		var footer = card.querySelector( '.rk-log-footer' );
		var body = card.querySelector( '.rk-ui-table tbody' );

		if ( ! body ) {
			var built = logBuildTable( doc );

			if ( footer && footer.parentNode === card && card.insertBefore ) {
				card.insertBefore( built.wrap, footer );
			} else {
				card.appendChild( built.wrap );
			}

			body = built.body;
		}

		if ( ! footer ) {
			footer = logBuildFooter( doc );
			card.appendChild( footer );
		}

		body.textContent = '';

		var index;

		for ( index = 0; index < rows.length; index++ ) {
			body.appendChild( logBuildRow( doc, rows[ index ] ) );
		}

		var showing = footer.querySelector ? footer.querySelector( '.rk-showing' ) : null;

		if ( ! showing ) {
			showing = logEl( doc, 'span', 'rk-showing', '' );
			footer.appendChild( showing );
		}

		showing.textContent = logShowingText( logShowing( current, perPage, filteredTotal ) );

		var totalPages = Math.max( 1, Math.ceil( filteredTotal / perPage ) );
		var nav = footer.querySelector ? footer.querySelector( '.rk-ui-page-nums' ) : null;
		var clear = footer.querySelector ? footer.querySelector( '.rk-filter-clear' ) : null;

		if ( totalPages > 1 ) {
			var fresh = logBuildPagination( doc, action, state, current, totalPages );

			if ( nav ) {
				if ( footer.replaceChild ) {
					footer.replaceChild( fresh, nav );
				} else {
					logRemove( nav );
					footer.appendChild( fresh );
				}
			} else if ( clear && footer.insertBefore && card ) {
				footer.insertBefore( fresh, clear );
			} else {
				footer.appendChild( fresh );
			}
		} else if ( nav ) {
			logRemove( nav );
		}

		var hasFilter = '' !== state.search || 'all' !== state.source || 'all' !== state.status;

		if ( hasFilter ) {
			if ( ! clear ) {
				clear = logEl( doc, 'a', 'rk-filter-clear', 'Clear filters' );
				footer.appendChild( clear );
			}

			if ( clear.setAttribute ) {
				clear.setAttribute( 'href', action );
			}
		} else if ( clear ) {
			logRemove( clear );
		}
	}

	/**
	 * Render one successful response, every value from the response.
	 *
	 * Counts, totals and rows all come from the REST payload, which reads
	 * through the same LogQuery layer as the server path, so the two agree
	 * by construction. The displayed page is clamped exactly like
	 * InstantIndexingLogView::page clamps it.
	 */
	function logRender( doc, card, action, state, data, page ) {
		var perPage = 'number' === typeof data.perPage && data.perPage > 0 ? data.perPage : LOG_PER_PAGE_FALLBACK;
		var filteredTotal = 'number' === typeof data.filteredTotal && data.filteredTotal >= 0 ? data.filteredTotal : 0;
		var total = 'number' === typeof data.total && data.total >= 0 ? data.total : 0;
		var rawCounts = data.statusCounts && 'object' === typeof data.statusCounts ? data.statusCounts : {};

		function countOf( key ) {
			return 'number' === typeof rawCounts[ key ] && rawCounts[ key ] >= 0 ? rawCounts[ key ] : 0;
		}

		var counts = {
			all: countOf( 'all' ),
			accepted: countOf( 'accepted' ),
			pending: countOf( 'pending' ),
			rejected: countOf( 'rejected' ),
			limited: countOf( 'limited' ),
			retry: countOf( 'retry' )
		};

		var totalPages = Math.max( 1, Math.ceil( filteredTotal / perPage ) );
		var current = Math.min( Math.max( 1, page ), totalPages );

		logRenderNumbers( card, logStatsFromCounts( counts, total ), total );
		logRenderTabs( card, action, state, counts );
		logRenderTable( doc, card, action, state, data, current, perPage, filteredTotal );
	}

	/**
	 * Copy one address bar state into the form fields.
	 */
	function logSyncForm( form, params ) {
		var searchEl = form.querySelector( '[name="s"]' );
		var sourceEl = form.querySelector( '[name="rk_source"]' );
		var statusEl = form.querySelector( '[name="rk_status"]' );

		if ( searchEl && 'undefined' !== typeof searchEl.value ) {
			searchEl.value = 'string' === typeof params.s ? params.s : '';
		}

		if ( sourceEl && 'undefined' !== typeof sourceEl.value ) {
			sourceEl.value = 'auto' === params.rk_source || 'manual' === params.rk_source ? params.rk_source : 'all';
		}

		if ( statusEl && 'undefined' !== typeof statusEl.value ) {
			statusEl.value = LOG_TAB_KEYS.indexOf( params.rk_status ) !== -1 ? params.rk_status : 'all';
		}
	}

	/**
	 * Wire the progressive enhancement log loader.
	 *
	 * Finds the GET filter form plus the log card and returns quietly when
	 * either is missing, when the localized REST config is missing, or
	 * when fetch is unavailable, so the script stays harmless on any other
	 * screen and the plain form plus anchors keep working. A generation
	 * token drops stale responses, mirroring the analysis editor: only the
	 * newest request may write. Any failure navigates to the equivalent
	 * GET URL instead of leaving a stale table behind.
	 */
	function wireLogLoader( scope ) {
		if ( ! scope || ! scope.querySelector || ! scope.createElement ) {
			return;
		}

		var cfg = config();

		if ( ! cfg || ! cfg.logUrl || ! cfg.restNonce ) {
			return;
		}

		if ( 'undefined' === typeof window || ! window.fetch ) {
			return;
		}

		var form = scope.querySelector( '.rk-log-filters' );
		var card = scope.querySelector( '.rk-log-card' );

		if ( ! form || ! card ) {
			return;
		}

		var doc = scope;
		var action = form.getAttribute ? ( form.getAttribute( 'action' ) || '' ) : '';
		var generation = 0;

		function controls() {
			return [
				scope.getElementById ? scope.getElementById( 'rk-log-search' ) : null,
				scope.getElementById ? scope.getElementById( 'rk-log-source' ) : null,
				form.querySelector( '[type="submit"]' )
			];
		}

		function setBusy( on ) {
			if ( card.setAttribute && card.removeAttribute ) {
				if ( on ) {
					card.setAttribute( 'aria-busy', 'true' );
				} else {
					card.removeAttribute( 'aria-busy' );
				}
			}

			var found = controls();
			var index;

			for ( index = 0; index < found.length; index++ ) {
				if ( found[ index ] ) {
					found[ index ].disabled = !! on;
				}
			}
		}

		function fallback( state, page ) {
			setBusy( false );

			if ( window.location ) {
				window.location.href = logPageUrl( action, state, page );
			} else if ( form.submit ) {
				try {
					form.submit();
				} catch ( error ) {
				}
			}
		}

		function load( page, push ) {
			var state = logReadState( form );
			var safePage = 'number' === typeof page && page > 0 ? Math.floor( page ) : 1;

			generation += 1;
			var token = generation;

			var query = logRestQuery( state, safePage );
			var url = String( cfg.logUrl ) + ( '' === query ? '' : ( String( cfg.logUrl ).indexOf( '?' ) !== -1 ? '&' : '?' ) + query );

			setBusy( true );

			window.fetch( url, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': String( cfg.restNonce ) }
			} ).then( function ( response ) {
				if ( ! response || ! response.ok ) {
					throw new Error( 'log request failed' );
				}

				return response.json();
			} ).then( function ( data ) {
				if ( token !== generation ) {
					return;
				}

				if ( ! data || 'object' !== typeof data || 'string' === typeof data.code || ! Array.isArray( data.rows ) ) {
					throw new Error( 'log response invalid' );
				}

				setBusy( false );
				logRender( doc, card, action, state, data, safePage );

				if ( push && window.history && window.history.pushState ) {
					try {
						window.history.pushState( {}, '', logPageUrl( action, state, safePage ) );
					} catch ( error ) {
					}
				}
			} ).catch( function () {
				if ( token !== generation ) {
					return;
				}

				fallback( state, safePage );
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			if ( event && event.preventDefault ) {
				event.preventDefault();
			}

			load( 1, true );
		} );

		card.addEventListener( 'click', function ( event ) {
			var target = event && event.target ? event.target : null;
			var link = target && target.closest ? target.closest( 'a.rk-ui-page-link' ) : null;

			if ( ! link || ! link.getAttribute ) {
				return;
			}

			var href = link.getAttribute( 'href' );

			if ( ! href ) {
				return;
			}

			var params = logParseQuery( href );
			var paged = parseInt( params.rk_paged || '', 10 );

			if ( event.preventDefault ) {
				event.preventDefault();
			}

			load( isNaN( paged ) || paged < 1 ? 1 : paged, true );
		} );

		if ( window.addEventListener ) {
			window.addEventListener( 'popstate', function () {
				var params = logParseQuery( window.location ? window.location.href : '' );

				logSyncForm( form, params );

				var paged = parseInt( params.rk_paged || '', 10 );

				load( isNaN( paged ) || paged < 1 ? 1 : paged, false );
			} );
		}
	}

	// Exposed so the node test harness can load this file in a vm and call
	// the pure validator directly, without a DOM.
	if ( 'undefined' !== typeof window ) {
		if ( ! window.rankkernelInstantIndexing ) {
			window.rankkernelInstantIndexing = {};
		}

		window.rankkernelInstantIndexing.validate = validate;
		window.rankkernelInstantIndexing.logLoader = {
			categoryFor: logCategoryFor,
			statusLabel: logStatusLabel,
			statusPill: logStatusPill,
			sourceLabel: logSourceLabel,
			sourcePill: logSourcePill,
			statsFromCounts: logStatsFromCounts,
			pageNumbers: logPageNumbers,
			showing: logShowing,
			showingText: logShowingText,
			restQuery: logRestQuery,
			pageUrl: logPageUrl,
			parseQuery: logParseQuery,
			readState: logReadState,
			wire: wireLogLoader
		};
		window.rankkernelInstantIndexing.notices = {
			hideNotice: hideNotice,
			isErrorNotice: isErrorNotice,
			wireDismiss: wireDismiss,
			wireAutoDismiss: wireAutoDismiss
		};
		window.rankkernelInstantIndexing.collapsibles = {
			isOpen: isOpen,
			setOpen: setOpen,
			wireCollapsible: wireCollapsible,
			wirePanelSwitcher: wirePanelSwitcher,
			wirePanelOpeners: wirePanelOpeners
		};
	}

	onReady( function () {
		if ( document.querySelectorAll ) {
			wireDismiss( document );
			wireAutoDismiss( document );
		}

		if ( document.querySelector ) {
			wireLogLoader( document );
		}

		if ( document.getElementById ) {
			var panels = wirePanelSwitcher(
				document,
				[
					{ toggleId: 'rk-submit-toggle', panelId: 'rk-submit-panel', hideId: 'rk-submit-hide' },
					{ toggleId: 'rk-settings-toggle', panelId: 'rk-settings-panel', hideId: 'rk-settings-hide' },
					{ toggleId: 'rk-help-toggle', panelId: 'rk-help-panel', hideId: 'rk-help-hide' }
				]
			);

			wirePanelOpeners( document, panels );
		}

		var field = document.getElementById ? document.getElementById( FIELD_ID ) : null;
		var status = document.getElementById ? document.getElementById( STATUS_ID ) : null;

		if ( ! field || ! status ) {
			return;
		}

		var button = document.getElementById( BUTTON_ID );
		var form = field.form || ( field.closest ? field.closest( 'form' ) : null );
		var host = String( config().siteHost || '' );
		var port = String( config().sitePort || '' );
		var timer = null;

		function run() {
			var result = validate( field.value, host, port );

			render( status, button, result );

			return result;
		}

		field.addEventListener( 'input', function () {
			if ( timer ) {
				window.clearTimeout( timer );
			}

			timer = window.setTimeout( run, DEBOUNCE_MS );
		} );

		// A submit with anything invalid is stopped here, so the request
		// cannot be sent with bad input.
		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				if ( run().disabled ) {
					event.preventDefault();
				}
			} );
		}

		run();
	} );
} )();
