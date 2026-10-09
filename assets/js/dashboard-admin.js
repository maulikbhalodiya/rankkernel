/**
 * Dashboard, module toggle AJAX progressive enhancement.
 *
 * Every module row posts a small form. With JavaScript this layer intercepts
 * the submit and POSTs the same boolean to the REST module toggle endpoint,
 * then flips the switch and refreshes the attention bar in place. When the
 * endpoint is unreachable, or REST is unavailable, or the nonce is missing,
 * the handler falls back to the native form submit so the same toggle still
 * works through the plain POST plus redirect path.
 *
 * Planned modules never render a toggle form, so they are never wired here;
 * their disabled button stays disabled.
 *
 * Configuration is injected by wp_localize_script under window.rankkernelDashboard:
 *   modulesUrl {string} REST base, e.g. /wp-json/rankkernel/v1/modules
 *   restNonce  {string} Nonce for the wp_rest nonce action
 *
 * After a successful toggle the sidebar submenu is refreshed in place:
 * the current admin page is re-requested, its rankkernel submenu is
 * parsed out of the response, and the live one is swapped only when the
 * normalized HTML actually differs. Any failure leaves the menu alone —
 * the next full page load renders the correct items. The switch
 * itself is never locked; rapid toggles abort or supersede older
 * refreshes via a generation guard.
 *
 * @package RankKernel
 */

