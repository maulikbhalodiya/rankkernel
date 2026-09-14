<?php
/**
 * 404 Monitor module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;

/**
 * 404 Monitor module, default off.
 *
 * Register wires services with no hooks: it ensures the log table and seeds
 * the settings option. Boot registers frontend hooks only when the module is
 * enabled. Disabled means no class work and zero hooks, proven by test.
 */
class MonitorModule implements ModuleInterface {
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
	 * Logger instance, built at boot.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

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
		return '404';
	}

	/**
	 * Get human readable name.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return __( '404 Monitor', 'rankkernel' );
	}

	/**
	 * Module priority.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 50;
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
			$this->enabledCache = $this->enableMap->isEnabled( '404' );

			return $this->enabledCache;
		}

		$map = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		if ( array_key_exists( '404', $map ) ) {
			$this->enabledCache = (bool) $map['404'];
		} else {
			$this->enabledCache = in_array( '404', $map, true );
		}

		return $this->enabledCache;
	}

	/**
	 * Wire services, no hooks.
	 *
	 * Ensures the log table and seeds the settings option.
	 */
	public function register(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		LogTable::ensureTables();

		$settings = new MonitorSettings();
		$settings->ensureSchema();
	}

	/**
	 * Boot hooks, only when enabled.
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$logger = new Logger();

		$this->logger = $logger;

		$logger->register();
	}

	/**
	 * Get the logger, for testing.
	 *
	 * @return Logger|null The result.
	 */
	public function getLogger(): ?Logger {
		return $this->logger;
	}
}
