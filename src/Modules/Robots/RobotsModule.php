<?php
/**
 * Crawl Signals module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;

/**
 * Crawl Signals module: virtual robots.txt and AI crawler controls.
 *
 * Optional and default off. When disabled nothing is instantiated and no
 * hook is registered. robots.txt is always virtual: the module filters the
 * WordPress output and never writes a physical file.
 */
class RobotsModule implements ModuleInterface {
	/**
	 * Cached enabled check.
	 *
	 * @var bool|null
	 */
	private ?bool $enabledCache = null;

	/**
	 * Shared enable map.
	 *
	 * @var ModuleEnableMap|null
	 */
	private ?ModuleEnableMap $enableMap;

	/**
	 * Settings store, built on boot only.
	 *
	 * @var RobotsSettings|null
	 */
	private ?RobotsSettings $settings = null;

	/**
	 * Builder, built on boot only.
	 *
	 * @var RobotsBuilder|null
	 */
	private ?RobotsBuilder $builder = null;

	/**
	 * Llms.txt settings, built on boot only.
	 *
	 * @var LlmsSettings|null
	 */
	private ?LlmsSettings $llmsSettings = null;

	/**
	 * Llms.txt router, built on boot only when llms.txt is enabled.
	 *
	 * @var LlmsRouter|null
	 */
	private ?LlmsRouter $llmsRouter = null;

	/**
	 * Llms.txt file writer, built on boot only when llms.txt is enabled.
	 *
	 * @var LlmsFileWriter|null
	 */
	private ?LlmsFileWriter $llmsWriter = null;

	/**
	 * Constructor.
	 *
	 * @param ModuleEnableMap|null $enableMap Optional shared enable map.
	 */
	public function __construct( ?ModuleEnableMap $enableMap = null ) {
		$this->enableMap = $enableMap;
	}

	/**
	 * Get module id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'robots';
	}

	/**
	 * Get human readable name.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return __( 'Crawl Signals', 'rankkernel' );
	}

	/**
	 * Module priority.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 25;
	}

	/**
	 * Dependencies.
	 *
	 * @return string[] The result.
	 */
	public function dependsOn(): array {
		return [];
	}

	/**
	 * Whether the module is enabled.
	 *
	 * @return bool The result.
	 */
	public function isEnabled(): bool {
		if ( null !== $this->enabledCache ) {
			return $this->enabledCache;
		}

		if ( null !== $this->enableMap ) {
			$this->enabledCache = $this->enableMap->isEnabled( 'robots' );

			return $this->enabledCache;
		}

		$map = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		if ( array_key_exists( 'robots', $map ) ) {
			$this->enabledCache = (bool) $map['robots'];
		} else {
			$this->enabledCache = in_array( 'robots', $map, true );
		}

		return $this->enabledCache;
	}

	/**
	 * Register services. No hooks, nothing heavy is built here.
	 */
	public function register(): void {
	}

	/**
	 * Boot hooks, only when enabled.
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$this->settings = new RobotsSettings();
		$this->builder  = new RobotsBuilder();

		add_filter( 'robots_txt', [ $this, 'filterRobots' ], 5, 2 );
		add_action( 'admin_notices', [ $this, 'renderPhysicalFileNotice' ] );

		$this->llmsSettings = new LlmsSettings();

		if ( (bool) $this->llmsSettings->get( 'enabled', false ) ) {
			$this->llmsWriter = new LlmsFileWriter();
			$this->llmsRouter = new LlmsRouter( new LlmsGenerator(), new LlmsCollector(), $this->llmsSettings );
			$this->llmsRouter->register();

			add_action( 'save_post', [ $this, 'invalidateLlms' ] );
			add_action( 'edited_terms', [ $this, 'invalidateLlms' ] );
			add_action( 'admin_notices', [ $this, 'renderLlmsPhysicalNotice' ] );
		}

		$this->maybeFlushRules();
	}

	/**
	 * Invalidate the cached llms.txt document.
	 */
	public function invalidateLlms(): void {
		LlmsRouter::invalidate();
	}

