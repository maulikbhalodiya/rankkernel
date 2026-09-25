'use strict';

/**
 * Every icon the admin UI requests must exist in the shipped icon font.
 *
 * The Material Symbols subset is a hand built file, so a ligature name that
 * the subset dropped renders as the literal word instead of a glyph. This
 * guard collects every ligature name the admin views and stylesheets ask
 * for, decodes the shipped woff2, and asserts each name shapes to a glyph
 * through the font's own GSUB ligature rules. Alias names such as
 * `assessment`, which shapes to the `insert_chart` outline, pass because the
 * check follows the shaping result and never the raw ligature string.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync, readdirSync } = require( 'node:fs' );
const path = require( 'node:path' );
const zlib = require( 'node:zlib' );

const root = path.resolve( __dirname, '..', '..' );
const fontPath = path.join( root, 'assets', 'fonts', 'material-symbols-outlined-variable.woff2' );

// WOFF2 known table tags, the sixty three entries the flag byte indexes.
const KNOWN_TAGS = [
	'cmap', 'head', 'hhea', 'hmtx', 'maxp', 'name', 'OS/2', 'post',
	'cvt ', 'fpgm', 'glyf', 'loca', 'prep', 'CFF ', 'VORG', 'EBDT',
	'EBLC', 'gasp', 'hdmx', 'kern', 'LTSH', 'PCLT', 'VDMX', 'vhea',
	'vmtx', 'BASE', 'GDEF', 'GPOS', 'GSUB', 'EBSC', 'JSTF', 'MATH',
	'CBDT', 'CBLC', 'COLR', 'CPAL', 'SVG ', 'sbix', 'acnt', 'avar',
	'bdat', 'bloc', 'bsln', 'cvar', 'fdsc', 'feat', 'fmtx', 'fvar',
	'gvar', 'hsty', 'just', 'lcar', 'mort', 'morx', 'opbd', 'prop',
	'trak', 'Zapf', 'Silf', 'Glat', 'Gloc', 'Feat', 'Sill'
];

/**
 * Read a WOFF2 UIntBase128 value, five bytes at most.
 */
function readBase128( buffer, state ) {
	let value = 0;

	for ( let index = 0; index < 5; index++ ) {
		const byte = buffer[ state.offset++ ];

		if ( index === 0 && byte === 0x80 ) {
			throw new Error( 'UIntBase128 must not start with a zero byte' );
		}

		value = value * 128 + ( byte & 0x7f );

		if ( value > 0xffffffff ) {
			throw new Error( 'UIntBase128 overflows 32 bits' );
		}

		if ( ( byte & 0x80 ) === 0 ) {
			return value;
		}
	}

	throw new Error( 'UIntBase128 must not span more than five bytes' );
}

/**
 * Decompress a woff2 file into its raw sfnt tables, keyed by tag.
 *
 * Only the untransformed tables are consumed here, so the walk records
 * every table length to keep the decompressed stream aligned, and the
 * transformed glyf and loca bytes are skipped whole.
 */
function decodeWoff2( buffer ) {
	if ( buffer.toString( 'latin1', 0, 4 ) !== 'wOF2' ) {
		throw new Error( 'the icon font must be a woff2 file' );
	}

	const numTables = buffer.readUInt16BE( 12 );
	const totalCompressedSize = buffer.readUInt32BE( 20 );
	const state = { offset: 48 };
	const entries = [];

	for ( let index = 0; index < numTables; index++ ) {
		const flags = buffer[ state.offset++ ];
		const knownTagIndex = flags & 0x3f;
		const transformVersion = flags >> 6;
		let tag;

		if ( knownTagIndex === 63 ) {
			tag = buffer.toString( 'latin1', state.offset, state.offset + 4 );
			state.offset += 4;
		} else {
			tag = KNOWN_TAGS[ knownTagIndex ];
		}

		const origLength = readBase128( buffer, state );
		const transformed = tag === 'glyf' || tag === 'loca' ? transformVersion !== 3 : transformVersion !== 0;
		const length = transformed ? readBase128( buffer, state ) : origLength;

		entries.push( { tag, length } );
	}

	const compressed = buffer.subarray( state.offset, state.offset + totalCompressedSize );
	const data = zlib.brotliDecompressSync( compressed );
	const tables = {};
	let cursor = 0;

	for ( const entry of entries ) {
		tables[ entry.tag ] = data.subarray( cursor, cursor + entry.length );
		cursor += entry.length;
	}

	return tables;
}

