<?php
/**
 * Fake wpdb for sitemap settings tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

/**
 * Minimal wpdb double honoring exclusion clauses and left joins.
 *
 * Parses the real SQL text for NOT IN id groups, role LIKE fragments,
 * and join shapes, so provider settings behavior is tested for real.
 */
final class SitemapSettingsFakeWpdb {
    /** @var string */
    public $posts = 'wp_posts';
    /** @var string */
    public $postmeta = 'wp_postmeta';
    /** @var string */
    public $terms = 'wp_terms';
    /** @var string */
    public $term_taxonomy = 'wp_term_taxonomy';
    /** @var string */
    public $term_relationships = 'wp_term_relationships';
    /** @var string */
    public $termmeta = 'wp_termmeta';
    /** @var string */
    public $users = 'wp_users';
    /** @var string */
    public $usermeta = 'wp_usermeta';
    /** @var string */
    public $prefix = 'wp_';

    /** @var array<int, array<string, mixed>> */
    public array $postsRows = [];
    /** @var array<int, string> */
    public array $postmetaRows = [];
    /** @var array<int, int> */
    public array $termsList = [];
    /** @var array<int, string> */
    public array $termTaxonomy = [];
    /** @var array<int, int[]> */
    public array $relationships = [];
    /** @var array<int, string> */
    public array $termmetaRows = [];
    /** @var array<int, string> */
    public array $usersRows = [];
    /** @var array<int, string[]> */
    public array $userRoles = [];

    /** @var string */
    public string $lastSql = '';

    /** @var string[] */
    public array $queries = [];

    public function esc_like(string $text): string {
        return addcslashes($text, '_%\\');
    }

    public function prepare(string $query, mixed ...$args): string {
        if (1 === count($args) && is_array($args[0])) {
            $args = $args[0];
        }

        foreach ($args as $arg) {
            if (is_int($arg) || (is_string($arg) && ctype_digit($arg))) {
                $query = (string) preg_replace('/%d/', (string) (int) $arg, $query, 1);
            } else {
                $query = (string) preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $query, 1);
            }
        }

        $this->lastSql   = $query;
        $this->queries[] = $query;

