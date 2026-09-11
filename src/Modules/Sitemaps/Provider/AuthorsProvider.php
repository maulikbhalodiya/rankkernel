<?php
/**
 * Authors sitemap provider.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps\Provider;

use RankKernel\Modules\Sitemaps\SitemapSettings;

/**
 * Provides sitemap entries for authors with published posts.
 *
 * Noindex authors are deferred until author prefs land (no author level
 * robots data model exists yet). Post less authors list with their
 * registration date as lastmod when the include empty setting is on.
 */
class AuthorsProvider {
    /**
     * Constructor.
     *
     * Null settings mean defaults and touch no globals, which keeps
     * zero argument construction side effect free. Production wires a
     * real instance through the index builder.
     *
     * @param SitemapSettings|null $settings Settings store or null for defaults.
     */
    public function __construct( private readonly ?SitemapSettings $settings = null ) {
    }

    /**
     * Get available sets, returns authors if there are published authors.
     *
     * @return string[]
     */
    public function getSets(): array {
        if (! (bool) ($this->settings?->get('authors_sitemap', true) ?? true)) {
            return [];
        }

        $count = $this->getCount('authors');

        if (0 === $count) {
            return [];
        }

        return [ 'authors' ];
    }

    /**
     * Get count of authors with published posts.
     *
     * When the include empty setting is on, counts every user instead,
     * minus role and user exclusions.
     *
     * @param string $set Set name, expected authors.
     * @return int
     */
    public function getCount(string $set): int {
        if ('authors' !== $set) {
            return 0;
        }

        if (! (bool) ($this->settings?->get('authors_sitemap', true) ?? true)) {
            return 0;
        }

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        if ((bool) ($this->settings?->get('authors_include_empty', false) ?? false)) {
            return $this->getCountIncludingEmpty();
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

        $params = array_merge([ 'publish' ], $types);
        $sql   .= $this->authorExclusionClauses('post_author', $params);

        $args = array_merge([ $sql ], $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- sitemap tables have no core API, query uses placeholders with prepare through argument unpacking.
        $count = $wpdb->get_var($wpdb->prepare(...$args));

        return (int) $count;
    }

    /**
     * Count every user, minus exclusions.
     */
    private function getCountIncludingEmpty(): int {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        $params = [];
        $sql    = "SELECT COUNT(*) FROM {$wpdb->users} u WHERE 1=1";
        $sql   .= $this->authorExclusionClauses('u.ID', $params);

        $args = array_merge([ $sql ], $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- sitemap tables have no core API, query uses placeholders with prepare through argument unpacking.
        $count = $wpdb->get_var($wpdb->prepare(...$args));

        return (int) $count;
    }

    /**
     * Get entries for authors page.
     *
     * When the include empty setting is on, every user is listed and
     * post less authors use their registration date as lastmod.
     *
     * @param string $set     Set name.
     * @param int    $page    Page number, 1 based.
     * @param int    $perPage Entries per page.
     * @return array<int, array{loc: string, lastmod: string, images: string[]}>
     */
    public function getEntries(string $set, int $page, int $perPage): array {
        if ('authors' !== $set) {
            return [];
        }

        if (! (bool) ($this->settings?->get('authors_sitemap', true) ?? true)) {
            return [];
        }

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ( $page - 1 ) * $perPage;

        if ((bool) ($this->settings?->get('authors_include_empty', false) ?? false)) {
            $rows = $this->queryEntriesIncludingEmpty($perPage, $offset);
        } else {
            $rows = $this->queryEntriesWithPosts($perPage, $offset);
        }

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

            $lastmodGmt = isset($row['lastmod_gmt']) && is_string($row['lastmod_gmt']) ? $row['lastmod_gmt'] : '';

            if ('' === $lastmodGmt) {
                $registered = isset($row['user_registered']) ? (string) $row['user_registered'] : '';

                if ('' === $registered) {
                    continue;
                }

                $lastmodGmt = $registered;
            }

            $lastmod = (string) mysql2date(DATE_W3C, $lastmodGmt, false);
            if ('' === $lastmod) {
                continue;
            }

            $entries[] = [
                'loc'     => $url,
                'lastmod' => $lastmod,
                'images'  => [],
            ];
        }

        return $entries;
    }

    /**
     * Query authors with published public type posts.
     *
     * @param int $perPage Entries per page.
     * @param int $offset  Result offset.
     * @return mixed Query rows.
     */
    private function queryEntriesWithPosts( int $perPage, int $offset ): mixed {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

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
            . " WHERE post_status = %s AND post_type IN ($placeholders)";

        $params = array_merge([ 'publish' ], $types);
        $sql   .= $this->authorExclusionClauses('post_author', $params);
        $sql   .= ' GROUP BY post_author ORDER BY post_author ASC LIMIT %d OFFSET %d';

        $params[] = $perPage;
        $params[] = $offset;

        $args = array_merge([ $sql ], $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- sitemap tables have no core API, query uses placeholders with prepare through argument unpacking.
        return $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);
    }

    /**
     * Query every user left joined to posts, minus exclusions.
     *
     * Authors with posts use their latest post date, post less
     * authors fall back to their registration date in the caller.
     *
     * @param int $perPage Entries per page.
     * @param int $offset  Result offset.
     * @return mixed Query rows.
     */
    private function queryEntriesIncludingEmpty( int $perPage, int $offset ): mixed {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

        $publicTypes = get_post_types([ 'public' => true ], 'names');
        if (! is_array($publicTypes) || [] === $publicTypes) {
            return [];
        }

        $types = array_values(array_filter($publicTypes, static fn (mixed $v): bool => is_string($v) && '' !== $v));
        if ([] === $types) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($types), '%s'));

        $sql = "SELECT u.ID as post_author, MAX(p.post_modified_gmt) as lastmod_gmt,"
            . " u.user_registered as user_registered FROM {$wpdb->users} u"
            . " LEFT JOIN {$wpdb->posts} p ON p.post_author = u.ID"
            . " AND p.post_status = %s AND p.post_type IN ($placeholders)"
            . ' WHERE 1=1';

        $params = array_merge([ 'publish' ], $types);
        $sql   .= $this->authorExclusionClauses('u.ID', $params);
        $sql   .= ' GROUP BY u.ID ORDER BY u.ID ASC LIMIT %d OFFSET %d';

        $params[] = $perPage;
        $params[] = $offset;

        $args = array_merge([ $sql ], $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- sitemap tables have no core API, query uses placeholders with prepare through argument unpacking.
        return $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);
    }

