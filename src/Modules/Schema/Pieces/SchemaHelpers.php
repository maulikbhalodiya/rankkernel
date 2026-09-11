<?php
/**
 * Shared readers for the batch 3 schema pieces.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;

/**
 * Small, pure helpers shared by the commerce, media, and professional pieces.
 *
 * Every reader follows the payload contract. Manual field overrides win,
 * post derived Context values fill the gaps, and invalid values are
 * dropped instead of emitted raw. Pieces never pre encode output, the
 * Generator encodes the graph once.
 */
final class SchemaHelpers {
    /**
     * String map of manual field overrides, defensive on both shapes.
     *
     * Fresh rows hold an empty schema list, saved rows hold the object
     * shape. Non scalar values are dropped, scalars are cast to string.
     *
     * @param Context $ctx Request context.
     * @return array<string, string>
     */
    public static function fields( Context $ctx ): array {
        $meta = $ctx->meta();

        $schema = $meta['schema'] ?? [];

        if (! is_array($schema)) {
            return [];
        }

        $fields = $schema['fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        $out = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key) || '' === trim($key)) {
                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $out[ $key ] = (string) $value;
        }

        return $out;
    }

    /**
     * Raw payload type string, empty when unset.
     *
     * @param Context $ctx Request context.
     */
    public static function payloadType( Context $ctx ): string {
        $meta = $ctx->meta();

        $schema = $meta['schema'] ?? [];

        if (! is_array($schema)) {
            return '';
        }

        $type = $schema['type'] ?? '';

        if (! is_string($type)) {
            return '';
        }

        return trim($type);
    }

    /**
     * Headline, manual override first, then the raw post title.
     *
     * @param Context               $ctx    Request context.
     * @param array<string, string> $fields Manual overrides.
     */
    public static function headline( Context $ctx, array $fields ): string {
        $override = trim($fields['headline'] ?? '');

        if ('' !== $override) {
            return $override;
        }

        return trim($ctx->title());
    }

    /**
     * Description, manual override first, then the excerpt fallback.
     *
     * @param Context               $ctx    Request context.
     * @param array<string, string> $fields Manual overrides.
     */
    public static function description( Context $ctx, array $fields ): string {
        $override = trim($fields['description'] ?? '');

        if ('' !== $override) {
            return $override;
        }

        return trim($ctx->excerpt());
    }

    /**
     * Normalize a free form date to W3C, dropped when unparseable.
     *
     * Any strtotime parseable string is accepted. Invalid strings
     * return an empty string and are never emitted raw.
     *
     * @param string $raw Raw date value.
     */
    public static function normalizeDate( string $raw ): string {
        $clean = trim($raw);

        if ('' === $clean) {
            return '';
        }

        $stamp = strtotime($clean);

        if (false === $stamp) {
            return '';
        }

        if (function_exists('mysql2date')) {
            $w3c = mysql2date(DATE_W3C, (string) gmdate('Y-m-d H:i:s', $stamp));

            if (is_string($w3c) && '' !== $w3c) {
                return $w3c;
            }
        }

        $fallback = gmdate(DATE_W3C, $stamp);

        return $fallback;
    }

    /**
     * Normalize a duration to ISO 8601.
     *
     * A PT prefixed value passes through when it matches the hours,
     * minutes, and seconds shape. A plain integer is read as minutes
     * and converted to PT minutes. Anything else is dropped.
     *
     * @param string $raw Raw duration value.
     */
    public static function toDuration( string $raw ): string {
        $clean = trim($raw);

        if ('' === $clean) {
            return '';
        }

        if (1 === preg_match('/^PT(?:[0-9]+H)?(?:[0-9]+M)?(?:[0-9]+S)?$/', $clean) && 'PT' !== $clean) {
            return $clean;
        }

        if (ctype_digit($clean)) {
            return 'PT' . $clean . 'M';
        }

        return '';
    }

    /**
     * Normalize a currency to uppercase 3 letter code, else empty.
     *
     * @param string $raw Raw currency value.
     */
    public static function currency( string $raw ): string {
        $clean = strtoupper(trim($raw));

        if (1 === preg_match('/^[A-Z]{3}$/', $clean)) {
            return $clean;
        }

        return '';
    }

    /**
     * Normalize a price to string, empty when not numeric.
     *
     * @param mixed $raw Raw price value.
     */
    public static function priceString( mixed $raw ): string {
        $text = is_string($raw) ? trim($raw) : (string) $raw;

        if ('' === $text || ! is_numeric($text)) {
            return '';
        }

        return $text;
    }