        return $query;
    }

    /**
     * Every id inside any NOT IN digit group.
     *
     * @return int[]
     */
    private function notInIds(string $sql): array {
        $ids = [];

        if (preg_match_all('/NOT IN \(([\d,\s]+)\)/', $sql, $matches) >= 1) {
            foreach ($matches[1] as $group) {
                foreach (explode(',', (string) $group) as $part) {
                    $id = (int) trim($part);

                    if (0 !== $id) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Role slugs inside %"slug"% LIKE fragments.
     *
     * @return string[]
     */
    private function roleFragments(string $sql): array {
        $roles = [];

        if (preg_match_all('/%"([^"]+)"%/', stripcslashes($sql), $matches) >= 1) {
            foreach ($matches[1] as $role) {
                $roles[] = (string) $role;
            }
        }

        return $roles;
    }

    /**
     * Whether a user id is excluded by NOT IN or role clauses.
     */
    private function userExcluded(int $userId, string $sql): bool {
        if (in_array($userId, $this->notInIds($sql), true)) {
            return true;
        }

        $roles     = $this->userRoles[ $userId ] ?? [];
        $fragments = $this->roleFragments($sql);

        foreach ($fragments as $fragment) {
            if (in_array($fragment, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a LIKE pattern from the SQL as a matcher regex.
     */
    private function likePattern(string $sql): ?string {
        if (1 !== preg_match("/LIKE '((?:[^'\\\\]|\\\\.)*)'/", $sql, $m)) {
            return null;
        }

        $parts = explode('%', stripcslashes($m[1]));
        $regex = '/^';

        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $regex .= '.*';
            }

            $regex .= preg_quote($part, '/');
        }

        return $regex . '$/s';
    }

    /**
     * Whether a meta value is excluded by the SQL LIKE clause.
     */
    private function likeExcludes(?string $meta, ?string $regex): bool {
        if (null === $regex || null === $meta) {
            return false;
        }

        return 1 === preg_match($regex, $meta);
    }

    /**
     * Post types inside a post_type IN string list.
     *
     * @return string[]
     */
    private function sqlTypes(string $sql): array {
        $types = [];

        if (1 === preg_match('/post_type IN \(([^)]+)\)/', $sql, $m)) {
            foreach (explode(',', $m[1]) as $part) {
                $type = trim($part, " '");

                if ('' !== $type) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    public function get_var(?string $query = null): mixed {
        $sql = (string) $query;

        if (str_contains($sql, 'COUNT(DISTINCT post_author)')) {
            return count($this->matchingAuthors($sql));
        }

        if (str_contains($sql, 'COUNT(DISTINCT t.term_id)')) {
            return count($this->matchingTerms($sql, false));
        }

        if (str_contains($sql, 'FROM ' . $this->users)) {
            return count($this->matchingUsers($sql));
        }

        if (str_contains($sql, 'FROM ' . $this->terms)) {
            return count($this->matchingTerms($sql, true));
        }

        return count($this->matchingPosts($sql));
    }

    public function get_results(?string $query = null, mixed $output = null): mixed {
        $sql = (string) $query;

        if (1 === preg_match('/SELECT (?:post_id|term_id), meta_value/', $sql)) {
            return $this->metaBatchRows($sql);
        }

        if (str_contains($sql, 't.term_id')) {
            return $this->matchingTermRows($sql);
        }

        if (str_contains($sql, 'FROM ' . $this->users)) {
            return $this->matchingUserRows($sql);
        }

        if (str_contains($sql, 'GROUP BY post_author')) {
            return $this->matchingAuthorRows($sql);
        }

        return $this->matchingPostRows($sql);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchingPosts(string $sql): array {
        $type = 1 === preg_match("/post_type = '([^']+)'/", $sql, $m) ? $m[1] : '';
        $like = $this->likePattern($sql);
        $not  = $this->notInIds($sql);
        $out  = [];

        foreach ($this->postsRows as $row) {
            if (($row['post_type'] ?? '') !== $type) {
                continue;
            }

            if (($row['post_status'] ?? '') !== 'publish') {
                continue;
            }

            if (str_contains($sql, 'post_password') && '' !== ($row['post_password'] ?? '')) {
                continue;
            }

            $id = (int) ($row['ID'] ?? 0);

            if (in_array($id, $not, true)) {
                continue;
            }

            $meta = $this->postmetaRows[ $id ] ?? null;

            if ($this->likeExcludes($meta, $like)) {
                continue;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchingPostRows(string $sql): array {
        $rows = $this->matchingPosts($sql);

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['post_modified_gmt'] ?? ''), (string) ($a['post_modified_gmt'] ?? ''));

            if (0 !== $cmp) {
                return $cmp;
            }

            return (int) ($b['ID'] ?? 0) - (int) ($a['ID'] ?? 0);
        });

        $slice = array_slice($rows, $this->sqlOffset($sql), $this->sqlLimit($sql));
        $out   = [];

        foreach ($slice as $row) {
            $out[] = [
                'ID'                => $row['ID'],
                'post_modified_gmt' => $row['post_modified_gmt'],
            ];
        }

        return $out;
    }

    private function sqlLimit(string $sql): int {
        return 1 === preg_match('/LIMIT (\d+)/', $sql, $m) ? (int) $m[1] : 1000;
    }

    private function sqlOffset(string $sql): int {
        return 1 === preg_match('/OFFSET (\d+)/', $sql, $m) ? (int) $m[1] : 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function metaBatchRows(string $sql): array {
        $ids = [];

        if (1 === preg_match('/IN \(([\d,\s]+)\)/', $sql, $m)) {
            foreach (explode(',', $m[1]) as $part) {
                $id = (int) trim($part);

                if (0 !== $id) {
                    $ids[] = $id;
                }
            }
        }

        $isTerm = str_contains($sql, $this->termmeta);
        $key    = $isTerm ? 'term_id' : 'post_id';
        $map    = $isTerm ? $this->termmetaRows : $this->postmetaRows;
        $out    = [];

        foreach ($ids as $id) {
            if (! array_key_exists($id, $map)) {
                continue;
            }

            $out[] = [ $key => $id, 'meta_value' => $map[ $id ] ];
        }

        return $out;
    }

    /**
     * Terms of a taxonomy passing noindex and NOT IN filters.
     *
     * @return int[]
     */
    private function filteredTerms(string $sql): array {
        $taxonomy = 1 === preg_match("/taxonomy = '([^']+)'/", $sql, $m) ? $m[1] : '';
        $like     = $this->likePattern($sql);
        $not      = $this->notInIds($sql);
        $out      = [];

        foreach ($this->termsList as $termId) {
            if (($this->termTaxonomy[ $termId ] ?? '') !== $taxonomy) {
                continue;
            }

            if (in_array($termId, $not, true)) {
                continue;
            }

            $meta = $this->termmetaRows[ $termId ] ?? null;

            if ($this->likeExcludes($meta, $like)) {
                continue;
            }

            $out[] = $termId;
        }

        return $out;
    }

    /**
     * @return int[]
     */
    private function matchingTerms(string $sql, bool $includeEmpty): array {
        $out = [];

        foreach ($this->filteredTerms($sql) as $termId) {
            if (! $includeEmpty && ! $this->termHasPublishedPost($termId, $sql)) {
                continue;
            }

            $out[] = $termId;
        }

        return $out;
    }

    private function termHasPublishedPost(int $termId, string $sql): bool {
        $types = $this->sqlTypes($sql);

        foreach ($this->relationships[ $termId ] ?? [] as $postId) {
            foreach ($this->postsRows as $row) {
                if ((int) ($row['ID'] ?? 0) !== $postId) {
                    continue;
                }

                if (($row['post_status'] ?? '') === 'publish' && in_array($row['post_type'] ?? '', $types, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Latest related post date for a term, null when post less.
     */
    private function termLastmod(int $termId): ?string {
        $latest = null;

        foreach ($this->relationships[ $termId ] ?? [] as $postId) {
            foreach ($this->postsRows as $row) {
                if ((int) ($row['ID'] ?? 0) === $postId) {
                    $stamp = (string) ($row['post_modified_gmt'] ?? '');

                    if ('' !== $stamp && (null === $latest || $stamp > $latest)) {
                        $latest = $stamp;
                    }
                }
            }
        }

        return $latest;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchingTermRows(string $sql): array {
        $includeEmpty = str_contains($sql, 'LEFT JOIN');
        $rows         = [];

        foreach ($this->filteredTerms($sql) as $termId) {
            $latest = $this->termLastmod($termId);

            if (null === $latest && ! $includeEmpty) {
                continue;
            }

            // Joined mode only lists terms with published posts.
            if (null !== $latest && ! $includeEmpty && ! $this->termHasPublishedPost($termId, $sql)) {
                continue;
            }

            $rows[] = [ 'term_id' => $termId, 'lastmod_gmt' => $latest ];
        }

        usort($rows, static function (array $a, array $b): int {
            $la = $a['lastmod_gmt'] ?? null;
            $lb = $b['lastmod_gmt'] ?? null;

            if (null === $la && null === $lb) {
                return (int) $b['term_id'] - (int) $a['term_id'];
            }

            if (null === $la) {
                return 1;
            }

            if (null === $lb) {
                return -1;
            }

            $cmp = strcmp((string) $lb, (string) $la);

            if (0 !== $cmp) {
                return $cmp;
            }

            return (int) $b['term_id'] - (int) $a['term_id'];
        });

        return array_slice($rows, $this->sqlOffset($sql), $this->sqlLimit($sql));
    }

    /**
     * Users passing NOT IN and role filters.
     *
     * @return int[]
     */
    private function matchingUsers(string $sql): array {
        $out = [];

        foreach ($this->usersRows as $userId => $registered) {
            if ($this->userExcluded((int) $userId, $sql)) {
                continue;
            }

            $out[] = (int) $userId;
        }

        sort($out);

        return $out;
    }

    /**
     * Post based author rows, grouped and paged like the provider query.
     *
     * @return array<int, array<string, mixed>>
     */
    private function matchingAuthorRows(string $sql): array {
        $authors = $this->matchingAuthors($sql);

        sort($authors);

        $slice = array_slice($authors, $this->sqlOffset($sql), $this->sqlLimit($sql));
        $out   = [];

        foreach ($slice as $author) {
            $out[] = [
                'post_author' => $author,
                'lastmod_gmt' => $this->userLastmod($author, $sql),
            ];
        }

        return $out;
    }

    /**
     * Latest publish date for a user across eligible types.
     */
    private function userLastmod(int $userId, string $sql): ?string {
        $types  = $this->sqlTypes($sql);
        $latest = null;

        foreach ($this->postsRows as $row) {
            if ((int) ($row['post_author'] ?? 0) !== $userId) {
                continue;
            }

            if (($row['post_status'] ?? '') !== 'publish') {
                continue;
            }

            if (! in_array($row['post_type'] ?? '', $types, true)) {
                continue;
            }

            $stamp = (string) ($row['post_modified_gmt'] ?? '');

            if ('' !== $stamp && (null === $latest || $stamp > $latest)) {
                $latest = $stamp;
            }
        }

        return $latest;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchingUserRows(string $sql): array {
        $rows = [];

        foreach ($this->matchingUsers($sql) as $userId) {
            $rows[] = [
                'post_author'     => $userId,
                'lastmod_gmt'     => $this->userLastmod($userId, $sql),
                'user_registered' => $this->usersRows[ $userId ],
            ];
        }

        return array_slice($rows, $this->sqlOffset($sql), $this->sqlLimit($sql));
    }

    /**
     * Authors of eligible posts, minus exclusions.
     *
     * @return int[]
     */
    private function matchingAuthors(string $sql): array {
        $types   = $this->sqlTypes($sql);
        $authors = [];

        foreach ($this->postsRows as $row) {
            if (($row['post_status'] ?? '') !== 'publish') {
                continue;
            }

            if (! in_array($row['post_type'] ?? '', $types, true)) {
                continue;
            }

            $author = (int) ($row['post_author'] ?? 0);

            if (0 === $author) {
                continue;
            }

            if ($this->userExcluded($author, $sql)) {
                continue;
            }

            $authors[ $author ] = true;
        }

        return array_keys($authors);
    }
}