	/**
	 * Warn that a physical llms.txt shadows the virtual route.
	 *
	 * The file is never overwritten.
	 */
	public function renderLlmsPhysicalNotice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$writer = $this->llmsWriter;

		if ( null === $writer || ! $writer->exists() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'A physical llms.txt file exists in the site root, so the virtual llms.txt route is not used. RankKernel will not overwrite it.', 'rankkernel' );
		echo '</p></div>';
	}

	/**
	 * Get the llms.txt router, for testing.
	 *
	 * @return LlmsRouter|null The result.
	 */
	public function getLlmsRouter(): ?LlmsRouter {
		return $this->llmsRouter;
	}

	/**
	 * Get the llms.txt file writer, for testing.
	 *
	 * @return LlmsFileWriter|null The result.
	 */
	public function getLlmsWriter(): ?LlmsFileWriter {
		return $this->llmsWriter;
	}

	/**
	 * Filter the virtual robots.txt output.
	 *
	 * Starts from the core output, honours the public flag and never
	 * writes a physical file.
	 *
	 * @param string $output   Core robots.txt output.
	 * @param mixed  $isPublic Whether the site is public.
	 * @return string The result.
	 */
	public function filterRobots( string $output, mixed $isPublic = true ): string {
		if ( ! (bool) $isPublic ) {
			return $output;
		}

		$settings = ( null !== $this->settings ) ? $this->settings : new RobotsSettings();
		$builder  = ( null !== $this->builder ) ? $this->builder : new RobotsBuilder();

		$mode   = (string) $settings->get( 'mode', 'default' );
		$custom = (string) $settings->get( 'custom', '' );

		$base = ( 'custom' === $mode && '' !== trim( $custom ) ) ? $custom : $output;

		$presets    = RobotsDirectives::sanitizePresets( $settings->get( 'presets', [] ) );
		$sitemapUrl = RobotsDirectives::sanitizeSitemapUrl( (string) $settings->get( 'sitemap_url', '' ) );

		return $builder->build( $base, (bool) $isPublic, $presets, $sitemapUrl );
	}

	/**
	 * Warn that a physical robots.txt shadows the virtual editor.
	 *
	 * The file is never deleted.
	 */
	public function renderPhysicalFileNotice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->hasPhysicalFile() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'A physical robots.txt file exists in the site root, so the server serves it before WordPress. The RankKernel robots editor has no effect while it is present. RankKernel will not delete it.', 'rankkernel' );
		echo '</p></div>';
	}

	/**
	 * Whether a physical robots.txt exists at the site root.
	 *
	 * @return bool The result.
	 */
	public function hasPhysicalFile(): bool {
		$path = $this->physicalFilePath();

		return '' !== $path && file_exists( $path );
	}

	/**
	 * Absolute path of the physical robots.txt, filterable for tests.
	 *
	 * @return string The result.
	 */
	private function physicalFilePath(): string {
		$default = defined( 'ABSPATH' ) ? ABSPATH . 'robots.txt' : '';

		/**
		 * Filter the physical robots.txt path used for the bypass notice.
		 *
		 * @param string $path Absolute path.
		 */
		$path = apply_filters( 'rankkernel/robots/physical_file', $default ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		return ( is_string( $path ) && '' !== $path ) ? $path : $default;
	}

	/**
	 * Flush rewrite rules once per plugin version.
	 *
	 * A fresh install or upgrade has stale cached rules without the
	 * Crawl Signals routes, so they must be regenerated once.
	 */
	private function maybeFlushRules(): void {
		$version = \RankKernel\Plugin::version();
		$stored  = get_option( 'rankkernel_robots_rules_version', '' );

		if ( $version !== $stored ) {
			flush_rewrite_rules( false );
			update_option( 'rankkernel_robots_rules_version', $version, false );
		}
	}
}
