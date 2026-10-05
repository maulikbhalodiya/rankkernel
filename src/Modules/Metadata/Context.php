<?php
/**
 * Request context, built once per request.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Metadata;

defined( 'ABSPATH' ) || exit;

use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Immutable request context for metadata resolution.
 *
 * Built ONCE per request; memoizes the single meta read and hash.
 * Never mutates globals; no wp_reset_query().
 */
final class Context {
	/**
	 * Cached meta payload (null = not yet loaded).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $metaCache = null;

	/**
	 * Cached hash.
	 *
	 * @var string|null
	 */
	private ?string $hashCache = null;

	/**
	 * Memoized resolved templates by (hash|field).
	 *
	 * @var array<string, string>
	 */
	private array $resolvedMemo = [];

	/**
	 * Tags replacer for resolved() delegation.
	 *
	 * @var TagsReplacer
	 */
	private TagsReplacer $replacer;

	/**
	 * Constructor.
	 *
	 * @param WP_Query          $query    Current WP_Query.
	 * @param SettingsStore     $settings Settings store.
	 * @param TagsReplacer|null $replacer Optional replacer (for injection / tests).
	 */
	public function __construct(
		private readonly WP_Query $query,
		private readonly SettingsStore $settings,
		?TagsReplacer $replacer = null
	) {
		$this->replacer = $replacer ?? new TagsReplacer();
	}

	/**
	 * Cheap stable key for memoization (md5 of type|id|paged).
	 *
	 * @return string The result.
	 */
	public function hash(): string {
		if ( null !== $this->hashCache ) {
			return $this->hashCache;
		}

		$type  = $this->queriedType();
		$id    = $this->queriedId();
		$paged = $this->paginated() ? '1' : '0';

		// Include actual paged number for correctness.
		if ( function_exists( 'get_query_var' ) ) {
			$paged = (string) (int) get_query_var( 'paged', 0 );
		}

		$this->hashCache = md5( $type . '|' . (string) $id . '|' . $paged );

		return $this->hashCache;
	}

	/**
	 * Get merged meta payload (ONE get_post_meta / get_term_meta per request, memoized).
	 *
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		if ( null !== $this->metaCache ) {
			return $this->metaCache;
		}

		$type = $this->queriedType();
		$id   = $this->queriedId();
		$raw  = null;

		if ( 'post' === $type && $id > 0 ) {
			$raw = get_post_meta( $id, '_rankkernel_meta_data', true );
		} elseif ( 'term' === $type && $id > 0 ) {
			$raw = get_term_meta( $id, '_rankkernel_term_data', true );
		}

		// Merge over defaults via sanitize (fills missing keys, sanitizes).
		// Legacy JSON or serialized rows are string shaped, decode them
		// before sanitize so a stored row is never silently dropped.
		$this->metaCache = MetaPayload::sanitize( MetaPayload::decodeMetaValue( $raw ) );

		return $this->metaCache;
	}

	/**
	 * Resolve a template via TagsReplacer, memoized by (hash|field).
	 *
	 * @param string $field    Field key for memoization.
	 * @param string $template Template containing %%tokens%%.
	 * @return string Resolved template.
	 */
	public function resolved( string $field, string $template ): string {
		$key = $this->hash() . '|' . $field;

		if ( array_key_exists( $key, $this->resolvedMemo ) ) {
			return $this->resolvedMemo[ $key ];
		}

		$result = $this->replacer->replace( $this, $template, $field );

		$this->resolvedMemo[ $key ] = $result;

		return $result;
	}

	/**
	 * Whether the current query is singular.
	 *
	 * @return bool The result.
	 */
	public function isSingular(): bool {
		if ( is_callable( [ $this->query, 'is_singular' ] ) ) {
			return (bool) $this->query->is_singular();
		}

		return false;
	}

