<?php
/**
 * Movie piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * Movie as a Movie node.
 *
 * Needed when the payload type is Movie and a name resolves. Reads
 * headline, description, dateCreated, director, ratingValue, and
 * reviewCount from the payload fields. The director maps to a Person
 * with a Director job title, the date is dropped when unparseable,
 * and the aggregate rating appears only when both the value and the
 * count are valid.
 */
final class MoviePiece implements PieceInterface {
    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'movie';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('Movie' !== SchemaHelpers::payloadType($ctx)) {
            return false;
        }

        return '' !== SchemaHelpers::headline($ctx, SchemaHelpers::fields($ctx));
    }

    /**
     * Build the Movie node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('Movie' !== SchemaHelpers::payloadType($ctx)) {
            return [];
        }

        $fields = SchemaHelpers::fields($ctx);
        $name   = SchemaHelpers::headline($ctx, $fields);

        if ('' === $name) {
            return [];
        }

        $permalink = $ctx->permalink();

        if ('' === $permalink) {
            return [];
        }

        $node = [
            '@type' => 'Movie',
            '@id'   => $permalink . '#movie',
            'name'  => $name,
        ];

        $description = SchemaHelpers::description($ctx, $fields);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $created = SchemaHelpers::normalizeDate($fields['dateCreated'] ?? '');

        if ('' !== $created) {
            $node['dateCreated'] = $created;
        }

        $director = trim($fields['director'] ?? '');

        if ('' !== $director) {
            $node['director'] = [
                '@type'    => 'Person',
                'name'     => $director,
                'jobTitle' => 'Director',
            ];
        }

        $rating = SchemaHelpers::aggregateRating($fields);

        if ([] !== $rating) {
            $node['aggregateRating'] = $rating;
        }

        $image = $ctx->ogImage();

        if ('' !== $image) {
            $node['image'] = $image;
        }

        return $node;
    }
}
