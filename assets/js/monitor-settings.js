/**
 * 404 Monitor settings gear toggle.
 *
 * Small progressive enhancement only. The panel is server rendered with the
 * hidden attribute, so without this file the noscript style in the view
 * reveals it. Clicking the gear never touches the URL hash: it opens and
 * closes the panel in place and scrolls it into view without a jump.
 */
( function () {
	'use strict';

	/**
	 * Set the open state of the panel plus its toggle button.
	 *
	 * Closing only hides; it never disables, so every nonce field plus the
	 * submit button still posts when the panel is open again.
	 */
	function setOpen( toggle, panel, open ) {
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
	 * Bring the opened panel into view without surprising motion users.
	 *
	 * Smooth scrolling applies only when reduced motion is off. The call is
	 * guarded so markup without scrollIntoView stays safe.
	 */
	function scrollPanelIntoView( panel ) {
		if ( ! panel || ! panel.scrollIntoView ) {
			return;
		}

		var reduce = false;

		try {
			if ( window.matchMedia ) {
				reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			}
		} catch ( error ) {
			reduce = false;
		}

		try {
			panel.scrollIntoView( { block: 'nearest', behavior: reduce ? 'auto' : 'smooth' } );
		} catch ( error ) {
			try {
				panel.scrollIntoView();
			} catch ( ignored ) {
				// Scrolling is best effort; the panel is already open.
			}
		}
	}

	/**
	 * Wire the gear toggle plus the banner link against the live panel.
	 *
	 * The gear toggles: first click opens, a re-click on the same panel
	 * closes it. The banner link only opens, and both prevent the default
	 * anchor jump so the URL hash is never touched (GH-207 rule).
	 */
	function wire( scope ) {
		if ( ! scope || ! scope.getElementById ) {
			return null;
		}

		var toggle = scope.getElementById( 'rk-monitor-settings-toggle' );
		var panel  = scope.getElementById( 'rk-monitor-settings' );
		var banner = scope.getElementById( 'rk-monitor-settings-link' );

		if ( ! toggle || ! panel ) {
			return null;
		}

		toggle.addEventListener( 'click', function () {
			var willOpen = !! panel.hidden;

			setOpen( toggle, panel, willOpen );

			if ( willOpen ) {
				scrollPanelIntoView( panel );
			}
		} );

		if ( banner ) {
			banner.addEventListener( 'click', function ( event ) {
				if ( event && event.preventDefault ) {
					event.preventDefault();
				}

				setOpen( toggle, panel, true );
				scrollPanelIntoView( panel );
			} );
		}

		return { toggle: toggle, panel: panel, banner: banner };
	}

	if ( 'undefined' !== typeof document && document.addEventListener ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			wire( document );
		} );
	}

	if ( 'undefined' !== typeof window ) {
		if ( ! window.rankkernelMonitorSettings ) {
			window.rankkernelMonitorSettings = {};
		}

		window.rankkernelMonitorSettings.setOpen = setOpen;
		window.rankkernelMonitorSettings.wire = wire;
	}
} )();
