<?php
/**
 * Sitemap router, rewrite rules and request interception.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps;

use WP_Query;

/**
 * Handles rewrite rules, query vars, and sitemap rendering.
 */
class Router {
    /**
     * Constructor.
     *
     * @param IndexBuilder   $builder Index builder.
     * @param SitemapCache   $cache   Cache handler.
     * @param XslStylesheet  $xsl     XSL handler.
     */
    public function __construct(
        private readonly IndexBuilder $builder,
        private readonly SitemapCache $cache,
        private readonly XslStylesheet $xsl
    ) {
    }

    /**
     * Cached pretty permalink check, one option read per request.
     */
    private ?bool $prettyCache = null;

    /**
     * Whether pretty permalinks are enabled.
     */
    private function usingPrettyPermalinks(): bool {
        if (null === $this->prettyCache) {
            $structure = get_option('permalink_structure');

            $this->prettyCache = is_string($structure) && '' !== $structure;
        }

        return $this->prettyCache;
    }

    /**
     * URL of the sitemap index, pretty or plain form.
     */
    public function indexUrl(): string {
        if ($this->usingPrettyPermalinks()) {
            return home_url('/sitemap_index.xml');
        }

        return add_query_arg('rankkernel_sitemap', 'index', home_url('/'));
    }

    /**
     * URL of a sitemap set page, pretty or plain form.
     *
     * Plain mode uses prefixed GET params (rankkernel_sitemap and
     * rankkernel_sitemap_n) because WordPress core owns the unprefixed
     * sitemap query var and ours must never collide with it.
     *
     * @param string $set  Set name (post type slug, taxonomy name, authors).
     * @param int    $page Page number, 1 based.
     */
    public function sitemapUrl(string $set, int $page = 1): string {
        $page = max(1, $page);

        if ($this->usingPrettyPermalinks()) {
            $suffix = $page > 1 ? (string) $page : '';

            return home_url('/' . $set . '-sitemap' . $suffix . '.xml');
        }

        $args = [ 'rankkernel_sitemap' => $set ];

        if ($page > 1) {
            $args['rankkernel_sitemap_n'] = $page;
        }

        return add_query_arg($args, home_url('/'));
    }

    /**
     * URL of the XSL stylesheet, pretty or plain form.
     */
    public function xslUrl(): string {
        if ($this->usingPrettyPermalinks()) {
            return home_url('/sitemap.xsl');
        }

        return add_query_arg('rankkernel_sitemap_xsl', '1', home_url('/'));
    }

    /**
     * Register hooks.
     */
    public function register(): void {
        // Register rules synchronously: this runs at init priority 10, and a
        // nested init priority 1 hook would never fire (its moment passed).
        $this->addRewriteRules();
        add_filter('query_vars', [ $this, 'addQueryVars' ]);
        add_action('pre_get_posts', [ $this, 'intercept' ], 1);
        add_filter('redirect_canonical', [ $this, 'disableCanonical' ], 10, 1);
    }

    /**
     * Add rewrite rules.
     */
    public function addRewriteRules(): void {
        add_rewrite_rule('^sitemap_index\.xml$', 'index.php?rankkernel_sitemap=index', 'top');
        // phpcs:ignore Generic.Files.LineLength.TooLong
        add_rewrite_rule('^([^.]+)-sitemap([0-9]+)?\.xml$', 'index.php?rankkernel_sitemap=$matches[1]&rankkernel_sitemap_n=$matches[2]', 'top');
        add_rewrite_rule('^([a-z]+)?-?sitemap\.xsl$', 'index.php?rankkernel_sitemap_xsl=1', 'top');
    }

    /**
     * Add query vars.
     *
     * Only prefixed names are registered. WordPress core owns the
     * unprefixed sitemap query var, so plain URLs use the same prefixed
     * params as the rewrite targets.
     *
     * @param string[] $vars Existing vars.
     * @return string[]
     */
    public function addQueryVars(array $vars): array {
        $vars[] = 'rankkernel_sitemap';
        $vars[] = 'rankkernel_sitemap_n';
        $vars[] = 'rankkernel_sitemap_xsl';

        return $vars;
    }

