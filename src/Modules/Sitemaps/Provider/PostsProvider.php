<?php
/**
 * Posts sitemap provider.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps\Provider;

/**
 * Provides sitemap entries for public post types.
 */
class PostsProvider {
    /**
     * Get available post type sets.
     *
     * @return string[]
     */
    public function getSets(): array {
        $postTypes = get_post_types([ 'public' => true ], 'names');

        if (! is_array($postTypes)) {
            return [];
        }

        $sets = array_values(array_filter($postTypes, static fn (mixed $v): bool => is_string($v) && '' !== $v));

        return $sets;
    }

    /**
     * Get count of published posts for a set.
     *
     * @param string $postType Post type slug.
     * @return int
     */
    public function getCount(string $postType): int {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                $postType,
                'publish'
            )
        );

        return (int) $count;
    }

    /**
     * Get entries for a post type page.
     *
     * @param string $postType Post type slug.
     * @param int    $page     Page number, 1 based.
     * @param int    $perPage  Entries per page.
     * @return array<int, array{loc: string, lastmod: string, image: string|null}>
     */
    public function getEntries(string $postType, int $page, int $perPage): array {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ( $page - 1 ) * $perPage;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, Generic.Files.LineLength.TooLong
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                "SELECT ID, post_modified_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY ID ASC LIMIT %d OFFSET %d",
                $postType,
                'publish',
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        if (! is_array($rows) || [] === $rows) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['ID'])) {
                continue;
            }

            $postId = (int) $row['ID'];
            if (0 === $postId) {
                continue;
            }

            $permalink = get_permalink($postId);

            if (! is_string($permalink) || '' === $permalink) {
                $permalink = home_url('/?p=' . (string) $postId);
            }

            $modifiedGmt = isset($row['post_modified_gmt']) ? (string) $row['post_modified_gmt'] : '';

            $lastmod = '';
            if ('' !== $modifiedGmt) {
                $lastmod = (string) mysql2date(DATE_W3C, $modifiedGmt, false);
            }

            $image = null;
            if (function_exists('get_post_thumbnail_id') && function_exists('wp_get_attachment_image_url')) {
                $thumbId = (int) get_post_thumbnail_id($postId);
                if (0 !== $thumbId) {
                    $url = wp_get_attachment_image_url($thumbId, 'full');
                    if (is_string($url) && '' !== $url) {
                        $image = $url;
                    }
                }
            }

            $entries[] = [
                'loc'     => $permalink,
                'lastmod' => $lastmod,
                'image'   => $image,
            ];
        }

        return $entries;
    }
}
