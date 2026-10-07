/**
 * Live validation for the RankKernel support form.
 *
 * The limits come from the server via wp_localize_script, so the browser
 * cannot drift from what SupportRequest accepts. Server side validation is
 * still authoritative; this only saves the user a round trip.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

( function () {
	'use strict';

	var config = window.rankkernelSupport || {};
	var limits = config.limits || {};
	var i18n = config.i18n || {};
	var categories = config.categories || [];

	/**
	 * Replaces %d in a translated string with a number.
	 *
	 * @param {string} text Template with a single %d.
	 * @param {number} value Replacement number.
	 * @return {string} Filled string.
	 */
	function fill( text, value ) {
		return String( text || '' ).replace( '%d', String( value ) );
	}

	/**
	 * Creates or updates the live error node for a field.
	 *
	 * @param {HTMLElement} field Field element.
	 * @param {string}      message Error text, empty clears it.
	 * @return {void}
	 */
	function setError( field, message ) {
		var key = field.dataset.rkSupportField;
		var host = field.closest( '.rk-ui-form-row' ) || field.parentNode;
		var id = 'rk-support-' + key + '-error';
		var node = host ? host.querySelector( '#' + id ) : null;

		if ( ! message ) {
			field.setAttribute( 'aria-invalid', 'false' );

			if ( node ) {
				node.remove();
			}

			removeErrorDescribedBy( field, id );

			return;
		}

		field.setAttribute( 'aria-invalid', 'true' );

		if ( ! node ) {
			node = document.createElement( 'p' );
			node.className = 'rk-support-error';
			node.id = id;

			if ( host ) {
				host.appendChild( node );
			}
		}

		node.textContent = message;

		addErrorDescribedBy( field, id );
	}

	/**
	 * Adds an error id to the field's describedby list, keeping any hint.
	 *
	 * @param {HTMLElement} field Field element.
	 * @param {string}      id Error element id.
	 * @return {void}
	 */
	function addErrorDescribedBy( field, id ) {
		var current = ( field.getAttribute( 'aria-describedby' ) || '' ).split( /\s+/ ).filter( Boolean );

		if ( current.indexOf( id ) === -1 ) {
			current.push( id );
		}

		field.setAttribute( 'aria-describedby', current.join( ' ' ) );
	}

	/**
	 * Removes an error id from the field's describedby list.
	 *
	 * @param {HTMLElement} field Field element.
	 * @param {string}      id Error element id.
	 * @return {void}
	 */
	function removeErrorDescribedBy( field, id ) {
		var current = ( field.getAttribute( 'aria-describedby' ) || '' ).split( /\s+/ ).filter( function ( part ) {
			return part && part !== id;
		} );

		if ( current.length ) {
			field.setAttribute( 'aria-describedby', current.join( ' ' ) );
		} else {
			field.removeAttribute( 'aria-describedby' );
		}
	}

	/**
	 * Pure validation for one field.
	 *
	 * Takes primitives rather than elements so the rules can be tested without
	 * a DOM, and so the browser and the node harness run identical code.
	 *
	 * @param {string} key   Field key.
	 * @param {*}      value Field value. A string, a boolean for consent, or
	 *                         { size, type } for the screenshot.
	 * @return {string} Error message, or an empty string when acceptable.
	 */
	function validate( key, value ) {
		if ( 'subject' === key ) {
			var subject = String( value || '' ).trim();
			var min = limits.subjectMin || 4;
			var max = limits.subjectMax || 150;

			if ( subject.length > 0 && subject.length < min ) {
				return fill( i18n.subjectMin, min );
			}

			if ( subject.length > max ) {
				return fill( i18n.subjectMax, max );
			}
		}

		if ( 'message' === key ) {
			var body = String( value || '' ).trim();
			var bodyMin = limits.messageMin || 20;
			var bodyMax = limits.messageMax || 5000;

			if ( body.length > 0 && body.length < bodyMin ) {
				return fill( i18n.messageMin, bodyMin );
			}

			if ( body.length > bodyMax ) {
				return fill( i18n.messageMax, bodyMax );
			}
		}

		if ( 'email' === key ) {
			var address = String( value || '' ).trim();

			if ( address !== '' && ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( address ) ) {
				return i18n.emailInvalid;
			}
		}

		if ( 'category' === key && categories.indexOf( value ) === -1 ) {
			return i18n.categoryRequired;
		}

		if ( 'consent' === key && ! value ) {
			return i18n.consentRequired;
		}

		if ( 'screenshot' === key && value && value.length ) {
			var maxCount = limits.screenshotMaxCount || 5;

			if ( value.length > maxCount ) {
				return fill( i18n.screenshotTooMany, maxCount );
			}

			for ( var i = 0; i < value.length; i++ ) {
				var shot = value[ i ];

				if ( shot.size > ( limits.screenshotMaxBytes || 2097152 ) ) {
					return i18n.screenshotTooLarge;
				}

				if ( [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ].indexOf( shot.type ) === -1 ) {
					return i18n.screenshotType;
				}
			}
		}

		return '';
	}

	/**
	 * Reads the comparable value out of a form control.
	 *
	 * @param {HTMLElement} field Field element.
	 * @return {*} String, boolean, or the chosen files.
	 */
	function fieldValue( field ) {
		if ( 'consent' === field.dataset.rkSupportField ) {
			return field.checked;
		}

		if ( 'screenshot' === field.dataset.rkSupportField ) {
			return field.files ? Array.prototype.slice.call( field.files ) : [];
		}

		return field.value;
	}

	/**
	 * Validates one field and paints the result.
	 *
	 * @param {HTMLElement} field Field element.
	 * @return {boolean} True when the field is acceptable.
	 */
	function validateField( field ) {
		var message = validate( field.dataset.rkSupportField, fieldValue( field ) );

		setError( field, message );

		return message === '';
	}

	/**
	 * Updates a character counter under a field.
	 *
	 * @param {HTMLElement} field Field element.
	 * @return {void}
	 */
	function updateCount( field ) {
		var id = field.dataset.rkSupportCount;

		if ( ! id ) {
			return;
		}

		var node = document.getElementById( id );

		if ( ! node ) {
			return;
		}

		var max = 'subject' === field.dataset.rkSupportField ? ( limits.subjectMax || 150 ) : ( limits.messageMax || 5000 );

		node.textContent = String( field.value.length ) + ' / ' + String( max );
	}

	/**
	 * Validates every field, focusing the first problem.
	 *
	 * @param {HTMLElement} form Support form.
	 * @return {boolean} True when the form may be submitted.
	 */
	function validateAll( form ) {
		var fields = form.querySelectorAll( '[data-rk-support-field]' );
		var ok = true;
		var firstBad = null;

		Array.prototype.forEach.call( fields, function ( field ) {
			var valid = validateField( field );

			if ( ! valid ) {
				ok = false;

				if ( ! firstBad ) {
					firstBad = field;
				}
			}
		} );

		if ( firstBad ) {
			firstBad.focus();
		}

		return ok;
	}

	function init() {
		var page = document.querySelector( '.rk-support' );

		if ( ! page ) {
			return;
		}

		Array.prototype.forEach.call(
			page.querySelectorAll( '[data-rk-support-notice] .rk-ui-notice-dismiss' ),
			function ( button ) {
				button.addEventListener( 'click', function () {
					var notice = button.closest( '[data-rk-support-notice]' );

					if ( notice ) {
						notice.remove();
					}
				} );
			}
		);

		var form = page.querySelector( '[data-rk-support-form]' );

		if ( ! form ) {
			return;
		}

		var fileName = page.querySelector( '#rk-support-file-name' );
		var fileList = page.querySelector( '[data-rk-support-file-list]' );
		var shotField = form.querySelector( '[data-rk-support-field="screenshot"]' );
		var shotStore = null;

		if ( typeof DataTransfer !== 'undefined' ) {
			shotStore = new DataTransfer();
		}

		function emptyText() {
			return fileName ? fileName.getAttribute( 'data-rk-support-empty' ) || '' : '';
		}

		function currentShots() {
			if ( ! shotField || ! shotField.files ) {
				return [];
			}

			return Array.prototype.slice.call( shotField.files );
		}

		function renderShots() {
			var shots = currentShots();

			if ( fileName ) {
				if ( 1 === shots.length ) {
					fileName.textContent = shots[ 0 ].name;
					fileName.classList.add( 'has-file' );
				} else if ( shots.length > 1 ) {
					fileName.textContent = fill( i18n.filesSelected, shots.length );
					fileName.classList.add( 'has-file' );
				} else {
					fileName.textContent = emptyText();
					fileName.classList.remove( 'has-file' );
				}
			}

			if ( ! fileList ) {
				return;
			}

			while ( fileList.firstChild ) {
				fileList.removeChild( fileList.firstChild );
			}

			if ( shots.length < 2 ) {
				fileList.setAttribute( 'hidden', '' );

				return;
			}

			fileList.removeAttribute( 'hidden' );

			shots.forEach( function ( shot, index ) {
				var item = document.createElement( 'li' );
				item.className = 'rk-support-file-item';

				var name = document.createElement( 'span' );
				name.className = 'rk-support-file-item-name';

				var icon = document.createElement( 'span' );
				icon.className = 'rk-icon';
				icon.setAttribute( 'aria-hidden', 'true' );
				icon.textContent = 'cloud_upload';
				name.appendChild( icon );
				name.appendChild( document.createTextNode( shot.name ) );
				item.appendChild( name );

				var remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'rk-support-file-remove';
				remove.setAttribute( 'data-rk-support-remove', String( index ) );
				remove.setAttribute( 'aria-label', ( i18n.removeFile || '' ) + ' ' + shot.name );

				var removeIcon = document.createElement( 'span' );
				removeIcon.className = 'rk-icon';
				removeIcon.setAttribute( 'aria-hidden', 'true' );
				removeIcon.textContent = 'close';
				remove.appendChild( removeIcon );
				item.appendChild( remove );

				fileList.appendChild( item );
			} );
		}

		if ( shotField ) {
			form.addEventListener( 'change', function ( event ) {
				var field = event.target.closest( '[data-rk-support-field="screenshot"]' );

				if ( ! field || ! field.files || ! shotStore ) {
					renderShots();

					return;
				}

				Array.prototype.forEach.call( field.files, function ( shot ) {
					shotStore.items.add( shot );
				} );

				field.files = shotStore.files;
				renderShots();
			} );

			if ( fileList ) {
				fileList.addEventListener( 'click', function ( event ) {
					var button = event.target.closest( '[data-rk-support-remove]' );

					if ( ! button || ! shotStore || ! shotField.files ) {
						return;
					}

					var at = parseInt( button.getAttribute( 'data-rk-support-remove' ), 10 );
					var kept = new DataTransfer();

					Array.prototype.forEach.call( shotField.files, function ( shot, index ) {
						if ( index !== at ) {
							kept.items.add( shot );
						}
					} );

					shotStore = kept;
					shotField.files = kept.files;
					renderShots();
					validateField( shotField );
				} );
			}

			renderShots();
		}

		form.addEventListener(
			'submit',
			function ( event ) {
				if ( ! validateAll( form ) ) {
					event.preventDefault();
				}
			}
		);

		form.addEventListener( 'input', function ( event ) {
			var field = event.target.closest( '[data-rk-support-field]' );

			if ( ! field ) {
				return;
			}

			if ( field.dataset.rkSupportCount ) {
				updateCount( field );
			}

			// Live feedback once a field has been marked invalid, so the
			// message clears as soon as it is fixed.
			if ( field.getAttribute( 'aria-invalid' ) === 'true' ) {
				validateField( field );
			}
		} );

		form.addEventListener( 'change', function ( event ) {
			var field = event.target.closest( '[data-rk-support-field]' );

			if ( field && [ 'select-one', 'checkbox', 'file' ].indexOf( field.type ) !== -1 ) {
				validateField( field );
			}
		} );

		form.addEventListener(
			'blur',
			function ( event ) {
				var field = event.target.closest( '[data-rk-support-field]' );

				if ( field ) {
					validateField( field );
				}
			},
			true
		);

		Array.prototype.forEach.call( form.querySelectorAll( '[data-rk-support-count]' ), function ( field ) {
			updateCount( field );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	if ( 'undefined' !== typeof window ) {
		if ( ! window.rankkernelSupport ) {
			window.rankkernelSupport = {};
		}

		window.rankkernelSupport.validate = validate;
		window.rankkernelSupport.fieldValue = fieldValue;
	}
} )();