<?php
/**
 * Plugin Name: RankKernel – Free SEO & Schema Engine
 * Description: 100% free, lightweight SEO with no paywalls or upsell banners, metadata engine, XML sitemaps, schema, breadcrumbs, redirects, 404 monitor and IndexNow. Modules that are off cost zero: no hooks, no queries, no bloat.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: Maulik Bhalodiya
 * Author URI: https://github.com/maulikbhalodiya
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rankkernel
 * Domain Path: /languages
 *
 * @package RankKernel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Requirement checks BEFORE autoload.
if ( version_compare( PHP_VERSION, '8.2.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'RankKernel requires PHP 8.2 or higher. Please upgrade PHP to use this plugin.', 'rankkernel' );
			echo '</p></div>';
		}
	);
	return;
}

global $wp_version;
if ( isset( $wp_version ) && version_compare( $wp_version, '6.5', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'RankKernel requires WordPress 6.5 or higher. Please upgrade WordPress to use this plugin.', 'rankkernel' );
			echo '</p></div>';
		}
	);
	return;
}

// Single source of truth for the plugin version. Bump this one value on
// release and every asset URL and stored version reference follows.
if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
	define( 'RANKKERNEL_VERSION', '0.1.0' );
}
if ( ! defined( 'RANKKERNEL_FILE' ) ) {
	define( 'RANKKERNEL_FILE', __FILE__ );
}
if ( ! defined( 'RANKKERNEL_DIR' ) ) {
	define( 'RANKKERNEL_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RANKKERNEL_URL' ) ) {
	define( 'RANKKERNEL_URL', plugin_dir_url( __FILE__ ) );
}

// Autoload.
$rankkernel_autoloader = RANKKERNEL_DIR . 'vendor/autoload.php';
if ( file_exists( $rankkernel_autoloader ) ) {
	require_once $rankkernel_autoloader;
}

/**
 * Activation callback.
 */
function rankkernel_activate(): void {
	// Check requirements at activation, deactivate self if missing.
	if ( version_compare( PHP_VERSION, '8.2.0', '<' ) ) {
		deactivate_plugins( plugin_basename( RANKKERNEL_FILE ) );
		return;
	}
	global $wp_version;
	if ( isset( $wp_version ) && version_compare( $wp_version, '6.5', '<' ) ) {
		deactivate_plugins( plugin_basename( RANKKERNEL_FILE ) );
		return;
	}

	// Seed rankkernel_modules, default ON modules.
	$default_modules = array( 'metadata', 'analysis', 'sitemaps', 'schema', 'breadcrumbs', 'importer' );
	if ( false === get_option( 'rankkernel_modules' ) ) {
		add_option( 'rankkernel_modules', $default_modules );
	}

	// Seed rankkernel_settings defaults.
	if ( false === get_option( 'rankkernel_settings' ) ) {
		$defaults = \RankKernel\Settings\SettingsStore::defaults();
		add_option( 'rankkernel_settings', $defaults );
	}

	// Seed db version.
	if ( false === get_option( 'rankkernel_db_version' ) ) {
		add_option( 'rankkernel_db_version', '0.0.0', '', false );
	}

	// Conflict detection: Yoast / Rank Math / SEOPress active.
	$active_plugins = (array) get_option( 'active_plugins', array() );
	$conflicts      = array();
	if ( in_array( 'wordpress-seo/wordpress-seo.php', $active_plugins, true ) ) {
		$conflicts[] = 'Yoast SEO';
	}
	if ( in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true ) ) {
		$conflicts[] = 'Rank Math';
	}
	if ( in_array( 'wp-seopress/seopress.php', $active_plugins, true ) ) {
		$conflicts[] = 'SEOPress';
	}

	if ( array() !== $conflicts ) {
		update_option( 'rankkernel_conflict_notice', $conflicts );
	}
}
register_activation_hook( __FILE__, 'rankkernel_activate' );

/**
 * Deactivation callback, leave data intact.
 */
function rankkernel_deactivate(): void {
	// Intentionally leave all data, no destructive flush.
}
register_deactivation_hook( __FILE__, 'rankkernel_deactivate' );

/**
 * Conflict admin notice.
 */
function rankkernel_conflict_notice(): void {
	$conflicts = get_option( 'rankkernel_conflict_notice', array() );
	if ( array() === $conflicts || ! is_array( $conflicts ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! is_object( $screen ) || ! isset( $screen->id ) ) {
		return;
	}
	if ( ! is_string( $screen->id ) || ! str_contains( $screen->id, 'rankkernel' ) ) {
		return;
	}
	echo '<div class="notice notice-warning is-dismissible"><p>';
	echo esc_html(
		sprintf(
			/* translators: %s: comma-separated list of conflicting plugins */
			__( 'RankKernel detected another SEO plugin active (%s). Running multiple SEO plugins may cause duplicated meta tags. Please deactivate the one you do not need.', 'rankkernel' ),
			implode( ', ', $conflicts )
		)
	);
	echo '</p></div>';
}
add_action( 'admin_notices', 'rankkernel_conflict_notice' );

// Boot: plugins_loaded priority 10 -> registerCoreServices.
add_action(
	'plugins_loaded',
	static function (): void {
		\RankKernel\Plugin::getInstance()->registerCoreServices();
	},
	10
);

// Boot: init -> bootModules.
add_action(
	'init',
	static function (): void {
		\RankKernel\Plugin::getInstance()->bootModules();
	}
);
