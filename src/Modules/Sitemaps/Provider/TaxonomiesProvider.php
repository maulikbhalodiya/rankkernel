<?php
/**
 * Taxonomies sitemap provider.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps\Provider;

use RankKernel\Modules\Metadata\MetaPayload;
use RankKernel\Modules\Sitemaps\SitemapSettings;

/**
 * Provides sitemap entries for public taxonomies.
 *
 * Empty terms carry no post dates, so their lastmod falls back to
 * the current time when the include empty terms setting is on.
 */
class TaxonomiesProvider {
    /**
     * Inner LIKE text matching a noindex term payload.
     *
     * Same serialized shape as the posts provider (object typed meta is
     * serialized by core on write): key "index" exactly 5 chars followed
     * by boolean false, matched against the _rankkernel_term_data row
     * keyed by term id. No other sanitized key can emit these exact
     * bytes (title and image are 5 chars but always serialize as
     * strings, and no sibling robots key is named exactly "index").
     * Residual risk is a term whose free text literally contains this
     * byte sequence, which is astronomically unlikely and documented
     * here. Escaped with $wpdb->esc_like and wrapped in % % at query
     * time, passed via $wpdb->prepare as %s.
     */
    private const NOINDEX_LIKE_INNER = 's:5:"index";b:0';

    /**
     * Meta key holding the serialized term payload.
     */
    private const META_KEY = '_rankkernel_term_data';

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

        // Taxonomies disabled in sitemap settings vanish from the index.
        $sets = array_values(
            array_filter(
                $sets,
                fn (string $name): bool => $this->settings?->isTypeEnabled('tax', $name) ?? true
            )
        );

