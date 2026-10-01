<?php
/**
 * Admin menu and plugin action links.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Redirects\RedirectsSettings;
use RankKernel\Modules\Sitemaps\SitemapSettings;
use RankKernel\Settings\SettingsStore;

/**
 * Registers the admin menu page and Plugins list action links.
 */
final class AdminMenu {
	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage|null
	 */
	private ?SettingsPage $page = null;

	/**
	 * Sitemap settings page instance.
	 *
	 * @var SitemapSettingsPage|null
	 */
	private ?SitemapSettingsPage $sitemapPage = null;

	/**
	 * Schema settings page instance.
	 *
	 * @var SchemaSettingsPage|null
	 */
	private ?SchemaSettingsPage $schemaPage = null;

	/**
	 * Redirects page instance.
	 *
	 * @var RedirectsPage|null
	 */
	private ?RedirectsPage $redirectsPage = null;

	/**
	 * 404 Monitor page instance.
	 *
	 * @var NotFoundPage|null
	 */
	private ?NotFoundPage $monitorPage = null;

	/**
	 * Dashboard page instance.
	 *
	 * @var DashboardPage|null
	 */
	private ?DashboardPage $dashboardPage = null;

	/**
	 * Instant Indexing page instance.
	 *
	 * @var InstantIndexingPage|null
	 */
	private ?InstantIndexingPage $instantIndexingPage = null;

	/**
	 * Constructor.
	 *
	 * @param SettingsStore        $store     Settings store.
	 * @param ModuleEnableMap      $enableMap Module enable map.
	 * @param SitemapSettings|null $sitemap   Sitemap settings store, fresh one when null.
	 */
	public function __construct(
		private readonly SettingsStore $store,
		private readonly ModuleEnableMap $enableMap,
		private readonly ?SitemapSettings $sitemap = null
	) {
		// The X handle field belongs to the profile screens, not to the General
		// Settings screen, so its hooks must be registered on every admin request.
		// This controller is built eagerly, so the registration cannot be deferred
		// into a lazily constructed page controller.
		( new UserProfileField() )->register();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueSharedStyles' ] );

		// The sitemap screen owns a page scoped feature stylesheet, so its
		// enqueue runs on the same hook and gates itself by hook suffix.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueSitemapAssets' ] );
	}

	/**
	 * Enqueue sitemap page assets, gated by hook suffix.
	 *
	 * Performance optimization: checks the current screen hook suffix before
	 * instantiating the SitemapSettingsPage controller to avoid eager object creation
	 * on unrelated admin requests.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueSitemapAssets( string $hookSuffix = '' ): void {
		if ( SitemapSettingsPage::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		$this->getSitemapPage()->enqueueAssets( $hookSuffix );
	}

	/**
	 * Is the current screen one of the RankKernel admin screens?
	 *
	 * The dashboard registers as a top level page and every submenu registers
	 * under admin.php, so the top level hook is `toplevel_page_rankkernel` and
	 * each submenu hook is `rankkernel_page_rankkernel-` followed by its slug.
	 * Metabox and column screens sit on post.php, edit.php and term.php and are
	 * deliberately not matched here, because those enqueue their own assets.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 *
	 * @return bool True when the screen belongs to RankKernel.
	 */
	public static function isRankKernelScreen( string $hookSuffix ): bool {
		return 'toplevel_page_rankkernel' === $hookSuffix
			|| str_starts_with( $hookSuffix, 'rankkernel_page_rankkernel' );
	}

