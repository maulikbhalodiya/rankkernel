<?php
/**
 * Admin dashboard page, module cards plus toggle handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleRegistry;

/**
 * Renders the RankKernel dashboard of module cards and handles module toggles.
 *
 * Each card turns one module on or off and links to its settings screen.
 * A disabled module still adds no hooks and no runtime cost.
 */
final class DashboardPage {
	/**
	 * Admin page hook suffix for asset gating.
	 */
	public const HOOK_SUFFIX = 'toplevel_page_rankkernel';

	/**
	 * Admin page slug.
	 */
	public const SLUG = 'rankkernel';

	/**
	 * Short descriptions for the module cards, in our own words.
	 */
	private const DESCRIPTIONS = [
		'metadata'         => 'Titles, meta descriptions, robots, canonicals, Open Graph and Twitter cards for every page.',
		'sitemaps'         => 'XML sitemaps for posts, taxonomies and authors, with the core sitemap taken over.',
		'schema'           => 'One JSON-LD graph per page, assembled from the Schema pieces.',
		'breadcrumbs'      => 'An accessible breadcrumb trail with a shortcode, a template tag and a block.',
		'importer'         => 'One click import from Yoast, Rank Math and SEOPress. Planned.',
		'redirects'        => 'Manage 301, 302, 307, 410 and 451 redirects, with CSV import and export.',
		'404'              => 'Log 404 errors with sane pruning and one click redirect creation.',
		'instant-indexing' => 'Notify participating search engines when a URL changes, using the IndexNow protocol.',
		'robots'           => 'A virtual robots.txt with per crawler AI controls, plus a curated llms.txt.',
		'image-seo'        => 'Automatic image alt and title patterns. Planned.',
		'gutenberg'        => 'An editor sidebar with analysis and previews. Planned.',
		'ai'               => 'Bring your own key AI tools. Planned.',
		'headless'         => 'A read only REST payload for headless builds. Planned.',
	];

	/**
	 * Settings screen slug per module, empty when the module has none.
	 */
	private const SETTINGS_PAGES = [
		'metadata'         => 'rankkernel-general',
		'sitemaps'         => 'rankkernel-sitemap',
		'schema'           => 'rankkernel-schema',
		'breadcrumbs'      => 'rankkernel-general',
		'redirects'        => 'rankkernel-redirects',
		'404'              => 'rankkernel-404',
		'instant-indexing' => 'rankkernel-instant-indexing',
		'robots'           => 'rankkernel-general',
	];

	/**
	 * Settings section per module for the general page, empty for the top.
	 */
	private const SETTINGS_SECTIONS = [
		'metadata'    => 'general',
		'breadcrumbs' => 'breadcrumbs',
		'robots'      => 'robots',
	];

	/**
	 * Handle a module toggle on the load hook, before any output.
	 */
	public function maybeHandleSave(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to handleToggle which verifies capability plus nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['rankkernel_module_toggle'] ) ) {
			$this->handleToggle();
		}
	}

	/**
	 * Enqueue the dashboard stylesheet only on its own screen.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( self::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		wp_register_style(
			'rankkernel-dashboard-admin',
			plugins_url( 'assets/css/dashboard-admin.css', RANKKERNEL_FILE ),
			[],
			\RankKernel\Plugin::version()
		);
		wp_enqueue_style( 'rankkernel-dashboard-admin' );
	}

	/**
	 * Build the module cards.
	 *
	 * @return array<int, array{id: string, label: string, description: string, enabled: bool, settingsUrl: string}> The result.
	 */
	public function cards(): array {
		$enabled = $this->enabledIds();
		$cards   = [];

		foreach ( ModuleRegistry::all() as $moduleId => $moduleLabel ) {
			$id   = (string) $moduleId;
			$slug = self::SETTINGS_PAGES[ $id ] ?? '';
			$url  = '';

			if ( '' !== $slug ) {
				$url = admin_url( 'admin.php?page=' . $slug );

				if ( isset( self::SETTINGS_SECTIONS[ $id ] ) ) {
					$url .= '&section=' . self::SETTINGS_SECTIONS[ $id ];
				}
			}

			$cards[] = [
				'id'          => $id,
				'label'       => (string) $moduleLabel,
				'description' => self::DESCRIPTIONS[ $id ] ?? '',
				'enabled'     => in_array( $id, $enabled, true ),
				'settingsUrl' => $url,
			];
		}

		return $cards;
	}

	/**
	 * Prepare the view state and load the dashboard view.
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only flag, compared strictly against a literal.
		$settingsUpdated = isset( $_GET['settings-updated'] ) && '1' === (string) $_GET['settings-updated'];

		$cards = $this->cards();

		require __DIR__ . '/Views/dashboard.php';
	}

	/**
	 * Toggle one module, capability plus nonce, then redirect.
	 */
	private function handleToggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to manage RankKernel modules.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$verified = check_admin_referer( 'rankkernel_module_toggle' );

		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above, sanitized below.
		$rawId = isset( $_POST['rankkernel_module_toggle'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rankkernel_module_toggle'] ) ) : '';

		if ( '' !== $rawId && ModuleRegistry::has( $rawId ) ) {
			$enabled = $this->enabledIds();

			if ( in_array( $rawId, $enabled, true ) ) {
				$enabled = array_values( array_diff( $enabled, [ $rawId ] ) );
			} else {
				$enabled[] = $rawId;
			}

			update_option( 'rankkernel_modules', array_values( array_unique( $enabled ) ) );
			flush_rewrite_rules( false );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&settings-updated=1' ) );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Enabled module ids from the option, normalising a list or an assoc map.
	 *
	 * @return string[] The result.
	 */
	private function enabledIds(): array {
		$stored = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $stored ) ) {
			return [];
		}

		$enabled = [];

		foreach ( $stored as $key => $value ) {
			if ( is_string( $value ) ) {
				$enabled[] = $value;
			} elseif ( is_int( $key ) && true === $value ) {
				$enabled[] = (string) $key;
			}
		}

		return array_values( array_unique( $enabled ) );
	}
}
