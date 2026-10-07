/**
 * RankKernel schema settings screen script.
 *
 * Plain script, no build step. Opens the WordPress media library for the
 * organization logo, shows a preview, and clears on remove. It also turns
 * the social profiles textarea into a chip editor when JavaScript runs:
 * the stored URLs render as chips, the add row appends a newline separated
 * entry, and the textarea stays the real field the form submits, so the
 * page works with JavaScript off. Loaded on the schema settings screen
 * only.
 */
( function () {
	'use strict';

	// The translator falls back to the raw string when wp.i18n is absent. Each announcement
	// literal is translated at its call site instead, because the WordPress extractor only reads
	// literal arguments and a message passed through this helper would stay invisible to it.
	var __ = ( window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function' )
		? window.wp.i18n.__
		: function ( text ) { return text; };

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

	ready( function () {
		var select  = document.getElementById( 'rk-org-logo-select' );
		var remove  = document.getElementById( 'rk-org-logo-remove' );
		var input   = document.getElementById( 'rk-org-logo' );
		var preview = document.getElementById( 'rk-org-logo-preview' );

		if ( ! select || ! input || ! preview ) {
			return;
		}

		var frame = null;

		select.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( 'undefined' === typeof wp || ! wp.media ) {
				return;
			}

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: __( 'Select organization logo', 'rankkernel' ),
				button: { text: __( 'Use this image', 'rankkernel' ) },
				multiple: false,
				library: { type: 'image' }
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first();

				if ( ! attachment ) {
					return;
				}

				var url = attachment.get( 'url' );

				if ( 'string' !== typeof url || '' === url ) {
					return;
				}

				input.value      = url;
				preview.src      = url;
				preview.style.display = '';

				if ( remove ) {
					remove.style.display = '';
				}

				rankkernelAnnounce( __( 'Organization logo updated.', 'rankkernel' ) );
			} );

			frame.open();
		} );

		if ( remove ) {
			remove.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				input.value           = '';
				preview.src           = '';
				preview.style.display = 'none';
				remove.style.display  = 'none';

				rankkernelAnnounce( __( 'Organization logo removed.', 'rankkernel' ) );
			} );
		}
	} );

	/*
	 * Social profiles chip editor. The textarea is the control and the
	 * source of truth; this block only presents its lines as chips and
	 * writes every add or remove back into it.
	 */
	ready( function () {
		var app = document.getElementById( 'rk-schema-sameas-app' );

		if ( ! app ) {
			return;
		}

		var textarea = document.getElementById( 'rk-org-sameas' );
		var list     = document.getElementById( 'rk-schema-sameas-chips' );
		var input    = document.getElementById( 'rk-schema-sameas-new' );
		var add      = document.getElementById( 'rk-schema-sameas-add' );

		if ( ! textarea || ! list || ! input || ! add ) {
			return;
		}

		var removeLabel  = app.getAttribute( 'data-remove-label' ) || __( 'Remove', 'rankkernel' );
		var invalidLabel = app.getAttribute( 'data-invalid-label' ) || __( 'Enter a valid URL.', 'rankkernel' );

		function values() {
			var out   = [];
			var lines = textarea.value.split( /\r\n|\r|\n/ );

			for ( var i = 0; i < lines.length; i++ ) {
				var value = lines[i].trim();

				if ( '' !== value && -1 === out.indexOf( value ) ) {
					out.push( value );
				}
			}

			return out;
		}

		function sync( next ) {
			textarea.value = next.join( '\n' );
		}

		function chip( url ) {
			var item = document.createElement( 'li' );
			item.className = 'rk-schema-profile-chip';

			var text  = document.createElement( 'span' );
			text.className = 'rk-schema-profile-chip-url';

			var rest   = url.replace( /^https?:\/\//i, '' );
			var cut    = rest.indexOf( '/' );
			var muted  = document.createElement( 'span' );
			var strong = document.createElement( 'span' );

			muted.className  = 'rk-schema-profile-chip-muted';
			strong.className = 'rk-schema-profile-chip-strong';
			muted.textContent  = -1 === cut ? rest : rest.slice( 0, cut + 1 );
			strong.textContent = -1 === cut ? '' : rest.slice( cut + 1 );

			text.appendChild( muted );
			text.appendChild( strong );

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'rk-schema-profile-chip-remove';
			button.setAttribute( 'aria-label', removeLabel + ': ' + url );

			var icon = document.createElement( 'span' );
			icon.className = 'rk-icon';
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.textContent = 'close';

			button.appendChild( icon );
			button.addEventListener( 'click', function () {
				var next = [];
				var current = values();

				for ( var i = 0; i < current.length; i++ ) {
					if ( current[i] !== url ) {
						next.push( current[i] );
					}
				}

				sync( next );
				render();

				if ( next.length > 0 ) {
					var first = list.querySelector( '.rk-schema-profile-chip-remove' );

					if ( first ) {
						first.focus();
					}
				} else {
					input.focus();
				}

				/* translators: %s: profile URL */
				rankkernelAnnounce( sprintf( __( 'Removed profile URL %s.', 'rankkernel' ), url ) );
			} );

			item.appendChild( text );
			item.appendChild( button );

			return item;
		}

		function render() {
			while ( list.firstChild ) {
				list.removeChild( list.firstChild );
			}

			var current = values();

			for ( var i = 0; i < current.length; i++ ) {
				list.appendChild( chip( current[i] ) );
			}
		}

		function addUrl() {
			var value = input.value.trim();

			if ( '' === value ) {
				return;
			}

			if ( ! /^https?:\/\/\S+$/i.test( value ) ) {
				input.setCustomValidity( invalidLabel );
				input.reportValidity();

				return;
			}

			input.setCustomValidity( '' );

			var current = values();

			if ( -1 === current.indexOf( value ) ) {
				current.push( value );
				sync( current );
				render();

				/* translators: %s: profile URL */
				rankkernelAnnounce( sprintf( __( 'Added profile URL %s.', 'rankkernel' ), value ) );
			} else {
				/* translators: %s: profile URL */
				rankkernelAnnounce( sprintf( __( 'Profile URL %s is already in the list.', 'rankkernel' ), value ) );
			}

			input.value = '';
			input.focus();
		}

		add.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			addUrl();
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || 13 === event.keyCode ) {
				event.preventDefault();
				addUrl();
			}
		} );

		input.addEventListener( 'input', function () {
			input.setCustomValidity( '' );
		} );

		render();
		app.classList.add( 'is-enhanced' );
	} );
	/*
	 * Section tabs. The nav links point at the section ids, so without
	 * JavaScript every section stays visible and the anchors still jump.
	 * With JavaScript only the open section shows, and the highlight
	 * follows it, including on load when the address carries a hash.
	 */
	function sectionIds( links ) {
		var ids = [];

		Array.prototype.forEach.call( links, function ( link ) {
			var href = link.getAttribute( 'href' ) || '';

			if ( '#' === href.charAt( 0 ) && href.length > 1 ) {
				ids.push( href.slice( 1 ) );
			}
		} );

		return ids;
	}

	function pickSection( hash, ids ) {
		var wanted = String( hash || '' ).replace( /^#/, '' );

		if ( ids.indexOf( wanted ) !== -1 ) {
			return wanted;
		}

		return ids.length ? ids[ 0 ] : '';
	}

	ready( function () {
		if ( 'function' !== typeof document.querySelector || 'function' !== typeof document.getElementById ) {
			return;
		}

		var nav = document.querySelector( '.rk-schema-nav-list' );

		if ( ! nav ) {
			return;
		}

		var links = nav.querySelectorAll( '.rk-schema-nav-item' );
		var ids = sectionIds( links );
		var sections = [];

		Array.prototype.forEach.call( ids, function ( id ) {
			var section = document.getElementById( id );

			if ( section ) {
				sections.push( section );
			}
		} );

		if ( ! links.length || ! sections.length ) {
			return;
		}

		function activate( id ) {
			Array.prototype.forEach.call( links, function ( link ) {
				var open = link.getAttribute( 'href' ) === '#' + id;

				link.classList.toggle( 'is-current', open );

				if ( open ) {
					link.setAttribute( 'aria-current', 'true' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );

			Array.prototype.forEach.call( sections, function ( section ) {
				section.hidden = section.id !== id;
			} );
		}

		function activateFromHash() {
			activate( pickSection( window.location.hash, ids ) );
		}

		Array.prototype.forEach.call( links, function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				var id = pickSection( link.getAttribute( 'href' ), ids );

				event.preventDefault();
				activate( id );

				if ( window.history && 'function' === typeof window.history.pushState ) {
					window.history.pushState( null, '', '#' + id );
				} else {
					window.location.hash = id;
				}
			} );
		} );

		window.addEventListener( 'hashchange', activateFromHash );

		var initial = pickSection( window.location.hash, ids );
		activate( initial );

		if ( initial !== ids[ 0 ] ) {
			var target = document.getElementById( initial );

			if ( target && 'function' === typeof target.scrollIntoView ) {
				target.scrollIntoView();
			}
		}
	} );

	if ( 'undefined' !== typeof window ) {
		if ( ! window.rankkernelSchemaSettings ) {
			window.rankkernelSchemaSettings = {};
		}

		window.rankkernelSchemaSettings.pickSection = pickSection;
		window.rankkernelSchemaSettings.sectionIds = sectionIds;
	}
} )();