    /**
     * Aggregate rating, only when value and count are both valid.
     *
     * The value must be numeric within 0 to 5, the count must be a
     * positive absint. Anything else yields an empty array.
     *
     * @param array<string, string> $fields Manual overrides.
     * @return array<string, mixed>
     */
    public static function aggregateRating( array $fields ): array {
        $value = trim($fields['ratingValue'] ?? '');

        if ('' === $value || ! is_numeric($value)) {
            return [];
        }

        $number = (float) $value;

        if ($number < 0 || $number > 5) {
            return [];
        }

        $countRaw = trim($fields['reviewCount'] ?? '');

        if ('' === $countRaw) {
            return [];
        }

        $count = function_exists('absint') ? absint($countRaw) : abs((int) $countRaw);

        if ($count <= 0) {
            return [];
        }

        return [
            '@type'       => 'AggregateRating',
            'ratingValue' => $value,
            'reviewCount' => $count,
        ];
    }

    /**
     * Availability flag mapped to its schema URL, defaults to InStock.
     *
     * Accepts in_stock, out_of_stock, and preorder. Missing or unknown
     * flags fall back to InStock, pieces only emit offers with a price.
     *
     * @param string $raw Raw availability flag.
     */
    public static function availabilityUrl( string $raw ): string {
        $map = [
            'in_stock'     => 'https://schema.org/InStock',
            'out_of_stock' => 'https://schema.org/OutOfStock',
            'preorder'     => 'https://schema.org/PreOrder',
        ];

        $key = strtolower(trim($raw));

        return $map[ $key ] ?? 'https://schema.org/InStock';
    }

    /**
     * Event status mapped to its schema URL, defaults to EventScheduled.
     *
     * Accepts scheduled, cancelled, postponed, moved, and rescheduled.
     *
     * @param string $raw Raw status flag.
     */
    public static function eventStatusUrl( string $raw ): string {
        $map = [
            'scheduled'   => 'https://schema.org/EventScheduled',
            'cancelled'   => 'https://schema.org/EventCancelled',
            'postponed'   => 'https://schema.org/EventPostponed',
            'moved'       => 'https://schema.org/EventMovedOnline',
            'rescheduled' => 'https://schema.org/EventRescheduled',
        ];

        $key = strtolower(trim($raw));

        return $map[ $key ] ?? 'https://schema.org/EventScheduled';
    }

    /**
     * Effective primary type for the page.
     *
     * Hierarchy: per post payload type first, then the per post type
     * default setting (schema_default_{post_type}), then the computed
     * mapping fallback (posts map to BlogPosting, everything else to
     * Article). Slugs that could never be stored stay unread, so no
     * surprise options are created or trusted.
     *
     * @param Context            $ctx      Request context.
     * @param SettingsStore|null $settings Optional settings store.
     */
    public static function effectiveType( Context $ctx, ?SettingsStore $settings = null ): string {
        $payload = self::payloadType($ctx);

        if ('' !== $payload && in_array($payload, SchemaTypes::SUPPORTED, true)) {
            return $payload;
        }

        $postType = '';
        $postId   = $ctx->queriedId();

        if ($postId > 0 && function_exists('get_post_type')) {
            $slug = get_post_type($postId);

            if (is_string($slug)) {
                $postType = trim($slug);
            }
        }

        if ('' !== $postType) {
            $store   = $settings ?? new SettingsStore();
            $default = trim((string) $store->get('schema_default_' . $postType, ''));

            if (in_array($default, SchemaTypes::SUPPORTED, true)) {
                return $default;
            }
        }

        return SchemaTypes::defaultForPostType($postType);
    }

    /**
     * Whether the site represents a person instead of an organization.
     *
     * @param SettingsStore|null $settings Optional settings store.
     */
    public static function representsPerson( ?SettingsStore $settings = null ): bool {
        $store = $settings ?? new SettingsStore();

        return 'person' === trim((string) $store->get('site_represents', 'organization'));
    }

    /**
     * Publisher @id for publisher refs, person aware.
     *
     * Organization mode points at the organization node, person mode
     * points at the publisher person node. Refs built through this
     * helper always match the node OrganizationPiece emits.
     *
     * @param SettingsStore|null $settings Optional settings store.
     */
    public static function publisherId( ?SettingsStore $settings = null ): string {
        if (self::representsPerson($settings)) {
            return self::homeRoot() . '#publisher';
        }

        return self::orgId();
    }

    /**
     * Site level node @id for a fragment, e.g. website or organization.
     *
     * @param string $fragment Fragment without the hash.
     */
    public static function siteId( string $fragment ): string {
        return self::homeRoot() . '#' . ltrim(trim($fragment), '#');
    }

    /**
     * Page level node @id for a fragment, e.g. webpage or article.
     *
     * Uses the canonical URL when set, else the permalink, else the
     * home URL. Empty when no base resolves, callers drop the node.
     *
     * @param Context $ctx      Request context.
     * @param string  $fragment Fragment without the hash.
     */
    public static function pageId( Context $ctx, string $fragment ): string {
        $base = self::pageBase($ctx);

        if ('' === $base) {
            return '';
        }

        return $base . '#' . ltrim(trim($fragment), '#');
    }

