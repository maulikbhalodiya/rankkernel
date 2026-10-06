<?php
/**
 * Shared optional-module registry.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical list of optional modules and helpers.
 */
final class ModuleRegistry {
	/**
	 * Map of module id => human label.
	 *
	 * @var array<string|int, string>
	 */
	public const MODULES = [
		'metadata'         => 'Metadata Engine',
		'analysis'         => 'Content Analysis',
		'sitemaps'         => 'XML Sitemaps',
		'schema'           => 'Schema (JSON-LD)',
		'breadcrumbs'      => 'Breadcrumbs',
		'redirects'        => 'Redirects',
		'404'              => '404 Monitor',
		'instant-indexing' => 'Instant Indexing (IndexNow)',
		'robots'           => 'Robots.txt & .htaccess',
		'image-seo'        => 'Image SEO',
		'gutenberg'        => 'Gutenberg Suite',
		'ai'               => 'AI Suite (BYO Key)',
		'headless'         => 'Headless',
	];

	/**
	 * Module ids that are planned: visible to the owner as coming soon,
	 * but never toggleable and never booted, even if present in the option.
	 *
	 * @var string[]
	 */
	public const PLANNED = [ 'image-seo', 'gutenberg', 'ai', 'headless' ];

	/**
	 * Get all modules as id => label.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return self::MODULES;
	}

	/**
	 * Get all module ids.
	 *
	 * @return string[] The result.
	 */
	public static function ids(): array {
		return array_map( 'strval', array_keys( self::MODULES ) );
	}

	/**
	 * Whether a module id exists.
	 *
	 * @param string $id Id.
	 * @return bool The result.
	 */
	public static function has( string $id ): bool {
		return array_key_exists( $id, self::MODULES );
	}

	/**
	 * Whether a module id is planned (visible but not toggleable).
	 *
	 * @param string $id Id.
	 * @return bool The result.
	 */
	public static function isPlanned( string $id ): bool {
		return in_array( $id, self::PLANNED, true );
	}

	/**
	 * Get label for a module id.
	 *
	 * @throws \InvalidArgumentException If id is unknown.
	 * @param string $id Id.
	 * @return string The result.
	 */
	public static function label( string $id ): string {
		if ( ! array_key_exists( $id, self::MODULES ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Unknown module id: %s', $id ) ) );
		}

		return self::MODULES[ $id ];
	}
}
