'use strict';

/**
 * Input cap for the analysis engine.
 *
 * A very long post must not be scored in full on every debounced keystroke.
 * The engine caps the prose at MAX_ANALYZE_SENTENCES, following the
 * MAX_VALIDATE_LINES idiom in instant-indexing-admin.js, and scores the
 * leading sentences only. These tests pin the ceiling value, the untouched
 * behaviour below it and the deliberate truncation above it.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const Analyzer = require( '../../assets/js/analysis/analyzer.js' );

const root = path.resolve( __dirname, '..', '..' );

function checkById( result, id ) {
	return result.checks.filter( ( check ) => check.id === id )[ 0 ] || null;
}

function sentenceDoc( count, keywordIndex ) {
	const parts = [];
	for ( let i = 0; i < count; i++ ) {
		parts.push( i === keywordIndex ? 'red apples fill.' : 'word word word.' );
	}
	return '<p>' + parts.join( ' ' ) + '</p>';
}

function input( html ) {
	return {
		keywords: [ 'red apples' ],
		html: html,
		title: 'Red apples guide',
		description: 'All about red apples.',
		slug: 'red-apples-guide',
		site_url: 'https://example.com'
	};
}

test( 'the analysis cap uses the MAX_ANALYZE_SENTENCES ceiling and matches MAX_VALIDATE_LINES', () => {
	const source = readFileSync( path.join( root, 'assets', 'js', 'analysis', 'analyzer.js' ), 'utf8' );
	const declared = /var MAX_ANALYZE_SENTENCES = (\d+);/.exec( source );
	const houseSource = readFileSync( path.join( root, 'assets', 'js', 'instant-indexing-admin.js' ), 'utf8' );
	const house = /var MAX_VALIDATE_LINES = (\d+);/.exec( houseSource );

	assert.equal( Number( declared[ 1 ] ), 1000 );
	assert.equal( Number( house[ 1 ] ), 1000 );
	assert.equal( Analyzer.MAX_ANALYZE_SENTENCES, Number( declared[ 1 ] ) );
} );

test( 'a document at the cap is scored in full', () => {
	const result = Analyzer.analyze( input( sentenceDoc( 1000, 0 ) ) );
	const check = checkById( result, 'content_length' );

	assert.equal( check.status, 'pass' );
	assert.equal( check.earned, 8 );
	assert.ok( check.message.indexOf( '3000 words' ) !== -1, check.message );
	assert.equal( checkById( result, 'keyword_in_content' ).status, 'pass' );
} );

test( 'a document beyond the cap is truncated to the leading sentences', () => {
	const result = Analyzer.analyze( input( sentenceDoc( 1500, 0 ) ) );
	const check = checkById( result, 'content_length' );

	assert.equal( check.status, 'pass' );
	assert.ok( check.message.indexOf( '3000 words' ) !== -1, check.message );
	assert.equal( check.message.indexOf( '4500' ), -1 );
} );

test( 'a keyword used only past the cap is outside the scored document', () => {
	const beyond = Analyzer.analyze( input( sentenceDoc( 1500, 1400 ) ) );
	assert.equal( checkById( beyond, 'keyword_in_content' ).status, 'problem' );

	const inside = Analyzer.analyze( input( sentenceDoc( 1500, 900 ) ) );
	assert.equal( checkById( inside, 'keyword_in_content' ).status, 'pass' );
} );
