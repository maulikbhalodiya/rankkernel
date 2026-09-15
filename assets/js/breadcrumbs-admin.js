/**
 * RankKernel breadcrumbs separator chooser.
 *
 * Plain script, no build step. Progressive enhancement only: the Custom
 * separator input stays usable without JavaScript, this file only hides
 * it until the Custom radio is selected. Loaded on the RankKernel
 * settings screen only.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var radios = document.querySelectorAll( 'input[name="rk_breadcrumbs_separator_choice"]' );
	var wrap = document.getElementById( 'rk-breadcrumbs-separator-custom-wrap' );

	if ( ! radios.length || ! wrap ) {
		return;
	}

	function isCustomSelected() {
		for ( var i = 0; i < radios.length; i++ ) {
			if ( radios[i].checked && 'custom' === radios[i].value ) {
				return true;
			}
		}

		return false;
	}

	function sync() {
		if ( isCustomSelected() ) {
			wrap.removeAttribute( 'hidden' );
		} else {
			wrap.setAttribute( 'hidden', '' );
		}
	}

	radios.forEach( function ( radio ) {
		radio.addEventListener( 'change', sync );
	} );

	sync();
} );
