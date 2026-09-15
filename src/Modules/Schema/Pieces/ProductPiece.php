<?php
/**
 * Product piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Settings\SettingsStore;

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
	 * Settings store.
	 *
	 * @var SettingsStore
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
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'product';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'Product' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return '' !== SchemaHelpers::headline( $ctx, SchemaHelpers::fields( $ctx ) );
	}

	/**
	 * Build the Product node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, string>
	 */
	public function build( Context $ctx ): array {
		if ( 'Product' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );
		$name   = SchemaHelpers::headline( $ctx, $fields );

		if ( '' === $name ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$woo = $this->isWooCommerceAvailable() ? $this->readWooFields( $ctx->queriedId() ) : [];

		$node = [
			'@type' => 'Product',
			'@id'   => $permalink . '#product',
			'name'  => $name,
		];

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$image = $ctx->ogImage();

		if ( '' !== $image ) {
			$node['image'] = $image;
		}

		$sku = trim( $fields['sku'] ?? $woo['sku'] ?? '' );

		if ( '' !== $sku ) {
			$node['sku'] = $sku;
		}

		$offers = $this->offers( $fields, $woo );

		if ( [] !== $offers ) {
			$node['offers'] = $offers;
		}

		$rating = SchemaHelpers::aggregateRating( $this->ratingFields( $fields, $woo ) );

		if ( [] !== $rating ) {
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
	 *
	 * @return bool The result.
	 */
	protected function isWooCommerceAvailable(): bool {
		return class_exists( 'WooCommerce', false );
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
		if ( $postId <= 0 ) {
			return [];
		}

		return $this->wooFields( $postId, 'wc_get_product', 'get_woocommerce_currency' );
	}

	/**
	 * Pull guarded values through the Woo function names.
	 *
	 * The names travel as plain strings so the optional dependency
	 * stays invisible to static analysis when Woo is absent. Every
	 * lookup keeps its function and method guard pair.
	 *
	 * @param int    $postId     Current post id.
	 * @param string $factory    Product factory function name.
	 * @param string $currencyFn Currency function name.
	 * @return array<string, string>
	 */
	private function wooFields( int $postId, string $factory, string $currencyFn ): array {
		if ( ! function_exists( $factory ) || ! is_callable( $factory ) ) {
			return [];
		}

		$product = call_user_func( $factory, $postId );

		if ( ! is_object( $product ) ) {
			return [];
		}

		$out = [];

		$price = $this->productMethod( $product, 'get_price' );

		if ( is_numeric( $price ) && '' !== trim( (string) $price ) ) {
			$out['price'] = trim( (string) $price );
		}

		if ( function_exists( $currencyFn ) && is_callable( $currencyFn ) ) {
			$currency = call_user_func( $currencyFn );

			if ( ( is_string( $currency ) || is_numeric( $currency ) ) && '' !== trim( (string) $currency ) ) {
				$out['currency'] = trim( (string) $currency );
			}
		}

		$stock = $this->productMethod( $product, 'is_in_stock' );

		if ( is_bool( $stock ) ) {
			$out['availability'] = $stock ? 'in_stock' : 'out_of_stock';
		}

		$sku = $this->productMethod( $product, 'get_sku' );

		if ( is_string( $sku ) && '' !== trim( $sku ) ) {
			$out['sku'] = trim( $sku );
		}

		$average = $this->productMethod( $product, 'get_average_rating' );

		if ( is_numeric( $average ) && '' !== trim( (string) $average ) ) {
			$out['ratingValue'] = trim( (string) $average );
		}

		$count = $this->productMethod( $product, 'get_rating_count' );

		if ( is_numeric( $count ) ) {
			$out['reviewCount'] = trim( (string) $count );
		}

		return $out;
	}

	/**
	 * Call a product method through the guard pair, null when unavailable.
	 *
	 * String callables keep the optional Woo dependency invisible to
	 * static analysis, so this file stays clean with Woo absent.
	 *
	 * @param object $product Product object.
	 * @param string $method  Method name.
	 * @return mixed The result.
	 */
	private function productMethod( object $product, string $method ): mixed {
		if ( ! method_exists( $product, $method ) ) {
			return null;
		}

		$callable = [ $product, $method ];

		if ( ! is_callable( $callable ) ) {
			return null;
		}

		return call_user_func( $callable );
	}

	/**
	 * Offers block, payload fields win over Woo values.
	 *
	 * @param array<string, string> $fields Manual overrides.
	 * @param array<string, string> $woo    WooCommerce values.
	 * @return array<string, mixed>
	 */
	private function offers( array $fields, array $woo ): array {
		$price = SchemaHelpers::priceString( $fields['price'] ?? $woo['price'] ?? '' );

		if ( '' === $price ) {
			return [];
		}

		$offers = [
			'@type'        => 'Offer',
			'price'        => $price,
			'availability' => SchemaHelpers::availabilityUrl( $fields['availability'] ?? $woo['availability'] ?? '' ),
		];

		$currency = SchemaHelpers::currency( $fields['priceCurrency'] ?? $woo['currency'] ?? '' );

		if ( '' !== $currency ) {
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
