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
        'VideoObject',
        'ImageObject',
        'Course',
        'LocalBusiness',
        'Review',
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
}