	/**
	 * Queried object ID.
	 *
	 * @return int The result.
	 */
	public function queriedId(): int {
		if ( is_callable( [ $this->query, 'get_queried_object_id' ] ) ) {
			$id = $this->query->get_queried_object_id();

			if ( is_int( $id ) ) {
				return $id;
			}

			if ( is_numeric( $id ) ) {
				return (int) $id;
			}
		}

		// Fallback to global.
		if ( function_exists( 'get_queried_object_id' ) ) {
			return (int) get_queried_object_id();
		}

		return 0;
	}

	/**
	 * Queried type: 'post'|'term'|'home'|'search'|'404'|'feed'|'archive'|'preview'|...
	 *
	 * @return string The result.
	 */
	public function queriedType(): string {
		// Previews are not indexable URLs, treat as dedicated type.
		if ( function_exists( 'is_preview' ) && is_preview() ) {
			return 'preview';
		}

		// Query preview check, tolerant of Mockery mocks without expectation.
		try {
			if ( is_callable( [ $this->query, 'is_preview' ] ) && $this->query->is_preview() ) {
				return 'preview';
			}
		} catch ( \Throwable $e ) {
			// Mock without expectation, treat as not preview.
			unset( $e );
		}

		// Check feed first, is_feed may be true alongside other conditionals.
		if ( is_callable( [ $this->query, 'is_feed' ] ) && $this->query->is_feed() ) {
			return 'feed';
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return 'feed';
		}

		if ( is_callable( [ $this->query, 'is_search' ] ) && $this->query->is_search() ) {
			return 'search';
		}

		if ( is_callable( [ $this->query, 'is_404' ] ) && $this->query->is_404() ) {
			return '404';
		}

		if ( is_callable( [ $this->query, 'is_singular' ] ) && $this->query->is_singular() ) {
			return 'post';
		}

		if ( is_callable( [ $this->query, 'is_category' ] ) && $this->query->is_category() ) {
			return 'term';
		}

		if ( is_callable( [ $this->query, 'is_tag' ] ) && $this->query->is_tag() ) {
			return 'term';
		}

		if ( is_callable( [ $this->query, 'is_tax' ] ) && $this->query->is_tax() ) {
			return 'term';
		}

		if ( is_callable( [ $this->query, 'is_home' ] ) && $this->query->is_home() ) {
			return 'home';
		}

		if ( is_callable( [ $this->query, 'is_front_page' ] ) && $this->query->is_front_page() ) {
			return 'home';
		}

		if ( is_callable( [ $this->query, 'is_archive' ] ) && $this->query->is_archive() ) {
			return 'archive';
		}

		return 'home';
	}

