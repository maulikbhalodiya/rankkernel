<?php
/**
 * Supported schema types.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema;

/**
 * Central allowlist for the schema payload type override.
 *
 * The payload type selects the primary entity for the page. Unknown
 * values fall back to the automatic type and are never emitted raw,
 * so later batches (commerce, media, Pro) extend this list instead
 * of adding their own validation.
 */
final class SchemaTypes {
	/**
	 * Fallback type for missing or unknown payload types.
	 */
	public const DEFAULT = 'Article';

	/**
	 * Supported type names.
	 *
	 * Batch 3 adds the commerce, media, and professional types. Batch 4
	 * adds the Pro giveaway types. Unknown values still fall back to
	 * the automatic type.
	 *
	 * @var string[]
	 */
	public const SUPPORTED = [
		'Article',
		'BlogPosting',
		'NewsArticle',
		'WebPage',
		'FAQPage',
		'HowTo',
		'Product',
		'Recipe',
		'Event',
		'Service',
		'VideoObject',
		'ImageObject',
		'Book',
		'Course',
		'JobPosting',
		'SoftwareApplication',
		'MusicRecording',
		'LocalBusiness',
		'Review',
		'Movie',
		'ClaimReview',
		'Dataset',
		'PodcastEpisode',
		'Carousel',
		'QAPage',
		'ItemList',
	];

	/**
	 * Normalize a raw payload type to a supported name or the default.
	 *
	 * @param mixed $raw Raw type value.
	 * @return string The result.
	 */
	public static function normalize( mixed $raw ): string {
		if ( ! is_string( $raw ) ) {
			return self::DEFAULT;
		}

		$clean = trim( $raw );

		if ( in_array( $clean, self::SUPPORTED, true ) ) {
			return $clean;
		}

		return self::DEFAULT;
	}

	/**
	 * Authoritative type to piece map.
	 *
	 * Each selectable type names the generator piece id that builds
	 * its node. Article, BlogPosting, and NewsArticle share the
	 * article piece. Every entry here must stay registered in the
	 * SchemaModule generator, enforced by tests.
	 *
	 * @var array<string, string>
	 */
	public const PIECES = [
		'Article'             => 'article',
		'BlogPosting'         => 'article',
		'NewsArticle'         => 'article',
		'WebPage'             => 'webpage',
		'FAQPage'             => 'faq',
		'HowTo'               => 'howto',
		'Product'             => 'product',
		'Recipe'              => 'recipe',
		'Event'               => 'event',
		'Service'             => 'service',
		'VideoObject'         => 'videoobject',
		'ImageObject'         => 'imageobject',
		'Book'                => 'book',
		'Course'              => 'course',
		'JobPosting'          => 'jobposting',
		'SoftwareApplication' => 'softwareapplication',
		'MusicRecording'      => 'musicrecording',
		'LocalBusiness'       => 'localbusiness',
		'Review'              => 'review',
		'Movie'               => 'movie',
		'ClaimReview'         => 'claimreview',
		'Dataset'             => 'dataset',
		'PodcastEpisode'      => 'podcastepisode',
		'Carousel'            => 'carousel',
		'QAPage'              => 'qapage',
		'ItemList'            => 'itemlist',
	];

	/**
	 * Types served by the article piece node.
	 *
	 * @var string[]
	 */
	public const ARTICLE_FAMILY = [ 'Article', 'BlogPosting', 'NewsArticle' ];

	/**
	 * Human labels for the admin selects.
	 *
	 * @var array<string, string>
	 */
	public const LABELS = [
		'Article'             => 'Article',
		'BlogPosting'         => 'Blog Posting',
		'NewsArticle'         => 'News Article',
		'WebPage'             => 'Web Page',
		'FAQPage'             => 'FAQ Page',
		'HowTo'               => 'How To',
		'Product'             => 'Product',
		'Recipe'              => 'Recipe',
		'Event'               => 'Event',
		'Service'             => 'Service',
		'VideoObject'         => 'Video',
		'ImageObject'         => 'Image',
		'Book'                => 'Book',
		'Course'              => 'Course',
		'JobPosting'          => 'Job Posting',
		'SoftwareApplication' => 'Software Application',
		'MusicRecording'      => 'Music Recording',
		'LocalBusiness'       => 'Local Business',
		'Review'              => 'Review',
		'Movie'               => 'Movie',
		'ClaimReview'         => 'Fact Check',
		'Dataset'             => 'Dataset',
		'PodcastEpisode'      => 'Podcast Episode',
		'Carousel'            => 'Carousel',
		'QAPage'              => 'Question and Answer Page',
		'ItemList'            => 'Item List',
	];

