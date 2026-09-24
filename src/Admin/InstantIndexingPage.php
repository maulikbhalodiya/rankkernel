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

use RankKernel\Modules\InstantIndexing\IndexNowClient;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\InstantIndexing\InstantIndexingModule;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Plugin;

/**
 * Renders the Instant Indexing screen and handles its writes.
 *
 * The API key never reaches the browser. The screen reports whether a key
 * is configured and never the key itself, no field carries it, and
 * regeneration never echoes the replacement. A manual submission is
 * validated against the site host before the module entry point sees it,
 * and no submitted URL is ever fetched.
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
	 * Module id consulted before any manual submission.
	 */
	private const MODULE_ID = 'instant-indexing';

	/**
	 * Settings store.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Module enable map, the manual path refuses to run while disabled.
	 *
	 * @var ModuleEnableMap
	 */
	private ModuleEnableMap $enableMap;

	/**
	 * Submission callback, validated URLs in.
	 *
	 * @var callable(string[]): void
	 */
	private $submit;

	/**
	 * Constructor.
	 *
	 * The default callback routes through InstantIndexingModule, the
	 * single submission entry point, and never calls the client directly.
	 * It only runs after the enable map gate, so a disabled module never
	 * constructs a client.
	 *
	 * @param IndexNowSettings|null         $settings  Settings store, fresh one when null.
	 * @param ModuleEnableMap|null          $enableMap Enable map, fresh one when null.
	 * @param callable(string[]): void|null $submit    Submission callback, null uses the module entry point.
	 */
	public function __construct(
		?IndexNowSettings $settings = null,
		?ModuleEnableMap $enableMap = null,
		?callable $submit = null
	) {
		$this->settings  = $settings ?? new IndexNowSettings();
		$this->enableMap = $enableMap ?? new ModuleEnableMap();
		$this->submit    = $submit ?? function ( array $urls ): void {
			( new InstantIndexingModule( $this->enableMap, $this->settings ) )->submitUrls( $urls, 'manual' );
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
	 * The validation script is registered, enqueued and localized here.
	 * Only the site host and the batch limit reach the browser, the API
	 * key never does. The gate keeps the hook contract shared with the
	 * other module pages.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( self::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		if ( ! function_exists( 'plugins_url' ) || ! function_exists( 'wp_register_script' ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}

		$version = Plugin::version();
		$source  = plugins_url( 'assets/js/instant-indexing-admin.js', (string) RANKKERNEL_FILE );

		wp_register_script( 'rankkernel-instant-indexing-admin', $source, [], $version, true );
		wp_enqueue_script( 'rankkernel-instant-indexing-admin' );

		if ( ! function_exists( 'wp_localize_script' ) ) {
			return;
		}

		wp_localize_script(
			'rankkernel-instant-indexing-admin',
			'rankkernelInstantIndexing',
			[
				'siteHost' => $this->settings->siteHost(),
				'maxUrls'  => IndexNowClient::MAX_URLS,
			]
		);
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

		$homeUrl = function_exists( 'home_url' ) ? (string) home_url() : '';
		$base    = '' !== $homeUrl ? rtrim( $homeUrl, '/' ) . '/' : 'https://example.com/';

		$urlPlaceholder = $base . "\n" . $base . "sample-page/\n" . $base . 'hello-world/';

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
	 * Handle a manual submission of one or many URLs.
	 *
	 * The module must be enabled, because a disabled module makes zero
	 * outbound requests. Each line must pass wp_http_validate_url and
	 * its host must equal the site host, so www and the apex are
	 * different hosts. Every invalid line is logged with its own reason
	 * and dropped, every valid line is submitted in one call, and no
	 * request is ever made to a submitted URL.
	 *
	 * @return void
	 */
	private function handleSubmit(): void {
		$this->requireAccess( self::NONCE_SUBMIT );

		if ( ! $this->isModuleEnabled() ) {
			$this->reject( '', __( 'Rejected: the Instant Indexing module is disabled.', 'rankkernel' ) );
			$this->redirectTo( false );

			return;
		}

		$urls = $this->postedUrls();

		if ( [] === $urls ) {
			$this->reject( '', __( 'Rejected: no URLs were provided.', 'rankkernel' ) );
			$this->redirectTo( false );

			return;
		}

		$valid   = [];
		$invalid = 0;

		foreach ( $urls as $url ) {
			$validated = $this->validateUrl( $url );

			if ( '' === $validated ) {
				$this->reject( $url, __( 'Rejected: the URL could not be validated.', 'rankkernel' ) );
				++$invalid;
				continue;
			}

			if ( ! $this->isSiteHost( $validated ) ) {
				$this->reject( $url, __( 'Rejected: the URL host does not match this site.', 'rankkernel' ) );
				++$invalid;
				continue;
			}

			$valid[] = $validated;
		}

		if ( [] !== $valid ) {
			( $this->submit )( $valid );
		}

		$this->redirectTo( 0 === $invalid );
	}

	/**
	 * Validate one URL through the WordPress URL validator.
	 *
	 * @param string $url Candidate URL.
	 * @return string Validated URL, or an empty string when validation failed.
	 */
	private function validateUrl( string $url ): string {
		if ( ! function_exists( 'wp_http_validate_url' ) ) {
			return '';
		}

		$validated = wp_http_validate_url( $url );

		return is_string( $validated ) ? $validated : '';
	}

	/**
	 * Log a rejected manual submission, no request made.
	 *
	 * The caller redirects once after every rejection is logged, so a
	 * paste with several invalid URLs records one entry per URL and the
	 * screen still reloads a single time.
	 *
	 * @param string $url    Submitted URL, logged for the operator.
	 * @param string $reason Human readable rejection reason.
	 * @return void
	 */
	private function reject( string $url, string $reason ): void {
		$this->settings->logEntry( $url, 0, 'manual', $reason );
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
	 * Submitted URLs from POST, empty when absent or not a string.
	 *
	 * The textarea value is split on any newline style, each line
	 * trimmed, empty lines dropped and duplicates removed with order
	 * preserved. The values are never rewritten here, validation
	 * happens next and must see the real value.
	 *
	 * @return string[] The result.
	 */
	private function postedUrls(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified by the caller, value unslashed below, every URL validated by wp_http_validate_url plus the host check before use.
		$raw = $_POST['rankkernel_indexnow_urls'] ?? '';

		if ( ! is_string( $raw ) ) {
			return [];
		}

		$value = function_exists( 'wp_unslash' ) ? (string) wp_unslash( $raw ) : $raw;
		$lines = preg_split( '/\r\n|\r|\n/', $value );

		if ( ! is_array( $lines ) ) {
			return [];
		}

		$urls = [];
		$seen = [];

		foreach ( $lines as $line ) {
			$url = trim( (string) $line );

			if ( '' === $url || isset( $seen[ $url ] ) ) {
				continue;
			}

			$seen[ $url ] = true;
			$urls[]       = $url;
		}

		return $urls;
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
	 * Whether the Instant Indexing module is enabled in the enable map.
	 *
	 * @return bool The result.
	 */
	private function isModuleEnabled(): bool {
		return $this->enableMap->isEnabled( self::MODULE_ID );
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
