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

function body( keyword ) {
	return '<h1>' + keyword + '</h1><p>This paragraph talks about ' + keyword + ' and why it matters to a reader today. However, the details still need care and a clear example, because a reader wants context.</p><h2>More about ' + keyword + '</h2><p><img src="a.jpg" alt="' + keyword + '"> However, the details still need care and a clear example, because a reader wants context.</p><p>See <a href="/inner-page">our guide</a> and <a href="https://example.org/source" rel="follow">the source</a>.</p>';
}

function input( overrides ) {
	return Object.assign( {
		html: body( 'red apples' ),
		title: 'Red apples: a complete guide',
		description: 'A guide to red apples and how to pick the best ones.',
		slug: 'red-apples-guide',
		keywords: [ 'red apples' ],
		site_url: 'https://example.com'
	}, overrides || {} );
}

function longBody( keyword, words ) {
	const filler = 'However, the details still need care and a clear example, because a reader wants context.';
	let html = body( keyword );
	const count = () => html.replace( /<[^>]*>/g, ' ' ).trim().split( /\s+/ ).filter( Boolean ).length;
	while ( count() < words ) {
		html += '<p>' + filler + '</p>';
	}
	return html;
}

test( 'content_length reaches pass only at 2500 words and improves with partial credit', () => {
	const full = checkById( Analyzer.analyze( input( { html: longBody( 'red apples', 2500 ) } ) ), 'content_length' );
	assert.equal( full.status, 'pass' );
	assert.equal( full.earned, 8 );
	assert.ok( full.message.indexOf( 'completeness signal' ) !== -1 );
	assert.equal( checkById( Analyzer.analyze( input( { html: longBody( 'red apples', 1200 ) } ) ), 'content_length' ).earned, 3 );
} );

test( 'slug_length passes at 75 characters and improves at 76', () => {
	assert.equal( checkById( Analyzer.analyze( input( { slug: 'a'.repeat( 75 ) } ) ), 'slug_length' ).status, 'pass' );
	const at76 = checkById( Analyzer.analyze( input( { slug: 'a'.repeat( 76 ) } ) ), 'slug_length' );
	assert.equal( at76.status, 'improve' );
	assert.ok( at76.message.indexOf( '76 characters' ) !== -1 );
} );

test( 'link checks classify internal, external and followed links', () => {
	const result = Analyzer.analyze( input() );
	assert.equal( checkById( result, 'internal_links' ).status, 'pass' );
	assert.equal( checkById( result, 'external_links' ).status, 'pass' );
	assert.equal( checkById( result, 'followed_external' ).status, 'pass' );
	const nofollow = Analyzer.analyze( input( { html: '<p><a href="https://example.org" rel="nofollow">x</a></p>' } ) );
	assert.equal( checkById( nofollow, 'followed_external' ).status, 'improve' );
	const none = Analyzer.analyze( input( { html: '<p>no links at all in this body</p>' } ) );
	assert.equal( checkById( none, 'followed_external' ).status, 'na' );
	assert.equal( checkById( none, 'generic_anchor_text' ).status, 'na' );
} );

test( 'generic_anchor_text flags a click here anchor and a bare URL and passes a descriptive one', () => {
	const flagged = checkById( Analyzer.analyze( input( { html: '<p><a href="/x">click here</a></p>' } ) ), 'generic_anchor_text' );
	assert.equal( flagged.status, 'improve' );
	assert.ok( flagged.message.indexOf( 'describe the destination' ) !== -1 );
	assert.equal( checkById( Analyzer.analyze( input( { html: '<p><a href="/x">https://example.org/a</a></p>' } ) ), 'generic_anchor_text' ).status, 'improve' );
	assert.equal( checkById( Analyzer.analyze( input( { html: '<p><a href="/x">our full red apples guide</a></p>' } ) ), 'generic_anchor_text' ).status, 'pass' );
} );

test( 'image_alt_quality detects a missing alt and a stuffed alt', () => {
	const missing = checkById( Analyzer.analyze( input( { html: '<img src="a" alt=""><img src="b" alt="red apples">' } ) ), 'image_alt_quality' );
	assert.equal( missing.status, 'improve' );
	assert.ok( missing.message.indexOf( 'no alt text' ) !== -1 );
	const stuffed = checkById( Analyzer.analyze( input( { html: '<img src="a" alt="' + new Array( 25 ).fill( 'red' ).join( ' ' ) + '">' } ) ), 'image_alt_quality' );
	assert.equal( stuffed.status, 'improve' );
	assert.equal( checkById( Analyzer.analyze( input( { html: '<img src="a" alt="a bowl of red apples">' } ) ), 'image_alt_quality' ).status, 'pass' );
} );

