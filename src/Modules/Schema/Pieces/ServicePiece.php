<?php
/**
 * Service piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * Service as a Service node.
 *
 * Needed when the payload type is Service and a name resolves. Reads
 * headline, description, areaServed, price, and priceCurrency from the
 * payload fields. The provider ref points at the site organization,
 * offers appear only with a numeric price.
 */
final class ServicePiece implements PieceInterface {
    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'service';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('Service' !== SchemaHelpers::payloadType($ctx)) {
            return false;
        }

        return '' !== SchemaHelpers::headline($ctx, SchemaHelpers::fields($ctx));
    }

    /**
     * Build the Service node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('Service' !== SchemaHelpers::payloadType($ctx)) {
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
            '@type' => 'Service',
            '@id'   => $permalink . '#service',
            'name'  => $name,
        ];

        $description = SchemaHelpers::description($ctx, $fields);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $node['provider'] = [
            '@id' => SchemaHelpers::orgId(),
        ];

        $area = trim($fields['areaServed'] ?? '');

        if ('' !== $area) {
            $node['areaServed'] = $area;
        }

        $price = SchemaHelpers::priceString($fields['price'] ?? '');

        if ('' !== $price) {
            $offers = [
                '@type' => 'Offer',
                'price' => $price,
            ];

            $currency = SchemaHelpers::currency($fields['priceCurrency'] ?? '');

            if ('' !== $currency) {
                $offers['priceCurrency'] = $currency;
            }

            $node['offers'] = $offers;
        }

        return $node;
    }
}
