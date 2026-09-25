/**
 * Instant Indexing manual submission, live multi URL validation.
 *
 * Enables the submit button only while every non empty line is an absolute
 * http or https URL on this site. The server validates again, so the button
 * ships enabled in the HTML and a user without JavaScript can still submit.
 * Only the site host and the site port are localized into this file, the API
 * key never reaches the browser.
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

		return name.indexOf( 'rk-notice-error' ) !== -1;
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

		notices = scope.querySelectorAll( '.rk-instant-indexing .rk-notice' );

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
		var buttons = scope.querySelectorAll( '.rk-instant-indexing .rk-notice-dismiss' );
		var index;

		for ( index = 0; index < buttons.length; index++ ) {
			buttons[ index ].addEventListener( 'click', function ( event ) {
				var notice = event.target && event.target.closest ? event.target.closest( '.rk-notice' ) : null;

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

	// Exposed so the node test harness can load this file in a vm and call
	// the pure validator directly, without a DOM.
	if ( 'undefined' !== typeof window ) {
		if ( ! window.rankkernelInstantIndexing ) {
			window.rankkernelInstantIndexing = {};
		}

		window.rankkernelInstantIndexing.validate = validate;
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
