<?php
/**
 * Sitemaps module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;
use WP_Post;

/**
 * XML Sitemaps module.
 */
class SitemapsModule implements ModuleInterface {
    /**
     * Cached enabled check.
     */
    private ?bool $enabledCache = null;

    /**
     * Shared enable map.
     */
    private ?ModuleEnableMap $enableMap;

    /**
     * Router instance.
     */
    private ?Router $router = null;

    /**
     * Cache instance.
     */
    private ?SitemapCache $cache = null;

    /**
     * Constructor.
     *
     * @param ModuleEnableMap|null $enableMap Optional shared enable map.
     */
    public function __construct(?ModuleEnableMap $enableMap = null) {
        $this->enableMap = $enableMap;
    }

    /**
     * Get module id.
     */
    public function getId(): string {
        return 'sitemaps';
    }

    /**
     * Get human readable name.
     */
    public function getName(): string {
        return __('XML Sitemaps', 'rankkernel');
    }

    /**
     * Module priority.
     */
    public function getPriority(): int {
        return 20;
    }

    /**
     * Dependencies.
     *
     * @return string[]
     */
    public function dependsOn(): array {
        return [];
    }

    /**
     * Whether the module is enabled.
     */
    public function isEnabled(): bool {
        if (null !== $this->enabledCache) {
            return $this->enabledCache;
        }

        if (null !== $this->enableMap) {
            $this->enabledCache = $this->enableMap->isEnabled('sitemaps');

            return $this->enabledCache;
        }

        $map = get_option('rankkernel_modules', []);

        if (! is_array($map)) {
            $map = [];
        }

        if (array_key_exists('sitemaps', $map)) {
            $this->enabledCache = (bool) $map['sitemaps'];
        } else {
            $this->enabledCache = in_array('sitemaps', $map, true);
        }

        return $this->enabledCache;
    }

    /**
     * Register services, no tables.
     */
    public function register(): void {
    }

    /**
     * Boot hooks.
     */
    public function boot(): void {
        $builder = new IndexBuilder(null, null, null, (string) RANKKERNEL_VERSION);
        $cache   = new SitemapCache();
        $xsl     = new XslStylesheet();
        $router  = new Router($builder, $cache, $xsl);

        $this->cache  = $cache;
        $this->router = $router;

        // The builder renders sitemap URLs, the router owns them, so the
        // router is attached after construction (constructor injection
        // would cycle builder into router into builder).
        $builder->setRouter($router);

        $router->register();
        $cache->registerHooks();

        // Core sitemap takeover.
        add_filter('wp_sitemaps_enabled', '__return_false');

        // Sitemap directive for robots.txt, registered here (not in the
        // router) because it needs the blog_public option plus the
        // router URL, and the module owns both at boot time.
        add_filter('robots_txt', [ $this, 'sitemapDirective' ], 1);

        add_action('admin_notices', [ $this, 'renderTakeoverNotice' ]);

        // Ping hook point for cache warming.
        add_action('transition_post_status', [ $this, 'onTransitionPostStatus' ], 10, 3);

        // Code version bump: a plugin update that changes sitemap output
        // must not keep serving cached XML from the old code. Changing the
        // global validator once per version forces every set to rebuild.
        $codeVersion = get_option('rankkernel_sitemap_code_version', '');

        if (RANKKERNEL_VERSION !== $codeVersion) {
            update_option(SitemapCache::VALIDATOR_GLOBAL, (string) time() . '-' . (string) wp_rand(), false);
            update_option('rankkernel_sitemap_code_version', RANKKERNEL_VERSION, false);
        }

        // One flush per plugin version: rewrite rules registered above must
        // reach the cached rules array (a fresh install or upgrade has stale
        // cached rules without them, which 404s every sitemap URL).
        $rulesVersion = get_option('rankkernel_rewrite_rules_version', '');

        if (RANKKERNEL_VERSION !== $rulesVersion) {
            flush_rewrite_rules(false);
            update_option('rankkernel_rewrite_rules_version', RANKKERNEL_VERSION, false);
        }
    }

    /**
     * Append our sitemap index directive to robots.txt output.
     *
     * Strips every existing Sitemap line first, which removes the stale
     * WordPress core wp-sitemap.xml line (it 404s since core sitemaps are
     * disabled) and keeps repeated calls idempotent. Private blogs are
     * returned untouched.
     *
     * @param string $output Robots.txt output.
     */
    public function sitemapDirective(string $output): string {
        if (! (bool) get_option('blog_public')) {
            return $output;
        }

        $stripped = preg_replace('/^Sitemap:.*$/mi', '', $output);

        if (! is_string($stripped)) {
            $stripped = $output;
        }

        $base = rtrim($stripped);

        $indexUrl = null !== $this->router ? $this->router->indexUrl() : home_url('/sitemap_index.xml');

        $directive = 'Sitemap: ' . esc_url($indexUrl);

        if ('' === $base) {
            return $directive . "\n";
        }

        return $base . "\n" . $directive . "\n";
    }

    /**
     * Render takeover admin notice.
     */
    public function renderTakeoverNotice(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-info is-dismissible"><p>';
        echo esc_html__('Core WordPress sitemaps are disabled in favor of RankKernel sitemaps.', 'rankkernel');
        echo '</p></div>';
    }

    /**
     * Handle transition to publish for cache warming.
     *
     * @param string   $newStatus New status.
     * @param string   $oldStatus Old status.
     * @param WP_Post $post      Post object.
     */
    public function onTransitionPostStatus(string $newStatus, string $oldStatus, WP_Post $post): void {
        if ('publish' === $newStatus && 'publish' !== $oldStatus) {
            /**
             * Fires when a post transitions to publish, for sitemap cache warming.
             *
             * @param int $postId Post id.
             */
            do_action('rankkernel/sitemap/ping', (int) $post->ID);
        }
    }

    /**
     * Get router, for testing.
     */
    public function getRouter(): ?Router {
        return $this->router;
    }

    /**
     * Get cache, for testing.
     */
    public function getCache(): ?SitemapCache {
        return $this->cache;
    }
}