	/**
	 * Enqueue the shared design layers on every RankKernel admin screen.
	 *
	 * The token layer defines the --rk-* custom properties and the UI layer
	 * defines the rk-ui-* components. Both are scoped under a RankKernel root
	 * class, so loading them on every RankKernel screen is inert on a screen
	 * that does not opt in, and it means a new screen cannot silently ship
	 * without them. Feature stylesheets keep registering their own handle and
	 * enqueue that from their own page class.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueSharedStyles( string $hookSuffix = '' ): void {
		if ( ! self::isRankKernelScreen( $hookSuffix ) ) {
			return;
		}

		$pluginFile = defined( 'RANKKERNEL_FILE' ) ? (string) RANKKERNEL_FILE : '';

		AdminStyles::enqueueTokenLayer( $pluginFile );
		AdminStyles::enqueueUiLayer( $pluginFile );
	}

	/**
	 * Get the settings page (lazy instantiated).
	 *
	 * Performance optimization: defers page controller and repository construction
	 * until the page is explicitly accessed or rendered.
	 *
	 * @return SettingsPage The result.
	 */
	public function getPage(): SettingsPage {
		return $this->page ??= new SettingsPage( $this->store, $this->enableMap );
	}

	/**
	 * Get the sitemap settings page (lazy instantiated).
	 *
	 * Performance optimization: defers page controller and repository construction
	 * until the page is explicitly accessed or rendered.
	 *
	 * @return SitemapSettingsPage The result.
	 */
	public function getSitemapPage(): SitemapSettingsPage {
		return $this->sitemapPage ??= new SitemapSettingsPage( $this->sitemap ?? new SitemapSettings() );
	}

	/**
	 * Get the schema settings page (lazy instantiated).
	 *
	 * Performance optimization: defers page controller and repository construction
	 * until the page is explicitly accessed or rendered.
	 *
	 * @return SchemaSettingsPage The result.
	 */
	public function getSchemaPage(): SchemaSettingsPage {
		return $this->schemaPage ??= new SchemaSettingsPage( $this->store );
	}

	/**
	 * Get the redirects page (lazy instantiated).
	 *
	 * Performance optimization: defers page controller and repository construction
	 * until the page is explicitly accessed or rendered.
	 *
	 * @return RedirectsPage The result.
	 */
	public function getRedirectsPage(): RedirectsPage {
		return $this->redirectsPage ??= new RedirectsPage( new RedirectRepository(), new RedirectsSettings() );
	}

	/**
	 * Get the 404 Monitor page (lazy instantiated).
	 *
	 * Performance optimization: defers page controller and repository construction
	 * until the page is explicitly accessed or rendered.
	 *
	 * @return NotFoundPage The result.
	 */
	public function getMonitorPage(): NotFoundPage {
		return $this->monitorPage ??= new NotFoundPage();
	}

	/**
	 * Get the Instant Indexing page (lazy instantiated).
	 *
	 * Performance optimization: defers page controller and repository construction
	 * until the page is explicitly accessed or rendered.
	 *
	 * @return InstantIndexingPage The result.
	 */
	public function getInstantIndexingPage(): InstantIndexingPage {
		return $this->instantIndexingPage ??= new InstantIndexingPage( null, $this->enableMap );
	}

	/**
	 * Get the dashboard page (lazy instantiated).
	 *
	 * Performance optimization: defers page controller and repository construction
	 * until the page is explicitly accessed or rendered.
	 *
	 * @return DashboardPage The result.
	 */
	public function getDashboardPage(): DashboardPage {
		return $this->dashboardPage ??= new DashboardPage();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter(
			'plugin_action_links_' . plugin_basename( RANKKERNEL_FILE ),
			[ $this, 'addActionLinks' ]
		);

		add_action( 'admin_menu', [ $this, 'addMenuPage' ] );
	}

