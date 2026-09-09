<?php
/**
 * Sitemap cache with validator based invalidation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps;

/**
 * Cache wrapper for sitemap XML generation.
 *
 * Stores XML with validator strings, validates on read, and queues
 * invalidation on content changes.
 */
class SitemapCache {
    /**
     * Cache group for object cache.
     */
    public const GROUP = 'rankkernel-sitemaps';

    /**
     * Global validator option name.
     */
    public const VALIDATOR_GLOBAL = 'rankkernel_sitemap_validator_global';

    /**
     * Per set validator prefix.
     */
    public const VALIDATOR_PREFIX = 'rankkernel_sitemap_validator_';

    /**
     * Transient prefix for fallback storage.
     */
    private const TRANSIENT_PREFIX = 'rankkernel_sitemap_';

    /**
     * Queued invalidations.
     *
     * @var array<string, bool>
     */
    private array $queue = [];

    /**
     * Whether flush has already run this request.
     */
    private bool $flushed = false;

    /**
     * Whether shutdown hook has been registered.
     */
    private bool $shutdownHooked = false;

    /**
     * Check if cache is enabled.
     */
    public function isEnabled(): bool {
        /**
         * Filter whether sitemap cache is enabled.
         *
         * @param bool $enabled Default true.
         */
        $enabled = apply_filters('rankkernel/sitemap/enable_cache', true);

        return (bool) $enabled;
    }

    /**
     * Get cached XML or build it.
     *
     * @param string   $set     Sitemap set name, e.g. post, category, authors, index.
     * @param int      $page    Page number, 1 based.
     * @param callable $builder Builder that returns XML string.
     * @return string XML string.
     */
    public function get(string $set, int $page, callable $builder): string {
        if (! $this->isEnabled()) {
            return (string) $builder();
        }

        $cached = $this->getFromStore($this->cacheKey($set, $page));

        if (is_array($cached) && isset($cached['xml']) && is_string($cached['xml'])) {
            $currentGlobal = (string) get_option(self::VALIDATOR_GLOBAL, '');
            $currentSet    = (string) get_option(self::VALIDATOR_PREFIX . $set, '');

            $cachedGlobal = isset($cached['validator_global']) ? (string) $cached['validator_global'] : '';
            $cachedSet    = isset($cached['validator_set']) ? (string) $cached['validator_set'] : '';

            if ($cachedGlobal === $currentGlobal && $cachedSet === $currentSet) {
                return $cached['xml'];
            }
        }

        $xml = (string) $builder();

        $this->store($set, $page, $xml);

        return $xml;
    }

    /**
     * Read a cached array payload (for example the sitemap set map).
     *
     * Uses the global validator only, so any content change invalidates it.
     *
     * @param string   $key     Cache key.
     * @param callable $builder Builds the map on a cache miss.
     * @return array<string, int> Set name to page count map.
     */
    public function getMap(string $key, callable $builder): array {
        if (! $this->isEnabled()) {
            return (array) $builder();
        }

        $cached = $this->getFromStore($this->mapKey($key));

        if (is_array($cached) && isset($cached['map']) && is_array($cached['map'])) {
            $currentGlobal = (string) get_option(self::VALIDATOR_GLOBAL, '');
            $cachedGlobal  = isset($cached['validator_global']) ? (string) $cached['validator_global'] : '';

            if ($cachedGlobal === $currentGlobal) {
                return $cached['map'];
            }
        }

        $map = (array) $builder();

        $this->setToStore(
            $this->mapKey($key),
            [
                'map'              => $map,
                'validator_global' => (string) get_option(self::VALIDATOR_GLOBAL, ''),
            ]
        );

        return $map;
    }

    /**
     * Store XML in cache with current validators.
     *
     * @param string $set  Set name.
     * @param int    $page Page number.
     * @param string $xml  XML content.
     */
    public function store(string $set, int $page, string $xml): void {
        $payload = [
            'xml'              => $xml,
            'validator_global' => (string) get_option(self::VALIDATOR_GLOBAL, ''),
            'validator_set'    => (string) get_option(self::VALIDATOR_PREFIX . $set, ''),
        ];

        $this->setToStore($this->cacheKey($set, $page), $payload);
    }

    /**
     * Queue an invalidation for a set.
     *
     * @param string $set Set name or global.
     */
    public function queueInvalidation(string $set = 'global'): void {
        $this->queue[ $set ] = true;

        if (! $this->shutdownHooked) {
            $this->shutdownHooked = true;
            add_action('shutdown', [ $this, 'flushQueue' ], 10);
        }
    }

    /**
     * Flush queued invalidations, regenerates validator strings once per request.
     */
    public function flushQueue(): void {
        if ($this->flushed) {
            return;
        }

        $this->flushed = true;

        if ([] === $this->queue) {
            return;
        }

        foreach ($this->queue as $set => $flag) {
            if ('global' === $set) {
                $new = $this->generateValidator();
                update_option(self::VALIDATOR_GLOBAL, $new, false);
            } else {
                $new = $this->generateValidator();
                update_option(self::VALIDATOR_PREFIX . $set, $new, false);
            }
        }

        $this->queue = [];
    }

