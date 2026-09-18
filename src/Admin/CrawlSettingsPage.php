<?php
/**
 * Crawl Signals settings page, render plus save handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Robots\CrawlConsistency;
use RankKernel\Modules\Robots\LlmsCollector;
use RankKernel\Modules\Robots\LlmsFileWriter;
use RankKernel\Modules\Robots\LlmsGenerator;
use RankKernel\Modules\Robots\LlmsRouter;
use RankKernel\Modules\Robots\LlmsSettings;
use RankKernel\Modules\Robots\RobotsBuilder;
use RankKernel\Modules\Robots\RobotsDirectives;
use RankKernel\Modules\Robots\RobotsModule;
use RankKernel\Modules\Robots\RobotsSettings;
use RankKernel\Plugin;

/**
 * Renders the RankKernel Crawl Signals page and handles saves.
 *
 * Two tabs, robots.txt and llms.txt, each saved on its own. robots.txt is
 * always virtual. The llms.txt tab can optionally write a physical file.
 */
final class CrawlSettingsPage {
	/**
	 * Admin page hook suffix for asset gating.
	 */
	public const HOOK_SUFFIX = 'rankkernel_page_rankkernel-crawl';

	/**
	 * Admin page slug.
	 */
	public const SLUG = 'rankkernel-crawl';

	/**
	 * Tab ids in display order.
	 *
	 * @var string[]
	 */
	private const TABS = [ 'robots', 'llms' ];

	/**
	 * Representative core block used for the robots.txt preview.
	 */
	private const PREVIEW_CORE = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";

	/**
	 * Robots.txt settings store.
	 *
	 * @var RobotsSettings
	 */
	private RobotsSettings $robots;

	/**
	 * Llms.txt settings store.
	 *
	 * @var LlmsSettings
	 */
	private LlmsSettings $llms;

	/**
	 * Constructor.
	 *
	 * @param RobotsSettings|null $robots Optional robots settings store.
	 * @param LlmsSettings|null   $llms   Optional llms settings store.
	 */
	public function __construct( ?RobotsSettings $robots = null, ?LlmsSettings $llms = null ) {
		$this->robots = $robots ?? new RobotsSettings();
		$this->llms   = $llms ?? new LlmsSettings();
	}

