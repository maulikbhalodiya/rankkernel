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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to a handler which verifies capability plus its own nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
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

		if ( ! check_admin_referer( SupportRequest::NONCE_ACTION ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$honeypot = isset( $_POST[ SupportRequest::FIELD_HONEYPOT ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ SupportRequest::FIELD_HONEYPOT ] ) )
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
			'category'    => sanitize_text_field( wp_unslash( (string) ( $_POST['category'] ?? '' ) ) ),
			'subject'     => sanitize_text_field( wp_unslash( (string) ( $_POST['subject'] ?? '' ) ) ),
			'message'     => sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) ),
			'email'       => sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ),
			'consent'     => isset( $_POST[ SupportRequest::FIELD_CONSENT ] ) ? '1' : '',
			'diagnostics' => isset( $_POST[ SupportRequest::FIELD_DIAGNOSTICS ] ) ? '1' : '',
		];
		$file  = isset( $_FILES[ SupportRequest::FIELD_SCREENSHOT ] ) ? $_FILES[ SupportRequest::FIELD_SCREENSHOT ] : null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$check = SupportRequest::validate( $input );

		$this->values = $check['values'];
		$this->errors = $check['errors'];

		$upload = SupportDelivery::validateScreenshot( $file );

		if ( is_wp_error( $upload ) ) {
			$this->errors['screenshot'] = $upload->get_error_message();
		}

		// The upload is only moved into the uploads directory once every other
		// field has passed, so a rejected form never leaves a file behind.
		if ( [] !== $this->errors ) {
			$this->redirect( 'invalid' );

			return;
		}

		$attachments = [];

		if ( is_array( $file ) ) {
			$stored = SupportDelivery::storeScreenshot( $file );

			if ( is_wp_error( $stored ) ) {
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
	 * Reads the requesting address for rate limiting.
	 *
	 * @return string Address string.
	 */
	private function requestIp(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

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
		$key = isset( $_GET[ self::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::NOTICE_ARG ] ) ) : '';

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
		$notice     = $this->notice();
		$errors     = $this->errors();
		$values     = $this->values();
		$categories = SupportRequest::categories();

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
						'emailInvalid'       => __( 'Enter an email address we can reply to.', 'rankkernel' ),
						'categoryRequired'   => __( 'Choose what this is about.', 'rankkernel' ),
						'consentRequired'    => __( 'Please confirm you understand this message is emailed to the plugin author.', 'rankkernel' ),
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