    /**
     * Register invalidation hooks.
     */
    public function registerHooks(): void {
        add_action('save_post', [ $this, 'onSavePost' ], 10, 3);
        add_action('edited_terms', [ $this, 'onEditedTerms' ], 10, 2);
        add_action('delete_term', [ $this, 'onDeletedTerm' ], 10, 3);
        add_action('clean_term_cache', [ $this, 'onCleanTermCache' ], 10, 2);
        add_action('user_register', [ $this, 'onUserRegister' ], 10, 1);
        add_action('delete_user', [ $this, 'onDeleteUser' ], 10, 1);
        add_action('profile_update', [ $this, 'onProfileUpdate' ], 10, 1);
        add_action('update_option_rankkernel_settings', [ $this, 'onSettingsUpdate' ], 10, 3);
        add_action('update_option_rankkernel_modules', [ $this, 'onSettingsUpdate' ], 10, 3);
    }

    /**
     * Handle save_post.
     *
     * @param int      $postId Post id.
     * @param mixed    $post   Post object.
     * @param bool     $update Whether this is an update.
     */
    public function onSavePost(int $postId, mixed $post, bool $update): void {
        $this->queueInvalidation('global');

        if (is_object($post) && isset($post->post_type) && is_string($post->post_type) && '' !== $post->post_type) {
            $this->queueInvalidation($post->post_type);
        }
    }

    /**
     * Handle edited terms.
     *
     * @param int    $termId   Term id.
     * @param string $taxonomy Taxonomy name.
     */
    public function onEditedTerms(int $termId, string $taxonomy): void {
        $this->queueInvalidation('global');
        $this->queueInvalidation($taxonomy);
    }

    /**
     * Handle deleted term.
     *
     * Hooked to delete_term (not deleted_term_taxonomy, which passes only
     * the term taxonomy id) because per taxonomy invalidation needs the
     * taxonomy name the hook provides.
     *
     * @param int    $term     Term id.
     * @param int    $ttId     Term taxonomy id.
     * @param string $taxonomy Taxonomy name.
     */
    public function onDeletedTerm(int $term, int $ttId, string $taxonomy): void {
        $this->queueInvalidation('global');

        if ('' !== $taxonomy) {
            $this->queueInvalidation($taxonomy);
        }
    }

    /**
     * Handle term cache cleaning.
     *
     * @param mixed  $ids      Term ids being cleaned.
     * @param string $taxonomy Taxonomy name.
     */
    public function onCleanTermCache(mixed $ids, string $taxonomy): void {
        $this->queueInvalidation('global');

        if ('' !== $taxonomy) {
            $this->queueInvalidation($taxonomy);
        }
    }

    /**
     * Handle user register.
     *
     * @param int $userId User id.
     */
    public function onUserRegister(int $userId): void {
        $this->queueInvalidation('global');
        $this->queueInvalidation('authors');
    }

    /**
     * Handle user delete.
     *
     * @param int $userId User id.
     */
    public function onDeleteUser(int $userId): void {
        $this->queueInvalidation('global');
        $this->queueInvalidation('authors');
    }

    /**
     * Handle profile update.
     *
     * @param int $userId User id.
     */
    public function onProfileUpdate(int $userId): void {
        $this->queueInvalidation('global');
        $this->queueInvalidation('authors');
    }

    /**
     * Handle settings update.
     *
     * @param mixed $oldValue Old value.
     * @param mixed $value    New value.
     * @param string $option  Option name.
     */
    public function onSettingsUpdate(mixed $oldValue, mixed $value, string $option): void {
        $this->queueInvalidation('global');
    }

    /**
     * Get from store, handles object cache vs transient fallback.
     *
     * @param string $key Final cache key.
     * @return mixed Cached payload or null.
     */
    private function getFromStore(string $key): mixed {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            $found = false;
            $value = wp_cache_get($key, self::GROUP, false, $found);

            if ($found) {
                return $value;
            }

            return null;
        }

        $transientKey = self::TRANSIENT_PREFIX . $key;
        $value        = get_transient($transientKey);

        if (false === $value) {
            return null;
        }

        return $value;
    }

    /**
     * Set to store.
     *
     * @param string $key     Final cache key.
     * @param mixed  $payload Payload to store.
     */
    private function setToStore(string $key, mixed $payload): void {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            wp_cache_set($key, $payload, self::GROUP, 0);

            return;
        }

        $transientKey = self::TRANSIENT_PREFIX . $key;
        set_transient($transientKey, $payload, 0);
    }

    /**
     * Build cache key.
     *
     * @param string $set  Set name.
     * @param int    $page Page number.
     * @return string
     */
    private function cacheKey(string $set, int $page): string {
        return 'xml_' . $set . '_' . (string) $page;
    }

    /**
     * Map cache key, namespaced away from XML keys so a post type named
     * sets or index can never collide with internal payloads.
     *
     * @param string $key Map key.
     */
    private function mapKey(string $key): string {
        return 'map_' . $key . '_1';
    }

    /**
     * Generate a validator string.
     */
    private function generateValidator(): string {
        return (string) time() . '_' . uniqid('', true);
    }
}
