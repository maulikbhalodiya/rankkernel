<?php
/**
 * 404 Monitor settings, own option with autoload disabled.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Monitor;

/**
 * Manages the rankkernel_404_settings option.
 *
 * Follows the SettingsStore pattern (whitelisted keys, sanitized writes) but
 * keeps a separate option with autoload disabled, so monitor settings never
 * bloat the autoloaded options row. Growth bounds cannot be disabled: empty
 * or zero retention and row limits are clamped into their allowed ranges, so
 * the log can never grow without limit.
 */
final class MonitorSettings {
	/**
	 * Option name.
	 */
	public const OPTION = 'rankkernel_404_settings';

	/**
	 * Whitelisted setting keys.
	 *
	 * @var string[]
	 */
	private const ALLOWED_KEYS = [
		'advanced_fields',
		'retention_days',
		'max_rows',
		'flood_budget',
		'flood_window',
		'ignore_query',
		'exclusions',
	];

	/**
	 * Maximum stored exclusion rules, keeps the per request scan bounded.
	 */
	public const MAX_EXCLUSIONS = 200;

	/**
	 * Maximum length of one exclusion value.
	 */
	public const MAX_EXCLUSION_LENGTH = 500;

	/**
	 * Cached merged settings, defaults plus stored values.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Default settings.
	 *
	 * Retention defaults to 30 days, the log defaults to 1000 rows, the flood
	 * budget defaults to 50 new URIs per 300 second window, query strings are
	 * ignored by default, advanced fields stay off by default, and no URIs
	 * are excluded by default.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'advanced_fields' => false,
			'retention_days'  => 30,
			'max_rows'        => 1000,
			'flood_budget'    => 50,
			'flood_window'    => 300,
			'ignore_query'    => true,
			'exclusions'      => [],
		];
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback when the key is missing.
	 * @return mixed Setting value or the fallback.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $fallback;
	}

	/**
	 * Get all merged settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_option( self::OPTION, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$this->cache = array_merge( self::defaults(), $stored );

		return $this->cache;
	}

	/**
	 * Seed the option with defaults when it is missing.
	 */
	public function ensureSchema(): void {
		$stored = get_option( self::OPTION, null );

		if ( ! is_array( $stored ) ) {
			update_option( self::OPTION, self::defaults(), false );
			$this->cache = self::defaults();

			return;
		}

		$this->cache = array_merge( self::defaults(), $stored );
	}

	/**
	 * Update settings with a partial array, whitelisted keys only.
	 *
	 * @param array<string, mixed> $partial Partial settings to merge.
	 * @return bool Whether anything was written.
	 */
	public function set( array $partial ): bool {
		$sanitized = [];

		foreach ( $partial as $key => $value ) {
			if ( ! in_array( $key, self::ALLOWED_KEYS, true ) ) {
				continue;
			}

			$sanitized[ $key ] = $this->sanitize( $key, $value );
		}

		if ( [] === $sanitized ) {
			return false;
		}

		$merged = array_merge( $this->all(), $sanitized );

		update_option( self::OPTION, $merged, false );

		$this->cache = $merged;

		return true;
	}

	/**
	 * Retention window in days, always inside 1 to 365.
	 *
	 * @return int The result.
	 */
	public function getRetentionDays(): int {
		return (int) $this->get( 'retention_days', 30 );
	}

	/**
	 * Maximum log rows, always inside 100 to 10000.
	 *
	 * @return int The result.
	 */
	public function getMaxRows(): int {
		return (int) $this->get( 'max_rows', 1000 );
	}

	/**
	 * New URI budget per flood window.
	 *
	 * @return int The result.
	 */
	public function getFloodBudget(): int {
		return (int) $this->get( 'flood_budget', 50 );
	}

	/**
	 * Flood window in seconds.
	 *
	 * @return int The result.
	 */
	public function getFloodWindow(): int {
		return (int) $this->get( 'flood_window', 300 );
	}

	/**
	 * Whether the query string is ignored when logging.
	 *
	 * @return bool The result.
	 */
	public function isIgnoreQuery(): bool {
		return (bool) $this->get( 'ignore_query', true );
	}

	/**
	 * Whether referer and user agent capture is enabled.
	 *
	 * @return bool The result.
	 */
	public function isAdvancedFields(): bool {
		return (bool) $this->get( 'advanced_fields', false );
	}

	/**
	 * Exclusion rules, sanitized comparator plus value pairs.
	 *
	 * @return array<int, array{comparator: string, value: string}>
	 */
	public function getExclusions(): array {
		$stored = $this->get( 'exclusions', [] );

		if ( ! is_array( $stored ) ) {
			return [];
		}

		return $this->sanitizeExclusions( $stored );
	}

	/**
	 * Sanitize a single value by key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize( string $key, mixed $value ): mixed {
		if ( 'retention_days' === $key ) {
			$days = (int) $value;

			if ( $days < 1 ) {
				return 1;
			}

			if ( $days > 365 ) {
				return 365;
			}

			return $days;
		}

		if ( 'max_rows' === $key ) {
			$rows = (int) $value;

			if ( $rows < 100 ) {
				return 100;
			}

			if ( $rows > 10000 ) {
				return 10000;
			}

			return $rows;
		}

		if ( 'flood_budget' === $key ) {
			$budget = (int) $value;

			if ( $budget < 1 ) {
				return 1;
			}

			if ( $budget > 1000 ) {
				return 1000;
			}

			return $budget;
		}

		if ( 'flood_window' === $key ) {
			$window = (int) $value;

			if ( $window < 60 ) {
				return 60;
			}

			if ( $window > 3600 ) {
				return 3600;
			}

			return $window;
		}

		if ( 'exclusions' === $key ) {
			if ( ! is_array( $value ) ) {
				return [];
			}

			return $this->sanitizeExclusions( $value );
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		$normalized = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		if ( null !== $normalized ) {
			return $normalized;
		}

		return (bool) $value;
	}

	/**
	 * Keep only well formed exclusion rules, bounded in count and length.
	 *
	 * @param array<mixed> $rules Raw rules.
	 * @return array<int, array{comparator: string, value: string}>
	 */
	private function sanitizeExclusions( array $rules ): array {
		$clean = [];

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$comparator = isset( $rule['comparator'] ) ? (string) $rule['comparator'] : '';
			$value      = isset( $rule['value'] ) ? trim( (string) $rule['value'] ) : '';

			if ( ! Exclusions::isComparator( $comparator ) ) {
				continue;
			}

			if ( '' === $value ) {
				continue;
			}

			if ( strlen( $value ) > self::MAX_EXCLUSION_LENGTH ) {
				$value = substr( $value, 0, self::MAX_EXCLUSION_LENGTH );
			}

			$clean[] = [
				'comparator' => $comparator,
				'value'      => $value,
			];

			if ( count( $clean ) >= self::MAX_EXCLUSIONS ) {
				break;
			}
		}

		return $clean;
	}
}
