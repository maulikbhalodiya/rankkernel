/**
 * Adds a confirmation step to destructive actions marked with data-rk-confirm.
 *
 * Plain script, no build step. Loaded on the RankKernel General Settings
 * screen only.
 */
( function () {
	var nodes = document.querySelectorAll( '[data-rk-confirm]' );

	for ( var i = 0; i < nodes.length; i++ ) {
		nodes[ i ].addEventListener( 'click', function ( event ) {
			var message = this.getAttribute( 'data-rk-confirm' );

			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	}
} )();
