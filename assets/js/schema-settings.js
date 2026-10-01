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
} )();
