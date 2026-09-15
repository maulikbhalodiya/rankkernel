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
	 * Render the page.
	 */
	public function render(): void {
		$this->renderNotices();
		$this->renderForm();
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

	/**
	 * Render admin notices (success on settings-updated).
	 */
	private function renderNotices(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only flag, compared strictly against a literal, never stored or output.
		if ( isset( $_GET['settings-updated'] ) && '1' === (string) $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Settings saved.', 'rankkernel' );
			echo '</p></div>';
		}
	}

	/**
	 * Render the settings form.
	 */
	private function renderForm(): void {
		$allSettings = $this->store->all();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'RankKernel Settings', 'rankkernel' ) . '</h1>';

		echo '<form method="post" action="">';

		wp_nonce_field( 'rankkernel_settings' );

		// === Modules section.
		echo '<h2>' . esc_html__( 'Modules', 'rankkernel' ) . '</h2>';
		echo '<p class="description">';
		echo esc_html__(
			'Visible trail and breadcrumb schema share one trail. Place it with the block, shortcode, or template tag.',
			'rankkernel'
		);
		echo '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( ModuleRegistry::all() as $id => $label ) {
			$idStr   = (string) $id;
			$checked = $this->enableMap->isEnabled( $idStr );
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
			echo '<label>';
			echo '<input type="checkbox" name="rankkernel_modules[]" value="'
				. esc_attr( $idStr ) . '" ' . checked( $checked, true, false ) . ' /> ';
			echo esc_html( $label );
			echo '</label>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		// === Title & description templates.
		echo '<h2>' . esc_html__( 'Title &amp; Description Templates', 'rankkernel' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="rk-title-template">'
			. esc_html__( 'Title template', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="text" id="rk-title-template" name="title_template" value="'
			. esc_attr( (string) ( $allSettings['title_template'] ?? '' ) )
			. '" class="regular-text" />';
		echo '<p class="description">';
		echo esc_html__(
			'Available tokens: %%title%%, %%sitename%%, %%sep%%, %%excerpt%%, %%date%%, %%author%%, %%category%%, %%page%%, %%currentdate%%',
			'rankkernel'
		);
		echo '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="rk-desc-template">'
			. esc_html__( 'Description template', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="text" id="rk-desc-template" name="description_template" value="'
			. esc_attr( (string) ( $allSettings['description_template'] ?? '' ) )
			. '" class="regular-text" />';
		echo '<p class="description">';
		echo esc_html__(
			'Available tokens: %%title%%, %%sitename%%, %%sep%%, %%excerpt%%, %%date%%, %%author%%, %%category%%, %%page%%, %%currentdate%%',
			'rankkernel'
		);
		echo '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="rk-separator">'
			. esc_html__( 'Separator', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="text" id="rk-separator" name="separator" value="'
			. esc_attr( (string) ( $allSettings['separator'] ?? '' ) )
			. '" class="regular-text" maxlength="10" />';
		echo '</td></tr>';

		echo '</tbody></table>';

		// === Webmaster verification.
		echo '<h2>' . esc_html__( 'Webmaster Verification', 'rankkernel' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$webmasters = [
			'webmaster_google'    => __( 'Google', 'rankkernel' ),
			'webmaster_bing'      => __( 'Bing', 'rankkernel' ),
			'webmaster_yandex'    => __( 'Yandex', 'rankkernel' ),
			'webmaster_pinterest' => __( 'Pinterest', 'rankkernel' ),
			'webmaster_baidu'     => __( 'Baidu', 'rankkernel' ),
		];

		foreach ( $webmasters as $key => $label ) {
			$fieldId = 'rk-' . str_replace( '_', '-', $key );
			echo '<tr><th scope="row"><label for="' . esc_attr( $fieldId ) . '">'
				. esc_html( $label ) . '</label></th><td>';
			echo '<input type="text" id="' . esc_attr( $fieldId ) . '" name="'
				. esc_attr( $key ) . '" value="'
				. esc_attr( (string) ( $allSettings[ $key ] ?? '' ) )
				. '" class="regular-text" />';
			echo '</td></tr>';
		}

		echo '</tbody></table>';

		$this->renderBreadcrumbs();

		// === Uninstall section.
		echo '<h2>' . esc_html__( 'Uninstall', 'rankkernel' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Data removal', 'rankkernel' ) . '</th><td>';
		echo '<label>';
		$purgeChecked = ! empty( $allSettings['purge_on_uninstall'] );
		echo '<input type="checkbox" name="purge_on_uninstall" value="1" '
			. checked( $purgeChecked, true, false ) . ' /> ';
		echo esc_html__(
			'Delete all RankKernel data (options, metadata) when the plugin is deleted.',
			'rankkernel'
		);
		echo '</label>';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Save Settings', 'rankkernel' ), 'primary', 'rankkernel_save' );

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the breadcrumbs section.
	 *
	 * Native form markup matching the rest of the page, grouped under
	 * sub headings with short descriptions. Field names carry an
	 * rk_breadcrumbs_ prefix; the save handler maps them onto the
	 * module settings keys.
	 */
	private function renderBreadcrumbs(): void {
		$all = $this->breadcrumbsSettings()->all();

		$separator = isset( $all['separator'] ) ? (string) $all['separator'] : '/';

		echo '<h2>' . esc_html__( 'Breadcrumbs', 'rankkernel' ) . '</h2>';
		echo '<p class="description">';
		echo esc_html__(
			'Visible trail and breadcrumb schema share one trail. Place it with the block, shortcode, or template tag. Disabling the module in the module list disables breadcrumb integration.',
			'rankkernel'
		);
		echo '</p>';

		echo '<p class="description">';
		echo esc_html__( 'Theme template:', 'rankkernel' ) . ' ';
		echo '<code>' . esc_html( "if ( function_exists( 'rankkernel_breadcrumbs' ) ) { rankkernel_breadcrumbs(); }" ) . '</code>';
		echo '<br />';
		echo esc_html__( 'Shortcode:', 'rankkernel' ) . ' ';
		echo '<code>' . esc_html( '[rankkernel_breadcrumbs]' ) . '</code>';
		echo '</p>';

		echo '<h3>' . esc_html__( 'Appearance', 'rankkernel' ) . '</h3>';
		echo '<p class="description">';
		echo esc_html__( 'How the trail looks.', 'rankkernel' );
		echo '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->renderSeparatorChooser( $separator );

		echo '<tr><th scope="row"><label for="rk-breadcrumbs-home-label">'
			. esc_html__( 'Home label', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="text" id="rk-breadcrumbs-home-label" name="rk_breadcrumbs_home_label" value="'
			. esc_attr( (string) ( $all['home_label'] ?? 'Home' ) )
			. '" class="regular-text" />';
		echo '</td></tr>';

		$this->renderBreadcrumbsCheckbox(
			'rk_breadcrumbs_show_home',
			__( 'Show home link', 'rankkernel' ),
			! empty( $all['show_home'] ),
			__( 'Start the trail with a link to the homepage.', 'rankkernel' )
		);

		$this->renderBreadcrumbsCheckbox(
			'rk_breadcrumbs_show_current',
			__( 'Show current page', 'rankkernel' ),
			! empty( $all['show_current'] ),
			__( 'End the trail with the current page title.', 'rankkernel' )
		);

		$this->renderBreadcrumbsCheckbox(
			'rk_breadcrumbs_hide_on_front_page',
			__( 'Hide on front page', 'rankkernel' ),
			! empty( $all['hide_on_front_page'] ),
			__( 'Show no trail on the static front page.', 'rankkernel' )
		);

		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Trail behavior', 'rankkernel' ) . '</h3>';
		echo '<p class="description">';
		echo esc_html__( 'Which crumbs are included in the trail.', 'rankkernel' );
		echo '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->renderBreadcrumbsCheckbox(
			'rk_breadcrumbs_show_blog_page',
			__( 'Show blog page', 'rankkernel' ),
			! empty( $all['show_blog_page'] ),
			__( 'Include the posts page in post trails when one exists.', 'rankkernel' )
		);

		$this->renderBreadcrumbsCheckbox(
			'rk_breadcrumbs_show_ancestors',
			__( 'Show term ancestors', 'rankkernel' ),
			! empty( $all['show_ancestors'] ),
			__( 'Include parent terms above the current term.', 'rankkernel' )
		);

		echo '</tbody></table>';

		$this->renderTaxonomyPreferences( $all );

		echo '<p class="description">';
		echo esc_html__(
			'Archive, search, and 404 labels follow the trail builder defaults. Custom formats are not configurable in this version.',
			'rankkernel'
		);
		echo '</p>';
	}

	/**
	 * Render the separator chooser as native radio inputs.
	 *
	 * Presets post their own value; the Custom radio reveals a text
	 * input for any other separator. Both stay usable without
	 * JavaScript; the script only hides the custom input until Custom
	 * is selected. A stored non preset value preselects Custom and
	 * prefills its input, so existing settings survive unchanged.
	 *
	 * @param string $separator Stored separator.
	 */
	private function renderSeparatorChooser( string $separator ): void {
		$isCustom = ! BreadcrumbsSettings::isSeparatorPreset( $separator );

		echo '<tr><th scope="row">' . esc_html__( 'Separator', 'rankkernel' ) . '</th><td>';
		echo '<fieldset>';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Separator', 'rankkernel' ) . '</legend>';

		foreach ( BreadcrumbsSettings::separatorPresets() as $index => $preset ) {
			$radioId = 'rk-breadcrumbs-separator-choice-' . $index;
			$checked = ! $isCustom && $separator === $preset;

			echo '<label for="' . esc_attr( $radioId ) . '">';
			echo '<input type="radio" id="' . esc_attr( $radioId ) . '" name="rk_breadcrumbs_separator_choice" value="'
				. esc_attr( $preset ) . '" ' . checked( $checked, true, false ) . ' /> ';
			echo '<span>' . esc_html( $preset ) . '</span>';
			echo '</label><br />';
		}

		echo '<label for="rk-breadcrumbs-separator-choice-custom">';
		echo '<input type="radio" id="rk-breadcrumbs-separator-choice-custom" name="rk_breadcrumbs_separator_choice" value="custom" '
			. checked( $isCustom, true, false ) . ' /> ';
		echo esc_html__( 'Custom', 'rankkernel' );
		echo '</label>';

		echo '<div id="rk-breadcrumbs-separator-custom-wrap">';
		echo '<label for="rk-breadcrumbs-separator-custom">' . esc_html__( 'Custom separator', 'rankkernel' ) . '</label> ';
		echo '<input type="text" id="rk-breadcrumbs-separator-custom" name="rk_breadcrumbs_separator_custom" value="'
			. esc_attr( $isCustom ? $separator : '' )
			. '" class="small-text" maxlength="10" />';
		echo '</div>';

		echo '</fieldset>';
		echo '<p class="description">';
		echo esc_html__( 'Character shown between crumbs.', 'rankkernel' );
		echo '</p>';
		echo '</td></tr>';
	}

	/**
	 * Render taxonomy preferences inside a native disclosure.
	 *
	 * An active select renders only for post types with two or more
	 * usable public taxonomies. A post type with exactly one usable
	 * public taxonomy shows informational text; a post type with none
	 * shows nothing. Stored values for hidden controls are preserved
	 * by the save handler, never deleted.
	 *
	 * @param array<string, mixed> $all Merged breadcrumb settings.
	 */
	private function renderTaxonomyPreferences( array $all ): void {
		echo '<details id="rk-breadcrumbs-taxonomy-preferences">';
		echo '<summary>' . esc_html__( 'Taxonomy preferences', 'rankkernel' ) . '</summary>';
		echo '<p class="description">';
		echo esc_html__(
			'Chooses which taxonomy supplies the term branch when a post type has several.',
			'rankkernel'
		);
		echo '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( $this->breadcrumbsPostTypes() as $slug => $label ) {
			$taxes = $this->taxonomiesForType( $slug );

			if ( [] === $taxes ) {
				continue;
			}

			if ( 1 === count( $taxes ) ) {
				$onlyLabel = (string) reset( $taxes );

				echo '<tr><th scope="row">';
				// translators: %s: post type label.
				echo esc_html( sprintf( __( 'Primary taxonomy (%s)', 'rankkernel' ), $label ) );
				echo '</th><td><p class="description">';
				// translators: %s: taxonomy label.
				echo esc_html( sprintf( __( 'Uses %s, the only public taxonomy available.', 'rankkernel' ), $onlyLabel ) );
				echo '</p></td></tr>';

				continue;
			}

			$key     = 'primary_taxonomy_' . $slug;
			$field   = 'rk_breadcrumbs_primary_taxonomy_' . $slug;
			$fieldId = 'rk-breadcrumbs-primary-taxonomy-' . $slug;
			$current = isset( $all[ $key ] ) ? (string) $all[ $key ] : '';

			echo '<tr><th scope="row"><label for="' . esc_attr( $fieldId ) . '">';
			// translators: %s: post type label.
			echo esc_html( sprintf( __( 'Primary taxonomy (%s)', 'rankkernel' ), $label ) );
			echo '</label></th><td>';
			echo '<select id="' . esc_attr( $fieldId ) . '" name="' . esc_attr( $field ) . '">';
			echo '<option value=""' . ( '' === $current ? ' selected="selected"' : '' ) . '>';
			echo esc_html__( 'Default (first taxonomy with terms)', 'rankkernel' );
			echo '</option>';

			foreach ( $taxes as $taxSlug => $taxLabel ) {
				echo '<option value="' . esc_attr( $taxSlug ) . '"'
					. ( $current === $taxSlug ? ' selected="selected"' : '' ) . '>';
				echo esc_html( $taxLabel );
				echo '</option>';
			}

			echo '</select>';
			echo '<p class="description">';
			echo esc_html__( 'Which taxonomy supplies the term branch on single views.', 'rankkernel' );
			echo '</p>';
			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '</details>';
	}

	/**
	 * Render one breadcrumbs checkbox row.
	 *
	 * @param string $name    Field name.
	 * @param string $title   Row title.
	 * @param bool   $checked Whether checked.
	 * @param string $hint    Description text.
	 */
	private function renderBreadcrumbsCheckbox( string $name, string $title, bool $checked, string $hint ): void {
		echo '<tr><th scope="row">' . esc_html( $title ) . '</th><td>';
		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1" '
			. checked( $checked, true, false ) . ' /> ';
		echo esc_html( $hint );
		echo '</label>';
		echo '</td></tr>';
	}
}
