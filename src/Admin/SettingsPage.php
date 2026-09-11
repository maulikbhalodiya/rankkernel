<?php
/**
 * Admin settings page, render + save handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

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
	 * @param SettingsStore   $store     Settings store.
	 * @param ModuleEnableMap $enableMap Module enable map (single get_option).
	 */
	public function __construct(
		private readonly SettingsStore $store,
		private readonly ModuleEnableMap $enableMap
	) {
	}

	/**
	 * Handle a POST save on the load hook, before any output is sent.
	 *
	 * Runs on load-{page}, so wp_safe_redirect can still send headers.
	 */
	public function maybeHandleSave(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- delegates to handleSave which verifies capability plus nonce.
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

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
		foreach ( $textKeys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
				$partial[ $key ] = sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) );
			}
		}

		// Checkbox semantics: absent from POST = false.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
		$partial['purge_on_uninstall'] = isset( $_POST['purge_on_uninstall'] );

		$this->store->set( $partial );

		// Modules: validate ids against registry; save enabled-id list.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified.
		$rawModules = $_POST['rankkernel_modules'] ?? [];
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
	 * Render admin notices (success on settings-updated).
	 */
	private function renderNotices(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag.
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
			'Enable or disable optional modules. Changes take effect on the next request.',
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
}
