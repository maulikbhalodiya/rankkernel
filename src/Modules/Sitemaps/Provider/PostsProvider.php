<?php
/**
 * Posts sitemap provider.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps\Provider;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\MetaPayload;
use RankKernel\Modules\Sitemaps\SitemapSettings;

/**
 * Provides sitemap entries for public post types.
 *
 * Only featured images feed the image tag today, so both image
 * settings gate the thumbnail lookup. Content images arrive later.
 */
class PostsProvider {
	/**
	 * Inner LIKE text matching a noindex payload.
	 *
	 * The payload is stored as a PHP serialized meta row (object typed
	 * meta is serialized by core on write), so matching uses the
	 * serialized shape: key "index" is exactly 5 chars, followed by
	 * boolean false. No other sanitized key can emit these exact bytes
	 * (title and image are 5 chars but always serialize as strings, and
	 * no sibling robots key is named exactly "index"). Residual risk is
	 * a post whose free text literally contains this byte sequence,
	 * which is astronomically unlikely and documented here. Escaped with
	 * $wpdb->esc_like and wrapped in % % at query time, passed via
	 * $wpdb->prepare as %s.
	 */
	private const NOINDEX_LIKE_INNER = 's:5:"index";b:0';

	/**
	 * Meta key holding the serialized payload.
	 */
	private const META_KEY = '_rankkernel_meta_data';

	/**
	 * Constructor.
	 *
	 * Null settings mean defaults and touch no globals, which keeps
	 * zero argument construction side effect free. Production wires a
	 * real instance in SitemapsModule boot.
	 *
	 * @param SitemapSettings|null $settings Settings store or null for defaults.
	 */
	public function __construct( private readonly ?SitemapSettings $settings = null ) {
	}

	/**
	 * Get available post type sets.
	 *
	 * @return string[] The result.
	 */
	public function getSets(): array {
		$postTypes = get_post_types( [ 'public' => true ], 'names' );

		if ( ! is_array( $postTypes ) ) {
			return [];
		}

		// Attachments are never listed (matches the competitor default),
		// even if a theme registers the type as public.
		$sets = array_values(
			array_filter(
				$postTypes,
				static fn ( mixed $v ): bool => is_string( $v ) && '' !== $v && 'attachment' !== $v
			)
		);

		// Types disabled in sitemap settings vanish from the index.
		$sets = array_values(
			array_filter(
				$sets,
				fn ( string $type ): bool => $this->settings?->isTypeEnabled( 'pt', $type ) ?? true
			)
		);

		return $sets;
	}

	/**
	 * Get count of published posts for a set.
	 *
	 * @param string $postType Post type slug.
	 * @return int The result.
	 */
	public function getCount( string $postType ): int {
		if ( 'attachment' === $postType ) {
			return 0;
		}

		if ( ! ( $this->settings?->isTypeEnabled( 'pt', $postType ) ?? true ) ) {
			return 0;
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}

		$like = '%' . $wpdb->esc_like( self::NOINDEX_LIKE_INNER ) . '%';

		$params  = [ $postType, 'publish', $like ];
		$exclude = $this->excludeClause( $this->excludedPostIds(), 'p.ID', $params );

		$sql = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = %s"
			. " AND p.post_password = ''"
			. " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID"
			. " AND m.meta_key = '_rankkernel_meta_data' AND m.meta_value LIKE %s)"
			. $exclude;

		$args = array_merge( [ $sql ], $params );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- sitemap tables have no core API, query uses placeholders with prepare through argument unpacking.
		$count = $wpdb->get_var( $wpdb->prepare( ...$args ) );

		return (int) $count;
	}

