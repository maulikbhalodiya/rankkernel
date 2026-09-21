/**
 * RankKernel analysis engine: number formatting and message templates.
 *
 * Pure module. No dependencies. The translator is injected, so the browser
 * passes wp.i18n.__ and Node passes identity.
 */
( function ( root, factory ) {
	'use strict';

	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.RankKernelAnalysis = root.RankKernelAnalysis || {};
		root.RankKernelAnalysis.AnalysisFormat = factory();
	}
}( typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	var MESSAGES = {
		default_na: 'Not applicable yet.',
		title_na: 'Add an SEO title to check this.',
		description_na: 'Add a meta description to check this.',
		subheading_na: 'Add a subheading to check this.',
		image_na: 'Add an image or set a featured image to check this.',
		image_alt_na: 'Add an image to check this.',
		link_na: 'Add a link to check this.',
		outbound_na: 'There are no outbound links to check.',
		short_subheading_optional: 'Short enough that subheadings are optional.',
		short_transition_optional: 'Short enough that transition words are optional.',
		short_toc_optional: 'Short enough that a table of contents is optional.',

		keyword_in_title_pass: 'Your keyword "%s" appears in the SEO title.',
		keyword_in_title_problem: 'Your keyword "%s" does not appear in the SEO title.',
		keyword_in_description_pass: 'Your keyword "%s" appears in the meta description. The description is a display and click through signal, not a ranking factor, which is Google guidance.',
		keyword_in_description_problem: 'Your keyword "%s" does not appear in the meta description. The description is a display and click through signal, not a ranking factor.',
		keyword_in_slug_pass: 'Your keyword "%s" appears in the URL.',
		keyword_in_slug_problem: 'Your keyword "%s" does not appear in the URL.',
		keyword_in_opening_pass: 'Your keyword "%s" appears near the beginning.',
		keyword_in_opening_problem: 'Your keyword "%s" does not appear near the beginning.',
		keyword_in_content_pass: 'Your keyword "%s" appears in the content.',
		keyword_in_content_problem: 'Your keyword "%s" does not appear in the content.',
		keyword_in_subheading_pass: 'Your keyword "%s" appears in a subheading.',
		keyword_in_subheading_problem: 'Your keyword "%s" does not appear in a subheading.',
		keyword_in_image_alt_pass: 'Your keyword "%s" appears in an image alt.',
		keyword_in_image_alt_problem: 'Your keyword "%s" does not appear in an image alt.',

		density_count: 'Your keyword appears %1$d time(s), a density of %2$s percent.',
		density_pass_suffix: 'Google states there is no ideal keyword density, so a lower figure is fine.',
		density_improve_suffix: 'Above 2.5 percent the repetition can read as unnatural, which Google defines as keyword stuffing.',
		density_variation: 'Your keyword appears in a natural variation, which is fine.',
		density_absent: 'Your keyword does not appear in the content yet.',

		distribution_pass: 'Your keyword is spread evenly through the content.',
		distribution_improve: 'Your keyword is used, but a few sections never mention it.',
		distribution_problem: 'Large parts of the content never mention your keyword.',
		uniqueness_pass: 'This keyword is not used elsewhere.',
		uniqueness_improve: 'Other content already targets this keyword.',
		title_start_pass: 'Your keyword "%s" is near the start of the title.',
		title_start_problem: 'Your keyword "%s" is not near the start of the title.',

		content_length: 'The content is %d words long. Google states there is no ideal word count, so treat this as a completeness signal rather than a length requirement.',
		slug_length: 'The URL is %d characters long.',
		internal_links_pass: 'The content links to another page on this site.',
		internal_links_problem: 'No internal links found. Link to related content.',
		external_links_pass: 'The content links out to an external source.',
		external_links_problem: 'No outbound links found. Cite a source or reference.',
		followed_pass: 'At least one outbound link is followed.',
		followed_improve: 'Every outbound link is nofollow.',
		generic_pass: 'Every link explains where it goes. Anchor text should describe the destination, which is Google guidance.',
		generic_improve: '%d link(s) use generic anchor text such as click here or a bare URL. Anchor text should describe the destination, which is Google guidance.',

		title_number_pass: 'The title contains a number.',
		title_number_improve: 'Consider a number in the title.',
		title_power_pass: 'The title contains a power word.',
		title_power_improve: 'Consider a power word in the title.',
		title_sentiment_pass: 'The title carries a positive or negative sentiment.',
		title_sentiment_improve: 'The title reads neutral.',

		short_paragraphs_pass: 'The paragraphs are short enough to scan.',
		short_paragraphs_improve: 'At least one paragraph is long. Short paragraphs are easier to read.',
		sentence_length: '%s percent of the sentences are longer than 20 words.',
		subheading_pass: 'The content is broken up by subheadings.',
		subheading_no_sub: 'No subheadings found. Consider splitting the content.',
		subheading_gap: 'A long stretch has no subheading. Add one to break it up.',
		consecutive_pass: 'No run of sentences opens with the same word.',
		consecutive_improve: 'Several sentences in a row start with the same word. Vary the openings.',
		passive_voice: '%s percent of the sentences read as passive voice.',
		transition_words: '%s percent of the sentences use a transition word.',

		image_alt_quality_pass: 'Every image has descriptive alt text. Alt text is for accessibility and understanding, not a keyword slot, which is Google guidance.',
		image_alt_quality_improve: '%1$d image(s) have no alt text and %2$d look stuffed. Alt text is for accessibility and understanding, not a keyword slot, which is Google guidance.',
		media_none: 'No images or video found. Media helps a reader stay.',
		media_pass: 'The content includes enough media.',
		media_improve: 'Consider one or two more images or a video.',
		single_h1_pass: 'The content has at most one h1.',
		single_h1_improve: 'More than one h1 found. Keep a single h1 per page.',
		toc_pass: 'The content has a table of contents.',
		toc_improve: 'Long content reads better with a table of contents.',
		text_present_pass: 'The content has enough text to analyse.',
		text_present_problem: 'Add content so the analysis has something to read.'
	};

	function numberFormat( value, decimals ) {
		var places = 'number' === typeof decimals ? decimals : 0;
		var number = Number( value );
		if ( ! isFinite( number ) ) {
			number = 0;
		}
		var factor = Math.pow( 10, places );
		var scaled = number * factor;
		var rounded = scaled < 0 ? -Math.floor( -scaled + 0.5 ) : Math.floor( scaled + 0.5 );
		return ( rounded / factor ).toFixed( places );
	}

	function sprintf( template, args ) {
		var index = 0;
		return String( template ).replace( /%(?:(\d+)\$)?([sd])/g, function ( match, position, type ) {
			var value = position ? args[ parseInt( position, 10 ) - 1 ] : args[ index++ ];
			if ( 'd' === type ) {
				return String( parseInt( value, 10 ) || 0 );
			}
			return value == null ? '' : String( value );
		} );
	}

	function format( template, args, translate ) {
		var render = 'function' === typeof translate ? translate : function ( text ) { return text; };
		return sprintf( render( template ), args || [] );
	}

	return {
		MESSAGES: MESSAGES,
		numberFormat: numberFormat,
		format: format
	};
} ) );
