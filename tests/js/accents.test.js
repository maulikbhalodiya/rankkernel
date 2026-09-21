'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Accents = require( '../../assets/js/analysis/accents.js' );

test( 'removeAccents folds the WordPress Latin table', () => {
	assert.equal( Accents.removeAccents( 'caf\u00e9' ), 'cafe' );
	assert.equal( Accents.removeAccents( 'Cr\u00e8me br\u00fbl\u00e9e' ), 'Creme brulee' );
	assert.equal( Accents.removeAccents( '\u00c6sop \u0153uvre' ), 'AEsop oeuvre' );
	assert.equal( Accents.removeAccents( 'plain ascii' ), 'plain ascii' );
} );

test( 'removeAccents leaves non Latin scripts untouched', () => {
	assert.equal( Accents.removeAccents( '\u041f\u0440\u0438\u0432\u0435\u0442' ), '\u041f\u0440\u0438\u0432\u0435\u0442' );
} );
