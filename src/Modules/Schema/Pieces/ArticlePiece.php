<?php
/**
 * Article piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;

/**
 * Singular post as a BlogPosting or Article node.
 *
 * Needed on singular posts of type post and any other public post type
 * that is not page, attachments never, when the effective primary
 * type belongs to the article family or to the page level companions
 * FAQPage, HowTo, and QAPage. Every other primary type owns its own
 * piece, so the graph never carries two competing primaries for one
 * page. The node type resolves in order: payload type when supported,
 * then the per post type default setting (schema_default_{post_type}),
 * then the Automatic mapping fallback (post maps to BlogPosting,
 * everything else to Article).
 *
 * Headline choice: payload title literal (tokens resolved through the
 * shared Context memo), else the raw post title. The settings title
 * template is intentionally not applied, templates add site suffixes
 * that do not belong in a headline.
 */
final class ArticlePiece implements PieceInterface {
    /**
     * Settings store.
     */
    private readonly SettingsStore $settings;

    /**
     * Constructor.
     *
     * @param SettingsStore|null $settings Optional settings store.
     */
    public function __construct( ?SettingsStore $settings = null ) {
        $this->settings = $settings ?? new SettingsStore();
    }
    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'article';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('post' !== $ctx->queriedType()) {
            return false;
        }

        $postId = $ctx->queriedId();

        if ($postId <= 0) {
            return false;
        }

        $postType = function_exists('get_post_type') ? get_post_type($postId) : 'post';

        if (! is_string($postType) || '' === $postType) {
            $postType = 'post';
        }

        if (in_array($postType, [ 'page', 'attachment' ], true)) {
            return false;
        }

        $effective = SchemaHelpers::effectiveType($ctx, $this->settings);

        if (in_array($effective, SchemaTypes::ARTICLE_FAMILY, true)) {
            return true;
        }

        return in_array($effective, [ 'FAQPage', 'HowTo', 'QAPage' ], true);
    }

    /**
     * Build the Article node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        $postId   = $ctx->queriedId();
        $postType = function_exists('get_post_type') ? get_post_type($postId) : 'post';

        if (! is_string($postType) || '' === $postType) {
            $postType = 'post';
        }

        $permalink = $ctx->permalink();

        if ('' === $permalink) {
            return [];
        }

        $node = [
            '@type'    => $this->resolveType($ctx, $postType),
            '@id'      => $permalink . '#article',
            'headline' => $this->headline($ctx),
        ];

        $description = SchemaHelpers::description($ctx, SchemaHelpers::fields($ctx));

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $node['author'] = [
            '@id' => SchemaHelpers::personId($ctx),
        ];

        $node['publisher'] = [
            '@id' => SchemaHelpers::publisherId($this->settings),
        ];

        $published = $this->postDate($postId, false);
        $modified  = $this->postDate($postId, true);

        if ('' !== $published) {
            $node['datePublished'] = $published;
        }

        if ('' !== $modified) {
            $node['dateModified'] = $modified;
        }

        $image = $ctx->ogImage();

        if ('' !== $image) {
            $node['image'] = $image;
        }

        $node['mainEntityOfPage'] = [
            '@id' => $permalink . '#webpage',
        ];

        return $node;
    }

    /**
     * Node type, payload first, then the per post type default, then mapping.
     *
     * Companion page types (FAQPage, HowTo, QAPage) fall back to the
     * mapping default, their own pieces carry the page type node.
     *
     * @param Context $ctx      Request context.
     * @param string  $postType Post type slug.
     */
    private function resolveType( Context $ctx, string $postType ): string {
        $effective = SchemaHelpers::effectiveType($ctx, $this->settings);

        if (in_array($effective, SchemaTypes::ARTICLE_FAMILY, true)) {
            return $effective;
        }

        return SchemaTypes::defaultForPostType($postType);
    }

    /**
     * Headline, payload title literal first, then the raw post title.
     *
     * @param Context $ctx Request context.
     */
    private function headline( Context $ctx ): string {
        $meta         = $ctx->meta();
        $payloadTitle = isset($meta['title']) ? trim((string) $meta['title']) : '';

        if ('' !== $payloadTitle) {
            if (1 === preg_match('/%%[a-z_]+%%/', $payloadTitle)) {
                $resolved = $ctx->resolved('schema_headline', $payloadTitle);

                if ('' !== trim($resolved)) {
                    return trim($resolved);
                }
            } else {
                return $payloadTitle;
            }
        }

        return trim($ctx->title());
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
