<?php
/**
 * Tags replacer, memoized %%token%% resolution.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Metadata;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves %%tokens%% in templates with memoization.
 *
 * Memoized by (context_hash . '|' . field) so the same field is never
 * resolved twice per request even if called from title() and render().
 */
final class TagsReplacer {
	/**
	 * Memo cache keyed by (hash|field).
	 *
	 * @var array<string, string>
	 */
	private array $memo = [];

	/**
	 * Replace tokens in a template, memoized by context hash + field.
	 *
	 * Unknown tokens are stripped (replaced with ''). Custom tokens may be
	 * injected via the `rankkernel/tokens` filter where `$map` is
	 * token-name (no %%) => value.
	 *
	 * @param Context $ctx      Request context.
	 * @param string  $template Template containing %%tokens%%.
	 * @param string  $field    Field key for memoization (e.g. 'title', 'description').
	 * @return string Resolved template.
	 */
	public function replace( Context $ctx, string $template, string $field ): string {
		$key = $ctx->hash() . '|' . $field;

		if ( array_key_exists( $key, $this->memo ) ) {
			return $this->memo[ $key ];
		}

		$result = $this->doReplace( $ctx, $template );

		$this->memo[ $key ] = $result;

		return $result;
	}

	/**
	 * Actual replacement logic (not memoized).
	 *
	 * @param Context $ctx      Request context.
	 * @param string  $template Template.
	 * @return string Resolved string.
	 */
	private function doReplace( Context $ctx, string $template ): string {
		$map = [
			'title'       => $ctx->title(),
			'sitename'    => $ctx->siteName(),
			'sep'         => $ctx->separator(),
			'excerpt'     => $ctx->excerpt(),
			'date'        => $this->resolveDate( $ctx ),
			'author'      => $this->resolveAuthor( $ctx ),
			'category'    => $this->resolveCategory( $ctx ),
			'page'        => $this->resolvePage( $ctx ),
			'currentdate' => $this->resolveCurrentDate(),
		];

		/**
		 * Filter the token map.
		 *
		 * @param array<string,string> $map     Token map (name without %%) => value.
		 * @param Context              $context Current request context.
		 */
		$map = apply_filters( 'rankkernel/tokens', $map, $ctx ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( ! is_array( $map ) ) {
			$map = [];
		}

		$resolved = preg_replace_callback(
			'/%%([a-z_]+)%%/',
			static function ( array $matches ) use ( $map ): string {
				$token = $matches[1];

				return isset( $map[ $token ] ) ? (string) $map[ $token ] : '';
			},
			$template
		);

		return is_string( $resolved ) ? $resolved : $template;
	}

	/**
	 * Resolve %%date%% token.
	 *
	 * @param Context $ctx Context.
	 * @return string The result.
	 */
	private function resolveDate( Context $ctx ): string {
		if ( function_exists( 'get_the_date' ) ) {
			$date = get_the_date( '', $ctx->queriedId() );

			if ( is_string( $date ) ) {
				return $date;
			}
		}

		return '';
	}

	/**
	 * Resolve %%author%% token.
	 *
	 * @param Context $ctx Context.
	 * @return string The result.
	 */
	private function resolveAuthor( Context $ctx ): string {
		// Prefer author of queried post.
		$id = $ctx->queriedId();

		if ( $id > 0 && function_exists( 'get_post_field' ) ) {
			$authorId = get_post_field( 'post_author', $id );

			if ( is_numeric( $authorId ) && (int) $authorId > 0 && function_exists( 'get_the_author_meta' ) ) {
				$name = get_the_author_meta( 'display_name', (int) $authorId );

				if ( is_string( $name ) && '' !== $name ) {
					return $name;
				}
			}
		}

		if ( function_exists( 'get_the_author' ) ) {
			$author = get_the_author();

			if ( is_string( $author ) ) {
				return $author;
			}
		}

		return '';
	}

	/**
	 * Resolve %%category%% token (first category name).
	 *
	 * @param Context $ctx Context.
	 * @return string The result.
	 */
	private function resolveCategory( Context $ctx ): string {
		$id = $ctx->queriedId();

		if ( $id > 0 && function_exists( 'get_the_category' ) ) {
			$cats = get_the_category( $id );

			if ( is_array( $cats ) && [] !== $cats && isset( $cats[0] ) ) {
				$cat = $cats[0];

				if ( is_object( $cat ) && isset( $cat->name ) ) {
					return (string) $cat->name;
				}

				if ( is_array( $cat ) && isset( $cat['name'] ) ) {
					return (string) $cat['name'];
				}
			}
		}

		return '';
	}

	/**
	 * Resolve %%page%% token.
	 *
	 * @param Context $ctx Context.
	 * @return string The result.
	 */
	private function resolvePage( Context $ctx ): string {
		$paged = 0;
		$max   = 0;

		if ( function_exists( 'get_query_var' ) ) {
			$paged = (int) get_query_var( 'paged', 0 );
			$max   = (int) get_query_var( 'max_num_pages', 0 );
		}

		// Fallback to Context paginated info via query object.
		if ( 0 === $paged ) {
			$paged = $ctx->paginated() ? 2 : 0;
		}

		if ( $paged > 1 ) {
			if ( $max > 1 ) {
				return sprintf( 'Page %d of %d', $paged, $max );
			}

			// Try to get max from global WP_Query.
			global $wp_query;

			if ( isset( $wp_query ) && isset( $wp_query->max_num_pages ) && (int) $wp_query->max_num_pages > 1 ) {
				return sprintf( 'Page %d of %d', $paged, (int) $wp_query->max_num_pages );
			}

			return sprintf( 'Page %d', $paged );
		}

		return '';
	}

	/**
	 * Resolve %%currentdate%% token.
	 *
	 * @return string The result.
	 */
	private function resolveCurrentDate(): string {
		if ( function_exists( 'date_i18n' ) ) {
			$format = 'F j, Y';

			if ( function_exists( 'get_option' ) ) {
				$opt = get_option( 'date_format', 'F j, Y' );

				if ( is_string( $opt ) && '' !== $opt ) {
					$format = $opt;
				}
			}

			return (string) date_i18n( $format );
		}

		return gmdate( 'F j, Y' );
	}

	/**
	 * Clear memo (useful in tests).
	 */
	public function clearMemo(): void {
		$this->memo = [];
	}
}
