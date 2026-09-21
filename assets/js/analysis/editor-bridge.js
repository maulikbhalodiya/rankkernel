/**
 * RankKernel analysis engine: editor helpers.
 *
 * Pure module. It maps editor fields onto the engine input and answers a
 * cheap change question. It knows nothing about the DOM or React.
 */
( function ( root, factory ) {
	'use strict';

	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.RankKernelAnalysis = root.RankKernelAnalysis || {};
		root.RankKernelAnalysis.EditorBridge = factory();
	}
}( typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	function text( value ) {
		return 'string' === typeof value ? value : '';
	}

	function buildInput( fields, config ) {
		var source = fields || {};
		var settings = config || {};
		return {
			html: text( source.content ),
			title: text( source.title ),
			description: text( source.description ),
			slug: text( source.slug ),
			keywords: Array.isArray( source.keywords ) ? source.keywords.slice() : [],
			site_url: text( settings.homeUrl ),
			featured_alt: text( settings.featuredAlt )
		};
	}

	function signature( input ) {
		return [
			text( input.html ),
			text( input.title ),
			text( input.description ),
			text( input.slug ),
			Array.isArray( input.keywords ) ? input.keywords.join( '|' ) : '',
			text( input.site_url ),
			text( input.featured_alt )
		].join( '\u0000' );
	}

	function shouldRun( previous, next ) {
		return previous !== next;
	}

	return {
		buildInput: buildInput,
		signature: signature,
		shouldRun: shouldRun
	};
} ) );
