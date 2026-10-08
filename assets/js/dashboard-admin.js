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
 * @package RankKernel
 */

( function () {
	'use strict';

	var cfg = window.rankkernelDashboard || {};

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
