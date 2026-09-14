<?php
/**
 * Metadata module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Metadata;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;
use RankKernel\Settings\SettingsStore;

/**
 * Metadata Engine, flagship module.
 */
final class MetadataModule implements ModuleInterface {
	/**
	 * Cached enabled check (delegates to shared map if injected).
	 *
	 * @var bool|null
	 */
	private ?bool $enabledCache = null;

	/**
	 * Settings store.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $settings;

	/**
	 * Shared enable-map holder (single get_option per request).
	 *
	 * @var ModuleEnableMap|null
	 */
	private ?ModuleEnableMap $enableMap;

	/**
	 * Constructor.
	 *
	 * @param SettingsStore|null   $settings  Optional settings store.
	 * @param ModuleEnableMap|null $enableMap Optional shared enable map.
	 */
	public function __construct( ?SettingsStore $settings = null, ?ModuleEnableMap $enableMap = null ) {
		$this->settings  = $settings ?? new SettingsStore();
		$this->enableMap = $enableMap;
	}

	/**
	 * Get module id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'metadata';
	}

	/**
	 * Get human-readable name.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return __( 'Metadata Engine', 'rankkernel' );
	}

	/**
	 * Module priority.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 10;
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
	 * Whether the module is enabled (delegates to shared map if injected).
	 *
	 * @return bool The result.
	 */
	public function isEnabled(): bool {
		if ( null !== $this->enabledCache ) {
			return $this->enabledCache;
		}

		if ( null !== $this->enableMap ) {
			$this->enabledCache = $this->enableMap->isEnabled( 'metadata' );

			return $this->enabledCache;
		}

		$map = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		// Support both associative map and indexed list (activation seed is list).
		if ( array_key_exists( 'metadata', $map ) ) {
			$this->enabledCache = (bool) $map['metadata'];
		} else {
			$this->enabledCache = in_array( 'metadata', $map, true );
		}

		return $this->enabledCache;
	}

	/**
	 * Register meta keys (no hooks yet).
	 */
	public function register(): void {
		register_meta(
			'post',
			'_rankkernel_meta_data',
			[
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => MetaPayload::restSchema(),
				],
				'sanitize_callback' => [ MetaPayload::class, 'sanitize' ],
				'auth_callback'     => static fn ( mixed $value, string $meta_key, int $object_id ): bool
					=> current_user_can( 'edit_post', $object_id ),
			]
		);

		// Term payload = post shape minus post-only fields (schema module not needed here).
		register_meta(
			'term',
			'_rankkernel_term_data',
			[
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => MetaPayload::restSchema(),
				],
				'sanitize_callback' => [ MetaPayload::class, 'sanitize' ],
				'auth_callback'     => static fn ( mixed $value, string $meta_key, int $object_id ): bool
					=> current_user_can( 'edit_term', $object_id ),
			]
		);

		// Per-user prefs (module-off notices, AI prefs later); permissive schema.
		register_meta(
			'user',
			'_rankkernel_user_prefs',
			[
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => MetaPayload::userPrefsSchema(),
				],
				'sanitize_callback' => [ MetaPayload::class, 'sanitize' ],
				'auth_callback'     => static fn ( mixed $value, string $meta_key, int $object_id ): bool
					=> current_user_can( 'edit_user', $object_id ),
			]
		);
	}

	/**
	 * Boot hooks (only if enabled, caller enforces).
	 */
	public function boot(): void {
		$renderer = new HeadRenderer( $this->settings );
		$renderer->boot();
	}
}
