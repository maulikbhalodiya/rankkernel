<?php
/**
 * Support request validation tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Support\SupportRequest;

/**
 * Support Request Test.
 */
final class SupportRequestTest extends TestCase {

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'is_email' )->alias( static fn ( string $email ): bool => (bool) filter_var( $email, FILTER_VALIDATE_EMAIL ) );
	}

	/**
	 * Returns a complete, valid submission.
	 *
	 * @param array<string, string> $overrides Fields to replace.
	 * @return array<string, string> Field values.
	 */
	private function valid( array $overrides = [] ): array {
		return array_merge(
			[
				'category'    => 'bug',
				'subject'     => 'Sitemap index missing',
				'message'     => 'The sitemap index renders without any child sitemaps on our staging site.',
				'email'       => 'owner@example.com',
				'consent'     => '1',
				'diagnostics' => '0',
			],
			$overrides
		);
	}

	/**
	 * A complete submission carries no errors.
	 */
	public function test_a_complete_submission_is_accepted(): void {
		$result = SupportRequest::validate( $this->valid() );

		$this->assertSame( [], $result['errors'] );
		$this->assertSame( 'Sitemap index missing', $result['values']['subject'] );
	}

	/**
	 * Every category in the published list is accepted.
	 */
	public function test_every_published_category_is_accepted(): void {
		foreach ( array_keys( SupportRequest::categories() ) as $category ) {
			$result = SupportRequest::validate( $this->valid( [ 'category' => $category ] ) );

			$this->assertArrayNotHasKey( 'category', $result['errors'], $category . ' should be accepted' );
		}
	}

	/**
	 * An unknown category is refused.
	 */
	public function test_an_unknown_category_is_refused(): void {
		$result = SupportRequest::validate( $this->valid( [ 'category' => 'nonsense' ] ) );

		$this->assertArrayHasKey( 'category', $result['errors'] );
	}

	/**
	 * An empty category is refused.
	 */
	public function test_an_empty_category_is_refused(): void {
		$result = SupportRequest::validate( $this->valid( [ 'category' => '' ] ) );

		$this->assertArrayHasKey( 'category', $result['errors'] );
	}

	/**
	 * A subject under the minimum is refused, matching what the browser enforces.
	 */
	public function test_a_subject_under_the_minimum_is_refused(): void {
		$short  = str_repeat( 'a', SupportRequest::SUBJECT_MIN - 1 );
		$result = SupportRequest::validate( $this->valid( [ 'subject' => $short ] ) );

		$this->assertArrayHasKey( 'subject', $result['errors'] );
	}

	/**
	 * A subject exactly on the minimum is accepted.
	 */
	public function test_a_subject_on_the_minimum_is_accepted(): void {
		$exact  = str_repeat( 'a', SupportRequest::SUBJECT_MIN );
		$result = SupportRequest::validate( $this->valid( [ 'subject' => $exact ] ) );

		$this->assertArrayNotHasKey( 'subject', $result['errors'] );
	}

	/**
	 * A subject over the limit is refused.
	 */
	public function test_an_over_long_subject_is_refused(): void {
		$long   = str_repeat( 'a', SupportRequest::SUBJECT_MAX + 1 );
		$result = SupportRequest::validate( $this->valid( [ 'subject' => $long ] ) );

		$this->assertArrayHasKey( 'subject', $result['errors'] );
	}

	/**
	 * A subject exactly on the limit is accepted.
	 */
	public function test_a_subject_on_the_limit_is_accepted(): void {
		$exact  = str_repeat( 'a', SupportRequest::SUBJECT_MAX );
		$result = SupportRequest::validate( $this->valid( [ 'subject' => $exact ] ) );

		$this->assertArrayNotHasKey( 'subject', $result['errors'] );
	}

	/**
	 * A message under the minimum is refused.
	 */
	public function test_a_short_message_is_refused(): void {
		$result = SupportRequest::validate( $this->valid( [ 'message' => 'too short' ] ) );

		$this->assertArrayHasKey( 'message', $result['errors'] );
	}

	/**
	 * A message over the limit is refused.
	 */
	public function test_an_over_long_message_is_refused(): void {
		$long   = str_repeat( 'a', SupportRequest::MESSAGE_MAX + 1 );
		$result = SupportRequest::validate( $this->valid( [ 'message' => $long ] ) );

		$this->assertArrayHasKey( 'message', $result['errors'] );
	}

	/**
	 * A malformed email address is refused.
	 */
	public function test_a_malformed_email_is_refused(): void {
		$result = SupportRequest::validate( $this->valid( [ 'email' => 'not-an-address' ] ) );

		$this->assertArrayHasKey( 'email', $result['errors'] );
	}

	/**
	 * An empty email address is refused.
	 */
	public function test_an_empty_email_is_refused(): void {
		$result = SupportRequest::validate( $this->valid( [ 'email' => '' ] ) );

		$this->assertArrayHasKey( 'email', $result['errors'] );
	}

	/**
	 * A missing consent value is refused.
	 */
	public function test_a_missing_consent_is_refused(): void {
		$result = SupportRequest::validate( $this->valid( [ 'consent' => '' ] ) );

		$this->assertArrayHasKey( 'consent', $result['errors'] );
	}

	/**
	 * Diagnostics are recorded as an off value when unticked.
	 */
	public function test_diagnostics_default_to_off(): void {
		$result = SupportRequest::validate( $this->valid( [ 'diagnostics' => '' ] ) );

		$this->assertSame( '0', $result['values']['diagnostics'] );
	}

	/**
	 * Diagnostics are recorded as an on value when ticked.
	 */
	public function test_diagnostics_are_recorded_when_ticked(): void {
		$result = SupportRequest::validate( $this->valid( [ 'diagnostics' => '1' ] ) );

		$this->assertSame( '1', $result['values']['diagnostics'] );
	}

	/**
	 * Every empty required field is reported at once.
	 */
	public function test_all_empty_required_fields_are_reported_together(): void {
		$result = SupportRequest::validate(
			[
				'category' => '',
				'subject'  => '',
				'message'  => '',
				'email'    => '',
				'consent'  => '',
			]
		);

		$this->assertArrayHasKey( 'category', $result['errors'] );
		$this->assertArrayHasKey( 'subject', $result['errors'] );
		$this->assertArrayHasKey( 'message', $result['errors'] );
		$this->assertArrayHasKey( 'email', $result['errors'] );
		$this->assertArrayHasKey( 'consent', $result['errors'] );
	}

	/**
	 * The published limits are what the browser is told.
	 */
	public function test_limits_are_published_for_the_browser(): void {
		$limits = SupportRequest::limits();

		$this->assertSame( SupportRequest::SUBJECT_MIN, $limits['subjectMin'] );
		$this->assertSame( SupportRequest::SUBJECT_MAX, $limits['subjectMax'] );
		$this->assertSame( SupportRequest::MESSAGE_MIN, $limits['messageMin'] );
		$this->assertSame( SupportRequest::MESSAGE_MAX, $limits['messageMax'] );
		$this->assertSame( SupportRequest::SCREENSHOT_MAX_BYTES, $limits['screenshotMaxBytes'] );
	}

	/**
	 * The recipient is the support inbox.
	 */
	public function test_the_recipient_is_the_support_inbox(): void {
		$this->assertSame( 'rankkernelsupport@gmail.com', SupportRequest::RECIPIENT );
	}

	/**
	 * The message body carries the reply address.
	 */
	public function test_the_body_carries_the_reply_address(): void {
		$body = SupportRequest::body( $this->valid() );

		$this->assertStringContainsString( 'owner@example.com', $body );
		$this->assertStringContainsString( 'Sitemap index missing', $body );
		$this->assertStringNotContainsString( 'Site details', $body );
	}

	/**
	 * The body omits the diagnostics block when it is empty.
	 */
	public function test_the_body_omits_an_empty_diagnostics_block(): void {
		$body = SupportRequest::body( $this->valid(), '' );

		$this->assertStringNotContainsString( '--- Site details ---', $body );
	}

	/**
	 * The body includes the diagnostics block when one is supplied.
	 */
	public function test_the_body_includes_a_supplied_diagnostics_block(): void {
		$body = SupportRequest::body( $this->valid(), 'PHP: 8.3.0' );

		$this->assertStringContainsString( '--- Site details ---', $body );
		$this->assertStringContainsString( 'PHP: 8.3.0', $body );
	}

	/**
	 * Tear down the fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}
}
