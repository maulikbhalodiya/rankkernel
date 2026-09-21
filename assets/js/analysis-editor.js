/**
 * RankKernel content analysis panel (Classic Editor).
 *
 * Plain script, no build step, no dependencies. Reads the Classic fields
 * directly, posts the current draft to the analysis route and renders the
 * returned checklist. The transport comes from window.rankkernelMetaEditor,
 * so when the analysis module is off there is no path and this file does
 * nothing at all.
 *
 * Every request is debounced and the newest reply wins: a slower earlier
 * response can never overwrite a newer one. Pass and needs work rows carry
 * a screen reader label, so the state is never colour alone.
 */
( function () {
	'use strict';

	var cfg = window.rankkernelMetaEditor || {};
	var analysis = cfg.analysis || null;

	// A disabled module registers no route, so there is nothing to run.
	if ( ! analysis || ! analysis.path ) {
		return;
	}

	var DEBOUNCE_MS = 700;
	var FAIL_MESSAGE = 'The analysis could not be run. Try again.';
	var PERMISSION_MESSAGE = 'Save the post once, then run the analysis.';
	var EMPTY_MESSAGE = 'Add a focus keyword to run the content analysis.';
	var LOADING_MESSAGE = 'Analysing the current draft…';

	var results = document.querySelector( '[data-rk-analysis-results="1"]' );
	var keywordInput = document.getElementById( 'rankkernel-meta-focus-keywords' );

	if ( ! results ) {
		return;
	}

	var latest = 0;
	var timer = null;
	var controller = null;
	var hasResult = false;

	function valueOf( id ) {
		var node = document.getElementById( id );
		return node && 'string' === typeof node.value ? node.value : '';
	}

	// The analysis scores the post title, matching the block editor sidebar.
	// The SEO title is only a fallback when the post title field is absent.
	function postTitle() {
		var title = valueOf( 'title' );
		return '' !== title ? title : valueOf( 'rankkernel-meta-title' );
	}

	function postSlug() {
		var slug = valueOf( 'post_name' );

		if ( '' === slug ) {
			slug = valueOf( 'new-post-slug' );
		}

		if ( '' === slug ) {
			slug = valueOf( 'editable-post-name' );
		}

		return slug;
	}

	function editorContent() {
		try {
			if ( window.tinymce ) {
				var editor = window.tinymce.get( 'content' ) || window.tinymce.activeEditor;

				if ( editor && ! editor.isHidden() && 'function' === typeof editor.getContent ) {
					return editor.getContent() || '';
				}
			}
		} catch ( e ) {
			return valueOf( 'content' );
		}

		return valueOf( 'content' );
	}

	function keywords() {
		if ( ! keywordInput ) {
			return [];
		}

		var parts = String( keywordInput.value || '' ).split( ',' );
		var out = [];
		var i = 0;

		for ( i = 0; i < parts.length; i++ ) {
			var clean = parts[ i ].replace( /^\s+|\s+$/g, '' );

			if ( '' !== clean ) {
				out.push( clean );
			}
		}

		return out;
	}

	function setMessage( text, isError ) {
		results.innerHTML = '';

		var node = document.createElement( 'p' );
		node.className = isError ? 'description rk-analysis-error' : 'description';
		node.setAttribute( 'role', 'status' );
		node.textContent = text;
		results.appendChild( node );
	}

	function render( data ) {
		results.innerHTML = '';

		var raw = ( data && data.checks ) || [];
		var checks = [];
		var i = 0;

		for ( i = 0; i < raw.length; i++ ) {
			if ( raw[ i ] && 'na' !== raw[ i ].status ) {
				checks.push( raw[ i ] );
			}
		}

		var failCount = 0;
		var list = document.createElement( 'ul' );
		list.className = 'rk-checklist-items';

		for ( i = 0; i < checks.length; i++ ) {
			var check = checks[ i ];
			var ok = 'pass' === check.status;

			if ( ! ok ) {
				failCount++;
			}

			var item = document.createElement( 'li' );
			item.className = 'rk-checklist-item ' + ( ok ? 'is-ok' : 'is-fail' );

			var icon = document.createElement( 'span' );
			icon.className = 'dashicons ' + ( ok ? 'dashicons-yes-alt' : 'dashicons-dismiss' );
			icon.setAttribute( 'aria-hidden', 'true' );

			var spoken = document.createElement( 'span' );
			spoken.className = 'screen-reader-text';
			spoken.textContent = ok ? 'Pass: ' : 'Needs work: ';

			var label = document.createElement( 'span' );
			label.className = 'rk-checklist-label';
			label.textContent = check.message || '';

			item.appendChild( icon );
			item.appendChild( spoken );
			item.appendChild( label );
			list.appendChild( item );
		}

		var score = 'number' === typeof data.score ? data.score : 0;
		var badge = document.createElement( 'p' );
		badge.className = 'rk-analysis-score';

		var pill = document.createElement( 'span' );
		pill.className = 'rk-checklist-badge ' + ( 0 === failCount ? 'rk-badge-ok' : 'rk-badge-warn' );
		pill.textContent = score + ' / 100';

		badge.appendChild( pill );
		results.appendChild( badge );

		if ( checks.length > 0 ) {
			results.appendChild( list );
		}

		hasResult = true;
	}

	function request() {
		var list = keywords();

		if ( ! list.length ) {
			hasResult = false;
			setMessage( EMPTY_MESSAGE, false );
			return;
		}

		latest++;
		var id = latest;

		if ( controller ) {
			controller.abort();
		}

		controller = 'function' === typeof window.AbortController ? new window.AbortController() : null;

		if ( ! hasResult ) {
			setMessage( LOADING_MESSAGE, false );
		}

		window.fetch( analysis.path, {
			method: 'POST',
			credentials: 'same-origin',
			signal: controller ? controller.signal : undefined,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': analysis.nonce
			},
			body: JSON.stringify( {
				post_id: cfg.postId,
				title: postTitle(),
				description: valueOf( 'rankkernel-meta-description' ),
				slug: postSlug(),
				content: editorContent(),
				keywords: list
			} )
		} ).then( function ( response ) {
			var status = response.status;

			return response.json().then(
				function ( data ) {
					return { ok: response.ok, status: status, data: data };
				},
				function () {
					return { ok: false, status: status, data: null };
				}
			);
		} ).then( function ( reply ) {
			if ( id !== latest ) {
				return;
			}

			if ( ! reply.ok || ! reply.data ) {
				hasResult = false;
				setMessage( 403 === reply.status ? PERMISSION_MESSAGE : FAIL_MESSAGE, true );
				return;
			}

			render( reply.data );
		} ).catch( function () {
			if ( id !== latest ) {
				return;
			}

			hasResult = false;
			setMessage( FAIL_MESSAGE, true );
		} );
	}

	function schedule() {
		if ( timer ) {
			clearTimeout( timer );
		}

		timer = setTimeout( request, DEBOUNCE_MS );
	}

	var watchedIds = [
		'title',
		'post_name',
		'rankkernel-meta-title',
		'rankkernel-meta-description'
	];
	var i = 0;

	if ( keywordInput ) {
		keywordInput.addEventListener( 'input', schedule );
	}

	for ( i = 0; i < watchedIds.length; i++ ) {
		var watched = document.getElementById( watchedIds[ i ] );

		if ( watched ) {
			watched.addEventListener( 'input', schedule );
		}
	}

	var contentField = document.getElementById( 'content' );

	if ( contentField ) {
		contentField.addEventListener( 'input', schedule );
	}

	if ( window.tinymce ) {
		var bindEditor = function () {
			var editor = window.tinymce.get( 'content' ) || window.tinymce.activeEditor;

			if ( editor && ! editor.rankkernelAnalysisBound ) {
				editor.rankkernelAnalysisBound = true;
				editor.on( 'input change keyup', schedule );
			}
		};

		bindEditor();
		window.tinymce.on( 'AddEditor', bindEditor );
	}

	schedule();
} )();