        return $sets;
    }

    /**
     * Get count of terms for a taxonomy.
     *
     * Counts only terms with published posts by default. When the
     * include empty terms setting is on, counts every term instead.
     *
     * @param string $taxonomy Taxonomy name.
     * @return int
     */
    public function getCount(string $taxonomy): int {
        if (! ($this->settings?->isTypeEnabled('tax', $taxonomy) ?? true)) {
            return 0;
        }

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        if ($this->includeEmptyTerms()) {
            return $this->getCountIncludingEmpty($taxonomy);
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

        $like = '%' . $wpdb->esc_like(self::NOINDEX_LIKE_INNER) . '%';

        $params  = array_merge([ $taxonomy, 'publish' ], $types, [ $like ]);
        $exclude = $this->excludeClause($this->excludedTermIds(), 't.term_id', $params);

        $sql = "SELECT COUNT(DISTINCT t.term_id) FROM {$wpdb->terms} t"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id"
            . " INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id"
            . " INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id"
            . " WHERE tt.taxonomy = %s AND p.post_status = %s AND p.post_type IN ($placeholders)"
            . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->termmeta} tm WHERE tm.term_id = t.term_id"
            . " AND tm.meta_key = '_rankkernel_term_data' AND tm.meta_value LIKE %s)"
            . $exclude;

        $args = array_merge([ $sql ], $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var($wpdb->prepare(...$args));

        return (int) $count;
    }

    /**
     * Count every term of a taxonomy, including empty ones.
     *
     * Separate COUNT query without the post join.
     *
     * @param string $taxonomy Taxonomy name.
     */
    private function getCountIncludingEmpty(string $taxonomy): int {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return 0;
        }

        $like = '%' . $wpdb->esc_like(self::NOINDEX_LIKE_INNER) . '%';

        $params  = [ $taxonomy, $like ];
        $exclude = $this->excludeClause($this->excludedTermIds(), 't.term_id', $params);

        $sql = "SELECT COUNT(*) FROM {$wpdb->terms} t"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id"
            . " WHERE tt.taxonomy = %s"
            . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->termmeta} tm WHERE tm.term_id = t.term_id"
            . " AND tm.meta_key = '_rankkernel_term_data' AND tm.meta_value LIKE %s)"
            . $exclude;

        $args = array_merge([ $sql ], $params);

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

        $like = '%' . $wpdb->esc_like(self::NOINDEX_LIKE_INNER) . '%';

        $allowEmpty = $this->includeEmptyTerms();

        if ($allowEmpty) {
            $rows = $this->queryEntriesIncludingEmpty($taxonomy, $types, $placeholders, $like, $perPage, $offset);
        } else {
            $sql = "SELECT t.term_id, MAX(p.post_modified_gmt) as lastmod_gmt"
                . " FROM {$wpdb->terms} t"
                . " INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id"
                . " INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id"
                . " INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id"
                . " WHERE tt.taxonomy = %s AND p.post_status = %s AND p.post_type IN ($placeholders)"
                . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->termmeta} tm WHERE tm.term_id = t.term_id"
                . " AND tm.meta_key = '_rankkernel_term_data' AND tm.meta_value LIKE %s)";

            $params  = array_merge([ $taxonomy, 'publish' ], $types, [ $like ]);
            $exclude = $this->excludeClause($this->excludedTermIds(), 't.term_id', $params);

            $sql .= $exclude . ' GROUP BY t.term_id ORDER BY lastmod_gmt DESC, t.term_id DESC LIMIT %d OFFSET %d';

            $params[] = $perPage;
            $params[] = $offset;

            $args = array_merge([ $sql ], $params);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);
        }

        if (! is_array($rows) || [] === $rows) {
            return [];
        }

        $rows = $this->dropCanonicalMismatchRows($rows, $taxonomy);

        if ([] === $rows) {
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

            $lastmodGmt = isset($row['lastmod_gmt']) && is_string($row['lastmod_gmt']) ? $row['lastmod_gmt'] : '';

            if ('' === $lastmodGmt) {
                if (! $allowEmpty) {
                    continue;
                }

                $lastmodGmt = (string) current_time('mysql', true);

                if ('' === $lastmodGmt) {
                    continue;
                }
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

    /**
     * Query taxonomy entries left joined, so empty terms are listed too.
     *
     * Post filters live in the ON clause to keep post less rows.
     * Empty rows carry a null lastmod, resolved to current time later.
     *
     * @param string   $taxonomy     Taxonomy name.
     * @param string[] $types        Public post types.
     * @param string   $placeholders Type placeholders for the ON clause.
     * @param string   $like         Noindex LIKE pattern.
     * @param int      $perPage      Entries per page.
     * @param int      $offset       Result offset.
     * @return mixed Query rows.
     */
    private function queryEntriesIncludingEmpty(
        string $taxonomy,
        array $types,
        string $placeholders,
        string $like,
        int $perPage,
        int $offset
    ): mixed {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            return [];
        }

        $sql = "SELECT t.term_id, MAX(p.post_modified_gmt) as lastmod_gmt"
            . " FROM {$wpdb->terms} t"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id"
            . " LEFT JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id"
            . " LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id"
            . " AND p.post_status = %s AND p.post_type IN ($placeholders)"
            . " WHERE tt.taxonomy = %s"
            . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->termmeta} tm WHERE tm.term_id = t.term_id"
            . " AND tm.meta_key = '_rankkernel_term_data' AND tm.meta_value LIKE %s)";

        $params  = array_merge([ 'publish' ], $types, [ $taxonomy, $like ]);
        $exclude = $this->excludeClause($this->excludedTermIds(), 't.term_id', $params);

        $sql .= $exclude . ' GROUP BY t.term_id ORDER BY lastmod_gmt DESC, t.term_id DESC LIMIT %d OFFSET %d';

        $params[] = $perPage;
        $params[] = $offset;

        $args = array_merge([ $sql ], $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);
    }

    /**
     * Whether empty terms are listed.
     */
    private function includeEmptyTerms(): bool {
        return (bool) ($this->settings?->get('include_empty_terms', false) ?? false);
    }

    /**
     * Excluded term ids from settings, unique positive ints.
     *
     * @return int[]
     */
    private function excludedTermIds(): array {
        $ids = $this->settings?->get('exclude_terms', []) ?? [];

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
     * Append NOT IN clauses for excluded ids, chunked at 500 per clause.
     *
     * Returns an empty fragment when the list is empty, so default
     * queries keep their exact SQL shape.
     *
     * @param int[]             $ids    Excluded ids.
     * @param string            $column Qualified column, e.g. t.term_id.
     * @param array<int, mixed> $params Prepare params, ids appended in order.
     */
    private function excludeClause( array $ids, string $column, array &$params ): string {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if ([] === $ids) {
            return '';
        }

        $fragment = '';

        foreach (array_chunk($ids, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $fragment    .= " AND {$column} NOT IN ($placeholders)";

            foreach ($chunk as $id) {
                $params[] = $id;
            }
        }

        return $fragment;
    }

    /**
     * Drop rows whose stored canonical differs from the term link.
     *
     * One batched termmeta read for the page, decode per row, fail
     * open (unreadable payloads and unresolvable links are kept). Counts
     * stay unfiltered, an approximation the competitors accept too.
     *
     * @param array<int, mixed> $rows     Entry rows with term_id keys.
     * @param string            $taxonomy Taxonomy name.
     * @return array<int, mixed> Surviving rows.
     */
    private function dropCanonicalMismatchRows(array $rows, string $taxonomy): array {
        $ids = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['term_id'])) {
                continue;
            }

            $id = (int) $row['term_id'];

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
            if (! is_array($row) || ! isset($row['term_id'])) {
                continue;
            }

            $id        = (int) $row['term_id'];
            $canonical = $canonicals[ $id ] ?? '';

            if ('' === $canonical) {
                $kept[] = $row;

                continue;
            }

            $link = get_term_link($id, $taxonomy);

            if (is_wp_error($link) || ! is_string($link) || '' === $link) {
                $kept[] = $row;

                continue;
            }

            if (trailingslashit($canonical) !== trailingslashit($link)) {
                continue;
            }

            $kept[] = $row;
        }

        return $kept;
    }

    /**
     * Batch fetch non empty stored canonicals for term ids.
     *
     * @param int[] $ids Term ids.
     * @return array<int, string> Map of term id to canonical URL.
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

        $sql = "SELECT term_id, meta_value FROM {$wpdb->termmeta}"
            . " WHERE meta_key = %s AND term_id IN ($placeholders)";

        $args = array_merge([ $sql, self::META_KEY ], $ids);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $metaRows = $wpdb->get_results($wpdb->prepare(...$args), ARRAY_A);

        if (! is_array($metaRows)) {
            return [];
        }

        $out = [];

        foreach ($metaRows as $metaRow) {
            if (! is_array($metaRow) || ! isset($metaRow['term_id'], $metaRow['meta_value'])) {
                continue;
            }

            $termId = (int) $metaRow['term_id'];

            if (0 === $termId) {
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

            $out[ $termId ] = $payload['canonical'];
        }

        return $out;
    }
}
