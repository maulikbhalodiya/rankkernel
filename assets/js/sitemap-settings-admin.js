/**
 * RankKernel sitemap settings screen script.
 *
 * Plain script, no build step. Turns the server rendered tab links into
 * client side tabs: only the current panel shows, the highlight follows,
 * and pushState keeps the ?tab= address shareable without a scroll jump.
 * With JavaScript off the tab links still load the ?tab= pages and the
 * noscript style reveals every panel. Loaded on the sitemap settings
 * screen only.
 */
( function () {
	'use strict';

	var sprintf = ( window.wp && window.wp.i18n && typeof window.wp.i18n.sprintf === 'function' )
		? window.wp.i18n.sprintf
		: function ( format ) {
			var args = arguments;
			var index = 1;

			return String( format || '' ).replace( /%s/g, function () {
				return String( args[ index++ ] || '' );
			} );
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

		window.wp.a11y.speak( text );
	}

	function ready( callback ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', callback );
		} else {
			callback();
		}
	}

	/*
	 * Tab ids come from each link's data attribute, falling back to the
	 * tab query argument in its href so the markup degrades cleanly.
	 */
	function tabIdFromHref( href ) {
		var match = String( href || '' ).match( /[?&]tab=([^&#]+)/ );

		return match ? match[ 1 ] : '';
	}

	function linkTabId( link ) {
		var data = link.getAttribute( 'data-rk-tab' );

		if ( data ) {
			return data;
		}

		return tabIdFromHref( link.getAttribute( 'href' ) );
	}

	function tabIds( links ) {
		var ids = [];

		Array.prototype.forEach.call( links, function ( link ) {
			var id = linkTabId( link );

			if ( id ) {
				ids.push( id );
			}
		} );

		return ids;
	}

	function tabIdFromLocation( search, ids ) {
		var match = String( search || '' ).match( /[?&]tab=([^&#]+)/ );
		var wanted = match ? match[ 1 ] : '';

		if ( ids.indexOf( wanted ) !== -1 ) {
			return wanted;
		}

		return ids.length ? ids[ 0 ] : '';
	}

	function pickTab( search, ids ) {
		return tabIdFromLocation( search, ids );
	}

	ready( function () {
		if ( 'function' !== typeof document.querySelector || 'function' !== typeof document.getElementById ) {
			return;
		}

		var nav = document.querySelector( '.rk-ui-tabs' );

		if ( ! nav ) {
			return;
		}

		var links = nav.querySelectorAll( 'a.rk-ui-tab' );
		var ids = tabIds( links );

		if ( ! links.length || ! ids.length ) {
			return;
		}

		var panels = [];
		var byId = {};

		Array.prototype.forEach.call( ids, function ( id ) {
			var panel = document.querySelector( '[data-rk-tab-panel="' + id + '"]' );

			if ( ! panel ) {
				panel = document.getElementById( 'rk-sitemap-panel-' + id );
			}

			if ( panel ) {
				panel.setAttribute( 'data-rk-tab-panel', id );
				panels.push( panel );
				byId[ id ] = panel;
			}
		} );

		if ( ! panels.length ) {
			return;
		}

		function activate( id ) {
			Array.prototype.forEach.call( links, function ( link ) {
				var open = linkTabId( link ) === id;

				link.classList.toggle( 'is-current', open );

				if ( open ) {
					link.setAttribute( 'aria-current', 'page' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );

			Array.prototype.forEach.call( panels, function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-rk-tab-panel' ) !== id;
			} );
		}

		function activateFromLocation() {
			var search = window.location.search || '';

			activate( tabIdFromLocation( search, ids ) );
		}

		Array.prototype.forEach.call( links, function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				var id = linkTabId( link );

				if ( ! id || ids.indexOf( id ) === -1 ) {
					return;
				}

				event.preventDefault();
				activate( id );

				var href = link.getAttribute( 'href' ) || '';

				if ( window.history && 'function' === typeof window.history.pushState && href ) {
					window.history.pushState( null, '', href );
				} else if ( href ) {
					window.location.href = href;
				}
			} );
		} );

		window.addEventListener( 'popstate', activateFromLocation );
		window.addEventListener( 'hashchange', activateFromLocation );

		var initial = tabIdFromLocation( window.location.search, ids );
		activate( initial );

		var hash = String( window.location.hash || '' ).replace( /^#/, '' );
		var scrollTarget = hash ? document.getElementById( hash ) : null;

		if ( scrollTarget && 'function' === typeof scrollTarget.scrollIntoView ) {
			scrollTarget.scrollIntoView();
		} else if ( initial !== ids[ 0 ] && byId[ initial ] && 'function' === typeof byId[ initial ].scrollIntoView ) {
			byId[ initial ].scrollIntoView();
		}
	} );

	if ( 'undefined' !== typeof window ) {
		if ( ! window.rankkernelSitemapSettings ) {
			window.rankkernelSitemapSettings = {};
		}

		window.rankkernelSitemapSettings.pickTab = pickTab;
		window.rankkernelSitemapSettings.idsFromLinks = tabIds;
		window.rankkernelSitemapSettings.tabIdFromHref = tabIdFromHref;
	}
} )();
