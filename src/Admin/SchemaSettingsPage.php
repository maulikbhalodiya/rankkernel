<?php
/**
 * Schema settings page, render plus save handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;

/**
 * Renders the RankKernel schema settings page and handles saves.
 *
 * Single form covering identity, per post type defaults, and
 * validation tools. Saves merge over stored values through the
 * shared SettingsStore whitelist.
 */
final class SchemaSettingsPage {
    /**
     * Constructor.
     *
     * @param SettingsStore $store Settings store.
     */
    public function __construct(
        private readonly SettingsStore $store
    ) {
    }

    /**
     * Handle a POST save on the load hook, before any output is sent.
     *
     * Runs on load-{page}, so wp_safe_redirect can still send headers.
     */
    public function maybeHandleSave(): void {
        if ('POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset($_POST['rankkernel_schema_save'])) {
            $this->handleSave();
        }
    }

    /**
     * Render the page.
     */
    public function render(): void {
        $this->renderNotices();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Schema Settings', 'rankkernel') . '</h1>';

        $this->renderForm();

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

        $verified = check_admin_referer('rankkernel_schema_settings');
        if (false === $verified) {
            wp_die(
                esc_html__('Security check failed. Please refresh and try again.', 'rankkernel'),
                '',
                [ 'response' => 403 ]
            );
        }

        $this->store->set($this->collect());

        $redirect = admin_url('admin.php?page=rankkernel-schema&settings-updated=1');
        wp_safe_redirect($redirect);

        if (! defined('RANKKERNEL_TESTING')) {
            exit;
        }
    }

