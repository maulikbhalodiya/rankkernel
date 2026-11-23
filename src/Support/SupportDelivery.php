<?php
/**
 * Support submission delivery: abuse controls, uploads and mail.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Support;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Carries a validated support submission to the support inbox.
 *
 * Kept apart from SupportRequest so the validation rules stay pure and can be
 * tested without WordPress filesystem and mail state.
 */
final class SupportDelivery {

	/**
	 * Transient key prefix for rate limiting.
	 */
	private const RATE_KEY_PREFIX = 'rankkernel_support_rl_';

	/**
	 * Whether the submission passed the honeypot check.
	 *
	 * A real browser leaves a hidden field empty. Anything in it is a bot, and
	 * the submission is reported as accepted so the bot is not told it was
	 * caught.
	 *
	 * @param string $honeypot Submitted honeypot value.
	 * @return bool True when the submission looks human.
	 */
	public static function passesHoneypot( string $honeypot ): bool {
		return '' === trim( $honeypot );
	}

	/**
	 * Key for the rate-limit transient.
	 *
	 * @param string $ip Requesting address.
	 * @return string Transient key.
	 */
	public static function rateKey( string $ip ): string {
		return self::RATE_KEY_PREFIX . md5( $ip );
	}

	/**
	 * Decode stored rate-limit state.
	 *
	 * Current state is a structured array with an embedded absolute expiry.
	 * Older installs may still hold a plain integer counter whose expiry was
	 * kept in the _transient_timeout_* options row. That row exists only for
	 * database-backed transients, so it is consulted only while decoding the
	 * legacy shape and never becomes the source of truth for new state.
	 *
	 * @param mixed $raw Raw transient value.
	 * @return array{count: int, expires: int, legacy: bool} Decoded state.
	 */
	private static function decodeRateState( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			$count   = isset( $raw['count'] ) && is_numeric( $raw['count'] ) ? max( 0, (int) $raw['count'] ) : 0;
			$expires = isset( $raw['expires'] ) && is_numeric( $raw['expires'] ) ? max( 0, (int) $raw['expires'] ) : 0;

			return [
				'count'   => $count,
				'expires' => $expires,
				'legacy'  => false,
			];
		}

		if ( is_int( $raw ) || ( is_string( $raw ) && '' !== $raw && ctype_digit( $raw ) ) ) {
			return [
				'count'   => max( 0, (int) $raw ),
				'expires' => 0,
				'legacy'  => true,
			];
		}