	/**
	 * Required payload fields per type beyond the shared rules.
	 *
	 * FAQPage and HowTo validate through their row counters instead.
	 * Types absent here fall back to the headline rule, except the
	 * page and list types which need no manual fields at all.
	 *
	 * @var array<string, string[]>
	 */
	public const REQUIRED = [
		'Event'   => [ 'headline', 'startDate', 'locationName' ],
		'Product' => [ 'headline' ],
	];

	/**
	 * Types that need no manual fields to validate.
	 *
	 * @var string[]
	 */
	public const NO_FIELDS_REQUIRED = [ 'WebPage', 'FAQPage', 'HowTo', 'Carousel', 'QAPage', 'ItemList' ];

	/**
	 * Piece id for a type, empty when unmapped.
	 *
	 * @param string $type Schema type name.
	 * @return string The result.
	 */
	public static function pieceFor( string $type ): string {
		return self::PIECES[ $type ] ?? '';
	}

	/**
	 * Human label for a type, raw name as fallback.
	 *
	 * @param string $type Schema type name.
	 * @return string The result.
	 */
	public static function label( string $type ): string {
		return self::LABELS[ $type ] ?? $type;
	}

	/**
	 * Required payload field keys for a type.
	 *
	 * @param string $type Schema type name.
	 * @return string[] The result.
	 */
	public static function requiredFields( string $type ): array {
		if ( isset( self::REQUIRED[ $type ] ) ) {
			return self::REQUIRED[ $type ];
		}

		if ( ! in_array( $type, self::SUPPORTED, true ) ) {
			return [];
		}

		if ( in_array( $type, self::NO_FIELDS_REQUIRED, true ) ) {
			return [];
		}

		return [ 'headline' ];
	}

	/**
	 * Missing field message for a type, byte stable for the admin UI.
	 *
	 * @param string $type  Schema type name.
	 * @param string $field Payload field key.
	 * @return string The result.
	 */
	public static function requiredMessage( string $type, string $field ): string {
		if ( 'headline' === $field && 'Product' === $type ) {
			return 'Product name (headline) is required for Product.';
		}

		if ( 'headline' === $field && 'Event' === $type ) {
			return 'Name (headline) is required for Event.';
		}

		if ( 'headline' === $field ) {
			return sprintf( 'Headline is required for %s.', $type );
		}

		$labels = [
			'startDate'    => 'Start date',
			'locationName' => 'Location name',
		];

		return sprintf( '%s is required for %s.', $labels[ $field ] ?? $field, $type );
	}

	/**
	 * Normalize a raw payload type, keeping empty as Automatic.
	 *
	 * Empty means the metabox Automatic choice, which resolves later
	 * through the post type default and the computed mapping. Unknown
	 * non empty values still fall back to the default type.
	 *
	 * @param mixed $raw Raw type value.
	 * @return string The result.
	 */
	public static function normalizeOrEmpty( mixed $raw ): string {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return '';
		}

		return self::normalize( $raw );
	}

	/**
	 * Automatic type fallback for a post type.
	 *
	 * Used when neither the payload type nor the per post type default
	 * setting names a supported type. Posts map to BlogPosting, pages
	 * and everything else map to Article.
	 *
	 * @param string $postType Post type slug.
	 * @return string The result.
	 */
	public static function defaultForPostType( string $postType ): string {
		if ( 'post' === trim( $postType ) ) {
			return 'BlogPosting';
		}

		return 'Article';
	}
}
