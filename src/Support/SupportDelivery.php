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
	 * Whether this address may submit again.
	 *
	 * @param string $ip Requesting address.
	 * @return int Seconds remaining, or 0 when a submission is allowed.
	 */
	public static function retryAfter( string $ip ): int {
		if ( ! function_exists( 'get_transient' ) ) {
			return 0;
		}

		$count = (int) get_transient( self::rateKey( $ip ) );

		if ( $count < SupportRequest::RATE_LIMIT_MAX ) {
			return 0;
		}

		$ttl = function_exists( 'get_option' ) ? (int) get_option( '_transient_timeout_' . self::rateKey( $ip ), 0 ) : 0;

		if ( $ttl <= 0 ) {
			return 0;
		}

		$now       = function_exists( 'current_time' ) ? (int) time() : (int) time();
		$remaining = $ttl - $now;

		// A window whose expiry has already passed is not a block. Clamping to
		// one second here would refuse a legitimate submission instead.
		return $remaining > 0 ? $remaining : 0;
	}

	/**
	 * Records a submission against the rate limit.
	 *
	 * @param string $ip Requesting address.
	 * @return void
	 */
	public static function recordSubmission( string $ip ): void {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return;
		}

		$key   = self::rateKey( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= SupportRequest::RATE_LIMIT_MAX ) {
			return;
		}

		set_transient( $key, $count + 1, SupportRequest::RATE_LIMIT_SECONDS );
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

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( SupportRequest::SCREENSHOT_MAX_BYTES < $size ) {
			return new WP_Error(
				'support_screenshot_too_large',
				__( 'That screenshot is too large. Please keep it under 2 MB.', 'rankkernel' )
			);
		}

		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$type = isset( $file['type'] ) ? (string) $file['type'] : '';
		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error(
				'support_screenshot_failed',
				__( 'The screenshot could not be read. Please try again.', 'rankkernel' )
			);
		}

		// The browser-supplied type is a hint, not evidence, so the file's own
		// contents decide what it is.
		$mimes = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		];

		$checked = wp_check_filetype_and_ext( $tmp, $name, $mimes );

		if ( ! empty( $checked['type'] ) ) {
			$type = (string) $checked['type'];
		}

		if ( ! in_array( $type, SupportRequest::screenshotTypes(), true ) ) {
			return new WP_Error(
				'support_screenshot_type',
				__( 'The screenshot must be a PNG, JPEG, GIF or WebP image.', 'rankkernel' )
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
		if ( ! function_exists( 'wp_upload_dir' ) || ! function_exists( 'wp_handle_upload' ) ) {
			return new WP_Error(
				'support_screenshot_failed',
				__( 'Uploads are not available on this site.', 'rankkernel' )
			);
		}

		// Explicit MIME map ensuring jpg, jpeg, png, gif, webp are all accepted.
		$mimes = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		];

		$overrides = [
			'test_form' => false,
			'mimes'     => $mimes,
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

		if ( is_wp_error( $stored ) ) {
			return new WP_Error( 'support_screenshot_failed', $stored->get_error_message() );
		}

		return (string) ( $stored['path'] ?? '' );
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
			if ( is_string( $path ) && '' !== $path ) {
				wp_delete_file( $path );
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
