<?php
/**
 * Fake wpdb for sitemap canonical tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit\Support;

/**
 * Minimal wpdb double serving fixed rows plus a meta map.
 */
final class SitemapCanonFakeWpdb {
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

    /** @var array<int, array<string, mixed>> */
    public array $postRows = [];
    /** @var array<int, string> */
    public array $postmetaRows = [];
    /** @var array<int, array<string, mixed>> */
    public array $termRows = [];
    /** @var array<int, string> */
    public array $termmetaRows = [];

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

        return $query;
    }

    public function get_var(?string $query = null): mixed {
        return 1;
    }

    public function get_results(?string $query = null, mixed $output = null): mixed {
        $sql = (string) $query;

        if (1 === preg_match('/SELECT (?:post_id|term_id), meta_value/', $sql)) {
            $ids = [];

            if (1 === preg_match('/IN \(([\d,\s]+)\)/', $sql, $m)) {
                foreach (explode(',', $m[1]) as $part) {
                    $id = (int) trim($part);

                    if (0 !== $id) {
                        $ids[] = $id;
                    }
                }
            }

            $isTerm = str_contains($sql, 'wp_termmeta');
            $key    = $isTerm ? 'term_id' : 'post_id';
            $map    = $isTerm ? $this->termmetaRows : $this->postmetaRows;
            $out    = [];

            foreach ($ids as $id) {
                if (array_key_exists($id, $map)) {
                    $out[] = [ $key => $id, 'meta_value' => $map[ $id ] ];
                }
            }

            return $out;
        }

        if (str_contains($sql, 't.term_id')) {
            return $this->termRows;
        }

        return $this->postRows;
    }
}
