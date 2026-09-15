<?php
/**
 * Breadcrumbs block, registration plus server render.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs\blocks;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Breadcrumbs\BreadcrumbsSettings;
use RankKernel\Modules\Breadcrumbs\Renderer;
use RankKernel\Modules\Breadcrumbs\TrailBuilder;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Settings\SettingsStore;
use WP_Query;

use function RankKernel\Modules\Breadcrumbs\filter_breadcrumb_items;
use function RankKernel\Modules\Breadcrumbs\normalize_breadcrumb_items;
use function RankKernel\Modules\Breadcrumbs\resolve_breadcrumb_args;

/**
 * Registers the rankkernel/breadcrumbs block and renders it on the server.
 *
 * Dynamic block, save returns null in the editor script. The render
 * callback delegates to the same TrailBuilder and Renderer as the
 * template tag and shortcode, so visible output and schema share one
 * canonical trail. Ships no view script and no inline style tag.
 */
final class BreadcrumbsBlock {
	/**
	 * Constructor.
	 *
	 * @param BreadcrumbsSettings|null $settings Optional settings (tests).
	 */
	public function __construct(
		private readonly ?BreadcrumbsSettings $settings = null
	) {
	}

	/**
	 * Register the dynamic block type.
	 */
	public function register(): void {
		$this->registerBlock();
	}

	/**
	 * Plugin relative asset URL, empty when unavailable (tests, early boot).
	 *
	 * @param string $path Path.
	 * @return string The result.
	 */
	private static function assetUrl( string $path ): string {
		if ( ! function_exists( 'plugins_url' ) ) {
			return '';
		}

		$main = dirname( __DIR__, 4 ) . '/rankkernel.php';

		return (string) plugins_url( $path, $main );
	}

	/**
	 * Asset version from the single RANKKERNEL_VERSION constant, so
	 * bumping that one value in rankkernel.php busts the editor
	 * browser cache for the script and the stylesheet.
	 *
	 * @return string The result.
	 */
	private static function assetVersion(): string {
		return \RankKernel\Plugin::version();
	}

	/**
	 * Register the dynamic block type from its block.json folder.
	 *
	 * The editor script and style register explicitly with full
	 * dependency lists instead of relying on metadata auto loading,
	 * so the editor globals they use always load first. No view
	 * script ships: the block is static server output. The frontend
	 * stylesheet registers here and enqueues only on render.
	 */
	public function registerBlock(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( function_exists( 'wp_register_script' ) ) {
			wp_register_script(
				'rankkernel-breadcrumbs-editor',
				self::assetUrl( 'src/Modules/Breadcrumbs/blocks/breadcrumbs/breadcrumbs-editor.js' ),
				[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
				self::assetVersion(),
				true
			);
		}

		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style(
				'rankkernel-breadcrumbs-editor',
				self::assetUrl( 'src/Modules/Breadcrumbs/blocks/breadcrumbs/editor.css' ),
				[],
				self::assetVersion()
			);

			if ( ! wp_style_is( 'rankkernel-breadcrumbs', 'registered' ) ) {
				wp_register_style(
					'rankkernel-breadcrumbs',
					self::assetUrl( 'src/Modules/Breadcrumbs/breadcrumbs.css' ),
					[],
					self::assetVersion()
				);
			}
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			try {
				wp_set_script_translations( 'rankkernel-breadcrumbs-editor', 'rankkernel' );
			} catch ( \Throwable $e ) {
				// Test doubles can leak this name into later suites without a backend, so a failure here must never break registration. Production WP always provides it.
				unset( $e );
			}
		}

		register_block_type(
			__DIR__ . '/breadcrumbs',
			[
				'editor_script'   => 'rankkernel-breadcrumbs-editor',
				'editor_style'    => 'rankkernel-breadcrumbs-editor',
				'style'           => 'rankkernel-breadcrumbs',
				'render_callback' => [ $this, 'render' ],
			]
		);
	}