		return [
			'count'   => 0,
			'expires' => 0,
			'legacy'  => false,
		];
	}

	/**
	 * Whether this address may submit again.
	 *
	 * Best effort: this is a non-atomic read of the stored counter, so
	 * concurrent submissions can briefly exceed the cap.
	 *
	 * @param string $ip Requesting address.
	 * @return int Seconds remaining, or 0 when a submission is allowed.
	 */
	public static function retryAfter( string $ip ): int {
		if ( ! function_exists( 'get_transient' ) ) {
			return 0;
		}

		$state = self::decodeRateState( get_transient( self::rateKey( $ip ) ) );
		$now   = time();

		if ( $state['count'] < SupportRequest::RATE_LIMIT_MAX ) {
			return 0;
		}

		if ( ! $state['legacy'] ) {
			$remaining = $state['expires'] - $now;

			// Clamp a corrupted or tampered expiry to one configured window.
			return $remaining > 0 ? min( $remaining, SupportRequest::RATE_LIMIT_SECONDS ) : 0;
		}

		// Legacy integer state: honour its database timeout when one exists.
		$timeout = function_exists( 'get_option' ) ? (int) get_option( '_transient_timeout_' . self::rateKey( $ip ), 0 ) : 0;

		if ( $timeout > 0 ) {
			$remaining = $timeout - $now;

			// Clamp a corrupted or tampered timeout to one configured window.
			return $remaining > 0 ? min( $remaining, SupportRequest::RATE_LIMIT_SECONDS ) : 0;
		}

		// A capped legacy counter with no readable timeout must not fail open.
		// Block for at most one configured window; the transient expiring
		// naturally reopens submissions.
		return SupportRequest::RATE_LIMIT_SECONDS;
	}

	/**
	 * Records a successful submission against the rate limit.
	 *
	 * Each accepted under-cap submission stores the counter together with an
	 * embedded expiry and refreshes the transient TTL, which slides the window.
	 * A capped counter is never rewritten or extended. Legacy integer state is
	 * preserved and migrated to the structured shape on the next accepted
	 * submission; a capped legacy counter whose known timeout has expired is
	 * treated as a fresh window, while a missing or future timeout stays capped.
	 *
	 * @param string $ip Requesting address.
	 * @return void
	 */
	public static function recordSubmission( string $ip ): void {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return;
		}

		$key   = self::rateKey( $ip );
		$state = self::decodeRateState( get_transient( $key ) );
		$now   = time();
		$count = $state['count'];

		if ( $state['legacy'] ) {
			// A live legacy transient is still inside its own window. Preserve
			// the count, but never extend a counter already at the cap.
			if ( $count >= SupportRequest::RATE_LIMIT_MAX ) {
				$timeout = function_exists( 'get_option' ) ? (int) get_option( '_transient_timeout_' . $key, 0 ) : 0;

				// A known but expired timeout means the window elapsed while the
				// cached value survived. Start a fresh window. A missing timeout
				// (persistent cache) or a future timeout stays capped and is
				// never rewritten or extended.
				if ( $timeout <= 0 || $timeout > $now ) {
					return;
				}

				$count = 0;
			}
		} elseif ( $state['expires'] <= $now ) {
			// Structured state survived past its embedded expiry (cache drift)
			// or was malformed; start a fresh window.
			$count = 0;
		}

		if ( $count >= SupportRequest::RATE_LIMIT_MAX ) {
			return;
		}

		set_transient(
			$key,
			[
				'count'   => $count + 1,
				'expires' => $now + SupportRequest::RATE_LIMIT_SECONDS,
			],
			SupportRequest::RATE_LIMIT_SECONDS
		);
	}

	/**
	 * Normalizes the screenshot upload into a list of single files.
	 *
	 * A multiple file control arrives grouped by attribute, so each entry is
	 * rebuilt into the single file shape the validators accept. Empty slots
	 * are dropped, and anything that is not an upload at all becomes no files.
	 *
	 * @param array<string, mixed>|null $raw Raw $_FILES entry, or null.
	 * @return array<int, array<string, mixed>> Single file entries.
	 */
	public static function normalizeScreenshots( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		if ( isset( $raw['name'] ) && is_array( $raw['name'] ) ) {
			$files = [];
			$count = count( $raw['name'] );

			foreach ( [ 'name', 'type', 'tmp_name', 'error', 'size' ] as $slot ) {
				if ( ! isset( $raw[ $slot ] ) || ! is_array( $raw[ $slot ] ) ) {
					return [];
				}
			}

			for ( $i = 0; $i < $count; $i++ ) {
				$error = (int) ( $raw['error'][ $i ] ?? UPLOAD_ERR_NO_FILE );

				if ( UPLOAD_ERR_NO_FILE === $error ) {
					continue;
				}

				$files[] = [
					'name'     => $raw['name'][ $i ] ?? '',
					'type'     => $raw['type'][ $i ] ?? '',
					'tmp_name' => $raw['tmp_name'][ $i ] ?? '',
					'error'    => $error,
					'size'     => $raw['size'][ $i ] ?? 0,
				];
			}

			return $files;
		}

		if ( isset( $raw['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $raw['error'] ) {
			return [ $raw ];
		}

		return [];
	}

	/**
	 * Refuses more screenshots than one request may carry.
	 *
	 * @param array<int, array<string, mixed>> $files Normalized files.
	 * @return true|WP_Error True when the count fits, otherwise the reason.
	 */
	public static function validateScreenshotCount( array $files ) {
		if ( count( $files ) <= SupportRequest::SCREENSHOT_MAX_COUNT ) {
			return true;
		}

		return new WP_Error(
			'support_screenshots_too_many',
			sprintf(
				/* translators: %d: maximum number of screenshots. */
				__( 'Please keep it to %d screenshots or fewer.', 'rankkernel' ),
				SupportRequest::SCREENSHOT_MAX_COUNT
			)
		);
	}

	/**
	 * Validates an uploaded screenshot.
	 *
	 * @param array<string, mixed>|null $file One entry from $_FILES, or null when nothing was sent.
	 * @return true|WP_Error True when usable, otherwise the reason.
	 */
	public static function validateScreenshot( $file ) {
		if ( null === $file || ! is_array( $file ) || ! isset( $file['error'] ) ) {
			return true;
		}

		$error = (int) $file['error'];

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return true;
		}

		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return new WP_Error(
				'support_screenshot_too_large',
				__( 'That screenshot is too large. Please keep it under 2 MB.', 'rankkernel' )
			);
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error(
				'support_screenshot_failed',
				__( 'The screenshot could not be uploaded. Please try again.', 'rankkernel' )
			);
		}

		$size = isset( $file['size'] ) && is_scalar( $file['size'] ) ? (int) $file['size'] : 0;

		if ( SupportRequest::SCREENSHOT_MAX_BYTES < $size ) {
			return new WP_Error(
				'support_screenshot_too_large',
				__( 'That screenshot is too large. Please keep it under 2 MB.', 'rankkernel' )
			);
		}

		$name = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
		$type = isset( $file['type'] ) && is_string( $file['type'] ) ? $file['type'] : '';
		$tmp  = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error(
				'support_screenshot_failed',
				__( 'The screenshot could not be read. Please try again.', 'rankkernel' )
			);
		}

		// The browser-supplied type is a hint, not evidence, so the file's own
		// contents decide what it is. A checker that cannot identify the file
		// fails closed rather than falling back to the client-supplied type.
		$checked = wp_check_filetype_and_ext( $tmp, $name, SupportRequest::screenshotMimes() );

		if ( ! is_array( $checked ) || empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error(
				'support_screenshot_type',
				__( 'The screenshot must be a PNG, JPEG, GIF or WebP image.', 'rankkernel' )
			);
		}

		$type = (string) $checked['type'];

		if ( ! in_array( $type, SupportRequest::screenshotTypes(), true ) ) {
			return new WP_Error(
				'support_screenshot_type',
				__( 'The screenshot must be a PNG, JPEG, GIF or WebP image.', 'rankkernel' )
			);
		}

		// The reported size is client-supplied. Measure the temporary file
		// itself before accepting it, otherwise a request can claim a small
		// size for a larger upload.
		clearstatcache( true, $tmp );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed stat on the verified upload temp path is handled as a rejection.
		$actual = @filesize( $tmp );

		if ( false === $actual || 0 === $actual ) {
			return new WP_Error(
				'support_screenshot_failed',
				__( 'The screenshot could not be read. Please try again.', 'rankkernel' )
			);
		}

		if ( SupportRequest::SCREENSHOT_MAX_BYTES < $actual ) {
			return new WP_Error(
				'support_screenshot_too_large',
				__( 'That screenshot is too large. Please keep it under 2 MB.', 'rankkernel' )
			);
		}

		return true;
	}

	/**
	 * Moves an accepted upload into the uploads directory.
	 *
	 * @param array<string, mixed> $file One entry from $_FILES.
	 * @return string|WP_Error Stored path, or the failure.
	 */
	public static function storeScreenshot( array $file ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			return new WP_Error(
				'support_screenshot_failed',
				__( 'Uploads are not available on this site.', 'rankkernel' )
			);
		}

		$base = function_exists( 'get_temp_dir' ) ? (string) get_temp_dir() : sys_get_temp_dir();
		$base = rtrim( $base, '/\\' );

		try {
			$suffix = bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $throwable ) {
			$suffix = str_replace( '.', '', uniqid( '', true ) );
		}

		$tempDir = $base . DIRECTORY_SEPARATOR . 'rankkernel-support-' . $suffix;

		if ( function_exists( 'wp_mkdir_p' ) ) {
			if ( ! wp_mkdir_p( $tempDir ) ) {
				return new WP_Error(
					'support_screenshot_failed',
					__( 'Uploads are not available on this site.', 'rankkernel' )
				);
			}
		} elseif ( ! mkdir( $tempDir, 0700, true ) && ! is_dir( $tempDir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- fallback when the WordPress helper is unavailable.
			return new WP_Error(
				'support_screenshot_failed',
				__( 'Uploads are not available on this site.', 'rankkernel' )
			);
		}

		if ( function_exists( 'chmod' ) ) {
			@chmod( $tempDir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.PHP.NoSilencedErrors.Discouraged -- private temp directory for one mail attachment.
		}

		$privateDir = static function ( array $uploads ) use ( $tempDir ): array {
			$uploads['path']    = $tempDir;
			$uploads['url']     = '';
			$uploads['subdir']  = '';
			$uploads['basedir'] = $tempDir;
			$uploads['baseurl'] = '';
			$uploads['error']   = false;

			return $uploads;
		};

		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'upload_dir', $privateDir );
		}

		$overrides = [
			'test_form' => false,
			'mimes'     => SupportRequest::screenshotMimes(),
		];

		// The stub types this as array, but the real function returns WP_Error
		// on rejection, so the union is declared rather than assumed.
		/**
		 * Stored upload result.
		 *
		 * @var array<string, mixed>|WP_Error $stored
		 */
		$stored = wp_handle_upload(
			$file,
			$overrides
		);

		if ( function_exists( 'remove_filter' ) ) {
			remove_filter( 'upload_dir', $privateDir );
		}

		if ( is_wp_error( $stored ) ) {
			self::removeTempDir( $tempDir );

			return new WP_Error( 'support_screenshot_failed', $stored->get_error_message() );
		}

		// wp_handle_upload() returns the stored path under 'file'; older test
		// doubles used 'path'. Accept both so the attachment is never lost.
		return (string) ( $stored['file'] ?? $stored['path'] ?? '' );
	}

	/**
	 * Remove a per-upload private directory when it is empty.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function removeTempDir( string $dir ): void {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . '/*' );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- cleanup of the private support temp directory.
				}
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- cleanup of the private support temp directory.
	}

	/**
	 * Remove stale private support directories left by an interrupted request.
	 *
	 * @param int $maxAge Maximum age in seconds.
	 * @return void
	 */
	public static function cleanupStaleScreenshots( int $maxAge = 3600 ): void {
		$base = function_exists( 'get_temp_dir' ) ? (string) get_temp_dir() : sys_get_temp_dir();
		$base = rtrim( $base, '/\\' );

		$dirs = glob( $base . DIRECTORY_SEPARATOR . 'rankkernel-support-*', GLOB_ONLYDIR );

		if ( ! is_array( $dirs ) ) {
			return;
		}

		$cutoff = time() - max( 60, $maxAge );

		foreach ( $dirs as $dir ) {
			if ( ! is_string( $dir ) || ! is_dir( $dir ) ) {
				continue;
			}

			$mtime = filemtime( $dir );

			if ( false !== $mtime && $mtime >= $cutoff ) {
				continue;
			}

			self::removeTempDir( $dir );
		}
	}

	/**
	 * Removes stored screenshots once the mail attempt has finished.
	 *
	 * A screenshot exists only to be attached to one email, so it is deleted
	 * whether the send succeeded or failed.
	 *
	 * @param array<int, string> $paths Stored file paths.
	 * @return void
	 */
	public static function deleteScreenshots( array $paths ): void {
		if ( ! function_exists( 'wp_delete_file' ) ) {
			return;
		}

		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || '' === $path ) {
				continue;
			}

			wp_delete_file( $path );

			if ( file_exists( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- fallback when wp_delete_file did not remove the private attachment.
			}

			$dir = dirname( $path );

			if ( str_contains( basename( $dir ), 'rankkernel-support-' ) ) {
				self::removeTempDir( $dir );
			}
		}
	}

	/**
	 * Sends a validated submission.
	 *
	 * @param array<string, string> $values Validated values.
	 * @param array<int, string>    $attachments Absolute paths to attach.
	 * @return true|WP_Error True when accepted by the mail transport.
	 */
	public static function send( array $values, array $attachments = [] ) {
		if ( ! function_exists( 'wp_mail' ) ) {
			return new WP_Error( 'support_mail_unavailable', __( 'Email is not available on this site.', 'rankkernel' ) );
		}

		// Site details travel with every request, so the issue can be reproduced
		// in the same environment. There is no opt out on the form.
		$diagnostics = SupportRequest::diagnostics();

		$subject = sprintf(
			/* translators: %s: support category label. */
			__( '[RankKernel support] %s', 'rankkernel' ),
			SupportRequest::categories()[ $values['category'] ?? '' ] ?? __( 'Question', 'rankkernel' )
		);

		$sent = wp_mail(
			SupportRequest::RECIPIENT,
			$subject,
			SupportRequest::body( $values, $diagnostics ),
			[
				'Content-Type: text/plain; charset=UTF-8',
				sprintf( 'Reply-To: %s', $values['email'] ?? '' ),
			],
			$attachments
		);

		return $sent
			? true
			: new WP_Error( 'support_mail_failed', __( 'The message could not be sent. Please email us directly.', 'rankkernel' ) );
	}
}
