<?php
/**
 * Product piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * Product as a Product node.
 *
 * Needed when the payload type is Product and a name resolves. Reads
 * headline, description, price, priceCurrency, availability, sku,
 * ratingValue, and reviewCount from the payload fields, with WooCommerce
 * values filling the gaps through the readWooFields seam. Payload field
 * overrides always win over Woo values. Offers appear only with a numeric
 * price, currency is omitted unless it is a 3 letter code, availability
 * defaults to InStock, and the aggregate rating appears only when both
 * the value and the count are valid.
 */
class ProductPiece implements PieceInterface {
    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'product';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('Product' !== SchemaHelpers::payloadType($ctx)) {
            return false;
        }

        return '' !== SchemaHelpers::headline($ctx, SchemaHelpers::fields($ctx));
    }

    /**
     * Build the Product node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('Product' !== SchemaHelpers::payloadType($ctx)) {
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

        $woo = $this->isWooCommerceAvailable() ? $this->readWooFields($ctx->queriedId()) : [];

        $node = [
            '@type' => 'Product',
            '@id'   => $permalink . '#product',
            'name'  => $name,
        ];

        $description = SchemaHelpers::description($ctx, $fields);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $image = $ctx->ogImage();

        if ('' !== $image) {
            $node['image'] = $image;
        }

        $sku = trim($fields['sku'] ?? $woo['sku'] ?? '');

        if ('' !== $sku) {
            $node['sku'] = $sku;
        }

        $offers = $this->offers($fields, $woo);

        if ([] !== $offers) {
            $node['offers'] = $offers;
        }

        $rating = SchemaHelpers::aggregateRating($this->ratingFields($fields, $woo));

        if ([] !== $rating) {
            $node['aggregateRating'] = $rating;
        }

        return $node;
    }

    /**
     * Whether the WooCommerce path applies on this request.
     *
     * Isolated so tests can force the path through a subclass without
     * loading WooCommerce. Production returns true only when the
     * WooCommerce class is already loaded.
     */
    protected function isWooCommerceAvailable(): bool {
        return class_exists('WooCommerce', false);
    }

    /**
     * Read product facts from the WooCommerce product, guarded.
     *
     * Returns a string map with any of price, currency, availability,
     * sku, ratingValue, and reviewCount. Every lookup is guarded by
     * function and method checks, an absent or unreadable product
     * yields an empty array. Test doubles override this method to
     * feed canned values through the seam.
     *
     * @param int $postId Current post id.
     * @return array<string, string>
     */
    protected function readWooFields( int $postId ): array {
        return [];
    }

    /**
     * Offers block, payload fields win over Woo values.
     *
     * @param array<string, string> $fields Manual overrides.
     * @param array<string, string> $woo    WooCommerce values.
     * @return array<string, mixed>
     */
    private function offers( array $fields, array $woo ): array {
        $price = SchemaHelpers::priceString($fields['price'] ?? $woo['price'] ?? '');

        if ('' === $price) {
            return [];
        }

        $offers = [
            '@type'        => 'Offer',
            'price'        => $price,
            'availability' => SchemaHelpers::availabilityUrl($fields['availability'] ?? $woo['availability'] ?? ''),
        ];

        $currency = SchemaHelpers::currency($fields['priceCurrency'] ?? $woo['currency'] ?? '');

        if ('' !== $currency) {
            $offers['priceCurrency'] = $currency;
        }

        return $offers;
    }

    /**
     * Rating fields, payload values win over Woo values.
     *
     * @param array<string, string> $fields Manual overrides.
     * @param array<string, string> $woo    WooCommerce values.
     * @return array<string, string>
     */
    private function ratingFields( array $fields, array $woo ): array {
        return [
            'ratingValue' => $fields['ratingValue'] ?? $woo['ratingValue'] ?? '',
            'reviewCount' => $fields['reviewCount'] ?? $woo['reviewCount'] ?? '',
        ];
    }
}
