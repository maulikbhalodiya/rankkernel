/**
 * General Settings section loader.
 *
 * Loads one settings section at a time without a full page reload, and saves
 * the robots, llms and htaccess actions in place. Every failure falls back to a
 * normal page load, so nothing breaks without JavaScript.
 *
 * Plain script, no build step. Loaded on the RankKernel General Settings
 * screen only.
 */
( function () {
	'use strict';

	function onReady( callback ) {
		if ( document.readyState !== 'loading' ) {
			callback();
		} else {
			document.addEventListener( 'DOMContentLoaded', callback );
		}
	}

	function sectionOf( href ) {
		var match = String( href || '' ).match( /[?&]section=([a-z0-9_-]+)/ );

		return match ? match[1] : '';
	}

	function confirmed( node ) {
		var message = node.getAttribute( 'data-rk-confirm' );

		return ! message || window.confirm( message );
	}

	onReady( function () {
		var shell = document.querySelector( '.rk-settings' );

		if ( ! shell ) {
			return;
		}

		var body = shell.querySelector( '.rk-settings-body' );
		var form = body ? body.closest( 'form' ) : null;

		if ( ! body || ! form ) {
			return;
		}

		function busy( state ) {
			body.classList.toggle( 'rk-settings-is-busy', !! state );
		}

		function activeSection() {
			var link = shell.querySelector( '.rk-settings-nav a.is-active' );

			return link ? sectionOf( link.getAttribute( 'href' ) ) : 'general';
		}

		function markActive( section ) {
			var links = shell.querySelectorAll( '.rk-settings-nav a' );

			Array.prototype.forEach.call( links, function ( link ) {
				var isActive = sectionOf( link.getAttribute( 'href' ) ) === section;

				link.classList.toggle( 'is-active', isActive );

				if ( isActive ) {
					link.setAttribute( 'aria-current', 'page' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );
		}

		function swap( html ) {
			body.innerHTML = html;
		}

		function loadSection( url, section ) {
			var target = new URL( url, window.location.href );

			target.searchParams.set( 'rk_partial', section || 'general' );
			busy( true );

			window.fetch( target.toString(), {
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'load failed' );
				}

				return response.text();
			} ).then( function ( html ) {
				swap( html );
				busy( false );
				markActive( section );
				window.history.pushState( {}, '', url );
			} ).catch( function () {
				window.location.href = url;
			} );
		}

		function saveSection( section, marker ) {
			var data = new FormData( form );

			data.set( 'rk_partial', section );
			data.set( marker, '1' );
			busy( true );

			window.fetch( window.location.href, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'save failed' );
				}

				return response.text();
			} ).then( function ( html ) {
				swap( html );
				busy( false );
				markActive( section );
			} ).catch( function () {
				busy( false );
				form.submit();
			} );
		}

		shell.addEventListener( 'click', function ( event ) {
			var node = event.target;

			if ( ! node || ! node.closest ) {
				return;
			}

			var nav = node.closest( '.rk-settings-nav a' );

			if ( nav ) {
				event.preventDefault();
				loadSection( nav.getAttribute( 'href' ), sectionOf( nav.getAttribute( 'href' ) ) );
				return;
			}

			var tab = node.closest( '.rk-robots-tabs .rk-tab' );

			if ( tab ) {
				event.preventDefault();
				loadSection( tab.getAttribute( 'href' ), sectionOf( tab.getAttribute( 'href' ) ) );
				return;
			}

			var marker = '';

			if ( node.closest( '[name="rk_robots_save"]' ) ) {
				marker = 'rk_robots_save';
			} else if ( node.closest( '[name="rk_robots_reset"]' ) ) {
				marker = 'rk_robots_reset';
			} else if ( node.closest( '[name="rk_llms_save"]' ) ) {
				marker = 'rk_llms_save';
			} else if ( node.closest( '[name="rk_llms_write"]' ) ) {
				marker = 'rk_llms_write';
			} else if ( node.closest( '[name="rk_llms_reset"]' ) ) {
				marker = 'rk_llms_reset';
			} else if ( node.closest( '[name="rk_htaccess_save"]' ) ) {
				marker = 'rk_htaccess_save';
			}

			if ( '' === marker ) {
				return;
			}

			event.preventDefault();

			var button = node.closest( 'button' );

			if ( button && ! confirmed( button ) ) {
				return;
			}

			saveSection( activeSection(), marker );
		} );

		window.addEventListener( 'popstate', function () {
			window.location.reload();
		} );
	} );
} )();
