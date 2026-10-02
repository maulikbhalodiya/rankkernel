<?php
/**
 * Recipe piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;

/**
 * Recipe as a Recipe node.
 *
 * Needed when the payload type is Recipe or the fields hold a non empty
 * ingredients list, and a name resolves. Reads headline, description,
 * ingredients as a newline separated list, instructions as a newline
 * separated list, prepTime, cookTime, totalTime, and yield from the
 * payload fields. Durations accept PT prefixed values or plain minutes.
 * The author ref points at the post author, the published date comes
 * from the post date.
 */
final class RecipePiece implements PieceInterface {
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
		return 'recipe';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		$fields = SchemaHelpers::fields( $ctx );
		$type   = SchemaHelpers::effectiveType( $ctx, $this->settings );

		if ( 'Recipe' !== $type && [] === SchemaHelpers::splitLines( $fields['ingredients'] ?? '' ) ) {
			return false;
		}

		// The inference branch above lets a post with ingredients infer a
		// Recipe. Google also requires instructions and an image, so an
		// inferred or selected Recipe without them is not emitted. The
		// inference branch itself still works, it just cannot produce an
		// incomplete node.
		return $this->isComplete( $ctx );
	}

	/**
	 * Whether the type can produce a complete node.
	 *
	 * @param Context $ctx Request context.
	 * @return bool True when every required field is present.
	 */
	private function isComplete( Context $ctx ): bool {
		return SchemaTypes::isComplete( 'Recipe', SchemaHelpers::resolvedFields( $ctx ) );
	}

	/**
	 * Build the Recipe node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$fields = SchemaHelpers::fields( $ctx );
		$type   = SchemaHelpers::effectiveType( $ctx, $this->settings );

		if ( 'Recipe' !== $type && [] === SchemaHelpers::splitLines( $fields['ingredients'] ?? '' ) ) {
			return [];
		}

		$name = SchemaHelpers::headline( $ctx, $fields );

		if ( '' === $name || ! $this->isComplete( $ctx ) ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$node = [
			'@type' => 'Recipe',
			'@id'   => $permalink . '#recipe',
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

		$node['author'] = [
			'@id' => SchemaHelpers::personId( $ctx ),
		];

		$published = SchemaHelpers::postPublished( $ctx );

		if ( '' !== $published ) {
			$node['datePublished'] = $published;
		}

		foreach ( [ 'prepTime', 'cookTime', 'totalTime' ] as $key ) {
			$duration = SchemaHelpers::toDuration( $fields[ $key ] ?? '' );

			if ( '' !== $duration ) {
				$node[ $key ] = $duration;
			}
		}

		$yield = trim( $fields['yield'] ?? '' );

		if ( '' !== $yield ) {
			$node['recipeYield'] = $yield;
		}

		$ingredients = SchemaHelpers::splitLines( $fields['ingredients'] ?? '' );

		if ( [] !== $ingredients ) {
			$node['recipeIngredient'] = $ingredients;
		}

		$instructions = [];

		foreach ( SchemaHelpers::splitLines( $fields['instructions'] ?? '' ) as $line ) {
			$instructions[] = [
				'@type' => 'HowToStep',
				'text'  => $line,
			];
		}

		if ( [] !== $instructions ) {
			$node['recipeInstructions'] = $instructions;
		}

		return $node;
	}
}
