<?php
/**
 * Support request validation and delivery.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Validates a support submission and hands it to wp_mail().
 *
 * The limits here are the single source of truth for the form. The admin
 * script is localised with the same values, so the live validation in the
 * browser cannot drift from what the server accepts.
 */
final class SupportRequest {

	/**
	 * Address that submissions are delivered to.
	 */
	public const RECIPIENT = 'rankkernelsupport@gmail.com';

	/**
	 * Nonce action for the support form.
	 */
	public const NONCE_ACTION = 'rankkernel_support_submit';

	/**
	 * Form field name carrying the submission marker.
	 */
	public const FIELD_SUBMIT = 'rankkernel_support_submit';

	/**
	 * Field name of the honeypot.
	 */
	public const FIELD_HONEYPOT = 'rankkernel_support_website';

	/**
	 * Field name of the consent checkbox.
	 */
	public const FIELD_CONSENT = 'rankkernel_support_consent';

	/**
	 * Field name of the screenshot upload.
	 */
	public const FIELD_SCREENSHOT = 'rankkernel_support_screenshot';

	/**
	 * Shortest accepted subject.
	 */
	public const SUBJECT_MIN = 4;

	/**
	 * Longest accepted subject.
	 */
	public const SUBJECT_MAX = 150;

	/**
	 * Shortest accepted message.
	 */
	public const MESSAGE_MIN = 20;

	/**
	 * Longest accepted message.
	 */
	public const MESSAGE_MAX = 5000;

	/**
	 * Largest accepted screenshot, in bytes.
	 */
	public const SCREENSHOT_MAX_BYTES = 2097152;

	/**
	 * Most screenshots accepted with one request.
	 */
	public const SCREENSHOT_MAX_COUNT = 5;

	/**
	 * Seconds a single IP has to wait between submissions.
	 */
	public const RATE_LIMIT_SECONDS = 300;

	/**
	 * Maximum submissions kept per rate-limit window, per IP.
	 */
	public const RATE_LIMIT_MAX = 3;

	/**
	 * Supported categories.
	 *
	 * @return array<string, string> Value to label.
	 */
	public static function categories(): array {
		return [
			'bug'        => __( 'Something is not working', 'rankkernel' ),
			'suggestion' => __( 'Suggestion or feature request', 'rankkernel' ),
			'question'   => __( 'Question about using RankKernel', 'rankkernel' ),
		];
	}

	/**
	 * Image types accepted as a screenshot.
	 *
	 * @return array<int, string> Accepted MIME types.
	 */
	public static function screenshotTypes(): array {
		return [
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
		];
	}

	/**
	 * Limits published to the browser so live validation matches the server.
	 *
	 * @return array<string, int> Limit name to value.
	 */
	public static function limits(): array {
		return [
			'subjectMin'         => self::SUBJECT_MIN,
			'subjectMax'         => self::SUBJECT_MAX,
			'messageMin'         => self::MESSAGE_MIN,
			'messageMax'         => self::MESSAGE_MAX,
			'screenshotMaxBytes' => self::SCREENSHOT_MAX_BYTES,
			'screenshotMaxCount' => self::SCREENSHOT_MAX_COUNT,
		];
	}

