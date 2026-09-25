/**
 * General Settings section loader.
 *
 * Loads one settings section at a time without a full page reload, and saves
 * the robots, llms and htaccess actions in place, and binds the social media
 * picker. Every failure falls back to a normal page load, so nothing breaks
 * without JavaScript.
 *
 * Plain script, no build step. Loaded on the RankKernel General Settings
 * screen only.
 */
( function () {
	'use strict';

	var __ = ( window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function' )
		? window.wp.i18n.__
		: function ( text ) {
			return text;
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

		var mediaFrame = null;

		function openSocialMedia() {
			if ( 'undefined' === typeof wp || ! wp.media ) {
				return;
			}

			if ( ! mediaFrame ) {
				mediaFrame = wp.media( {
					title: 'Select default social image',
					button: { text: 'Use this image' },
					multiple: false,
					library: { type: 'image' }
				} );

				mediaFrame.on( 'select', function () {
					var attachment = mediaFrame.state().get( 'selection' ).first();
					var image = document.getElementById( 'rk-social-default-image' );
					var imageId = document.getElementById( 'rk-social-default-image-id' );
					var preview = document.getElementById( 'rk-social-default-image-preview' );
					var remove = document.getElementById( 'rk-social-default-image-remove' );

					if ( ! attachment || ! image || ! imageId ) {
						return;
					}

					var url = attachment.get( 'url' );
					var id = attachment.get( 'id' );

					if ( 'string' !== typeof url || '' === url || ! id ) {
						return;
					}

					image.value = url;
					imageId.value = String( id );

					if ( preview ) {
						preview.src = url;
						preview.style.display = '';
					}

					if ( remove ) {
						remove.style.display = '';
					}
				} );
			}

			mediaFrame.open();
		}

		function clearSocialMedia() {
			var image = document.getElementById( 'rk-social-default-image' );
			var imageId = document.getElementById( 'rk-social-default-image-id' );
			var preview = document.getElementById( 'rk-social-default-image-preview' );
			var remove = document.getElementById( 'rk-social-default-image-remove' );

			if ( image ) {
				image.value = '';
			}

			if ( imageId ) {
				imageId.value = '0';
			}

			if ( preview ) {
				preview.src = '';
				preview.style.display = 'none';
			}

			if ( remove ) {
				remove.style.display = 'none';
			}
		}

		document.addEventListener( 'click', function ( event ) {
			var node = event.target;

			if ( ! node || ! node.closest ) {
				return;
			}

			if ( node.closest( '#rk-social-default-image-select' ) ) {
				event.preventDefault();
				openSocialMedia();
				return;
			}

			if ( node.closest( '#rk-social-default-image-remove' ) ) {
				event.preventDefault();
				clearSocialMedia();
			}
		} );

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
				rankkernelAnnounce( __( 'Settings section loaded.', 'rankkernel' ) );
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
				if ( -1 !== String( marker ).indexOf( 'reset' ) ) {
					rankkernelAnnounce( __( 'Settings reset.', 'rankkernel' ) );
				} else {
					rankkernelAnnounce( __( 'Settings updated.', 'rankkernel' ) );
				}
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
