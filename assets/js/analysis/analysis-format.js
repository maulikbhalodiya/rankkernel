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

	// The translator falls back to the raw string when wp.i18n is absent, which keeps
	// the module loadable in Node. Each message literal is translated in the table
	// below, because the WordPress extractor only reads literal arguments and the
	// runtime hands the template to the injected translator as a variable.
	var __ = ( 'undefined' !== typeof window && window.wp && window.wp.i18n && 'function' === typeof window.wp.i18n.__ )
		? window.wp.i18n.__
		: function ( text ) { return text; };

	var MESSAGES = {
		default_na: __( 'Not applicable yet.', 'rankkernel' ),
		title_na: __( 'Add an SEO title to check this.', 'rankkernel' ),
		description_na: __( 'Add a meta description to check this.', 'rankkernel' ),
		subheading_na: __( 'Add a subheading to check this.', 'rankkernel' ),
		image_na: __( 'Add an image or set a featured image to check this.', 'rankkernel' ),
		image_alt_na: __( 'Add an image to check this.', 'rankkernel' ),
		link_na: __( 'Add a link to check this.', 'rankkernel' ),
		outbound_na: __( 'There are no outbound links to check.', 'rankkernel' ),
		short_subheading_optional: __( 'Short enough that subheadings are optional.', 'rankkernel' ),
		short_transition_optional: __( 'Short enough that transition words are optional.', 'rankkernel' ),
		short_toc_optional: __( 'Short enough that a table of contents is optional.', 'rankkernel' ),

		/* translators: %s: the focus keyword. */
		keyword_in_title_pass: __( 'Your keyword "%s" appears in the SEO title.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_title_problem: __( 'Your keyword "%s" does not appear in the SEO title.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_description_pass: __( 'Your keyword "%s" appears in the meta description. The description is a display and click through signal, not a ranking factor, which is Google guidance.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_description_problem: __( 'Your keyword "%s" does not appear in the meta description. The description is a display and click through signal, not a ranking factor.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_slug_pass: __( 'Your keyword "%s" appears in the URL.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_slug_problem: __( 'Your keyword "%s" does not appear in the URL.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_opening_pass: __( 'Your keyword "%s" appears near the beginning.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_opening_problem: __( 'Your keyword "%s" does not appear near the beginning.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_content_pass: __( 'Your keyword "%s" appears in the content.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_content_problem: __( 'Your keyword "%s" does not appear in the content.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_subheading_pass: __( 'Your keyword "%s" appears in a subheading.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_subheading_problem: __( 'Your keyword "%s" does not appear in a subheading.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_image_alt_pass: __( 'Your keyword "%s" appears in an image alt.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		keyword_in_image_alt_problem: __( 'Your keyword "%s" does not appear in an image alt.', 'rankkernel' ),

		/* translators: %1$d: number of keyword occurrences, %2$s: keyword density as a percentage. */
		density_count: __( 'Your keyword appears %1$d time(s), a density of %2$s percent.', 'rankkernel' ),
		density_pass_suffix: __( 'Google states there is no ideal keyword density, so a lower figure is fine.', 'rankkernel' ),
		density_improve_suffix: __( 'Above 2.5 percent the repetition can read as unnatural, which Google defines as keyword stuffing.', 'rankkernel' ),
		density_variation: __( 'Your keyword appears in a natural variation, which is fine.', 'rankkernel' ),
		density_absent: __( 'Your keyword does not appear in the content yet.', 'rankkernel' ),

		distribution_pass: __( 'Your keyword is spread evenly through the content.', 'rankkernel' ),
		distribution_improve: __( 'Your keyword is used, but a few sections never mention it.', 'rankkernel' ),
		distribution_problem: __( 'Large parts of the content never mention your keyword.', 'rankkernel' ),
		uniqueness_pass: __( 'This keyword is not used elsewhere.', 'rankkernel' ),
		uniqueness_improve: __( 'Other content already targets this keyword.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		title_start_pass: __( 'Your keyword "%s" is near the start of the title.', 'rankkernel' ),
		/* translators: %s: the focus keyword. */
		title_start_problem: __( 'Your keyword "%s" is not near the start of the title.', 'rankkernel' ),

		/* translators: %d: content word count. */
		content_length: __( 'The content is %d words long. Google states there is no ideal word count, so treat this as a completeness signal rather than a length requirement.', 'rankkernel' ),
		/* translators: %d: URL character count. */
		slug_length: __( 'The URL is %d characters long.', 'rankkernel' ),
		internal_links_pass: __( 'The content links to another page on this site.', 'rankkernel' ),
		internal_links_problem: __( 'No internal links found. Link to related content.', 'rankkernel' ),
		external_links_pass: __( 'The content links out to an external source.', 'rankkernel' ),
		external_links_problem: __( 'No outbound links found. Cite a source or reference.', 'rankkernel' ),
		followed_pass: __( 'At least one outbound link is followed.', 'rankkernel' ),
		followed_improve: __( 'Every outbound link is nofollow.', 'rankkernel' ),
		generic_pass: __( 'Every link explains where it goes. Anchor text should describe the destination, which is Google guidance.', 'rankkernel' ),
		/* translators: %d: number of links using generic anchor text. */
		generic_improve: __( '%d link(s) use generic anchor text such as click here or a bare URL. Anchor text should describe the destination, which is Google guidance.', 'rankkernel' ),

		title_number_pass: __( 'The title contains a number.', 'rankkernel' ),
		title_number_improve: __( 'Consider a number in the title.', 'rankkernel' ),
		title_power_pass: __( 'The title contains a power word.', 'rankkernel' ),
		title_power_improve: __( 'Consider a power word in the title.', 'rankkernel' ),
		title_sentiment_pass: __( 'The title carries a positive or negative sentiment.', 'rankkernel' ),
		title_sentiment_improve: __( 'The title reads neutral.', 'rankkernel' ),

		short_paragraphs_pass: __( 'The paragraphs are short enough to scan.', 'rankkernel' ),
		short_paragraphs_improve: __( 'At least one paragraph is long. Short paragraphs are easier to read.', 'rankkernel' ),
		/* translators: %s: percentage of sentences longer than 20 words. */
		sentence_length: __( '%s percent of the sentences are longer than 20 words.', 'rankkernel' ),
		subheading_pass: __( 'The content is broken up by subheadings.', 'rankkernel' ),
		subheading_no_sub: __( 'No subheadings found. Consider splitting the content.', 'rankkernel' ),
		subheading_gap: __( 'A long stretch has no subheading. Add one to break it up.', 'rankkernel' ),
		consecutive_pass: __( 'No run of sentences opens with the same word.', 'rankkernel' ),
		consecutive_improve: __( 'Several sentences in a row start with the same word. Vary the openings.', 'rankkernel' ),
		/* translators: %s: percentage of sentences read as passive voice. */
		passive_voice: __( '%s percent of the sentences read as passive voice.', 'rankkernel' ),
		/* translators: %s: percentage of sentences using a transition word. */
		transition_words: __( '%s percent of the sentences use a transition word.', 'rankkernel' ),

		image_alt_quality_pass: __( 'Every image has descriptive alt text. Alt text is for accessibility and understanding, not a keyword slot, which is Google guidance.', 'rankkernel' ),
		/* translators: %1$d: images without alt text, %2$d: images with stuffed alt text. */
		image_alt_quality_improve: __( '%1$d image(s) have no alt text and %2$d look stuffed. Alt text is for accessibility and understanding, not a keyword slot, which is Google guidance.', 'rankkernel' ),
		media_none: __( 'No images or video found. Media helps a reader stay.', 'rankkernel' ),
		media_pass: __( 'The content includes enough media.', 'rankkernel' ),
		media_improve: __( 'Consider one or two more images or a video.', 'rankkernel' ),
		single_h1_pass: __( 'The content has at most one h1.', 'rankkernel' ),
		single_h1_improve: __( 'More than one h1 found. Keep a single h1 per page.', 'rankkernel' ),
		toc_pass: __( 'The content has a table of contents.', 'rankkernel' ),
		toc_improve: __( 'Long content reads better with a table of contents.', 'rankkernel' ),
		text_present_pass: __( 'The content has enough text to analyse.', 'rankkernel' ),
		text_present_problem: __( 'Add content so the analysis has something to read.', 'rankkernel' )
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
