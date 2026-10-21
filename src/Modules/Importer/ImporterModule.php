<?php
/**
 * Importer module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Rest\ImportController;
use RankKernel\Settings\SettingsStore;

/**
 * Settings importer, reads Yoast SEO, Rank Math and AIOSEO data in.
 *
 * Register wires services with no hooks and boot adds one: the chunked
 * run endpoint on rest_api_init. The module owns zero front end or
 * admin hooks by design. Its screen is wired through AdminMenu like
 * every other page. Disabled means no class work and zero hooks,
 * proven by test.
 */
class ImporterModule implements ModuleInterface {
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
		return 'importer';
	}

	/**
	 * Get human readable name.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return 'Importer';
	}

	/**
	 * Module priority.
	 *
	 * Late boot: the importer only reads other plugins' data on its own
	 * screen, so it yields to every rendering module.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 60;
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
			$this->enabledCache = $this->enableMap->isEnabled( 'importer' );

			return $this->enabledCache;
		}

		$map = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		if ( array_key_exists( 'importer', $map ) ) {
			$this->enabledCache = (bool) $map['importer'];
		} else {
			$this->enabledCache = in_array( 'importer', $map, true );
		}

		return $this->enabledCache;
	}

	/**
	 * Wire services, no hooks.
	 *
	 * The importer needs no tables and no settings schema: it reads
	 * source plugins and writes through existing module storage.
	 */
	public function register(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}
	}

	/**
	 * Boot hooks, only when enabled.
	 *
	 * Exactly one hook: the chunked import endpoint on rest_api_init.
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );
	}

	/**
	 * Register the importer REST routes with source readers attached.
	 *
	 * The runner factory closes over the same readers so the run path
	 * re-derives exactly what Detect and Preview measured.
	 */
	public function registerRestRoutes(): void {
		$readers = [
			'yoast'     => new YoastReader(),
			'rank-math' => new RankMathReader(),
			'aioseo'    => new AioseoReader(),
		];

		// One lock for every source: two runs would fight over the same
		// destinations, because a redirect or a meta row belongs to the
		// site and not to the plugin it came from.
		$lock = new ImportLock();

		$controller = new ImportController(
			$readers,
			static function () use ( $readers, $lock ): ImportRunner {
				return new ImportRunner( $readers, new RedirectRepository(), new SettingsStore(), null, $lock );
			},
			$lock
		);
		$controller->registerRoutes();
	}
}
