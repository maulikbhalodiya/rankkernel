<?php
/**
 * WebPage piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Settings\SettingsStore;

/**
 * Current page as a WebPage node.
 *
 * Needed on singular, home, and archives, never on search, 404, feed, or
 * preview. The SearchResultsPage type is documented for the search results
 * page but not emitted while search stays excluded from isNeeded.
 */
final class WebpagePiece implements PieceInterface {
    /**
     * Constructor.
     *
     * @param SettingsStore $settings Settings store.
     */
    public function __construct( private readonly SettingsStore $settings ) {
    }

    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'webpage';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        return in_array($ctx->queriedType(), [ 'post', 'home', 'term', 'archive' ], true);
    }

    /**
     * Build the WebPage node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        $type = $ctx->queriedType();
        $base = $this->pageBase($ctx);

        if ('' === $base) {
            return [];
        }

        $node = [
            '@type' => $this->pageType($type),
            '@id'   => $base . '#webpage',
            'name'  => $this->resolvedName($ctx),
            'url'   => $base,
        ];

        $description = SchemaHelpers::description($ctx, SchemaHelpers::fields($ctx));

        if ('' !== $description) {
            $node['description'] = $description;
        }

        if ('post' === $type && $ctx->queriedId() > 0) {
            $published = $this->postDate($ctx->queriedId(), false);
            $modified  = $this->postDate($ctx->queriedId(), true);

            if ('' !== $published) {
                $node['datePublished'] = $published;
            }

            if ('' !== $modified) {
                $node['dateModified'] = $modified;
            }
        }

        $node['breadcrumb'] = [
            '@id' => $base . '#breadcrumb',
        ];

        if (function_exists('get_locale')) {
            $locale = (string) get_locale();

            if ('' !== $locale) {
                $node['inLanguage'] = $locale;
            }
        }

        $fields = SchemaHelpers::fields($ctx);

        $speakable = $this->speakableSelectors($fields);

        if ([] !== $speakable) {
            $node['speakable'] = [
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => $speakable,
            ];
        }

        $about = self::namedThings($fields['about'] ?? '');

        if ([] !== $about) {
            $node['about'] = $about;
        }

        $mentions = self::namedThings($fields['mentions'] ?? '');

        if ([] !== $mentions) {
            $node['mentions'] = $mentions;
        }

        return $node;
    }

    /**
     * Speakable selectors, newline separated string or defensive array.
     *
     * Speakable stays a WebPage property per the spec, never a graph
     * node of its own. Selectors split on newlines so comma lists
     * inside one selector survive intact. Caps at 20 entries.
     *
     * @param array<string, mixed> $fields Manual overrides.
     * @return string[]
     */
    private function speakableSelectors( array $fields ): array {
        $raw = $fields['speakable'] ?? '';

        if (is_array($raw)) {
            $rows = $raw;
        } else {
            $rows = preg_split('/\r\n|\r|\n/', (string) $raw);

            if (! is_array($rows)) {
                return [];
            }
        }

        $out = [];

        foreach ($rows as $row) {
            if (count($out) >= 20) {
                break;
            }

            if (! is_scalar($row)) {
                continue;
            }

            $clean = trim((string) $row);

            if ('' !== $clean) {
                $out[] = $clean;
            }
        }

        return array_values($out);
    }

    /**
     * Comma separated names as Thing entries, capped at 20.
     *
     * @param string $raw Raw field value.
     * @return array<int, array<string, string>>
     */
    private static function namedThings( string $raw ): array {
        $parts = explode(',', $raw);
        $out   = [];

        foreach ($parts as $part) {
            if (count($out) >= 20) {
                break;
            }

            $clean = trim($part);

            if ('' !== $clean) {
                $out[] = [
                    '@type' => 'Thing',
                    'name'  => $clean,
                ];
            }
        }

        return $out;
    }

    /**
     * Page type by context.
     *
     * @param string $type Queried type.
     */
    private function pageType( string $type ): string {
        if (in_array($type, [ 'term', 'archive' ], true)) {
            return 'CollectionPage';
        }

        if ($this->isFrontPage()) {
            return 'AboutPage';
        }

        return 'WebPage';
    }

    /**
     * Whether the current context is the front page.
     */
    private function isFrontPage(): bool {
        if (function_exists('is_front_page') && is_front_page()) {
            return true;
        }

        return false;
    }

    /**
     * Canonical or permalink base for the @id and url.
     *
     * @param Context $ctx Request context.
     */
    private function pageBase( Context $ctx ): string {
        $meta = $ctx->meta();

        if (isset($meta['canonical']) && is_string($meta['canonical']) && '' !== trim($meta['canonical'])) {
            return trim($meta['canonical']);
        }

        $permalink = $ctx->permalink();

        if ('' !== $permalink) {
            return $permalink;
        }

        if (function_exists('home_url')) {
            return (string) home_url('/');
        }

        return '';
    }

    /**
     * Resolved name, payload title literal, then settings template, then raw title.
     *
     * Mirrors the HeadRenderer title chain through the shared Context memo.
     *
     * @param Context $ctx Request context.
     */
    private function resolvedName( Context $ctx ): string {
        $meta         = $ctx->meta();
        $payloadTitle = isset($meta['title']) ? trim((string) $meta['title']) : '';

        if ('' !== $payloadTitle) {
            if (1 === preg_match('/%%[a-z_]+%%/', $payloadTitle)) {
                $resolved = $ctx->resolved('schema_webpage_name', $payloadTitle);

                if ('' !== trim($resolved)) {
                    return trim($resolved);
                }
            } else {
                return $payloadTitle;
            }
        }

        $template = trim((string) $this->settings->get('title_template', ''));

        if ('' !== $template) {
            $resolved = $ctx->resolved('schema_webpage_name', $template);

            if ('' !== trim($resolved)) {
                return trim($resolved);
            }
        }

        $title = trim($ctx->title());

        if ('' !== $title) {
            return $title;
        }

        return $ctx->siteName();
    }

    /**
     * Post published or modified date in W3C format.
     *
     * @param int  $postId   Post id.
     * @param bool $modified Whether to read the modified date.
     */
    private function postDate( int $postId, bool $modified ): string {
        if ($modified) {
            if (function_exists('get_the_modified_date')) {
                $date = get_the_modified_date('c', $postId);

                if (is_string($date)) {
                    return $date;
                }
            }

            return '';
        }

        if (function_exists('get_the_date')) {
            $date = get_the_date('c', $postId);

            if (is_string($date)) {
                return $date;
            }
        }

        return '';
    }
}
