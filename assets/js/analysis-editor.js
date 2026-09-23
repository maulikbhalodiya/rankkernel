/**
 * RankKernel content analysis panel (Classic Editor).
 *
 * Plain script, no build step, no dependencies. Reads the Classic fields
 * directly, scores the draft locally through window.RankKernelAnalysis and
 * renders the checklist. It issues no network request. The transport comes
 * from window.rankkernelMetaEditor, so when the analysis module is off there
 * is no path and this file does nothing at all.
 *
 * Every run is debounced, a newer input cancels a pending run, and identical
 * inputs are skipped by signature, so a slow run can never overwrite a newer
 * one. Pass and needs work rows carry a screen reader label, so the state is
 * never colour alone.
 */
( function () {
	'use strict';

	var cfg = window.rankkernelMetaEditor || {};
	var analysis = cfg.analysis || null;
	var engine = window.RankKernelAnalysis || {};
	var analyzer = engine.Analyzer || null;
	var bridge = engine.EditorBridge || null;

	// A disabled module registers no route, so there is nothing to run. The
	// panel is only enqueued beside the engine, so both are present together.
	if ( ! analysis || ! analysis.path || ! analyzer || ! bridge ) {
		return;
	}

	var DEBOUNCE_MS = 200;
	var FAIL_MESSAGE = 'The analysis could not be run. Try again.';
	var EMPTY_MESSAGE = 'Add a focus keyword to run the content analysis.';
	var LOADING_MESSAGE = 'Analysing the current draft…';

	var ANALYSIS_HONESTY = 'This score measures your content against a checklist. It does not predict rankings.';

	function translate( text ) {
		if ( window.wp && window.wp.i18n && 'function' === typeof window.wp.i18n.__ ) {
			return window.wp.i18n.__( text, 'rankkernel' );
		}

		return text;
	}

	function bandClass( band ) {
		if ( 'good' === band ) {
			return 'rk-badge-ok';
		}
		if ( 'improve' === band ) {
			return 'rk-badge-warn';
		}
		if ( 'problem' === band ) {
			return 'rk-badge-bad';
		}
		return 'rk-badge-none';
	}

	function bandLabel( band ) {
		if ( 'good' === band ) {
			return translate( 'Good' );
		}
		if ( 'improve' === band ) {
			return translate( 'Needs improvement' );
		}
		if ( 'problem' === band ) {
			return translate( 'Poor' );
		}
		return translate( 'Not analysed' );
	}

	function scoreSlot() {
		return document.querySelector( '[data-rk-analysis-score="1"]' );
	}

	function clearScore() {
		var slot = scoreSlot();

		if ( slot ) {
			slot.innerHTML = '';
		}
	}

	var results = document.querySelector( '[data-rk-analysis-results="1"]' );
	var keywordInput = document.getElementById( 'rankkernel-meta-focus-keywords' );

	if ( ! results ) {
		return;
	}

	var lastSignature = null;
	var generation = 0;
	var timer = null;
	var hasResult = false;

	function valueOf( id ) {
		var node = document.getElementById( id );
		return node && 'string' === typeof node.value ? node.value : '';
	}

	// The PHP stored score reads post_title only, so the panel scores that
	// field and never the SEO title override, which is not part of the score.
	function postTitle() {
		return valueOf( 'title' );
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
			// The same character list PHP trim() uses when it splits the
			// posted keyword field, so the browser and the stored list agree.
			var clean = parts[ i ].replace( /^[ \t\n\r\x00\x0B]+|[ \t\n\r\x00\x0B]+$/g, '' );

			if ( '' !== clean ) {
				out.push( clean );
			}
		}

		return out;
	}

	function setMessage( text, isError ) {
		clearScore();
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

		var list = document.createElement( 'ul' );
		list.className = 'rk-checklist-items';

		for ( i = 0; i < checks.length; i++ ) {
			var check = checks[ i ];
			var ok = 'pass' === check.status;

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
		var slot = scoreSlot();

		if ( slot ) {
			slot.innerHTML = '';

			var pill = document.createElement( 'span' );
			pill.className = 'rk-checklist-badge ' + bandClass( data.band );

			var scoreSpoken = document.createElement( 'span' );
			scoreSpoken.className = 'screen-reader-text';
			scoreSpoken.textContent = 'Score ' + score + ' out of 100, ' + bandLabel( data.band ) + '.';

			var value = document.createElement( 'span' );
			value.setAttribute( 'aria-hidden', 'true' );
			value.textContent = score + ' / 100';

			pill.appendChild( scoreSpoken );
			pill.appendChild( value );
			slot.appendChild( pill );
		}

		if ( checks.length > 0 ) {
			results.appendChild( list );
		}

		var note = document.createElement( 'p' );
		note.className = 'description rk-analysis-note';
		note.textContent = translate( ANALYSIS_HONESTY );
		results.appendChild( note );

		hasResult = true;
	}

	function run( token ) {
		// A newer schedule has already replaced this one.
		if ( token !== generation ) {
			return;
		}

		var fields = {
			title: postTitle(),
			description: valueOf( 'rankkernel-meta-description' ),
			slug: postSlug(),
			content: editorContent(),
			keywords: keywords()
		};
		var input = bridge.buildInput( fields, cfg );
		var nextSignature = bridge.signature( input );

		// Identical inputs never re-score, which is the cheap change check.
		if ( ! bridge.shouldRun( lastSignature, nextSignature ) ) {
			return;
		}

		if ( ! input.keywords.length ) {
			lastSignature = nextSignature;
			hasResult = false;
			setMessage( EMPTY_MESSAGE, false );
			return;
		}

		if ( ! hasResult ) {
			setMessage( LOADING_MESSAGE, false );
		}

		var result = null;

		try {
			result = analyzer.analyze( input, { translate: translate, stripAccents: true } );
		} catch ( e ) {
			// A throw is not a scored run, so the memo is cleared. Otherwise a
			// revert to the last good input would match the stale signature and
			// the failure message would stick even though the draft is scored.
			lastSignature = null;
			hasResult = false;
			setMessage( FAIL_MESSAGE, true );
			return;
		}

		// Only a run that produced a result is memoised, so an engine failure
		// can retry the identical input on the next event.
		lastSignature = nextSignature;

		render( result );
	}

	function schedule() {
		if ( timer ) {
			clearTimeout( timer );
		}

		// A run that a newer input replaced must not write its result.
		generation++;
		var token = generation;

		timer = setTimeout( function () {
			timer = null;
			run( token );
		}, DEBOUNCE_MS );
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

	// WordPress prints tinymce.js and creates the editor after the footer
	// scripts have run, so this file normally starts before window.tinymce
	// exists. The body editor is therefore bound on three paths: right away
	// when it already exists, from AddEditor when tinymce loads first and the
	// editor is created later, and from a bounded retry when this file runs
	// before tinymce itself. Every path only attaches a listener, and the
	// retry stops as soon as one content editor is bound.
	var BIND_RETRY_MS = 250;
	var BIND_RETRY_LIMIT = 40;
	var bindAttempts = 0;
	var bindTimer = null;
	var addEditorHooked = false;

	function stopBindRetry() {
		if ( null !== bindTimer ) {
			clearInterval( bindTimer );
			bindTimer = null;
		}
	}

	function bindEditor( editor ) {
		if ( ! editor ) {
			if ( ! window.tinymce || 'function' !== typeof window.tinymce.get ) {
				return false;
			}

			try {
				editor = window.tinymce.get( 'content' ) || window.tinymce.activeEditor;
			} catch ( e ) {
				return false;
			}
		}

		if ( ! editor || editor.rankernelAnalysisBound || 'function' !== typeof editor.on ) {
			return false;
		}

		editor.rankernelAnalysisBound = true;
		editor.on( 'input change keyup', schedule );
		stopBindRetry();

		return true;
	}

	function hookAddEditor() {
		if ( addEditorHooked || ! window.tinymce || 'function' !== typeof window.tinymce.on ) {
			return;
		}

		addEditorHooked = true;

		window.tinymce.on( 'AddEditor', function ( event ) {
			var added = event && event.editor;

			// A different wp_editor field is not the post body.
			if ( added && 'content' !== added.id ) {
				return;
			}

			bindEditor( added );
		} );
	}

	function attemptBind() {
		if ( bindEditor() ) {
			return;
		}

		hookAddEditor();

		bindAttempts++;

		if ( bindAttempts >= BIND_RETRY_LIMIT ) {
			stopBindRetry();
		}
	}

	// tinymce may already be on the page. The editor may not.
	hookAddEditor();

	if ( ! bindEditor() ) {
		bindTimer = setInterval( attemptBind, BIND_RETRY_MS );
	}

	schedule();
} )();
