<?php
/**
 * Redirects module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;

/**
 * Redirects module, default off.
 *
 * Register wires services with no hooks: it ensures the table and seeds the
 * settings option. It never invalidates the match cache, so the frontend
 * lookup stays cache first across requests. Boot registers frontend hooks
 * only when the module is enabled. Disabled means no class work and zero
 * hooks, proven by test.
 */
class RedirectsModule implements ModuleInterface {
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
	 * Redirector instance, built at boot.
	 *
	 * @var Redirector|null
	 */
	private ?Redirector $redirector = null;

	/**
	 * Slug watcher instance, built at boot.
	 *
	 * @var SlugWatcher|null
	 */
	private ?SlugWatcher $slugWatcher = null;

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
		return 'redirects';
	}

	/**
	 * Get human readable name.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return __( 'Redirects', 'rankkernel' );
	}

	/**
	 * Module priority.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 40;
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
			$this->enabledCache = $this->enableMap->isEnabled( 'redirects' );

			return $this->enabledCache;
		}

		$map = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		if ( array_key_exists( 'redirects', $map ) ) {
			$this->enabledCache = (bool) $map['redirects'];
		} else {
			$this->enabledCache = in_array( 'redirects', $map, true );
		}

		return $this->enabledCache;
	}

	/**
	 * Wire services, no hooks.
	 *
	 * Ensures the table, seeds settings, and flags schema readiness. It must
	 * not invalidate the match cache, because invalidation belongs to rule and
	 * setting writes. Bumping the validator here would retire the cache on
	 * every request and defeat the cache first frontend lookup.
	 */
	public function register(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		RedirectTable::ensureTables();

		$settings = new RedirectsSettings();
		$settings->ensureSchema();

		if ( RedirectTable::exists() ) {
			$settings->set( [ 'schema_ok' => true ] );
		}
	}

	/**
	 * Boot hooks, only when enabled.
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$redirector = new Redirector();

		$this->redirector = $redirector;

		$redirector->register();

		$watcher = new SlugWatcher();

		$this->slugWatcher = $watcher;

		$watcher->register();
	}

	/**
	 * Get the redirector, for testing.
	 *
	 * @return Redirector|null The result.
	 */
	public function getRedirector(): ?Redirector {
		return $this->redirector;
	}

	/**
	 * Get the slug watcher, for testing.
	 *
	 * @return SlugWatcher|null The result.
	 */
	public function getSlugWatcher(): ?SlugWatcher {
		return $this->slugWatcher;
	}
}
