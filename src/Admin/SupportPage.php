<?php
/**
 * Support request admin screen.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Plugin;
use RankKernel\Support\SupportDelivery;
use RankKernel\Support\SupportRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the support form and handles its submission.
 */
final class SupportPage {

	/**
	 * Menu slug for the screen.
	 */
	public const SLUG = 'rankkernel-support';

	/**
	 * Admin hook suffix, which is the parent slug plus the screen slug.
	 */
	public const HOOK_SUFFIX = 'rankkernel_page_' . self::SLUG;

	/**
	 * Capability required to view and submit the form.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Query argument carrying a notice key after a redirect.
	 */
	public const NOTICE_ARG = 'rk_support';

	/**
	 * Field errors carried across the redirect.
	 *
	 * @var array<string, string>
	 */
	private array $errors = [];

	/**
	 * Values to redisplay after a failed submission.
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	/**
	 * Handles a submission before any output, so the redirect can send headers.
	 *
	 * @return void
	 */
	public function maybeHandleSave(): void {
		// Delegates to a handler which verifies capability plus its own nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- delegates to a handler which verifies capability plus its own nonce.
		$requestMethod = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $requestMethod ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified inside handleSubmit.
		if ( ! isset( $_POST[ SupportRequest::FIELD_SUBMIT ] ) ) {
			return;
		}

		$this->handleSubmit();
	}

