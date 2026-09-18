<?php
/**
 * Admin settings page, render + save handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Breadcrumbs\BreadcrumbsSettings;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleRegistry;
use RankKernel\Settings\SettingsStore;

/**
 * Renders the RankKernel settings page and handles saves.
 *
 * Owns capability checks, nonce verification, request handling, validation,
 * redirects and view state preparation. The HTML lives in
 * src/Admin/Views/settings.php.
 */
final class SettingsPage {
	/**
	 * Constructor.
	 *
	 * @param SettingsStore            $store       Settings store.
	 * @param ModuleEnableMap          $enableMap   Module enable map (single get_option).
	 * @param BreadcrumbsSettings|null $breadcrumbs Optional breadcrumbs settings (tests).
	 */
	public function __construct(
		private readonly SettingsStore $store,
		private readonly ModuleEnableMap $enableMap,
		private readonly ?BreadcrumbsSettings $breadcrumbs = null
	) {
	}

	/**
	 * Handle a POST save on the load hook, before any output is sent.
	 *
	 * Runs on load-{page}, so wp_safe_redirect can still send headers.
	 */
	public function maybeHandleSave(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to handleSave which verifies capability plus nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['rankkernel_save'] ) ) {
			$this->handleSave();
		}
	}

	/**
	 * Prepare the view state and load the settings view.
	 */
	public function render(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only flag, compared strictly against a literal, never stored or output.
		$settingsUpdated = isset( $_GET['settings-updated'] ) && '1' === (string) $_GET['settings-updated'];

		$allSettings = $this->store->all();

		$modules = [];

		foreach ( ModuleRegistry::all() as $moduleId => $moduleLabel ) {
			$moduleIdStr = (string) $moduleId;
			$modules[]   = [
				'id'      => $moduleIdStr,
				'label'   => (string) $moduleLabel,
				'enabled' => $this->enableMap->isEnabled( $moduleIdStr ),
			];
		}

		$titleTemplate       = (string) ( $allSettings['title_template'] ?? '' );
		$descriptionTemplate = (string) ( $allSettings['description_template'] ?? '' );
		$titleSeparator      = (string) ( $allSettings['separator'] ?? '' );

		$webmasterLabels = [
			'webmaster_google'    => __( 'Google', 'rankkernel' ),
			'webmaster_bing'      => __( 'Bing', 'rankkernel' ),
			'webmaster_yandex'    => __( 'Yandex', 'rankkernel' ),
			'webmaster_pinterest' => __( 'Pinterest', 'rankkernel' ),
			'webmaster_baidu'     => __( 'Baidu', 'rankkernel' ),
		];

		$webmasters = [];

		foreach ( $webmasterLabels as $webmasterKey => $webmasterLabel ) {
			$webmasters[] = [
				'fieldId' => 'rk-' . str_replace( '_', '-', $webmasterKey ),
				'key'     => $webmasterKey,
				'label'   => $webmasterLabel,
				'value'   => (string) ( $allSettings[ $webmasterKey ] ?? '' ),
			];
		}

		$purgeChecked = ! empty( $allSettings['purge_on_uninstall'] );

		$breadcrumbs         = $this->breadcrumbsSettings()->all();
		$breadcrumbSeparator = isset( $breadcrumbs['separator'] ) ? (string) $breadcrumbs['separator'] : '/';
		$homeLabel           = (string) ( $breadcrumbs['home_label'] ?? 'Home' );

		$appearanceToggles = [
			[
				'name'    => 'rk_breadcrumbs_show_home',
				'title'   => __( 'Show home link', 'rankkernel' ),
				'checked' => ! empty( $breadcrumbs['show_home'] ),
				'hint'    => __( 'Start the trail with a link to the homepage.', 'rankkernel' ),
			],
			[
				'name'    => 'rk_breadcrumbs_show_current',
				'title'   => __( 'Show current page', 'rankkernel' ),
				'checked' => ! empty( $breadcrumbs['show_current'] ),
				'hint'    => __( 'End the trail with the current page title.', 'rankkernel' ),
			],
			[
				'name'    => 'rk_breadcrumbs_hide_on_front_page',
				'title'   => __( 'Hide on front page', 'rankkernel' ),
				'checked' => ! empty( $breadcrumbs['hide_on_front_page'] ),
				'hint'    => __( 'Show no trail on the static front page.', 'rankkernel' ),
			],
		];

		$behaviorToggles = [
			[
				'name'    => 'rk_breadcrumbs_show_blog_page',
				'title'   => __( 'Show blog page', 'rankkernel' ),
				'checked' => ! empty( $breadcrumbs['show_blog_page'] ),
				'hint'    => __( 'Include the posts page in post trails when one exists.', 'rankkernel' ),
			],
			[
				'name'    => 'rk_breadcrumbs_show_ancestors',
				'title'   => __( 'Show term ancestors', 'rankkernel' ),
				'checked' => ! empty( $breadcrumbs['show_ancestors'] ),
				'hint'    => __( 'Include parent terms above the current term.', 'rankkernel' ),
			],
		];

		$isCustomSeparator = ! BreadcrumbsSettings::isSeparatorPreset( $breadcrumbSeparator );

		$separatorChoices = [];

		foreach ( BreadcrumbsSettings::separatorPresets() as $index => $preset ) {
			$separatorChoices[] = [
				'id'      => 'rk-breadcrumbs-separator-choice-' . $index,
				'value'   => $preset,
				'checked' => ! $isCustomSeparator && $breadcrumbSeparator === $preset,
			];
		}

		$taxonomyRows = [];

		foreach ( $this->breadcrumbsPostTypes() as $slug => $label ) {
			$taxes = $this->taxonomiesForType( $slug );

			if ( [] === $taxes ) {
				continue;
			}

			if ( 1 === count( $taxes ) ) {
				$onlyLabel = (string) reset( $taxes );

				$taxonomyRows[] = [
					'rowType' => 'info',
					// translators: %s: post type label.
					'title'   => sprintf( __( 'Primary taxonomy (%s)', 'rankkernel' ), $label ),
					// translators: %s: taxonomy label.
					'hint'    => sprintf( __( 'Uses %s, the only public taxonomy available.', 'rankkernel' ), $onlyLabel ),
				];

				continue;
			}

			$key     = 'primary_taxonomy_' . $slug;
			$field   = 'rk_breadcrumbs_primary_taxonomy_' . $slug;
			$fieldId = 'rk-breadcrumbs-primary-taxonomy-' . $slug;
			$current = isset( $breadcrumbs[ $key ] ) ? (string) $breadcrumbs[ $key ] : '';

			$taxonomyOptions = [];

			foreach ( $taxes as $taxSlug => $taxLabel ) {
				$taxonomyOptions[] = [
					'slug'  => $taxSlug,
					'label' => $taxLabel,
				];
			}

			$taxonomyRows[] = [
				'rowType' => 'select',
				// translators: %s: post type label.
				'title'   => sprintf( __( 'Primary taxonomy (%s)', 'rankkernel' ), $label ),
				'fieldId' => $fieldId,
				'field'   => $field,
				'current' => $current,
				'options' => $taxonomyOptions,
			];
		}

		$settingsSections = [
			[
				'id'    => 'general',
				'label' => __( 'General', 'rankkernel' ),
			],
			[
				'id'    => 'breadcrumbs',
				'label' => __( 'Breadcrumbs', 'rankkernel' ),
			],
			[
				'id'    => 'webmaster',
				'label' => __( 'Webmaster Tools', 'rankkernel' ),
			],
			[
				'id'    => 'modules',
				'label' => __( 'Modules', 'rankkernel' ),
			],
			[
				'id'    => 'advanced',
				'label' => __( 'Advanced', 'rankkernel' ),
			],
		];

		require __DIR__ . '/Views/settings.php';
	}

	/**
	 * Handle save, capability + nonce, then persist settings + modules.
	 */
	private function handleSave(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to manage RankKernel settings.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$verified = check_admin_referer( 'rankkernel_settings' );
		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		// Collect settings partial from POST, sanitize each value.
		$partial = [];

		$textKeys = [
			'title_template',
			'description_template',
			'separator',
			'webmaster_google',
			'webmaster_bing',
			'webmaster_yandex',
			'webmaster_pinterest',
			'webmaster_baidu',
		];

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified above.
		foreach ( $textKeys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
				$partial[ $key ] = sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) );
			}
		}

		// Checkbox semantics: absent from POST = false.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified.
		$partial['purge_on_uninstall'] = isset( $_POST['purge_on_uninstall'] );

		$this->store->set( $partial );

		$this->saveBreadcrumbs();

		// Modules: validate ids against registry; save enabled-id list.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce already verified, unslashed here, sanitized or validated on the following statements.
		$rawModules = isset( $_POST['rankkernel_modules'] ) ? wp_unslash( $_POST['rankkernel_modules'] ) : [];
		if ( ! is_array( $rawModules ) ) {
			$rawModules = [];
		}

		$enabled = [];
		foreach ( $rawModules as $rawId ) {
			$id = sanitize_text_field( (string) $rawId );
			if ( '' !== $id && ModuleRegistry::has( $id ) ) {
				$enabled[] = $id;
			}
		}

		// De-duplicate, preserve registry order? Keep as filtered list.
		$enabled = array_values( array_unique( $enabled ) );

		update_option( 'rankkernel_modules', $enabled );

		// Rules of rewrite-based modules (sitemaps) must reach the cached
		// rules array when the enable list changes.
		flush_rewrite_rules( false );

		$redirect = admin_url( 'admin.php?page=rankkernel&settings-updated=1' );
		wp_safe_redirect( $redirect );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Breadcrumb settings store, injected or fresh.
	 *
	 * @return BreadcrumbsSettings The result.
	 */
	private function breadcrumbsSettings(): BreadcrumbsSettings {
		return $this->breadcrumbs ?? new BreadcrumbsSettings();
	}

	/**
	 * Enqueue the breadcrumbs separator script on this screen only.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( 'toplevel_page_rankkernel' !== $hookSuffix ) {
			return;
		}

		if ( ! function_exists( 'plugins_url' ) ) {
			return;
		}

		$src     = plugins_url( 'assets/js/breadcrumbs-admin.js', (string) RANKKERNEL_FILE );
		$version = \RankKernel\Plugin::version();

		wp_register_script( 'rankkernel-breadcrumbs-admin', $src, [], $version, true );
		wp_enqueue_script( 'rankkernel-breadcrumbs-admin' );

		wp_register_style(
			'rankkernel-settings-admin',
			plugins_url( 'assets/css/settings-admin.css', (string) RANKKERNEL_FILE ),
			[],
			$version
		);
		wp_enqueue_style( 'rankkernel-settings-admin' );
	}

	/**
	 * Save the breadcrumbs section through the module settings class.
	 *
	 * Field names carry an rk_breadcrumbs_ prefix so they never collide
	 * with the title template separator key. Values sanitize through
	 * BreadcrumbsSettings::set, which whitelists keys and validates the
	 * per post type taxonomy mappings.
	 *
	 * Exactly one canonical separator setting is stored. The chooser
	 * posts a radio choice plus a custom text field; the save computes
	 * the separator from both and falls back to the legacy single text
	 * field when the chooser is absent. Taxonomy mappings save only for
	 * post types that render a select (two or more usable public
	 * taxonomies); hidden controls post nothing, so their stored values
	 * are preserved and never deleted.
	 */
	private function saveBreadcrumbs(): void {
		$partial = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handleSave.
		if ( isset( $_POST['rk_breadcrumbs_separator_choice'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified, sanitized below.
			$choice = (string) wp_unslash( $_POST['rk_breadcrumbs_separator_choice'] );

			if ( 'custom' === $choice ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified, sanitized below.
				$custom = isset( $_POST['rk_breadcrumbs_separator_custom'] ) ? (string) wp_unslash( $_POST['rk_breadcrumbs_separator_custom'] ) : '';

				$partial['separator'] = sanitize_text_field( $custom );
			} else {
				$partial['separator'] = sanitize_text_field( $choice );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handleSave.
		} elseif ( isset( $_POST['rk_breadcrumbs_separator'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified, sanitized below.
			$partial['separator'] = sanitize_text_field( (string) wp_unslash( $_POST['rk_breadcrumbs_separator'] ) );
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handleSave.
		if ( isset( $_POST['rk_breadcrumbs_home_label'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified, sanitized below.
			$partial['home_label'] = sanitize_text_field( (string) wp_unslash( $_POST['rk_breadcrumbs_home_label'] ) );
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified, checkbox semantics: absent from POST means false.
		$partial['show_home'] = isset( $_POST['rk_breadcrumbs_show_home'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified, checkbox semantics: absent from POST means false.
		$partial['show_current'] = isset( $_POST['rk_breadcrumbs_show_current'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified, checkbox semantics: absent from POST means false.
		$partial['hide_on_front_page'] = isset( $_POST['rk_breadcrumbs_hide_on_front_page'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified, checkbox semantics: absent from POST means false.
		$partial['show_blog_page'] = isset( $_POST['rk_breadcrumbs_show_blog_page'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified, checkbox semantics: absent from POST means false.
		$partial['show_ancestors'] = isset( $_POST['rk_breadcrumbs_show_ancestors'] );

		foreach ( $this->breadcrumbsPostTypes() as $slug => $label ) {
			if ( count( $this->taxonomiesForType( $slug ) ) < 2 ) {
				continue;
			}

			$field = 'rk_breadcrumbs_primary_taxonomy_' . $slug;
			$key   = 'primary_taxonomy_' . $slug;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handleSave.
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce already verified, unslashed here, validated by the settings class against registered taxonomies.
			$raw = wp_unslash( $_POST[ $field ] );

			$partial[ $key ] = is_string( $raw ) ? $raw : '';
		}

		$this->breadcrumbsSettings()->set( $partial );
	}

	/**
	 * Public post types as slug to label, without attachments.
	 *
	 * @return array<string, string>
	 */
	private function breadcrumbsPostTypes(): array {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [];
		}

		try {
			$types = get_post_types( [ 'public' => true ], 'objects' );
		} catch ( \Throwable $e ) {
			// Test doubles can leak this name into later suites without a backend, so a failure here must never break the save. Production WP always provides it.
			unset( $e );

			return [];
		}

		if ( ! is_array( $types ) ) {
			return [];
		}

		/**
		 * Core type map.
		 *
		 * @var array<mixed, mixed> $map
		 */
		$map = $types;

		return $this->slugsWithLabels( $map, [ 'attachment' ] );
	}

	/**
	 * Reduce an objects or names map to slug to label pairs.
	 *
	 * Slugs outside the settings key pattern are skipped, their keys
	 * could never be stored.
	 *
	 * @param array<mixed, mixed> $map  Type map from core.
	 * @param string[]            $skip Slugs to drop.
	 * @return array<string, string>
	 */
	private function slugsWithLabels( array $map, array $skip ): array {
		$out = [];

		foreach ( $map as $slug => $item ) {
			if ( ! is_string( $slug ) || '' === $slug || in_array( $slug, $skip, true ) ) {
				continue;
			}

			if ( 1 !== preg_match( '/^[a-z0-9_]+$/', $slug ) ) {
				continue;
			}

			$label = $slug;

			if ( is_object( $item ) && isset( $item->label ) && is_string( $item->label ) && '' !== $item->label ) {
				$label = $item->label;
			} elseif ( is_string( $item ) && '' !== $item ) {
				$label = $item;
			}

			$out[ $slug ] = $label;
		}

		return $out;
	}

	/**
	 * Public taxonomy slugs registered for a post type.
	 *
	 * @param string $postType Post type slug.
	 * @return array<string, string> Slug to label pairs.
	 */
	private function taxonomiesForType( string $postType ): array {
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			return [];
		}

		try {
			$taxes = get_object_taxonomies( $postType, 'objects' );
		} catch ( \Throwable $e ) {
			// Test doubles can leak this name into later suites without a backend, so a failure here must never break the save. Production WP always provides it.
			unset( $e );

			return [];
		}

		if ( ! is_array( $taxes ) ) {
			return [];
		}

		/**
		 * Core taxonomy map.
		 *
		 * @var array<mixed, mixed> $map
		 */
		$map = $taxes;

		$out = [];

		foreach ( $map as $slug => $tax ) {
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}

			$label  = $slug;
			$public = false;

			if ( is_object( $tax ) ) {
				if ( isset( $tax->label ) && is_string( $tax->label ) && '' !== $tax->label ) {
					$label = $tax->label;
				}

				$public = ! empty( $tax->public );
			} elseif ( is_string( $tax ) && '' !== $tax ) {
				$label  = $tax;
				$public = true;
			}

			if ( $public ) {
				$out[ $slug ] = $label;
			}
		}

		return $out;
	}
}
