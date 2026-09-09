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

        if (! headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
        }

        if ('index' === $set) {
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
                nocache_headers();
            }

            $this->finishRender();

            return;
        }

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