	/**
	 * Validates and delivers a submission, then redirects.
	 *
	 * @return void
	 */
	private function handleSubmit(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to send a support request.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		check_admin_referer( SupportRequest::NONCE_ACTION );

		SupportDelivery::cleanupStaleScreenshots();

		$honeypot = isset( $_POST[ SupportRequest::FIELD_HONEYPOT ] ) && is_string( $_POST[ SupportRequest::FIELD_HONEYPOT ] )
			? sanitize_text_field( wp_unslash( $_POST[ SupportRequest::FIELD_HONEYPOT ] ) )
			: '';

		// A filled honeypot is reported as sent, so a bot learns nothing.
		if ( ! SupportDelivery::passesHoneypot( $honeypot ) ) {
			$this->redirect( 'sent' );

			return;
		}

		$ip    = $this->requestIp();
		$retry = SupportDelivery::retryAfter( $ip );

		if ( 0 < $retry ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %d: number of seconds. */
						__( 'You have sent several requests already. Please wait %d seconds and try again.', 'rankkernel' ),
						$retry
					)
				),
				'',
				[ 'response' => 429 ]
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above, each value sanitized below.
		$input = [
			'category'    => isset( $_POST['category'] ) && is_string( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'subject'     => isset( $_POST['subject'] ) && is_string( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'message'     => isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			'email'       => isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'consent'     => isset( $_POST[ SupportRequest::FIELD_CONSENT ] ) ? '1' : '',
			// Site details are always sent, so the value is fixed here rather
			// than read from a control that no longer exists.
			'diagnostics' => '1',
		];
		// Raw upload metadata is sanitized at the boundary before normalization.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above; every upload field is sanitized in sanitizeScreenshotUpload() below.
		$rawFile = isset( $_FILES[ SupportRequest::FIELD_SCREENSHOT ] ) ? $_FILES[ SupportRequest::FIELD_SCREENSHOT ] : null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$file = $this->sanitizeScreenshotUpload( $rawFile );

		$check = SupportRequest::validate( $input );

		$this->values = $check['values'];
		$this->errors = $check['errors'];

		$files = SupportDelivery::normalizeScreenshots( $file );

		$countCheck = SupportDelivery::validateScreenshotCount( $files );

		if ( is_wp_error( $countCheck ) ) {
			$this->errors['screenshot'] = $countCheck->get_error_message();
		} else {
			foreach ( $files as $single ) {
				$upload = SupportDelivery::validateScreenshot( $single );

				if ( is_wp_error( $upload ) ) {
					$this->errors['screenshot'] = $upload->get_error_message();

					break;
				}
			}
		}

		// The upload is only moved into the uploads directory once every other
		// field has passed, so a rejected form never leaves a file behind.
		if ( [] !== $this->errors ) {
			$this->redirect( 'invalid' );

			return;
		}

		$attachments = [];

		foreach ( $files as $single ) {
			$stored = SupportDelivery::storeScreenshot( $single );

			if ( is_wp_error( $stored ) ) {
				// A sibling file may already be stored, so those are removed
				// before bouncing back.
				SupportDelivery::deleteScreenshots( $attachments );
				$this->errors['screenshot'] = $stored->get_error_message();
				$this->redirect( 'invalid' );

				return;
			}

			if ( '' !== $stored ) {
				$attachments[] = $stored;
			}
		}

		$sent = SupportDelivery::send( $this->values, $attachments );

		// The screenshot exists only to ride along with this one email, so it
		// is removed whether the send succeeded or failed.
		SupportDelivery::deleteScreenshots( $attachments );

		if ( is_wp_error( $sent ) ) {
			$this->errors['form'] = $sent->get_error_message();
			$this->redirect( 'invalid' );

			return;
		}

		SupportDelivery::recordSubmission( $ip );
		$this->redirect( 'sent' );
	}

	/**
	 * Sanitizes raw upload metadata at the boundary.
	 *
	 * Builds a sanitized copy of a $_FILES entry before it reaches
	 * SupportDelivery::normalizeScreenshots(), preserving the single-file
	 * versus multi-file shape so valid uploads flow through unchanged.
	 * Downstream validation (is_uploaded_file, MIME sniff, actual size,
	 * private temp dir, cleanup) is untouched.
	 *
	 * @param mixed $raw Raw $_FILES entry, or null.
	 * @return array<string, mixed>|null Sanitized copy, or null when not an upload array.
	 */
	private function sanitizeScreenshotUpload( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$sanitized = [];

		if ( array_key_exists( 'name', $raw ) ) {
			if ( is_array( $raw['name'] ) ) {
				$sanitized['name'] = array_map(
					static fn ( $v ): string => is_string( $v ) ? sanitize_file_name( $v ) : '',
					$raw['name']
				);
			} else {
				$sanitized['name'] = is_string( $raw['name'] ) ? sanitize_file_name( $raw['name'] ) : '';
			}
		}

		if ( array_key_exists( 'type', $raw ) ) {
			$cleanType = static function ( $v ): string {
				if ( ! is_string( $v ) ) {
					return '';
				}

				if ( function_exists( 'sanitize_mime_type' ) ) {
					return sanitize_mime_type( $v );
				}

				return sanitize_text_field( $v );
			};

			if ( is_array( $raw['type'] ) ) {
				$sanitized['type'] = array_map( $cleanType, $raw['type'] );
			} else {
				$sanitized['type'] = $cleanType( $raw['type'] );
			}
		}

		if ( array_key_exists( 'tmp_name', $raw ) ) {
			if ( is_array( $raw['tmp_name'] ) ) {
				$sanitized['tmp_name'] = array_map(
					static fn ( $v ): string => is_string( $v ) ? $v : '',
					$raw['tmp_name']
				);
			} else {
				$sanitized['tmp_name'] = is_string( $raw['tmp_name'] ) ? $raw['tmp_name'] : '';
			}
		}

		if ( array_key_exists( 'error', $raw ) ) {
			$cleanError = static function ( $v ): int {
				return absint( is_scalar( $v ) ? $v : 0 );
			};

			if ( is_array( $raw['error'] ) ) {
				$sanitized['error'] = array_map( $cleanError, $raw['error'] );
			} else {
				$sanitized['error'] = $cleanError( $raw['error'] );
			}
		}

		if ( array_key_exists( 'size', $raw ) ) {
			$cleanSize = static function ( $v ): int {
				return max( 0, absint( is_scalar( $v ) ? $v : 0 ) );
			};

			if ( is_array( $raw['size'] ) ) {
				$sanitized['size'] = array_map( $cleanSize, $raw['size'] );
			} else {
				$sanitized['size'] = $cleanSize( $raw['size'] );
			}
		}

		return $sanitized;
	}

	/**
	 * Reads the requesting address for rate limiting.
	 *
	 * @return string Address string.
	 */
	private function requestIp(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return '' === $raw ? 'unknown' : $raw;
	}

	/**
	 * Sends the post-redirect-get redirect, carrying only a notice key.
	 *
	 * Field text never enters the URL; the view revalidates the submission
	 * from the stored values instead.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect( string $notice ): void {
		$url = add_query_arg( self::NOTICE_ARG, $notice, $this->pageUrl() );

		// Field errors survive the redirect in a transient, keyed per user, so
		// nothing sensitive lands in the address bar or in browser history.
		if ( 'invalid' === $notice ) {
			$this->stashErrors();
		}

		wp_safe_redirect( $url );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Stores field errors and redisplay values for the next request.
	 *
	 * @return void
	 */
	private function stashErrors(): void {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}

		$payload = [
			'errors' => $this->errors,
			'values' => $this->values,
		];

		set_transient( $this->stashKey(), $payload, 300 );
	}

	/**
	 * Transient key for this user's stashed errors.
	 *
	 * @return string Transient key.
	 */
	private function stashKey(): string {
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		return 'rankkernel_support_stash_' . $user;
	}

