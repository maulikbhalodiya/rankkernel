<?php
/**
 * Sitemap settings page, render plus save handler.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Sitemaps\Router;
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to handleSave which verifies capability plus nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['rankkernel_sitemap_save'] ) ) {
			$this->handleSave();
		}
	}

	/**
	 * Current tab from the query string, general on unknown input.
	 *
	 * @return string The result.
	 */
	public function currentTab(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only display flag, unslashed here, sanitized or validated on the following statements.
		$raw = $_GET['tab'] ?? '';

		if ( ! is_string( $raw ) ) {
			return 'general';
		}

		$tab = sanitize_key( wp_unslash( $raw ) );

		if ( ! in_array( $tab, self::TABS, true ) ) {
			return 'general';
		}

		return $tab;
	}

	/**
	 * Prepare the view state and load the sitemap settings view.
	 */
	public function render(): void {
		$all = $this->settings->all();

		// Read only display flag, compared strictly against a literal, never stored or output.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only flag, compared strictly against a literal, never stored or output.
		$settingsUpdated = isset( $_GET['settings-updated'] ) && '1' === (string) $_GET['settings-updated'];

		$tab = $this->currentTab();

		$tabLabels = [
			'general'    => __( 'General', 'rankkernel' ),
			'post-types' => __( 'Post Types', 'rankkernel' ),
			'taxonomies' => __( 'Taxonomies', 'rankkernel' ),
			'authors'    => __( 'Authors', 'rankkernel' ),
		];

		$tabItems = [];

		foreach ( $tabLabels as $tabId => $tabLabel ) {
			$tabItems[] = [
				'url'   => admin_url( 'admin.php?page=rankkernel-sitemap&tab=' . $tabId ),
				'class' => 'nav-tab' . ( $tabId === $tab ? ' nav-tab-active' : '' ),
				'label' => $tabLabel,
			];
		}

		$showGeneral    = 'general' === $tab;
		$showPostTypes  = 'post-types' === $tab;
		$showTaxonomies = 'taxonomies' === $tab;
		$showAuthors    = 'authors' === $tab;

		if ( $showGeneral ) {
			$indexUrl     = Router::indexUrl();
			$itemsPerPage = (string) ( $all['items_per_page'] ?? 1000 );

			$generalRows = [
				[
					'kind'    => 'checkbox',
					'name'    => 'include_images',
					'title'   => __( 'Images in Sitemaps', 'rankkernel' ),
					'checked' => ! empty( $all['include_images'] ),
					'hint'    => __( 'Include reference to images from the post content in sitemaps. This helps search engines index the important images on your pages.', 'rankkernel' ),
				],
				[
					'kind'    => 'checkbox',
					'name'    => 'include_featured_image',
					'title'   => __( 'Include Featured Images', 'rankkernel' ),
					'checked' => ! empty( $all['include_featured_image'] ),
					'hint'    => __( 'Include the Featured Image too, even if it does not appear directly in the post content.', 'rankkernel' ),
				],
				[
					'kind'  => 'ids',
					'name'  => 'exclude_posts',
					'title' => __( 'Exclude Posts', 'rankkernel' ),
					'value' => $this->idsText( $all['exclude_posts'] ?? [] ),
					'hint'  => __( 'Enter post IDs of posts you want to exclude from the sitemap, separated by commas. This option applies to all posts types including posts, pages, and custom post types.', 'rankkernel' ),
				],
				[
					'kind'  => 'ids',
					'name'  => 'exclude_terms',
					'title' => __( 'Exclude Terms', 'rankkernel' ),
					'value' => $this->idsText( $all['exclude_terms'] ?? [] ),
					'hint'  => __( 'Add term IDs, separated by comma. This option is applied for all taxonomies.', 'rankkernel' ),
				],
				[
					'kind'    => 'checkbox',
					'name'    => 'include_empty_terms',
					'title'   => __( 'Include empty terms', 'rankkernel' ),
					'checked' => ! empty( $all['include_empty_terms'] ),
					'hint'    => __( 'List terms that have no published posts.', 'rankkernel' ),
				],
			];
		}

		if ( $showPostTypes ) {
			$postTypeRows = [];

			foreach ( $this->publicPostTypes() as $slug => $label ) {
				$key            = 'pt_' . $slug . '_sitemap';
				$postTypeRows[] = [
					'key'     => $key,
					'label'   => $label,
					'enabled' => (bool) ( $all[ $key ] ?? true ),
					'url'     => home_url( '/' . $slug . '-sitemap.xml' ),
				];
			}
		}

		if ( $showTaxonomies ) {
			$taxonomyRows = [];

			foreach ( $this->publicTaxonomies() as $slug => $label ) {
				$key            = 'tax_' . $slug . '_sitemap';
				$taxonomyRows[] = [
					'key'     => $key,
					'label'   => $label,
					'enabled' => (bool) ( $all[ $key ] ?? true ),
					'url'     => home_url( '/' . $slug . '-sitemap.xml' ),
				];
			}
		}

		if ( $showAuthors ) {
			$authorsRows = [
				[
					'name'    => 'authors_sitemap',
					'title'   => __( 'Authors sitemap', 'rankkernel' ),
					'checked' => ! empty( $all['authors_sitemap'] ),
					'hint'    => __( 'List authors in the sitemap index.', 'rankkernel' ),
				],
				[
					'name'    => 'authors_include_empty',
					'title'   => __( 'Include authors without posts', 'rankkernel' ),
					'checked' => ! empty( $all['authors_include_empty'] ),
					'hint'    => __( 'List every user, not just authors with published posts.', 'rankkernel' ),
				],
			];

			$excludedRoles = $all['authors_exclude_roles'] ?? [];

			if ( ! is_array( $excludedRoles ) ) {
				$excludedRoles = [];
			}

			$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : [];

			$hasEditableRoles = is_array( $roles ) && [] !== $roles;
			$roleRows         = [];

			if ( $hasEditableRoles ) {
				foreach ( $roles as $slug => $details ) {
					if ( ! is_string( $slug ) || '' === $slug ) {
						continue;
					}

					$roleName = $slug;

					if ( is_array( $details ) && isset( $details['name'] ) && is_string( $details['name'] ) ) {
						$roleName = $details['name'];
					}

					$roleRows[] = [
						'slug'     => $slug,
						'name'     => $roleName,
						'excluded' => in_array( $slug, $excludedRoles, true ),
					];
				}
			}

			$authorsExcludeUsers = [
				'name'  => 'authors_exclude_users',
				'title' => __( 'Exclude users', 'rankkernel' ),
				'value' => $this->idsText( $all['authors_exclude_users'] ?? [] ),
				'hint'  => __( 'Comma separated user ids to leave out of the authors sitemap.', 'rankkernel' ),
			];
		}

		require __DIR__ . '/Views/sitemap-settings.php';
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

		$verified = check_admin_referer( 'rankkernel_sitemap_settings' );
		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$tab = $this->currentTab();

		$this->settings->set( $this->collectPartial( $tab ) );

		SitemapCache::invalidateAll();

		$redirect = admin_url( 'admin.php?page=rankkernel-sitemap&tab=' . $tab . '&settings-updated=1' );
		wp_safe_redirect( $redirect );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
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

		if ( 'general' === $tab ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
			if ( isset( $_POST['items_per_page'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value cast below, clamped on save, unslashed here, cast to scalar on the following statement.
				$rawItems                  = wp_unslash( $_POST['items_per_page'] );
				$partial['items_per_page'] = is_string( $rawItems ) ? (int) $rawItems : 0;
			}

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
			$partial['include_images'] = isset( $_POST['include_images'] );
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
			$partial['include_featured_image'] = isset( $_POST['include_featured_image'] );

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
			if ( isset( $_POST['exclude_posts'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value exploded below, absint on save, unslashed here, sanitized or validated on the following statements.
				$rawPosts                 = wp_unslash( $_POST['exclude_posts'] );
				$partial['exclude_posts'] = is_string( $rawPosts ) ? explode( ',', $rawPosts ) : [];
			}

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
			if ( isset( $_POST['exclude_terms'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value exploded below, absint on save, unslashed here, sanitized or validated on the following statements.
				$rawTerms                 = wp_unslash( $_POST['exclude_terms'] );
				$partial['exclude_terms'] = is_string( $rawTerms ) ? explode( ',', $rawTerms ) : [];
			}

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
			$partial['include_empty_terms'] = isset( $_POST['include_empty_terms'] );

			return $partial;
		}

		if ( 'post-types' === $tab ) {
			foreach ( $this->publicPostTypes() as $slug => $label ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
				$partial[ 'pt_' . $slug . '_sitemap' ] = isset( $_POST[ 'pt_' . $slug . '_sitemap' ] );
			}

			return $partial;
		}

		if ( 'taxonomies' === $tab ) {
			foreach ( $this->publicTaxonomies() as $slug => $label ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
				$partial[ 'tax_' . $slug . '_sitemap' ] = isset( $_POST[ 'tax_' . $slug . '_sitemap' ] );
			}

			return $partial;
		}

		// Authors tab.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
		$partial['authors_sitemap'] = isset( $_POST['authors_sitemap'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
		$partial['authors_include_empty'] = isset( $_POST['authors_include_empty'] );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified in handleSave before collectPartial runs, unslashed here, sanitized or validated on the following statements.
		$rawRoles = isset( $_POST['authors_exclude_roles'] ) ? wp_unslash( $_POST['authors_exclude_roles'] ) : [];
		if ( ! is_array( $rawRoles ) ) {
			$rawRoles = [];
		}

		$roles = [];

		foreach ( $rawRoles as $rawRole ) {
			if ( is_string( $rawRole ) || is_numeric( $rawRole ) ) {
				$roles[] = (string) $rawRole;
			}
		}

		$partial['authors_exclude_roles'] = $roles;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleSave before collectPartial runs.
		if ( isset( $_POST['authors_exclude_users'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handleSave, value exploded below, absint on save, unslashed here, sanitized or validated on the following statements.
			$rawUsers                         = wp_unslash( $_POST['authors_exclude_users'] );
			$partial['authors_exclude_users'] = is_string( $rawUsers ) ? explode( ',', $rawUsers ) : [];
		}

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
	 * Public taxonomies as slug to label.
	 *
	 * @return array<string, string>
	 */
	private function publicTaxonomies(): array {
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

		if ( ! is_array( $taxonomies ) ) {
			return [];
		}

		return $this->slugsWithLabels( $taxonomies, [] );
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
	 * Comma list of positive integer ids from a stored value.
	 *
	 * @param mixed $value Stored ids.
	 * @return string The result.
	 */
	private function idsText( mixed $value ): string {
		if ( ! is_array( $value ) ) {
			return '';
		}

		$ids = [];

		foreach ( $value as $item ) {
			$int = (int) $item;

			if ( $int > 0 ) {
				$ids[] = (string) $int;
			}
		}

		return implode( ',', $ids );
	}
}
