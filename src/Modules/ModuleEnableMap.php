<?php
/**
 * Module enable-map holder, single get_option('rankkernel_modules') per request.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules;

defined( 'ABSPATH' ) || exit;

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
	 * Normalize a stored value to a clean list of enabled ids.
	 *
	 * Both historic shapes are accepted: a plain list of ids, and an
	 * id => bool map. Falsy map entries stay disabled, and anything that is
	 * not a non-empty id is dropped. Writers must go through this so the two
	 * toggle paths can never disagree on what the option means.
	 *
	 * @param mixed $raw Stored option value.
	 * @return array<int, string> Enabled ids.
	 */
	public static function normalizeList( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$ids = [];

		foreach ( $raw as $key => $value ) {
			if ( is_int( $key ) ) {
				if ( is_string( $value ) && '' !== $value ) {
					$ids[] = $value;
				}
			} elseif ( is_string( $key ) && '' !== $key && $value ) {
				$ids[] = $key;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Constructor, performs the single get_option read for the request.
	 */
	public function __construct() {
		$map = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		$this->raw = $map;

		foreach ( self::normalizeList( $map ) as $id ) {
			$this->enabledSet[ $id ] = true;
		}
	}

	/**
	 * Whether a module id is enabled.
	 *
	 * @param string $id Id.
	 * @return bool The result.
	 */
	public function isEnabled( string $id ): bool {
		if ( ModuleRegistry::isPlanned( $id ) ) {
			return false;
		}

		return $this->enabledSet[ $id ] ?? false;
	}

	/**
	 * Raw option value (as stored).
	 *
	 * @return array<string, bool>
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
