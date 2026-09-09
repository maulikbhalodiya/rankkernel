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

/**
 * Singular post as a BlogPosting or Article node.
 *
 * Needed on singular posts of type post and any other public post type
 * that is not page, attachments never. Post type post maps to BlogPosting,
 * everything else maps to Article. The per post type default type setting
 * (article_default_type) lands in a later task.
 *
 * Headline choice: payload title literal (tokens resolved through the
 * shared Context memo), else the raw post title. The settings title
 * template is intentionally not applied, templates add site suffixes
 * that do not belong in a headline.
 */
final class ArticlePiece implements PieceInterface {
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

        return true;
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
            '@type'    => 'post' === $postType ? 'BlogPosting' : 'Article',
            '@id'      => $permalink . '#article',
            'headline' => $this->headline($ctx),
        ];

        $description = $ctx->excerpt();

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $node['author'] = [
            '@id' => $this->personId($ctx),
        ];

        $node['publisher'] = [
            '@id' => self::homeRoot() . '#organization',
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
     * Person @id for the post author, with permalink fallback.
     *
     * @param Context $ctx Request context.
     */
    private function personId( Context $ctx ): string {
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

    /**
     * Home root with trailing slash.
     */
    private static function homeRoot(): string {
        $home = function_exists('home_url') ? (string) home_url('/') : '';

        if (function_exists('trailingslashit')) {
            return trailingslashit($home);
        }

        return rtrim($home, '/') . '/';
    }
}
