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
     */
    public function getId(): string;

    /**
     * Human-readable module name.
     */
    public function getName(): string;

    /**
     * Whether the module is enabled (reads cached settings map).
     */
    public function isEnabled(): bool;

    /**
     * Boot priority, lower values boot first.
     */
    public function getPriority(): int;

    /**
     * Module ids this module depends on.
     *
     * @return string[]
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
