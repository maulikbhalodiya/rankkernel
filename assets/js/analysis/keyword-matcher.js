/**
 * RankKernel analysis engine: keyword matching.
 *
 * Pure module. Mirrors KeywordMatcher.php. Function words are English only
 * and are used only for strategies two and three.
 */
( function ( root, factory ) {
	'use strict';

	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory( require( './text-stats.js' ), require( './accents.js' ) );
	} else {
		root.RankKernelAnalysis = root.RankKernelAnalysis || {};
		root.RankKernelAnalysis.KeywordMatcher = factory( root.RankKernelAnalysis.TextStats, root.RankKernelAnalysis.Accents );
	}
}( typeof globalThis !== 'undefined' ? globalThis : this, function ( TextStats, Accents ) {
	'use strict';

	var FUNCTION_WORDS = {
		a: 1, about: 1, above: 1, after: 1, again: 1, against: 1, all: 1, also: 1,
		am: 1, an: 1, and: 1, any: 1, are: 1, as: 1, at: 1, be: 1, because: 1,
		been: 1, before: 1, being: 1, below: 1, between: 1, both: 1, but: 1, by: 1,
		can: 1, cannot: 1, could: 1, did: 1, do: 1, does: 1, doing: 1, down: 1,
		during: 1, each: 1, few: 1, for: 1, from: 1, further: 1, had: 1, has: 1,
		have: 1, having: 1, he: 1, her: 1, here: 1, hers: 1, herself: 1, him: 1,
		himself: 1, his: 1, how: 1, i: 1, if: 1, in: 1, into: 1, is: 1, it: 1,
		its: 1, itself: 1, just: 1, me: 1, more: 1, most: 1, my: 1, myself: 1,
		no: 1, nor: 1, not: 1, now: 1, of: 1, off: 1, on: 1, once: 1, only: 1,
		or: 1, other: 1, our: 1, ours: 1, ourselves: 1, out: 1, over: 1, own: 1,
		same: 1, she: 1, should: 1, so: 1, some: 1, such: 1, than: 1, that: 1,
		the: 1, their: 1, theirs: 1, them: 1, themselves: 1, then: 1, there: 1,
		these: 1, they: 1, this: 1, those: 1, through: 1, to: 1, too: 1, under: 1,
		until: 1, up: 1, very: 1, was: 1, we: 1, were: 1, what: 1, when: 1,
		where: 1, which: 1, while: 1, who: 1, whom: 1, why: 1, will: 1, with: 1,
		would: 1, you: 1, your: 1, yours: 1, yourself: 1, yourselves: 1
	};

	function strip( value ) {
		return 'function' === typeof value ? '' : String( value == null ? '' : value );
	}

	function normalize( text, stripAccents ) {
		var value = strip( text );
		if ( false !== stripAccents ) {
			value = Accents.removeAccents( value );
		}
		value = value.toLowerCase();
		value = value.replace( /[^\p{L}\p{N}]+/gu, ' ' );
		value = value.replace( /[ \t\n\r\f\v\u0085\u00a0\u1680\u180e\u2000-\u200a\u2028\u2029\u202f\u205f\u3000]+/gu, ' ' );
		return TextStats.phpTrim( value );
	}

	function words( text, stripAccents ) {
		var normalized = normalize( text, stripAccents );
		return '' === normalized ? [] : normalized.split( ' ' );
	}

	function contentWords( text, stripAccents ) {
		var list = words( text, stripAccents );
		var out = [];
		for ( var i = 0; i < list.length; i++ ) {
			if ( ! Object.prototype.hasOwnProperty.call( FUNCTION_WORDS, list[ i ] ) ) {
				out.push( list[ i ] );
			}
		}
		return out.length > 0 ? out : list;
	}

	function phrasePresent( haystack, needle ) {
		return ( ' ' + haystack + ' ' ).indexOf( ' ' + needle + ' ' ) !== -1;
	}

	function sentenceHasAll( sentence, content ) {
		for ( var i = 0; i < content.length; i++ ) {
			if ( ! phrasePresent( sentence, content[ i ] ) ) {
				return false;
			}
		}
		return true;
	}

	function contains( haystack, keyword, stripAccents ) {
		var needle = normalize( keyword, stripAccents );
		if ( '' === needle ) {
			return false;
		}
		var hay = normalize( haystack, stripAccents );
		if ( '' === hay ) {
			return false;
		}
		if ( phrasePresent( hay, needle ) ) {
			return true;
		}
		var content = contentWords( keyword, stripAccents );
		if ( content.length > 1 ) {
			var list = TextStats.sentences( strip( haystack ) );
			for ( var i = 0; i < list.length; i++ ) {
				if ( sentenceHasAll( normalize( list[ i ], stripAccents ), content ) ) {
					return true;
				}
			}
		}
		var reduced = contentWords( haystack, stripAccents ).join( ' ' );
		if ( '' !== reduced ) {
			return phrasePresent( reduced, content.join( ' ' ) );
		}
		return false;
	}

	function occurrences( haystack, keyword, stripAccents ) {
		var needle = normalize( keyword, stripAccents );
		if ( '' === needle ) {
			return 0;
		}
		var hay = normalize( haystack, stripAccents );
		if ( '' === hay ) {
			return 0;
		}
		var padded = ' ' + hay + ' ';
		var target = ' ' + needle + ' ';
		var count = 0;
		var from = 0;
		var at = padded.indexOf( target, from );
		while ( at !== -1 ) {
			count++;
			from = at + target.length;
			at = padded.indexOf( target, from );
		}
		return count;
	}

	function density( haystack, keyword, stripAccents ) {
		var total = words( haystack, stripAccents ).length;
		if ( 0 === total ) {
			return 0;
		}
		return ( occurrences( haystack, keyword, stripAccents ) / total ) * 100;
	}

	return {
		normalize: normalize,
		words: words,
		contentWords: contentWords,
		contains: contains,
		occurrences: occurrences,
		density: density
	};
} ) );
