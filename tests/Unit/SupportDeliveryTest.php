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
	 * Persistent object-cache transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $objectCache = [];

	/**
	 * Persistent object-cache expiry timestamps.
	 *
	 * @var array<string, int>
	 */
	private array $objectExpiry = [];

	/**
	 * Whether the test simulates a persistent object cache.
	 *
	 * @var bool
	 */
	private bool $extCache = false;

	/**
	 * Simulated per-site storage prefix (models core's non-global group).
	 *
	 * @var string
	 */
	private string $blogPrefix = '';

	/**
	 * Simulated current Unix time.
	 *
	 * @var int
	 */
	private int $now = 1000;

	/**
	 * Simulate a transient read failure.
	 *
	 * @var bool
	 */
	private bool $failTransientRead = false;

	/**
	 * Simulate a transient write failure.
	 *
	 * @var bool
	 */
	private bool $failTransientWrite = false;

	/**
	 * Keep a transient readable after its timeout option has expired, modelling
	 * delayed or inconsistent expiration cleanup.
	 *
	 * @var bool
	 */
	private bool $ignoreTransientExpiry = false;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$this->transients            = [];
		$this->timeouts              = [];
		$this->objectCache           = [];
		$this->objectExpiry          = [];
		$this->extCache              = false;
		$this->blogPrefix            = '';
		$this->now                   = 1000;
		$this->failTransientRead     = false;
		$this->failTransientWrite    = false;
		$this->ignoreTransientExpiry = false;

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		// Model WordPress transient delegation: a persistent object cache keeps
		// value and expiry in the cache and never writes _transient_timeout_*.
		Functions\when( 'get_transient' )->alias(
			function ( string $key ) {
				if ( $this->failTransientRead ) {
					return false;
				}

				$key = $this->blogPrefix . $key;

				if ( $this->extCache ) {
					if ( ! $this->ignoreTransientExpiry && isset( $this->objectExpiry[ $key ] ) && $this->objectExpiry[ $key ] > 0 && $this->objectExpiry[ $key ] <= $this->now ) {
						unset( $this->objectCache[ $key ], $this->objectExpiry[ $key ] );

						return false;
					}

					return $this->objectCache[ $key ] ?? false;
				}

				if ( ! $this->ignoreTransientExpiry && isset( $this->timeouts[ $key ] ) && $this->timeouts[ $key ] > 0 && $this->timeouts[ $key ] <= $this->now ) {
					unset( $this->transients[ $key ], $this->timeouts[ $key ] );

					return false;
				}

				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value, int $ttl = 0 ): bool {
				if ( $this->failTransientWrite ) {
					return false;
				}

				$key = $this->blogPrefix . $key;

				if ( $this->extCache ) {
					$this->objectCache[ $key ]  = $value;
					$this->objectExpiry[ $key ] = $ttl > 0 ? $this->now + $ttl : 0;

					return true;
				}

				$this->transients[ $key ] = $value;
				$this->timeouts[ $key ]   = $ttl > 0 ? $this->now + $ttl : 0;

				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				$key = $this->blogPrefix . $key;
				unset( $this->transients[ $key ], $this->timeouts[ $key ], $this->objectCache[ $key ], $this->objectExpiry[ $key ] );

				return true;
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( string $name, $fallback = false ) {
				$prefix = '_transient_timeout_';

				if ( str_starts_with( $name, $prefix ) ) {
					return $this->timeouts[ $this->blogPrefix . substr( $name, strlen( $prefix ) ) ] ?? $fallback;
				}

				return $fallback;
			}
		);
		Functions\when( 'time' )->alias(
			function (): int {
				return $this->now;
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'home_url' )->justReturn( '' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_get_theme' )->justReturn( false );
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

		$state = get_transient( SupportDelivery::rateKey( $ip ) );

		$this->assertIsArray( $state );
		$this->assertSame( SupportRequest::RATE_LIMIT_MAX, $state['count'] );
	}

	/**
	 * Persistent cache without a timeout option still blocks the fourth attempt.
	 *
	 * This is the M-3 regression: the old limiter read _transient_timeout_*,
	 * which persistent caches never write, so it failed open.
	 */
	public function test_persistent_cache_without_timeout_option_blocks_fourth_submission(): void {
		$this->extCache = true;
		$ip             = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * Database-backed transients still block the fourth attempt.
	 */
	public function test_database_backed_transients_block_fourth_submission(): void {
		$this->extCache = false;
		$ip             = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * Each accepted under-cap submission slides the 300 second expiry.
	 */
	public function test_accepted_submissions_slide_the_expiry(): void {
		$ip = '203.0.113.7';

		SupportDelivery::recordSubmission( $ip );

		$this->now += 120;
		SupportDelivery::recordSubmission( $ip );

		$state = get_transient( SupportDelivery::rateKey( $ip ) );

		$this->assertIsArray( $state );
		$this->assertSame( 2, $state['count'] );
		$this->assertSame( $this->now + SupportRequest::RATE_LIMIT_SECONDS, $state['expires'] );
	}

	/**
	 * A capped window is not extended by a rejected check or record attempt.
	 */
	public function test_capped_window_is_not_extended(): void {
		$ip = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		$before = get_transient( SupportDelivery::rateKey( $ip ) );

		$this->now += 60;
		SupportDelivery::retryAfter( $ip );
		SupportDelivery::recordSubmission( $ip );

		$after = get_transient( SupportDelivery::rateKey( $ip ) );

		$this->assertSame( $before['expires'], $after['expires'] );
		$this->assertSame( SupportRequest::RATE_LIMIT_MAX, (int) $after['count'] );
	}

	/**
	 * An expired structured window allows a new submission and resets the count.
	 */
	public function test_expired_structured_window_resets(): void {
		$ip = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		$this->now += SupportRequest::RATE_LIMIT_SECONDS + 1;

		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );

		SupportDelivery::recordSubmission( $ip );

		$state = get_transient( SupportDelivery::rateKey( $ip ) );

		$this->assertIsArray( $state );
		$this->assertSame( 1, $state['count'] );
	}

	/**
	 * Legacy scalar below the cap keeps its count and migrates on record.
	 */
	public function test_legacy_scalar_below_cap_migrates_without_losing_count(): void {
		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->transients[ $key ] = 2;
		$this->timeouts[ $key ]   = $this->now + 200;

		SupportDelivery::recordSubmission( $ip );

		$state = get_transient( $key );

		$this->assertIsArray( $state );
		$this->assertSame( 3, $state['count'] );
		$this->assertSame( $this->now + SupportRequest::RATE_LIMIT_SECONDS, $state['expires'] );
	}

	/**
	 * Legacy scalar at the cap with a valid database timeout blocks.
	 */
	public function test_legacy_scalar_at_cap_with_timeout_blocks(): void {
		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->transients[ $key ] = 3;
		$this->timeouts[ $key ]   = $this->now + 200;

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * Legacy scalar at the cap without a timeout option blocks conservatively.
	 */
	public function test_legacy_scalar_at_cap_without_timeout_blocks_conservatively(): void {
		$this->extCache = true;
		$ip             = '203.0.113.7';
		$key            = SupportDelivery::rateKey( $ip );

		$this->objectCache[ $key ]  = 3;
		$this->objectExpiry[ $key ] = $this->now + 200;

		$retry = SupportDelivery::retryAfter( $ip );

		$this->assertGreaterThan( 0, $retry );
		$this->assertLessThanOrEqual( SupportRequest::RATE_LIMIT_SECONDS, $retry );
	}

	/**
	 * A legacy transient that disappears starts a new window.
	 */
	public function test_legacy_transient_disappearing_starts_new_window(): void {
		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->transients[ $key ] = 3;
		$this->timeouts[ $key ]   = $this->now + 200;

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $ip ) );

		delete_transient( $key );

		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );

		SupportDelivery::recordSubmission( $ip );

		$state = get_transient( $key );

		$this->assertIsArray( $state );
		$this->assertSame( 1, $state['count'] );
	}

	/**
	 * Malformed transient state is handled safely.
	 */
	public function test_malformed_state_is_handled_safely(): void {
		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		foreach ( [
			'garbage',
			[
				'count'   => 'nope',
				'expires' => 'nope',
			],
			[ 'unexpected' => true ],
		] as $malformed ) {
			$this->transients[ $key ] = $malformed;

			$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );

			SupportDelivery::recordSubmission( $ip );

			$state = get_transient( $key );

			$this->assertIsArray( $state );
			$this->assertSame( 1, $state['count'] );
			$this->assertIsInt( $state['expires'] );
		}
	}

	/**
	 * A far-future expiry or timeout is clamped to one configured window.
	 */
	public function test_far_future_expiry_is_clamped_to_one_window(): void {
		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->transients[ $key ] = [
			'count'   => SupportRequest::RATE_LIMIT_MAX,
			'expires' => $this->now + 999999,
		];
		$this->timeouts[ $key ]   = $this->now + 999999;

		$structured = SupportDelivery::retryAfter( $ip );

		$this->assertGreaterThan( 0, $structured );
		$this->assertLessThanOrEqual( SupportRequest::RATE_LIMIT_SECONDS, $structured );

		$this->transients[ $key ] = SupportRequest::RATE_LIMIT_MAX;
		$this->timeouts[ $key ]   = $this->now + 999999;

		$legacy = SupportDelivery::retryAfter( $ip );

		$this->assertGreaterThan( 0, $legacy );
		$this->assertLessThanOrEqual( SupportRequest::RATE_LIMIT_SECONDS, $legacy );
	}

	/**
	 * A live transient with a stale embedded expiry resets on the next record.
	 */
	public function test_embedded_expiry_drift_resets_fresh_window(): void {
		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->transients[ $key ] = [
			'count'   => SupportRequest::RATE_LIMIT_MAX,
			'expires' => $this->now - 1,
		];
		// The transient itself is still readable (later storage expiry).
		$this->timeouts[ $key ] = $this->now + 200;

		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );

		SupportDelivery::recordSubmission( $ip );

		$state = get_transient( $key );

		$this->assertIsArray( $state );
		$this->assertSame( 1, $state['count'] );
		$this->assertSame( $this->now + SupportRequest::RATE_LIMIT_SECONDS, $state['expires'] );
	}

	/**
	 * A capped legacy counter whose known timeout expired starts a new window.
	 */
	public function test_stale_capped_legacy_with_expired_timeout_resets(): void {
		$this->ignoreTransientExpiry = true;

		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->transients[ $key ] = SupportRequest::RATE_LIMIT_MAX;
		$this->timeouts[ $key ]   = $this->now - 1;

		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );

		SupportDelivery::recordSubmission( $ip );

		$state = get_transient( $key );

		$this->assertIsArray( $state );
		$this->assertSame( 1, $state['count'] );
		$this->assertSame( $this->now + SupportRequest::RATE_LIMIT_SECONDS, $state['expires'] );
	}

	/**
	 * A capped legacy counter with a future timeout is never rewritten.
	 */
	public function test_capped_legacy_with_future_timeout_is_not_rewritten(): void {
		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->transients[ $key ] = SupportRequest::RATE_LIMIT_MAX;
		$this->timeouts[ $key ]   = $this->now + 200;

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $ip ) );

		SupportDelivery::recordSubmission( $ip );

		$this->assertSame( SupportRequest::RATE_LIMIT_MAX, $this->transients[ $key ] );
		$this->assertSame( $this->now + 200, $this->timeouts[ $key ] );
	}

	/**
	 * A capped legacy counter with no timeout stays capped and is not rewritten.
	 */
	public function test_capped_legacy_without_timeout_is_not_rewritten(): void {
		$this->extCache = true;

		$ip  = '203.0.113.7';
		$key = SupportDelivery::rateKey( $ip );

		$this->objectCache[ $key ]  = SupportRequest::RATE_LIMIT_MAX;
		$this->objectExpiry[ $key ] = $this->now + 200;

		$this->assertSame( SupportRequest::RATE_LIMIT_SECONDS, SupportDelivery::retryAfter( $ip ) );

		SupportDelivery::recordSubmission( $ip );

		$this->assertSame( SupportRequest::RATE_LIMIT_MAX, $this->objectCache[ $key ] );
		$this->assertSame( $this->now + 200, $this->objectExpiry[ $key ] );
	}

	/**
	 * Counters are isolated per address.
	 */
	public function test_rate_limit_is_isolated_per_address(): void {
		$first  = '203.0.113.7';
		$second = '203.0.113.8';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $first );
		}

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $first ) );
		$this->assertSame( 0, SupportDelivery::retryAfter( $second ) );
	}

	/**
	 * Counters are isolated per site when the cache prefixes keys per blog.
	 */
	public function test_rate_limit_is_isolated_per_site(): void {
		$ip = '203.0.113.7';

		$this->blogPrefix = '2:';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		$this->assertGreaterThan( 0, SupportDelivery::retryAfter( $ip ) );

		$this->blogPrefix = '3:';
		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );

		SupportDelivery::recordSubmission( $ip );
		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );
	}

	/**
	 * Best-effort limits: read and write failures do not block legitimate mail.
	 */
	public function test_transient_failures_are_best_effort(): void {
		$ip = '203.0.113.7';

		for ( $i = 0; $i < SupportRequest::RATE_LIMIT_MAX; $i++ ) {
			SupportDelivery::recordSubmission( $ip );
		}

		// A read failure looks like a cold start, so the limiter cannot block.
		$this->failTransientRead = true;
		$this->assertSame( 0, SupportDelivery::retryAfter( $ip ) );

		// A write failure means the submission is not counted.
		$this->failTransientRead  = false;
		$this->failTransientWrite = true;
		SupportDelivery::recordSubmission( '203.0.113.9' );
		$this->assertSame( 0, SupportDelivery::retryAfter( '203.0.113.9' ) );
	}

	/**
	 * A single file upload normalizes to a one item list.
	 */
	public function test_a_single_upload_normalizes_to_one_file(): void {
		$file = [
			'error'    => UPLOAD_ERR_OK,
			'size'     => 1024,
			'name'     => 'shot.png',
			'type'     => 'image/png',
			'tmp_name' => '/tmp/shot.png',
		];

		$this->assertSame( [ $file ], SupportDelivery::normalizeScreenshots( $file ) );
	}

	/**
	 * A multiple file upload normalizes to one entry per file.
	 */
	public function test_a_multiple_upload_normalizes_per_file(): void {
		$raw = [
			'name'     => [ 'a.png', 'b.png' ],
			'type'     => [ 'image/png', 'image/png' ],
			'tmp_name' => [ '/tmp/a.png', '/tmp/b.png' ],
			'error'    => [ UPLOAD_ERR_OK, UPLOAD_ERR_OK ],
			'size'     => [ 10, 12 ],
		];

		$files = SupportDelivery::normalizeScreenshots( $raw );

		$this->assertCount( 2, $files );
		$this->assertSame( 'a.png', $files[0]['name'] );
		$this->assertSame( 'b.png', $files[1]['name'] );
	}

	/**
	 * Empty upload slots are dropped from a multiple upload.
	 */
	public function test_empty_slots_are_dropped_from_a_multiple_upload(): void {
		$raw = [
			'name'     => [ 'a.png', '' ],
			'type'     => [ 'image/png', '' ],
			'tmp_name' => [ '/tmp/a.png', '' ],
			'error'    => [ UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE ],
			'size'     => [ 10, 0 ],
		];

		$this->assertCount( 1, SupportDelivery::normalizeScreenshots( $raw ) );
	}

	/**
	 * Nothing uploaded normalizes to no files.
	 */
	public function test_nothing_uploaded_normalizes_to_no_files(): void {
		$this->assertSame( [], SupportDelivery::normalizeScreenshots( null ) );
		$this->assertSame( [], SupportDelivery::normalizeScreenshots( [ 'error' => UPLOAD_ERR_NO_FILE ] ) );
		$this->assertSame( [], SupportDelivery::normalizeScreenshots( 'nonsense' ) );
	}

	/**
	 * A file count within the cap passes.
	 */
	public function test_a_file_count_within_the_cap_passes(): void {
		$files = [];

		for ( $i = 0; $i < SupportRequest::SCREENSHOT_MAX_COUNT; $i++ ) {
			$files[] = [ 'error' => UPLOAD_ERR_OK ];
		}

		$this->assertTrue( SupportDelivery::validateScreenshotCount( $files ) );
	}

	/**
	 * A file count past the cap is refused.
	 */
	public function test_a_file_count_past_the_cap_is_refused(): void {
		$files = [];

		for ( $i = 0; $i < SupportRequest::SCREENSHOT_MAX_COUNT + 1; $i++ ) {
			$files[] = [ 'error' => UPLOAD_ERR_OK ];
		}

		$result = SupportDelivery::validateScreenshotCount( $files );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'support_screenshots_too_many', $result->get_error_code() );
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
	 * ValidateScreenshot passes explicit $mimes array including JPEG extensions to wp_check_filetype_and_ext.
	 */
	public function test_validate_screenshot_passes_explicit_mimes(): void {
		Functions\when( 'is_uploaded_file' )->justReturn( true );

		$tmp = tempnam( sys_get_temp_dir(), 'rk-shot-' );
		$this->assertIsString( $tmp );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture creates a real temp file for the size check.
		file_put_contents( $tmp, 'x' );

		$passedMimes = null;
		Functions\when( 'wp_check_filetype_and_ext' )->alias(
			static function ( string $path, string $name, ?array $mimes = null ) use ( &$passedMimes ): array {
				$passedMimes = $mimes;

				return [
					'ext'  => 'jpg',
					'type' => 'image/jpeg',
				];
			}
		);

		$result = SupportDelivery::validateScreenshot(
			[
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
				'name'     => 'screenshot.jpg',
				'type'     => 'image/jpeg',
				'tmp_name' => $tmp,
			]
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes the temp file it created.
		unlink( $tmp );

		$this->assertTrue( $result );
		$this->assertIsArray( $passedMimes );
		$this->assertSame( 'image/jpeg', $passedMimes['jpg|jpeg|jpe'] ?? null );
	}

	/**
	 * The actual temporary file size, not the client-supplied size, is capped.
	 */
	public function test_a_lying_reported_size_is_refused_by_the_actual_filesize(): void {
		Functions\when( 'is_uploaded_file' )->justReturn( true );
		Functions\when( 'wp_check_filetype_and_ext' )->justReturn(
			[
				'ext'  => 'png',
				'type' => 'image/png',
			]
		);

		$tmp = tempnam( sys_get_temp_dir(), 'rk-shot-' );
		$this->assertIsString( $tmp );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture creates an oversized temp file for the size check.
		file_put_contents( $tmp, str_repeat( 'a', SupportRequest::SCREENSHOT_MAX_BYTES + 1 ) );

		$result = SupportDelivery::validateScreenshot(
			[
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
				'name'     => 'shot.png',
				'type'     => 'image/png',
				'tmp_name' => $tmp,
			]
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes the temp file it created.
		unlink( $tmp );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'support_screenshot_too_large', $result->get_error_code() );
	}

	/**
	 * StoreScreenshot passes explicit $mimes array to wp_handle_upload.
	 */
	public function test_store_screenshot_passes_explicit_mimes_to_handle_upload(): void {
		Functions\when( 'wp_upload_dir' )->justReturn( [ 'basedir' => '/tmp/uploads' ] );

		$capturedMimes = null;
		Functions\when( 'wp_handle_upload' )->alias(
			static function ( array $file, array $overrides ) use ( &$capturedMimes ): array {
				$capturedMimes = $overrides['mimes'] ?? null;

				return [
					'file' => '/tmp/uploads/shot.jpg',
					'url'  => 'http://example.org/uploads/shot.jpg',
					'type' => 'image/jpeg',
					'path' => '/tmp/uploads/shot.jpg',
				];
			}
		);

		$result = SupportDelivery::storeScreenshot(
			[
				'name'     => 'shot.jpg',
				'type'     => 'image/jpeg',
				'tmp_name' => '/tmp/shot.jpg',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
			]
		);

		$this->assertSame( '/tmp/uploads/shot.jpg', $result );
		$this->assertIsArray( $capturedMimes );
		$this->assertSame( 'image/jpeg', $capturedMimes['jpg|jpeg|jpe'] ?? null );
	}

	/**
	 * The standard wp_handle_upload() file key is used for the attachment path.
	 */
	public function test_store_screenshot_accepts_wordpress_file_key(): void {
		$base = sys_get_temp_dir() . '/rk-support-file-' . uniqid();
		mkdir( $base, 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates its own private temp dir.

		Functions\when( 'get_temp_dir' )->justReturn( $base );
		Functions\when( 'wp_mkdir_p' )->alias(
			static function ( string $dir ): bool {
				return is_dir( $dir ) || mkdir( $dir, 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates the private upload dir.
			}
		);
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_filter' )->justReturn( true );
		Functions\when( 'wp_handle_upload' )->justReturn(
			[
				'file' => '/tmp/private-shot.png',
				'url'  => '',
				'type' => 'image/png',
			]
		);

		$result = SupportDelivery::storeScreenshot(
			[
				'name'     => 'private-shot.png',
				'type'     => 'image/png',
				'tmp_name' => '/tmp/private-shot.png',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
			]
		);

		$this->assertSame( '/tmp/private-shot.png', $result );
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
	 * Diagnostics travel with every send, so the issue can be reproduced in
	 * the same environment. There is no opt out.
	 */
	public function test_diagnostics_are_attached_even_when_the_flag_is_off(): void {
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
				'diagnostics' => '0',
			]
		);

		$this->assertStringContainsString( '--- Site details ---', $message );
		$this->assertStringContainsString( '6.9', $message );
	}

	/**
	 * The upload handler must be pointed at a private system-temp directory.
	 */
	public function test_store_screenshot_uses_private_temp_directory(): void {
		$base = sys_get_temp_dir() . '/rk-support-test-' . uniqid();
		mkdir( $base, 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates its own private temp dir.

		Functions\when( 'get_temp_dir' )->justReturn( $base );
		Functions\when( 'wp_mkdir_p' )->alias(
			static function ( string $dir ): bool {
				return is_dir( $dir ) || mkdir( $dir, 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates the private upload dir.
			}
		);

		$filter = null;
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, callable $callback ) use ( &$filter ): void {
				if ( 'upload_dir' === $hook ) {
					$filter = $callback;
				}
			}
		);
		Functions\when( 'remove_filter' )->justReturn( true );

		Functions\when( 'wp_handle_upload' )->alias(
			static function ( array $file, array $overrides ) use ( &$filter ): array {
				unset( $file, $overrides );

				$dirs = $filter( [] );

				return [ 'path' => $dirs['path'] . '/shot.png' ];
			}
		);

		$result = SupportDelivery::storeScreenshot(
			[
				'name'     => 'shot.png',
				'type'     => 'image/png',
				'tmp_name' => '/tmp/shot.png',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
			]
		);

		$this->assertIsString( $result );
		$this->assertStringStartsWith( $base . '/rankkernel-support-', $result );
		$this->assertStringNotContainsString( '/uploads/', $result );

		foreach ( (array) glob( $base . '/rankkernel-support-*' ) as $dir ) {
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temp dir.
		}

		rmdir( $base ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture removes its own temp dir.
	}

	/**
	 * Deleting a private screenshot removes its per-upload directory too.
	 */
	public function test_delete_screenshots_removes_private_directory(): void {
		$dir = sys_get_temp_dir() . '/rankkernel-support-' . uniqid(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates its own private temp dir.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates its own private temp dir.
		mkdir( $dir, 0700, true );
		$file = $dir . '/shot.png';

		file_put_contents( $file, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes its own temp file.

		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture removes its own temp file.
			}
		);

		SupportDelivery::deleteScreenshots( [ $file ] );

		$this->assertFalse( file_exists( $file ) );
		$this->assertFalse( is_dir( $dir ) );
	}

	/**
	 * Stale private upload directories are removed.
	 */
	public function test_cleanup_stale_screenshots_removes_old_directories(): void {
		$base = sys_get_temp_dir() . '/rk-support-test-' . uniqid();
		$dir  = $base . '/rankkernel-support-stale';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture creates its own private temp dir.
		mkdir( $dir, 0700, true );

		file_put_contents( $dir . '/shot.png', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture writes its own temp file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- test fixture ages its own temp dir.
		touch( $dir, time() - 7200 );

		Functions\when( 'get_temp_dir' )->justReturn( $base );

		SupportDelivery::cleanupStaleScreenshots();

		$this->assertFalse( is_dir( $dir ) );
	}

	/**
	 * Tear down the fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}
}