( function () {
	'use strict';

	var cfg = window.rankkernelDashboard || {};

	var __ = ( window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function' )
		? window.wp.i18n.__
		: function ( text ) {
			return text;
		};

	var sprintf = ( window.wp && window.wp.i18n && typeof window.wp.i18n.sprintf === 'function' )
		? window.wp.i18n.sprintf
		: function ( text, param ) {
			return String( text || '' ).replace( '%s', String( param || '' ) );
		};

	/**
	 * Speak an accessible announcement when wp.a11y is available.
	 *
	 * @param {string} text Translated message to announce.
	 */
	function rankkernelAnnounce( text ) {
		if ( ! window.wp || ! window.wp.a11y || typeof window.wp.a11y.speak !== 'function' ) {
			return;
		}

		window.wp.a11y.speak( text, 'polite' );
	}

	/**
	 * Translate through wp.i18n when present; fall back to the raw string.
	 */
	var __ = ( window.wp && window.wp.i18n && 'function' === typeof window.wp.i18n.__ )
		? window.wp.i18n.__
		: function ( text ) {
			return text;
		};

	/**
	 * Announce an accessible status line when wp.a11y is present.
	 */
	function dashboardAnnounce( text ) {
		if ( ! window.wp || ! window.wp.a11y || 'function' !== typeof window.wp.a11y.speak ) {
			return;
		}

		window.wp.a11y.speak( text );
	}

	/**
	 * Read the module id the form has always posted.
	 */
	function moduleId( form ) {
		var input = form.querySelector( 'input[name="rankkernel_module_toggle"]' );
		return input ? String( input.value || '' ) : '';
	}

	/**
	 * Display name of the module for one form.
	 */
	function moduleName( form ) {
		var row = form.closest( '.rk-dashboard-module' );
		var title = row ? row.querySelector( '.rk-dashboard-module-title' ) : null;
		return title ? String( title.textContent || '' ).trim() : '';
	}

	/**
	 * Swap the verb in the switch aria-label so screen readers stay accurate.
	 */
	function flippedLabel( label, nowOn ) {
		var text = String( label || '' );
		if ( nowOn ) {
			return text.replace( /^Turn on/, 'Turn off' );
		}
		return text.replace( /^Turn off/, 'Turn on' );
	}

	/**
	 * Recompute the attention bar and its stat from the switch states in the
	 * DOM, no extra request.
	 */
	function updateAttention() {
		var forms = document.querySelectorAll( 'form.rk-dashboard-module-toggle' );
		var off = [];

		for ( var i = 0; i < forms.length; i++ ) {
			var button = forms[ i ].querySelector( 'button[role="switch"]' );
			if ( button && 'true' !== button.getAttribute( 'aria-checked' ) ) {
				var name = moduleName( forms[ i ] );
				if ( name ) {
					off.push( name );
				}
			}
		}

		var count = off.length;

		var countEl = document.getElementById( 'rk-attention-count' );
		if ( countEl ) {
			countEl.textContent = String( count );
		}

		var tagEl = document.getElementById( 'rk-attention-tag' );
		if ( tagEl ) {
			if ( count > 0 ) {
				tagEl.removeAttribute( 'hidden' );
			} else {
				tagEl.setAttribute( 'hidden', '' );
			}
		}

		var iconWrap = document.getElementById( 'rk-attention-stat-icon' );
		if ( iconWrap ) {
			iconWrap.classList.remove( 'rk-dashboard-stat-icon-neutral' );
			iconWrap.classList.remove( 'rk-dashboard-stat-icon-positive' );
			iconWrap.classList.add( count > 0 ? 'rk-dashboard-stat-icon-neutral' : 'rk-dashboard-stat-icon-positive' );

			var icon = iconWrap.querySelector( '.rk-icon' );
			if ( icon ) {
				icon.textContent = count > 0 ? 'info' : 'check_circle';
			}
		}

		var bar = document.getElementById( 'rk-attention-bar' );
		if ( bar ) {
			if ( count > 0 ) {
				bar.style.display = '';

				var barCount = document.getElementById( 'rk-attention-bar-count' );
				if ( barCount ) {
					barCount.textContent = 1 === count ? '1 optional module is off' : count + ' optional modules are off';
				}

				var names = document.getElementById( 'rk-attention-bar-names' );
				if ( names ) {
					names.textContent = '• ' + off.join( ', ' );
				}

				var body = document.getElementById( 'rk-attention-bar-text' );
				if ( body ) {
					body.textContent = 'Switched off: ' + off.join( ', ' ) + '. Turn modules on or off from the Modules list.';
				}
			} else {
				bar.style.display = 'none';
			}
		}
	}

	/**
	 * Collapse markup whitespace so two serializations of the same
	 * submenu compare equal regardless of formatting.
	 */
	function normalizeHtml( html ) {
		return String( html || '' )
			.replace( />\s+</g, '><' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	/**
	 * Whether two submenu serializations render the same items.
	 */
	function submenusEqual( a, b ) {
		return normalizeHtml( a ) === normalizeHtml( b );
	}

	/**
	 * Locate the rankkernel submenu inside a document.
	 */
	function findSubmenu( doc ) {
		if ( ! doc ) {
			return null;
		}

		var top = doc.getElementById ? doc.getElementById( 'toplevel_page_rankkernel' ) : null;

		if ( ! top && doc.querySelector ) {
			top = doc.querySelector( '#toplevel_page_rankkernel' );
		}

		return top && top.querySelector ? top.querySelector( '.wp-submenu' ) : null;
	}

	/**
	 * Whether the user asked for reduced motion.
	 */
	function prefersReducedMotion() {
		try {
			if ( window.matchMedia ) {
				return !! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			}
		} catch ( error ) {
			return false;
		}

		return false;
	}

	/**
	 * Pull the submenu inner HTML out of a full admin page response.
	 */
	function extractSubmenuHtml( html ) {
		if ( 'function' !== typeof window.DOMParser ) {
			return null;
		}

		try {
			var parsed = new window.DOMParser().parseFromString( String( html ), 'text/html' );
			var submenu = findSubmenu( parsed );

			return submenu ? submenu.innerHTML : null;
		} catch ( error ) {
			return null;
		}
	}

	var menuRefreshToken = 0;
	var menuRefreshController = null;

	/**
	 * Swap the live submenu contents with fresh markup, fading out/in
	 * unless the user prefers reduced motion. Focus stays on the toggle
	 * that was clicked; the submenu links are plain anchors and carry no
	 * bound listeners, so only the reference is re-acquired afterwards.
	 */
	function swapSubmenu( freshHtml ) {
		var current = findSubmenu( document );

		if ( ! current ) {
			return;
		}

		var apply = function () {
			var live = findSubmenu( document );

			if ( ! live ) {
				return;
			}

			live.innerHTML = freshHtml;

			var updated = findSubmenu( document );

			if ( updated && updated.style ) {
				updated.style.opacity = '1';
			}

			dashboardAnnounce( __( 'Sidebar menu updated.', 'rankkernel' ) );
		};

		if ( prefersReducedMotion() || ! current.style ) {
			apply();
			return;
		}

		current.style.transition = 'opacity 150ms ease';
		current.style.opacity = '0';
		window.setTimeout( apply, 150 );
	}

	/**
	 * Re-fetch the current admin page and refresh the sidebar submenu
	 * when its normalized markup differs. Never interrupts the caller:
	 * fetch, parse, or generation loss all leave the menu untouched.
	 */
	function refreshMenu() {
		if ( ! document.getElementById || ! document.getElementById( 'toplevel_page_rankkernel' ) ) {
			return;
		}

		if ( 'function' !== typeof window.fetch || ! window.location || ! window.location.href ) {
			return;
		}

		// Last intent wins: a newer toggle supersedes any in-flight one.
		menuRefreshToken++;
		var token = menuRefreshToken;

		if ( menuRefreshController && 'function' === typeof menuRefreshController.abort ) {
			try {
				menuRefreshController.abort();
			} catch ( error ) {
				// Abort is best effort; the token guard drops stale results.
			}
		}

		menuRefreshController = null;

		var signal;

		if ( 'function' === typeof window.AbortController ) {
			menuRefreshController = new window.AbortController();
			signal = menuRefreshController.signal;
		}

		window.fetch( String( window.location.href ), {
			credentials: 'same-origin',
			signal: signal
		} ).then( function ( response ) {
			if ( ! response || ! response.ok ) {
				throw new Error( 'menu refresh failed' );
			}

			return response.text();
		} ).then( function ( html ) {
			if ( token !== menuRefreshToken ) {
				return;
			}

			var fresh = extractSubmenuHtml( html );
			var current = findSubmenu( document );

			if ( null === fresh || ! current ) {
				return;
			}

			if ( submenusEqual( fresh, current.innerHTML ) ) {
				return;
			}

			swapSubmenu( fresh );
		} ).catch( function () {
			// Leave the menu as-is; the next full page load is correct.
		} );
	}

	window.rankkernelMenuRefresh = {
		normalizeHtml: normalizeHtml,
		submenusEqual: submenusEqual,
		extractSubmenuHtml: extractSubmenuHtml,
		findSubmenu: findSubmenu
	};

	/**
	 * Send the toggle to REST, flip state on success, fall back to the native
	 * form post otherwise.
	 */
	function submitToggle( form ) {
		var button = form.querySelector( 'button[role="switch"]' );
		var id = moduleId( form );
		var nowOn = ! button || 'true' !== button.getAttribute( 'aria-checked' );

		if ( ! id || ! cfg.modulesUrl || ! cfg.restNonce || typeof window.fetch !== 'function' ) {
			form.submit();
			return;
		}

		window.fetch( String( cfg.modulesUrl ).replace( /\/+$/, '' ) + '/' + encodeURIComponent( id ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': String( cfg.restNonce )
			},
			body: JSON.stringify( { enabled: nowOn } )
		} ).then( function ( response ) {
			if ( ! response || ! response.ok ) {
				throw new Error( 'module toggle failed' );
			}

			if ( button ) {
				button.setAttribute( 'aria-checked', nowOn ? 'true' : 'false' );
				button.classList.toggle( 'is-on', nowOn );
				button.setAttribute( 'aria-label', flippedLabel( button.getAttribute( 'aria-label' ), nowOn ) );
			}

			updateAttention();
			refreshMenu();

			var name = moduleName( form );
			var msg;

			if ( name ) {
				msg = nowOn
					/* translators: %s: Module name */
					? sprintf( __( '%s module enabled.', 'rankkernel' ), name )
					/* translators: %s: Module name */
					: sprintf( __( '%s module disabled.', 'rankkernel' ), name );
			} else {
				msg = nowOn
					? __( 'Module enabled.', 'rankkernel' )
					: __( 'Module disabled.', 'rankkernel' );
			}

			rankkernelAnnounce( msg );
		} ).catch( function () {
			form.submit();
		} );
	}

	function bind() {
		var forms = document.querySelectorAll( 'form.rk-dashboard-module-toggle' );

		for ( var i = 0; i < forms.length; i++ ) {
			( function ( form ) {
				form.addEventListener( 'submit', function ( event ) {
					if ( event && typeof event.preventDefault === 'function' ) {
						event.preventDefault();
					}
					submitToggle( form );
				} );
			} )( forms[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}
} )();
