<?php
/**
 * Taxonomies sitemap provider.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps\Provider;

/**
 * Provides sitemap entries for public taxonomies.
 */
class TaxonomiesProvider {
    /**
     * Get available taxonomy sets.
     *
     * @return string[]
     */
    public function getSets(): array {
        $taxonomies = get_taxonomies([ 'public' => true ], 'names');

        if (! is_array($taxonomies)) {
            return [];
        }

        // phpcs:ignore Generic.Files.LineLength.TooLong
        $sets = array_values(array_filter($taxonomies, static fn (mixed $v): bool => is_string($v) && '' !== $v));

        return $sets;
    }

    /**
     * Get count of terms that have published posts for a taxonomy.
     *
     * @param string $taxonomy Taxonomy name.
     * @return int
     */
    public function getCount(string $taxonomy): int {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        $publicTypes = get_post_types([ 'public' => true ], 'names');
        if (! is_array($publicTypes) || [] === $publicTypes) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($publicTypes), '%s'));

        // phpcs:ignore Generic.Files.LineLength.TooLong
        $types = array_values(array_filter($publicTypes, static fn (mixed $v): bool => is_string($v) && '' !== $v));
        if ([] === $types) {
            return 0;
        }

        $sql = "SELECT COUNT(DISTINCT t.term_id) FROM {$wpdb->terms} t"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id"
            . " INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id"
            . " INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id"
            . " WHERE tt.taxonomy = %s AND p.post_status = %s AND p.post_type IN ($placeholders)";

        $args = array_merge([ $sql, $taxonomy, 'publish' ], $types);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var($wpdb->prepare(...$args));

        return (int) $count;
    }

    /**
     * Get entries for a taxonomy page.
     *
     * @param string $taxonomy Taxonomy name.
     * @param int    $page     Page number, 1 based.
     * @param int    $perPage  Entries per page.
     * @return array<int, array{loc: string, lastmod: string, image: string|null}>
     */
    public function getEntries(string $taxonomy, int $page, int $perPage): array {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ( $page - 1 ) * $perPage;

        $publicTypes = get_post_types([ 'public' => true ], 'names');
        if (! is_array($publicTypes) || [] === $publicTypes) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($publicTypes), '%s'));
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $types        = array_values(array_filter($publicTypes, static fn (mixed $v): bool => is_string($v) && '' !== $v));
        if ([] === $types) {
            return [];
        }

        $sql = "SELECT t.term_id, MAX(p.post_modified_gmt) as lastmod_gmt"
            . " FROM {$wpdb->terms} t"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id"
            . " INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id"
            . " INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id"
            . " WHERE tt.taxonomy = %s AND p.post_status = %s AND p.post_type IN ($placeholders)"
            . " GROUP BY t.term_id ORDER BY t.term_id ASC LIMIT %d OFFSET %d";

        $args = array_merge([ $sql, $taxonomy, 'publish' ], $types, [ $perPage, $offset ]);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);

        if (! is_array($rows) || [] === $rows) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['term_id'])) {
                continue;
            }

            $termId = (int) $row['term_id'];
            if (0 === $termId) {
                continue;
            }

            $term = get_term($termId, $taxonomy);

            if (! $term || is_wp_error($term)) {
                continue;
            }

            $link = get_term_link($term);

            if (is_wp_error($link) || ! is_string($link) || '' === $link) {
                continue;
            }

            $lastmodGmt = isset($row['lastmod_gmt']) ? (string) $row['lastmod_gmt'] : '';
            if ('' === $lastmodGmt) {
                continue;
            }

            $lastmod = (string) mysql2date(DATE_W3C, $lastmodGmt, false);
            if ('' === $lastmod) {
                continue;
            }

            $entries[] = [
                'loc'     => $link,
                'lastmod' => $lastmod,
                'image'   => null,
            ];
        }

        return $entries;
    }
}
