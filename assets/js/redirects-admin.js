/**
 * Redirect Manager confirmations.
 *
 * Small progressive enhancement only. Every destructive link works without
 * JavaScript, this file only asks for confirmation first.
 */
/**
 * Announcement helper for screen readers using wp.a11y.speak.
 */
function rkAnnounce( message ) {
	if ( ! window.wp || ! window.wp.a11y || typeof window.wp.a11y.speak !== 'function' ) {
		return;
	}

	var text = ( window.wp.i18n && typeof window.wp.i18n.__ === 'function' )
		? window.wp.i18n.__( message, 'rankkernel' )
		: message;

	window.wp.a11y.speak( text );
}

document.addEventListener( 'DOMContentLoaded', function () {
	var confirmLinks = document.querySelectorAll( '.rk-redirects .rk-confirm' );

	confirmLinks.forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			var message = link.getAttribute( 'data-rk-confirm' ) || 'Are you sure?';

			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	} );

	var bulkForm = document.getElementById( 'rk-bulk-form' );

	if ( bulkForm ) {
		bulkForm.addEventListener( 'submit', function ( event ) {
			var action = document.getElementById( 'rk-bulk-action' );

			if ( action && 'delete' === action.value ) {
				var message = bulkForm.getAttribute( 'data-rk-confirm' ) || 'Are you sure?';

				if ( ! window.confirm( message ) ) {
					event.preventDefault();
				}
			}
		} );
	}

	var selectAll = document.getElementById( 'rk-select-all' );

	if ( selectAll && bulkForm ) {
		selectAll.addEventListener( 'change', function () {
			var boxes = bulkForm.querySelectorAll( 'input[name="rule_ids[]"]' );

			boxes.forEach( function ( box ) {
				box.checked = selectAll.checked;
			} );

			rkAnnounce( selectAll.checked ? 'All redirects selected.' : 'All redirects deselected.' );
		} );
	}
} );