/**
 * Parse a cmap format 4 subtable into a code point to glyph id map.
 */
function parseCmapFormat4( table, offset ) {
	const segCountX2 = table.readUInt16BE( offset + 6 );
	const endCodes = offset + 14;
	const startCodes = endCodes + segCountX2 + 2;
	const idDeltas = startCodes + segCountX2;
	const idRangeOffsets = idDeltas + segCountX2;
	const map = new Map();

	for ( let segment = 0; segment < segCountX2 / 2; segment++ ) {
		const end = table.readUInt16BE( endCodes + segment * 2 );
		const start = table.readUInt16BE( startCodes + segment * 2 );
		const delta = table.readInt16BE( idDeltas + segment * 2 );
		const rangeOffset = table.readUInt16BE( idRangeOffsets + segment * 2 );

		for ( let code = start; code <= end && code !== 0xffff; code++ ) {
			let glyph = 0;

			if ( rangeOffset === 0 ) {
				glyph = ( code + delta ) & 0xffff;
			} else {
				const address = idRangeOffsets + segment * 2 + rangeOffset + ( code - start ) * 2;
				glyph = table.readUInt16BE( address );

				if ( glyph !== 0 ) {
					glyph = ( glyph + delta ) & 0xffff;
				}
			}

			if ( glyph !== 0 ) {
				map.set( code, glyph );
			}
		}
	}

	return map;
}

/**
 * Parse a cmap format 12 subtable into a code point to glyph id map.
 */
function parseCmapFormat12( table, offset ) {
	const groupCount = table.readUInt32BE( offset + 12 );
	const map = new Map();

	for ( let group = 0; group < groupCount; group++ ) {
		const start = table.readUInt32BE( offset + 16 + group * 12 );
		const end = table.readUInt32BE( offset + 20 + group * 12 );
		const startGlyph = table.readUInt32BE( offset + 24 + group * 12 );

		for ( let code = start; code <= end; code++ ) {
			map.set( code, startGlyph + ( code - start ) );
		}
	}

	return map;
}

/**
 * Merge every Unicode cmap subtable into one code point to glyph id map.
 */
function parseCmap( table ) {
	const count = table.readUInt16BE( 2 );
	const map = new Map();

	for ( let index = 0; index < count; index++ ) {
		const platformId = table.readUInt16BE( 4 + index * 8 );
		const encodingId = table.readUInt16BE( 6 + index * 8 );
		const offset = table.readUInt32BE( 8 + index * 8 );

		if ( platformId !== 0 && ! ( platformId === 3 && ( encodingId === 1 || encodingId === 10 ) ) ) {
			continue;
		}

		const format = table.readUInt16BE( offset );
		const subtable = format === 4 ? parseCmapFormat4( table, offset ) : format === 12 ? parseCmapFormat12( table, offset ) : new Map();

		for ( const [ code, glyph ] of subtable ) {
			if ( ! map.has( code ) ) {
				map.set( code, glyph );
			}
		}
	}

	return map;
}

/**
 * Parse a coverage table into its glyph id list, in coverage order.
 */
function parseCoverage( table, offset ) {
	const format = table.readUInt16BE( offset );
	const glyphs = [];

	if ( format === 1 ) {
		const glyphCount = table.readUInt16BE( offset + 2 );

		for ( let index = 0; index < glyphCount; index++ ) {
			glyphs.push( table.readUInt16BE( offset + 4 + index * 2 ) );
		}

		return glyphs;
	}

	const rangeCount = table.readUInt16BE( offset + 2 );

	for ( let index = 0; index < rangeCount; index++ ) {
		const start = table.readUInt16BE( offset + 4 + index * 6 );
		const end = table.readUInt16BE( offset + 6 + index * 6 );

		for ( let glyph = start; glyph <= end; glyph++ ) {
			glyphs.push( glyph );
		}
	}

	return glyphs;
}