	/**
	 * Handle a POST save on the load hook, before any output.
	 */
	public function maybeHandleSave(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to handleSave which verifies capability plus nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ( isset( $_POST['rankkernel_crawl_save'] ) || isset( $_POST['rankkernel_llms_write'] ) ) ) {
			$this->handleSave();
		}
	}

	/**
	 * Enqueue assets only on this screen.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( self::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		wp_register_style(
			'rankkernel-admin',
			plugins_url( 'assets/css/rankkernel-admin.css', RANKKERNEL_FILE ),
			[],
			Plugin::version()
		);
		wp_enqueue_style( 'rankkernel-admin' );
	}

	/**
	 * Current tab from the query string, robots on unknown input.
	 *
	 * @return string The result.
	 */
	public function currentTab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only display flag, unslashed and sanitized below.
		$raw = $_GET['tab'] ?? '';

		if ( ! is_string( $raw ) ) {
			return 'robots';
		}

		$tab = sanitize_key( wp_unslash( $raw ) );

		return in_array( $tab, self::TABS, true ) ? $tab : 'robots';
	}

	/**
	 * Prepare view state and load the view.
	 */
	public function render(): void {
		$tab = $this->currentTab();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only flag, compared strictly against a literal.
		$settingsUpdated = isset( $_GET['settings-updated'] ) && '1' === (string) $_GET['settings-updated'];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only flag, sanitized below.
		$notice = sanitize_key( (string) ( $_GET['rk_notice'] ?? '' ) );

		$tabLabels = [
			'robots' => __( 'robots.txt', 'rankkernel' ),
			'llms'   => __( 'llms.txt', 'rankkernel' ),
		];

		$tabItems = [];

		foreach ( $tabLabels as $tabId => $tabLabel ) {
			$tabItems[] = [
				'url'   => admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tabId ),
				'class' => 'nav-tab' . ( $tabId === $tab ? ' nav-tab-active' : '' ),
				'label' => $tabLabel,
			];
		}

		$robotsAll = $this->robots->all();
		$llmsAll   = $this->llms->all();

		$presetDefs     = [];
		$enabledPresets = RobotsDirectives::sanitizePresets( $robotsAll['presets'] ?? [] );

		foreach ( RobotsDirectives::presets() as $slug => $def ) {
			$presetDefs[] = [
				'slug'    => (string) $slug,
				'label'   => (string) ( $def['label'] ?? $slug ),
				'enabled' => in_array( (string) $slug, $enabledPresets, true ),
			];
		}

		$robotsMode       = (string) ( $robotsAll['mode'] ?? 'default' );
		$robotsCustom     = (string) ( $robotsAll['custom'] ?? '' );
		$sitemapUrl       = (string) ( $robotsAll['sitemap_url'] ?? '' );
		$robotsValidation = RobotsDirectives::validate( $robotsCustom );

		$previewBase   = ( '' !== trim( $robotsCustom ) ) ? $robotsCustom : self::PREVIEW_CORE;
		$robotsPreview = ( new RobotsBuilder() )->build( $previewBase, true, $enabledPresets, $sitemapUrl );

		$llmsEnabled  = ! empty( $llmsAll['enabled'] );
		$llmsSummary  = (string) ( $llmsAll['summary'] ?? '' );
		$llmsLimit    = (string) ( $llmsAll['limit'] ?? 100 );
		$llmsExcerpt  = (string) ( $llmsAll['excerpt_length'] ?? 160 );
		$llmsPhysical = ! empty( $llmsAll['physical'] );
		$llmsExclude  = $this->idsText( $llmsAll['exclude_ids'] ?? [] );

		$llmsPostTypes = [];

		foreach ( $this->publicPostTypes() as $slug => $label ) {
			$llmsPostTypes[] = [
				'slug'    => $slug,
				'label'   => $label,
				'enabled' => in_array( $slug, (array) ( $llmsAll['post_types'] ?? [] ), true ),
			];
		}

		$llmsTaxonomies = [];

		foreach ( $this->publicTaxonomies() as $slug => $label ) {
			$llmsTaxonomies[] = [
				'slug'    => $slug,
				'label'   => $label,
				'enabled' => in_array( $slug, (array) ( $llmsAll['taxonomies'] ?? [] ), true ),
			];
		}

		$llmsPreview = $llmsEnabled
			? ( new LlmsGenerator() )->render(
				( new LlmsCollector() )->collect( $this->llms ),
				(string) get_bloginfo( 'name' ),
				$llmsSummary
			)
			: '';

		$consistency        = CrawlConsistency::warnings( $enabledPresets, $llmsEnabled, $robotsPreview );
		$robotsPhysical     = ( new RobotsModule() )->hasPhysicalFile();
		$llmsPhysicalExists = ( new LlmsFileWriter() )->exists();

		require __DIR__ . '/Views/crawl-settings.php';
	}

	/**
	 * Handle save, capability plus nonce, then persist and redirect.
	 */
	private function handleSave(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to manage RankKernel settings.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$verified = check_admin_referer( 'rankkernel_crawl_settings' );

		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		if ( isset( $_POST['rankkernel_llms_write'] ) ) {
			$this->writePhysicalLlms();
			return;
		}

		$tab = $this->currentTab();

		if ( 'llms' === $tab ) {
			$this->llms->set( $this->collectLlms() );
			LlmsRouter::invalidate();
		} else {
			$this->robots->set( $this->collectRobots() );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab . '&settings-updated=1' ) );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Write the physical llms.txt, then redirect with a status notice.
	 */
	private function writePhysicalLlms(): void {
		$content = ( new LlmsGenerator() )->render(
			( new LlmsCollector() )->collect( $this->llms ),
			(string) get_bloginfo( 'name' ),
			(string) $this->llms->get( 'summary', '' )
		);

		$result = ( new LlmsFileWriter() )->write( $content );

		if ( $result['written'] ) {
			$notice = 'written';
		} elseif ( 'exists' === $result['reason'] ) {
			$notice = 'exists';
		} else {
			$notice = 'failed';
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=llms&rk_notice=' . $notice ) );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Collect the robots.txt fields from POST.
	 *
	 * @return array<string, mixed>
	 */
	private function collectRobots(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and sanitized on save.
		$rawMode = isset( $_POST['mode'] ) ? wp_unslash( $_POST['mode'] ) : 'default';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and sanitized on save.
		$rawCustom = isset( $_POST['custom'] ) ? wp_unslash( $_POST['custom'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and sanitized on save.
		$rawPresets = isset( $_POST['presets'] ) ? wp_unslash( $_POST['presets'] ) : [];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and sanitized on save.
		$rawSitemap = isset( $_POST['sitemap_url'] ) ? wp_unslash( $_POST['sitemap_url'] ) : '';

		return [
			'mode'        => is_string( $rawMode ) ? $rawMode : 'default',
			'custom'      => is_string( $rawCustom ) ? $rawCustom : '',
			'presets'     => is_array( $rawPresets ) ? $rawPresets : [],
			'sitemap_url' => is_string( $rawSitemap ) ? $rawSitemap : '',
		];
	}

	/**
	 * Collect the llms.txt fields from POST.
	 *
	 * @return array<string, mixed>
	 */
	private function collectLlms(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed here, sanitized on save.
		$rawSummary = isset( $_POST['llms_summary'] ) ? wp_unslash( $_POST['llms_summary'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and sanitized on save.
		$rawTypes = isset( $_POST['llms_post_types'] ) ? wp_unslash( $_POST['llms_post_types'] ) : [];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and sanitized on save.
		$rawTax = isset( $_POST['llms_taxonomies'] ) ? wp_unslash( $_POST['llms_taxonomies'] ) : [];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and cast below.
		$rawLimit = isset( $_POST['llms_limit'] ) ? wp_unslash( $_POST['llms_limit'] ) : 100;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and cast below.
		$rawExcerpt = isset( $_POST['llms_excerpt_length'] ) ? wp_unslash( $_POST['llms_excerpt_length'] ) : 160;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, unslashed and exploded below.
		$rawExclude = isset( $_POST['llms_exclude_ids'] ) ? wp_unslash( $_POST['llms_exclude_ids'] ) : '';

		return [
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave.
			'enabled'        => isset( $_POST['llms_enabled'] ),
			'summary'        => is_string( $rawSummary ) ? $rawSummary : '',
			'post_types'     => is_array( $rawTypes ) ? $rawTypes : [],
			'taxonomies'     => is_array( $rawTax ) ? $rawTax : [],
			'limit'          => is_scalar( $rawLimit ) ? (int) $rawLimit : 100,
			'excerpt_length' => is_scalar( $rawExcerpt ) ? (int) $rawExcerpt : 160,
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave.
			'physical'       => isset( $_POST['llms_physical'] ),
			'exclude_ids'    => is_string( $rawExclude ) ? explode( ',', $rawExclude ) : [],
		];
	}

	/**
	 * Public post types as slug to label, without attachments.
	 *
	 * @return array<string, string>
	 */
	private function publicPostTypes(): array {
		$types = get_post_types( [ 'public' => true ], 'objects' );

		if ( ! is_array( $types ) ) {
			return [];
		}

		return $this->slugsWithLabels( $types, [ 'attachment' ] );
	}

	/**
	 * Public taxonomies as slug to label.
	 *
	 * @return array<string, string>
	 */
	private function publicTaxonomies(): array {
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

		if ( ! is_array( $taxonomies ) ) {
			return [];
		}

		return $this->slugsWithLabels( $taxonomies, [] );
	}

	/**
	 * Reduce a type map to slug to label pairs.
	 *
	 * @param array<mixed, mixed> $map  Type map from core.
	 * @param string[]            $skip Slugs to drop.
	 * @return array<string, string>
	 */
	private function slugsWithLabels( array $map, array $skip ): array {
		$out = [];

		foreach ( $map as $slug => $item ) {
			if ( ! is_string( $slug ) || '' === $slug || in_array( $slug, $skip, true ) ) {
				continue;
			}

			if ( 1 !== preg_match( '/^[a-z0-9_]+$/', $slug ) ) {
				continue;
			}

			$label = $slug;

			if ( is_object( $item ) && isset( $item->label ) && is_string( $item->label ) && '' !== $item->label ) {
				$label = $item->label;
			} elseif ( is_string( $item ) && '' !== $item ) {
				$label = $item;
			}

			$out[ $slug ] = $label;
		}

		return $out;
	}

	/**
	 * Comma list of positive integer ids from a stored value.
	 *
	 * @param mixed $value Stored ids.
	 * @return string The result.
	 */
	private function idsText( mixed $value ): string {
		if ( ! is_array( $value ) ) {
			return '';
		}

		$ids = [];

		foreach ( $value as $item ) {
			$int = (int) $item;

			if ( $int > 0 ) {
				$ids[] = (string) $int;
			}
		}

		return implode( ',', $ids );
	}
}
