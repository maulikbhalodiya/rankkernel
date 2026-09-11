<?php
/**
 * Schema graph normalizer.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema;

/**
 * Final cleanup pass over the assembled @graph.
 *
 * Pieces avoid empty values on their own, but filters and stored
 * node lists can inject anything. Every rule here is structural and
 * never destroys valid data: non nodes are dropped, nodes without a
 * @type are dropped, nested @context keys are stripped, empty
 * strings, nulls, and empty arrays are removed recursively (zero,
 * false, and '0' survive), duplicate @ids keep the first node, and
 * internal refs that point at missing nodes are pruned. External
 * refs are never touched.
 */
final class GraphNormalizer {
    /**
     * Normalize a raw graph into clean nodes.
     *
     * @param mixed $graph Raw graph value.
     * @return array<int, array<string, mixed>>
     */
    public static function normalize( mixed $graph ): array {
        if (! is_array($graph)) {
            return [];
        }

        $nodes = [];

        foreach ($graph as $item) {
            if (! is_array($item) || [] === $item) {
                continue;
            }

            if (array_is_list($item)) {
                continue;
            }

            $node = self::cleanValue($item);

            if (! is_array($node) || [] === $node) {
                continue;
            }

            $type = $node['@type'] ?? '';

            if (is_array($type)) {
                $kept = array_filter(
                    $type,
                    static fn (mixed $t): bool => is_string($t) && '' !== trim($t)
                );
                $type = array_values($kept);

                if ([] === $type) {
                    continue;
                }

                $node['@type'] = $type;
            } elseif (! is_string($type) || '' === trim($type)) {
                continue;
            }

            unset($node['@context']);

            if ([] === $node) {
                continue;
            }

            $nodes[] = $node;
        }

        $nodes = self::dedupeIds($nodes);

        return array_values(self::pruneDanglingRefs($nodes));
    }

    /**
     * Recursively strip empty values from a level.
     *
     * Empty means null, empty string, or empty array. Zero, false,
     * and the string '0' are real values and survive.
     *
     * @param mixed $value Raw value.
     * @return mixed Clean value, null when the level is empty.
     */
    private static function cleanValue( mixed $value ): mixed {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            return '' === trim($value) ? null : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if ([] === $value) {
            return null;
        }

        $out = [];

        foreach ($value as $key => $item) {
            if ('@context' === $key) {
                continue;
            }

            $clean = self::cleanValue($item);

            if (null === $clean) {
                continue;
            }

            $out[ $key ] = $clean;
        }

        return [] === $out ? null : $out;
    }

    /**
     * Drop later nodes that reuse an earlier @id.
     *
     * @param array<int, array<string, mixed>> $nodes Clean nodes.
     * @return array<int, array<string, mixed>>
     */
    private static function dedupeIds( array $nodes ): array {
        $seen = [];
        $out  = [];

        foreach ($nodes as $node) {
            $id = $node['@id'] ?? '';

            if (is_string($id) && '' !== $id) {
                if (isset($seen[ $id ])) {
                    continue;
                }

                $seen[ $id ] = true;
            }

            $out[] = $node;
        }

        return $out;
    }

    /**
     * Prune internal refs that point at missing nodes.
     *
     * Only refs under our own home root are considered, external
     * @ids from third party data are never touched. A ref is an
     * array holding exactly one @id key.
     *
     * @param array<int, array<string, mixed>> $nodes Clean nodes.
     * @return array<int, array<string, mixed>>
     */
    private static function pruneDanglingRefs( array $nodes ): array {
        $ids = [];

        foreach ($nodes as $node) {
            $id = $node['@id'] ?? '';

            if (is_string($id) && '' !== $id) {
                $ids[ $id ] = true;
            }
        }

        $root = self::homeRoot();

        $out = [];

        foreach ($nodes as $node) {
            $pruned = self::pruneLevel($node, $ids, $root);

            if (is_array($pruned) && [] !== $pruned) {
                $out[] = $pruned;
            }
        }

        return $out;
    }

    /**
     * Prune one level, dropping dangling internal refs.
     *
     * @param mixed                $value Raw level.
     * @param array<string, bool>  $ids   Known node @ids.
     * @param string               $root  Home root prefix.
     * @return mixed Pruned level, null when empty.
     */
    private static function pruneLevel( mixed $value, array $ids, string $root ): mixed {
        if (! is_array($value)) {
            return $value;
        }

        if ([] === $value) {
            return null;
        }

        if (self::isRef($value)) {
            $target = (string) $value['@id'];

            if ('' !== $root && str_starts_with($target, $root) && ! isset($ids[ $target ])) {
                return null;
            }

            return $value;
        }

        $out = [];

        foreach ($value as $key => $item) {
            $pruned = self::pruneLevel($item, $ids, $root);

            if (null === $pruned) {
                continue;
            }

            $out[ $key ] = $pruned;
        }

        if ([] === $out) {
            return null;
        }

        return array_is_list($value) ? array_values($out) : $out;
    }

    /**
     * Whether a value is an @id only reference.
     *
     * @param array<mixed, mixed> $value Raw value.
     */
    private static function isRef( array $value ): bool {
        if (1 !== count($value)) {
            return false;
        }

        if (! array_key_exists('@id', $value)) {
            return false;
        }

        return is_string($value['@id']) && '' !== $value['@id'];
    }

    /**
     * Home root with trailing slash, empty when unavailable.
     */
    private static function homeRoot(): string {
        $home = function_exists('home_url') ? (string) home_url('/') : '';

        if ('' === trim($home)) {
            return '';
        }

        if (function_exists('trailingslashit')) {
            return trailingslashit($home);
        }

        return rtrim($home, '/') . '/';
    }
}
