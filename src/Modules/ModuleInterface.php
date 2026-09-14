<?php
/**
 * Module interface.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules;

/**
 * Contract for a RankKernel module.
 */
interface ModuleInterface {
	/**
	 * Unique module identifier (slug).
	 *
	 * @return string The result.
	 */
	public function getId(): string;

	/**
	 * Human-readable module name.
	 *
	 * @return string The result.
	 */
	public function getName(): string;

	/**
	 * Whether the module is enabled (reads cached settings map).
	 *
	 * @return bool The result.
	 */
	public function isEnabled(): bool;

	/**
	 * Boot priority, lower values boot first.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int;

	/**
	 * Module ids this module depends on.
	 *
	 * @return string[] The result.
	 */
	public function dependsOn(): array;

	/**
	 * Wire services (no hooks yet).
	 */
	public function register(): void;

	/**
	 * Register hooks, only if enabled.
	 */
	public function boot(): void;
}
