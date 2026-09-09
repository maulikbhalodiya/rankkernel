<?php
/**
 * Authors sitemap provider.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps\Provider;

/**
 * Provides sitemap entries for authors with published posts.
 */
class AuthorsProvider {
    /**
     * Get available sets, returns authors if there are published authors.
     *
     * @return string[]
     */
    public function getSets(): array {
        $count = $this->getCount('authors');

        if (0 === $count) {
            return [];
        }

        return [ 'authors' ];
    }

    /**
     * Get count of authors with published posts.
     *
     * @param string $set Set name, expected authors.
     * @return int
     */
    public function getCount(string $set): int {
        if ('authors' !== $set) {
            return 0;
        }

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        // Authors of public content only: internal types (flamingo, forms,
        // oembed cache) publish rows that must not create author entries.
        $publicTypes = get_post_types([ 'public' => true ], 'names');
        if (! is_array($publicTypes) || [] === $publicTypes) {
            return 0;
        }

        $types = array_values(array_filter($publicTypes, static fn (mixed $v): bool => is_string($v) && '' !== $v));
        if ([] === $types) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($types), '%s'));

        // phpcs:ignore Generic.Files.LineLength.TooLong
        $sql = "SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ($placeholders)";

        $args = array_merge([ $sql, 'publish' ], $types);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var($wpdb->prepare(...$args));

        return (int) $count;
    }

    /**
     * Get entries for authors page.
     *
     * @param string $set     Set name.
     * @param int    $page    Page number, 1 based.
     * @param int    $perPage Entries per page.
     * @return array<int, array{loc: string, lastmod: string, image: string|null}>
     */
    public function getEntries(string $set, int $page, int $perPage): array {
        if ('authors' !== $set) {
            return [];
        }

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

        $types = array_values(array_filter($publicTypes, static fn (mixed $v): bool => is_string($v) && '' !== $v));
        if ([] === $types) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($types), '%s'));

        $sql = "SELECT post_author, MAX(post_modified_gmt) as lastmod_gmt FROM {$wpdb->posts}"
            . " WHERE post_status = %s AND post_type IN ($placeholders)"
            . ' GROUP BY post_author ORDER BY post_author ASC LIMIT %d OFFSET %d';

        $args = array_merge([ $sql, 'publish' ], $types, [ $perPage, $offset ]);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, Generic.Files.LineLength.TooLong
        $rows = $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);

        if (! is_array($rows) || [] === $rows) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['post_author'])) {
                continue;
            }

            $authorId = (int) $row['post_author'];
            if (0 === $authorId) {
                continue;
            }

            $url = get_author_posts_url($authorId);

            if (! is_string($url) || '' === $url) {
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
                'loc'     => $url,
                'lastmod' => $lastmod,
                'image'   => null,
            ];
        }

        return $entries;
    }
}
