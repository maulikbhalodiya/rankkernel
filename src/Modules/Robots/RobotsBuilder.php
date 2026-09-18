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
 * Builds the final robots.txt from core output, crawler policy and custom rules.
 *
 * The Sitemap line is owned by the Sitemaps module. When the custom mode
 * replaces the core output, the existing Sitemap lines are preserved so the
 * one owner is never lost.
 */
final class RobotsBuilder {
	/**
	 * Build the robots.txt output.
	 *
	 * When an override is set it becomes the whole document unchanged.
	 * Otherwise the crawler policy is inserted above the wildcard group of
	 * the core output, which keeps the Sitemaps module Sitemap line.
	 *
	 * @param string                $base     Core robots.txt output.
	 * @param bool                  $isPublic Whether the site is public.
	 * @param array<string, string> $policies Crawler slug to policy map.
	 * @param string                $override Custom override, the whole document.
	 * @return string The result.
	 */
	public function build( string $base, bool $isPublic, array $policies, string $override ): string {
		if ( ! $isPublic ) {
			return $base;
		}

		if ( '' !== trim( $override ) ) {
			return rtrim( trim( $override ) ) . "\n";
		}

		$text  = rtrim( $base );
		$block = $this->crawlerBlock( $policies );

		if ( '' !== $block ) {
			$text = $this->insertAboveWildcard( $text, $block );
		}

		$text = trim( $text );

		return ( '' === $text ) ? '' : $text . "\n";
	}

	/**
	 * Crawler directive groups for blocked and explicitly allowed crawlers.
	 *
	 * @param array<string, string> $policies Crawler slug to policy map.
	 * @return string The result.
	 */
	private function crawlerBlock( array $policies ): string {
		$groups = [];

		foreach ( CrawlerPolicy::all() as $slug => $crawler ) {
			$slug   = (string) $slug;
			$policy = CrawlerPolicy::sanitizePolicy( $policies[ $slug ] ?? CrawlerPolicy::CUSTOM );
			$token  = (string) $crawler['token'];

			if ( CrawlerPolicy::BLOCK === $policy ) {
				$groups[] = 'User-agent: ' . $token . "\nDisallow: /";
			} elseif ( CrawlerPolicy::ALLOW === $policy ) {
				$groups[] = 'User-agent: ' . $token . "\nAllow: /";
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