	/**
	 * Reads and clears any stashed field errors.
	 *
	 * @return array<string, string> Errors keyed by field.
	 */
	private function takeStashedErrors(): array {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'delete_transient' ) ) {
			return [];
		}

		$key     = $this->stashKey();
		$payload = get_transient( $key );

		if ( ! is_array( $payload ) ) {
			return [];
		}

		delete_transient( $key );

		$errors = isset( $payload['errors'] ) && is_array( $payload['errors'] ) ? $payload['errors'] : [];
		$values = isset( $payload['values'] ) && is_array( $payload['values'] ) ? $payload['values'] : [];

		$this->errors = array_map( 'strval', $errors );
		$this->values = array_map( 'strval', $values );

		return $this->errors;
	}

	/**
	 * URL of this screen.
	 *
	 * @return string Admin URL.
	 */
	private function pageUrl(): string {
		if ( function_exists( 'admin_url' ) ) {
			return admin_url( 'admin.php?page=' . self::SLUG );
		}

		return '';
	}

	/**
	 * Notice key from the current request.
	 *
	 * @return string Notice key, or an empty string.
	 */
	public function notice(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
		$key = isset( $_GET[ self::NOTICE_ARG ] ) && is_string( $_GET[ self::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_ARG ] ) ) : '';

		return in_array( $key, [ 'sent', 'invalid' ], true ) ? $key : '';
	}

	/**
	 * Field errors for redisplay.
	 *
	 * @return array<string, string> Errors keyed by field.
	 */
	public function errors(): array {
		if ( [] === $this->errors ) {
			$this->takeStashedErrors();
		}

		return $this->errors;
	}

	/**
	 * Values for redisplay.
	 *
	 * @return array<string, string> Values keyed by field.
	 */
	public function values(): array {
		$this->errors();

		return $this->values;
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		$notice         = $this->notice();
		$errors         = $this->errors();
		$values         = $this->values();
		$categories     = SupportRequest::categories();
		$version        = defined( 'RANKKERNEL_VERSION' ) ? (string) RANKKERNEL_VERSION : '';
		$diagnosticRows = SupportRequest::diagnosticRows();

		$view = __DIR__ . '/Views/support.php';

		if ( is_readable( $view ) ) {
			require $view;
		}
	}

	/**
	 * Enqueues the screen stylesheet and script.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueueAssets( string $hookSuffix = '' ): void {
		if ( self::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		$file = defined( 'RANKKERNEL_FILE' ) ? (string) RANKKERNEL_FILE : '';

		AdminStyles::enqueueTokenLayer( $file );

		if ( ! function_exists( 'plugins_url' ) || ! function_exists( 'wp_register_style' ) ) {
			return;
		}

		wp_register_style(
			'rankkernel-support-admin',
			plugins_url( 'assets/css/support-admin.css', RANKKERNEL_FILE ),
			[ AdminStyles::TOKEN_HANDLE, AdminStyles::UI_HANDLE ],
			Plugin::version()
		);

		wp_register_script(
			'rankkernel-support-admin',
			plugins_url( 'assets/js/support-admin.js', RANKKERNEL_FILE ),
			[],
			Plugin::version(),
			true
		);

		if ( function_exists( 'wp_localize_script' ) ) {
			wp_localize_script(
				'rankkernel-support-admin',
				'rankkernelSupport',
				[
					'limits'     => SupportRequest::limits(),
					'categories' => array_keys( SupportRequest::categories() ),
					'i18n'       => [
						/* translators: %d: minimum number of characters. */
						'subjectMin'         => __( 'The subject must be at least %d characters.', 'rankkernel' ),
						/* translators: %d: maximum number of characters. */
						'subjectMax'         => __( 'The subject must be %d characters or fewer.', 'rankkernel' ),
						/* translators: %d: minimum number of characters. */
						'messageMin'         => __( 'Please add a little more detail, at least %d characters.', 'rankkernel' ),
						/* translators: %d: maximum number of characters. */
						'messageMax'         => __( 'The message must be %d characters or fewer.', 'rankkernel' ),
						'screenshotTooLarge' => __( 'That screenshot is too large. Please keep it under 2 MB.', 'rankkernel' ),
						'screenshotType'     => __( 'The screenshot must be a PNG, JPEG, GIF or WebP image.', 'rankkernel' ),
						/* translators: %d: maximum number of screenshots. */
													'screenshotTooMany' => __( 'Please keep it to %d screenshots or fewer.', 'rankkernel' ),
						/* translators: %d: number of selected files. */
						'filesSelected'      => __( '%d files selected', 'rankkernel' ),
						'removeFile'         => __( 'Remove', 'rankkernel' ),
						'emailInvalid'       => __( 'That email address does not look valid.', 'rankkernel' ),
						'emailRequired'      => __( 'Add an email address so we can reply.', 'rankkernel' ),
						'categoryRequired'   => __( 'Choose what this is about.', 'rankkernel' ),
						'consentRequired'    => __( 'Please confirm before sending.', 'rankkernel' ),
						'subjectRequired'    => __( 'Add a short subject.', 'rankkernel' ),
						'messageRequired'    => __( 'Describe what happened.', 'rankkernel' ),
					],
				]
			);
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'rankkernel-support-admin' );
		}

		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'rankkernel-support-admin' );
		}
	}
}