/**
 * Collect ligature substitution rules from one GSUB subtable.
 *
 * Lookup type 7 wraps another lookup, which is how the shipped font
 * carries its type 4 rules.
 */
function collectLigatures( table, lookupType, offset, out ) {
	if ( lookupType === 7 ) {
		const extensionType = table.readUInt16BE( offset + 2 );
		const extensionOffset = table.readUInt32BE( offset + 4 );

		collectLigatures( table, extensionType, offset + extensionOffset, out );

		return;
	}

	if ( lookupType !== 4 ) {
		return;
	}

	const coverageOffset = table.readUInt16BE( offset + 2 );
	const ligSetCount = table.readUInt16BE( offset + 4 );
	const coverage = parseCoverage( table, offset + coverageOffset );

	for ( let index = 0; index < ligSetCount; index++ ) {
		const setOffset = offset + table.readUInt16BE( offset + 6 + index * 2 );
		const ligatureCount = table.readUInt16BE( setOffset );

		for ( let ligature = 0; ligature < ligatureCount; ligature++ ) {
			const ligatureOffset = setOffset + table.readUInt16BE( setOffset + 2 + ligature * 2 );
			const glyph = table.readUInt16BE( ligatureOffset );
			const componentCount = table.readUInt16BE( ligatureOffset + 2 );
			const key = [ coverage[ index ] ];

			for ( let component = 1; component < componentCount; component++ ) {
				key.push( table.readUInt16BE( ligatureOffset + 4 + ( component - 1 ) * 2 ) );
			}

			out.set( key.join( ',' ), glyph );
		}
	}
}

/**
 * Map every ligature glyph sequence in the font to its result glyph.
 */
function parseLigatures( table ) {
	// The version 1.1 header keeps the lookup list offset as a 16 bit
	// value at byte 8 and appends the 32 bit FeatureVariations offset.
	const lookupListOffset = table.readUInt16BE( 8 );
	const lookupCount = table.readUInt16BE( lookupListOffset );
	const ligatures = new Map();

	for ( let index = 0; index < lookupCount; index++ ) {
		const lookupOffset = lookupListOffset + table.readUInt16BE( lookupListOffset + 2 + index * 2 );
		const lookupType = table.readUInt16BE( lookupOffset );
		const subTableCount = table.readUInt16BE( lookupOffset + 4 );

		for ( let subTable = 0; subTable < subTableCount; subTable++ ) {
			const subOffset = lookupOffset + table.readUInt16BE( lookupOffset + 6 + subTable * 2 );

			collectLigatures( table, lookupType, subOffset, ligatures );
		}
	}

	return ligatures;
}

/**
 * Resolve a ligature name to its glyph id, null when it does not shape.
 */
function shapesToGlyph( name, cmap, ligatures ) {
	const sequence = [];

	for ( const character of name ) {
		const glyph = cmap.get( character.codePointAt( 0 ) );

		if ( glyph === undefined ) {
			return null;
		}

		sequence.push( glyph );
	}

	const result = ligatures.get( sequence.join( ',' ) );

	return result === undefined ? null : result;
}

/**
 * Recursively list every PHP file under a directory.
 */
function phpFiles( directory ) {
	const found = [];

	for ( const entry of readdirSync( directory, { withFileTypes: true } ) ) {
		const full = path.join( directory, entry.name );

		if ( entry.isDirectory() ) {
			found.push( ...phpFiles( full ) );
		} else if ( entry.name.endsWith( '.php' ) ) {
			found.push( full );
		}
	}

	return found;
}

/**
 * Every ligature name the admin views request through an rk-icon span.
 *
 * A span may compute its name in PHP. Array subscripts are removed before
 * the quoted literals are read, so a name such as $notice['type'] cannot
 * leak the array key into the requested set.
 */
