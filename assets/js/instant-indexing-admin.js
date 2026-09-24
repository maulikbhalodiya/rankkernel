/**
 * Instant Indexing manual submission, live multi URL validation.
 *
 * Enables the submit button only while every non empty line is an absolute
 * http or https URL on this site. The server validates again, so the button
 * ships enabled in the HTML and a user without JavaScript can still submit.
 * Only the site host and the batch limit are localized into this file, the
 * API key never reaches the browser.
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
	 * Returns the per line entries plus the summary line and whether the
	 * submit button must stay disabled.
	 */
	function validate( text, host ) {
		var lines = String( text || '' ).split( /\r\n|\r|\n/ );
		var entries = [];
		var validCount = 0;
		var invalidCount = 0;
		var index;
		var url;
		var reason;

		for ( index = 0; index < lines.length; index++ ) {
			url = lines[ index ].trim();

			if ( '' === url ) {
				continue;
			}

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
	 * A URL is user input, so it is never written as markup.
	 */
	function render( status, button, result ) {
		var summary = document.createElement( 'p' );
		var list;
		var item;
		var entry;
		var index;

		if ( result.invalidCount > 0 ) {
			summary.className = 'notice notice-warning inline';
		} else if ( result.total > 0 ) {
			summary.className = 'notice notice-success inline';
		} else {
			summary.className = 'description';
		}

		summary.textContent = result.summary;

		status.textContent = '';
		status.appendChild( summary );

		if ( result.invalidCount > 0 ) {
			list = document.createElement( 'ul' );
			list.className = 'rankkernel-indexnow-validation';

			for ( index = 0; index < result.entries.length; index++ ) {
				entry = result.entries[ index ];
				item = document.createElement( 'li' );

				item.className = entry.valid ? 'is-valid' : 'is-invalid';
				item.textContent = entry.valid ? '\u2713 ' + entry.url : entry.url + ' ' + entry.reason;

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

		if ( ! field || ! status ) {
			return;
		}

		var button = document.getElementById( BUTTON_ID );
		var host = String( config().siteHost || '' );
		var timer = null;

		function run() {
			render( status, button, validate( field.value, host ) );
		}

		field.addEventListener( 'input', function () {
			if ( timer ) {
				window.clearTimeout( timer );
			}

			timer = window.setTimeout( run, DEBOUNCE_MS );
		} );

		if ( button ) {
			button.addEventListener( 'click', function ( event ) {
				run();

				if ( button.disabled ) {
					event.preventDefault();
				}
			} );
		}

		run();
	} );
} )();
