<?php
/**
 * Breadcrumbs module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;

/**
 * Breadcrumbs module, default on.
 *
 * Register wires the settings with no hooks. Boot registers the schema
 * trail filter only when enabled, so a disabled module adds zero hooks
 * and BreadcrumbPiece keeps its home plus current fallback. This module
 * never emits JSON-LD itself; BreadcrumbPiece stays the only
 * BreadcrumbList emitter.
 */
class BreadcrumbsModule implements ModuleInterface {
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
	 * Module settings.
	 *
	 * @var BreadcrumbsSettings
	 */
	private BreadcrumbsSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param ModuleEnableMap|null     $enableMap Optional shared enable map.
	 * @param BreadcrumbsSettings|null $settings  Optional settings (tests).
	 */
	public function __construct( ?ModuleEnableMap $enableMap = null, ?BreadcrumbsSettings $settings = null ) {
		$this->enableMap = $enableMap;
		$this->settings  = $settings ?? new BreadcrumbsSettings();
	}

	/**
	 * Get module id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'breadcrumbs';
	}

	/**
	 * Get human readable name.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return __( 'Breadcrumbs', 'rankkernel' );
	}

	/**
	 * Module priority, after schema.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 35;
	}

	/**
	 * Dependencies, needs the metadata Context.
	 *
	 * @return string[] The result.
	 */
	public function dependsOn(): array {
		return [ 'metadata' ];
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
			$this->enabledCache = $this->enableMap->isEnabled( 'breadcrumbs' );

			return $this->enabledCache;
		}

		$map = get_option( 'rankkernel_modules', [] );

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		if ( array_key_exists( 'breadcrumbs', $map ) ) {
			$this->enabledCache = (bool) $map['breadcrumbs'];
		} else {
			$this->enabledCache = in_array( 'breadcrumbs', $map, true );
		}

		return $this->enabledCache;
	}

	/**
	 * Wire the settings option, no hooks.
	 */
	public function register(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$this->settings->ensureSchema();
	}

	/**
	 * Boot hooks, only when enabled.
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		add_filter( 'rankkernel/schema/breadcrumb_trail', [ $this, 'filterBreadcrumbTrail' ], 10, 2 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
	}

	/**
	 * Build the canonical trail for a context.
	 *
	 * Shared entry point for the schema adapter now and the visible
	 * renderer in a later phase.
	 *
	 * @param Context $ctx Request context.
	 * @return Item[] Ordered trail.
	 */
	public function buildTrail( Context $ctx ): array {
		return ( new TrailBuilder( $ctx, $this->settings ) )->build();
	}

	/**
	 * Map the canonical trail onto the schema filter shape.
	 *
	 * Drops visible only items (pagination, flagged schema_excluded)
	 * and maps the rest to name and url entries for BreadcrumbPiece.
	 * An empty builder trail keeps the incoming fallback untouched.
	 * Never emits JSON-LD.
	 *
	 * @param mixed $trail Incoming trail entries.
	 * @param mixed $ctx   Request context.
	 * @return array<int, array{name: string, url: string}> Schema trail.
	 */
	public function filterBreadcrumbTrail( mixed $trail, mixed $ctx ): array {
		if ( ! $ctx instanceof Context ) {
			return $this->sanitizeIncoming( $trail );
		}

		$items = $this->buildTrail( $ctx );

		if ( [] === $items ) {
			return $this->sanitizeIncoming( $trail );
		}

		$out = [];

		foreach ( $items as $item ) {
			if ( $item->schemaExcluded() ) {
				continue;
			}

			$out[] = [
				'name' => $item->label(),
				'url'  => $item->url(),
			];
		}

		return $out;
	}

	/**
	 * Sanitize an incoming trail into the schema shape.
	 *
	 * Keeps name and url entries only, so the piece fallback survives
	 * third party input intact.
	 *
	 * @param mixed $trail Incoming trail entries.
	 * @return array<int, array{name: string, url: string}> Schema trail.
	 */
	private function sanitizeIncoming( mixed $trail ): array {
		if ( ! is_array( $trail ) ) {
			return [];
		}

		$out = [];

		foreach ( $trail as $crumb ) {
			if ( ! is_array( $crumb ) ) {
				continue;
			}

			$name = isset( $crumb['name'] ) ? trim( (string) $crumb['name'] ) : '';
			$url  = isset( $crumb['url'] ) ? trim( (string) $crumb['url'] ) : '';

			if ( '' === $name && '' === $url ) {
				continue;
			}

			$out[] = [
				'name' => $name,
				'url'  => $url,
			];
		}

		return $out;
	}
}
