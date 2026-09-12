/**
 * Redirect Manager confirmations.
 *
 * Small progressive enhancement only. Every destructive link works without
 * JavaScript, this file only asks for confirmation first.
 */
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
		} );
	}
} );
