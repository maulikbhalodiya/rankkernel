<?php
/**
 * Course piece.
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
 * Course as a Course node.
 *
 * Needed when the payload type is Course and a name resolves. Reads
 * headline and description from the payload fields. The provider ref
 * points at the site organization.
 */
final class CoursePiece implements PieceInterface {
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
		return 'course';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'Course' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return $this->isComplete( $ctx );
	}

	/**
	 * Whether the type can produce a complete node.
	 *
	 * A node missing the properties Google requires for its rich result is
	 * published to every consumer and fails validation in Search Console, so
	 * an incomplete node is never emitted. The metabox warns about the same
	 * field set through SchemaTypes::requiredFields, so an author is told
	 * rather than silently losing the entity.
	 *
	 * @param Context $ctx Request context.
	 * @return bool True when every required field is present.
	 */
	private function isComplete( Context $ctx ): bool {
		return SchemaTypes::isComplete( 'Course', SchemaHelpers::resolvedFields( $ctx ) );
	}

	/**
	 * Build the Course node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'Course' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return [];
		}

		$fields = SchemaHelpers::fields( $ctx );
		$name   = SchemaHelpers::headline( $ctx, $fields );

		if ( '' === $name ) {
			return [];
		}

		if ( ! $this->isComplete( $ctx ) ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$node = [
			'@type'    => 'Course',
			'@id'      => $permalink . '#course',
			'name'     => $name,
			'provider' => [
				'@id' => SchemaHelpers::publisherId( $this->settings ),
			],
		];

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		return $node;
	}
}
