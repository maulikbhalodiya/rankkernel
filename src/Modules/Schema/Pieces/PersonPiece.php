<?php
/**
 * Person piece.
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
 * Content author as a Person node.
 *
 * Needed on singular posts with an author and on author archives. On
 * author archives the Person is the main entity. The manual author
 * field overrides the profile display name when set. The sameAs list
 * stays absent until user profile prefs land in a later task, empty
 * values are never emitted. The schema_author setting turns the node
 * off entirely.
 */
final class PersonPiece implements PieceInterface {
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
		return 'person';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( ! (bool) $this->settings->get( 'schema_author', true ) ) {
			return false;
		}

		$type = $ctx->queriedType();

		if ( 'post' === $type ) {
			return $this->authorId( $ctx ) > 0;
		}

		if ( 'archive' === $type && function_exists( 'is_author' ) && is_author() ) {
			return true;
		}

		return false;
	}

	/**
	 * Build the Person node.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$authorId = $this->authorId( $ctx );

		if ( $authorId <= 0 ) {
			return [];
		}

		$name = '';

		$fields = SchemaHelpers::fields( $ctx );
		$manual = trim( $fields['author'] ?? '' );

		if ( '' !== $manual ) {
			$name = $manual;
		} elseif ( function_exists( 'get_the_author_meta' ) ) {
			$meta = get_the_author_meta( 'display_name', $authorId );

			if ( is_string( $meta ) ) {
				$name = trim( $meta );
			}
		}

		$url = '';

		if ( function_exists( 'get_author_posts_url' ) ) {
			$authorUrl = get_author_posts_url( $authorId );

			if ( is_string( $authorUrl ) ) {
				$url = trim( $authorUrl );
			}
		}

		$id = '' !== $url ? $url . '#author' : $ctx->permalink() . '#author';

		if ( '#author' === $id ) {
			return [];
		}

		if ( '' === $name ) {
			return [];
		}

		$node = [
			'@type' => 'Person',
			'@id'   => $id,
			'name'  => $name,
		];

		if ( '' !== $url ) {
			$node['url'] = $url;
		}

		return $node;
	}

	/**
	 * Resolve the author id for singular posts and author archives.
	 *
	 * @param Context $ctx Request context.
	 * @return int The result.
	 */
	private function authorId( Context $ctx ): int {
		$type = $ctx->queriedType();

		if ( 'post' === $type ) {
			$postId = $ctx->queriedId();

			if ( $postId > 0 && function_exists( 'get_post_field' ) ) {
				$author = get_post_field( 'post_author', $postId );

				if ( is_numeric( $author ) && (int) $author > 0 ) {
					return (int) $author;
				}
			}

			return 0;
		}

		if ( 'archive' === $type ) {
			$id = $ctx->queriedId();

			if ( $id > 0 ) {
				return $id;
			}

			if ( function_exists( 'get_queried_object_id' ) ) {
				$objectId = (int) get_queried_object_id();

				if ( $objectId > 0 ) {
					return $objectId;
				}
			}
		}

		return 0;
	}
}
