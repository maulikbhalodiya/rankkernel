<?php
/**
 * Module manager, the hard gate.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules;

/**
 * Manages module registration, evaluation, and boot.
 */
final class ModuleManager {
    /** @var array<string, ModuleInterface> */
    private array $registry = [];

    /** @var array<string, bool> */
    private array $enabledMap = [];

    /**
     * Whether the enable map has been warmed.
     */
    private bool $evaluated = false;

    /**
     * Shared enable-map holder (single get_option per request).
     */
    private ?ModuleEnableMap $enableMapSource;

    /**
     * Constructor.
     *
     * @param ModuleEnableMap|null $enableMap Optional shared enable map.
     */
    public function __construct( ?ModuleEnableMap $enableMap = null ) {
        $this->enableMapSource = $enableMap;
    }

    /**
     * Inject / replace the shared enable map.
     */
    public function setEnableMap( ModuleEnableMap $map ): void {
        $this->enableMapSource = $map;
    }

    /**
     * Register a module.
     */
    public function register( ModuleInterface $module ): void {
        $this->registry[ $module->getId() ] = $module;
    }

    /**
     * Evaluate all registered modules' enabled state ONCE and cache the result.
     *
     * When a shared ModuleEnableMap is injected, delegation is via the map
     * (zero additional get_option calls). Without a map, falls back to
     * calling ModuleInterface::isEnabled() once per module and caching.
     */
    public function evaluateAll(): void {
        if ($this->evaluated) {
            return;
        }

        $this->evaluated = true;

        foreach ($this->registry as $id => $module) {
            // Numeric string ids such as 404 arrive as int keys, normalize once.
            $key = (string) $id;

            if (null !== $this->enableMapSource) {
                $this->enabledMap[ $key ] = $this->enableMapSource->isEnabled($key);
            } else {
                $this->enabledMap[ $key ] = $module->isEnabled();
            }
        }
    }

    /**
     * Boot only enabled modules, enforcing dependsOn().
     *
     * Modules are sorted by getPriority() ascending before boot.
     * A module whose dependency is not enabled is forced OFF and an action
     * rankkernel/module/force_disabled is fired with (moduleId, missingDependency).
     */
    public function bootEnabled(): void {
        if (! $this->evaluated) {
            $this->evaluateAll();
        } else {
            foreach ($this->registry as $id => $module) {
                // Numeric string ids such as 404 arrive as int keys, normalize once.
                $key = (string) $id;

                if (! array_key_exists($key, $this->enabledMap)) {
                    if (null !== $this->enableMapSource) {
                        $this->enabledMap[ $key ] = $this->enableMapSource->isEnabled($key);
                    } else {
                        $this->enabledMap[ $key ] = $module->isEnabled();
                    }
                }
            }
        }

        // Sort registry by priority ascending.
        $sorted = $this->registry;
        uasort(
            $sorted,
            static fn( ModuleInterface $a, ModuleInterface $b ): int => $a->getPriority() <=> $b->getPriority()
        );

        foreach ($sorted as $id => $module) {
            // Numeric string ids such as 404 arrive as int keys, normalize once.
            $key = (string) $id;

            if (! $this->isOn($key)) {
                continue;
            }

            $missing = null;
            foreach ($module->dependsOn() as $depId) {
                if (! $this->isOn($depId)) {
                    $missing = $depId;
                    break;
                }
            }

            if (null !== $missing) {
                $this->enabledMap[ $key ] = false;
                /**
                 * Fires when a module is force-disabled due to a missing dependency.
                 *
                 * @param string $moduleId          The module that was disabled.
                 * @param string $missingDependency The missing dependency id.
                 */
                do_action('rankkernel/module/force_disabled', $key, $missing);
                continue;
            }

            $module->register();
            $module->boot();
        }
    }

    /**
     * Check whether a module id is enabled (reads cached map, never re-queries).
     */
    public function isOn( string $id ): bool {
        return $this->enabledMap[ $id ] ?? false;
    }

    /**
     * Get a registered module by id, or null if not found.
     */
    public function get( string $id ): ?ModuleInterface {
        return $this->registry[ $id ] ?? null;
    }

    /**
     * Get enabled module instances only, in boot (priority) order.
     *
     * @return array<string, ModuleInterface>
     */
    public function enabledModules(): array {
        $sorted = $this->registry;
        uasort(
            $sorted,
            static fn( ModuleInterface $a, ModuleInterface $b ): int => $a->getPriority() <=> $b->getPriority()
        );

        $out = [];
        foreach ($sorted as $id => $module) {
            // Numeric string ids such as 404 arrive as int keys, normalize once.
            $key = (string) $id;

            if ($this->isOn($key)) {
                $out[ $key ] = $module;
            }
        }

        return $out;
    }
}
