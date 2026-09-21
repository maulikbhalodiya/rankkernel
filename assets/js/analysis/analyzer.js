/**
 * RankKernel analysis engine: scoring and checks.
 *
 * Pure module. Mirrors Analyzer.php. No DOM, no WordPress, no network.
 */
( function ( root, factory ) {
	'use strict';

	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory( require( './keyword-matcher.js' ), require( './text-stats.js' ), require( './analysis-format.js' ) );
	} else {
		root.RankKernelAnalysis = root.RankKernelAnalysis || {};
		root.RankKernelAnalysis.Analyzer = factory( root.RankKernelAnalysis.KeywordMatcher, root.RankKernelAnalysis.TextStats, root.RankKernelAnalysis.AnalysisFormat );
	}
}( typeof globalThis !== 'undefined' ? globalThis : this, function ( KeywordMatcher, TextStats, AnalysisFormat ) {
	'use strict';

	var RULES_VERSION = 1;
	var BAND_GOOD = 'good';
	var BAND_IMPROVE = 'improve';
	var BAND_PROBLEM = 'problem';
	var STATUS_PASS = 'pass';
	var STATUS_IMPROVE = 'improve';
	var STATUS_PROBLEM = 'problem';
	var STATUS_NA = 'na';
	var CATEGORY_SEO = 'seo';
	var CATEGORY_TITLE = 'title';
	var CATEGORY_READABILITY = 'readability';
	var M = AnalysisFormat.MESSAGES;

	var WEIGHTS = {
		keyword_in_title: 36, keyword_in_description: 2, keyword_in_slug: 5, keyword_in_opening: 3,
		keyword_in_content: 3, keyword_in_subheading: 3, keyword_in_image_alt: 2, keyword_density: 6,
		keyword_distribution: 3, keyword_uniqueness: 0, title_starts_with_keyword: 3, title_has_number: 1,
		title_has_power_word: 1, title_sentiment: 1, content_length: 8, slug_length: 4, internal_links: 5,
		external_links: 4, followed_external: 2, generic_anchor_text: 3, image_alt_quality: 3,
		short_paragraphs: 3, sentence_length: 3, subheading_distribution: 3, consecutive_sentences: 3,
		passive_voice: 3, transition_words: 3, media: 6, single_h1: 3, table_of_contents: 2, text_present: 3
	};

	var POWER_WORDS = [ 'best', 'free', 'guide', 'how', 'new', 'proven', 'easy', 'fast', 'ultimate', 'complete', 'essential', 'simple', 'top', 'why', 'secret', 'step', 'expert', 'quick' ];
	var POSITIVE_WORDS = [ 'best', 'great', 'good', 'love', 'easy', 'free', 'help', 'improve', 'save', 'win', 'safe', 'fast', 'simple', 'boost', 'success' ];
	var NEGATIVE_WORDS = [ 'bad', 'worst', 'fail', 'error', 'problem', 'risk', 'stop', 'avoid', 'lose', 'hard', 'slow', 'broken', 'warn', 'never', 'mistake' ];
	var TRANSITION_WORDS = [ 'also', 'although', 'because', 'but', 'consequently', 'finally', 'first', 'for example', 'however', 'instead', 'meanwhile', 'moreover', 'next', 'since', 'therefore', 'though', 'thus', 'while', 'additionally', 'as a result' ];
	var GENERIC_ANCHORS = [ 'click here', 'read more', 'this', 'here', 'link', 'website' ];
	var PASSIVE_PATTERN = /\b(is|are|was|were|be|been|being)\s+(\w+ed|done|made|given|taken|seen|known|shown|held|built|sent|found|kept|left|written|read)\b/i;

	function identity( text ) {
		return text;
	}

	function makeCheck( id, category, status, earned, message ) {
		var weight = Object.prototype.hasOwnProperty.call( WEIGHTS, id ) ? WEIGHTS[ id ] : 0;
		return {
			id: id,
			category: category,
			status: status,
			weight: weight,
			earned: STATUS_NA === status ? 0 : earned,
			message: message
		};
	}

	function roundScore( value ) {
		// PHP's round() pre-rounds to about 15 significant digits before the
		// half away from zero step, so the browser score equals the stored one.
		return Math.round( Number( value.toPrecision( 15 ) ) );
	}

	function scoreOf( checks ) {
		var earned = 0;
		var max = 0;
		for ( var i = 0; i < checks.length; i++ ) {
			if ( STATUS_NA === checks[ i ].status ) {
				continue;
			}
			earned += checks[ i ].earned;
			max += checks[ i ].weight;
		}
		return max > 0 ? roundScore( ( earned / max ) * 100 ) : 0;
	}

	function bandOf( score ) {
		if ( score >= 81 ) {
			return BAND_GOOD;
		}
		if ( score >= 51 ) {
			return BAND_IMPROVE;
		}
		return BAND_PROBLEM;
	}

	function normalizeKeywords( list, stripAccents ) {
		if ( ! Array.isArray( list ) ) {
			return [];
		}
		var seen = {};
		var out = [];
		for ( var i = 0; i < list.length; i++ ) {
			var value = TextStats.phpTrim( String( list[ i ] ) );
			var key = KeywordMatcher.normalize( value, stripAccents );
			if ( '' === key || Object.prototype.hasOwnProperty.call( seen, key ) ) {
				continue;
			}
			seen[ key ] = 1;
			out.push( value );
		}
		return out;
	}

	function t( translate, template, args ) {
		return AnalysisFormat.format( template, args, translate );
	}

	function pass( id, category, message ) {
		return makeCheck( id, category, STATUS_PASS, WEIGHTS[ id ], message );
	}

	function improve( id, category, earned, message ) {
		return makeCheck( id, category, STATUS_IMPROVE, earned, message );
	}

	function problem( id, category, message ) {
		return makeCheck( id, category, STATUS_PROBLEM, 0, message );
	}

	function na( id, category, message ) {
		return makeCheck( id, category, STATUS_NA, 0, message );
	}

	function codePoints( text ) {
		return Array.from( String( text ) );
	}

	function keywordInContent( ctx ) {
		var id = 'keyword_in_content';
		if ( '' === ctx.text ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		if ( KeywordMatcher.contains( ctx.text, ctx.keyword, ctx.stripAccents ) ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_content_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_content_problem, [ ctx.keyword ] ) );
	}

	function keywordInSubheading( ctx ) {
		var id = 'keyword_in_subheading';
		var subs = ctx.headings.filter( function ( heading ) { return heading.level >= 2; } );
		var joined = subs.map( function ( heading ) { return heading.text; } ).join( '\n' );
		if ( '' === TextStats.phpTrim( joined ) ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.subheading_na, [] ) );
		}
		if ( KeywordMatcher.contains( joined, ctx.keyword, ctx.stripAccents ) ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_subheading_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_subheading_problem, [ ctx.keyword ] ) );
	}

	function keywordDensity( ctx ) {
		var id = 'keyword_density';
		if ( 0 === KeywordMatcher.words( ctx.text, ctx.stripAccents ).length ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		var count = KeywordMatcher.occurrences( ctx.text, ctx.keyword, ctx.stripAccents );
		if ( 0 === count ) {
			var absent = KeywordMatcher.contains( ctx.text, ctx.keyword, ctx.stripAccents )
				? t( ctx.translate, M.density_variation, [] )
				: t( ctx.translate, M.density_absent, [] );
			return problem( id, CATEGORY_SEO, absent );
		}
		var value = KeywordMatcher.density( ctx.text, ctx.keyword, ctx.stripAccents );
		var base = t( ctx.translate, M.density_count, [ count, AnalysisFormat.numberFormat( value, 2 ) ] );
		if ( value <= 2.5 ) {
			return pass( id, CATEGORY_SEO, base + ' ' + t( ctx.translate, M.density_pass_suffix, [] ) );
		}
		return improve( id, CATEGORY_SEO, 2, base + ' ' + t( ctx.translate, M.density_improve_suffix, [] ) );
	}

	function keywordDistribution( ctx ) {
		var id = 'keyword_distribution';
		var total = ctx.sentences.length;
		if ( total < 15 ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		var windowSize = 5;
		var windows = total - windowSize + 1;
		var worst = 0;
		for ( var i = 0; i < windows; i++ ) {
			var slice = ctx.sentences.slice( i, i + windowSize ).join( ' ' );
			if ( ! KeywordMatcher.contains( slice, ctx.keyword, ctx.stripAccents ) ) {
				worst++;
			}
		}
		if ( 0 === worst ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.distribution_pass, [] ) );
		}
		var ratio = worst / Math.max( 1, windows );
		if ( ratio <= 0.5 ) {
			return improve( id, CATEGORY_SEO, 1, t( ctx.translate, M.distribution_improve, [] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.distribution_problem, [] ) );
	}

	function keywordInTitle( ctx ) {
		var id = 'keyword_in_title';
		if ( '' === ctx.title ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.title_na, [] ) );
		}
		if ( KeywordMatcher.contains( ctx.title, ctx.keyword, ctx.stripAccents ) ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_title_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_title_problem, [ ctx.keyword ] ) );
	}

	function keywordInDescription( ctx ) {
		var id = 'keyword_in_description';
		if ( '' === ctx.description ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.description_na, [] ) );
		}
		if ( KeywordMatcher.contains( ctx.description, ctx.keyword, ctx.stripAccents ) ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_description_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_description_problem, [ ctx.keyword ] ) );
	}

	function keywordInSlug( ctx ) {
		var id = 'keyword_in_slug';
		if ( '' === ctx.slug ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		var hay = ctx.slug.replace( /[-_]/g, ' ' );
		if ( KeywordMatcher.contains( hay, ctx.keyword, ctx.stripAccents ) ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_slug_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_slug_problem, [ ctx.keyword ] ) );
	}

	function keywordInOpening( ctx ) {
		var id = 'keyword_in_opening';
		var list = KeywordMatcher.words( ctx.text, ctx.stripAccents );
		if ( 0 === list.length ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		var limit = list.length > 400 ? Math.floor( list.length * 0.1 ) : list.length;
		var take = Math.max( 1, limit );
		var opening = list.slice( 0, take ).join( ' ' );
		if ( KeywordMatcher.contains( opening, ctx.keyword, ctx.stripAccents ) ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_opening_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_opening_problem, [ ctx.keyword ] ) );
	}

	function keywordInImageAlt( ctx ) {
		var id = 'keyword_in_image_alt';
		var alts = ctx.alts.slice();
		if ( '' !== ctx.featuredAlt ) {
			alts.push( ctx.featuredAlt );
		}
		var joined = alts.join( '\n' );
		if ( '' === TextStats.phpTrim( joined ) ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.image_na, [] ) );
		}
		if ( KeywordMatcher.contains( joined, ctx.keyword, ctx.stripAccents ) ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_image_alt_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.keyword_in_image_alt_problem, [ ctx.keyword ] ) );
	}

	function titleStartsWithKeyword( ctx ) {
		var id = 'title_starts_with_keyword';
		if ( '' === ctx.title ) {
			return na( id, CATEGORY_TITLE, t( ctx.translate, M.title_na, [] ) );
		}
		var hay = codePoints( KeywordMatcher.normalize( ctx.title, ctx.stripAccents ) );
		var needle = codePoints( KeywordMatcher.normalize( ctx.keyword, ctx.stripAccents ) );
		var position = -1;
		if ( needle.length > 0 ) {
			for ( var i = 0; i + needle.length <= hay.length; i++ ) {
				var match = true;
				for ( var j = 0; j < needle.length; j++ ) {
					if ( hay[ i + j ] !== needle[ j ] ) {
						match = false;
						break;
					}
				}
				if ( match ) {
					position = i;
					break;
				}
			}
		}
		if ( position !== -1 && position < Math.floor( hay.length / 2 ) ) {
			return pass( id, CATEGORY_TITLE, t( ctx.translate, M.title_start_pass, [ ctx.keyword ] ) );
		}
		return problem( id, CATEGORY_TITLE, t( ctx.translate, M.title_start_problem, [ ctx.keyword ] ) );
	}

	function keywordUniqueness( ctx ) {
		var id = 'keyword_uniqueness';
		if ( null === ctx.usedKeywords || undefined === ctx.usedKeywords ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		var key = KeywordMatcher.normalize( ctx.keyword, ctx.stripAccents );
		var list = Array.isArray( ctx.usedKeywords ) ? ctx.usedKeywords : [];
		for ( var i = 0; i < list.length; i++ ) {
			if ( KeywordMatcher.normalize( list[ i ], ctx.stripAccents ) === key ) {
				return improve( id, CATEGORY_SEO, 0, t( ctx.translate, M.uniqueness_improve, [] ) );
			}
		}
		return pass( id, CATEGORY_SEO, t( ctx.translate, M.uniqueness_pass, [] ) );
	}

	function sharedChecks( ctx ) {
		return [ keywordInContent( ctx ), keywordInSubheading( ctx ), keywordDensity( ctx ), keywordDistribution( ctx ) ];
	}

	function keywordChecks( ctx ) {
		return [ keywordInTitle( ctx ), keywordInDescription( ctx ), keywordInSlug( ctx ), keywordInOpening( ctx ), keywordInImageAlt( ctx ), titleStartsWithKeyword( ctx ), keywordUniqueness( ctx ) ];
	}

	function hostOf( url ) {
		var match = String( url == null ? '' : url ).match( /^([a-z][a-z0-9+.-]*:)?\/\/([^/?#]*)/i );
		if ( ! match ) {
			return '';
		}
		var authority = match[ 2 ].replace( /^[^@]*@/, '' );
		var host;
		if ( '[' === authority.charAt( 0 ) ) {
			var end = authority.indexOf( ']' );
			host = end === -1 ? authority : authority.slice( 0, end + 1 );
		} else {
			host = authority.split( ':' )[ 0 ];
		}
		return host.toLowerCase();
	}

	function classifyLinks( links, siteUrl ) {
		var host = hostOf( siteUrl );
		var out = { internal: 0, external: 0, followed: 0 };
		for ( var i = 0; i < links.length; i++ ) {
			var href = TextStats.phpTrim( links[ i ].href );
			if ( '' === href || '#' === href.charAt( 0 ) || 0 === href.indexOf( 'mailto:' ) || 0 === href.indexOf( 'tel:' ) ) {
				continue;
			}
			var linkHost = hostOf( href );
			if ( '' === linkHost || ( '' !== host && host === linkHost ) ) {
				out.internal++;
				continue;
			}
			out.external++;
			if ( links[ i ].rel.indexOf( 'nofollow' ) === -1 ) {
				out.followed++;
			}
		}
		return out;
	}

	function genericAnchorCount( links, stripAccents ) {
		var total = 0;
		var flagged = 0;
		for ( var i = 0; i < links.length; i++ ) {
			var href = TextStats.phpTrim( links[ i ].href );
			if ( '' === href || '#' === href.charAt( 0 ) || 0 === href.indexOf( 'mailto:' ) || 0 === href.indexOf( 'tel:' ) ) {
				continue;
			}
			total++;
			var text = TextStats.phpTrim( links[ i ].text );
			var normalized = KeywordMatcher.normalize( text, stripAccents );
			if ( GENERIC_ANCHORS.indexOf( normalized ) !== -1 || /^https?:\/\/[^ ]+$/i.test( text ) ) {
				flagged++;
			}
		}
		return { total: total, flagged: flagged };
	}

	function hasAny( list, set ) {
		for ( var i = 0; i < list.length; i++ ) {
			if ( set.indexOf( list[ i ] ) !== -1 ) {
				return true;
			}
		}
		return false;
	}

	function contentLength( ctx ) {
		var id = 'content_length';
		var count = ctx.words.length;
		if ( 0 === count ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		var message = t( ctx.translate, M.content_length, [ count ] );
		if ( count >= 2500 ) {
			return pass( id, CATEGORY_SEO, message );
		}
		var earned = count >= 2000 ? 5 : ( count >= 1500 ? 4 : ( count >= 1000 ? 3 : ( count >= 600 ? 2 : 0 ) ) );
		return improve( id, CATEGORY_SEO, earned, message );
	}

	function slugLength( ctx ) {
		var id = 'slug_length';
		if ( '' === ctx.slug ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.default_na, [] ) );
		}
		var length = codePoints( ctx.slug ).length;
		var message = t( ctx.translate, M.slug_length, [ length ] );
		return length <= 75 ? pass( id, CATEGORY_SEO, message ) : improve( id, CATEGORY_SEO, 0, message );
	}

	function internalLinks( ctx ) {
		var id = 'internal_links';
		if ( ctx.linkStats.internal > 0 ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.internal_links_pass, [] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.internal_links_problem, [] ) );
	}

	function externalLinks( ctx ) {
		var id = 'external_links';
		if ( ctx.linkStats.external > 0 ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.external_links_pass, [] ) );
		}
		return problem( id, CATEGORY_SEO, t( ctx.translate, M.external_links_problem, [] ) );
	}

	function followedExternal( ctx ) {
		var id = 'followed_external';
		if ( 0 === ctx.linkStats.external ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.outbound_na, [] ) );
		}
		if ( ctx.linkStats.followed > 0 ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.followed_pass, [] ) );
		}
		return improve( id, CATEGORY_SEO, 0, t( ctx.translate, M.followed_improve, [] ) );
	}

	function genericAnchorText( ctx ) {
		var id = 'generic_anchor_text';
		if ( 0 === ctx.anchorStats.total ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.link_na, [] ) );
		}
		if ( 0 === ctx.anchorStats.flagged ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.generic_pass, [] ) );
		}
		return improve( id, CATEGORY_SEO, 0, t( ctx.translate, M.generic_improve, [ ctx.anchorStats.flagged ] ) );
	}

	function titleHasNumber( ctx ) {
		var id = 'title_has_number';
		if ( '' === ctx.title ) {
			return na( id, CATEGORY_TITLE, t( ctx.translate, M.default_na, [] ) );
		}
		return /\d/.test( ctx.title )
			? pass( id, CATEGORY_TITLE, t( ctx.translate, M.title_number_pass, [] ) )
			: improve( id, CATEGORY_TITLE, 0, t( ctx.translate, M.title_number_improve, [] ) );
	}

	function titleHasPowerWord( ctx ) {
		var id = 'title_has_power_word';
		if ( '' === ctx.title ) {
			return na( id, CATEGORY_TITLE, t( ctx.translate, M.default_na, [] ) );
		}
		return hasAny( KeywordMatcher.words( ctx.title, ctx.stripAccents ), POWER_WORDS )
			? pass( id, CATEGORY_TITLE, t( ctx.translate, M.title_power_pass, [] ) )
			: improve( id, CATEGORY_TITLE, 0, t( ctx.translate, M.title_power_improve, [] ) );
	}

	function titleSentiment( ctx ) {
		var id = 'title_sentiment';
		if ( '' === ctx.title ) {
			return na( id, CATEGORY_TITLE, t( ctx.translate, M.default_na, [] ) );
		}
		var list = KeywordMatcher.words( ctx.title, ctx.stripAccents );
		return ( hasAny( list, POSITIVE_WORDS ) || hasAny( list, NEGATIVE_WORDS ) )
			? pass( id, CATEGORY_TITLE, t( ctx.translate, M.title_sentiment_pass, [] ) )
			: improve( id, CATEGORY_TITLE, 0, t( ctx.translate, M.title_sentiment_improve, [] ) );
	}

	function shortParagraphs( ctx ) {
		var id = 'short_paragraphs';
		if ( 0 === ctx.paragraphs.length ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.default_na, [] ) );
		}
		return TextStats.longestParagraph( ctx.paragraphs ) <= 120
			? pass( id, CATEGORY_READABILITY, t( ctx.translate, M.short_paragraphs_pass, [] ) )
			: improve( id, CATEGORY_READABILITY, 0, t( ctx.translate, M.short_paragraphs_improve, [] ) );
	}

	function sentenceLength( ctx ) {
		var id = 'sentence_length';
		if ( 0 === ctx.sentences.length ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.default_na, [] ) );
		}
		var ratio = TextStats.longSentenceRatio( ctx.sentences, 20 );
		var message = t( ctx.translate, M.sentence_length, [ AnalysisFormat.numberFormat( ratio * 100, 0 ) ] );
		return ratio <= 0.25 ? pass( id, CATEGORY_READABILITY, message ) : improve( id, CATEGORY_READABILITY, 0, message );
	}

	function subheadingDistribution( ctx ) {
		var id = 'subheading_distribution';
		var count = ctx.words.length;
		if ( 0 === count ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.default_na, [] ) );
		}
		var subs = ctx.headings.filter( function ( heading ) { return heading.level >= 2; } ).length;
		if ( 0 === subs ) {
			if ( count <= 300 ) {
				return na( id, CATEGORY_READABILITY, t( ctx.translate, M.short_subheading_optional, [] ) );
			}
			return improve( id, CATEGORY_READABILITY, 0, t( ctx.translate, M.subheading_no_sub, [] ) );
		}
		return Math.ceil( count / subs ) <= 300
			? pass( id, CATEGORY_READABILITY, t( ctx.translate, M.subheading_pass, [] ) )
			: improve( id, CATEGORY_READABILITY, 0, t( ctx.translate, M.subheading_gap, [] ) );
	}

	function consecutiveSentences( ctx ) {
		var id = 'consecutive_sentences';
		if ( ctx.sentences.length < 3 ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.default_na, [] ) );
		}
		return TextStats.longestRepeatedOpening( ctx.sentences ) < 3
			? pass( id, CATEGORY_READABILITY, t( ctx.translate, M.consecutive_pass, [] ) )
			: improve( id, CATEGORY_READABILITY, 0, t( ctx.translate, M.consecutive_improve, [] ) );
	}

	function passiveVoice( ctx ) {
		var id = 'passive_voice';
		if ( 0 === ctx.sentences.length ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.default_na, [] ) );
		}
		var passive = 0;
		for ( var i = 0; i < ctx.sentences.length; i++ ) {
			if ( PASSIVE_PATTERN.test( ctx.sentences[ i ] ) ) {
				passive++;
			}
		}
		var ratio = passive / ctx.sentences.length;
		var message = t( ctx.translate, M.passive_voice, [ AnalysisFormat.numberFormat( ratio * 100, 0 ) ] );
		return ratio <= 0.1 ? pass( id, CATEGORY_READABILITY, message ) : improve( id, CATEGORY_READABILITY, 0, message );
	}

	function transitionWords( ctx ) {
		var id = 'transition_words';
		if ( 0 === ctx.sentences.length ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.default_na, [] ) );
		}
		if ( ctx.words.length <= 200 ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.short_transition_optional, [] ) );
		}
		var found = 0;
		for ( var i = 0; i < ctx.sentences.length; i++ ) {
			var padded = ' ' + KeywordMatcher.normalize( ctx.sentences[ i ], ctx.stripAccents ) + ' ';
			for ( var j = 0; j < TRANSITION_WORDS.length; j++ ) {
				if ( padded.indexOf( ' ' + TRANSITION_WORDS[ j ] + ' ' ) !== -1 ) {
					found++;
					break;
				}
			}
		}
		var ratio = found / ctx.sentences.length;
		var message = t( ctx.translate, M.transition_words, [ AnalysisFormat.numberFormat( ratio * 100, 0 ) ] );
		return ratio >= 0.3 ? pass( id, CATEGORY_READABILITY, message ) : improve( id, CATEGORY_READABILITY, 0, message );
	}

	function imageAltQuality( ctx ) {
		var id = 'image_alt_quality';
		var alts = ctx.alts.slice();
		if ( '' !== ctx.featuredAlt ) {
			alts.push( ctx.featuredAlt );
		}
		if ( 0 === alts.length ) {
			return na( id, CATEGORY_SEO, t( ctx.translate, M.image_alt_na, [] ) );
		}
		var missing = 0;
		var stuffed = 0;
		for ( var i = 0; i < alts.length; i++ ) {
			if ( '' === TextStats.phpTrim( alts[ i ] ) ) {
				missing++;
			} else if ( KeywordMatcher.words( alts[ i ], ctx.stripAccents ).length > 20 ) {
				stuffed++;
			}
		}
		if ( 0 === missing && 0 === stuffed ) {
			return pass( id, CATEGORY_SEO, t( ctx.translate, M.image_alt_quality_pass, [] ) );
		}
		return improve( id, CATEGORY_SEO, 0, t( ctx.translate, M.image_alt_quality_improve, [ missing, stuffed ] ) );
	}

	function media( ctx ) {
		var id = 'media';
		var images = ctx.media.images;
		var videos = ctx.media.videos;
		if ( 0 === images && 0 === videos ) {
			return improve( id, CATEGORY_READABILITY, 0, t( ctx.translate, M.media_none, [] ) );
		}
		var imgScore = images >= 4 ? 6 : ( 3 === images ? 4 : ( 2 === images ? 2 : ( 1 === images ? 1 : 0 ) ) );
		var earned = Math.min( 6, imgScore + Math.min( 2, videos ) );
		if ( earned >= 6 ) {
			return pass( id, CATEGORY_READABILITY, t( ctx.translate, M.media_pass, [] ) );
		}
		return improve( id, CATEGORY_READABILITY, earned, t( ctx.translate, M.media_improve, [] ) );
	}

	function singleH1( ctx ) {
		var id = 'single_h1';
		var count = ctx.headings.filter( function ( heading ) { return heading.level === 1; } ).length;
		return count < 2
			? pass( id, CATEGORY_READABILITY, t( ctx.translate, M.single_h1_pass, [] ) )
			: improve( id, CATEGORY_READABILITY, 0, t( ctx.translate, M.single_h1_improve, [] ) );
	}

	function tableOfContents( ctx ) {
		var id = 'table_of_contents';
		if ( ctx.toc ) {
			return pass( id, CATEGORY_READABILITY, t( ctx.translate, M.toc_pass, [] ) );
		}
		if ( ctx.words.length < 1500 ) {
			return na( id, CATEGORY_READABILITY, t( ctx.translate, M.short_toc_optional, [] ) );
		}
		return improve( id, CATEGORY_READABILITY, 0, t( ctx.translate, M.toc_improve, [] ) );
	}

	function textPresent( ctx ) {
		var id = 'text_present';
		return codePoints( ctx.text ).length >= 50
			? pass( id, CATEGORY_READABILITY, t( ctx.translate, M.text_present_pass, [] ) )
			: problem( id, CATEGORY_READABILITY, t( ctx.translate, M.text_present_problem, [] ) );
	}

	function contentChecks( ctx ) {
		return [
			contentLength( ctx ),
			slugLength( ctx ),
			internalLinks( ctx ),
			externalLinks( ctx ),
			followedExternal( ctx ),
			genericAnchorText( ctx ),
			titleHasNumber( ctx ),
			titleHasPowerWord( ctx ),
			titleSentiment( ctx ),
			shortParagraphs( ctx ),
			sentenceLength( ctx ),
			subheadingDistribution( ctx ),
			consecutiveSentences( ctx ),
			passiveVoice( ctx ),
			transitionWords( ctx ),
			imageAltQuality( ctx ),
			media( ctx ),
			singleH1( ctx ),
			tableOfContents( ctx ),
			textPresent( ctx )
		];
	}

	function extract( input ) {
		var html = input.html == null ? '' : String( input.html );
		var text = TextStats.plainText( html );
		return {
			html: html,
			text: text,
			words: TextStats.words( text ),
			sentences: TextStats.sentences( text ),
			paragraphs: TextStats.paragraphs( text ),
			headings: TextStats.headings( html ),
			alts: TextStats.imageAlts( html ),
			links: TextStats.links( html ),
			media: TextStats.media( html ),
			toc: TextStats.hasToc( html )
		};
	}

	function contextFor( input, extracted, options, keyword ) {
		return {
			html: extracted.html,
			text: extracted.text,
			words: extracted.words,
			sentences: extracted.sentences,
			paragraphs: extracted.paragraphs,
			headings: extracted.headings,
			alts: extracted.alts,
			links: extracted.links,
			media: extracted.media,
			toc: extracted.toc,
			title: TextStats.phpTrim( input.title == null ? '' : String( input.title ) ),
			description: TextStats.phpTrim( input.description == null ? '' : String( input.description ) ),
			slug: TextStats.phpTrim( input.slug == null ? '' : String( input.slug ) ),
			siteUrl: input.site_url == null ? '' : String( input.site_url ),
			featuredAlt: input.featured_alt == null ? '' : String( input.featured_alt ),
			usedKeywords: Array.isArray( input.used_keywords ) ? input.used_keywords : null,
			linkStats: classifyLinks( extracted.links, input.site_url == null ? '' : String( input.site_url ) ),
			anchorStats: genericAnchorCount( extracted.links, false !== options.stripAccents ),
			keyword: keyword,
			translate: 'function' === typeof options.translate ? options.translate : identity,
			stripAccents: false !== options.stripAccents
		};
	}

	function analyze( input, options ) {
		var data = input || {};
		var opts = options || {};
		var keywords = normalizeKeywords( data.keywords, opts.stripAccents );
		if ( 0 === keywords.length ) {
			return { score: 0, band: BAND_PROBLEM, checks: [], keywords: [] };
		}
		var extracted = extract( data );
		var ctx = contextFor( data, extracted, opts, keywords[ 0 ] );
		var primaryChecks = sharedChecks( ctx ).concat( keywordChecks( ctx ) ).concat( contentChecks( ctx ) );
		var primaryScore = scoreOf( primaryChecks );
		var entries = [ { keyword: keywords[ 0 ], primary: true, score: primaryScore, band: bandOf( primaryScore ), checks: primaryChecks } ];
		for ( var i = 1; i < keywords.length; i++ ) {
			var sharedCtx = contextFor( data, extracted, opts, keywords[ i ] );
			var list = sharedChecks( sharedCtx );
			var value = scoreOf( list );
			entries.push( { keyword: keywords[ i ], primary: false, score: value, band: bandOf( value ), checks: list } );
		}
		return { score: primaryScore, band: bandOf( primaryScore ), checks: primaryChecks, keywords: entries };
	}

	return {
		RULES_VERSION: RULES_VERSION,
		WEIGHTS: WEIGHTS,
		analyze: analyze
	};
} ) );
