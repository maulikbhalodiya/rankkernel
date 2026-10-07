<?php
/**
 * Support delivery, abuse control and screenshot tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Support\SupportDelivery;
use RankKernel\Support\SupportRequest;
use WP_Error;

/**
 * Support Delivery Test.
 */
final class SupportDeliveryTest extends TestCase {

	/**
	 * Transient values keyed by name.
	 *
	 * @var array<string, int>
	 */
	private array $transients = [];

	/**
	 * Transient expiry timestamps keyed by transient name.
	 *
	 * @var array<string, int>
	 */
	private array $timeouts = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$this->transients = [];
		$this->timeouts   = [];

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'get_transient' )->alias(
			function ( string $key ) {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value ): bool {
				$this->transients[ $key ] = (int) $value;

				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->transients[ $key ] );

				return true;
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( string $name, $fallback = false ) {
				$prefix = '_transient_timeout_';

				if ( str_starts_with( $name, $prefix ) ) {
					return $this->timeouts[ substr( $name, strlen( $prefix ) ) ] ?? $fallback;
				}

				return $fallback;
			}
		);
		Functions\when( 'time' )->alias( static fn (): int => 1000 );
		// No opinion by default, so the browser supplied type still decides.
		Functions\when( 'wp_check_filetype_and_ext' )->alias(
			static fn (): array => [
				'ext'  => false,
				'type' => false,
			]
		);
	}

	/**
	 * An empty honeypot looks human.
	 */
	public function test_an_empty_honeypot_passes(): void {
		$this->assertTrue( SupportDelivery::passesHoneypot( '' ) );
		$this->assertTrue( SupportDelivery::passesHoneypot( '   ' ) );
	}

	/**
	 * A filled honeypot is caught.
	 */
	public function test_a_filled_honeypot_fails(): void {
		$this->assertFalse( SupportDelivery::passesHoneypot( 'https://spam.example' ) );
	}

	/**
	 * The rate-limit key is derived from the address, not stored in the clear.
	 */
	public function test_the_rate_limit_key_is_not_the_raw_address(): void {
		$key = SupportDelivery::rateKey( '203.0.113.7' );

		$this->assertStringNotContainsString( '203.0.113.7', $key );
		$this->assertStringStartsWith( 'rankkernel_support_rl_', $key );
	}

	/**
	 * Two addresses get independent buckets.
	 */
	public function test_the_rate_limit_key_differs_per_address(): void {
		$this->assertNotSame(
			SupportDelivery::rateKey( '203.0.113.7' ),
			SupportDelivery::rateKey( '203.0.113.8' )
		);
	}

	/**
	 * A first submission is always allowed.
	 */
	public function test_the_first_submission_is_allowed(): void {
		$this->assertSame( 0, SupportDelivery::retryAfter( '203.0.113.7' ) );
	}

	/**
	 * Submissions up to the cap stay allowed.
	 */
	public function test_submissions_below_the_cap_stay_allowed(): void {
		$ip = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX - 1; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * A user who is still under the cap is not blocked, even though their own
	 * previous submission left the window open.
	 *
	 * Without the under-cap gate this would report the remaining window and
	 * refuse a legitimate second submission.
	 */
	public function test_an_open_window_below_the_cap_does_not_block(): void {
		$ip = '203.0.113.7';

		SupportDelivery::recordSubmission( $ip );

		// The window from that first submission is still live.
		$this->timeouts[ SupportDelivery::rateKey( $ip ) ] = 1300;

		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * The request that reaches the cap is blocked.
	 */
	public function test_the_cap_blocks_further_submissions(): void {
		$ip = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		// WordPress stores the transient expiry in the options table, so that
		// is where the remaining window has to be stubbed.
		$this->timeouts[ SupportDelivery::rateKey( $ip ) ] = 1300;

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * An expired window is not blocking.
	 */
	public function test_an_expired_window_does_not_block(): void {
		$ip = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		// The window already elapsed, so the stored timeout is in the past.
		$this->timeouts[ SupportDelivery::rateKey( $ip ) ] = 900;

		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * Recording past the cap does not inflate the bucket further.
	 */
	public function test_recording_past_the_cap_does_not_inflate(): void {
		$ip = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX + 5; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		$this->assertSame( SupportRequest::RATE_LIMIT_MAX, (int) get_transient( SupportDelivery::rateKey( $ip ) ) );
	}

	/**
	 * No file at all is fine, the screenshot is optional.
	 */
	public function test_no_screenshot_is_accepted(): void {
		$this->assertTrue( SupportDelivery::validateScreenshot( null ) );
	}

	/**
	 * An empty file input is fine.
	 */
	public function test_an_untouched_file_input_is_accepted(): void {
		$this->assertTrue(
			SupportDelivery::validateScreenshot( [ 'error' => UPLOAD_ERR_NO_FILE ] )
		);
	}

	/**
	 * A file over the cap is refused with the size reason.
	 */
	public function test_an_oversized_screenshot_is_refused(): void {
		$result = SupportDelivery::validateScreenshot(
			[
				'error' => UPLOAD_ERR_OK,
				'size'  => SupportRequest::SCREENSHOT_MAX_BYTES + 1,
				'name'  => 'shot.png',
				'type'  => 'image/png',
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'support_screenshot_too_large', $result->get_error_code() );
	}

	/**
	 * A file the server already rejected for size is refused before any read.
	 */
	public function test_a_server_side_size_rejection_is_refused(): void {
		$result = SupportDelivery::validateScreenshot( [ 'error' => UPLOAD_ERR_INI_SIZE ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'support_screenshot_too_large', $result->get_error_code() );
	}

	/**
	 * A file whose type is not an image is refused.
	 */
	public function test_a_non_image_screenshot_is_refused(): void {
		Functions\when( 'is_uploaded_file' )->justReturn( true );

		$result = SupportDelivery::validateScreenshot(
			[
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
				'name'     => 'payload.php',
				'type'     => 'application/x-php',
				'tmp_name' => '/tmp/whatever',
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'support_screenshot_type', $result->get_error_code() );
	}

	/**
	 * A non-uploaded path is refused, so a crafted tmp_name cannot be read.
	 */
	public function test_a_non_uploaded_path_is_refused(): void {
		Functions\when( 'is_uploaded_file' )->justReturn( false );

		$result = SupportDelivery::validateScreenshot(
			[
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
				'name'     => 'shot.png',
				'type'     => 'image/png',
				'tmp_name' => '/etc/passwd',
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'support_screenshot_failed', $result->get_error_code() );
	}

	/**
	 * Only the four image types are accepted.
	 */
	public function test_the_accepted_image_types_are_narrow(): void {
		$this->assertSame(
			[ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ],
			SupportRequest::screenshotTypes()
		);
	}

	/**
	 * A successful send returns true.
	 */
	public function test_a_successful_send_returns_true(): void {
		$captured = [];

		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $message, $headers = [], $attachments = [] ) use ( &$captured ): bool {
				$captured = [
					'to'          => $to,
					'subject'     => $subject,
					'message'     => $message,
					'headers'     => $headers,
					'attachments' => $attachments,
				];

				return true;
			}
		);

		$result = SupportDelivery::send(
			[
				'category'    => 'bug',
				'subject'     => 'Broken sitemap',
				'message'     => 'The sitemap index is empty on staging.',
				'email'       => 'owner@example.com',
				'diagnostics' => '0',
			],
			[ '/tmp/shot.png' ]
		);

		$this->assertTrue( $result );
		$this->assertSame( SupportRequest::RECIPIENT, $captured['to'] );
		$this->assertStringContainsString( 'Reply-To: owner@example.com', implode( "\n", $captured['headers'] ) );
		$this->assertSame( [ '/tmp/shot.png' ], $captured['attachments'] );
	}

	/**
	 * A rejected send surfaces the transport failure.
	 */
	public function test_a_failed_send_returns_an_error(): void {
		Functions\when( 'wp_mail' )->justReturn( false );

		$result = SupportDelivery::send(
			[
				'category'    => 'bug',
				'subject'     => 'Broken sitemap',
				'message'     => 'The sitemap index is empty on staging.',
				'email'       => 'owner@example.com',
				'diagnostics' => '0',
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'support_mail_failed', $result->get_error_code() );
	}

	/**
	 * Ticking diagnostics attaches the site details block.
	 */
	public function test_diagnostics_are_attached_when_requested(): void {
		$message = '';

		Functions\when( 'get_bloginfo' )->justReturn( '6.9' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_get_theme' )->justReturn( false );
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body ) use ( &$message ): bool {
				$message = (string) $body;

				return true;
			}
		);

		SupportDelivery::send(
			[
				'category'    => 'bug',
				'subject'     => 'Broken sitemap',
				'message'     => 'The sitemap index is empty on staging.',
				'email'       => 'owner@example.com',
				'diagnostics' => '1',
			]
		);

		$this->assertStringContainsString( '--- Site details ---', $message );
		$this->assertStringContainsString( '6.9', $message );
	}

	/**
	 * Diagnostics are not attached when unticked.
	 */
	public function test_diagnostics_are_absent_when_not_requested(): void {
		$message = '';

		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body ) use ( &$message ): bool {
				$message = (string) $body;

				return true;
			}
		);

		SupportDelivery::send(
			[
				'category'    => 'bug',
				'subject'     => 'Broken sitemap',
				'message'     => 'The sitemap index is empty on staging.',
				'email'       => 'owner@example.com',
				'diagnostics' => '0',
			]
		);

		$this->assertStringNotContainsString( '--- Site details ---', $message );
	}

	/**
	 * Tear down the fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}
}