/**
 * Redirect editor progressive disclosure plus live validation.
 *
 * The editor renders in its correct server side state, this script only
 * makes the toggle instant, keeps visible controls relevant to the current
 * match type and code, and checks regex patterns without saving. The
 * pattern is only ever constructed, never executed, and capped at 200
 * characters before construction.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var editor = document.getElementById( 'rk-redirect-editor' );
	var toggle = document.getElementById( 'rk-add-toggle' );

	if ( toggle && editor ) {
		toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( editor.hasAttribute( 'hidden' ) ) {
				editor.removeAttribute( 'hidden' );
				toggle.setAttribute( 'aria-expanded', 'true' );
				rkAnnounce( 'Redirect editor opened.' );

				var first = document.getElementById( 'rk-source' );

				if ( first ) {
					first.focus();
				}
			} else {
				editor.setAttribute( 'hidden', '' );
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.focus();
				rkAnnounce( 'Redirect editor closed.' );
			}
		} );
	}

	if ( ! editor ) {
		return;
	}

	var matchSelect = document.getElementById( 'rk-match' );
	var matchHint = document.getElementById( 'rk-match-hint' );
	var regexRow = document.getElementById( 'rk-regex-row' );
	var feedback = document.getElementById( 'rk-regex-feedback' );
	var sourceInput = document.getElementById( 'rk-source' );
	var sourceHint = document.getElementById( 'rk-source-hint' );
	var codeSelect = document.getElementById( 'rk-code' );
	var codeHint = document.getElementById( 'rk-code-hint' );
	var targetRow = document.getElementById( 'rk-target-row' );
	var targetInput = document.getElementById( 'rk-target' );
	var terminalNote = document.getElementById( 'rk-terminal-note' );

	function selectedHint( select ) {
		var option = select.options[ select.selectedIndex ];

		return option ? option.getAttribute( 'data-hint' ) || '' : '';
	}

	function isTerminalCode() {
		return codeSelect && ( '410' === codeSelect.value || '451' === codeSelect.value );
	}

	function isRegexMode() {
		return matchSelect && 'regex' === matchSelect.value;
	}

	function checkRegex() {
		if ( ! feedback || ! sourceInput ) {
			return;
		}

		var pattern = sourceInput.value;
		var message = feedback.getAttribute( 'data-msg-empty' ) || '';
		var ok = false;

		if ( '' !== pattern && pattern.length > 200 ) {
			message = feedback.getAttribute( 'data-msg-long' ) || message;
		} else if ( '' !== pattern ) {
			try {
				void new RegExp( pattern );
				ok = true;
				message = feedback.getAttribute( 'data-msg-valid' ) || message;

				var trimmed = pattern.replace( /^\s+|\s+$/g, '' );

				if ( 0 !== trimmed.indexOf( '^' ) || trimmed.length - 1 !== trimmed.lastIndexOf( '$' ) ) {
					message += ' ' + ( feedback.getAttribute( 'data-msg-anchor' ) || '' );
				}
			} catch ( e ) {
				message = feedback.getAttribute( 'data-msg-invalid' ) || message;
			}
		}

		feedback.textContent = message.replace( /\s+$/g, '' );
		feedback.className = ok ? 'rk-regex-ok' : 'rk-regex-bad';
	}

	function warnFragment() {
		if ( ! sourceInput || ! sourceHint || ! sourceHint.getAttribute( 'data-fragment' ) ) {
			return;
		}

		var existing = document.getElementById( 'rk-source-live' );

		if ( ! isRegexMode() && -1 !== sourceInput.value.indexOf( '#' ) ) {
			if ( ! existing ) {
				existing = document.createElement( 'p' );
				existing.id = 'rk-source-live';
				existing.className = 'rk-regex-bad';
				existing.setAttribute( 'role', 'status' );
				sourceHint.parentNode.insertBefore( existing, sourceHint );
			}

			existing.textContent = sourceHint.getAttribute( 'data-fragment' );
		} else if ( existing ) {
			existing.remove();
		}
	}

	function syncMatch() {
		if ( matchHint && matchSelect ) {
			matchHint.textContent = selectedHint( matchSelect );
		}

		if ( regexRow ) {
			if ( isRegexMode() ) {
				regexRow.removeAttribute( 'hidden' );
			} else {
				regexRow.setAttribute( 'hidden', '' );
			}
		}

		checkRegex();
		warnFragment();
	}

	function syncCode() {
		if ( codeHint && codeSelect ) {
			codeHint.textContent = selectedHint( codeSelect );
		}

		var terminal = isTerminalCode();

		if ( targetRow ) {
			if ( terminal ) {
				targetRow.setAttribute( 'hidden', '' );
			} else {
				targetRow.removeAttribute( 'hidden' );
			}
		}

		if ( targetInput ) {
			targetInput.disabled = terminal;
		}

		if ( terminalNote ) {
			if ( terminal ) {
				terminalNote.removeAttribute( 'hidden' );
			} else {
				terminalNote.setAttribute( 'hidden', '' );
			}
		}
	}

	if ( matchSelect ) {
		matchSelect.addEventListener( 'change', syncMatch );
	}

	if ( codeSelect ) {
		codeSelect.addEventListener( 'change', syncCode );
	}

	if ( sourceInput ) {
		sourceInput.addEventListener( 'input', function () {
			if ( isRegexMode() ) {
				checkRegex();
			}

			warnFragment();
		} );
	}
} );

/**
 * Use recommended destination action.
 *
 * Copies the recommended final destination into the editor destination
 * field only. Never submits or saves, and the notice stays meaningful
 * when JavaScript is unavailable because the recommendation text itself
 * remains visible.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var buttons = document.querySelectorAll( '[data-rk-use-destination]' );

	buttons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var destination = button.getAttribute( 'data-rk-use-destination' ) || '';

			if ( '' === destination ) {
				return;
			}

			var target = document.getElementById( 'rk-target' );

			if ( ! target ) {
				return;
			}

			var editor = document.getElementById( 'rk-redirect-editor' );
			var toggle = document.getElementById( 'rk-add-toggle' );

			if ( editor && editor.hasAttribute( 'hidden' ) ) {
				editor.removeAttribute( 'hidden' );

				if ( toggle ) {
					toggle.setAttribute( 'aria-expanded', 'true' );
				}
			}

			target.value = destination;
			target.focus();
			rkAnnounce( 'Recommended destination applied.' );
		} );
	} );
} );
