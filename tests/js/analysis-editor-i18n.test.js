'use strict';

/**
 * Localisation of the Classic analysis panel.
 *
 * analysis-editor.js is a classic DOM script, so it runs here in a vm sandbox
 * with a minimal fake document and injected timers. The tests pin the three
 * status messages, the two screen reader state labels and the score
 * announcement, plus the raw string fallback when wp.i18n is absent and the
 * literal call sites the WordPress string extractor depends on.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );
const Analyzer = require( '../../assets/js/analysis/analyzer.js' );
const Bridge = require( '../../assets/js/analysis/editor-bridge.js' );
const AnalysisFormat = require( '../../assets/js/analysis/analysis-format.js' );

function source() {
	return readFileSync( path.join( root, 'assets', 'js', 'analysis-editor.js' ), 'utf8' );
}

function fakeNode( props ) {
	return Object.assign( {
		value: '',
		className: '',
		textContent: '',
		children: [],
		attributes: {},
		listeners: {},
		get innerHTML() {
			return this._innerHTML || '';
		},
		set innerHTML( value ) {
			this._innerHTML = value;
			this.children = [];
		},
		addEventListener( type, handler ) {
			this.listeners[ type ] = handler;
		},
		setAttribute( name, value ) {
			this.attributes[ name ] = value;
		},
		getAttribute( name ) {
			return this.attributes[ name ] || null;
		},
		appendChild( child ) {
			this.children.push( child );
			return child;
		}
	}, props || {} );
}

function fakeDocument( elements ) {
	return {
		getElementById( id ) {
			return elements[ id ] || null;
		},
		querySelector( selector ) {
			return elements[ selector ] || null;
		},
		createElement( tag ) {
			return fakeNode( { tagName: tag } );
		}
	};
}

function boot( options ) {
	const settings = options || {};
	const results = fakeNode();
	const score = fakeNode();
	const keyword = fakeNode( { value: undefined === settings.keyword ? 'red apples' : settings.keyword } );
	const elements = {
		'[data-rk-analysis-results="1"]': results,
		'[data-rk-analysis-score="1"]': score,
		'rankkernel-meta-focus-keywords': keyword,
		title: fakeNode( { value: 'Red apples guide' } ),
		post_name: fakeNode( { value: 'red-apples-guide' } ),
		'rankkernel-meta-description': fakeNode( { value: 'All about red apples.' } ),
		content: fakeNode( { value: '<p>red apples here.</p>' } )
	};
	const timeouts = [];
	const sandbox = {
		document: fakeDocument( elements ),
		rankkernelMetaEditor: {
			analysis: { path: '/wp-json/rankkernel/v1/analysis' },
			homeUrl: 'https://example.com'
		},
		RankKernelAnalysis: {
			Analyzer: settings.analyzer || Analyzer,
			EditorBridge: Bridge,
			AnalysisFormat: AnalysisFormat
		},
		wp: settings.wp,
		setTimeout( callback ) {
			timeouts.push( callback );
			return timeouts.length;
		},
		clearTimeout() {},
		setInterval( callback ) {
			return callback && 1;
		},
		clearInterval() {}
	};
	sandbox.window = sandbox;

	vm.runInNewContext( source(), sandbox );

	return {
		sandbox,
		results,
		score,
		timeouts,
		flush() {
			while ( timeouts.length > 0 ) {
				timeouts.shift()();
			}
		}
	};
}

function translatingWp( sprintfCalls ) {
	return {
		i18n: {
			__: ( text, domain ) => domain + ':' + text,
			sprintf( template, ...args ) {
				if ( sprintfCalls ) {
					sprintfCalls.push( { template, args } );
				}

				return template.replace( '%1$d', args[ 0 ] ).replace( '%2$s', args[ 1 ] );
			}
		}
	};
}

function renderAnalyzer( band ) {
	return {
		analyze() {
			return {
				score: 90,
				band: band || 'good',
				checks: [
					{ status: 'pass', message: 'pass message' },
					{ status: 'improve', message: 'improve message' },
					{ status: 'na', message: 'hidden message' }
				]
			};
		}
	};
}

test( 'the classic panel translates the empty keyword message and falls back to the raw string', () => {
	const translated = boot( { keyword: '', wp: translatingWp() } );
	translated.flush();
	assert.equal( translated.results.children[ 0 ].textContent, 'rankkernel:Add a focus keyword to run the content analysis.' );

	const bare = boot( { keyword: '' } );
	bare.flush();
	assert.equal( bare.results.children[ 0 ].textContent, 'Add a focus keyword to run the content analysis.' );
} );

test( 'the classic panel translates the loading message', () => {
	const seen = [];
	let results = null;
	const analyzer = {
		analyze() {
			seen.push( results.children[ 0 ].textContent );
			return { score: 0, band: 'problem', checks: [] };
		}
	};
	const fixture = boot( { analyzer, wp: translatingWp() } );
	results = fixture.results;
	fixture.flush();

	assert.deepEqual( seen, [ 'rankkernel:Analysing the current draft…' ] );
} );

test( 'the classic panel translates the failed run message', () => {
	const analyzer = {
		analyze() {
			throw new Error( 'engine failure' );
		}
	};
	const fixture = boot( { analyzer, wp: translatingWp() } );
	fixture.flush();

	assert.equal( fixture.results.children[ 0 ].textContent, 'rankkernel:The analysis could not be run. Try again.' );
	assert.equal( fixture.results.children[ 0 ].className, 'description rk-analysis-error' );
} );

test( 'the classic panel translates the screen reader pass and needs work labels', () => {
	const fixture = boot( { analyzer: renderAnalyzer(), wp: translatingWp() } );
	fixture.flush();

	const list = fixture.results.children[ 0 ];
	assert.equal( list.children[ 0 ].children[ 1 ].textContent, 'rankkernel:Pass: ' );
	assert.equal( list.children[ 1 ].children[ 1 ].textContent, 'rankkernel:Needs work: ' );
	assert.equal( list.children[ 0 ].children[ 1 ].className, 'screen-reader-text' );
} );

test( 'the classic panel announces the score through one ordered placeholder string', () => {
	const sprintfCalls = [];
	const fixture = boot( { analyzer: renderAnalyzer( 'good' ), wp: translatingWp( sprintfCalls ) } );
	fixture.flush();

	const pill = fixture.score.children[ 0 ];
	assert.deepEqual( sprintfCalls, [
		{ template: 'rankkernel:Score %1$d out of 100, %2$s.', args: [ 90, 'rankkernel:Good' ] }
	] );
	assert.equal( pill.children[ 0 ].textContent, 'rankkernel:Score 90 out of 100, rankkernel:Good.' );
	assert.equal( pill.children[ 0 ].className, 'screen-reader-text' );
} );

test( 'the classic panel renders the raw score announcement when wp.i18n is absent', () => {
	const fixture = boot( { analyzer: renderAnalyzer( 'improve' ) } );
	fixture.flush();

	assert.equal( fixture.score.children[ 0 ].children[ 0 ].textContent, 'Score 90 out of 100, Needs improvement.' );
	assert.equal( fixture.results.children[ 1 ].textContent, 'This score measures your content against a checklist. It does not predict rankings.' );
} );

test( 'every user facing classic panel literal is a direct translation argument', () => {
	const code = source();
	const literals = [];
	const pattern = /__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'rankkernel'\s*\)/g;
	let match;

	while ( ( match = pattern.exec( code ) ) !== null ) {
		literals.push( match[ 1 ] );
	}

	[
		'Good',
		'Needs improvement',
		'Poor',
		'Not analysed',
		'Pass: ',
		'Needs work: ',
		'Score %1$d out of 100, %2$s.',
		'This score measures your content against a checklist. It does not predict rankings.',
		'Add a focus keyword to run the content analysis.',
		'Analysing the current draft…',
		'The analysis could not be run. Try again.'
	].forEach( ( literal ) => {
		assert.ok(
			literals.includes( literal ),
			JSON.stringify( literal ) + ' must be a literal translation argument'
		);
	} );

	assert.match(
		code,
		/\/\* translators: 1: the analysis score from 0 to 100, 2: the score band label\. \*\/\s*\n\s*__\( 'Score %1\$d out of 100, %2\$s\.', 'rankkernel' \)/
	);
} );
