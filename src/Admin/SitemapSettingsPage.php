<?php
/**
 * Sitemap settings page, render plus save handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Modules\Sitemaps\SitemapCache;
use RankKernel\Modules\Sitemaps\SitemapSettings;

/**
 * Renders the RankKernel sitemap settings page and handles saves.
 *
 * Tabbed form, one tab saved at a time. Saves merge over stored
 * values, so untouched tabs keep their settings.
 */
final class SitemapSettingsPage {
    /**
     * Tab ids in display order.
     *
     * @var string[]
     */
    private const TABS = [ 'general', 'post-types', 'taxonomies', 'authors' ];

    /**
     * Constructor.
     *
     * @param SitemapSettings $settings Sitemap settings store.
     */
    public function __construct(
        private readonly SitemapSettings $settings
    ) {
    }

    /**
     * Handle a POST save on the load hook, before any output is sent.
     *
     * Runs on load-{page}, so wp_safe_redirect can still send headers.
     */
    public function maybeHandleSave(): void {
        if ('POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset($_POST['rankkernel_sitemap_save'])) {
            $this->handleSave();
        }
    }

    /**
     * Current tab from the query string, general on unknown input.
     */
    public function currentTab(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        $raw = $_GET['tab'] ?? '';

        if (! is_string($raw)) {
            return 'general';
        }

        $tab = sanitize_key(wp_unslash($raw));

        if (! in_array($tab, self::TABS, true)) {
            return 'general';
        }

        return $tab;
    }

    /**
     * Render the page.
     */
    public function render(): void {
        $this->renderNotices();

        $tab = $this->currentTab();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Sitemap Settings', 'rankkernel') . '</h1>';

        $this->renderTabs($tab);
        $this->renderForm($tab);

        echo '</div>';
    }

    /**
     * Handle save, capability plus nonce, then persist and redirect.
     */
    private function handleSave(): void {
        if (! current_user_can('manage_options')) {
            wp_die(
                esc_html__('Sorry, you are not allowed to manage RankKernel settings.', 'rankkernel'),
                '',
                [ 'response' => 403 ]
            );
        }

        $verified = check_admin_referer('rankkernel_sitemap_settings');
        if (false === $verified) {
            wp_die(
                esc_html__('Security check failed. Please refresh and try again.', 'rankkernel'),
                '',
                [ 'response' => 403 ]
            );
        }

        $tab = $this->currentTab();

        $this->settings->set($this->collectPartial($tab));

        SitemapCache::invalidateAll();

        $redirect = admin_url('admin.php?page=rankkernel-sitemap&tab=' . $tab . '&settings-updated=1');
        wp_safe_redirect($redirect);

        if (! defined('RANKKERNEL_TESTING')) {
            exit;
        }
    }

