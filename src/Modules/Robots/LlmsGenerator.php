<?php
/**
 * Llms.txt markdown rendering and validation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

use RankKernel\Modules\Metadata\MetaPayload;

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
	 * Maximum entries listed under one H2 section.
	 */
	public const MAX_ITEMS_PER_SECTION = 20;

	/**
	 * Maximum characters kept from an entry excerpt.
	 */
	public const MAX_EXCERPT_CHARS = 160;

	/**
	 * Post meta key holding the RankKernel metadata payload.
	 */
	private const META_KEY = '_rankkernel_meta_data';

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
	 * Build the automatic sections from published site content.
	 *
	 * ROADMAP 1.2 promises H2 sections per post type and taxonomy with
	 * trimmed, stripped excerpts. This is that builder. It is used only when
	 * the operator has written no curated content, so a hand written document
	 * is never diluted by generated sections.
	 *
	 * Selection excludes attachments, anything not publicly queryable, every
	 * entry whose payload marks it noindex, and any entry whose password
	 * protects it. Counts are capped per section.
	 *
	 * @return string Markdown sections, empty when nothing is selectable.
	 */
	public function autoSections(): string {
		$out = '';

		foreach ( $this->autoPostTypeSections() as $section ) {
			$out .= $section;
		}

		foreach ( $this->autoTaxonomySections() as $section ) {
			$out .= $section;
		}

		return $out;
	}

	/**
	 * One H2 section per public post type.
	 *
	 * @return string[] Markdown sections.
	 */
	private function autoPostTypeSections(): array {
		$sections = [];

		foreach ( $this->publicPostTypes() as $postType ) {
			if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post_type_object' ) ) {
				return $sections;
			}

			$posts = get_posts(
				[
					'post_type'           => $postType,
					'post_status'         => 'publish',
					'posts_per_page'      => self::MAX_ITEMS_PER_SECTION,
					'has_password'        => false,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'suppress_filters'    => false,
					'orderby'             => 'date',
					'order'               => 'DESC',
				]
			);

			if ( ! is_array( $posts ) || [] === $posts ) {
				continue;
			}

			$items = [];

			foreach ( $posts as $post ) {
				$item = $this->postItem( $post );

				if ( '' !== $item ) {
					$items[] = $item;
				}
			}

			if ( [] === $items ) {
				continue;
			}

			$sections[] = $this->section( $this->postTypeLabel( $postType ), $items );
		}

		return $sections;
	}

	/**
	 * One H2 section per public taxonomy that has terms in use.
	 *
	 * @return string[] Markdown sections.
	 */
	private function autoTaxonomySections(): array {
		$sections = [];

		if ( ! function_exists( 'get_taxonomies' ) || ! function_exists( 'get_terms' ) ) {
			return $sections;
		}

		$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

		if ( ! is_array( $taxonomies ) ) {
			return $sections;
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! is_string( $taxonomy ) || 'post_format' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'number'     => self::MAX_ITEMS_PER_SECTION,
				]
			);

			if ( ! is_array( $terms ) ) {
				continue;
			}

			$items = [];

			foreach ( $terms as $candidate ) {
				$item = $this->termItem( $candidate );

				if ( '' !== $item ) {
					$items[] = $item;
				}
			}

			if ( [] === $items ) {
				continue;
			}

			$sections[] = $this->section( $this->taxonomyLabel( $taxonomy ), $items );
		}

		return $sections;
	}

	/**
	 * Public post types that may appear in the document.
	 *
	 * Attachments are excluded because an attachment page is a media wrapper
	 * with no text of its own, matching the sitemap exclusion and the noindex
	 * rule for attachment pages.
	 *
	 * @return string[] Post type names.
	 */
	private function publicPostTypes(): array {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [];
		}

		$types = get_post_types( [ 'public' => true ], 'names' );

		if ( ! is_array( $types ) ) {
			return [];
		}

		$types = array_filter(
			$types,
			static fn ( mixed $type ): bool => is_string( $type ) && 'attachment' !== $type
		);

		/**
		 * Filters the post types listed in the generated llms.txt sections.
		 *
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'rankkernel_llms_post_types', array_values( $types ) );
	}

	/**
	 * Render one taxonomy entry, or an empty string when it must be excluded.
	 *
	 * @param mixed $candidate Term as returned by get_terms().
	 * @return string Markdown list item, empty when excluded.
	 */
	private function termItem( mixed $candidate ): string {
		$term = is_object( $candidate ) ? $candidate : null;

		if ( null === $term || ! property_exists( $term, 'name' ) ) {
			return '';
		}

		$name = trim( (string) $term->name );

		if ( '' === $name || ! function_exists( 'get_term_link' ) ) {
			return '';
		}

		$url = get_term_link( $term );

		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		return '- [' . $this->escapeTitle( $name ) . '](' . $url . ')';
	}

	/**
	 * Render one entry, or an empty string when it must be excluded.
	 *
	 * @param mixed $post Post object.
	 * @return string Markdown list item, empty when excluded.
	 */
	private function postItem( mixed $post ): string {
		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return '';
		}

		$id = (int) $post->ID;

		if ( $id <= 0 ) {
			return '';
		}

		if ( ! function_exists( 'get_permalink' ) || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}

		// A payload that turns indexing off must not be advertised as content.
		$meta = MetaPayload::sanitize( MetaPayload::decodeMetaValue( get_post_meta( $id, self::META_KEY, true ) ) );

		if ( empty( $meta['robots']['index'] ) ) {
			return '';
		}

		$url = get_permalink( $id );

		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$title = isset( $post->post_title ) && is_string( $post->post_title ) ? $post->post_title : '';

		if ( '' === trim( $title ) ) {
			return '';
		}

		$item = '- [' . $this->escapeTitle( $title ) . '](' . $url . ')';
		$note = $this->excerpt( $id );

		return '' !== $note ? $item . ': ' . $note : $item;
	}

	/**
	 * Trimmed, stripped excerpt for a post.
	 *
	 * @param int $id Post id.
	 * @return string Excerpt, empty when unavailable.
	 */
	private function excerpt( int $id ): string {
		if ( ! function_exists( 'get_the_excerpt' ) ) {
			return '';
		}

		$raw = get_the_excerpt( $id );

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return '';
		}

		$text = wp_strip_all_tags( $raw );
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > self::MAX_EXCERPT_CHARS ) {
			return rtrim( mb_substr( $text, 0, self::MAX_EXCERPT_CHARS ), ' .,;:' ) . '...';
		}

		if ( ! function_exists( 'mb_strlen' ) && strlen( $text ) > self::MAX_EXCERPT_CHARS ) {
			return rtrim( substr( $text, 0, self::MAX_EXCERPT_CHARS ), ' .,;:' ) . '...';
		}

		return $text;
	}

	/**
	 * Assemble one H2 section.
	 *
	 * @param string   $label Section heading.
	 * @param string[] $items List items.
	 * @return string Markdown section.
	 */
	private function section( string $label, array $items ): string {
		if ( [] === $items ) {
			return '';
		}

		return "\n## " . $this->escapeTitle( $label ) . "\n\n" . implode( "\n", $items ) . "\n";
	}

	/**
	 * Human readable label for a post type.
	 *
	 * @param string $postType Post type name.
	 * @return string The result.
	 */
	private function postTypeLabel( string $postType ): string {
		if ( ! function_exists( 'get_post_type_object' ) ) {
			return $postType;
		}

		$object = get_post_type_object( $postType );

		if ( is_object( $object ) ) {
			$label = $this->objectLabel( $object );

			if ( '' !== $label ) {
				return $label;
			}
		}

		return $postType;
	}

	/**
	 * Human readable label for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string The result.
	 */
	private function taxonomyLabel( string $taxonomy ): string {
		if ( ! function_exists( 'get_taxonomy' ) ) {
			return $taxonomy;
		}

		$object = get_taxonomy( $taxonomy );

		if ( is_object( $object ) ) {
			$label = $this->objectLabel( $object );

			if ( '' !== $label ) {
				return $label;
			}
		}

		return $taxonomy;
	}

	/**
	 * Singular label from a post type or taxonomy object.
	 *
	 * @param object $subject Post type or taxonomy object.
	 * @return string The label, empty when unavailable.
	 */
	private function objectLabel( object $subject ): string {
		if ( ! property_exists( $subject, 'labels' ) || ! is_object( $subject->labels ) ) {
			return '';
		}

		$labels = $subject->labels;

		if ( ! property_exists( $labels, 'name' ) ) {
			return '';
		}

		$name = $labels->name;

		return is_string( $name ) ? trim( $name ) : '';
	}

	/**
	 * Escape the Markdown characters that would break a link label.
	 *
	 * @param string $text Raw text.
	 * @return string The result.
	 */
	private function escapeTitle( string $text ): string {
		$text = str_replace( [ "\r\n", "\r", "\n" ], ' ', $text );

		return str_replace( [ '[', ']', '(', ')' ], [ '\\[', '\\]', '\\(', '\\)' ], trim( $text ) );
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