test( 'media gives partial credit by image and video count and caps at six', () => {
	assert.equal( checkById( Analyzer.analyze( input( { html: '<p>text</p>' } ) ), 'media' ).status, 'improve' );
	assert.equal( checkById( Analyzer.analyze( input( { html: '<img src="a"><img src="b">' } ) ), 'media' ).earned, 2 );
	assert.equal( checkById( Analyzer.analyze( input( { html: '<img src="a"><img src="b"><img src="c"><img src="d"><video></video>' } ) ), 'media' ).status, 'pass' );
} );

test( 'readability checks follow their preconditions', () => {
	assert.equal( checkById( Analyzer.analyze( input( { html: '<p>' + new Array( 130 ).fill( 'word' ).join( ' ' ) + '</p>' } ) ), 'short_paragraphs' ).status, 'improve' );
	assert.equal( checkById( Analyzer.analyze( input( { html: '<p>Short. Tiny.</p>' } ) ), 'consecutive_sentences' ).status, 'na' );
	const passive = checkById( Analyzer.analyze( input( { html: '<p>The ball was thrown by the boy. It was seen by all. More words here now.</p>' } ) ), 'passive_voice' );
	assert.ok( [ 'pass', 'improve' ].indexOf( passive.status ) !== -1 );
} );

test( 'single_h1, table_of_contents and text_present follow their rules', () => {
	assert.equal( checkById( Analyzer.analyze( input( { html: '<h1>a</h1><h1>b</h1><p>body text here</p>' } ) ), 'single_h1' ).status, 'improve' );
	assert.equal( checkById( Analyzer.analyze( input( { html: longBody( 'red apples', 1600 ) + '<!-- wp:rankkernel/toc /-->' } ) ), 'table_of_contents' ).status, 'pass' );
	assert.equal( checkById( Analyzer.analyze( input( { html: '<p>short</p>' } ) ), 'text_present' ).status, 'problem' );
} );

test( 'title checks pass and improve as specified', () => {
	assert.equal( checkById( Analyzer.analyze( input( { title: '10 red apples tips' } ) ), 'title_has_number' ).status, 'pass' );
	assert.equal( checkById( Analyzer.analyze( input( { title: 'Nothing here' } ) ), 'title_has_power_word' ).status, 'improve' );
	assert.equal( checkById( Analyzer.analyze( input( { title: 'A good guide to apples' } ) ), 'title_sentiment' ).status, 'pass' );
} );

test( 'the final primary checklist is 31 checks in the spec order', () => {
	const result = Analyzer.analyze( input() );
	assert.equal( result.checks.length, 31 );
	assert.deepEqual( result.checks.map( ( check ) => check.id ), [
		'keyword_in_content', 'keyword_in_subheading', 'keyword_density', 'keyword_distribution',
		'keyword_in_title', 'keyword_in_description', 'keyword_in_slug', 'keyword_in_opening',
		'keyword_in_image_alt', 'title_starts_with_keyword', 'keyword_uniqueness',
		'content_length', 'slug_length', 'internal_links', 'external_links', 'followed_external',
		'generic_anchor_text', 'title_has_number', 'title_has_power_word', 'title_sentiment',
		'short_paragraphs', 'sentence_length', 'subheading_distribution', 'consecutive_sentences',
		'passive_voice', 'transition_words', 'image_alt_quality', 'media', 'single_h1',
		'table_of_contents', 'text_present'
	] );
} );

test( 'the score rounds like PHP where the percentage lands just under a half point', () => {
	const result = Analyzer.analyze( {
		keywords: [ 'red apples' ],
		html: '<p>Red apples are a favourite fruit for many careful readers today.</p><p>The growing season shapes the flavour of every single harvest here.</p><h2>More about red apples</h2><p>Garden tools help the grower through the long and busy spring days.</p><p>A wooden crate keeps the fruit safe and cool inside the shed.</p><img src="a.jpg" alt="red apples photo one"><p>The market stall opens early on a bright and busy morning.</p><img src="b.jpg" alt="red apples photo two"><p>A basket of red apples sells well at the local fair.</p><img src="c.jpg" alt="red apples photo three"><p>The kitchen table holds the recipe cards for the baker today.</p><img src="d.jpg" alt="red apples photo four"><p>A sharp knife makes the daily work quick and easy always.</p><p>The orchard sleeps under a thick layer of soft winter snow.</p><p>See <a href="/orchard-guide">the orchard guide</a></p><p>A new season of fruit starts again in the coming year.</p>',
		title: '10 best garden tools for spring',
		description: 'A guide to red apples and how to pick the best ones.',
		slug: 'red-apples-guide',
		site_url: 'https://example.com'
	} );
	const scored = result.checks.filter( ( check ) => check.status !== 'na' );
	const earned = scored.reduce( ( sum, check ) => sum + check.earned, 0 );
	const applicable = scored.reduce( ( sum, check ) => sum + check.weight, 0 );
	assert.equal( earned + '/' + applicable, '69/120' );
	assert.equal( result.score, 58 );
	assert.equal( result.band, 'improve' );
} );
