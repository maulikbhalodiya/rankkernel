<?php
/**
 * Llms.txt markdown rendering.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the llms.txt markdown from collected sections.
 *
 * Pure formatting. Titles and excerpts have the Markdown control
 * characters []():# escaped so content cannot inject structure.
 */
final class LlmsGenerator {
	/**
	 * Render the llms.txt document.
	 *
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @param string                           $siteName Site name.
	 * @param string                           $summary  Summary.
	 * @return string The result.
	 */
	public function render( array $sections, string $siteName, string $summary ): string {
		$out = '# ' . $this->escape( $siteName ) . "\n";

		if ( '' !== trim( $summary ) ) {
			$out .= "\n> " . $this->escape( trim( $summary ) ) . "\n";
		}

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$title = isset( $section['title'] ) ? (string) $section['title'] : '';
			$items = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : [];

			if ( '' === $title || [] === $items ) {
				continue;
			}

			$out .= "\n## " . $this->escape( $title ) . "\n";

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$itemTitle = isset( $item['title'] ) ? (string) $item['title'] : '';
				$url       = isset( $item['url'] ) ? (string) $item['url'] : '';
				$excerpt   = isset( $item['excerpt'] ) ? (string) $item['excerpt'] : '';

				if ( '' === $itemTitle || '' === $url ) {
					continue;
				}

				$line = '- [' . $this->escape( $itemTitle ) . '](' . $this->escapeUrl( $url ) . ')';

				if ( '' !== $excerpt ) {
					$line .= ': ' . $this->escape( $excerpt );
				}

				$out .= $line . "\n";
			}
		}

		return rtrim( $out ) . "\n";
	}

	/**
	 * Escape Markdown control characters in text.
	 *
	 * @param string $text Raw text.
	 * @return string The result.
	 */
	public function escape( string $text ): string {
		return (string) preg_replace( '/([\\\\\[\]\(\)#])/', '\\\\$1', $text );
	}

	/**
	 * Escape parentheses in a URL so it stays a valid Markdown link target.
	 *
	 * @param string $url URL.
	 * @return string The result.
	 */
	private function escapeUrl( string $url ): string {
		return str_replace( [ '(', ')' ], [ '%28', '%29' ], $url );
	}
}
