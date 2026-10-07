<?php
/**
 * Shared RankKernel admin style handles.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the shared admin stylesheet handles.
 *
 * The token layer used to reach every feature stylesheet through a CSS
 * import rule, which the browser fetches sequentially after parsing each
 * parent sheet. That is a render blocking second request on every RankKernel
 * admin screen. The token layer, and the optional UI component layer, are
 * registered here as their own versioned handles instead. A feature
 * stylesheet depends on them, so WordPress prints every sheet in parallel
 * and the token layer loads once per screen.
 */
final class AdminStyles {
	/**
	 * Handle for the shared design token layer.
	 */
	public const TOKEN_HANDLE = 'rankkernel-admin';

	/**
	 * Handle for the shared admin UI component layer.
	 */
	public const UI_HANDLE = 'rankkernel-ui';

	/**
	 * Register and enqueue the shared design token layer.
	 *
	 * @param string $pluginFile Main plugin file used to build the URL.
	 * @return void
	 */
	public static function enqueueTokenLayer( string $pluginFile ): void {
		self::enqueueStyle( self::TOKEN_HANDLE, 'assets/css/rankkernel-admin.css', $pluginFile, [] );
	}

	/**
	 * Register and enqueue the shared admin UI component layer.
	 *
	 * The UI layer consumes only the token layer custom properties, so it
	 * depends on the token handle and never loads without it.
	 *
	 * @param string $pluginFile Main plugin file used to build the URL.
	 * @return void
	 */
	public static function enqueueUiLayer( string $pluginFile ): void {
		self::enqueueStyle( self::UI_HANDLE, 'assets/css/rankkernel-ui.css', $pluginFile, [ self::TOKEN_HANDLE ] );
	}

	/**
	 * Register and enqueue one stylesheet handle.
	 *
	 * Every WordPress call is guarded so a missing function fails safe
	 * instead of fataling, which also keeps stubbed unit tests working.
	 *
	 * @param string        $handle     Stylesheet handle.
	 * @param string        $path       Path relative to the plugin root.
	 * @param string        $pluginFile Main plugin file used to build the URL.
	 * @param array<string> $deps       Registered dependency handles.
	 * @return void
	 */
	private static function enqueueStyle( string $handle, string $path, string $pluginFile, array $deps ): void {
		if ( ! function_exists( 'plugins_url' ) || ! function_exists( 'wp_register_style' ) ) {
			return;
		}

		wp_register_style( $handle, plugins_url( $path, $pluginFile ), $deps, Plugin::version() );

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( $handle );
		}

		if ( self::TOKEN_HANDLE === $handle ) {
			self::preloadMaterialSymbolsFont( $pluginFile );
		}
	}

	/**
	 * Emit a preload for the Material Symbols webfont on admin screens.
	 *
	 * The icons are ligature text spans, so without an early fetch every
	 * page paint shows the raw ligature word until the woff2 arrives. The
	 * link is printed from admin_head so it lands ahead of the stylesheets.
	 *
	 * @param string $pluginFile Main plugin file used to build the URL.
	 * @return void
	 */
	private static function preloadMaterialSymbolsFont( string $pluginFile ): void {
		if ( ! function_exists( 'add_action' ) || ! function_exists( 'plugins_url' ) ) {
			return;
		}

		add_action(
			'admin_head',
			static function () use ( $pluginFile ): void {
				$url = plugins_url( 'assets/fonts/material-symbols-outlined-variable.woff2', $pluginFile );
				echo '<link rel="preload" as="font" type="font/woff2" crossorigin href="' . esc_url( $url ) . '">' . "\n";
			}
		);
	}
}
