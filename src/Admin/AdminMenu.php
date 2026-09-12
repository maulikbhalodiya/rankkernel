<?php
/**
 * Admin menu and plugin action links.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

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
     */
    private readonly SettingsPage $page;

    /**
     * Sitemap settings page instance.
     */
    private readonly SitemapSettingsPage $sitemapPage;

    /**
     * Schema settings page instance.
     */
    private readonly SchemaSettingsPage $schemaPage;

    /**
     * Redirects page instance.
     */
    private readonly RedirectsPage $redirectsPage;

    /**
     * Constructor.
     *
     * @param SettingsStore    $store     Settings store.
     * @param ModuleEnableMap  $enableMap Module enable map.
     * @param SitemapSettings|null $sitemap Sitemap settings store, fresh one when null.
     */
    public function __construct(
        private readonly SettingsStore $store,
        private readonly ModuleEnableMap $enableMap,
        ?SitemapSettings $sitemap = null
    ) {
        $this->page        = new SettingsPage($this->store, $this->enableMap);
        $this->sitemapPage = new SitemapSettingsPage($sitemap ?? new SitemapSettings());
        $this->schemaPage  = new SchemaSettingsPage($this->store);
        $this->redirectsPage = new RedirectsPage(new RedirectRepository(), new RedirectsSettings());
    }

    /**
     * Get the settings page (for testing).
     */
    public function getPage(): SettingsPage {
        return $this->page;
    }

    /**
     * Get the sitemap settings page (for testing).
     */
    public function getSitemapPage(): SitemapSettingsPage {
        return $this->sitemapPage;
    }

    /**
     * Get the schema settings page (for testing).
     */
    public function getSchemaPage(): SchemaSettingsPage {
        return $this->schemaPage;
    }

    /**
     * Get the redirects page (for testing).
     */
    public function getRedirectsPage(): RedirectsPage {
        return $this->redirectsPage;
    }

    /**
     * Register hooks.
     */
    public function register(): void {
        add_filter(
            'plugin_action_links_' . plugin_basename(RANKKERNEL_FILE),
            [ $this, 'addActionLinks' ]
        );

        add_action('admin_menu', [ $this, 'addMenuPage' ]);
    }

    /**
     * Add Settings link to the plugin row (first position).
     *
     * @param string[] $links Existing links.
     * @return string[]
     */
    public function addActionLinks( array $links ): array {
        $url      = admin_url('admin.php?page=rankkernel');
        $settings = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'rankkernel') . '</a>';

        array_unshift($links, $settings);

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
            'rankkernel',
            [ $this->page, 'render' ],
            'dashicons-search',
            80
        );

        // Save handling runs on the load hook, before ANY output, so the
        // post-redirect-get pattern can send its Location header.
        add_action('load-' . $hook, [ $this->page, 'maybeHandleSave' ]);

        $sitemapHook = add_submenu_page(
            'rankkernel',
            'Sitemap Settings',
            'Sitemap',
            'manage_options',
            'rankkernel-sitemap',
            [ $this->sitemapPage, 'render' ]
        );

        // Same load hook save pattern, so the tab redirect stays header safe.
        add_action('load-' . $sitemapHook, [ $this->sitemapPage, 'maybeHandleSave' ]);
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
            [ $this->schemaPage, 'render' ]
        );

        add_action('load-' . $hook, [ $this->schemaPage, 'maybeHandleSave' ]);
        add_action('admin_enqueue_scripts', [ $this->schemaPage, 'enqueueAssets' ]);
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
            [ $this->redirectsPage, 'render' ]
        );

        add_action('load-' . $hook, [ $this->redirectsPage, 'maybeHandleSave' ]);
        add_action('admin_enqueue_scripts', [ $this->redirectsPage, 'enqueueAssets' ]);
    }
}
