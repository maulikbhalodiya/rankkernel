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

	// PHP's \s is two different sets. Without the /u modifier it is ASCII:
	// space, tab, newline, carriage return, form feed and vertical tab. With
	// /u (PHP 7.4 through 8.5 on PCRE2 10.40 and later) it adds U+0085,
	// U+00A0, U+1680, U+180E and U+2000 to U+3000 spacing, and still excludes
	// U+FEFF. JavaScript's \s adds U+FEFF and drops U+0085. Patterns mirrored
	// from PHP therefore spell out the set the PHP side really uses, so both
	// engines cut the same text and return the same score.
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
		text = text.replace( /<br[ \t\n\r\f\v]*\/?>/gi, '\n' );
		text = text.replace( /<\/(p|div|h[1-6]|li|blockquote|tr)>/gi, '\n' );
		text = text.replace( /<[^>]*>/g, ' ' );
		text = decodeEntities( text );
		text = text.replace( /[ \t\x0B\f\r]+/g, ' ' );
		text = text.replace( /\n[ \t\n\r\f\v]*\n+/g, '\n' );
		return phpTrim( text );
	}

	var WORD_SPLIT = /[^\p{L}\p{N}'\u2019-]+/u;
	var WORD_EDGE = /^['\u2019-]+|['\u2019-]+$/g;

	function words( text ) {
		var parts = String( text == null ? '' : text ).split( WORD_SPLIT );
		var out = [];
		for ( var i = 0; i < parts.length; i++ ) {
			var part = parts[ i ].replace( WORD_EDGE, '' );
			if ( '' !== part ) {
				out.push( part );
			}
		}
		return out;
	}

	function sentences( text ) {
		var out = [];
		var lines = String( text == null ? '' : text ).split( '\n' );
		for ( var i = 0; i < lines.length; i++ ) {
			var line = phpTrim( lines[ i ] );
			if ( '' === line ) {
				continue;
			}
			var parts = line.split( /(?<=[.!?])[ \t\n\r\f\v\u0085\u00a0\u1680\u180e\u2000-\u200a\u2028\u2029\u202f\u205f\u3000]+/u );
			for ( var j = 0; j < parts.length; j++ ) {
				var part = phpTrim( parts[ j ] );
				if ( '' !== part ) {
					out.push( part );
				}
			}
		}
		return out;
	}

	function paragraphs( text ) {
		var parts = String( text == null ? '' : text ).split( /\n+/ );
		var out = [];
		for ( var i = 0; i < parts.length; i++ ) {
			var part = phpTrim( parts[ i ] );
			if ( '' !== part ) {
				out.push( part );
			}
		}
		return out;
	}

	function headings( html ) {
		var out = [];
		var pattern = /<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/gi;
		var match;
		while ( ( match = pattern.exec( String( html == null ? '' : html ) ) ) !== null ) {
			out.push( { level: parseInt( match[ 1 ], 10 ), text: plainText( match[ 2 ] ) } );
		}
		return out;
	}

	function imageAlts( html ) {
		var out = [];
		var tags = String( html == null ? '' : html ).match( /<img\b[^>]*>/gi ) || [];
		for ( var i = 0; i < tags.length; i++ ) {
			var alt = tags[ i ].match( /\balt[ \t\n\r\f\v]*=[ \t\n\r\f\v]*("([^"]*)"|'([^']*)')/i );
			out.push( alt ? ( alt[ 2 ] !== undefined ? alt[ 2 ] : alt[ 3 ] ) : '' );
		}
		return out;
	}

	function links( html ) {
		var out = [];
		var pattern = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi;
		var match;
		while ( ( match = pattern.exec( String( html == null ? '' : html ) ) ) !== null ) {
			var attrs = match[ 1 ];
			var hrefMatch = attrs.match( /\bhref[ \t\n\r\f\v]*=[ \t\n\r\f\v]*("([^"]*)"|'([^']*)')/i );
			var relMatch = attrs.match( /\brel[ \t\n\r\f\v]*=[ \t\n\r\f\v]*("([^"]*)"|'([^']*)')/i );
			var rawHref = hrefMatch ? ( hrefMatch[ 2 ] !== undefined ? hrefMatch[ 2 ] : hrefMatch[ 3 ] ) : '';
			var rawRel = relMatch ? ( relMatch[ 2 ] !== undefined ? relMatch[ 2 ] : relMatch[ 3 ] ) : '';
			out.push( { href: decodeEntities( rawHref ), rel: rawRel.toLowerCase(), text: plainText( match[ 2 ] ) } );
		}
		return out;
	}

	function media( html ) {
		var text = String( html == null ? '' : html );
		var images = ( text.match( /<img\b[^>]*>/gi ) || [] ).length + ( text.match( /\[gallery\b[^\]]*\]/gi ) || [] ).length;
		var videos = ( text.match( /<video\b[^>]*>|<iframe\b[^>]*(youtube|vimeo)[^>]*>|\[video\b[^\]]*\]/gi ) || [] ).length;
		return { images: images, videos: videos };
	}

	function hasToc( html ) {
		return /wp:rankkernel\/toc|\[rankkernel_toc|wp-block-rank-math-toc-block|\[toc\b/i.test( String( html == null ? '' : html ) );
	}

	function longSentenceRatio( list, limit ) {
		if ( ! list || 0 === list.length ) {
			return 0;
		}
		var long = 0;
		for ( var i = 0; i < list.length; i++ ) {
			if ( words( list[ i ] ).length > limit ) {
				long++;
			}
		}
		return long / list.length;
	}

	function longestParagraph( list ) {
		var max = 0;
		for ( var i = 0; i < list.length; i++ ) {
			var count = words( list[ i ] ).length;
			if ( count > max ) {
				max = count;
			}
		}
		return max;
	}

	function longestRepeatedOpening( list ) {
		var previous = null;
		var run = 0;
		var max = 0;
		for ( var i = 0; i < list.length; i++ ) {
			var first = words( list[ i ] );
			var head = first.length > 0 ? first[ 0 ].toLowerCase() : '';
			if ( null !== previous && '' !== previous && previous === head ) {
				run++;
			} else {
				run = 1;
			}
			previous = head;
			if ( run > max ) {
				max = run;
			}
		}
		return max;
	}

	function readingTime( wordCount ) {
		return Math.max( 1, Math.ceil( wordCount / 200 ) );
	}

	return {
		decodeEntities: decodeEntities,
		phpTrim: phpTrim,
		plainText: plainText,
		words: words,
		sentences: sentences,
		paragraphs: paragraphs,
		headings: headings,
		imageAlts: imageAlts,
		links: links,
		media: media,
		hasToc: hasToc,
		longSentenceRatio: longSentenceRatio,
		longestParagraph: longestParagraph,
		longestRepeatedOpening: longestRepeatedOpening,
		readingTime: readingTime
	};
} ) );