function collectViewNames() {
	const names = new Set();
	const spanPattern = /<span\b[^>]*class="[^"]*\brk-icon\b[^"]*"[^>]*>([\s\S]*?)<\/span>/g;

	for ( const file of phpFiles( path.join( root, 'src', 'Admin', 'Views' ) ) ) {
		const source = readFileSync( file, 'utf8' );

		for ( const match of source.matchAll( spanPattern ) ) {
			const content = match[ 1 ].trim();

			if ( /^[a-z][a-z0-9_]*$/.test( content ) ) {
				names.add( content );

				continue;
			}

			if ( content.indexOf( '<?php' ) === -1 && content.indexOf( '<?=' ) === -1 ) {
				throw new Error( `unrecognized rk-icon content in ${ path.relative( root, file ) }: ${ content }` );
			}

			const literals = content.replace( /\$[A-Za-z_][A-Za-z0-9_]*\s*\[[^\]]*\]/g, '' );

			for ( const literal of literals.matchAll( /'([a-z][a-z0-9_]*)'|"([a-z][a-z0-9_]*)"/g ) ) {
				names.add( literal[ 1 ] || literal[ 2 ] );
			}
		}
	}

	return names;
}

/**
 * Every icon name the admin stylesheets set through a content declaration.
 *
 * Only lowercase ligature shaped values count, so the metadata stylesheets
 * keep their plain text symbols such as the check mark out of the set.
 */
function collectStyleNames() {
	const names = new Set();
	const cssDirectory = path.join( root, 'assets', 'css' );

	for ( const file of readdirSync( cssDirectory ) ) {
		if ( ! file.endsWith( '.css' ) ) {
			continue;
		}

		const css = readFileSync( path.join( cssDirectory, file ), 'utf8' ).replace( /\/\*[\s\S]*?\*\//g, '' );

		for ( const match of css.matchAll( /content:\s*(["'])([a-z][a-z0-9_]*)\1/g ) ) {
			names.add( match[ 2 ] );
		}
	}

	return names;
}

test( 'every icon the admin UI requests exists in the shipped Material Symbols subset', () => {
	const requested = new Set( [ ...collectViewNames(), ...collectStyleNames() ] );

	assert.ok( requested.size > 0, 'the scanner must find at least one requested icon' );
	assert.ok( requested.has( 'help' ), 'the scanner must see the header help icon' );

	const tables = decodeWoff2( readFileSync( fontPath ) );

	assert.ok( tables.cmap, 'the icon font must carry a cmap table' );
	assert.ok( tables.GSUB, 'the icon font must carry a GSUB table' );
	assert.ok( tables.maxp, 'the icon font must carry a maxp table' );

	const cmap = parseCmap( tables.cmap );
	const ligatures = parseLigatures( tables.GSUB );
	const numGlyphs = tables.maxp.readUInt32BE( 4 );
	const missing = [];

	for ( const name of requested ) {
		const glyph = shapesToGlyph( name, cmap, ligatures );

		if ( glyph === null ) {
			missing.push( name );

			continue;
		}

		assert.ok( glyph < numGlyphs, `${ name } must shape to a real glyph` );
	}

	assert.deepEqual(
		[ ...missing ].sort(),
		[],
		`icon ligature names missing from the shipped font: ${ missing.join( ', ' ) }`
	);
} );

test( 'documented alias ligature names resolve through shaping, not their raw name', () => {
	const tables = decodeWoff2( readFileSync( fontPath ) );
	const cmap = parseCmap( tables.cmap );
	const ligatures = parseLigatures( tables.GSUB );

	assert.notEqual( shapesToGlyph( 'insert_chart', cmap, ligatures ), null, 'insert_chart must shape' );
	assert.notEqual( shapesToGlyph( 'auto_fix', cmap, ligatures ), null, 'auto_fix must shape' );

	assert.equal(
		shapesToGlyph( 'assessment', cmap, ligatures ),
		shapesToGlyph( 'insert_chart', cmap, ligatures ),
		'assessment must shape to the insert_chart outline'
	);
	assert.equal(
		shapesToGlyph( 'auto_fix_high', cmap, ligatures ),
		shapesToGlyph( 'auto_fix', cmap, ligatures ),
		'auto_fix_high must shape to the auto_fix outline'
	);
} );