	/**
	 * Get entries for a post type page.
	 *
	 * @param string $postType Post type slug.
	 * @param int    $page     Page number, 1 based.
	 * @param int    $perPage  Entries per page.
	 * @return array<int, array{loc: string, lastmod: string, images: string[]}>
	 */
	public function getEntries( string $postType, int $page, int $perPage ): array {
		if ( 'attachment' === $postType ) {
			return [];
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return [];
		}

		$page    = max( 1, $page );
		$perPage = max( 1, $perPage );
		$offset  = ( $page - 1 ) * $perPage;

		$like = '%' . $wpdb->esc_like( self::NOINDEX_LIKE_INNER ) . '%';

		$params  = [ $postType, 'publish', $like ];
		$exclude = $this->excludeClause( $this->excludedPostIds(), 'p.ID', $params );

		$sql = "SELECT p.ID, p.post_modified_gmt, p.post_content FROM {$wpdb->posts} p WHERE p.post_type = %s"
			. " AND p.post_status = %s AND p.post_password = ''"
			. " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID"
			. " AND m.meta_key = '_rankkernel_meta_data' AND m.meta_value LIKE %s)"
			. $exclude
			. ' ORDER BY p.post_modified_gmt DESC, p.ID DESC LIMIT %d OFFSET %d';

		$params[] = $perPage;
		$params[] = $offset;

		$args = array_merge( [ $sql ], $params );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- sitemap tables have no core API, query uses placeholders with prepare through argument unpacking.
		$rows = $wpdb->get_results( $wpdb->prepare( ...$args ), ARRAY_A );

		if ( ! is_array( $rows ) || [] === $rows ) {
			return [];
		}

		// Performance optimization: prime post object caches in a single batch query to prevent N+1 queries in get_permalink().
		$postIds = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['ID'] ) && (int) $row['ID'] > 0 ) {
				$postIds[] = (int) $row['ID'];
			}
		}

		if ( [] !== $postIds && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $postIds, false, false );
		}

		$rows = $this->dropCanonicalMismatchRows( $rows );

		if ( [] === $rows ) {
			return [];
		}

		$entries = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['ID'] ) ) {
				continue;
			}

			$postId = (int) $row['ID'];
			if ( 0 === $postId ) {
				continue;
			}

			$permalink = isset( $row['_permalink'] ) && is_string( $row['_permalink'] )
				? $row['_permalink']
				: get_permalink( $postId );

			if ( ! is_string( $permalink ) || '' === $permalink ) {
				$permalink = home_url( '/?p=' . (string) $postId );
			}

			$modifiedGmt = isset( $row['post_modified_gmt'] ) ? (string) $row['post_modified_gmt'] : '';

			$lastmod = '';
			if ( '' !== $modifiedGmt ) {
				$lastmod = (string) mysql2date( DATE_W3C, $modifiedGmt, false );
			}

			$images = [];
			if ( $this->contentImagesAllowed() ) {
				$featured    = null;
				$hasThumbFns = function_exists( 'get_post_thumbnail_id' )
					&& function_exists( 'wp_get_attachment_image_url' );
				if ( $this->featuredAllowed() && $hasThumbFns ) {
					$thumbId = (int) get_post_thumbnail_id( $postId );
					if ( 0 !== $thumbId ) {
						$url = wp_get_attachment_image_url( $thumbId, 'full' );
						if ( is_string( $url ) && '' !== $url ) {
							$featured = $url;
						}
					}
				}

				$content = isset( $row['post_content'] ) && is_string( $row['post_content'] ) ? $row['post_content'] : '';
				$images  = $this->extractContentImages( $postId, $content, $featured );
			}

			$entries[] = [
				'loc'     => $permalink,
				'lastmod' => $lastmod,
				'images'  => $images,
			];
		}

		return $entries;
	}

	/**
	 * Excluded post ids from settings, unique positive ints.
	 *
	 * @return int[] The result.
	 */
	private function excludedPostIds(): array {
		$ids = $this->settings?->get( 'exclude_posts', [] ) ?? [];

		if ( ! is_array( $ids ) ) {
			return [];
		}

		$clean = [];

		foreach ( $ids as $id ) {
			$int = (int) $id;

			if ( $int > 0 ) {
				$clean[] = $int;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Extract image URLs from post content: featured image first, then
	 * inline <img> sources, then gallery shortcode attachments.
	 *
	 * Only same-host URLs are kept (external images stay out by default,
	 * mirroring the leading plugins). Deduplicated, capped at 100 per URL.
	 *
	 * @param int         $postId   Post id.
	 * @param string      $content  Raw post content.
	 * @param string|null $featured Featured image URL or null.
	 * @return string[] The result.
	 */
	private function extractContentImages( int $postId, string $content, ?string $featured ): array {
		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- parses the site home URL built by core, result strictly type checked before use.
		$host = is_string( parse_url( $home, PHP_URL_HOST ) ) ? strtolower( (string) parse_url( $home, PHP_URL_HOST ) ) : '';

		$found = [];
		if ( is_string( $featured ) && '' !== $featured ) {
			$found[] = $featured;
		}

		if ( '' !== $content && class_exists( 'DOMDocument' ) ) {
			// Render dynamic blocks first: carousels, sliders, and other
			// dynamic blocks exist only at render time, so parsing raw
			// content would miss every image they output.
			$rendered = function_exists( 'do_blocks' ) ? (string) do_blocks( $content ) : $content;

			$dom = new \DOMDocument();

			$internal = libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="UTF-8">' . $rendered );
			libxml_clear_errors();
			libxml_use_internal_errors( $internal );

			foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
				$src        = trim( (string) $img->getAttribute( 'src' ) );
				$normalized = $this->normalizeImageUrl( $src, $home, $host );
				if ( '' !== $normalized ) {
					$found[] = $normalized;
				}
			}
		}

		if ( '' !== $content && function_exists( 'wp_get_attachment_url' ) ) {
			if ( preg_match_all( '/\[gallery[^\]]*ids\s*=\s*"([^"]+)"[^\]]*\]/', $content, $matches ) > 0 ) {
				foreach ( $matches[1] as $idList ) {
					foreach ( explode( ',', (string) $idList ) as $rawId ) {
						$attachmentId = (int) trim( (string) $rawId );
						if ( $attachmentId <= 0 ) {
							continue;
						}

						$url        = wp_get_attachment_url( $attachmentId );
						$normalized = is_string( $url ) ? $this->normalizeImageUrl( trim( $url ), $home, $host ) : '';
						if ( '' !== $normalized ) {
							$found[] = $normalized;
						}
					}
				}
			}
		}

		$unique = array_values( array_unique( $found ) );

		return array_slice( $unique, 0, 100 );
	}

	/**
	 * Normalize an image source to an absolute same-host URL, or empty.
	 *
	 * Skips data URIs and external hosts. Root-relative and
	 * protocol-relative sources resolve against the home URL.
	 *
	 * @param string $src  Src.
	 * @param string $home Home.
	 * @param string $host Host.
	 * @return string The result.
	 */
	private function normalizeImageUrl( string $src, string $home, string $host ): string {
		if ( '' === $src || str_starts_with( $src, 'data:' ) ) {
			return '';
		}

		if ( str_starts_with( $src, '//' ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- parses the site home URL built by core, result strictly type checked before use.
			$scheme = is_string( parse_url( $home, PHP_URL_SCHEME ) ) ? (string) parse_url( $home, PHP_URL_SCHEME ) : 'https';
			$src    = $scheme . ':' . $src;
		} elseif ( str_starts_with( $src, '/' ) ) {
			$src = rtrim( $home, '/' ) . $src;
		}

        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- parses image source strings gathered above, result strictly type checked before use.
		$srcHost = parse_url( $src, PHP_URL_HOST );
		if ( ! is_string( $srcHost ) || '' === $srcHost ) {
			return '';
		}

		if ( '' !== $host && strtolower( $srcHost ) !== $host ) {
			return '';
		}

		return $src;
	}

	/**
	 * Whether any image work may run (master images toggle).
	 *
	 * @return bool The result.
	 */
	private function contentImagesAllowed(): bool {
		return (bool) ( $this->settings?->get( 'include_images', true ) ?? true );
	}

	/**
	 * Whether the featured image may lead the list (sub toggle).
	 *
	 * Content images are unaffected by this flag.
	 *
	 * @return bool The result.
	 */
	private function featuredAllowed(): bool {
		return (bool) ( $this->settings?->get( 'include_featured_image', true ) ?? true );
	}

	/**
	 * Append NOT IN clauses for excluded ids, chunked at 500 per clause.
	 *
	 * Returns an empty fragment when the list is empty, so default
	 * queries keep their exact SQL shape.
	 *
	 * @param int[]             $ids    Excluded ids.
	 * @param string            $column Qualified column, e.g. p.ID.
	 * @param array<int, mixed> $params Prepare params, ids appended in order.
	 * @return string The result.
	 */
	private function excludeClause( array $ids, string $column, array &$params ): string {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$ids = array_values( array_filter( $ids, static fn ( int $id ): bool => $id > 0 ) );

		if ( [] === $ids ) {
			return '';
		}

		$fragment = '';

		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$fragment    .= " AND {$column} NOT IN ($placeholders)";

			foreach ( $chunk as $id ) {
				$params[] = $id;
			}
		}

		return $fragment;
	}

	/**
	 * Drop rows whose stored canonical differs from the permalink.
	 *
	 * One batched postmeta read for the page, decode per row, fail
	 * open (unreadable payloads and unresolvable permalinks are kept).
	 * Counts stay unfiltered, an approximation the competitors accept too.
	 *
	 * @param array<int, mixed> $rows Entry rows with ID keys.
	 * @return array<int, mixed> Surviving rows.
	 */
	private function dropCanonicalMismatchRows( array $rows ): array {
		$ids = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['ID'] ) ) {
				continue;
			}

			$id = (int) $row['ID'];

			if ( 0 !== $id ) {
				$ids[] = $id;
			}
		}

		if ( [] === $ids ) {
			return $rows;
		}

		$canonicals = $this->fetchCanonicals( $ids );

		if ( [] === $canonicals ) {
			return $rows;
		}

		$kept = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['ID'] ) ) {
				continue;
			}

			$id        = (int) $row['ID'];
			$canonical = $canonicals[ $id ] ?? '';

			if ( '' === $canonical ) {
				$kept[] = $row;

				continue;
			}

			$permalink = get_permalink( $id );

			if ( ! is_string( $permalink ) || '' === $permalink ) {
				$kept[] = $row;

				continue;
			}

			if ( trailingslashit( $canonical ) !== trailingslashit( $permalink ) ) {
				continue;
			}

			$row['_permalink'] = $permalink;
			$kept[]            = $row;
		}

		return $kept;
	}

	/**
	 * Batch fetch non empty stored canonicals for post ids.
	 *
	 * @param int[] $ids Post ids.
	 * @return array<int, string> Map of post id to canonical URL.
	 */
	private function fetchCanonicals( array $ids ): array {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return [];
		}

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$ids = array_values( array_filter( $ids, static fn ( int $id ): bool => 0 !== $id ) );

		if ( [] === $ids ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$sql = "SELECT post_id, meta_value FROM {$wpdb->postmeta}"
			. " WHERE meta_key = %s AND post_id IN ($placeholders)";

		$args = array_merge( [ $sql, self::META_KEY ], $ids );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- sitemap tables have no core API, query uses placeholders with prepare through argument unpacking.
		$metaRows = $wpdb->get_results( $wpdb->prepare( ...$args ), ARRAY_A );

		if ( ! is_array( $metaRows ) ) {
			return [];
		}

		$out = [];

		foreach ( $metaRows as $metaRow ) {
			if ( ! is_array( $metaRow ) || ! isset( $metaRow['post_id'], $metaRow['meta_value'] ) ) {
				continue;
			}

			$postId = (int) $metaRow['post_id'];

			if ( 0 === $postId ) {
				continue;
			}

			if ( ! is_string( $metaRow['meta_value'] ) || '' === $metaRow['meta_value'] ) {
				continue;
			}

			$payload = MetaPayload::decodeMetaValue( $metaRow['meta_value'] );

			if ( ! isset( $payload['canonical'] ) || ! is_string( $payload['canonical'] ) ) {
				continue;
			}

			if ( '' === $payload['canonical'] ) {
				continue;
			}

			$out[ $postId ] = $payload['canonical'];
		}

		return $out;
	}
}
