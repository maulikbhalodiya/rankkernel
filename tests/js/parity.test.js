'use strict';

/**
 * Cross engine parity: the browser port against the real PHP engine.
 *
 * Both engines run on the same fixtures in the same test run, so there are no
 * stored golden files to drift. The PHP engine is executed in a child process,
 * which is why this test is a Node test and not a PHPUnit test: WPCS forbids
 * the shell calls PHPUnit would need, and this file is outside that ruleset.
 *
 * The comparison is the full payload. For every fixture it checks the score,
 * the band, the check id order and every field of every check, including the
 * `na` statuses on both sides, plus every supporting keyword entry.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { execFileSync } = require( 'node:child_process' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const Analyzer = require( '../../assets/js/analysis/analyzer.js' );
const AnalysisFormat = require( '../../assets/js/analysis/analysis-format.js' );
const TextStats = require( '../../assets/js/analysis/text-stats.js' );
const PHP_SOURCE = require( './parity/php-runner.js' );

// tests/js/parity.test.js, so the plugin root is two levels up.
const root = path.resolve( __dirname, '..', '..' );
const fixtures = JSON.parse( readFileSync( path.join( __dirname, 'parity', 'fixtures.json' ), 'utf8' ) );

// Fixtures where the two engines are known to disagree today. Equality loops
// skip them, and each one is pinned field by field further down, so a change
// on either side still fails the suite.
const KNOWN_DIVERGENCES = {
	'accented-anchor-off': 'the JavaScript generic anchor path does not thread the accent flag'
};

const CHECK_FIELDS = [ 'id', 'category', 'status', 'weight', 'earned', 'message' ];

function phpPayload() {
	const output = execFileSync( 'php', [ '-r', PHP_SOURCE ], {
		cwd: root,
		input: JSON.stringify( { fixtures: fixtures } ),
		encoding: 'utf8',
		maxBuffer: 64 * 1024 * 1024
	} );

	if ( '' === output.trim() ) {
		throw new Error( 'the PHP runner produced no output. A missing ABSPATH makes every engine class exit silently with status 0' );
	}

	return JSON.parse( output );
}

function jsPayload() {
	const out = {};

	for ( const fixture of fixtures ) {
		const result = Analyzer.analyze( fixture.input, { stripAccents: false !== fixture.stripAccents } );
		out[ fixture.id ] = {
			score: result.score,
			band: result.band,
			checks: result.checks,
			keywords: result.keywords
		};
	}

	return out;
}

const payload = phpPayload();
const php = payload.fixtures;
const js = jsPayload();
const compared = fixtures.filter( ( fixture ) => ! Object.prototype.hasOwnProperty.call( KNOWN_DIVERGENCES, fixture.id ) );

function checkById( engine, id, checkId ) {
	const found = engine[ id ].checks.filter( ( check ) => check.id === checkId )[ 0 ];
	assert.ok( found, checkId + ' missing in ' + id );
	return found;
}

function assertAgree( id, checkId, status, earned ) {
	for ( const engine of [ [ 'php', php ], [ 'js', js ] ] ) {
		const found = checkById( engine[ 1 ], id, checkId );
		assert.equal( found.status, status, engine[ 0 ] + ' status ' + checkId + ' in ' + id );
		if ( undefined !== earned ) {
			assert.equal( found.earned, earned, engine[ 0 ] + ' earned ' + checkId + ' in ' + id );
		}
	}
}

function assertStatusOnly( id, checkId, status ) {
	assertAgree( id, checkId, status );
}

test( 'the fixture file carries unique ids', () => {
	const seen = new Set();
	for ( const fixture of fixtures ) {
		assert.ok( ! seen.has( fixture.id ), 'duplicate fixture id ' + fixture.id );
		seen.add( fixture.id );
	}
} );

test( 'the PHP runner answers with a result for every fixture', () => {
	assert.equal( Object.keys( php ).length, fixtures.length );
	for ( const fixture of compared ) {
		assert.ok( php[ fixture.id ], 'php missing ' + fixture.id );
		assert.ok( js[ fixture.id ], 'js missing ' + fixture.id );
		assert.equal( js[ fixture.id ].checks.length, php[ fixture.id ].checks.length, 'check count in ' + fixture.id );
	}
} );

test( 'score and band match for every fixture', () => {
	for ( const fixture of compared ) {
		const id = fixture.id;
		assert.equal( js[ id ].score, php[ id ].score, 'score in ' + id );
		assert.equal( js[ id ].band, php[ id ].band, 'band in ' + id );
	}
} );

test( 'the check ids and their order match for every fixture', () => {
	for ( const fixture of compared ) {
		const id = fixture.id;
		assert.deepEqual(
			js[ id ].checks.map( ( check ) => check.id ),
			php[ id ].checks.map( ( check ) => check.id ),
			'check order in ' + id
		);
	}
} );

test( 'every field of every check matches for every fixture, na included', () => {
	for ( const fixture of compared ) {
		const id = fixture.id;

		for ( let index = 0; index < php[ id ].checks.length; index++ ) {
			const phpCheck = php[ id ].checks[ index ];
			const jsCheck = js[ id ].checks[ index ];

			for ( const field of CHECK_FIELDS ) {
				assert.equal( jsCheck[ field ], phpCheck[ field ], field + ' on ' + phpCheck.id + ' in ' + id );
			}
		}
	}
} );

test( 'every supporting keyword entry matches for every fixture', () => {
	for ( const fixture of compared ) {
		const id = fixture.id;
		const phpKeywords = php[ id ].keywords;
		const jsKeywords = js[ id ].keywords;

		assert.equal( jsKeywords.length, phpKeywords.length, 'keyword count in ' + id );

		for ( let entry = 0; entry < phpKeywords.length; entry++ ) {
			for ( const field of [ 'keyword', 'primary', 'score', 'band' ] ) {
				assert.deepEqual( jsKeywords[ entry ][ field ], phpKeywords[ entry ][ field ], field + ' of keyword ' + entry + ' in ' + id );
			}

			assert.equal( jsKeywords[ entry ].checks.length, phpKeywords[ entry ].checks.length, 'keyword check count in ' + id + ' entry ' + entry );

			for ( let index = 0; index < phpKeywords[ entry ].checks.length; index++ ) {
				const phpCheck = phpKeywords[ entry ].checks[ index ];
				const jsCheck = jsKeywords[ entry ].checks[ index ];

				for ( const field of CHECK_FIELDS ) {
					assert.equal( jsCheck[ field ], phpCheck[ field ], field + ' on ' + phpCheck.id + ' in ' + id + ' keyword ' + entry );
				}
			}
		}
	}
} );

test( 'the score and band boundaries land on exactly 50, 51, 80 and 81', () => {
	const boundaries = [
		[ 'score-50', 50, 'problem' ],
		[ 'score-51', 51, 'improve' ],
		[ 'score-80', 80, 'improve' ],
		[ 'score-81', 81, 'good' ]
	];

	for ( const [ id, score, band ] of boundaries ) {
		assert.equal( php[ id ].score, score, 'php score ' + id );
		assert.equal( js[ id ].score, score, 'js score ' + id );
		assert.equal( php[ id ].band, band, 'php band ' + id );
		assert.equal( js[ id ].band, band, 'js band ' + id );
	}
} );

test( 'keyword_density passes at exactly 2.5 percent and improves just above it', () => {
	assertAgree( 'density-exact-2-5', 'keyword_density', 'pass', 6 );
	assertAgree( 'density-just-above-2-5', 'keyword_density', 'improve', 2 );

	for ( const engine of [ php, js ] ) {
		assert.match( checkById( engine, 'density-exact-2-5', 'keyword_density' ).message, /a density of 2\.50 percent/ );
		assert.match( checkById( engine, 'density-just-above-2-5', 'keyword_density' ).message, /a density of 2\.53 percent/ );
	}
} );

test( 'media partial credit follows the image and video ladder to the cap', () => {
	const ladder = [
		[ 'media-1-image', 1 ],
		[ 'media-2-images', 2 ],
		[ 'media-3-images', 4 ],
		[ 'media-4-images', 6 ],
		[ 'media-1-video', 1 ],
		[ 'media-2-videos', 2 ],
		[ 'media-3-videos', 2 ],
		[ 'media-4-images-2-videos', 6 ]
	];

	for ( const [ id, earned ] of ladder ) {
		assertAgree( id, 'media', 6 === earned ? 'pass' : 'improve', earned );
	}
} );

test( 'the media check counts gallery and video shortcodes but not lookalikes', () => {
	assertAgree( 'media-shortcodes', 'media', 'improve', 4 );
	assertAgree( 'media-gallery-shortcodes', 'media', 'improve', 2 );
	assertAgree( 'media-video-shortcode', 'media', 'improve', 1 );
	assertAgree( 'media-videocam-not-counted', 'media', 'improve', 0 );
	assertAgree( 'media-iframe', 'media', 'improve', 1 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'media-videocam-not-counted', 'media' ).message, 'No images or video found. Media helps a reader stay.' );
	}
} );

test( 'keyword_distribution at the 15 sentence floor and at exactly half the windows', () => {
	assertStatusOnly( 'distribution-14-sentences', 'keyword_distribution', 'na' );
	assertAgree( 'distribution-15-improve', 'keyword_distribution', 'improve', 1 );
	assertAgree( 'distribution-15-problem', 'keyword_distribution', 'problem', 0 );
	assertAgree( 'distribution-ratio-half', 'keyword_distribution', 'improve', 1 );
	assertAgree( 'distribution-ratio-above-half', 'keyword_distribution', 'problem', 0 );
	assertAgree( 'distribution-pass', 'keyword_distribution', 'pass', 3 );
} );

test( 'subheading_distribution at the 300 word gap boundary', () => {
	assertStatusOnly( 'subheading-words-300', 'subheading_distribution', 'na' );
	assertAgree( 'subheading-words-301', 'subheading_distribution', 'improve', 0 );
	assertAgree( 'subheading-gap-300', 'subheading_distribution', 'pass', 3 );
	assertAgree( 'subheading-gap-301', 'subheading_distribution', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'subheading-words-300', 'subheading_distribution' ).message, 'Short enough that subheadings are optional.' );
		assert.equal( checkById( engine, 'subheading-words-301', 'subheading_distribution' ).message, 'No subheadings found. Consider splitting the content.' );
	}
} );

test( 'transition_words at the 200 word floor and at a ratio of exactly 0.30', () => {
	assertStatusOnly( 'transition-words-200', 'transition_words', 'na' );
	assertAgree( 'transition-words-201-ratio-30', 'transition_words', 'pass', 3 );
	assertAgree( 'transition-words-201-ratio-below', 'transition_words', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'transition-words-200', 'transition_words' ).message, 'Short enough that transition words are optional.' );
		assert.equal( checkById( engine, 'transition-words-201-ratio-30', 'transition_words' ).message, '30 percent of the sentences use a transition word.' );
	}
} );

test( 'passive_voice passes at a ratio of exactly 0.10 and improves above it', () => {
	assertAgree( 'passive-ratio-10', 'passive_voice', 'pass', 3 );
	assertAgree( 'passive-ratio-above', 'passive_voice', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'passive-ratio-10', 'passive_voice' ).message, '10 percent of the sentences read as passive voice.' );
		assert.equal( checkById( engine, 'passive-ratio-above', 'passive_voice' ).message, '20 percent of the sentences read as passive voice.' );
	}
} );

test( 'keyword_in_opening reads the whole text at 400 words and the first ten percent at 401', () => {
	assertAgree( 'opening-400-late', 'keyword_in_opening', 'pass', 3 );
	assertAgree( 'opening-401-late', 'keyword_in_opening', 'problem', 0 );
	assertAgree( 'opening-401-early', 'keyword_in_opening', 'pass', 3 );
} );

test( 'text_present at exactly 50 characters, including an astral pair', () => {
	assertAgree( 'text-present-49', 'text_present', 'problem', 0 );
	assertAgree( 'text-present-50', 'text_present', 'pass', 3 );
	assertAgree( 'text-present-emoji-49', 'text_present', 'problem', 0 );
	assertAgree( 'text-present-emoji-50', 'text_present', 'pass', 3 );
} );

test( 'slug_length passes at exactly 75 characters and improves at 76', () => {
	assertAgree( 'slug-length-75', 'slug_length', 'pass', 4 );
	assertAgree( 'slug-length-76', 'slug_length', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'slug-length-75', 'slug_length' ).message, 'The URL is 75 characters long.' );
		assert.equal( checkById( engine, 'slug-length-76', 'slug_length' ).message, 'The URL is 76 characters long.' );
	}
} );

test( 'sentence_length passes at a ratio of exactly 0.25 and improves above it', () => {
	assertAgree( 'sentence-length-ratio-25', 'sentence_length', 'pass', 3 );
	assertAgree( 'sentence-length-ratio-above', 'sentence_length', 'improve', 0 );
} );

test( 'mailto and tel anchors stay out of the generic anchor check', () => {
	assertStatusOnly( 'generic-mailto-asymmetry', 'generic_anchor_text', 'na' );
	assertAgree( 'generic-mailto-plus-generic', 'generic_anchor_text', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'generic-mailto-asymmetry', 'generic_anchor_text' ).message, 'Add a link to check this.' );
		assert.equal( checkById( engine, 'generic-mailto-plus-generic', 'generic_anchor_text' ).message, '1 link(s) use generic anchor text such as click here or a bare URL. Anchor text should describe the destination, which is Google guidance.' );
	}
} );

test( 'the featured alt feeds the keyword alt check and the alt quality check', () => {
	assertStatusOnly( 'featured-alt-pass', 'keyword_in_image_alt', 'pass' );
	assertStatusOnly( 'featured-alt-absent', 'keyword_in_image_alt', 'na' );
	assertAgree( 'featured-alt-quality-only', 'image_alt_quality', 'pass', 3 );
	assertStatusOnly( 'featured-alt-blank', 'image_alt_quality', 'na' );
	assertAgree( 'featured-alt-with-images', 'image_alt_quality', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'featured-alt-with-images', 'image_alt_quality' ).message, '1 image(s) have no alt text and 0 look stuffed. Alt text is for accessibility and understanding, not a keyword slot, which is Google guidance.' );
	}

	assertAgree( 'alt-stuffed-only', 'image_alt_quality', 'improve', 0 );
	assertAgree( 'alt-descriptive', 'image_alt_quality', 'pass', 3 );
	assertStatusOnly( 'alt-none', 'image_alt_quality', 'na' );
} );

test( 'an entity href and a mixed case rel are decoded and lowercased', () => {
	assertStatusOnly( 'href-entity-mixed-rel', 'external_links', 'pass' );
	assertAgree( 'href-entity-mixed-rel', 'followed_external', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.equal( checkById( engine, 'href-entity-mixed-rel', 'followed_external' ).message, 'Every outbound link is nofollow.' );
	}

	assertAgree( 'href-padded', 'internal_links', 'pass', 5 );
} );

test( 'apostrophe and hyphen words keep the two word counters apart', () => {
	assertStatusOnly( 'apostrophe-keyword', 'keyword_in_content', 'pass' );
	assertAgree( 'apostrophe-keyword', 'keyword_density', 'improve', 2 );
	assertStatusOnly( 'hyphen-keyword', 'keyword_in_content', 'pass' );
	assertAgree( 'hyphen-keyword', 'keyword_density', 'improve', 2 );
	assertAgree( 'hyphen-keyword', 'content_length', 'improve', 0 );

	for ( const engine of [ php, js ] ) {
		assert.match( checkById( engine, 'apostrophe-keyword', 'keyword_density' ).message, /appears 2 time\(s\), a density of 11\.11 percent/ );
		assert.match( checkById( engine, 'hyphen-keyword', 'keyword_density' ).message, /appears 2 time\(s\), a density of 13\.33 percent/ );
		// TextStats::words keeps the hyphen inside the token, so 13 words, while
		// the matcher splits it for density.
		assert.match( checkById( engine, 'hyphen-keyword', 'content_length' ).message, /The content is 13 words long\./ );
	}
} );

test( 'the matcher cases the task 3 review found missing all agree', () => {
	assertStatusOnly( 'singular-keyword-singular-text', 'keyword_in_content', 'pass' );
	assertStatusOnly( 'singular-keyword-plural-text', 'keyword_in_content', 'problem' );
	assertStatusOnly( 'function-word', 'keyword_in_content', 'pass' );
	assertStatusOnly( 'function-word-only', 'keyword_in_content', 'pass' );
	assertStatusOnly( 'function-word-reverse', 'keyword_in_content', 'pass' );
	assertStatusOnly( 'unrelated-keyword', 'keyword_in_content', 'problem' );
	assertStatusOnly( 'keyword-cross-sentence', 'keyword_in_content', 'problem' );
	assertStatusOnly( 'keyword-one-sentence', 'keyword_in_content', 'pass' );
	assertStatusOnly( 'keyword-boundary-match', 'keyword_in_content', 'pass' );
	assertStatusOnly( 'keyword-boundary-no-match', 'keyword_in_content', 'problem' );
	assertStatusOnly( 'occurrences-overlap', 'keyword_in_content', 'problem' );
} );

test( 'both accent paths are exercised and both engines follow the flag', () => {
	assertStatusOnly( 'accent-keyword-on', 'keyword_in_content', 'pass' );
	assertStatusOnly( 'accent-keyword-pinned-off', 'keyword_in_content', 'problem' );
	assertStatusOnly( 'accent-keyword-on-plus-off-text', 'keyword_in_content', 'pass' );
	assertAgree( 'accented-anchor-on', 'generic_anchor_text', 'improve', 0 );
} );

test( 'the script, style and malformed markup extraction agrees', () => {
	assertStatusOnly( 'malformed-markup', 'generic_anchor_text', 'pass' );
	assertStatusOnly( 'malformed-markup', 'image_alt_quality', 'pass' );
	assertAgree( 'malformed-markup', 'media', 'improve', 1 );
	assertAgree( 'named-entities', 'text_present', 'pass', 3 );
	assertStatusOnly( 'numeric-entities-href', 'generic_anchor_text', 'pass' );
	assertAgree( 'headings-nested', 'keyword_in_subheading', 'pass', 3 );
	assertAgree( 'sentence-newline', 'consecutive_sentences', 'pass', 3 );
} );

test( 'the default fixture still reaches the good band and its full checklist', () => {
	assert.ok( php[ 'default-good' ].score >= 81, 'score ' + php[ 'default-good' ].score );
	assert.equal( php[ 'default-good' ].band, 'good' );
	assert.equal( js[ 'default-good' ].band, 'good' );
	assert.equal( php[ 'default-good' ].checks.length, 31 );
	assert.equal( php[ 'default-good' ].checks[ 0 ].id, 'keyword_in_content' );
	assert.equal( php[ 'default-good' ].checks[ 30 ].id, 'text_present' );
} );

test( 'a supporting keyword carries exactly its four shared checks in both engines', () => {
	for ( const engine of [ php, js ] ) {
		const second = engine[ 'two-keywords' ].keywords[ 1 ];
		assert.equal( second.primary, false );
		assert.deepEqual( second.checks.map( ( check ) => check.id ).sort(), [ 'keyword_density', 'keyword_distribution', 'keyword_in_content', 'keyword_in_subheading' ] );
	}
} );

// Known divergence, pinned so that any change on either side fails the suite.
// The JavaScript generic anchor path calls KeywordMatcher.normalize without the
// accent flag, unlike every other call site. In production remove_accents is
// always loaded, so both engines strip and the score matches; with the flag off
// PHP leaves the accent and the anchor stays descriptive while the browser
// folds it to "here" and flags generic. When the JavaScript is fixed to thread
// the flag, remove this fixture from KNOWN_DIVERGENCES and replace this test.
test( 'known divergence: the accented anchor does not thread the accent flag', () => {
	const id = 'accented-anchor-off';
	const phpCheck = checkById( php, id, 'generic_anchor_text' );
	const jsCheck = checkById( js, id, 'generic_anchor_text' );

	assert.equal( phpCheck.status, 'pass' );
	assert.equal( phpCheck.earned, 3 );
	assert.equal( phpCheck.message, 'Every link explains where it goes. Anchor text should describe the destination, which is Google guidance.' );
	assert.equal( jsCheck.status, 'improve' );
	assert.equal( jsCheck.earned, 0 );
	assert.equal( jsCheck.message, '1 link(s) use generic anchor text such as click here or a bare URL. Anchor text should describe the destination, which is Google guidance.' );
	assert.equal( php[ id ].score, 58 );
	assert.equal( js[ id ].score, 53 );
} );

// Reserved divergence: the PHP word edge trim is byte based and the JavaScript
// trim is character based. PHP's charlist contains the bytes of U+2019, so it
// strips the trailing 0x80 byte from a Cyrillic word such as "мир" and leaves
// invalid UTF-8 behind, while the JavaScript keeps the whole character. Neither
// side may be altered; this pin makes a change on either one fail.
test( 'reserved divergence: the word edge trim is byte based in PHP and character based in JavaScript', () => {
	assert.deepEqual( payload.probes.wordEdgeTrim, { count: 2, last_hex: 'd0bcd0b8d1', utf8_valid: false } );

	const words = TextStats.words( 'Привет мир' );
	assert.equal( words.length, 2 );
	assert.equal( words[ 1 ], 'мир' );
	assert.equal( Buffer.from( words[ 1 ], 'utf8' ).toString( 'hex' ), 'd0bcd0b8d180' );
} );

// Reserved divergence: number_format pre-rounds the decimal value, so PHP
// answers 1.01 and 1.02 where the JavaScript double sits just under the half
// and answers 1.00 and 1.01. The values are pinned on both sides.
test( 'reserved divergence: numberFormat on the binary boundary values', () => {
	assert.deepEqual( payload.probes.numberFormat, [ '1.01', '1.02', '2.50' ] );
	assert.deepEqual(
		[ AnalysisFormat.numberFormat( 1.005, 2 ), AnalysisFormat.numberFormat( 1.015, 2 ), AnalysisFormat.numberFormat( 2.5, 2 ) ],
		[ '1.00', '1.01', '2.50' ]
	);
} );

// Reserved divergence: invalid scalar and noncharacter numeric entities.
// Installed PHP leaves the three references literal, the JavaScript decoder
// turns them into code points. Pinned on both sides, and the href fixture above
// proves the difference does not reach a compared check through an href.
test( 'reserved divergence: invalid numeric entities stay literal in PHP and decode in JavaScript', () => {
	assert.deepEqual( payload.probes.entities, [ '2623303b', '262378443830303b', '262336353533353b' ] );

	const decoded = [ '&#0;', '&#xD800;', '&#65535;' ].map( ( entity ) => TextStats.decodeEntities( entity ) );
	assert.deepEqual( decoded.map( ( text ) => text.length ), [ 1, 1, 1 ] );
	assert.deepEqual( decoded.map( ( text ) => text.codePointAt( 0 ).toString( 16 ) ), [ '0', 'd800', 'ffff' ] );
} );