    /**
     * Intercept sitemap requests on pre_get_posts.
     *
     * @param WP_Query $query Query object.
     */
    public function intercept(WP_Query $query): void {
        // Legacy /sitemap.xml redirects to the index, same as the
        // leading SEO plugins, so the short URL never 404s.
        $wp      = $GLOBALS['wp'] ?? null;
        $request = (is_object($wp) && property_exists($wp, 'request')) ? $wp->request : null;

        if (is_string($request) && 'sitemap.xml' === trim($request, '/')) {
            wp_safe_redirect($this->indexUrl(), 301);

            $this->finishRender();

            return;
        }

        $sitemap = get_query_var('rankkernel_sitemap');
        $xsl     = get_query_var('rankkernel_sitemap_xsl');

        $isSitemap = (is_string($sitemap) && '' !== $sitemap)
            || ! empty($xsl);

        if (! $isSitemap) {
            return;
        }

        // Guard against theme output.
        if (function_exists('remove_all_actions')) {
            remove_all_actions('wp_footer');
        }

        // XSL request.
        $xslVal = get_query_var('rankkernel_sitemap_xsl');
        if (! empty($xslVal)) {
            $this->xsl->output();

            $this->finishRender();

            return;
        }

        $set = is_string($sitemap) ? $sitemap : '';
        $n   = get_query_var('rankkernel_sitemap_n');
        $page = 1;

        if (is_string($n) && '' !== $n) {
            $page = max(1, (int) $n);
        } elseif (is_int($n) && 0 !== $n) {
            $page = max(1, $n);
        } elseif (is_numeric($n) && '' !== (string) $n) {
            $page = max(1, (int) $n);
        }

        if ('index' === $set) {
            $this->sendXmlHeaders();

            $xml = $this->cache->get(
                'index',
                1,
                fn (): string => $this->builder->buildIndexXml()
            );

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML already escaped in builder.
            echo $xml;

            $this->finishRender();

            return;
        }

        // Unknown set names and out-of-range pages 404, mirroring Yoast:
        // an empty urlset with HTTP 200 would advertise a broken sitemap.
        // The set map rides the sitemap cache, so a warm cache answers
        // without provider queries.
        $sets = $this->cache->getMap(
            'sets',
            fn (): array => $this->builder->getSetsWithPageCounts()
        );
        $pages = (int) ( $sets[ $set ] ?? 0 );

        if ($pages < 1 || $page > $pages) {
            if (! headers_sent()) {
                status_header(404);
                header('Content-Type: text/plain; charset=UTF-8');
                nocache_headers();
            }

            echo 'Sitemap not found.';

            $this->finishRender();

            return;
        }

        $this->sendXmlHeaders();

        $xml = $this->cache->get(
            $set,
            $page,
            fn (): string => $this->builder->buildEntriesXml($set, $page)
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML already escaped in builder.
        echo $xml;

        $this->finishRender();
    }

    /**
     * Finish a sitemap render: exit unless running under tests.
     */
    private function finishRender(): void {
        if (! defined('RANKKERNEL_TESTING')) {
            exit;
        }
    }

    /**
     * Send sitemap XML headers (only on successful 200 renders).
     */
    private function sendXmlHeaders(): void {
        if (! headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
        }
    }

    /**
     * Disable canonical redirect for sitemap requests.
     *
     * @param mixed $redirect Current redirect value.
     * @return mixed False when sitemap var present, otherwise original.
     */
    public function disableCanonical(mixed $redirect): mixed {
        $sitemap = get_query_var('rankkernel_sitemap');
        $xsl     = get_query_var('rankkernel_sitemap_xsl');
        $n       = get_query_var('rankkernel_sitemap_n');

        if ((is_string($sitemap) && '' !== $sitemap) || ! empty($xsl) || ! empty($n)) {
            return false;
        }

        return $redirect;
    }
}