    /**
     * Excluded user ids from settings, unique positive ints.
     *
     * @return int[]
     */
    private function excludedUserIds(): array {
        $ids = $this->settings?->get('authors_exclude_users', []) ?? [];

        if (! is_array($ids)) {
            return [];
        }

        $clean = [];

        foreach ($ids as $id) {
            $int = (int) $id;

            if ($int > 0) {
                $clean[] = $int;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Excluded role slugs from settings.
     *
     * @return string[]
     */
    private function excludedRoles(): array {
        $roles = $this->settings?->get('authors_exclude_roles', []) ?? [];

        if (! is_array($roles)) {
            return [];
        }

        $clean = [];

        foreach ($roles as $role) {
            $slug = is_string($role) ? $role : '';

            if ('' !== $slug) {
                $clean[] = $slug;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Append user and role exclusion clauses.
     *
     * User ids use NOT IN, chunked at 500 per clause. Roles use a
     * NOT EXISTS check against the capabilities meta, one per role,
     * with the role fragment escaped for LIKE and passed via prepare.
     * Returns an empty string when both lists are empty, so default
     * queries keep their exact SQL shape.
     *
     * @param string            $userColumn Qualified user id column.
     * @param array<int, mixed> $params     Prepare params, values appended in order.
     */
    private function authorExclusionClauses( string $userColumn, array &$params ): string {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return '';
        }

        $clauses = '';

        $userIds = $this->excludedUserIds();

        foreach (array_chunk($userIds, 500) as $chunk) {
            $chunk = array_values(array_filter(array_map('intval', $chunk), static fn (int $id): bool => $id > 0));

            if ([] === $chunk) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $clauses     .= " AND {$userColumn} NOT IN ($placeholders)";

            foreach ($chunk as $id) {
                $params[] = $id;
            }
        }

        foreach ($this->excludedRoles() as $role) {
            $fragment  = '%"' . $wpdb->esc_like($role) . '"%';
            $clauses  .= " AND NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id = {$userColumn}"
                . ' AND um.meta_key = %s AND um.meta_value LIKE %s)';
            $params[]  = $wpdb->prefix . 'capabilities';
            $params[]  = $fragment;
        }

        return $clauses;
    }
}
