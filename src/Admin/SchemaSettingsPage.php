<?php
/**
 * Schema settings page, controller plus view state preparation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;

/**
 * Handles the RankKernel schema settings screen.
 *
 * Owns capability checks, nonce verification, saving, validation, and view
 * state preparation. The HTML lives in src/Admin/Views/schema-settings.php.
 *
 * Single form covering identity, per post type defaults, and validation
 * tools. Saves merge over stored values through the shared SettingsStore
 * whitelist.
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to handleSave which verifies capability plus nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['rankkernel_schema_save'] ) ) {
			$this->handleSave();
		}
	}

	/**
	 * Enqueue the media picker script on the schema settings screen only.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( 'rankkernel_page_rankkernel-schema' !== $hookSuffix ) {
			return;
		}

		if ( ! function_exists( 'wp_enqueue_media' ) || ! function_exists( 'plugins_url' ) ) {
			return;
		}

		wp_enqueue_media();

		$src     = plugins_url( 'assets/js/schema-settings.js', (string) RANKKERNEL_FILE );
		$version = \RankKernel\Plugin::version();

		wp_register_script( 'rankkernel-schema-settings', $src, [ 'media-editor' ], $version, true );
		wp_enqueue_script( 'rankkernel-schema-settings' );
	}

	/**
	 * Prepare the view state and load the schema settings view.
	 */
	public function render(): void {
		$all = $this->store->all();

		// Read only display flag, compared strictly against a literal, never stored or output.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read only flag, compared strictly against a literal, never stored or output.
		$settingsUpdated = isset( $_GET['settings-updated'] ) && '1' === (string) $_GET['settings-updated'];

		$represents = 'organization';

		if ( isset( $all['site_represents'] ) && is_string( $all['site_represents'] ) ) {
			$represents = $all['site_represents'];
		}

		$orgName = isset( $all['org_name'] ) ? (string) $all['org_name'] : '';
		$orgLogo = isset( $all['org_logo'] ) ? (string) $all['org_logo'] : '';

		$sameAs = $all['org_sameas'] ?? [];

		if ( ! is_array( $sameAs ) ) {
			$sameAs = [];
		}

		$sameAsLines = [];

		foreach ( $sameAs as $url ) {
			$clean = trim( (string) $url );

			if ( '' !== $clean ) {
				$sameAsLines[] = $clean;
			}
		}

		$defaultRows = [];

		foreach ( $this->publicPostTypes() as $slug => $label ) {
			$key     = 'schema_default_' . $slug;
			$current = isset( $all[ $key ] ) && is_string( $all[ $key ] ) ? $all[ $key ] : '';

			$defaultRows[] = [
				'key'     => $key,
				'label'   => $label,
				'current' => $current,
			];
		}

		$schemaTypes = [];

		foreach ( SchemaTypes::SUPPORTED as $type ) {
			$schemaTypes[] = [
				'value' => $type,
				'label' => SchemaTypes::label( $type ),
			];
		}

		$home           = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$richResultsUrl = 'https://search.google.com/test/rich-results?url=' . rawurlencode( $home );
		$validatorUrl   = 'https://validator.schema.org/';

		$websiteSearchAction = ! empty( $all['website_search_action'] );
		$schemaBreadcrumbs   = ! empty( $all['schema_breadcrumbs'] );
		$schemaAuthor        = ! empty( $all['schema_author'] );

		require __DIR__ . '/Views/schema-settings.php';
	}

	/**
	 * Handle save, capability plus nonce, then persist and redirect.
	 */
	private function handleSave(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to manage RankKernel settings.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$verified = check_admin_referer( 'rankkernel_schema_settings' );
		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$this->store->set( $this->collect() );

		$redirect = admin_url( 'admin.php?page=rankkernel-schema&settings-updated=1' );
		wp_safe_redirect( $redirect );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
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

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
		if ( isset( $_POST['site_represents'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value sanitized in the store, unslashed here, sanitized or validated on the following statements.
			$rawRepresents = is_string( $_POST['site_represents'] ) ? wp_unslash( $_POST['site_represents'] ) : '';

			$partial['site_represents'] = $rawRepresents;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
		if ( isset( $_POST['org_name'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value sanitized in the store, unslashed here, sanitized or validated on the following statements.
			$partial['org_name'] = is_string( $_POST['org_name'] ) ? wp_unslash( $_POST['org_name'] ) : '';
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
		if ( isset( $_POST['org_logo'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value sanitized in the store, unslashed here, sanitized or validated on the following statements.
			$partial['org_logo'] = is_string( $_POST['org_logo'] ) ? wp_unslash( $_POST['org_logo'] ) : '';
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
		if ( isset( $_POST['org_sameas'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value sanitized in the store, unslashed here, sanitized or validated on the following statements.
			$rawSameAs = is_string( $_POST['org_sameas'] ) ? wp_unslash( $_POST['org_sameas'] ) : '';
			$lines     = preg_split( '/\r\n|\r|\n/', $rawSameAs );

			$partial['org_sameas'] = is_array( $lines ) ? $lines : [];
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
		$partial['website_search_action'] = isset( $_POST['website_search_action'] );

		foreach ( $this->publicPostTypes() as $slug => $label ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
			if ( isset( $_POST[ 'schema_default_' . $slug ] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value validated in the store, unslashed here, sanitized or validated on the following statements.
				$rawDefault = wp_unslash( $_POST[ 'schema_default_' . $slug ] );

				$partial[ 'schema_default_' . $slug ] = is_string( $rawDefault ) ? $rawDefault : '';
			}
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
		$partial['schema_breadcrumbs'] = isset( $_POST['schema_breadcrumbs'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collect runs.
		$partial['schema_author'] = isset( $_POST['schema_author'] );

		return $partial;
	}

	/**
	 * Public post types as slug to label, without attachments.
	 *
	 * @return array<string, string>
	 */
	private function publicPostTypes(): array {
		$types = get_post_types( [ 'public' => true ], 'objects' );

		if ( ! is_array( $types ) ) {
			return [];
		}

		return $this->slugsWithLabels( $types, [ 'attachment' ] );
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
}