	/**
	 * Validates a submission.
	 *
	 * @param array<string, string> $input Sanitised field values keyed by field name.
	 * @return array{errors: array<string, string>, values: array<string, string>} Errors keyed by field, and the cleaned values to redisplay.
	 */
	public static function validate( array $input ): array {
		$errors = [];
		$values = [];

		$category = trim( (string) ( $input['category'] ?? '' ) );

		if ( '' === $category || ! isset( self::categories()[ $category ] ) ) {
			$errors['category'] = __( 'Choose what this is about.', 'rankkernel' );
		} else {
			$values['category'] = $category;
		}

		$subject = trim( (string) ( $input['subject'] ?? '' ) );

		if ( '' === $subject ) {
			$errors['subject'] = __( 'Add a short subject.', 'rankkernel' );
		} elseif ( self::SUBJECT_MIN > mb_strlen( $subject ) ) {
			$errors['subject'] = sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'The subject must be at least %d characters.', 'rankkernel' ),
				self::SUBJECT_MIN
			);
		} elseif ( self::SUBJECT_MAX < mb_strlen( $subject ) ) {
			$errors['subject'] = sprintf(
				/* translators: %d: maximum number of characters. */
				__( 'The subject must be %d characters or fewer.', 'rankkernel' ),
				self::SUBJECT_MAX
			);
		} else {
			$values['subject'] = $subject;
		}

		$message = trim( (string) ( $input['message'] ?? '' ) );

		if ( '' === $message ) {
			$errors['message'] = __( 'Describe what happened.', 'rankkernel' );
		} elseif ( self::MESSAGE_MIN > mb_strlen( $message ) ) {
			$errors['message'] = sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'Please add a little more detail, at least %d characters.', 'rankkernel' ),
				self::MESSAGE_MIN
			);
		} elseif ( self::MESSAGE_MAX < mb_strlen( $message ) ) {
			$errors['message'] = sprintf(
				/* translators: %d: maximum number of characters. */
				__( 'The message must be %d characters or fewer.', 'rankkernel' ),
				self::MESSAGE_MAX
			);
		} else {
			$values['message'] = $message;
		}

		$email = trim( (string) ( $input['email'] ?? '' ) );

		if ( '' === $email ) {
			$errors['email'] = __( 'Add an email address so we can reply.', 'rankkernel' );
		} elseif ( ! is_email( $email ) ) {
			$errors['email'] = __( 'That email address does not look valid.', 'rankkernel' );
		} elseif ( 254 < strlen( $email ) ) {
			$errors['email'] = __( 'That email address is too long.', 'rankkernel' );
		} else {
			$values['email'] = $email;
		}

		if ( empty( $input['consent'] ) ) {
			$errors['consent'] = __( 'Please confirm before sending.', 'rankkernel' );
		}

		$values['diagnostics'] = empty( $input['diagnostics'] ) ? '0' : '1';

		return [
			'errors' => $errors,
			'values' => $values,
		];
	}

	/**
	 * Builds the plain text body for a validated submission.
	 *
	 * @param array<string, string> $values Validated values.
	 * @param string                $diagnostics Diagnostic block, or an empty string.
	 * @return string Message body.
	 */
	public static function body( array $values, string $diagnostics = '' ): string {
		$labels = self::categories();
		$lines  = [];

		$lines[] = sprintf(
			'Category: %s',
			$labels[ $values['category'] ?? '' ] ?? ( $values['category'] ?? '' )
		);
		$lines[] = sprintf( 'Subject: %s', $values['subject'] ?? '' );
		$lines[] = sprintf( 'Reply to: %s', $values['email'] ?? '' );
		$lines[] = '';
		$lines[] = (string) ( $values['message'] ?? '' );

		if ( '' !== $diagnostics ) {
			$lines[] = '';
			$lines[] = '--- Site details ---';
			$lines[] = $diagnostics;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Collects site details for a submission.
	 *
	 * @return array<string, string> Label to value, empty values included so
	 *                               the screen preview and the email agree.
	 */
	public static function diagnosticRows(): array {
		return [
			'RankKernel' => defined( 'RANKKERNEL_VERSION' ) ? (string) RANKKERNEL_VERSION : self::pluginVersion(),
			'WordPress'  => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
			'PHP'        => PHP_VERSION,
			'Theme'      => self::themeName(),
			'Site'       => function_exists( 'home_url' ) ? (string) home_url() : '',
			'Multisite'  => function_exists( 'is_multisite' ) && is_multisite() ? 'yes' : 'no',
		];
	}

	/**
	 * Collects site details for a submission.
	 *
	 * @return string Diagnostic block.
	 */
	public static function diagnostics(): string {
		$lines = array();

		foreach ( self::diagnosticRows() as $label => $value ) {
			if ( '' !== (string) $value ) {
				$lines[] = $label . ': ' . $value;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Reads the plugin version.
	 *
	 * @return string Version string.
	 */
	private static function pluginVersion(): string {
		if ( class_exists( '\RankKernel\Plugin' ) && method_exists( '\RankKernel\Plugin', 'version' ) ) {
			return (string) \RankKernel\Plugin::version();
		}

		return '';
	}

	/**
	 * Reads the active theme name.
	 *
	 * @return string Theme name and version.
	 */
	private static function themeName(): string {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return '';
		}

		$theme = wp_get_theme();

		if ( ! is_object( $theme ) || ! method_exists( $theme, 'get' ) ) {
			return '';
		}

		$name = (string) $theme->get( 'Name' );

		return '' === $name ? '' : $name;
	}
}
