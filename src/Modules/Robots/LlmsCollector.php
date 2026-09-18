<?php
/**
 * Llms.txt content selection.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Selects indexable, public content for llms.txt.
 *
 * Excludes noindex, private, password protected and attachment content,
 * caps each section and trims the excerpt at a word boundary.
 */
final class LlmsCollector {
	/**
	 * Post meta key holding the RankKernel metadata payload.
	 */
	public const META_KEY = '_rankernel_meta_data';

	/**
	 * Collect sections for the llms.txt document.
	 *
	 * @param LlmsSettings $settings Settings.
	 * @return array<int, array{title: string, items: array<int, array{title: string, url: string, excerpt: string}>}> The result.
	 */
	public function collect( LlmsSettings $settings ): array {
		$limit        = (int) $settings->get( 'limit', 100 );
		$excerptLimit = (int) $settings->get( 'excerpt_length', 160 );
		$exclude      = $settings->get( 'exclude_ids', [] );
		$exclude      = is_array( $exclude ) ? array_values( array_map( 'intval', $exclude ) ) : [];

		$sections = [];

		$postTypes = $settings->get( 'post_types', [] );

		if ( is_array( $postTypes ) ) {
			foreach ( $postTypes as $type ) {
				$type  = (string) $type;
				$items = ( '' === $type ) ? [] : $this->collectPosts( $type, $limit, $excerptLimit, $exclude );

				if ( [] !== $items ) {
					$sections[] = [
						'title' => $this->postTypeLabel( $type ),
						'items' => $items,
					];
				}
			}
		}

		$taxonomies = $settings->get( 'taxonomies', [] );

		if ( is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy = (string) $taxonomy;
				$items    = ( '' === $taxonomy ) ? [] : $this->collectTerms( $taxonomy, $limit, $excerptLimit );

				if ( [] !== $items ) {
					$sections[] = [
						'title' => $this->taxonomyLabel( $taxonomy ),
						'items' => $items,
					];
				}
			}
		}

		return $sections;
	}

	/**
	 * Collect posts for a post type.
	 *
	 * @param string $type          Post type.
	 * @param int    $limit         Cap.
	 * @param int    $excerptLength Excerpt length.
	 * @param int[]  $exclude       Excluded ids.
	 * @return array<int, array{title: string, url: string, excerpt: string}> The result.
	 */
	private function collectPosts( string $type, int $limit, int $excerptLength, array $exclude ): array {
		$posts = get_posts(
			[
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'post__not_in'   => $exclude,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			]
		);

		$items = [];

		foreach ( (array) $posts as $post ) {
			$id = (int) $post->ID;

			if ( $id <= 0 ) {
				continue;
			}

			if ( 'attachment' === (string) $post->post_type ) {
				continue;
			}

			if ( '' !== (string) $post->post_password ) {
				continue;
			}

			if ( ! $this->isIndexable( $id ) ) {
				continue;
			}

			$raw     = '' !== (string) $post->post_excerpt ? (string) $post->post_excerpt : (string) $post->post_content;
			$items[] = [
				'title'   => $this->text( (string) $post->post_title ),
				'url'     => (string) get_permalink( $post ),
				'excerpt' => $this->excerpt( $raw, $excerptLength ),
			];
		}

		return $items;
	}

	/**
	 * Collect terms for a taxonomy.
	 *
	 * @param string $taxonomy      Taxonomy.
	 * @param int    $limit         Cap.
	 * @param int    $excerptLength Excerpt length.
	 * @return array<int, array{title: string, url: string, excerpt: string}> The result.
	 */
	private function collectTerms( string $taxonomy, int $limit, int $excerptLength ): array {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => $limit,
			]
		);

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$items = [];

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );

			if ( ! is_string( $link ) || '' === $link ) {
				continue;
			}

			$items[] = [
				'title'   => $this->text( (string) $term->name ),
				'url'     => $link,
				'excerpt' => $this->excerpt( (string) $term->description, $excerptLength ),
			];
		}

		return $items;
	}

	/**
	 * Whether a post is indexable per its stored metadata payload.
	 *
	 * @param int $id Post id.
	 * @return bool The result.
	 */
	private function isIndexable( int $id ): bool {
		$payload = get_post_meta( $id, self::META_KEY, true );

		if ( ! is_array( $payload ) || ! isset( $payload['robots'] ) || ! is_array( $payload['robots'] ) ) {
			return true;
		}

		if ( ! array_key_exists( 'index', $payload['robots'] ) ) {
			return true;
		}

		return (bool) $payload['robots']['index'];
	}

	/**
	 * Trim and clean an excerpt.
	 *
	 * @param string $raw    Raw text.
	 * @param int    $length Maximum length in characters.
	 * @return string The result.
	 */
	private function excerpt( string $raw, int $length ): string {
		$text = $this->text( $raw );

		if ( '' === $text ) {
			return '';
		}

		if ( strlen( $text ) > $length ) {
			$cut  = substr( $text, 0, $length );
			$last = strrpos( $cut, ' ' );

			if ( false !== $last && $last > 0 ) {
				$cut = substr( $cut, 0, $last );
			}

			$text = rtrim( $cut ) . '...';
		}

		return $text;
	}

	/**
	 * Strip shortcodes, tags and collapse whitespace.
	 *
	 * @param string $raw Raw text.
	 * @return string The result.
	 */
	private function text( string $raw ): string {
		$text = strip_shortcodes( $raw );
		$text = wp_strip_all_tags( $text );
		$text = (string) preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Label for a post type.
	 *
	 * @param string $type Post type.
	 * @return string The result.
	 */
	private function postTypeLabel( string $type ): string {
		$object = get_post_type_object( $type );

		if ( is_object( $object ) && isset( $object->labels->name ) ) {
			return (string) $object->labels->name;
		}

		return ucwords( str_replace( [ '-', '_' ], ' ', $type ) );
	}

	/**
	 * Label for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string The result.
	 */
	private function taxonomyLabel( string $taxonomy ): string {
		$object = get_taxonomy( $taxonomy );

		if ( is_object( $object ) && isset( $object->labels->name ) ) {
			return (string) $object->labels->name;
		}

		return ucwords( str_replace( [ '-', '_' ], ' ', $taxonomy ) );
	}
}
