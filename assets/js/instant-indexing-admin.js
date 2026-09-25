/**
 * Instant Indexing manual submission, live multi URL validation.
 *
 * Enables the submit button only while every non empty line is an absolute
 * http or https URL on this site. The server validates again, so the button
 * ships enabled in the HTML and a user without JavaScript can still submit.
 * Only the site host is localized into this file, the API key never reaches
 * the browser.
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
	 * Reason a single line cannot be submitted, empty when it can.
	 *
	 * The URL parser lowercases the host and the comparison against the
	 * site host stays exact, so www and the apex are different hosts.
	 */
	function reasonFor( url, host ) {
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

		if ( String( parsed.hostname ) !== String( host || '' ) ) {
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
	function validate( text, host ) {
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

			reason = reasonFor( url, host );

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

				if ( notice ) {
					notice.style.display = 'none';
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
	}

	onReady( function () {
		var field = document.getElementById( FIELD_ID );
		var status = document.getElementById( STATUS_ID );

		if ( document.querySelectorAll ) {
			wireDismiss( document );
		}

		if ( ! field || ! status ) {
			return;
		}

		var button = document.getElementById( BUTTON_ID );
		var form = field.form || ( field.closest ? field.closest( 'form' ) : null );
		var host = String( config().siteHost || '' );
		var timer = null;

		function run() {
			var result = validate( field.value, host );

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