    /**
     * Collect the current tab fields from POST.
     *
     * Checkbox absent means false. Id lists parse from comma text.
     *
     * @param string $tab Current tab id.
     * @return array<string, mixed>
     */
    private function collectPartial( string $tab ): array {
        $partial = [];

        if ('general' === $tab) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
            if (isset($_POST['items_per_page'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast below, clamped on save.
                $rawItems = wp_unslash($_POST['items_per_page']);
                $partial['items_per_page'] = is_string($rawItems) ? (int) $rawItems : 0;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
            $partial['include_images'] = isset($_POST['include_images']);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
            $partial['include_featured_image'] = isset($_POST['include_featured_image']);

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
            if (isset($_POST['exclude_posts'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- exploded below, absint on save.
                $rawPosts = wp_unslash($_POST['exclude_posts']);
                $partial['exclude_posts'] = is_string($rawPosts) ? explode(',', $rawPosts) : [];
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
            if (isset($_POST['exclude_terms'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- exploded below, absint on save.
                $rawTerms = wp_unslash($_POST['exclude_terms']);
                $partial['exclude_terms'] = is_string($rawTerms) ? explode(',', $rawTerms) : [];
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
            $partial['include_empty_terms'] = isset($_POST['include_empty_terms']);

            return $partial;
        }

        if ('post-types' === $tab) {
            foreach ($this->publicPostTypes() as $slug => $label) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
                $partial[ 'pt_' . $slug . '_sitemap' ] = isset($_POST[ 'pt_' . $slug . '_sitemap' ]);
            }

            return $partial;
        }

        if ('taxonomies' === $tab) {
            foreach ($this->publicTaxonomies() as $slug => $label) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
                $partial[ 'tax_' . $slug . '_sitemap' ] = isset($_POST[ 'tax_' . $slug . '_sitemap' ]);
            }

            return $partial;
        }

        // Authors tab.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
        $partial['authors_sitemap'] = isset($_POST['authors_sitemap']);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
        $partial['authors_include_empty'] = isset($_POST['authors_include_empty']);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
        $rawRoles = $_POST['authors_exclude_roles'] ?? [];
        if (! is_array($rawRoles)) {
            $rawRoles = [];
        }

        $roles = [];

        foreach ($rawRoles as $rawRole) {
            if (is_string($rawRole) || is_numeric($rawRole)) {
                $roles[] = (string) $rawRole;
            }
        }

        $partial['authors_exclude_roles'] = $roles;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
        if (isset($_POST['authors_exclude_users'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- exploded below, absint on save.
            $rawUsers = wp_unslash($_POST['authors_exclude_users']);
            $partial['authors_exclude_users'] = is_string($rawUsers) ? explode(',', $rawUsers) : [];
        }

        return $partial;
    }

    /**
     * Public post types as slug to label, without attachments.
     *
     * @return array<string, string>
     */
    private function publicPostTypes(): array {
        $types = get_post_types([ 'public' => true ], 'objects');

        if (! is_array($types)) {
            return [];
        }

        return $this->slugsWithLabels($types, [ 'attachment' ]);
    }

    /**
     * Public taxonomies as slug to label.
     *
     * @return array<string, string>
     */
    private function publicTaxonomies(): array {
        $taxonomies = get_taxonomies([ 'public' => true ], 'objects');

        if (! is_array($taxonomies)) {
            return [];
        }

        return $this->slugsWithLabels($taxonomies, []);
    }

    /**
     * Reduce an objects or names map to slug to label pairs.
     *
     * Slugs outside the settings key pattern are skipped, their keys
     * could never be stored.
     *
     * @param array<mixed, mixed> $map     Type map from core.
     * @param string[]            $skip    Slugs to drop.
     * @return array<string, string>
     */
    private function slugsWithLabels( array $map, array $skip ): array {
        $out = [];

        foreach ($map as $slug => $item) {
            if (! is_string($slug) || '' === $slug || in_array($slug, $skip, true)) {
                continue;
            }

            if (1 !== preg_match('/^[a-z0-9_]+$/', $slug)) {
                continue;
            }

            $label = $slug;

            if (is_object($item) && isset($item->label) && is_string($item->label) && '' !== $item->label) {
                $label = $item->label;
            } elseif (is_string($item) && '' !== $item) {
                $label = $item;
            }

            $out[ $slug ] = $label;
        }

        return $out;
    }

    /**
     * Render admin notices (success on settings-updated).
     */
    private function renderNotices(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag.
        if (isset($_GET['settings-updated']) && '1' === (string) $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Settings saved.', 'rankkernel');
            echo '</p></div>';
        }
    }

    /**
     * Render the tab links.
     *
     * @param string $current Current tab id.
     */
    private function renderTabs( string $current ): void {
        $tabs = [
            'general'    => __('General', 'rankkernel'),
            'post-types' => __('Post Types', 'rankkernel'),
            'taxonomies' => __('Taxonomies', 'rankkernel'),
            'authors'    => __('Authors', 'rankkernel'),
        ];

        echo '<h2 class="nav-tab-wrapper">';

        foreach ($tabs as $id => $label) {
            $url   = admin_url('admin.php?page=rankkernel-sitemap&tab=' . $id);
            $class = 'nav-tab' . ( $id === $current ? ' nav-tab-active' : '');

            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">';
            echo esc_html($label);
            echo '</a>';
        }

        echo '</h2>';
    }

    /**
     * Render the tabbed form.
     *
     * @param string $tab Current tab id.
     */
    private function renderForm( string $tab ): void {
        $all = $this->settings->all();

        echo '<form method="post" action="">';

        wp_nonce_field('rankkernel_sitemap_settings');

        if ('general' === $tab) {
            $this->renderGeneral($all);
        } elseif ('post-types' === $tab) {
            $this->renderPostTypes($all);
        } elseif ('taxonomies' === $tab) {
            $this->renderTaxonomies($all);
        } else {
            $this->renderAuthors($all);
        }

        submit_button(__('Save Sitemap Settings', 'rankkernel'), 'primary', 'rankkernel_sitemap_save');

        echo '</form>';
    }

    /**
     * Render the general section.
     *
     * @param array<string, mixed> $all Merged settings.
     */
    private function renderGeneral( array $all ): void {
        echo '<h2>' . esc_html__('General', 'rankkernel') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="rk-items-per-page">';
        echo esc_html__('Entries per page', 'rankkernel');
        echo '</label></th><td>';
        echo '<input type="number" id="rk-items-per-page" name="items_per_page" value="'
            . esc_attr((string) ( $all['items_per_page'] ?? 1000 ))
            . '" class="small-text" min="1" max="50000" />';
        echo '</td></tr>';

        $this->renderCheckboxRow(
            'include_images',
            __('Include images', 'rankkernel'),
            ! empty($all['include_images']),
            __('List featured images in post sitemaps.', 'rankkernel')
        );

        $this->renderCheckboxRow(
            'include_featured_image',
            __('Include featured image', 'rankkernel'),
            ! empty($all['include_featured_image']),
            __('Look up the featured image for each post.', 'rankkernel')
        );

        $this->renderIdsRow(
            'exclude_posts',
            __('Exclude posts', 'rankkernel'),
            $all['exclude_posts'] ?? [],
            __('Comma separated post ids to leave out of sitemaps.', 'rankkernel')
        );

        $this->renderIdsRow(
            'exclude_terms',
            __('Exclude terms', 'rankkernel'),
            $all['exclude_terms'] ?? [],
            __('Comma separated term ids to leave out of sitemaps.', 'rankkernel')
        );

        $this->renderCheckboxRow(
            'include_empty_terms',
            __('Include empty terms', 'rankkernel'),
            ! empty($all['include_empty_terms']),
            __('List terms that have no published posts.', 'rankkernel')
        );

        echo '</tbody></table>';
    }

    /**
     * Render the post types section.
     *
     * @param array<string, mixed> $all Merged settings.
     */
    private function renderPostTypes( array $all ): void {
        echo '<h2>' . esc_html__('Post Types', 'rankkernel') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        foreach ($this->publicPostTypes() as $slug => $label) {
            $key     = 'pt_' . $slug . '_sitemap';
            $enabled = $all[ $key ] ?? true;
            $url     = home_url('/' . $slug . '-sitemap.xml');

            echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
            echo '<label>';
            echo '<input type="checkbox" name="' . esc_attr($key) . '" value="1" '
                . checked((bool) $enabled, true, false) . ' /> ';
            echo esc_html__('Enable sitemap', 'rankkernel');
            echo '</label>';
            echo '<p class="description">' . esc_url($url) . '</p>';
            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render the taxonomies section.
     *
     * @param array<string, mixed> $all Merged settings.
     */
    private function renderTaxonomies( array $all ): void {
        echo '<h2>' . esc_html__('Taxonomies', 'rankkernel') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        foreach ($this->publicTaxonomies() as $slug => $label) {
            $key     = 'tax_' . $slug . '_sitemap';
            $enabled = $all[ $key ] ?? true;
            $url     = home_url('/' . $slug . '-sitemap.xml');

            echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
            echo '<label>';
            echo '<input type="checkbox" name="' . esc_attr($key) . '" value="1" '
                . checked((bool) $enabled, true, false) . ' /> ';
            echo esc_html__('Enable sitemap', 'rankkernel');
            echo '</label>';
            echo '<p class="description">' . esc_url($url) . '</p>';
            echo '</td></tr>';
        }

        echo '<tr><td colspan="2"><p class="description">';
        echo esc_html__(
            'Empty terms are listed only when the general include empty terms setting is on.',
            'rankkernel'
        );
        echo '</p></td></tr>';

        echo '</tbody></table>';
    }

    /**
     * Render the authors section.
     *
     * @param array<string, mixed> $all Merged settings.
     */
    private function renderAuthors( array $all ): void {
        echo '<h2>' . esc_html__('Authors', 'rankkernel') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        $this->renderCheckboxRow(
            'authors_sitemap',
            __('Authors sitemap', 'rankkernel'),
            ! empty($all['authors_sitemap']),
            __('List authors in the sitemap index.', 'rankkernel')
        );

        $this->renderCheckboxRow(
            'authors_include_empty',
            __('Include authors without posts', 'rankkernel'),
            ! empty($all['authors_include_empty']),
            __('List every user, not just authors with published posts.', 'rankkernel')
        );

        echo '<tr><th scope="row">' . esc_html__('Exclude roles', 'rankkernel') . '</th><td>';

        $excludedRoles = $all['authors_exclude_roles'] ?? [];
        if (! is_array($excludedRoles)) {
            $excludedRoles = [];
        }

        $roles = function_exists('get_editable_roles') ? get_editable_roles() : [];

        if (! is_array($roles) || [] === $roles) {
            echo '<p class="description">';
            echo esc_html__('No editable roles found.', 'rankkernel');
            echo '</p>';
        } else {
            foreach ($roles as $slug => $details) {
                if (! is_string($slug) || '' === $slug) {
                    continue;
                }

                $name = $slug;

                if (is_array($details) && isset($details['name']) && is_string($details['name'])) {
                    $name = $details['name'];
                }

                echo '<label>';
                echo '<input type="checkbox" name="authors_exclude_roles[]" value="'
                    . esc_attr($slug) . '" '
                    . checked(in_array($slug, $excludedRoles, true), true, false) . ' /> ';
                echo esc_html($name);
                echo '</label><br />';
            }
        }

        echo '</td></tr>';

        $this->renderIdsRow(
            'authors_exclude_users',
            __('Exclude users', 'rankkernel'),
            $all['authors_exclude_users'] ?? [],
            __('Comma separated user ids to leave out of the authors sitemap.', 'rankkernel')
        );

        echo '</tbody></table>';
    }

    /**
     * Render a checkbox row.
     *
     * @param string $name    Field name.
     * @param string $title   Row title.
     * @param bool   $checked Whether checked.
     * @param string $hint    Description text.
     */
    private function renderCheckboxRow( string $name, string $title, bool $checked, string $hint ): void {
        echo '<tr><th scope="row">' . esc_html($title) . '</th><td>';
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1" '
            . checked($checked, true, false) . ' /> ';
        echo esc_html($hint);
        echo '</label>';
        echo '</td></tr>';
    }

    /**
     * Render a comma ids textarea row.
     *
     * @param string $name  Field name.
     * @param string $title Row title.
     * @param mixed  $value Stored ids.
     * @param string $hint  Description text.
     */
    private function renderIdsRow( string $name, string $title, mixed $value, string $hint ): void {
        if (! is_array($value)) {
            $value = [];
        }

        $ids = [];

        foreach ($value as $id) {
            $int = (int) $id;

            if ($int > 0) {
                $ids[] = (string) $int;
            }
        }

        echo '<tr><th scope="row"><label for="rk-' . esc_attr($name) . '">';
        echo esc_html($title);
        echo '</label></th><td>';
        echo '<textarea id="rk-' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="2" cols="40">';
        echo esc_textarea(implode(',', $ids));
        echo '</textarea>';
        echo '<p class="description">' . esc_html($hint) . '</p>';
        echo '</td></tr>';
    }
}
