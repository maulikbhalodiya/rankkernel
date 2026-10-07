'use strict';

/**
 * Live validation rules for the Support screen.
 *
 * The script is a classic DOM script. Its rules run through the pure validate
 * function the file exposes on the localized config, so the node harness and
 * the browser exercise the same code with no DOM involved.
 */

const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.resolve( __dirname, '..', '..' );

const LIMITS = {
	subjectMin: 4,
	subjectMax: 150,
	messageMin: 20,
	messageMax: 5000,
	screenshotMaxBytes: 2097152
};

const I18N = {
	subjectMin: 'The subject must be at least %d characters.',
	subjectMax: 'The subject must be %d characters or fewer.',
	messageMin: 'Please add a little more detail, at least %d characters.',
	messageMax: 'The message must be %d characters or fewer.',
	emailInvalid: 'Enter an email address we can reply to.',
	categoryRequired: 'Choose what this is about.',
	consentRequired: 'Please confirm you understand this message is emailed to the plugin author.',
	screenshotTooLarge: 'That screenshot is too large. Please keep it under 2 MB.',
	screenshotType: 'The screenshot must be a PNG, JPEG, GIF or WebP image.'
};

function load() {
	const document = {
		readyState: 'complete',
		addEventListener() {},
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		},
		getElementById() {
			return null;
		}
	};

	const sandbox = {
		document,
		rankkernelSupport: {
			limits: LIMITS,
			i18n: I18N,
			categories: [ 'bug', 'suggestion', 'question' ]
		}
	};

	sandbox.window = sandbox;
	vm.runInNewContext(
		readFileSync( path.join( root, 'assets', 'js', 'support-admin.js' ), 'utf8' ),
		sandbox
	);

	assert.equal( typeof sandbox.rankkernelSupport.validate, 'function' );
	assert.equal( typeof sandbox.rankkernelSupport.fieldValue, 'function' );

	return sandbox.rankkernelSupport;
}

test( 'the pure validator and field reader are reachable from the harness', () => {
	load();
} );

test( 'a short subject reports the minimum', () => {
	const { validate } = load();

	assert.equal( validate( 'subject', 'abc' ), 'The subject must be at least 4 characters.' );
} );

test( 'a subject on the minimum passes', () => {
	const { validate } = load();

	assert.equal( validate( 'subject', 'abcd' ), '' );
} );

test( 'an over long subject reports the maximum', () => {
	const { validate } = load();

	assert.equal(
		validate( 'subject', 'a'.repeat( 151 ) ),
		'The subject must be 150 characters or fewer.'
	);
} );

test( 'an empty subject is left to the server, not nagged about', () => {
	const { validate } = load();

	assert.equal( validate( 'subject', '' ), '' );
} );

test( 'a short message reports the minimum', () => {
	const { validate } = load();

	assert.equal(
		validate( 'message', 'too short' ),
		'Please add a little more detail, at least 20 characters.'
	);
} );

test( 'an over long message reports the maximum', () => {
	const { validate } = load();

	assert.equal(
		validate( 'message', 'a'.repeat( 5001 ) ),
		'The message must be 5000 characters or fewer.'
	);
} );

test( 'a malformed email reports a replyable address error', () => {
	const { validate } = load();

	assert.equal( validate( 'email', 'not-an-address' ), 'Enter an email address we can reply to.' );
	assert.equal( validate( 'email', 'a@b' ), 'Enter an email address we can reply to.' );
} );

test( 'a well formed email passes', () => {
	const { validate } = load();

	assert.equal( validate( 'email', 'owner@example.com' ), '' );
} );

test( 'an empty email is left to the server', () => {
	const { validate } = load();

	assert.equal( validate( 'email', '' ), '' );
} );

test( 'an unchosen category asks for one', () => {
	const { validate } = load();

	assert.equal( validate( 'category', '' ), 'Choose what this is about.' );
} );

test( 'a category outside the published list is refused', () => {
	const { validate } = load();

	assert.equal( validate( 'category', 'nonsense' ), 'Choose what this is about.' );
} );

test( 'each published category passes', () => {
	const { validate } = load();

	for ( const category of [ 'bug', 'suggestion', 'question' ] ) {
		assert.equal( validate( 'category', category ), '' );
	}
} );

test( 'unticked consent asks for confirmation', () => {
	const { validate } = load();

	assert.equal(
		validate( 'consent', false ),
		'Please confirm you understand this message is emailed to the plugin author.'
	);
} );

test( 'ticked consent passes', () => {
	const { validate } = load();

	assert.equal( validate( 'consent', true ), '' );
} );

test( 'an oversized screenshot is refused', () => {
	const { validate } = load();

	assert.equal(
		validate( 'screenshot', { size: LIMITS.screenshotMaxBytes + 1, type: 'image/png' } ),
		'That screenshot is too large. Please keep it under 2 MB.'
	);
} );

test( 'a screenshot on the size limit passes', () => {
	const { validate } = load();

	assert.equal( validate( 'screenshot', { size: LIMITS.screenshotMaxBytes, type: 'image/png' } ), '' );
} );

test( 'a non image screenshot is refused', () => {
	const { validate } = load();

	assert.equal(
		validate( 'screenshot', { size: 10, type: 'application/pdf' } ),
		'The screenshot must be a PNG, JPEG, GIF or WebP image.'
	);
} );

test( 'an untouched screenshot field is fine', () => {
	const { validate } = load();

	assert.equal( validate( 'screenshot', null ), '' );
} );

test( 'a field reader reports consent as a boolean', () => {
	const { fieldValue } = load();

	assert.equal(
		fieldValue( { dataset: { rkSupportField: 'consent' }, checked: true } ),
		true
	);
	assert.equal(
		fieldValue( { dataset: { rkSupportField: 'consent' }, checked: false } ),
		false
	);
} );

test( 'a field reader returns the chosen file for the screenshot', () => {
	const { fieldValue } = load();
	const file = { size: 12, type: 'image/png' };

	assert.equal(
		fieldValue( { dataset: { rkSupportField: 'screenshot' }, files: [ file ] } ),
		file
	);
	assert.equal(
		fieldValue( { dataset: { rkSupportField: 'screenshot' }, files: [] } ),
		null
	);
} );

test( 'a field reader passes text values straight through', () => {
	const { fieldValue } = load();

	assert.equal(
		fieldValue( { dataset: { rkSupportField: 'subject' }, value: ' Sitemap ' } ),
		' Sitemap '
	);
} );