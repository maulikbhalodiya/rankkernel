<?php
/**
 * Robots.txt output assembly.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the final robots.txt from a base block, AI presets and a sitemap URL.
 */
final class RobotsBuilder {
	/**
	 * Build the robots.txt output.
	 *
	 * Honours the public flag, inserts the AI crawler groups above the
	 * wildcard group and appends at most one sitemap directive.
	 *
	 * @param string   $base       Base block (core output or custom text).
	 * @param bool     $isPublic   Whether the site is public.
	 * @param string[] $presets    Enabled preset slugs.
	 * @param string   $sitemapUrl Optional absolute sitemap URL override.
	 * @return string The result.
	 */
	public function build( string $base, bool $isPublic, array $presets, string $sitemapUrl ): string {
		if ( ! $isPublic ) {
			return $base;
		}

		$text = $base;

		if ( '' !== $sitemapUrl ) {
			$text = (string) preg_replace( '/^Sitemap:.*$/mi', '', $text );
		}

		$text  = trim( $text );
		$block = $this->presetBlock( $presets );

		if ( '' !== $block ) {
			$text = $this->insertAboveWildcard( $text, $block );
		}

		if ( '' !== $sitemapUrl ) {
			$line = 'Sitemap: ' . esc_url( $sitemapUrl );
			$text = ( '' === $text ) ? $line : $text . "\n\n" . $line;
		}

		$text = trim( $text );

		return ( '' === $text ) ? '' : $text . "\n";
	}

	/**
	 * Build the AI crawler group block.
	 *
	 * @param string[] $presets Enabled preset slugs.
	 * @return string The result.
	 */
	private function presetBlock( array $presets ): string {
		$map    = RobotsDirectives::presets();
		$groups = [];

		foreach ( $presets as $slug ) {
			if ( ! isset( $map[ $slug ] ) ) {
				continue;
			}

			$agents = $map[ $slug ]['agents'] ?? null;

			if ( ! is_array( $agents ) ) {
				continue;
			}

			foreach ( $agents as $agent ) {
				$groups[] = 'User-agent: ' . $agent . "\nDisallow: /";
			}
		}

		return implode( "\n\n", $groups );
	}

	/**
	 * Insert a block directly above the wildcard user-agent group.
	 *
	 * @param string $base  Base text.
	 * @param string $block Block to insert.
	 * @return string The result.
	 */
	private function insertAboveWildcard( string $base, string $block ): string {
		$matched = preg_match( '/^User-agent:\s*\*/mi', $base, $matches, PREG_OFFSET_CAPTURE );

		if ( 1 === $matched ) {
			$offset = (int) $matches[0][1];
			$before = rtrim( substr( $base, 0, $offset ) );
			$after  = substr( $base, $offset );
			$head   = ( '' === $before ) ? $block : $before . "\n\n" . $block;

			return $head . "\n\n" . $after;
		}

		return ( '' === $base ) ? $block : $block . "\n\n" . $base;
	}
}
