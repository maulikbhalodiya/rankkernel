<?php
/**
 * Posts sitemap provider.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps\Provider;

use RankKernel\Modules\Metadata\MetaPayload;

/**
 * Provides sitemap entries for public post types.
 */
class PostsProvider {
    /**
     * Inner LIKE text matching a noindex payload.
     *
     * The payload is stored as a PHP serialized meta row (object typed
     * meta is serialized by core on write), so matching uses the
     * serialized shape: key "index" is exactly 5 chars, followed by
     * boolean false. No other sanitized key can emit these exact bytes
     * (title and image are 5 chars but always serialize as strings, and
     * no sibling robots key is named exactly "index"). Residual risk is
     * a post whose free text literally contains this byte sequence,
     * which is astronomically unlikely and documented here. Escaped with
     * $wpdb->esc_like and wrapped in % % at query time, passed via
     * $wpdb->prepare as %s.
     */
    private const NOINDEX_LIKE_INNER = 's:5:"index";b:0';

    /**
     * Meta key holding the serialized payload.
     */
    private const META_KEY = '_rankkernel_meta_data';

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

        // Attachments are never listed (matches the competitor default),
        // even if a theme registers the type as public.
        $sets = array_values(
            array_filter(
                $postTypes,
                static fn (mixed $v): bool => is_string($v) && '' !== $v && 'attachment' !== $v
            )
        );

        return $sets;
    }

    /**
     * Get count of published posts for a set.
     *
     * @param string $postType Post type slug.
     * @return int
     */
    public function getCount(string $postType): int {
        if ('attachment' === $postType) {
            return 0;
        }

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        $like = '%' . $wpdb->esc_like(self::NOINDEX_LIKE_INNER) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = %s"
                . " AND p.post_password = ''"
                . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID"
                . " AND m.meta_key = '_rankkernel_meta_data' AND m.meta_value LIKE %s)",
                $postType,
                'publish',
                $like
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
        if ('attachment' === $postType) {
            return [];
        }

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ( $page - 1 ) * $perPage;

        $like = '%' . $wpdb->esc_like(self::NOINDEX_LIKE_INNER) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, Generic.Files.LineLength.TooLong
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                "SELECT p.ID, p.post_modified_gmt FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = %s AND p.post_password = '' AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_rankkernel_meta_data' AND m.meta_value LIKE %s) ORDER BY p.post_modified_gmt DESC, p.ID DESC LIMIT %d OFFSET %d",
                $postType,
                'publish',
                $like,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        if (! is_array($rows) || [] === $rows) {
            return [];
        }

        $rows = $this->dropCanonicalMismatchRows($rows);

        if ([] === $rows) {
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

    /**
     * Drop rows whose stored canonical differs from the permalink.
     *
     * One batched postmeta read for the page, decode per row, fail
     * open (unreadable payloads and unresolvable permalinks are kept).
     * Counts stay unfiltered, an approximation the competitors accept too.
     *
     * @param array<int, mixed> $rows Entry rows with ID keys.
     * @return array<int, mixed> Surviving rows.
     */
    private function dropCanonicalMismatchRows(array $rows): array {
        $ids = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['ID'])) {
                continue;
            }

            $id = (int) $row['ID'];

            if (0 !== $id) {
                $ids[] = $id;
            }
        }

        if ([] === $ids) {
            return $rows;
        }

        $canonicals = $this->fetchCanonicals($ids);

        if ([] === $canonicals) {
            return $rows;
        }

        $kept = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['ID'])) {
                continue;
            }

            $id        = (int) $row['ID'];
            $canonical = $canonicals[ $id ] ?? '';

            if ('' === $canonical) {
                $kept[] = $row;

                continue;
            }

            $permalink = get_permalink($id);

            if (! is_string($permalink) || '' === $permalink) {
                $kept[] = $row;

                continue;
            }

            if (trailingslashit($canonical) !== trailingslashit($permalink)) {
                continue;
            }

            $kept[] = $row;
        }

        return $kept;
    }

    /**
     * Batch fetch non empty stored canonicals for post ids.
     *
     * @param int[] $ids Post ids.
     * @return array<int, string> Map of post id to canonical URL.
     */
    private function fetchCanonicals(array $ids): array {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => 0 !== $id));

        if ([] === $ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "SELECT post_id, meta_value FROM {$wpdb->postmeta}"
            . " WHERE meta_key = %s AND post_id IN ($placeholders)";

        $args = array_merge([ $sql, self::META_KEY ], $ids);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $metaRows = $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);

        if (! is_array($metaRows)) {
            return [];
        }

        $out = [];

        foreach ($metaRows as $metaRow) {
            if (! is_array($metaRow) || ! isset($metaRow['post_id'], $metaRow['meta_value'])) {
                continue;
            }

            $postId = (int) $metaRow['post_id'];

            if (0 === $postId) {
                continue;
            }

            if (! is_string($metaRow['meta_value']) || '' === $metaRow['meta_value']) {
                continue;
            }

            $payload = MetaPayload::decodeMetaValue($metaRow['meta_value']);

            if (! isset($payload['canonical']) || ! is_string($payload['canonical'])) {
                continue;
            }

            if ('' === $payload['canonical']) {
                continue;
            }

            $out[ $postId ] = $payload['canonical'];
        }

        return $out;
    }
}
