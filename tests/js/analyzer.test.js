'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Analyzer = require( '../../assets/js/analysis/analyzer.js' );

function checkById( result, id ) {
	return result.checks.filter( ( check ) => check.id === id )[ 0 ] || null;
}

test( 'an empty keyword list yields the empty result', () => {
	const result = Analyzer.analyze( { keywords: [], html: '<p>hi</p>' } );
	assert.equal( result.score, 0 );
	assert.equal( result.band, 'problem' );
	assert.deepEqual( result.checks, [] );
	assert.deepEqual( result.keywords, [] );
} );

test( 'the primary checklist has 31 checks in spec order and the top level checks equal keywords[0].checks', () => {
	const result = Analyzer.analyze( { keywords: [ 'red apples' ], html: '<p>red apples and red apples here.</p>', title: 'Red apples guide', description: 'All about red apples.', slug: 'red-apples-guide' } );
	assert.equal( result.checks.length, 31 );
	assert.deepEqual( result.checks, result.keywords[ 0 ].checks );
	assert.equal( result.score, result.keywords[ 0 ].score );
	assert.equal( result.band, result.keywords[ 0 ].band );
	assert.equal( result.checks[ 0 ].id, 'keyword_in_content' );
	assert.equal( result.checks[ 1 ].id, 'keyword_in_subheading' );
	assert.equal( result.checks[ 2 ].id, 'keyword_density' );
	assert.equal( result.checks[ 3 ].id, 'keyword_distribution' );
	assert.equal( result.checks[ 4 ].id, 'keyword_in_title' );
	assert.equal( result.checks[ 10 ].id, 'keyword_uniqueness' );
	assert.equal( result.checks[ 30 ].id, 'text_present' );
} );

test( 'weights sum to 130 and keyword_uniqueness weighs 0', () => {
	const total = Object.keys( Analyzer.WEIGHTS ).reduce( ( sum, id ) => sum + Analyzer.WEIGHTS[ id ], 0 );
	assert.equal( total, 130 );
	assert.equal( Analyzer.WEIGHTS.keyword_uniqueness, 0 );
	assert.equal( Analyzer.RULES_VERSION, 1 );
} );

test( 'an na check earns 0 and keeps its weight', () => {
	const result = Analyzer.analyze( { keywords: [ 'red apples' ], html: '<p>red apples here.</p>', title: '' } );
	const title = checkById( result, 'keyword_in_title' );
	assert.equal( title.status, 'na' );
	assert.equal( title.earned, 0 );
	assert.equal( title.weight, 36 );
} );

test( 'bands map at the exact boundaries', () => {
	const good = Analyzer.analyze( { keywords: [ 'red apples' ], html: '<h1>red apples</h1><p>This paragraph talks about red apples and why it matters to a reader today. However, the details still need care and a clear example, because a reader wants context.</p><h2>More about red apples</h2><p><img src="a.jpg" alt="red apples"> However, the details still need care and a clear example, because a reader wants context.</p><p>See <a href="/inner-page">our guide</a> and <a href="https://example.org/source" rel="follow">the source</a>.</p>', title: 'Red apples: a complete guide', description: 'A guide to red apples and how to pick the best ones.', slug: 'red-apples-guide', site_url: 'https://example.com' } );
	assert.ok( good.score >= 81, 'score ' + good.score );
	assert.equal( good.band, 'good' );
} );

test( 'duplicate keywords are dropped by normalized key and the first spelling is kept', () => {
	const result = Analyzer.analyze( { keywords: [ 'Red Apples', 'red apples', 'RED APPLES' ], html: '<p>red apples</p>' } );
	assert.equal( result.keywords.length, 1 );
	assert.equal( result.keywords[ 0 ].keyword, 'Red Apples' );
} );

test( 'a supporting keyword carries exactly four checks and is scored on those', () => {
	const result = Analyzer.analyze( { keywords: [ 'red apples', 'autumn harvest' ], html: '<p>red apples and an autumn harvest.</p>' } );
	assert.equal( result.keywords.length, 2 );
	const second = result.keywords[ 1 ];
	assert.equal( second.primary, false );
	assert.deepEqual( second.checks.map( ( check ) => check.id ).sort(), [ 'keyword_density', 'keyword_distribution', 'keyword_in_content', 'keyword_in_subheading' ] );
	assert.equal( result.keywords[ 0 ].primary, true );
} );

test( 'keyword_uniqueness is na without used_keywords and improve on a normalized match', () => {
	const na = checkById( Analyzer.analyze( { keywords: [ 'red apples' ], html: '<p>red apples</p>' } ), 'keyword_uniqueness' );
	assert.equal( na.status, 'na' );
	assert.equal( na.weight, 0 );
	const match = checkById( Analyzer.analyze( { keywords: [ 'red apples' ], html: '<p>red apples</p>', used_keywords: [ 'Red  Apples' ] } ), 'keyword_uniqueness' );
	assert.equal( match.status, 'improve' );
	const free = checkById( Analyzer.analyze( { keywords: [ 'red apples' ], html: '<p>red apples</p>', used_keywords: [] } ), 'keyword_uniqueness' );
	assert.equal( free.status, 'pass' );
} );
