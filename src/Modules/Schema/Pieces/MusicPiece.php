<?php
/**
 * Music recording piece.
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
 * Recording as a MusicRecording node.
 *
 * Needed when the payload type is MusicRecording and a name resolves.
 * Reads headline, description, artist, and album from the payload
 * fields. The artist maps to a MusicGroup, the album maps to a
 * MusicAlbum, both omitted when empty.
 */
final class MusicPiece implements PieceInterface {
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
		return 'musicrecording';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'MusicRecording' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
			return false;
		}

		return '' !== SchemaHelpers::headline( $ctx, SchemaHelpers::fields( $ctx ) );
	}

	/**
	 * Build the MusicRecording node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		if ( 'MusicRecording' !== SchemaHelpers::effectiveType( $ctx, $this->settings ) ) {
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

		$node = [
			'@type' => 'MusicRecording',
			'@id'   => $permalink . '#music',
			'name'  => $name,
		];

		$description = SchemaHelpers::description( $ctx, $fields );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$artist = trim( $fields['artist'] ?? '' );

		if ( '' !== $artist ) {
			$node['byArtist'] = [
				'@type' => 'MusicGroup',
				'name'  => $artist,
			];
		}

		$album = trim( $fields['album'] ?? '' );

		if ( '' !== $album ) {
			$node['inAlbum'] = [
				'@type' => 'MusicAlbum',
				'name'  => $album,
			];
		}

		return $node;
	}
}
