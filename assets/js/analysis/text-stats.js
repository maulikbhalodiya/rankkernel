/**
 * RankKernel analysis engine: text extraction and statistics.
 *
 * Pure module. No DOM, no WordPress, no network. It loads as a plain
 * browser script and is requireable by Node with no shims.
 */
( function ( root, factory ) {
	'use strict';

	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.RankKernelAnalysis = root.RankKernelAnalysis || {};
		root.RankKernelAnalysis.TextStats = factory();
	}
}( typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	var PHP_TRIM = /^[ \t\n\r\x00\x0B]+|[ \t\n\r\x00\x0B]+$/g;
	var NAMED_ENTITIES = {
		amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0',
		copy: '\u00a9', reg: '\u00ae', trade: '\u2122', hellip: '\u2026',
		mdash: '\u2014', ndash: '\u2013', lsquo: '\u2018', rsquo: '\u2019',
		ldquo: '\u201c', rdquo: '\u201d', times: '\u00d7', divide: '\u00f7',
		deg: '\u00b0', plusmn: '\u00b1', frac12: '\u00bd', frac14: '\u00bc',
		frac34: '\u00be', euro: '\u20ac', pound: '\u00a3', yen: '\u00a5',
		cent: '\u00a2', sect: '\u00a7', para: '\u00b6', middot: '\u00b7',
		laquo: '\u00ab', raquo: '\u00bb', bull: '\u2022', dagger: '\u2020',
		Dagger: '\u2021', permil: '\u2030', prime: '\u2032', Prime: '\u2033',
		larr: '\u2190', rarr: '\u2192', harr: '\u2194', le: '\u2264',
		ge: '\u2265', ne: '\u2260', infin: '\u221e', alpha: '\u03b1',
		beta: '\u03b2', gamma: '\u03b3', delta: '\u03b4', pi: '\u03c0',
		Sigma: '\u03a3', sigma: '\u03c3', omega: '\u03c9', ensp: '\u2002',
		emsp: '\u2003', thinsp: '\u2009'
	};
	var ENTITY_PATTERN = /&(#x[0-9a-f]+|#[0-9]+|[a-z][a-z0-9]*);/gi;

	function decodeEntities( text ) {
		return String( text == null ? '' : text ).replace( ENTITY_PATTERN, function ( match, body ) {
			if ( '#' === body.charAt( 0 ) ) {
				var lower = body.toLowerCase();
				var code = 'x' === lower.charAt( 1 ) ? parseInt( body.slice( 2 ), 16 ) : parseInt( body.slice( 1 ), 10 );
				if ( ! isFinite( code ) || code < 0 || code > 0x10ffff ) {
					return match;
				}
				return String.fromCodePoint( code );
			}
			if ( Object.prototype.hasOwnProperty.call( NAMED_ENTITIES, body ) ) {
				return NAMED_ENTITIES[ body ];
			}
			return match;
		} );
	}

	function phpTrim( text ) {
		return String( text == null ? '' : text ).replace( PHP_TRIM, '' );
	}

	function plainText( html ) {
		var text = String( html == null ? '' : html );
		text = text.replace( /<(script|style)\b[^>]*>[\s\S]*?<\/\1>/gi, ' ' );
		text = text.replace( /<br\s*\/?>/gi, '\n' );
		text = text.replace( /<\/(p|div|h[1-6]|li|blockquote|tr)>/gi, '\n' );
		text = text.replace( /<[^>]*>/g, ' ' );
		text = decodeEntities( text );
		text = text.replace( /[ \t\x0B\f\r]+/g, ' ' );
		text = text.replace( /\n\s*\n+/g, '\n' );
		return phpTrim( text );
	}

	return {
		decodeEntities: decodeEntities,
		phpTrim: phpTrim,
		plainText: plainText
	};
} ) );
