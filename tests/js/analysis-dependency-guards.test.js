'use strict';

/**
 * Defensive dependency guards for the analysis engine.
 *
 * WordPress registers every engine script with explicit dependencies, so a
 * missing sibling is a failed deploy or a dequeued handle, not a normal path.
 * These tests run the UMD factory in a vm with the sibling missing, which is
 * the only way to hand the factory `undefined`.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );
const realMatcher = require( '../../assets/js/analysis/keyword-matcher.js' );
const realStats = require( '../../assets/js/analysis/text-stats.js' );
const realAccents = require( '../../assets/js/analysis/accents.js' );
const realFormat = require( '../../assets/js/analysis/analysis-format.js' );

function source( relative ) {
	return readFileSync( path.join( root, relative ), 'utf8' );
}

function run( relative, sandbox ) {
	vm.runInNewContext( source( relative ), sandbox );
	return sandbox;
}

function checkById( result, id ) {
	return result.checks.filter( ( check ) => check.id === id )[ 0 ] || null;
}

test( 'analyzer loads and scores when AnalysisFormat is undefined', () => {
	const sandbox = { RankKernelAnalysis: { KeywordMatcher: realMatcher, TextStats: realStats } };
	run( 'assets/js/analysis/analyzer.js', sandbox );

	const Analyzer = sandbox.RankKernelAnalysis.Analyzer;

	assert.equal( typeof Analyzer, 'object' );
	const result = Analyzer.analyze( {
		keywords: [ 'red apples' ],
		html: '<p>We sell red apples and more red apples here.</p>',
		title: 'Red apples guide',
		site_url: 'https://example.com'
	} );

	assert.equal( typeof result.score, 'number' );
	assert.equal( result.checks.length, 31 );
	assert.equal( checkById( result, 'keyword_in_content' ).status, 'pass' );
	result.checks.forEach( ( check ) => assert.equal( typeof check.message, 'string' ) );
} );

test( 'keyword matcher normalizes and matches when TextStats and Accents are undefined', () => {
	const sandbox = run( 'assets/js/analysis/keyword-matcher.js', { RankKernelAnalysis: {} } );
	const Matcher = sandbox.RankKernelAnalysis.KeywordMatcher;

	assert.equal( Matcher.normalize( '  Caf\u00e9,  Test!  ' ), 'caf\u00e9 test' );
	assert.deepEqual( Array.from( Matcher.words( '  Caf\u00e9,  Test!  ' ) ), [ 'caf\u00e9', 'test' ] );
	assert.equal( Matcher.contains( 'We sell red apples here.', 'red apples' ), true );
	assert.equal( Matcher.contains( 'We sell redapples here.', 'red apples' ), false );
	// The sentence reordering strategy needs the sibling splitter, so without
	// the sibling the direct phrase result still answers and the strategy is
	// skipped rather than throwing.
	assert.equal( Matcher.contains( 'Your cat may like this food.', 'cat food' ), false );
} );

test( 'analyzer scores through a keyword matcher whose siblings are undefined', () => {
	const sandbox = { RankKernelAnalysis: { TextStats: realStats, AnalysisFormat: realFormat } };
	run( 'assets/js/analysis/keyword-matcher.js', sandbox );
	run( 'assets/js/analysis/analyzer.js', sandbox );

	const result = sandbox.RankKernelAnalysis.Analyzer.analyze( {
		keywords: [ 'red apples' ],
		html: '<p>We sell red apples here.</p>',
		title: 'Red apples guide',
		site_url: 'https://example.com'
	} );

	assert.equal( result.checks.length, 31 );
	assert.equal( checkById( result, 'keyword_in_content' ).status, 'pass' );
} );

test( 'the normalize cache computes each key once through the guarded helpers', () => {
	let trimCalls = 0;
	let accentCalls = 0;
	const countingStats = Object.assign( {}, realStats, {
		phpTrim( value ) {
			trimCalls++;
			return realStats.phpTrim( value );
		}
	} );
	const countingAccents = {
		removeAccents( value ) {
			accentCalls++;
			return realAccents.removeAccents( value );
		}
	};
	const sandbox = run( 'assets/js/analysis/keyword-matcher.js', {
		RankKernelAnalysis: { TextStats: countingStats, Accents: countingAccents }
	} );
	const Matcher = sandbox.RankKernelAnalysis.KeywordMatcher;

	Matcher.beginNormalizeCache();
	assert.equal( Matcher.normalize( 'Caf\u00e9,  Test!' ), 'cafe test' );
	assert.equal( Matcher.normalize( 'Caf\u00e9,  Test!' ), 'cafe test' );
	assert.equal( trimCalls, 1 );
	assert.equal( accentCalls, 1 );

	Matcher.endNormalizeCache();
	assert.equal( Matcher.normalize( 'Caf\u00e9,  Test!' ), 'cafe test' );
	assert.equal( trimCalls, 2 );
	assert.equal( accentCalls, 2 );
} );
