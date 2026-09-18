<?php
/**
 * Consistency checks between robots.txt and llms.txt.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Pure consistency warnings for the Crawl Signals settings screen.
 */
final class CrawlConsistency {
	/**
	 * Collect warnings for an inconsistent robots.txt and llms.txt setup.
	 *
	 * @param string[] $presets     Enabled AI crawler preset slugs.
	 * @param bool     $llmsEnabled Whether llms.txt is on.
	 * @param string   $robotsText  Effective robots.txt block.
	 * @return string[] The result.
	 */
	public static function warnings( array $presets, bool $llmsEnabled, string $robotsText ): array {
		if ( ! $llmsEnabled ) {
			return [];
		}

		$warnings = [];

		if ( [] !== $presets ) {
			$warnings[] = __( 'llms.txt is on while one or more AI crawlers are blocked in robots.txt, so those crawlers cannot read it.', 'rankkernel' );
		}

		if ( self::wildcardDisallowsAll( $robotsText ) ) {
			$warnings[] = __( 'robots.txt disallows the whole site, so crawlers cannot reach the llms.txt URL.', 'rankkernel' );
		}

		return $warnings;
	}

	/**
	 * Whether a wildcard user-agent group disallows the whole site.
	 *
	 * @param string $robotsText Robots.txt block.
	 * @return bool The result.
	 */
	private static function wildcardDisallowsAll( string $robotsText ): bool {
		$inWildcard = false;

		foreach ( explode( "\n", $robotsText ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			$position = strpos( $line, ':' );

			if ( false === $position ) {
				continue;
			}

			$name  = strtolower( trim( substr( $line, 0, $position ) ) );
			$value = trim( substr( $line, $position + 1 ) );

			if ( 'user-agent' === $name ) {
				$inWildcard = ( '*' === $value );
				continue;
			}

			if ( $inWildcard && 'disallow' === $name && '/' === $value ) {
				return true;
			}
		}

		return false;
	}
}
