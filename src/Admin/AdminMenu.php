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
}