	/**
	 * Add Settings link to the plugin row (first position).
	 *
	 * @param string[] $links Existing links.
	 * @return string[] The result.
	 */
	public function addActionLinks( array $links ): array {
		$url      = admin_url( 'admin.php?page=rankkernel' );
		$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'rankkernel' ) . '</a>';

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Register the RankKernel top-level menu.
	 */
	public function addMenuPage(): void {
		$hook = add_menu_page(
			'RankKernel',
			'RankKernel',
			'manage_options',
			DashboardPage::SLUG,
			[ $this, 'renderDashboard' ],
			'dashicons-search',
			80
		);

		// Registered explicitly, because WordPress fills the first submenu slot
		// from the parent menu title when nothing claims the parent slug. Without
		// this the submenu would read RankKernel and repeat the top level label.
		add_submenu_page(
			'rankkernel',
			'Dashboard',
			'Dashboard',
			'manage_options',
			DashboardPage::SLUG,
			[ $this, 'renderDashboard' ]
		);

		// Save handling runs on the load hook, before ANY output, so the
		// post-redirect-get pattern can send its Location header.
		add_action( 'load-' . $hook, [ $this, 'handleDashboardSave' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueDashboardAssets' ] );

		$sitemapHook = add_submenu_page(
			'rankkernel',
			'Sitemap Settings',
			'Sitemap',
			'manage_options',
			'rankkernel-sitemap',
			[ $this, 'renderSitemap' ]
		);

		// Same load hook save pattern, so the tab redirect stays header safe.
		add_action( 'load-' . $sitemapHook, [ $this, 'handleSitemapSave' ] );
	}

	/**
	 * Render dashboard page callback.
	 */
	public function renderDashboard(): void {
		$this->getDashboardPage()->render();
	}

	/**
	 * Handle dashboard save callback.
	 */
	public function handleDashboardSave(): void {
		$this->getDashboardPage()->maybeHandleSave();
	}

	/**
	 * Enqueue dashboard page assets callback.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueDashboardAssets( string $hookSuffix = '' ): void {
		if ( DashboardPage::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		$this->getDashboardPage()->enqueueAssets( $hookSuffix );
	}

	/**
	 * Render sitemap page callback.
	 */
	public function renderSitemap(): void {
		$this->getSitemapPage()->render();
	}

	/**
	 * Handle sitemap save callback.
	 */
	public function handleSitemapSave(): void {
		$this->getSitemapPage()->maybeHandleSave();
	}

	/**
	 * Register the RankKernel General settings submenu page.
	 *
	 * Uses the same load hook save pattern as the other pages, so the
	 * redirect stays header safe.
	 */
	public function addGeneralPage(): void {
		$hook = add_submenu_page(
			'rankkernel',
			'General Settings',
			'General Settings',
			'manage_options',
			'rankkernel-general',
			[ $this, 'renderGeneral' ]
		);

		add_action( 'load-' . $hook, [ $this, 'handleGeneralSave' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueGeneralAssets' ] );
	}

	/**
	 * Render general settings callback.
	 */
	public function renderGeneral(): void {
		$this->getPage()->render();
	}

	/**
	 * Handle general settings save callback.
	 */
	public function handleGeneralSave(): void {
		$this->getPage()->maybeHandleSave();
	}

	/**
	 * Enqueue general settings assets callback.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueGeneralAssets( string $hookSuffix = '' ): void {
		if ( SettingsPage::HOOK_SUFFIX !== $hookSuffix && 'dashboard_page_rankkernel-general' !== $hookSuffix ) {
			return;
		}

		$this->getPage()->enqueueAssets( $hookSuffix );
	}

	/**
	 * Register the RankKernel schema submenu page.
	 *
	 * Hooked separately from the top level menu so callers control
	 * ordering. Uses the same load hook save pattern as the sitemap
	 * page, so the redirect stays header safe.
	 */
	public function addSchemaPage(): void {
		$hook = add_submenu_page(
			'rankkernel',
			'Schema Settings',
			'Schema',
			'manage_options',
			'rankkernel-schema',
			[ $this, 'renderSchema' ]
		);

		add_action( 'load-' . $hook, [ $this, 'handleSchemaSave' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueSchemaAssets' ] );
	}

	/**
	 * Render schema settings callback.
	 */
	public function renderSchema(): void {
		$this->getSchemaPage()->render();
	}

	/**
	 * Handle schema settings save callback.
	 */
	public function handleSchemaSave(): void {
		$this->getSchemaPage()->maybeHandleSave();
	}

	/**
	 * Enqueue schema settings assets callback.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueSchemaAssets( string $hookSuffix = '' ): void {
		if ( SchemaSettingsPage::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		$this->getSchemaPage()->enqueueAssets( $hookSuffix );
	}

	/**
	 * Register the RankKernel redirects submenu page.
	 *
	 * Hooked separately from the top level menu so callers control
	 * ordering. Uses the same load hook save pattern as the other
	 * pages, so the redirect stays header safe.
	 */
	public function addRedirectsPage(): void {
		$hook = add_submenu_page(
			'rankkernel',
			'Redirects',
			'Redirects',
			'manage_options',
			'rankkernel-redirects',
			[ $this, 'renderRedirects' ]
		);

		add_action( 'load-' . $hook, [ $this, 'handleRedirectsSave' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueRedirectsAssets' ] );
	}

	/**
	 * Render redirects page callback.
	 */
	public function renderRedirects(): void {
		$this->getRedirectsPage()->render();
	}

	/**
	 * Handle redirects save callback.
	 */
	public function handleRedirectsSave(): void {
		$this->getRedirectsPage()->maybeHandleSave();
	}

	/**
	 * Enqueue redirects assets callback.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueRedirectsAssets( string $hookSuffix = '' ): void {
		if ( RedirectsPage::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		$this->getRedirectsPage()->enqueueAssets( $hookSuffix );
	}

	/**
	 * Register the RankKernel 404 Monitor submenu page.
	 *
	 * Hooked separately from the top level menu so callers control
	 * ordering. Uses the same load hook save pattern as the other
	 * pages, so the redirect stays header safe.
	 */
	public function addMonitorPage(): void {
		$hook = add_submenu_page(
			'rankkernel',
			'404 Monitor',
			'404 Monitor',
			'manage_options',
			'rankkernel-404',
			[ $this, 'renderMonitor' ]
		);

		add_action( 'load-' . $hook, [ $this, 'handleMonitorSave' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueMonitorAssets' ] );
	}

	/**
	 * Render monitor page callback.
	 */
	public function renderMonitor(): void {
		$this->getMonitorPage()->render();
	}

	/**
	 * Handle monitor save callback.
	 */
	public function handleMonitorSave(): void {
		$this->getMonitorPage()->maybeHandleSave();
	}

	/**
	 * Enqueue monitor assets callback.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueMonitorAssets( string $hookSuffix = '' ): void {
		if ( NotFoundPage::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		$this->getMonitorPage()->enqueueAssets( $hookSuffix );
	}

	/**
	 * Register the RankKernel Instant Indexing submenu page.
	 *
	 * Hooked separately from the top level menu so callers control
	 * ordering. Uses the same load hook save pattern as the other
	 * pages, so the redirect stays header safe.
	 */
	public function addInstantIndexingPage(): void {
		$hook = add_submenu_page(
			'rankkernel',
			'Instant Indexing',
			'Instant Indexing',
			'manage_options',
			InstantIndexingPage::SLUG,
			[ $this, 'renderInstantIndexing' ]
		);

		add_action( 'load-' . $hook, [ $this, 'handleInstantIndexingSave' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueInstantIndexingAssets' ] );
	}

	/**
	 * Render instant indexing page callback.
	 */
	public function renderInstantIndexing(): void {
		$this->getInstantIndexingPage()->render();
	}

	/**
	 * Handle instant indexing save callback.
	 */
	public function handleInstantIndexingSave(): void {
		$this->getInstantIndexingPage()->maybeHandleSave();
	}

	/**
	 * Enqueue instant indexing assets callback.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueInstantIndexingAssets( string $hookSuffix = '' ): void {
		if ( InstantIndexingPage::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		$this->getInstantIndexingPage()->enqueueAssets( $hookSuffix );
	}
}
