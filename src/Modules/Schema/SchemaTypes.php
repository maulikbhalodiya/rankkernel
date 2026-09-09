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
     */
    public static function normalize( mixed $raw ): string {
        if (! is_string($raw)) {
            return self::DEFAULT;
        }

        $clean = trim($raw);

        if (in_array($clean, self::SUPPORTED, true)) {
            return $clean;
        }

        return self::DEFAULT;
    }

    /**
     * Automatic type fallback for a post type.
     *
     * Used when neither the payload type nor the per post type default
     * setting names a supported type. Posts map to BlogPosting, pages
     * and everything else map to Article.
     *
     * @param string $postType Post type slug.
     */
    public static function defaultForPostType( string $postType ): string {
        if ('post' === trim($postType)) {
            return 'BlogPosting';
        }

        return 'Article';
    }
}
