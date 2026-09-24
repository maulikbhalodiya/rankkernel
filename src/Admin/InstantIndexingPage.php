<?php
/**
 * Instant Indexing admin page, key status plus manual submit.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\InstantIndexing\InstantIndexingModule;

/**
 * Renders the Instant Indexing screen and handles its writes.
 *
 * The API key never reaches the browser. The screen reports whether a key
 * is configured and never the key itself, no field carries it, and
 * regeneration never echoes the replacement. A manual submission is
 * validated against the site host before the module entry point sees it,
 * and the submitted URL is never fetched.
 */
final class InstantIndexingPage {
	/**
	 * Menu slug for the screen.
	 */
	public const SLUG = 'rankkernel-instant-indexing';

	/**
	 * Hook suffix for the screen, used to gate asset loading.
	 */
	public const HOOK_SUFFIX = 'rankkernel_page_rankkernel-instant-indexing';

	/**
	 * Capability required for every write on this screen.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Nonce action for the auto submit toggle.
	 */
	private const NONCE_SAVE = 'rankkernel_indexnow_save';

	/**
	 * Nonce action for key regeneration.
	 */
	private const NONCE_REGENERATE = 'rankkernel_indexnow_regenerate';

	/**
	 * Nonce action for the manual submit form.
	 */
	private const NONCE_SUBMIT = 'rankkernel_indexnow_submit';

	/**
	 * Settings store.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Submission callback, one validated URL in.
	 *
	 * @var callable(string): void
	 */
	private $submit;

	/**
	 * Constructor.
	 *
	 * The default callback routes through InstantIndexingModule, the
	 * single submission entry point, and never calls the client directly.
	 *
	 * @param IndexNowSettings|null       $settings Settings store, fresh one when null.
	 * @param callable(string): void|null $submit   Submission callback, null uses the module entry point.
	 */
	public function __construct( ?IndexNowSettings $settings = null, ?callable $submit = null ) {
		$this->settings = $settings ?? new IndexNowSettings();
		$this->submit   = $submit ?? function ( string $url ): void {
			( new InstantIndexingModule( null, $this->settings ) )->submitUrls( [ $url ], 'manual' );
		};
	}

	/**
	 * Handle a posted form on the load hook, before any output is sent.
	 *
	 * Runs on load rankkernel page rankkernel instant indexing, so wp safe
	 * redirect can still send headers. The marker field selects one of
	 * three branches and each branch verifies capability plus its own
	 * nonce before writing.
	 *
	 * @return void
	 */
	public function maybeHandleSave(): void {
		switch ( $this->postedAction() ) {
			case 'save':
				$this->handleSave();
				break;
			case 'regenerate':
				$this->handleRegenerate();
				break;
			case 'submit':
				$this->handleSubmit();
				break;
		}
	}

	/**
	 * Enqueue screen assets, and only on this screen.
	 *
	 * The screen renders with the standard wp-admin styles and needs no
	 * script, so no asset is registered. The gate keeps the hook contract
	 * shared with the other module pages.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( self::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}
	}

	/**
	 * Prepare the view state and load the Instant Indexing view.
	 *
	 * The key is reduced to a boolean here, so the view never receives
	 * the key value and cannot render it by mistake.
	 *
	 * @return void
	 */
	public function render(): void {
		// Read only display flag, compared strictly against a literal, never stored or output.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read only display flag, compared strictly against a literal, never stored or output.
		$settingsUpdated = isset( $_GET['settings-updated'] ) && '1' === $_GET['settings-updated'];

		$keyConfigured = '' !== $this->settings->getKey();
		$autoSubmit    = $this->settings->getAutoSubmit();
		$logRows       = [];

		foreach ( $this->settings->logEntries() as $entry ) {
			$logRows[] = [
				'url'     => (string) ( $entry['url'] ?? '' ),
				'code'    => (int) ( $entry['code'] ?? 0 ),
				'source'  => (string) ( $entry['source'] ?? '' ),
				'time'    => (string) ( $entry['time'] ?? '' ),
				'message' => (string) ( $entry['message'] ?? '' ),
			];
		}

		$nonceSave       = self::NONCE_SAVE;
		$nonceRegenerate = self::NONCE_REGENERATE;
		$nonceSubmit     = self::NONCE_SUBMIT;

		require __DIR__ . '/Views/instant-indexing.php';
	}

