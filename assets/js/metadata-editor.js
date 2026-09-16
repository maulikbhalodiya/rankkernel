/**
 * RankKernel metadata editor (Classic Editor metabox behaviour).
 *
 * Plain script, no build step. No network requests, no REST calls from this
 * path. All preview work is local and debounced. Token values are never
 * invented here: templates arrive resolved from the server and the token
 * picker only lists keys present in tokenLabels.
 */
( function () {
	'use strict';

	var cfg = window.rankkernelMetaEditor || {};
	var templates = cfg.templates || {};
	var tokenLabels = cfg.tokenLabels || {};
	var limits = cfg.limits || {};
	var defaults = cfg.defaults || {};
	var strings = cfg.strings || {};
	var TITLE_LIMIT = parseInt( limits.title, 10 ) || 60;
	var DESC_LIMIT = parseInt( limits.description, 10 ) || 160;
	var MEASURE_FONT = '16px Arial, sans-serif';
	var DEBOUNCE_MS = 150;

	var FIELD_NAMES = {
		title: 'rankkernel_meta[title]',
		description: 'rankkernel_meta[description]',
		canonical: 'rankkernel_meta[canonical]',
		robotsIndex: 'rankkernel_meta[robots][index]',
		robotsNofollow: 'rankkernel_meta[robots][nofollow]',
		robotsNoarchive: 'rankkernel_meta[robots][noarchive]',
		robotsNosnippet: 'rankkernel_meta[robots][nosnippet]',
		robotsNoimageindex: 'rankkernel_meta[robots][noimageindex]',
		robotsMaxSnippet: 'rankkernel_meta[robots][max_snippet]',
		robotsMaxImage: 'rankkernel_meta[robots][max_image_preview]',
		robotsMaxVideo: 'rankkernel_meta[robots][max_video_preview]',
		ogTitle: 'rankkernel_meta[og][title]',
		ogDescription: 'rankkernel_meta[og][description]',
		ogImage: 'rankkernel_meta[og][image]',
		ogImageId: 'rankkernel_meta[og][image_id]',
		twCard: 'rankkernel_meta[twitter][card]',
		twTitle: 'rankkernel_meta[twitter][title]',
		twDescription: 'rankkernel_meta[twitter][description]',
		twImage: 'rankkernel_meta[twitter][image]',
		twImageId: 'rankkernel_meta[twitter][image_id]'
	};

	var TOKEN_TARGETS = [ 'title', 'description', 'ogTitle', 'ogDescription', 'twTitle', 'twDescription' ];
	var INHERITED_FIELDS = {
		title: 'title',
		description: 'description',
		ogTitle: 'title',
		ogDescription: 'description',
		twTitle: 'title',
		twDescription: 'description'
	};

	function $( root, selector ) {
		return root.querySelector( selector );
	}

	function $all( root, selector ) {
		return Array.prototype.slice.call( root.querySelectorAll( selector ) );
	}

	function str( key, fallback ) {
		return 'string' === typeof strings[ key ] && '' !== strings[ key ] ? strings[ key ] : fallback;
	}

	function debounce( fn, wait ) {
		var timer = null;
		return function () {
			var args = arguments;
			var self = this;
			if ( timer ) {
				clearTimeout( timer );
			}
			timer = setTimeout( function () {
				timer = null;
				fn.apply( self, args );
			}, wait );
		};
	}

	function esc( value ) {
		return String( value == null ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * Field lookup by name attribute, with id fallback. The PHP view names
	 * every input rankkernel_meta[...] so name binding keeps this working
	 * even when ids change. Pass an id when the PHP view provides a stable
	 * one and it will be preferred.
	 */
	function field( root, name, id ) {
		var byId = id ? document.getElementById( id ) : null;
		if ( byId && root.contains( byId ) ) {
			return byId;
		}
		if ( byId && ! root.contains( byId ) && ! root.querySelector ) {
			return byId;
		}
		return $( root, '[name="' + name + '"]' );
	}

	function fieldValue( root, name, id ) {
		var input = field( root, name, id );
		if ( ! input ) {
			return '';
		}
		if ( 'checkbox' === input.type ) {
			return input.checked ? input.value || '1' : '';
		}
		return input.value || '';
	}

	function effectiveValue( raw, templateKey ) {
		var value = String( raw == null ? '' : raw ).trim();
		if ( '' !== value ) {
			return { text: String( raw ), inherited: false };
		}
		return { text: String( templates[ templateKey ] || '' ), inherited: true };
	}

	var measureCanvas = null;

	function measureWidth( text ) {
		try {
			if ( ! measureCanvas ) {
				measureCanvas = document.createElement( 'canvas' );
			}
			var ctx = measureCanvas.getContext( '2d' );
			if ( ! ctx ) {
				return -1;
			}
			ctx.font = MEASURE_FONT;
			return Math.round( ctx.measureText( String( text || '' ) ).width );
		} catch ( e ) {
			return -1;
		}
	}

	function counterStatus( length, limit ) {
		if ( length <= limit ) {
			return 'ok';
		}
		if ( length <= limit + 20 ) {
			return 'warn';
		}
		return 'over';
	}

	function statusWord( status ) {
		if ( 'warn' === status ) {
			return str( 'longLabel', 'Long' );
		}
		if ( 'over' === status ) {
			return str( 'tooLongLabel', 'Too long' );
		}
		return str( 'okLabel', 'OK' );
	}

	function shortUrl( permalink ) {
		var url = String( permalink || '' );
		url = url.replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
		return url;
	}

	function insertAtCaret( input, text ) {
		if ( ! input || 'string' !== typeof text || '' === text ) {
			return;
		}
		if ( input.disabled || input.readOnly ) {
			return;
		}
		var start = typeof input.selectionStart === 'number' ? input.selectionStart : input.value.length;
		var end = typeof input.selectionEnd === 'number' ? input.selectionEnd : input.value.length;
		var before = input.value.slice( 0, start );
		var after = input.value.slice( end );
		input.value = before + text + after;
		var caret = start + text.length;
		try {
			input.focus();
			input.setSelectionRange( caret, caret );
		} catch ( e ) {
			input.focus();
		}
		var event;
		try {
			event = new Event( 'input', { bubbles: true } );
		} catch ( e ) {
			event = document.createEvent( 'Event' );
			event.initEvent( 'input', true, true );
		}
		input.dispatchEvent( event );
	}

	function initTabs( root ) {
		var tablist = $( root, '[role="tablist"]' );
		if ( ! tablist ) {
			return;
		}
		var tabs = $all( tablist, '[role="tab"]' );
		if ( ! tabs.length ) {
			return;
		}

		function panels() {
			return $all( root, '[role="tabpanel"]' );
		}

		function activate( tab, focus ) {
			tabs.forEach( function ( other ) {
				var selected = other === tab;
				other.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
				other.tabIndex = selected ? 0 : -1;
			} );
			panels().forEach( function ( panel ) {
				var match = tab.getAttribute( 'aria-controls' ) === panel.id ||
					tab.getAttribute( 'data-rk-tab' ) === panel.getAttribute( 'data-rk-panel' );
				if ( match ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', '' );
				}
			} );
			if ( focus ) {
				tab.focus();
			}
		}

		function focusAt( index ) {
			var next = tabs[ ( index + tabs.length ) % tabs.length ];
			activate( next, true );
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				activate( tab, false );
			} );
			tab.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
					event.preventDefault();
					focusAt( index + 1 );
				} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
					event.preventDefault();
					focusAt( index - 1 );
				} else if ( 'Home' === event.key ) {
					event.preventDefault();
					focusAt( 0 );
				} else if ( 'End' === event.key ) {
					event.preventDefault();
					focusAt( tabs.length - 1 );
				} else if ( 'Enter' === event.key || ' ' === event.key ) {
					event.preventDefault();
					activate( tab, false );
				}
			} );
		} );

		var selected = tabs.filter( function ( tab ) {
			return 'true' === tab.getAttribute( 'aria-selected' );
		} )[ 0 ] || tabs[ 0 ];
		activate( selected, false );
	}

	function buildTokenPicker( root, getTarget ) {
		var lists = $all( root, '[data-rk-token-list]' );
		if ( ! lists.length ) {
			return;
		}
		var tokens = Object.keys( tokenLabels );
		lists.forEach( function ( list ) {
			list.innerHTML = '';
			if ( ! tokens.length ) {
				var empty = document.createElement( 'p' );
				empty.className = 'description';
				empty.textContent = str( 'noTokens', 'No tokens available for this post type.' );
				list.appendChild( empty );
				return;
			}
			tokens.forEach( function ( token ) {
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'button button-small';
				button.setAttribute( 'data-rk-token', token );
				button.textContent = token;
				button.title = tokenLabels[ token ] || token;
				button.setAttribute( 'aria-label', str( 'insertToken', 'Insert token' ) + ' ' + token + ' (' + ( tokenLabels[ token ] || token ) + ')' );
				button.addEventListener( 'click', function () {
					var target = getTarget();
					if ( target ) {
						insertAtCaret( target, token );
					}
				} );
				list.appendChild( button );
			} );
		} );
	}

	function updateCounter( root, key, text, limit ) {
		var node = $( root, '[data-rk-count-for="' + key + '"]' );
		if ( ! node ) {
			return;
		}
		var chars = String( text || '' ).length;
		var px = measureWidth( text );
		var status = counterStatus( chars, limit );
		var pxLabel = px >= 0 ? String( px ) + 'px' : str( 'charsOnly', 'chars only' );
		node.textContent = chars + ' / ' + limit + ' ' + str( 'charsLabel', 'chars' ) + ', ' + pxLabel + ', ' + statusWord( status );
		node.setAttribute( 'data-rk-state', status );
		node.classList.remove( 'rk-is-ok', 'rk-is-warn', 'rk-is-over' );
		node.classList.add( 'ok' === status ? 'rk-is-ok' : ( 'warn' === status ? 'rk-is-warn' : 'rk-is-over' ) );
	}

	function updateInheritedBadge( root, key, inherited ) {
		var badge = $( root, '[data-rk-state-for="' + key + '"]' );
		if ( ! badge ) {
			return;
		}
		badge.textContent = inherited ? str( 'inheritedLabel', 'Inherited' ) : str( 'customLabel', 'Custom' );
		badge.setAttribute( 'data-rk-state', inherited ? 'inherited' : 'custom' );
		badge.classList.toggle( 'rk-is-inherited', inherited );
		badge.classList.toggle( 'rk-is-custom', ! inherited );
	}

	function updateSerp( root, values ) {
		var box = $( root, '[data-rk-serp]' );
		if ( ! box ) {
			return;
		}
		var titleNode = $( box, '[data-rk-serp-title]' );
		var urlNode = $( box, '[data-rk-serp-url]' );
		var descNode = $( box, '[data-rk-serp-desc]' );
		var siteNode = $( box, '[data-rk-serp-site]' );
		var title = effectiveValue( values.title, 'title' );
		var desc = effectiveValue( values.description, 'description' );
		if ( titleNode ) {
			titleNode.textContent = title.text || str( 'untitledLabel', 'Untitled' );
		}
		if ( urlNode ) {
			urlNode.textContent = shortUrl( cfg.permalink || cfg.homeUrl || '' );
		}
		if ( descNode ) {
			descNode.textContent = desc.text || '';
		}
		if ( siteNode ) {
			siteNode.textContent = cfg.siteName || cfg.siteUrl || '';
		}
	}

	function updateSocial( root, values ) {
		var box = $( root, '[data-rk-social]' );
		if ( ! box ) {
			return;
		}
		var title = String( values.ogTitle ).trim() !== '' ? String( values.ogTitle ) : effectiveValue( values.title, 'title' ).text;
		var desc = String( values.ogDescription ).trim() !== '' ? String( values.ogDescription ) : effectiveValue( values.description, 'description' ).text;
		var image = String( values.ogImage ).trim() !== '' ? String( values.ogImage ).trim() : String( defaults.ogImage || '' );
		var titleNode = $( box, '[data-rk-social-title]' );
		var descNode = $( box, '[data-rk-social-desc]' );
		var siteNode = $( box, '[data-rk-social-site]' );
		var imgNode = $( box, '[data-rk-social-image]' );
		var card = String( values.twCard || 'summary_large_image' );
		if ( titleNode ) {
			titleNode.textContent = title || str( 'untitledLabel', 'Untitled' );
		}
		if ( descNode ) {
			descNode.textContent = desc || '';
		}
		if ( siteNode ) {
			siteNode.textContent = cfg.siteName || cfg.siteUrl || '';
		}
		if ( imgNode ) {
			if ( image ) {
				imgNode.setAttribute( 'src', image );
				imgNode.removeAttribute( 'hidden' );
			} else {
				imgNode.setAttribute( 'src', '' );
				imgNode.setAttribute( 'hidden', '' );
			}
			imgNode.setAttribute( 'alt', '' );
		}
		box.setAttribute( 'data-rk-card', 'summary' === card ? 'summary' : 'summary_large_image' );
	}

	function readValues( root ) {
		return {
			title: fieldValue( root, FIELD_NAMES.title, 'rankkernel-meta-title' ),
			description: fieldValue( root, FIELD_NAMES.description, 'rankkernel-meta-description' ),
			ogTitle: fieldValue( root, FIELD_NAMES.ogTitle, 'rankkernel-meta-og-title' ),
			ogDescription: fieldValue( root, FIELD_NAMES.ogDescription, 'rankkernel-meta-og-description' ),
			ogImage: fieldValue( root, FIELD_NAMES.ogImage, 'rankkernel-meta-og-image' ),
			twTitle: fieldValue( root, FIELD_NAMES.twTitle, 'rankkernel-meta-twitter-title' ),
			twDescription: fieldValue( root, FIELD_NAMES.twDescription, 'rankkernel-meta-twitter-description' ),
			twCard: fieldValue( root, FIELD_NAMES.twCard, 'rankkernel-meta-twitter-card' )
		};
	}

	function refresh( root ) {
		var values = readValues( root );
		var title = effectiveValue( values.title, 'title' );
		var desc = effectiveValue( values.description, 'description' );
		updateCounter( root, 'title', title.text, TITLE_LIMIT );
		updateCounter( root, 'description', desc.text, DESC_LIMIT );
		updateInheritedBadge( root, 'title', title.inherited );
		updateInheritedBadge( root, 'description', desc.inherited );
		Object.keys( INHERITED_FIELDS ).forEach( function ( key ) {
			var map = {
				title: 'title',
				description: 'description',
				ogTitle: 'ogTitle',
				ogDescription: 'ogDescription',
				twTitle: 'twTitle',
				twDescription: 'twDescription'
			};
			var raw = values[ map[ key ] ];
			updateInheritedBadge( root, key, String( raw == null ? '' : raw ).trim() === '' );
		} );
		updateSerp( root, values );
		updateSocial( root, values );
	}

	function initResets( root, schedule ) {
		$all( root, '[data-rk-reset]' ).forEach( function ( button ) {
			var key = button.getAttribute( 'data-rk-reset' );
			var map = {
				title: [ FIELD_NAMES.title, 'rankkernel-meta-title' ],
				description: [ FIELD_NAMES.description, 'rankkernel-meta-description' ],
				ogTitle: [ FIELD_NAMES.ogTitle, 'rankkernel-meta-og-title' ],
				ogDescription: [ FIELD_NAMES.ogDescription, 'rankkernel-meta-og-description' ],
				twTitle: [ FIELD_NAMES.twTitle, 'rankkernel-meta-twitter-title' ],
				twDescription: [ FIELD_NAMES.twDescription, 'rankkernel-meta-twitter-description' ],
				ogImage: [ FIELD_NAMES.ogImage, 'rankkernel-meta-og-image' ],
				twImage: [ FIELD_NAMES.twImage, 'rankkernel-meta-twitter-image' ]
			};
			button.addEventListener( 'click', function () {
				var target = map[ key ];
				if ( ! target ) {
					return;
				}
				var input = field( root, target[ 0 ], target[ 1 ] );
				if ( ! input ) {
					return;
				}
				input.value = '';
				var idField = null;
				if ( 'ogImage' === key ) {
					idField = field( root, FIELD_NAMES.ogImageId, 'rankkernel-meta-og-image-id' );
				} else if ( 'twImage' === key ) {
					idField = field( root, FIELD_NAMES.twImageId, 'rankkernel-meta-twitter-image-id' );
				}
				if ( idField ) {
					idField.value = '';
				}
				schedule();
				input.focus();
			} );
		} );
	}

	function initDeviceToggle( root ) {
		var box = $( root, '[data-rk-serp]' );
		if ( ! box ) {
			return;
		}
		$all( root, '[data-rk-device]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var device = button.getAttribute( 'data-rk-device' );
				box.setAttribute( 'data-rk-device-view', 'mobile' === device ? 'mobile' : 'desktop' );
				box.classList.toggle( 'rk-is-mobile', 'mobile' === device );
				$all( root, '[data-rk-device]' ).forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
				} );
			} );
		} );
	}

	function openMedia( root, kind, schedule ) {
		var isOg = 'og' !== kind ? false : true;
		var urlField = field( root, isOg ? FIELD_NAMES.ogImage : FIELD_NAMES.twImage, isOg ? 'rankkernel-meta-og-image' : 'rankkernel-meta-twitter-image' );
		var idField = field( root, isOg ? FIELD_NAMES.ogImageId : FIELD_NAMES.twImageId, isOg ? 'rankkernel-meta-og-image-id' : 'rankkernel-meta-twitter-image-id' );
		if ( ! urlField ) {
			return;
		}
		if ( ! window.wp || ! window.wp.media ) {
			urlField.focus();
			return;
		}
		var frame = window.wp.media( {
			title: str( 'mediaTitle', 'Select preview image' ),
			button: { text: str( 'mediaButton', 'Use this image' ) },
			multiple: false
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first();
			if ( ! attachment ) {
				return;
			}
			var json = attachment.toJSON();
			var src = ( json.sizes && json.sizes.full && json.sizes.full.url ) || json.url || '';
			urlField.value = src;
			if ( idField ) {
				idField.value = json.id || '';
			}
			schedule();
		} );
		frame.open();
	}

	function initMedia( root, schedule ) {
		$all( root, '[data-rk-media-pick]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				openMedia( root, button.getAttribute( 'data-rk-media-pick' ), schedule );
			} );
		} );
		$all( root, '[data-rk-media-remove]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var kind = button.getAttribute( 'data-rk-media-remove' );
				var isOg = 'og' === kind;
				var urlField = field( root, isOg ? FIELD_NAMES.ogImage : FIELD_NAMES.twImage, isOg ? 'rankkernel-meta-og-image' : 'rankkernel-meta-twitter-image' );
				var idField = field( root, isOg ? FIELD_NAMES.ogImageId : FIELD_NAMES.twImageId, isOg ? 'rankkernel-meta-og-image-id' : 'rankkernel-meta-twitter-image-id' );
				if ( urlField ) {
					urlField.value = '';
				}
				if ( idField ) {
					idField.value = '';
				}
				schedule();
			} );
		} );
	}

	function initRoot( root ) {
		var schedule = debounce( function () {
			refresh( root );
		}, DEBOUNCE_MS );

		initTabs( root );

		var lastTarget = null;
		function currentTarget() {
			if ( lastTarget && root.contains( lastTarget ) ) {
				return lastTarget;
			}
			return field( root, FIELD_NAMES.title, 'rankkernel-meta-title' ) ||
				field( root, FIELD_NAMES.description, 'rankkernel-meta-description' );
		}

		TOKEN_TARGETS.forEach( function ( key ) {
			var map = {
				title: [ FIELD_NAMES.title, 'rankkernel-meta-title' ],
				description: [ FIELD_NAMES.description, 'rankkernel-meta-description' ],
				ogTitle: [ FIELD_NAMES.ogTitle, 'rankkernel-meta-og-title' ],
				ogDescription: [ FIELD_NAMES.ogDescription, 'rankkernel-meta-og-description' ],
				twTitle: [ FIELD_NAMES.twTitle, 'rankkernel-meta-twitter-title' ],
				twDescription: [ FIELD_NAMES.twDescription, 'rankkernel-meta-twitter-description' ]
			};
			var input = field( root, map[ key ][ 0 ], map[ key ][ 1 ] );
			if ( input ) {
				input.addEventListener( 'focus', function () {
					lastTarget = input;
				} );
			}
		} );

		buildTokenPicker( root, currentTarget );
		initResets( root, schedule );
		initDeviceToggle( root );
		initMedia( root, schedule );

		root.addEventListener( 'input', function ( event ) {
			var target = event.target;
			if ( target && 'INPUT' !== target.tagName && 'TEXTAREA' !== target.tagName && 'SELECT' !== target.tagName ) {
				return;
			}
			schedule();
		} );
		root.addEventListener( 'change', schedule );

		refresh( root );
	}

	// Shared preview helpers for the Gutenberg sidebar. The sidebar calls
	// these when present and falls back to its own copies otherwise.
	window.rankkernelMetaEditorPreview = {
		effectiveValue: effectiveValue,
		measureWidth: measureWidth,
		counterStatus: counterStatus,
		statusWord: statusWord,
		shortUrl: shortUrl,
		escape: esc,
		fieldNames: FIELD_NAMES,
		debounce: debounce
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		$all( document, '[data-rk-meta-editor]' ).forEach( initRoot );
	} );
} )();