    /**
     * Collect the form fields from POST.
     *
     * Checkbox absent means false. The per post type default selects
     * post raw values, the store drops unknown type names.
     *
     * @return array<string, mixed>
     */
    private function collect(): array {
        $partial = [];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
        if (isset($_POST['site_represents'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in the store.
            $partial['site_represents'] = is_string($_POST['site_represents']) ? wp_unslash($_POST['site_represents']) : '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
        if (isset($_POST['org_name'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in the store.
            $partial['org_name'] = is_string($_POST['org_name']) ? wp_unslash($_POST['org_name']) : '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
        if (isset($_POST['org_logo'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in the store.
            $partial['org_logo'] = is_string($_POST['org_logo']) ? wp_unslash($_POST['org_logo']) : '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
        if (isset($_POST['org_sameas'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in the store.
            $rawSameAs = is_string($_POST['org_sameas']) ? wp_unslash($_POST['org_sameas']) : '';
            $lines     = preg_split('/\r\n|\r|\n/', $rawSameAs);

            $partial['org_sameas'] = is_array($lines) ? $lines : [];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
        $partial['website_search_action'] = isset($_POST['website_search_action']);

        foreach ($this->publicPostTypes() as $slug => $label) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
            if (isset($_POST[ 'schema_default_' . $slug ])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in the store.
                $rawDefault = wp_unslash($_POST[ 'schema_default_' . $slug ]);

                $partial[ 'schema_default_' . $slug ] = is_string($rawDefault) ? $rawDefault : '';
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
        $partial['schema_breadcrumbs'] = isset($_POST['schema_breadcrumbs']);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
        $partial['schema_author'] = isset($_POST['schema_author']);

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

        $out = [];

        foreach ($types as $slug => $item) {
            if (! is_string($slug) || '' === $slug || 'attachment' === $slug) {
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
     * Render the form with identity, defaults, and tools sections.
     */
    private function renderForm(): void {
        $all = $this->store->all();

        echo '<form method="post" action="">';

        wp_nonce_field('rankkernel_schema_settings');

        $this->renderIdentity($all);
        $this->renderDefaults($all);
        $this->renderTools();

        submit_button(__('Save Schema Settings', 'rankkernel'), 'primary', 'rankkernel_schema_save');

        echo '</form>';
    }

    /**
     * Render the identity section.
     *
     * @param array<string, mixed> $all Merged settings.
     */
    private function renderIdentity( array $all ): void {
        $represents = isset($all['site_represents']) && is_string($all['site_represents']) ? $all['site_represents'] : 'organization';
        $orgName    = isset($all['org_name']) ? (string) $all['org_name'] : '';
        $orgLogo    = isset($all['org_logo']) ? (string) $all['org_logo'] : '';

        $sameAs = $all['org_sameas'] ?? [];

        if (! is_array($sameAs)) {
            $sameAs = [];
        }

        $sameAsLines = [];

        foreach ($sameAs as $url) {
            $clean = trim((string) $url);

            if ('' !== $clean) {
                $sameAsLines[] = $clean;
            }
        }

        echo '<h2>' . esc_html__('Identity', 'rankkernel') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="rk-site-represents">';
        echo esc_html__('Site Represents', 'rankkernel');
        echo '</label></th><td>';
        echo '<select id="rk-site-represents" name="site_represents">';
        echo '<option value="organization"' . selected($represents, 'organization', false) . '>';
        echo esc_html__('Organization', 'rankkernel');
        echo '</option>';
        echo '<option value="person"' . selected($represents, 'person', false) . '>';
        echo esc_html__('Person', 'rankkernel');
        echo '</option>';
        echo '</select>';
        echo '<p class="description">';
        echo esc_html__('Whether the site identity represents an organization or a person.', 'rankkernel');
        echo '</p></td></tr>';

        echo '<tr><th scope="row"><label for="rk-org-name">';
        echo esc_html__('Organization Name', 'rankkernel');
        echo '</label></th><td>';
        echo '<input type="text" id="rk-org-name" name="org_name" value="'
            . esc_attr($orgName)
            . '" class="regular-text" />';
        echo '<p class="description">';
        echo esc_html__('Falls back to the site name when empty.', 'rankkernel');
        echo '</p></td></tr>';

        echo '<tr><th scope="row"><label for="rk-org-logo">';
        echo esc_html__('Organization Logo', 'rankkernel');
        echo '</label></th><td>';
        echo '<input type="url" id="rk-org-logo" name="org_logo" value="'
            . esc_attr($orgLogo)
            . '" class="regular-text" />';
        echo '<p class="description">';
        echo esc_html__('Logo URL used on the organization node.', 'rankkernel');
        echo '</p></td></tr>';

        echo '<tr><th scope="row"><label for="rk-org-sameas">';
        echo esc_html__('Same As', 'rankkernel');
        echo '</label></th><td>';
        echo '<textarea id="rk-org-sameas" name="org_sameas" rows="4" cols="50">';
        echo esc_textarea(implode("\n", $sameAsLines));
        echo '</textarea>';
        echo '<p class="description">';
        echo esc_html__('One profile URL per line, for example social profiles.', 'rankkernel');
        echo '</p></td></tr>';

        $this->renderCheckboxRow(
            'website_search_action',
            __('Search Action', 'rankkernel'),
            ! empty($all['website_search_action']),
            __('Add a search action to the WebSite node.', 'rankkernel')
        );

        echo '</tbody></table>';
    }

    /**
     * Render the defaults section.
     *
     * @param array<string, mixed> $all Merged settings.
     */
    private function renderDefaults( array $all ): void {
        echo '<h2>' . esc_html__('Defaults', 'rankkernel') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        foreach ($this->publicPostTypes() as $slug => $label) {
            $key     = 'schema_default_' . $slug;
            $current = isset($all[ $key ]) && is_string($all[ $key ]) ? $all[ $key ] : '';

            echo '<tr><th scope="row"><label for="rk-' . esc_attr($key) . '">';
            echo esc_html($label);
            echo '</label></th><td>';
            echo '<select id="rk-' . esc_attr($key) . '" name="' . esc_attr($key) . '">';
            echo '<option value=""' . selected($current, '', false) . '>';
            echo esc_html__('Automatic', 'rankkernel');
            echo '</option>';

            foreach (SchemaTypes::SUPPORTED as $type) {
                echo '<option value="' . esc_attr($type) . '"' . selected($current, $type, false) . '>';
                echo esc_html($type);
                echo '</option>';
            }

            echo '</select>';
            echo '<p class="description">';
            echo esc_html__('Default schema type for this post type. Automatic picks the type from the post type.', 'rankkernel');
            echo '</p></td></tr>';
        }

        $this->renderCheckboxRow(
            'schema_breadcrumbs',
            __('Breadcrumbs', 'rankkernel'),
            ! empty($all['schema_breadcrumbs']),
            __('Output the BreadcrumbList node.', 'rankkernel')
        );

        $this->renderCheckboxRow(
            'schema_author',
            __('Author', 'rankkernel'),
            ! empty($all['schema_author']),
            __('Output the author Person node.', 'rankkernel')
        );

        echo '</tbody></table>';
    }

    /**
     * Render the tools section with validation links.
     */
    private function renderTools(): void {
        $home = function_exists('home_url') ? (string) home_url('/') : '';

        $richResults = 'https://search.google.com/test/rich-results?url=' . rawurlencode($home);
        $validator   = 'https://validator.schema.org/';

        echo '<h2>' . esc_html__('Tools', 'rankkernel') . '</h2>';
        echo '<p>';
        echo '<a href="' . esc_url($richResults) . '" target="_blank" rel="noopener">';
        echo esc_html__('Rich Results Test', 'rankkernel');
        echo '</a>';
        echo ' | ';
        echo '<a href="' . esc_url($validator) . '" target="_blank" rel="noopener">';
        echo esc_html__('Schema Validator', 'rankkernel');
        echo '</a>';
        echo '</p>';
        echo '<p class="description">';
        echo esc_html__('The Rich Results Test opens with your home URL prefilled.', 'rankkernel');
        echo '</p>';
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
}