	/**
	 * Handle the auto submit toggle.
	 *
	 * Absent from POST means false, matching the purge_on_uninstall
	 * pattern on the settings page.
	 *
	 * @return void
	 */
	private function handleSave(): void {
		$this->requireAccess( self::NONCE_SAVE );

		// Verified in requireAccess, checkbox presence is the value.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in requireAccess, checkbox presence is the value.
		$this->settings->set( [ 'auto_submit' => isset( $_POST['rankkernel_indexnow_auto_submit'] ) ] );

		$this->redirectTo();
	}

	/**
	 * Handle key regeneration, never echoing the replacement.
	 *
	 * @return void
	 */
	private function handleRegenerate(): void {
		$this->requireAccess( self::NONCE_REGENERATE );

		$this->settings->resetKey();

		$this->redirectTo();
	}

	/**
	 * Handle a manual submission.
	 *
	 * The URL must pass wp_http_validate_url and its host must equal the
	 * site host, www and the apex are different hosts. A rejected URL is
	 * logged and the submit callback is never reached, so no request is
	 * ever made to it.
	 *
	 * @return void
	 */
	private function handleSubmit(): void {
		$this->requireAccess( self::NONCE_SUBMIT );

		$url = $this->postedUrl();

		if ( '' === $url || ! function_exists( 'wp_http_validate_url' ) ) {
			$this->reject( $url, __( 'Rejected: the URL could not be validated.', 'rankkernel' ) );

			return;
		}

		$validated = wp_http_validate_url( $url );

		if ( ! is_string( $validated ) || ! $this->isSiteHost( $validated ) ) {
			$this->reject( $url, __( 'Rejected: only URLs on this site can be submitted.', 'rankkernel' ) );

			return;
		}

		( $this->submit )( $validated );

		$this->redirectTo();
	}

	/**
	 * Log a rejected manual submission and reload the screen, no request made.
	 *
	 * @param string $url    Submitted URL, logged for the operator.
	 * @param string $reason Human readable rejection reason.
	 * @return void
	 */
	private function reject( string $url, string $reason ): void {
		$this->settings->logEntry( $url, 0, 'manual', $reason );

		$this->redirectTo( false );
	}

	/**
	 * Action marker from POST, empty when absent or not a string.
	 *
	 * @return string The result.
	 */
	private function postedAction(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- action marker only, compared strictly against literals, each branch verifies capability plus its own nonce.
		$raw = $_POST['rankkernel_indexnow_action'] ?? '';

		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Submitted URL from POST, empty when absent or not a string.
	 *
	 * The value is never trusted here. It must pass wp_http_validate_url
	 * and the site host check before the submit callback sees it.
	 *
	 * @return string The result.
	 */
	private function postedUrl(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified by the caller, value validated by wp_http_validate_url plus the host check before use.
		$raw = $_POST['rankkernel_indexnow_url'] ?? '';

		return is_string( $raw ) ? trim( $raw ) : '';
	}

	/**
	 * Verify capability plus nonce, stopping with 403 otherwise.
	 *
	 * @param string $nonceAction Nonce action expected for this write.
	 * @return void
	 */
	private function requireAccess( string $nonceAction ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to manage Instant Indexing.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		if ( false === check_admin_referer( $nonceAction ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}
	}

	/**
	 * Whether a validated URL host exactly equals the site host.
	 *
	 * The comparison is byte exact through hash_equals, so www and the
	 * apex are different hosts.
	 *
	 * @param string $url Validated URL.
	 * @return bool The result.
	 */
	private function isSiteHost( string $url ): bool {
		return hash_equals( $this->settings->siteHost(), $this->urlHost( $url ) );
	}

	/**
	 * Host component of a URL, native parser fallback outside WordPress.
	 *
	 * @param string $url URL to parse.
	 * @return string The result.
	 */
	private function urlHost( string $url ): string {
		if ( function_exists( 'wp_parse_url' ) ) {
			return (string) wp_parse_url( $url, PHP_URL_HOST );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- wp_parse_url is unavailable outside WordPress, the fallback only parses a URL already accepted by wp_http_validate_url.
		return (string) parse_url( $url, PHP_URL_HOST );
	}

	/**
	 * Redirect back to the screen, header safe on the load hook.
	 *
	 * @param bool $saved Whether the saved flag should render a notice.
	 * @return void
	 */
	private function redirectTo( bool $saved = true ): void {
		$url = admin_url( 'admin.php?page=' . self::SLUG );

		if ( $saved ) {
			$url .= '&settings-updated=1';
		}

		wp_safe_redirect( $url );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}
}
