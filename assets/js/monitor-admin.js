/**
 * 404 Monitor confirmations.
 *
 * Small progressive enhancement only. Every destructive link works without
 * JavaScript, this file only asks for confirmation first.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	// Announcement is best effort by contract: speak when wp.a11y is present, stay silent otherwise.
	function rkAnnounce( message ) {
		if ( ! window.wp || ! window.wp.a11y || typeof window.wp.a11y.speak !== 'function' ) {
			return;
		}

		var text = ( window.wp.i18n && typeof window.wp.i18n.__ === 'function' )
			? window.wp.i18n.__( message, 'rankkernel' )
			: message;

		window.wp.a11y.speak( text );
	}

	var confirmLinks = document.querySelectorAll( '.rk-monitor .rk-confirm' );

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

	var clearForm = document.getElementById( 'rk-clear-form' );

	if ( clearForm ) {
		clearForm.addEventListener( 'submit', function ( event ) {
			var message = clearForm.getAttribute( 'data-rk-confirm' ) || 'Are you sure?';

			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	}

	var selectAll = document.getElementById( 'rk-select-all' );

	if ( selectAll && bulkForm ) {
		selectAll.addEventListener( 'change', function () {
			var boxes = bulkForm.querySelectorAll( 'input[name="entry_ids[]"]' );

			boxes.forEach( function ( box ) {
				box.checked = selectAll.checked;
			} );
		} );
	}

	var exclusionBody = document.getElementById( 'rk-exclusions-body' );
	var exclusionAdd = document.getElementById( 'rk-exclusion-add' );
	var exclusionTemplate = document.getElementById( 'rk-exclusion-template' );

	if ( exclusionAdd && exclusionBody && exclusionTemplate ) {
		exclusionAdd.addEventListener( 'click', function () {
			var clone = exclusionTemplate.content.cloneNode( true );

			exclusionBody.appendChild( clone );

			var rows = exclusionBody.querySelectorAll( 'tr' );
			var lastInput = rows.length ? rows[ rows.length - 1 ].querySelector( 'input' ) : null;

			if ( lastInput ) {
				lastInput.focus();
			}

			rkAnnounce( 'Exclusion row added.' );
		} );

		exclusionBody.addEventListener( 'click', function ( event ) {
			var target = event.target;

			if ( ! target || ! target.closest ) {
				return;
			}

			var button = target.closest( '.rk-exclusion-remove' );

			if ( ! button ) {
				return;
			}

			var row = button.closest( 'tr' );

			if ( ! row ) {
				return;
			}

			if ( exclusionBody.querySelectorAll( 'tr' ).length > 1 ) {
				// Removing the focused row drops focus to the page body, so move it to the next
				// surviving row when there is one, and to the previous row when the removed row was last.
				var focusRow = row.nextElementSibling || row.previousElementSibling;

				row.remove();

				var focusInput = focusRow ? focusRow.querySelector( 'input' ) : null;

				if ( focusInput ) {
					focusInput.focus();
				}

				rkAnnounce( 'Exclusion row removed.' );
			} else {
				var input = row.querySelector( 'input' );

				if ( input ) {
					input.value = '';
					input.focus();
				}

				rkAnnounce( 'Exclusion row cleared.' );
			}
		} );
	}
} );
