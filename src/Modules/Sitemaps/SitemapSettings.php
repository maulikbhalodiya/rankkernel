<?php
/**
 * Sitemap settings store.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps;

/**
 * Manages the rankkernel_sitemap_settings option.
 *
 * Small array, read on every sitemap render, so it is registered
 * with autoload YES.
 */
final class SitemapSettings {
    /**
     * Option name.
     */
    public const OPTION = 'rankkernel_sitemap_settings';

    /**
     * Fixed setting keys (dynamic per type keys are matched by pattern).
     *
     * @var string[]
     */
    private const FIXED_KEYS = [
        'items_per_page',
        'include_images',
        'include_featured_image',
        'exclude_posts',
        'exclude_terms',
        'include_empty_terms',
        'authors_sitemap',
        'authors_include_empty',
        'authors_exclude_roles',
        'authors_exclude_users',
    ];

    /**
     * Cached merged settings (defaults plus stored).
     *
     * @var array<string, mixed>|null
     */
    private ?array $cache = null;

    /**
     * Get default settings.
     *
     * Per type pt_{type}_sitemap and per taxonomy tax_{type}_sitemap
     * keys are dynamic and default to true when absent, so they are
     * not pre seeded here.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'items_per_page'         => 1000,
            'include_images'         => true,
            'include_featured_image' => true,
            'exclude_posts'          => [],
            'exclude_terms'          => [],
            'include_empty_terms'    => false,
            'authors_sitemap'        => true,
            'authors_include_empty'  => false,
            'authors_exclude_roles'  => [],
            'authors_exclude_users'  => [],
        ];
    }

    /**
     * Get a setting value.
     *
     * @param string $key      Setting key.
     * @param mixed  $fallback Fallback if not set.
     * @return mixed
     */
    public function get( string $key, mixed $fallback = null ): mixed {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[ $key ];
        }

        return $fallback;
    }

    /**
     * Get all merged settings.
     *
     * @return array<string, mixed>
     */
    public function all(): array {
        if (null !== $this->cache) {
            return $this->cache;
        }

        // Some unit tests boot providers without defining get_option.
        // Fall back to defaults there. Real WordPress always defines it.
        $stored = function_exists('get_option') ? get_option(self::OPTION, []) : [];

        if (! is_array($stored)) {
            $stored = [];
        }

        $this->cache = array_merge(self::defaults(), $stored);

        return $this->cache;
    }

    /**
     * Update settings with a partial array (whitelisted keys only).
     *
     * Unknown keys are dropped. The partial is merged over the stored
     * values, so tabbed admin saves only touch their own keys.
     *
     * @param array<string, mixed> $partial Partial settings to merge.
     * @return bool Whether anything was saved.
     */
    public function set( array $partial ): bool {
        $sanitized = [];

        foreach ($partial as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (! $this->isAllowed($key)) {
                continue;
            }

            $sanitized[ $key ] = $this->sanitize($key, $value);
        }

        if ([] === $sanitized) {
            return false;
        }

        $merged = array_merge($this->all(), $sanitized);

        update_option(self::OPTION, $merged, true);

        $this->cache = $merged;

        return true;
    }

    /**
     * Whether a post type or taxonomy sitemap is enabled.
     *
     * Dynamic keys default to true when absent.
     *
     * @param string $kind Either pt or tax.
     * @param string $name Post type or taxonomy name.
     */
    public function isTypeEnabled( string $kind, string $name ): bool {
        $key = $kind . '_' . $name . '_sitemap';
        $all = $this->all();

        if (! array_key_exists($key, $all)) {
            return true;
        }

        return (bool) $all[ $key ];
    }

    /**
     * Whether a key may be stored.
     *
     * @param string $key Setting key.
     */
    private function isAllowed( string $key ): bool {
        if (in_array($key, self::FIXED_KEYS, true)) {
            return true;
        }

        return $this->isDynamicKey($key);
    }

    /**
     * Whether a key is a dynamic per type toggle.
     *
     * @param string $key Setting key.
     */
    private function isDynamicKey( string $key ): bool {
        if (1 === preg_match('/^pt_[a-z0-9_]+_sitemap$/', $key)) {
            return true;
        }

        if (1 === preg_match('/^tax_[a-z0-9_]+_sitemap$/', $key)) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize a single value by key.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Raw value.
     * @return mixed Sanitized value.
     */
    private function sanitize( string $key, mixed $value ): mixed {
        if ('items_per_page' === $key) {
            $perPage = (int) $value;

            if ($perPage < 1) {
                $perPage = 1;
            }

            if ($perPage > 50000) {
                $perPage = 50000;
            }

            return $perPage;
        }

        if ('exclude_posts' === $key || 'exclude_terms' === $key || 'authors_exclude_users' === $key) {
            return $this->sanitizeIdList($value);
        }

        if ('authors_exclude_roles' === $key) {
            return $this->sanitizeRoleList($value);
        }

        return (bool) $value;
    }

    /**
     * Filter an id list to unique positive ints.
     *
     * @param mixed $value Raw list (array or comma separated string).
     * @return int[]
     */
    private function sanitizeIdList( mixed $value ): array {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $item) {
            $id = absint($item);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Filter a role list to unique non empty slugs.
     *
     * @param mixed $value Raw list (array or comma separated string).
     * @return string[]
     */
    private function sanitizeRoleList( mixed $value ): array {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $roles = [];

        foreach ($value as $item) {
            $role = sanitize_key((string) $item);

            if ('' !== $role) {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }
}