	/**
	 * Whether the current page is paginated (paged > 1).
	 *
	 * @return bool The result.
	 */
	public function paginated(): bool {
		if ( function_exists( 'get_query_var' ) ) {
			$paged = (int) get_query_var( 'paged', 0 );

			if ( $paged > 1 ) {
				return true;
			}
		}

		if ( is_callable( [ $this->query, 'get' ] ) ) {
			$paged = (int) $this->query->get( 'paged' );

			if ( $paged > 1 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Trimmed excerpt (max ~160 chars, word-boundary, wp_strip_all_tags).
	 *
	 * @return string The result.
	 */
	public function excerpt(): string {
		$id      = $this->queriedId();
		$excerpt = '';

		if ( $id > 0 ) {
			if ( function_exists( 'get_the_excerpt' ) ) {
				$raw = get_the_excerpt( $id );

				if ( is_string( $raw ) ) {
					$excerpt = $raw;
				}
			}

			if ( '' === $excerpt && function_exists( 'get_post_field' ) ) {
				$field = get_post_field( 'post_excerpt', $id );

				if ( is_string( $field ) && '' !== $field ) {
					$excerpt = $field;
				}
			}

			if ( '' === $excerpt && function_exists( 'get_post_field' ) ) {
				$content = get_post_field( 'post_content', $id );

				if ( is_string( $content ) && '' !== $content ) {
					$excerpt = $content;
				}
			}
		}

		if ( '' === $excerpt ) {
			return '';
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$excerpt = wp_strip_all_tags( $excerpt );
		} else {
			$excerpt = strip_tags( $excerpt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- strip_tags fallback preserves inner text where wp_kses_post and wp_strip_all_tags are unavailable, matching the MetaPayload fallback.
		}

		$excerpt = trim( preg_replace( '/\s+/', ' ', $excerpt ) ?? $excerpt );

		if ( '' === $excerpt ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $excerpt ) <= 160 ) {
				return $excerpt;
			}

			$trimmed   = mb_substr( $excerpt, 0, 160 );
			$lastSpace = mb_strrpos( $trimmed, ' ' );

			if ( false !== $lastSpace && $lastSpace > 100 ) {
				$trimmed = mb_substr( $trimmed, 0, (int) $lastSpace );
			}

			return trim( $trimmed );
		}

		if ( strlen( $excerpt ) <= 160 ) {
			return $excerpt;
		}

		$trimmed   = substr( $excerpt, 0, 160 );
		$lastSpace = strrpos( $trimmed, ' ' );

		if ( false !== $lastSpace && $lastSpace > 100 ) {
			$trimmed = substr( $trimmed, 0, $lastSpace );
		}

		return trim( $trimmed );
	}

	/**
	 * Queried object for the current request, term or author on archives.
	 *
	 * Uses the query object first and the global accessor as a fallback,
	 * both guarded exactly like the rest of this class so mocks without
	 * the method never fatal.
	 *
	 * @return object|null The result.
	 */
	public function queriedObject(): ?object {
		if ( is_callable( [ $this->query, 'get_queried_object' ] ) ) {
			try {
				$obj = $this->query->get_queried_object();

				if ( is_object( $obj ) ) {
					return $obj;
				}
			} catch ( \Throwable $e ) {
				// Mock without expectation, fall through to the global.
				unset( $e );
			}
		}

		if ( function_exists( 'get_queried_object' ) ) {
			try {
				$obj = get_queried_object();

				if ( is_object( $obj ) ) {
					return $obj;
				}
			} catch ( \Throwable $e ) {
				// Global accessor unavailable in this test context.
				unset( $e );
			}
		}

		return null;
	}

	/**
	 * Whether the current query is an author archive.
	 *
	 * Author archives are typed 'archive' by queriedType(), so they are
	 * detected explicitly here through is_author() on the query or global.
	 * Non archive requests return early so they never touch the author
	 * conditional.
	 *
	 * @return bool The result.
	 */
	public function isAuthorArchive(): bool {
		if ( 'archive' !== $this->queriedType() ) {
			return false;
		}

		if ( is_callable( [ $this->query, 'is_author' ] ) ) {
			try {
				if ( $this->query->is_author() ) {
					return true;
				}
			} catch ( \Throwable $e ) {
				// Mock without expectation, fall through to the global.
				unset( $e );
			}
		}

		if ( function_exists( 'is_author' ) ) {
			return (bool) is_author();
		}

		return false;
	}

	/**
	 * Name of the queried term on a term archive.
	 *
	 * Reads the queried object's name first, then falls back to the term id.
	 *
	 * @return string The result.
	 */
	public function termName(): string {
		$obj = $this->queriedObject();

		if ( is_object( $obj ) && isset( $obj->name ) && is_string( $obj->name ) && '' !== $obj->name ) {
			return $obj->name;
		}

		if ( function_exists( 'single_term_title' ) ) {
			try {
				$title = single_term_title( '', false );

				if ( is_string( $title ) && '' !== $title ) {
					return $title;
				}
			} catch ( \Throwable $e ) {
				// Term title helper unavailable in this test context.
				unset( $e );
			}
		}

		$id = $this->queriedId();

		if ( $id > 0 && function_exists( 'get_term_field' ) ) {
			try {
				$name = get_term_field( 'name', $id );

				if ( is_string( $name ) && '' !== $name ) {
					return $name;
				}
			} catch ( \Throwable $e ) {
				// Term field helper unavailable in this test context.
				unset( $e );
			}
		}

		return '';
	}

	/**
	 * Display name of the queried author on an author archive.
	 *
	 * Reads the author id from the queried object, falls back to the
	 * queried id, then resolves the display name, then get_the_author.
	 *
	 * @return string The result.
	 */
	public function authorDisplayName(): string {
		$authorId = 0;
		$obj      = $this->queriedObject();

		if ( is_object( $obj ) ) {
			if ( isset( $obj->ID ) && is_numeric( $obj->ID ) ) {
				$authorId = (int) $obj->ID;
			} elseif (
				isset( $obj->data )
				&& is_object( $obj->data )
				&& isset( $obj->data->ID )
				&& is_numeric( $obj->data->ID )
			) {
				$authorId = (int) $obj->data->ID;
			}
		}

		if ( $authorId <= 0 ) {
			$authorId = $this->queriedId();
		}

		if ( $authorId > 0 && function_exists( 'get_the_author_meta' ) ) {
			$name = get_the_author_meta( 'display_name', $authorId );

			if ( is_string( $name ) && '' !== $name ) {
				return $name;
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
	 * Title for the queried object.
	 *
	 * Term archives resolve to the term name and author archives to the
	 * author display name before the post fallback, so %%title%% never
	 * resolves a colliding post against a term or user id.
	 *
	 * @return string The result.
	 */
	public function title(): string {
		if ( 'term' === $this->queriedType() ) {
			$term = $this->termName();

			if ( '' !== $term ) {
				return $term;
			}
		}

		if ( $this->isAuthorArchive() ) {
			$author = $this->authorDisplayName();

			if ( '' !== $author ) {
				return $author;
			}
		}

		$id = $this->queriedId();

		if ( $id > 0 && function_exists( 'get_the_title' ) ) {
			$t = get_the_title( $id );

			if ( is_string( $t ) && '' !== $t ) {
				return $t;
			}
		}

		if ( function_exists( 'single_post_title' ) ) {
			$t = single_post_title( '', false );

			if ( is_string( $t ) && '' !== $t ) {
				return $t;
			}
		}

		return '';
	}

	/**
	 * Site name.
	 *
	 * @return string The result.
	 */
	public function siteName(): string {
		if ( function_exists( 'get_bloginfo' ) ) {
			return (string) get_bloginfo( 'name' );
		}

		return '';
	}

	/**
	 * Separator from settings.
	 *
	 * @return string The result.
	 */
	public function separator(): string {
		$sep = $this->settings->get( 'separator', '–' );

		return is_string( $sep ) ? $sep : '–';
	}

	/**
	 * Permalink / term link / home URL for the current context.
	 *
	 * For archive contexts (author/date/post-type) returns the correct
	 * archive URL or '' when derivation is unreliable, never falls back to
	 * home_url for archives (prevents homepage canonical on archives).
	 *
	 * @return string The result.
	 */
	public function permalink(): string {
		$type = $this->queriedType();
		$id   = $this->queriedId();

		if ( 'post' === $type && $id > 0 && function_exists( 'get_permalink' ) ) {
			$url = get_permalink( $id );

			return is_string( $url ) ? $url : '';
		}

		if ( 'term' === $type && $id > 0 && function_exists( 'get_term_link' ) ) {
			$url = get_term_link( $id );

			if ( is_string( $url ) ) {
				return $url;
			}

			return '';
		}

		if ( 'archive' === $type ) {
			// Author archive.
			if (
				is_callable( [ $this->query, 'is_author' ] )
				&& $this->query->is_author()
				&& function_exists( 'get_author_posts_url' )
			) {
				$authorId = $id;

				if ( $authorId <= 0 && is_callable( [ $this->query, 'get_queried_object' ] ) ) {
					$obj = $this->query->get_queried_object();

					if ( is_object( $obj ) && isset( $obj->ID ) && is_numeric( $obj->ID ) ) {
						$authorId = (int) $obj->ID;
					} elseif ( is_object( $obj ) && isset( $obj->data ) && is_object( $obj->data ) && isset( $obj->data->ID ) ) {
						$authorId = (int) $obj->data->ID;
					}
				}

				if ( $authorId > 0 ) {
					$url = get_author_posts_url( $authorId );

					if ( is_string( $url ) && '' !== $url ) {
						return $url;
					}
				}

				return '';
			}

			// Date archives.
			if ( is_callable( [ $this->query, 'is_date' ] ) && $this->query->is_date() ) {
				$year  = 0;
				$month = 0;
				$day   = 0;

				if ( function_exists( 'get_query_var' ) ) {
					$year  = (int) get_query_var( 'year' );
					$month = (int) get_query_var( 'monthnum' );
					$day   = (int) get_query_var( 'day' );
				}

				if ( is_callable( [ $this->query, 'get' ] ) ) {
					if ( 0 === $year ) {
						$year = (int) $this->query->get( 'year' );
					}

					if ( 0 === $month ) {
						$month = (int) $this->query->get( 'monthnum' );
					}

					if ( 0 === $day ) {
						$day = (int) $this->query->get( 'day' );
					}
				}

				if ( $year && $month && $day && function_exists( 'get_day_link' ) ) {
					return (string) get_day_link( $year, $month, $day );
				}

				if ( $year && $month && function_exists( 'get_month_link' ) ) {
					return (string) get_month_link( $year, $month );
				}

				if ( $year && function_exists( 'get_year_link' ) ) {
					return (string) get_year_link( $year );
				}

				return '';
			}

			// Post-type archive.
			if (
				is_callable( [ $this->query, 'is_post_type_archive' ] )
				&& $this->query->is_post_type_archive()
				&& function_exists( 'get_post_type_archive_link' )
			) {
				$postType = null;

				if ( is_callable( [ $this->query, 'get' ] ) ) {
					$postType = $this->query->get( 'post_type' );
				}

				if ( is_string( $postType ) && '' !== $postType ) {
					$url = get_post_type_archive_link( $postType );

					if ( is_string( $url ) && '' !== $url ) {
						return $url;
					}
				}
			}

			return '';
		}

		if ( 'home' === $type ) {
			// Blog posts page: when static front page + separate posts page and
			// is_home() is the posts page, canonicalize to the posts page URL.
			if ( function_exists( 'get_option' ) && function_exists( 'get_permalink' ) ) {
				$pageForPosts = (int) get_option( 'page_for_posts', 0 );

				if ( $pageForPosts > 0 ) {
					$isPostsPage = false;

					if ( is_callable( [ $this->query, 'is_home' ] ) && $this->query->is_home() ) {
						$isPostsPage = true;
					} elseif ( function_exists( 'is_home' ) && is_home() ) {
						$isPostsPage = true;
					}

					if ( $isPostsPage ) {
						$url = get_permalink( $pageForPosts );

						if ( is_string( $url ) && '' !== $url ) {
							return $url;
						}
					}
				}
			}

			if ( function_exists( 'home_url' ) ) {
				return (string) home_url( '/' );
			}
		}

		return '';
	}

	/**
	 * Cached og-image data (url + attachment id) for single-source resolution.
	 *
	 * @var array{url:string,id:int}|null
	 */
	private ?array $ogImageData = null;

	/**
	 * Resolve og-image url and its attachment id in ONE place.
	 *
	 * Chain: payload og.image (custom URL, id 0) → og.image_id → featured → site default.
	 * Custom URL never pairs with an attachment's dimensions.
	 *
	 * @return array{url:string,id:int} The result.
	 */
	private function resolveOgImageData(): array {
		if ( null !== $this->ogImageData ) {
			return $this->ogImageData;
		}

		$meta = $this->meta();
		$og   = $meta['og'] ?? [];

		// 1) Custom URL, id 0, never use attachment dimensions.
		if ( is_array( $og ) && isset( $og['image'] ) && is_string( $og['image'] ) && '' !== trim( $og['image'] ) ) {
			$this->ogImageData = [
				'url' => trim( $og['image'] ),
				'id'  => 0,
			];

			return $this->ogImageData;
		}

		// 2) Attachment id.
		if (
			is_array( $og )
			&& isset( $og['image_id'] )
			&& (int) $og['image_id'] > 0
			&& function_exists( 'wp_get_attachment_image_url' )
		) {
			$url = wp_get_attachment_image_url( (int) $og['image_id'], 'large' );

			if ( is_string( $url ) && '' !== $url ) {
				$this->ogImageData = [
					'url' => $url,
					'id'  => (int) $og['image_id'],
				];

				return $this->ogImageData;
			}
		}

		// 3) Featured image fallback for singular.
		if ( $this->isSingular() && $this->queriedId() > 0 ) {
			if ( function_exists( 'get_post_thumbnail_id' ) && function_exists( 'wp_get_attachment_image_url' ) ) {
				$thumbId = get_post_thumbnail_id( $this->queriedId() );

				if ( (int) $thumbId > 0 ) {
					$url = wp_get_attachment_image_url( (int) $thumbId, 'large' );

					if ( is_string( $url ) && '' !== $url ) {
						$this->ogImageData = [
							'url' => $url,
							'id'  => (int) $thumbId,
						];

						return $this->ogImageData;
					}
				}
			}
		}

		// 4) Site default image, only when both settings identify the same attachment.
		$defaultImage = $this->settings->get( 'social_default_image', '' );
		$defaultId    = $this->settings->get( 'social_default_image_id', 0 );
		$defaultImage = is_string( $defaultImage ) ? trim( $defaultImage ) : '';
		$defaultId    = is_numeric( $defaultId ) ? (int) $defaultId : 0;

		if ( '' !== $defaultImage && $defaultId > 0 ) {
			$this->ogImageData = [
				'url' => $defaultImage,
				'id'  => $defaultId,
			];

			return $this->ogImageData;
		}

		$this->ogImageData = [
			'url' => '',
			'id'  => 0,
		];

		return $this->ogImageData;
	}

	/**
	 * OG image, chain: payload og.image -> og.image_id via wp_get_attachment_image_url -> featured image -> site default -> ''.
	 *
	 * @return string The result.
	 */
	public function ogImage(): string {
		return $this->resolveOgImageData()['url'];
	}

	/**
	 * Attachment id that produced the current ogImage, or 0 if custom URL / none.
	 *
	 * @return int The result.
	 */
	public function ogImageAttachmentId(): int {
		return $this->resolveOgImageData()['id'];
	}

	/**
	 * Alt text for the resolved OG image.
	 *
	 * A payload override wins. Attachment alt is read only when the resolved
	 * image came from an attachment, never for a custom URL.
	 *
	 * @return string The result.
	 */
	public function ogImageAlt(): string {
		$meta = $this->meta();
		$og   = $meta['og'] ?? [];
		$alt  = is_array( $og ) && isset( $og['image_alt'] ) ? trim( (string) $og['image_alt'] ) : '';

		if ( '' !== $alt ) {
			return $alt;
		}

		$attachmentId = $this->ogImageAttachmentId();

		if ( $attachmentId <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}

		$attachmentAlt = get_post_meta( $attachmentId, '_wp_attachment_image_alt', true );

		return is_string( $attachmentAlt ) ? trim( $attachmentAlt ) : '';
	}
}
