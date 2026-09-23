<?php
/**
 * Plugin singleton / service locator.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel;

defined( 'ABSPATH' ) || exit;

use RankKernel\Admin\AdminMenu;
use RankKernel\Admin\MetadataBox;
use RankKernel\Admin\SchemaMetabox;
use RankKernel\Database\Migrations\MigrationRunner;
use RankKernel\Modules\Analysis\AnalysisModule;
use RankKernel\Modules\Breadcrumbs\BreadcrumbsModule;
use RankKernel\Modules\Metadata\MetadataModule;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleManager;
use RankKernel\Modules\Monitor\MonitorModule;
use RankKernel\Modules\Redirects\RedirectsModule;
use RankKernel\Modules\Robots\RobotsModule;
use RankKernel\Modules\Schema\SchemaModule;
use RankKernel\Modules\Sitemaps\SitemapsModule;
use RankKernel\Rest\ModulesController;
use RankKernel\Rest\SettingsController;
use RankKernel\Settings\SettingsStore;

/**
 * Main plugin class, service locator (not a DI container).
 */
final class Plugin {
	/**
	 * Plugin version read from the single source constant.
	 *
	 * Rankkernel.php defines RANKKERNEL_VERSION, the one place to bump
	 * on release. Every asset URL and stored version reference flows
	 * through here, so changing that one value busts every browser
	 * cache at once. The fallback keeps unit tests that never define
	 * the constant working.
	 *
	 * @return string The result.
	 */
	public static function version(): string {
		return defined( 'RANKKERNEL_VERSION' ) ? (string) RANKKERNEL_VERSION : '0.0.0';
	}

	/**
	 * Singleton instance.
	 *
	 * @var static?Plugin
	 */
	private static ?Plugin $instance = null;

	/**
	 * Core services.
	 *
	 * @var array<string, object>
	 */
	private array $services = [];

	/**
	 * Enabled module instances only.
	 *
	 * @var array<string, object>
	 */
	private array $modules = [];

	/**
	 * Private constructor, use getInstance().
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {
	}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup(): void {
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin The result.
	 */
	public static function getInstance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register core services.
	 *
	 * Constructs SettingsStore, ModuleManager, and REST controllers.
	 * Parses the module enable map ONCE, no module instantiation yet.
	 */
	public function registerCoreServices(): void {
		$settingsStore = new SettingsStore();
		$enableMap     = new ModuleEnableMap();
		$moduleManager = new ModuleManager( $enableMap );

		$settingsController = new SettingsController( $settingsStore );
		$modulesController  = new ModulesController();

		$migrationRunner = new MigrationRunner();

		/**
		 * Baseline migration, marks the initial schema-less baseline (v1 core
		 * has zero required tables per blueprint §D.4).
		 */
		$migrationRunner->register(
			'0.1.0',
			static function (): void {
			}
		);

		$this->services['settings']            = $settingsStore;
		$this->services['enable_map']          = $enableMap;
		$this->services['module_manager']      = $moduleManager;
		$this->services['settings_controller'] = $settingsController;
		$this->services['modules_controller']  = $modulesController;
		$this->services['migrations']          = $migrationRunner;

		// Admin UI, register only on admin screens.
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			$adminMenu = new AdminMenu( $settingsStore, $enableMap );
			$adminMenu->register();
			$this->services['admin_menu'] = $adminMenu;

			add_action( 'admin_menu', [ $adminMenu, 'addGeneralPage' ] );
			add_action( 'admin_menu', [ $adminMenu, 'addSchemaPage' ] );
			add_action( 'admin_menu', [ $adminMenu, 'addRedirectsPage' ] );
			add_action( 'admin_menu', [ $adminMenu, 'addMonitorPage' ] );

			/*
			 * AJAX handler for the redirect list partial refresh.
			 * Only available to authenticated admins — wp_ajax_ not
			 * wp_ajax_nopriv_ so unauthenticated requests are rejected
			 * before our handler even runs.
			 */
			add_action(
				'wp_ajax_rankkernel_redirects_list',
				[ $adminMenu->getRedirectsPage(), 'handleAjaxList' ]
			);

			$schemaMetabox = new SchemaMetabox();
			$schemaMetabox->register();
			$this->services['schema_metabox'] = $schemaMetabox;

			// Metadata editor metabox and Gutenberg sidebar, hard gated on the
			// metadata module so a disabled module stays silent.
			if ( $enableMap->isEnabled( 'metadata' ) ) {
				$metadataBox = new MetadataBox( $settingsStore, null, null, null, $enableMap );
				$metadataBox->register();
				$this->services['metadata_box'] = $metadataBox;
			}
		}

		// Metadata module (optional, default-ON per activation seed).
		$metadataModule = new MetadataModule( $settingsStore, $enableMap );
		$moduleManager->register( $metadataModule );

		// Content Analysis module (optional, default-ON per activation seed).
		$analysisModule = new AnalysisModule( $enableMap );
		$moduleManager->register( $analysisModule );

		// Schema module (optional, default-ON per activation seed).
		$schemaModule = new SchemaModule( $settingsStore, $enableMap );
		$moduleManager->register( $schemaModule );

		// Breadcrumbs module (optional, default-ON per activation seed).
		$breadcrumbsModule = new BreadcrumbsModule( $enableMap );
		$moduleManager->register( $breadcrumbsModule );

		// Sitemaps module (optional, default-ON per activation seed).
		$sitemapsModule = new SitemapsModule( $enableMap );
		$moduleManager->register( $sitemapsModule );

		// Redirects module (optional, default off).
		$redirectsModule = new RedirectsModule( $enableMap );
		$moduleManager->register( $redirectsModule );

		// 404 Monitor module (optional, default off).
		$monitorModule = new MonitorModule( $enableMap );
		$moduleManager->register( $monitorModule );

		// Crawl Signals module (optional, default off).
		$robotsModule = new RobotsModule( $enableMap );
		$moduleManager->register( $robotsModule );

		add_action( 'init', [ $migrationRunner, 'maybeRun' ], 10 );

		// Evaluate module enable map once.
		$moduleManager->evaluateAll();

		// Register REST routes on rest_api_init (core service, always on).
		add_action(
			'rest_api_init',
			function () use ( $settingsController, $modulesController ): void {
				$settingsController->registerRoutes();
				$modulesController->registerRoutes();
			}
		);

		// Load text domain on init.
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'rankkernel', false, dirname( plugin_basename( RANKKERNEL_FILE ) ) . '/languages' );
			}
		);
	}

	/**
	 * Boot enabled modules.
	 *
	 * Delegates to ModuleManager::bootEnabled() which instantiates + boots
	 * only enabled modules, enforcing dependsOn().
	 */
	public function bootModules(): void {
		$manager = $this->services['module_manager'] ?? null;

		if ( ! $manager instanceof ModuleManager ) {
			return;
		}

		$manager->bootEnabled();

		$this->modules = $manager->enabledModules();
	}

	/**
	 * Get enabled module instances (after bootModules).
	 *
	 * @return array<string, object>
	 */
	public function modules(): array {
		return $this->modules;
	}

	/**
	 * Service locator.
	 *
	 * @param string $id Service id.
	 * @return mixed Service instance.
	 * @throws \RuntimeException If service not found.
	 */
	public function get( string $id ): mixed {
		if ( ! array_key_exists( $id, $this->services ) ) {
			throw new \RuntimeException( esc_html( sprintf( 'RankKernel service not found: %s', $id ) ) );
		}

		return $this->services[ $id ];
	}
}
