<?php
/**
 * Module enable-map holder — single get_option('rankkernel_modules') per request.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules;

/**
 * Holds the normalized enable map; performs THE one option read.
 */
final class ModuleEnableMap {
    /**
     * Raw option value.
     *
     * @var mixed[]
     */
    private array $raw;

    /**
     * Normalized enabled ids set.
     *
     * @var array<string, bool>
     */
    private array $enabledSet = [];

    /**
     * Constructor — performs the single get_option read for the request.
     */
    public function __construct() {
        $map = get_option('rankkernel_modules', []);

        if (! is_array($map)) {
            $map = [];
        }

        $this->raw = $map;

        foreach ($map as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && '' !== $value) {
                    $this->enabledSet[ $value ] = true;
                }
            } elseif (is_string($key) && '' !== $key) {
                $this->enabledSet[ $key ] = (bool) $value;
            }
        }
    }

    /**
     * Whether a module id is enabled.
     */
    public function isEnabled( string $id ): bool {
        return $this->enabledSet[ $id ] ?? false;
    }

    /**
     * Raw option value (as stored).
     *
     * @return mixed[]
     */
    public function raw(): array {
        return $this->raw;
    }

    /**
     * Normalized enabled id set.
     *
     * @return array<string, bool>
     */
    public function all(): array {
        return $this->enabledSet;
    }
}
