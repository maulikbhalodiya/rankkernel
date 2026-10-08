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
define( 'RANKKERNEL_VERSION', '0.1.0' );
define( 'RANKKERNEL_FILE', __FILE__ );
define( 'RANKKERNEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'RANKKERNEL_URL', plugin_dir_url( __FILE__ ) );

// Autoload.
//
// The plugin ships no Composer runtime dependencies. The composer.json require
// block lists only the PHP version, and every other entry is a development tool
// that is never deployed. Composer's autoloader therefore exists for one reason:
// to map the plugin's own RankKernel namespace onto src/.
//
// Registering that mapping here as well means the plugin loads on a site where
// the files were deployed without running composer install, which is the normal
// case for a copy, a zip or a git clone. Composer's autoloader is still loaded
// when it is present, so development tooling and any future runtime dependency
// keep working exactly as before.
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'RankKernel\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$file = RANKKERNEL_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

$rankkernel_autoloader = RANKKERNEL_DIR . 'vendor/autoload.php';
if ( file_exists( $rankkernel_autoloader ) ) {
	require_once $rankkernel_autoloader;
}

// Defence in depth. The fallback above should always resolve the plugin's own
// classes, so this only fires when something is genuinely wrong, such as an
// incomplete copy of the plugin. Say so plainly rather than letting the first
// class reference die later with a bare class not found the operator cannot act on.
if ( ! class_exists( \RankKernel\Plugin::class ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'RankKernel could not load its own classes, so the plugin files look incomplete. Please reinstall RankKernel.', 'rankkernel' );
			echo '</p></div>';
		}
	);

	return;
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
	$default_modules = array( 'metadata', 'analysis', 'sitemaps', 'schema', 'breadcrumbs' );
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

	// Conflict detection seeds the notice option (cheap cache; renderer re-checks live).
	$conflicts = rankkernel_detect_seo_conflicts();
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
function rankkernel_seo_conflicts_map(): array {
	return array(
		'wordpress-seo/wordpress-seo.php'             => 'Yoast SEO',
		'seo-by-rank-math/rank-math.php'              => 'Rank Math',
		'wp-seopress/seopress.php'                    => 'SEOPress',
		'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
		'autodescription/autodescription.php'         => 'The SEO Framework',
		'slim-seo/slim-seo.php'                       => 'Slim SEO',
	);
}

/**
 * Detect active SEO plugin conflicts from single-site and network-activated plugins.
 *
 * @return array<int, string> List of conflicting plugin labels.
 */
function rankkernel_detect_seo_conflicts(): array {
	$active  = (array) get_option( 'active_plugins', array() );
	$network = get_option( 'active_sitewide_plugins', array() );
	if ( is_array( $network ) ) {
		$active = array_merge( $active, array_keys( $network ) );
	}
	$conflicts = array();
	foreach ( rankkernel_seo_conflicts_map() as $basename => $label ) {
		if ( in_array( $basename, $active, true ) ) {
			$conflicts[] = $label;
		}
	}
	return $conflicts;
}

/**
 * Conflict admin notice.
 */
function rankkernel_conflict_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! is_object( $screen ) || ! isset( $screen->id ) || ! is_string( $screen->id ) ) {
		return;
	}
	$screen_id = $screen->id;
	if ( 'toplevel_page_rankkernel' !== $screen_id && ! str_starts_with( $screen_id, 'rankkernel_page_' ) && 'rankkernel' !== $screen_id && 'plugins' !== $screen_id && 'plugins.php' !== $screen_id ) {
		return;
	}
	// Live re-check every call; the option is a cache only.
	$conflicts = rankkernel_detect_seo_conflicts();
	$stored    = get_option( 'rankkernel_conflict_notice', array() );
	if ( array() === $conflicts ) {
		if ( is_array( $stored ) && array() !== $stored ) {
			delete_option( 'rankkernel_conflict_notice' );
		}
		return;
	}
	if ( $stored !== $conflicts ) {
		update_option( 'rankkernel_conflict_notice', $conflicts );
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