    /**
     * Canonical or permalink base for page level @ids and urls.
     *
     * @param Context $ctx Request context.
     */
    public static function pageBase( Context $ctx ): string {
        $meta = $ctx->meta();

        if (isset($meta['canonical']) && is_string($meta['canonical']) && '' !== trim($meta['canonical'])) {
            return trim($meta['canonical']);
        }

        $permalink = $ctx->permalink();

        if ('' !== $permalink) {
            return $permalink;
        }

        if (function_exists('home_url')) {
            $home = (string) home_url('/');

            if ('' !== trim($home)) {
                return $home;
            }
        }

        return '';
    }

    /**
     * Postal address block, empty when no address data resolves.
     *
     * @param array<string, string> $fields Manual overrides.
     * @return array<string, mixed>
     */
    public static function postalAddress( array $fields ): array {
        $address = [
            '@type' => 'PostalAddress',
        ];

        foreach (
            [
                'streetAddress'   => 'streetAddress',
                'addressLocality' => 'addressLocality',
                'addressRegion'   => 'addressRegion',
                'postalCode'      => 'postalCode',
                'addressCountry'  => 'addressCountry',
            ] as $field => $key
        ) {
            $value = trim($fields[ $field ] ?? '');

            if ('' !== $value) {
                $address[ $key ] = $value;
            }
        }

        if (count($address) <= 1) {
            return [];
        }

        return $address;
    }

    /**
     * Validated http or https URL, empty when anything else appears.
     *
     * @param string $raw Raw URL value.
     */
    public static function httpUrl( string $raw ): string {
        $clean = trim($raw);

        if ('' === $clean) {
            return '';
        }

        if (function_exists('esc_url_raw')) {
            $clean = esc_url_raw($clean);

            if (! is_string($clean)) {
                return '';
            }

            $clean = trim($clean);

            if ('' === $clean) {
                return '';
            }
        }

        $parts = parse_url($clean);

        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ( $parts['scheme'] ?? '' ));

        if (! in_array($scheme, [ 'http', 'https' ], true)) {
            return '';
        }

        if ('' === trim((string) ( $parts['host'] ?? '' ))) {
            return '';
        }

        return $clean;
    }

    /**
     * Home root with trailing slash.
     */
    public static function homeRoot(): string {
        $home = function_exists('home_url') ? (string) home_url('/') : '';

        if (function_exists('trailingslashit')) {
            return trailingslashit($home);
        }

        return rtrim($home, '/') . '/';
    }

    /**
     * Organization @id for provider and publisher refs.
     */
    public static function orgId(): string {
        return self::homeRoot() . '#organization';
    }

    /**
     * Person @id for the post author, with permalink fallback.
     *
     * @param Context $ctx Request context.
     */
    public static function personId( Context $ctx ): string {
        $authorId = 0;

        if (function_exists('get_post_field')) {
            $author = get_post_field('post_author', $ctx->queriedId());

            if (is_numeric($author)) {
                $authorId = (int) $author;
            }
        }

        if ($authorId > 0 && function_exists('get_author_posts_url')) {
            $url = get_author_posts_url($authorId);

            if (is_string($url) && '' !== trim($url)) {
                return trim($url) . '#author';
            }
        }

        return $ctx->permalink() . '#author';
    }

    /**
     * Post published date in W3C format, empty when unavailable.
     *
     * @param Context $ctx Request context.
     */
    public static function postPublished( Context $ctx ): string {
        if (! function_exists('get_the_date')) {
            return '';
        }

        $date = get_the_date('c', $ctx->queriedId());

        return is_string($date) ? $date : '';
    }

    /**
     * Split a newline separated field into trimmed non empty rows.
     *
     * @param string $raw Raw field value.
     * @return string[]
     */
    public static function splitLines( string $raw ): array {
        $rows = preg_split('/\r\n|\r|\n/', $raw);

        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $clean = trim((string) $row);

            if ('' !== $clean) {
                $out[] = $clean;
            }
        }

        return array_values($out);
    }

    /**
     * Clean an ISBN to digits and X only, empty when anything else appears.
     *
     * Hyphens and spaces are stripped before validation.
     *
     * @param string $raw Raw ISBN value.
     */
    public static function cleanIsbn( string $raw ): string {
        $clean = str_replace([ '-', ' ' ], '', trim($raw));

        if ('' === $clean) {
            return '';
        }

        if (1 !== preg_match('/^[0-9Xx]+$/', $clean)) {
            return '';
        }

        return $clean;
    }
}
