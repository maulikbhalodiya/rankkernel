'use strict';

/**
 * i18n and import-cap contracts for the block editor sidebar.
 *
 * metadata-sidebar.js is a Gutenberg script that returns early without its
 * wp globals, so the declarations under test are extracted from source and
 * executed in a vm sandbox with a controlled wp.i18n. The tests pin:
 * - SCHEMA_TYPES keeps the raw schema.org type in row[0] and translates only
 *   the display label in row[1],
 * - SCHEMA_FIELD_LABELS keys stay byte-identical while the values translate,
 * - TOKEN_GROUPS still yields the same token to group mapping, with the group
 *   heading translated at the render point,
 * - the 2 MB import cap rejects an oversized file before readAsText,
 * - the __ shim degrades to identity when wp.i18n is absent.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );

function source() {
	return readFileSync( path.join( root, 'assets', 'js', 'metadata-sidebar.js' ), 'utf8' );
}

function walk( code, from, onChar ) {
	const openers = '([{';
	const closers = ')]}';
	let depth = 0;
	let quote = null;
	let lineComment = false;
	let blockComment = false;

	for ( let i = from; i < code.length; i++ ) {
		const ch = code[ i ];
		const next = code[ i + 1 ];

		if ( lineComment ) {
			if ( '\n' === ch ) {
				lineComment = false;
			}
			continue;
		}
		if ( blockComment ) {
			if ( '*' === ch && '/' === next ) {
				blockComment = false;
				i++;
			}
			continue;
		}
		if ( quote ) {
			if ( '\\' === ch ) {
				i++;
				continue;
			}
			if ( ch === quote ) {
				quote = null;
			}
			continue;
		}
		if ( "'" === ch || '"' === ch || '`' === ch ) {
			quote = ch;
			continue;
		}
		if ( '/' === ch && '/' === next ) {
			lineComment = true;
			i++;
			continue;
		}
		if ( '/' === ch && '*' === next ) {
			blockComment = true;
			i++;
			continue;
		}
		if ( openers.includes( ch ) ) {
			depth++;
		} else if ( closers.includes( ch ) ) {
			depth--;
		}

		const stop = onChar( ch, depth, i );
		if ( undefined !== stop ) {
			return stop;
		}
	}

	throw new Error( 'unterminated source starting at ' + from );
}

// Return `var NAME = ...;` verbatim from source.
function statement( code, name ) {
	const start = code.indexOf( 'var ' + name + ' = ' );
	assert.notEqual( start, -1, name + ' declaration must exist' );
	const end = walk( code, start, ( ch, depth, i ) => ( ';' === ch && 0 === depth ? i : undefined ) );
	return code.slice( start, end + 1 );
}

// Return the offset of the closing brace matching the brace at `brace`.
function braceEnd( code, brace ) {
	assert.equal( code[ brace ], '{', 'expected an opening brace' );
	return walk( code, brace, ( ch, depth, i ) => ( '}' === ch && 0 === depth ? i : undefined ) );
}

// Return `function NAME(...) {...}` verbatim from source.
function functionSource( code, name ) {
	const start = code.indexOf( 'function ' + name + '(' );
	assert.notEqual( start, -1, name + ' function must exist' );
	return code.slice( start, braceEnd( code, code.indexOf( '{', start ) ) + 1 );
}

// The exact shims the file declares, so the sandbox runs the shipped code.
function shims( code ) {
	return statement( code, '__' ) + '\n' + statement( code, 'sprintf' );
}

function sandboxFor( options ) {
	const opts = options || {};
	const seen = [];
	const sandbox = { __seen: seen };
	const translate = opts.translate;
	const translateFn = 'function' === typeof translate ? translate : ( text ) => 'translated:' + text;

	sandbox.window = sandbox;
	sandbox.wp = {};

	if ( translate ) {
		sandbox.wp.i18n = {
			__( text, domain ) {
				seen.push( [ text, domain ] );
				return translateFn( text );
			}
		};
	}

	return sandbox;
}

function runStatement( code, name, options ) {
	const sandbox = sandboxFor( options );
	vm.runInNewContext( shims( code ) + '\n' + statement( code, name ) + '\nresult = ' + name + ';', sandbox );
	return { value: sandbox.result, seen: sandbox.__seen };
}

// Values built inside the vm belong to another realm, so plain them before
// the strict deep comparison.
function plain( value ) {
	return JSON.parse( JSON.stringify( value ) );
}

const SCHEMA_TYPE_VALUES = [
	'Article',
	'BlogPosting',
	'NewsArticle',
	'WebPage',
	'FAQPage',
	'HowTo',
	'Product',
	'Recipe',
	'Event',
	'Service',
	'VideoObject',
	'ImageObject',
	'Book',
	'Course',
	'JobPosting',
	'SoftwareApplication',
	'MusicRecording',
	'LocalBusiness',
	'Review',
	'Movie',
	'ClaimReview',
	'Dataset',
	'PodcastEpisode',
	'Carousel',
	'QAPage',
	'ItemList'
];

const SCHEMA_TYPE_LABELS = [
	'Article',
	'Blog Posting',
	'News Article',
	'Web Page',
	'FAQ Page',
	'How To',
	'Product',
	'Recipe',
	'Event',
	'Service',
	'Video',
	'Image',
	'Book',
	'Course',
	'Job Posting',
	'Software Application',
	'Music Recording',
	'Local Business',
	'Review',
	'Movie',
	'Fact Check',
	'Dataset',
	'Podcast Episode',
	'Carousel',
	'Question and Answer Page',
	'Item List'
];

const SCHEMA_FIELD_KEYS = [
	'headline',
	'description',
	'author',
	'price',
	'priceCurrency',
	'sku',
	'availability',
	'ratingValue',
	'reviewCount',
	'bestRating',
	'worstRating',
	'isbn',
	'startDate',
	'endDate',
	'locationName',
	'streetAddress',
	'addressLocality',
	'addressRegion',
	'postalCode',
	'addressCountry',
	'performer',
	'eventStatus',
	'ingredients',
	'instructions',
	'prepTime',
	'cookTime',
	'totalTime',
	'yield',
	'areaServed',
	'thumbnailUrl',
	'uploadDate',
	'duration',
	'contentUrl',
	'appCategory',
	'operatingSystem',
	'artist',
	'album',
	'dateCreated',
	'director',
	'company',
	'jobLocation',
	'salary',
	'datePosted',
	'validThrough',
	'claimReviewed',
	'datePublished',
	'license',
	'distributionUrl',
	'distributionFormat',
	'seriesName',
	'question',
	'answer',
	'answerAuthor',
	'itemName',
	'reviewBody',
	'telephone',
	'priceRange',
	'openingHours',
	'caption',
	'width',
	'height',
	'speakable',
	'about',
	'mentions'
];

const SCHEMA_FIELD_LABEL_VALUES = [
	'Headline',
	'Description',
	'Author',
	'Price',
	'Price currency',
	'SKU',
	'Availability',
	'Rating value',
	'Review count',
	'Best rating',
	'Worst rating',
	'ISBN',
	'Start date',
	'End date',
	'Location name',
	'Street address',
	'City',
	'Region',
	'Postal code',
	'Country',
	'Performer',
	'Event status',
	'Ingredients',
	'Instructions',
	'Prep time',
	'Cook time',
	'Total time',
	'Yield',
	'Area served',
	'Thumbnail URL',
	'Upload date',
	'Duration',
	'Content URL',
	'App category',
	'Operating system',
	'Artist',
	'Album',
	'Date created',
	'Director',
	'Company',
	'Job location',
	'Salary',
	'Date posted',
	'Valid through',
	'Claim reviewed',
	'Date published',
	'License URL',
	'File URL',
	'File format',
	'Series name',
	'Question',
	'Answer',
	'Answer author',
	'Reviewed item',
	'Review text',
	'Phone',
	'Price range',
	'Opening hours',
	'Caption',
	'Width',
	'Height',
	'Speakable selectors',
	'About',
	'Mentions'
];

const FIXED_LITERALS = SCHEMA_TYPE_LABELS.concat( SCHEMA_FIELD_LABEL_VALUES, [
	'Long',
	'Too long',
	'OK',
	'Post',
	'Site',
	'Other',
	'Product name (headline) is required for Product.',
	'Name (headline) is required for Event.',
	'Headline is required for %1$s.',
	'%1$s is required for %2$s.',
	'At least one question is required for FAQPage. Add questions with the FAQ block or the Classic editor.',
	'At least one step is required for HowTo. Add steps with the How-To block or the Classic editor.',
	'Content analysis score: %1$d / 100, %2$s.',
	'This image is smaller than the minimum %1$dx%2$d (%3$dx%4$d).',
	'Import file is larger than 2 MB, nothing was saved.'
] );

test( 'SCHEMA_TYPES keeps every raw schema.org value and translates only the display label', () => {
	const { value: rows, seen } = runStatement( source(), 'SCHEMA_TYPES', { translate: true } );

	assert.equal( rows.length, SCHEMA_TYPE_VALUES.length );
	assert.deepEqual( plain( rows.map( ( row ) => row[ 0 ] ) ), SCHEMA_TYPE_VALUES );
	assert.deepEqual(
		plain( rows.map( ( row ) => row[ 1 ] ) ),
		SCHEMA_TYPE_LABELS.map( ( label ) => 'translated:' + label )
	);
	assert.ok( seen.length >= SCHEMA_TYPE_VALUES.length, 'every label must pass through __()' );
	assert.ok( seen.slice( 0, SCHEMA_TYPE_VALUES.length ).every( ( call ) => 'rankkernel' === call[ 1 ] ) );
} );

test( 'SCHEMA_FIELD_LABELS keeps every schema key and translates every label value', () => {
	const { value: labels, seen } = runStatement( source(), 'SCHEMA_FIELD_LABELS', { translate: true } );

	assert.deepEqual( Object.keys( labels ), SCHEMA_FIELD_KEYS );
	assert.equal( seen.length, SCHEMA_FIELD_KEYS.length, 'every label value must pass through __()' );
	assert.ok( Object.values( labels ).every( ( value ) => /^translated:/.test( value ) ) );
	assert.equal( labels.headline, 'translated:Headline' );
	assert.equal( labels.addressLocality, 'translated:City' );
} );

test( 'TOKEN_GROUPS token-to-group mapping is unchanged and the heading is translated at render', () => {
	const code = source();
	const sandbox = sandboxFor( { translate: true } );

	vm.runInNewContext(
		shims( code ) +
			'\n' +
			statement( code, 'TOKEN_GROUPS' ) +
			'\n' +
			functionSource( code, 'tokenGroupName' ) +
			'\n' +
			functionSource( code, 'tokenGroupLabel' ) +
			'\nresult = {' +
			'\n\tgroups: TOKEN_GROUPS,' +
			'\n\ttitle: tokenGroupName( "%%title%%" ),' +
			'\n\tsep: tokenGroupName( "%%sep%%" ),' +
			'\n\tunknown: tokenGroupName( "%%unlisted%%" ),' +
			'\n\theadings: [ tokenGroupLabel( "Post" ), tokenGroupLabel( "Site" ), tokenGroupLabel( "Other" ) ]' +
			'\n};',
		sandbox
	);

	assert.deepEqual( plain( sandbox.result.groups ), {
		'%%title%%': 'Post',
		'%%excerpt%%': 'Post',
		'%%category%%': 'Post',
		'%%author%%': 'Post',
		'%%date%%': 'Post',
		'%%page%%': 'Post',
		'%%sitename%%': 'Site',
		'%%sep%%': 'Site'
	} );
	assert.equal( sandbox.result.title, 'Post' );
	assert.equal( sandbox.result.sep, 'Site' );
	assert.equal( sandbox.result.unknown, 'Other' );
	assert.deepEqual( plain( sandbox.result.headings ), [ 'translated:Post', 'translated:Site', 'translated:Other' ] );
	assert.ok(
		code.includes( "el( 'p', { className: 'rk-token-group-label', 'aria-hidden': 'true' }, tokenGroupLabel( name ) )" ),
		'the token group heading must translate through tokenGroupLabel() at the render point'
	);
} );

test( 'statusWord fallback translates Long/Too long/OK and defers to the shared helper', () => {
	const code = source();
	const declarations = statement( code, 'shared' ) + '\n' + functionSource( code, 'statusWord' );

	const sandbox = sandboxFor( { translate: true } );
	vm.runInNewContext(
		shims( code ) + '\n' + declarations + '\nresult = [ statusWord( "warn" ), statusWord( "over" ), statusWord( "ok" ) ];',
		sandbox
	);

	assert.deepEqual( plain( sandbox.result ), [ 'translated:Long', 'translated:Too long', 'translated:OK' ] );

	const sharedSandbox = sandboxFor( { translate: true } );
	vm.runInNewContext(
		shims( code ) +
			'\n' +
			declarations +
			'\nshared.statusWord = function () { return "shared-helper-wins"; };' +
			'\nresult = statusWord( "over" );',
		sharedSandbox
	);

	assert.equal( sharedSandbox.result, 'shared-helper-wins' );
} );

test( 'schema required messages compose translated labels with sprintf templates', () => {
	const code = source();
	// Only the field label translates, so the composed placeholder template
	// stays visible in the assertion.
	const onlyLabel = ( text ) => ( 'Start date' === text ? 'Start date FR' : text );
	const sandbox = sandboxFor( { translate: onlyLabel } );

	vm.runInNewContext(
		shims( code ) +
			'\n' +
			statement( code, 'SCHEMA_FIELD_LABELS' ) +
			'\n' +
			functionSource( code, 'schemaRequiredMessage' ) +
			'\nresult = [' +
			'\n\tschemaRequiredMessage( "Product", "headline" ),' +
			'\n\tschemaRequiredMessage( "Event", "headline" ),' +
			'\n\tschemaRequiredMessage( "Recipe", "headline" ),' +
			'\n\tschemaRequiredMessage( "Event", "startDate" )' +
			'\n];',
		sandbox
	);

	assert.deepEqual( plain( sandbox.result ), [
		'Product name (headline) is required for Product.',
		'Name (headline) is required for Event.',
		'Headline is required for Recipe.',
		'Start date FR is required for Event.'
	] );
} );

function importHandlerSource( code ) {
	const anchor = code.indexOf( "id: 'rk-schema-import'" );
	assert.notEqual( anchor, -1, 'the schema import input must exist' );
	const start = code.indexOf( 'function ( event ) {', code.indexOf( 'onChange: function ( event ) {', anchor ) );
	assert.notEqual( start, -1, 'the schema import handler must exist' );
	return 'handler = ' + code.slice( start, braceEnd( code, code.indexOf( '{', start ) ) + 1 ) + ';';
}

function bootImportHandler( options ) {
	const code = source();
	const reads = [];
	const instances = [];
	const errors = [];
	const saved = [];
	const cleared = [];
	const sandbox = sandboxFor( options );

	class FakeFileReader {
		constructor() {
			this.result = '';
			this.onload = null;
			this.onerror = null;
			this.onabort = null;
			instances.push( this );
		}

		readAsText( file ) {
			reads.push( file );
			this.read = file;
		}
	}

	sandbox.FileReader = FakeFileReader;
	sandbox.setError = ( path, message ) => errors.push( [ path, message ] );
	sandbox.saveSchema = ( schema ) => saved.push( schema );
	sandbox.clearDraft = ( path ) => cleared.push( path );

	vm.runInNewContext(
		shims( code ) +
			'\n' +
			statement( code, 'MAX_IMPORT_BYTES' ) +
			'\n' +
			functionSource( code, 'schemaImportTooLarge' ) +
			'\n' +
			importHandlerSource( code ),
		sandbox
	);

	return { handler: sandbox.handler, reads, instances, errors, saved, cleared };
}

test( 'the 2 MB import cap rejects an oversized file before readAsText and accepts one at the limit', () => {
	const oversized = bootImportHandler( { translate: true } );
	oversized.handler( { target: { files: [ { size: 2097153 } ] } } );

	assert.equal( oversized.reads.length, 0, 'an oversized file must never reach readAsText' );
	assert.deepEqual( oversized.errors, [
		[ 'schema.customText', 'translated:Import file is larger than 2 MB, nothing was saved.' ]
	] );

	const atLimit = bootImportHandler();
	atLimit.handler( { target: { files: [ { size: 2097152 } ] } } );

	assert.equal( atLimit.reads.length, 1, 'a file at the 2 MB limit is accepted' );

	const small = bootImportHandler();
	const file = { size: 1024 };
	small.handler( { target: { files: [ file ] } } );

	assert.equal( small.reads.length, 1 );
	assert.equal( small.reads[ 0 ], file );

	// Complete the small import so the success path is covered too.
	small.instances[ 0 ].result = '{"type":"Article"}';
	small.instances[ 0 ].onload();

	assert.deepEqual( plain( small.saved ), [ { type: 'Article' } ] );
	assert.ok( small.errors.some( ( entry ) => 'schema.customText' === entry[ 0 ] && '' === entry[ 1 ] ) );
} );

test( 'the import cap matches the Redirects CSV handler 2 MB convention', () => {
	const php = readFileSync( path.join( root, 'src', 'Modules', 'Redirects', 'CsvHandler.php' ), 'utf8' );

	assert.match( php, /MAX_FILE_SIZE = 2097152;/ );
	assert.match( source(), /var MAX_IMPORT_BYTES = 2097152;/ );
} );

test( 'the __ and sprintf shims degrade to identity formatting without wp.i18n', () => {
	const code = source();
	const bare = sandboxFor();

	vm.runInNewContext(
		shims( code ) +
			'\nresult = [' +
			'\n\t__( "Hello", "rankkernel" ),' +
			'\n\tsprintf( __( "Score %1$d out of 100, %2$s.", "rankkernel" ), 90, "Good" )' +
			'\n];',
		bare
	);

	assert.deepEqual( plain( bare.result ), [ 'Hello', 'Score 90 out of 100, Good.' ] );

	const translating = sandboxFor( { translate: true } );
	vm.runInNewContext( shims( code ) + '\nresult = __( "Hello", "rankkernel" );', translating );

	assert.equal( translating.result, 'translated:Hello' );
} );

test( 'the toolbar score and social minimum notices use sprintf templates with translators comments', () => {
	const code = source();

	assert.ok(
		code.includes( "sprintf( __( 'Content analysis score: %1$d / 100, %2$s.', 'rankkernel' ), props.score, props.bandLabel )" ),
		'the toolbar score announcement must be one ordered sprintf template'
	);
	assert.ok(
		code.includes( '/* translators: 1: content analysis score from 0 to 100, 2: score band label. */' ),
		'the toolbar score template needs a translators comment'
	);
	assert.ok(
		! code.includes( "__( 'Content analysis score', 'rankkernel' ) + ': '" ),
		'the old concatenated toolbar label must be gone'
	);

	assert.ok(
		code.includes( "sprintf( __( 'This image is smaller than the minimum %1$dx%2$d (%3$dx%4$d).', 'rankkernel' ), SOCIAL_MIN_W, SOCIAL_MIN_H, check.width, check.height )" ),
		'the social minimum notice must be one ordered sprintf template'
	);
	assert.ok(
		code.includes( '/* translators: 1: minimum image width in pixels, 2: minimum image height in pixels, 3: actual image width in pixels, 4: actual image height in pixels. */' ),
		'the social minimum template needs a translators comment'
	);
	assert.ok(
		! code.includes( "__( 'This image is smaller than the minimum', 'rankkernel' ) + ' '" ),
		'the old concatenated social notice must be gone'
	);
} );

test( 'every fixed user-facing literal is a direct translation argument', () => {
	const code = source();
	const literals = [];
	const pattern = /__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'rankkernel'\s*\)/g;
	let match;

	while ( ( match = pattern.exec( code ) ) !== null ) {
		literals.push( match[ 1 ] );
	}

	FIXED_LITERALS.forEach( ( literal ) => {
		assert.ok(
			literals.includes( literal ),
			JSON.stringify( literal ) + ' must be a literal translation argument'
		);
	} );

	// The only non-literal call is analyseText(), which passes dynamic engine
	// messages through the same translator.
	assert.equal(
		( code.match( /__\(/g ) || [] ).length,
		literals.length + 1,
		'no other call may pass a variable to the translator'
	);

	assert.ok(
		code.includes( "sprintf( __( 'Headline is required for %1$s.', 'rankkernel' ), type )" ),
		'the headline message must be a numbered sprintf template'
	);
	assert.ok( ! code.includes( "'Headline is required for ' + type" ), 'the headline message must not concatenate' );
	assert.ok( ! code.includes( 'var labels = { startDate:' ), 'the duplicate label map must be gone' );
} );
