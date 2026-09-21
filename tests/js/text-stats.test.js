'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const TextStats = require( '../../assets/js/analysis/text-stats.js' );

test( 'decodeEntities decodes named and numeric entities', () => {
	assert.equal( TextStats.decodeEntities( '&amp;&copy;' ), '&\u00a9' );
	assert.equal( TextStats.decodeEntities( '&#x1F600;' ), '\u{1F600}' );
	assert.equal( TextStats.decodeEntities( '&#8212;' ), '\u2014' );
	assert.equal( TextStats.decodeEntities( '&nbsp;' ), '\u00a0' );
	assert.equal( TextStats.decodeEntities( '&unknown;' ), '&unknown;' );
} );

test( 'plainText drops script and style, turns breaks and blocks into newlines and decodes entities', () => {
	const html = '<style>p{x:1}</style><script>var a=1;</script><p>One &amp; two</p><br>Three';
	assert.equal( TextStats.plainText( html ), 'One & two\nThree' );
} );

test( 'phpTrim trims only the PHP trim character set and keeps a non breaking space', () => {
	assert.equal( TextStats.phpTrim( '\u00a0 x \u00a0' ), '\u00a0 x \u00a0' );
	assert.equal( TextStats.phpTrim( '\u0000\u000B x \t' ), 'x' );
} );

test( 'words keeps apostrophes and hyphens and drops punctuation', () => {
	assert.deepEqual( TextStats.words( "Don't use well-known, cheap toys." ), [ "Don't", 'use', 'well-known', 'cheap', 'toys' ] );
	assert.equal( TextStats.words( 'One, two. Three! Four?' ).length, 4 );
	assert.equal( TextStats.words( '\u041f\u0440\u0438\u0432\u0435\u0442 \u043c\u0438\u0440' ).length, 2 );
	assert.equal( TextStats.words( '   ' ).length, 0 );
	// The class keeps hyphen and apostrophe inside a word, and an en dash
	// U+2013 is not in the class, so it splits into two words.
	assert.equal( TextStats.words( 'a\u2013b' ).length, 2 );
} );

test( 'sentences split on punctuation followed by whitespace and on newlines', () => {
	assert.deepEqual( TextStats.sentences( 'First one. Second one!\nThird line' ), [ 'First one.', 'Second one!', 'Third line' ] );
	assert.deepEqual( TextStats.sentences( 'No terminator here' ), [ 'No terminator here' ] );
} );

test( 'paragraphs split on newlines and drop empties', () => {
	assert.deepEqual( TextStats.paragraphs( 'One\n\nTwo\nThree' ), [ 'One', 'Two', 'Three' ] );
} );

test( 'headings read levels and inline markup', () => {
	const got = TextStats.headings( '<h1>Top</h1><h2>Sub <em>here</em></h2>' );
	assert.deepEqual( got, [ { level: 1, text: 'Top' }, { level: 2, text: 'Sub here' } ] );
} );

test( 'imageAlts keeps empty alts and ignores unquoted or missing ones', () => {
	const html = '<img src="a" alt=""><img src="b"><img src="c" alt="A cat"><img src="d" alt=bare>';
	assert.deepEqual( TextStats.imageAlts( html ), [ '', '', 'A cat', '' ] );
} );

test( 'links read href, rel and text and decode entities in href', () => {
	const html = '<a href="/go?x=1&amp;y=2" rel="NoFollow">Read <strong>this</strong></a>';
	assert.deepEqual( TextStats.links( html ), [ { href: '/go?x=1&y=2', rel: 'nofollow', text: 'Read this' } ] );
} );

test( 'media counts images, galleries, videos and youtube or vimeo iframes', () => {
	const html = '<img src="1"><img src="2">[gallery ids="1,2"]<video></video><iframe src="https://youtube.com/embed/x"></iframe><iframe src="https://example.com"></iframe>';
	assert.deepEqual( TextStats.media( html ), { images: 3, videos: 2 } );
} );

test( 'hasToc detects all four markers', () => {
	assert.equal( TextStats.hasToc( '<!-- wp:rankkernel/toc /-->' ), true );
	assert.equal( TextStats.hasToc( '[rankkernel_toc]' ), true );
	assert.equal( TextStats.hasToc( 'wp-block-rank-math-toc-block' ), true );
	assert.equal( TextStats.hasToc( '[toc]' ), true );
	assert.equal( TextStats.hasToc( '<p>no toc</p>' ), false );
} );

test( 'statistics helpers match the PHP fixtures', () => {
	assert.equal( TextStats.longSentenceRatio( [ 'one two three four five six', 'short here' ], 5 ), 0.5 );
	assert.equal( TextStats.longSentenceRatio( [], 5 ), 0 );
	assert.equal( TextStats.longestParagraph( [ 'one two', 'one two three four' ] ), 4 );
	assert.equal( TextStats.longestParagraph( [] ), 0 );
	assert.equal( TextStats.longestRepeatedOpening( [ 'The cat sat', 'The dog ran', 'A bird flew' ] ), 2 );
	assert.equal( TextStats.longestRepeatedOpening( [] ), 0 );
	assert.equal( TextStats.readingTime( 401 ), 3 );
} );