	/**
	 * Render the block.
	 *
	 * Attributes mirror the template tag args: showHomeItem maps to
	 * show_home, showCurrentItem to show_current, separator overrides
	 * the stored separator when non empty. showOnHomePage false
	 * suppresses output on the front page. Block attributes sanitize
	 * on render. The optional content and block params exist because
	 * WP core passes them to every render callback; this render
	 * ignores content and reads attributes only. Returns an empty
	 * string when the context yields no trail.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block inner content, unused.
	 * @param mixed                $block      Parsed block instance, unused.
	 * @return string The result.
	 */
	public function render( array $attributes, string $content = '', mixed $block = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- callback signature required by the stubbed WordPress function under test.
		$settings = $this->settings ?? new BreadcrumbsSettings();

		if ( ! $this->showOnHomePage( $attributes ) && $this->isFrontPage() ) {
			return '';
		}

		$query = $this->currentQuery();

		if ( null === $query ) {
			return '';
		}

		$args = [
			'separator'    => $this->blockSeparator( $attributes, $settings ),
			'show_home'    => array_key_exists( 'showHomeItem', $attributes ) ? (bool) $attributes['showHomeItem'] : (bool) $settings->get( 'show_home', true ),
			'show_current' => array_key_exists( 'showCurrentItem', $attributes ) ? (bool) $attributes['showCurrentItem'] : (bool) $settings->get( 'show_current', true ),
		];

		// Route through the shared resolver so the block honors the
		// rankkernel/breadcrumbs/args filter exactly like the template tag.
		$args = resolve_breadcrumb_args( $settings, $args );

		$ctx     = new Context( $query, new SettingsStore() );
		$builder = new TrailBuilder(
			$ctx,
			$settings,
			[
				'show_home'    => $args['show_home'],
				'show_current' => $args['show_current'],
			]
		);
		$items   = normalize_breadcrumb_items( filter_breadcrumb_items( $builder->build() ) );

		// Visibility is applied by the builder before pagination, so the
		// renderer must not trim again.
		$renderArgs                     = $args;
		$renderArgs['apply_visibility'] = false;

		$html = ( new Renderer() )->render( $items, $renderArgs );

		if ( '' === $html ) {
			return '';
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'rankkernel-breadcrumbs' );
		}

		if ( function_exists( 'apply_filters' ) ) {
			$html = (string) apply_filters( 'rankkernel/breadcrumbs', $html, $args ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? (string) get_block_wrapper_attributes() : '';

		if ( '' === $wrapper ) {
			return $html;
		}

		return '<div ' . $wrapper . '>' . $html . '</div>';
	}

	/**
	 * Whether the block may render on the home page.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return bool The result.
	 */
	private function showOnHomePage( array $attributes ): bool {
		if ( ! array_key_exists( 'showOnHomePage', $attributes ) ) {
			return false;
		}

		return (bool) $attributes['showOnHomePage'];
	}

	/**
	 * Block separator: non empty attribute wins, else the setting.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param BreadcrumbsSettings  $settings   Breadcrumb settings.
	 * @return string The result.
	 */
	private function blockSeparator( array $attributes, BreadcrumbsSettings $settings ): string {
		if ( isset( $attributes['separator'] ) && is_string( $attributes['separator'] ) && '' !== trim( $attributes['separator'] ) ) {
			return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $attributes['separator'] ) : trim( $attributes['separator'] );
		}

		return (string) $settings->get( 'separator', '/' );
	}

	/**
	 * Whether the current request is the front page.
	 *
	 * @return bool The result.
	 */
	private function isFrontPage(): bool {
		return function_exists( 'is_front_page' ) && (bool) is_front_page();
	}

	/**
	 * Current query for the breadcrumb context.
	 *
	 * Prefers the block context post id when the global query is
	 * unavailable is handled by falling back to the global query,
	 * which carries the already loaded WordPress objects.
	 *
	 * @return WP_Query|null Query instance or null when unavailable.
	 */
	private function currentQuery(): ?WP_Query {
		global $wp_query;

		if ( isset( $wp_query ) && $wp_query instanceof WP_Query ) {
			return $wp_query;
		}

		return null;
	}
}
