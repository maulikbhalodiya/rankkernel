<?php
/**
 * Llms.txt markdown rendering and validation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the curated llms.txt document.
 *
 * The document is an index, not a feed: an H1 site name, a blockquote
 * summary and the curated sections the user wrote as Markdown link lists.
 * The generator validates that every link target is an absolute http(s) URL.
 */
final class LlmsGenerator {
	/**
	 * Render the llms.txt document.
	 *
	 * @param string $siteName Site name.
	 * @param string $summary  Summary.
	 * @param string $content  Curated sections in Markdown.
	 * @return string The result.
	 */
	public function render( string $siteName, string $summary, string $content ): string {
		$out = '# ' . $this->inline( $siteName ) . "\n";

		if ( '' !== trim( $summary ) ) {
			$out .= "\n> " . $this->inline( trim( $summary ) ) . "\n";
		}

		$body = trim( $content );

		if ( '' !== $body ) {
			$out .= "\n" . $body . "\n";
		}

		return rtrim( $out ) . "\n";
	}

	/**
	 * Validate the curated content.
	 *
	 * Every Markdown link target must be absolute and http(s). Lines that
	 * are not list items or headings are ignored.
	 *
	 * @param string $content Curated sections.
	 * @return array{errors: string[], warnings: string[]} The result.
	 */
	public function validate( string $content ): array {
		$errors   = [];
		$warnings = [];
		$seen     = [];

		foreach ( explode( "\n", $content ) as $index => $line ) {
			$line = trim( $line );

			if ( ! str_starts_with( $line, '-' ) ) {
				continue;
			}

			if ( 1 !== preg_match( '/\]\(([^)]+)\)/', $line, $matches ) ) {
				/* translators: %d: line number. */
				$errors[] = sprintf( __( 'Line %d: a list item needs a [title](url) link.', 'rankkernel' ), $index + 1 );
				continue;
			}

			$url = trim( $matches[1] );

			if ( ! RobotsDirectives::isAbsoluteHttpUrl( $url ) ) {
				/* translators: %d: line number. */
				$errors[] = sprintf( __( 'Line %d: the link target must be absolute and http(s).', 'rankkernel' ), $index + 1 );
				continue;
			}

			if ( isset( $seen[ $url ] ) ) {
				/* translators: %d: line number. */
				$warnings[] = sprintf( __( 'Line %d: this URL is already listed above.', 'rankkernel' ), $index + 1 );
			}

			$seen[ $url ] = true;
		}

		return [
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * Collapse a value to one line for the H1 or blockquote.
	 *
	 * @param string $text Raw text.
	 * @return string The result.
	 */
	private function inline( string $text ): string {
		$text = str_replace( [ "\r\n", "\r", "\n" ], ' ', $text );
		$text = (string) preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}
}
